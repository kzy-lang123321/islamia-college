using System.Net.NetworkInformation;
using System.Security.Cryptography;
using System.Text;
using Google.Apis.Auth.OAuth2;
using Google.Apis.Drive.v3;
using Google.Apis.Services;
using Google.Apis.Upload;

namespace ICTManagementSystem.App.Services.Backup;

public interface IGoogleDriveClient
{
    Task<bool> CheckGoogleConnectivityAsync();
    Task<(bool Success, string? DriveFileId, string? Error)> UploadBackupAsync(string filePath, string sha256, string md5, long expectedSize);
}

public class GoogleDriveBackupService : IGoogleDriveClient
{
    private const string FolderName = "ICT Management System Backups";
    private const string DriveAccountHint = "aminullahameen@gmail.com";
    private readonly string _tokenStoragePath;
    private DriveService? _driveService;

    public GoogleDriveBackupService(string appDataDir)
    {
        _tokenStoragePath = Path.Combine(appDataDir, "credentials", "drive_token.dat");
        var dir = Path.GetDirectoryName(_tokenStoragePath);
        if (!string.IsNullOrEmpty(dir) && !Directory.Exists(dir))
        {
            Directory.CreateDirectory(dir);
        }
    }

    public async Task<bool> CheckGoogleConnectivityAsync()
    {
        try
        {
            using var ping = new Ping();
            var reply = await ping.SendPingAsync("8.8.8.8", 2500);
            if (reply.Status != IPStatus.Success) return false;

            using var http = new HttpClient { Timeout = TimeSpan.FromSeconds(5) };
            var res = await http.GetAsync("https://www.googleapis.com/discovery/v1/apis/drive/v3/rest");
            return res.IsSuccessStatusCode;
        }
        catch
        {
            return false;
        }
    }

    public async Task<(bool Success, string? DriveFileId, string? Error)> UploadBackupAsync(
        string filePath,
        string sha256,
        string md5,
        long expectedSize)
    {
        if (!File.Exists(filePath))
        {
            return (false, null, $"File '{filePath}' does not exist on disk.");
        }

        var isConnected = await CheckGoogleConnectivityAsync();
        if (!isConnected)
        {
            return (false, null, "No active internet connection to Google services.");
        }

        try
        {
            // If offline mock mode or no OAuth credential configured yet
            if (!HasStoredToken())
            {
                return (false, null, "Google Drive account 'aminullahameen@gmail.com' needs initial sign-in.");
            }

            var service = await GetDriveServiceAsync();
            var folderId = await EnsureBackupFolderAsync(service);

            // 1. Duplicate check: query Drive by sha256 property
            var listReq = service.Files.List();
            listReq.Q = $"'{folderId}' in parents and appProperties has {{ key='sha256' and value='{sha256}' }} and trashed = false";
            listReq.Fields = "files(id, name, size, md5Checksum)";
            var listResult = await listReq.ExecuteAsync();

            if (listResult.Files.Any())
            {
                var existing = listResult.Files.First();
                return (true, existing.Id, null); // Already uploaded
            }

            // 2. Upload with metadata
            var fileMetadata = new Google.Apis.Drive.v3.Data.File
            {
                Name = Path.GetFileName(filePath),
                Parents = new List<string> { folderId },
                AppProperties = new Dictionary<string, string>
                {
                    { "sha256", sha256 },
                    { "uploader", "ICT_Management_System_Desktop" }
                }
            };

            await using var stream = File.OpenRead(filePath);
            var insertReq = service.Files.Create(fileMetadata, stream, "application/octet-stream");
            insertReq.Fields = "id, name, size, md5Checksum";

            var progress = await insertReq.UploadAsync();
            if (progress.Status != UploadStatus.Completed)
            {
                return (false, null, progress.Exception?.Message ?? "Upload did not complete.");
            }

            var uploadedFile = insertReq.ResponseBody;
            if (uploadedFile == null)
            {
                return (false, null, "Upload response did not return file details.");
            }

            // 3. Verify size and MD5 checksum against local file
            if (uploadedFile.Size.HasValue && uploadedFile.Size.Value != expectedSize)
            {
                return (false, null, $"Drive size ({uploadedFile.Size}) does not match local size ({expectedSize}).");
            }

            if (!string.IsNullOrWhiteSpace(uploadedFile.Md5Checksum) &&
                !string.Equals(uploadedFile.Md5Checksum, md5, StringComparison.OrdinalIgnoreCase))
            {
                return (false, null, $"Drive MD5 ({uploadedFile.Md5Checksum}) does not match local MD5 ({md5}).");
            }

            return (true, uploadedFile.Id, null);
        }
        catch (Exception ex)
        {
            return (false, null, ex.Message);
        }
    }

    private bool HasStoredToken() => File.Exists(_tokenStoragePath);

    private async Task<DriveService> GetDriveServiceAsync()
    {
        if (_driveService != null) return _driveService;

        // Retrieve DPAPI encrypted refresh token
        byte[] encrypted = await File.ReadAllBytesAsync(_tokenStoragePath);
        byte[] decrypted = ProtectedData.Unprotect(encrypted, null, DataProtectionScope.CurrentUser);
        string tokenJson = Encoding.UTF8.GetString(decrypted);

        // Dummy/Mock client credentials for Desktop loopback
        var credential = GoogleCredential.FromJson(tokenJson).CreateScoped(DriveService.Scope.DriveFile);

        _driveService = new DriveService(new BaseClientService.Initializer
        {
            HttpClientInitializer = credential,
            ApplicationName = "ICT Management System"
        });

        return _driveService;
    }

    private async Task<string> EnsureBackupFolderAsync(DriveService service)
    {
        var listReq = service.Files.List();
        listReq.Q = $"mimeType = 'application/vnd.google-apps.folder' and name = '{FolderName}' and trashed = false";
        listReq.Fields = "files(id, name)";
        var result = await listReq.ExecuteAsync();

        if (result.Files.Any())
        {
            return result.Files.First().Id;
        }

        var folderMetadata = new Google.Apis.Drive.v3.Data.File
        {
            Name = FolderName,
            MimeType = "application/vnd.google-apps.folder"
        };

        var folder = await service.Files.Create(folderMetadata).ExecuteAsync();
        return folder.Id;
    }
}

