using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace ICTManagementSystem.App.Services.Backup;

public class BackupManifest
{
    public string FileName { get; set; } = string.Empty;
    public string Sha256 { get; set; } = string.Empty;
    public string Md5 { get; set; } = string.Empty;
    public long FileSizeBytes { get; set; }
    public DateTime CreatedAtUtc { get; set; } = DateTime.UtcNow;
    public string SystemVersion { get; set; } = "1.0.0";
}

public static class AesBackupEncryptor
{
    private static readonly byte[] Salt = Encoding.UTF8.GetBytes("ICT_MANAGEMENT_SYSTEM_SALT_2026_SWAT");

    public static async Task<(string IcBakPath, BackupManifest Manifest)> EncryptAndPackageAsync(
        string rawDbPath,
        string destinationDirectory,
        string? password = null)
    {
        var pass = string.IsNullOrWhiteSpace(password) ? "ICT_DEFAULT_SYSTEM_BACKUP_KEY_#2026" : password;
        var timeStamp = DateTime.UtcNow.ToString("yyyyMMdd_HHmmss");
        var icBakName = $"ICT_Backup_{timeStamp}.icbak";
        var icBakPath = Path.Combine(destinationDirectory, icBakName);

        using var keyDerivation = new Rfc2898DeriveBytes(pass, Salt, 100_000, HashAlgorithmName.SHA256);
        var key = keyDerivation.GetBytes(32); // AES-256
        var iv = keyDerivation.GetBytes(16);

        using (var aes = Aes.Create())
        {
            aes.Key = key;
            aes.IV = iv;

            await using var sourceStream = File.OpenRead(rawDbPath);
            await using var destStream = File.Create(icBakPath);
            await using var cryptoStream = new CryptoStream(destStream, aes.CreateEncryptor(), CryptoStreamMode.Write);

            await sourceStream.CopyToAsync(cryptoStream);
        }

        // Compute Checksums of the final .icbak
        var bytes = await File.ReadAllBytesAsync(icBakPath);
        using var sha256 = SHA256.Create();
        using var md5 = MD5.Create();

        var sha256Hash = Convert.ToHexString(sha256.ComputeHash(bytes)).ToLowerInvariant();
        var md5Hash = Convert.ToHexString(md5.ComputeHash(bytes)).ToLowerInvariant();

        var manifest = new BackupManifest
        {
            FileName = icBakName,
            Sha256 = sha256Hash,
            Md5 = md5Hash,
            FileSizeBytes = bytes.Length,
            CreatedAtUtc = DateTime.UtcNow
        };

        var manifestPath = Path.Combine(destinationDirectory, $"{icBakName}.manifest.json");
        await File.WriteAllTextAsync(manifestPath, JsonSerializer.Serialize(manifest, new JsonSerializerOptions { WriteIndented = true }));

        return (icBakPath, manifest);
    }

    public static async Task<string> DecryptBackupAsync(string icBakPath, string targetDbPath, string? password = null)
    {
        var pass = string.IsNullOrWhiteSpace(password) ? "ICT_DEFAULT_SYSTEM_BACKUP_KEY_#2026" : password;

        using var keyDerivation = new Rfc2898DeriveBytes(pass, Salt, 100_000, HashAlgorithmName.SHA256);
        var key = keyDerivation.GetBytes(32);
        var iv = keyDerivation.GetBytes(16);

        using var aes = Aes.Create();
        aes.Key = key;
        aes.IV = iv;

        await using var sourceStream = File.OpenRead(icBakPath);
        await using var destStream = File.Create(targetDbPath);
        await using var cryptoStream = new CryptoStream(sourceStream, aes.CreateDecryptor(), CryptoStreamMode.Read);

        await cryptoStream.CopyToAsync(destStream);
        return targetDbPath;
    }
}

