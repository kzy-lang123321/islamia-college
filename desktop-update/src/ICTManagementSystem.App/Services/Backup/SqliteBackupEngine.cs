using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.App.Services.Backup;

public class SqliteBackupEngine
{
    private readonly string _liveDbPath;
    private readonly string _backupStorageDir;
    private readonly SyncQueueDbContext _queueContext;

    public SqliteBackupEngine(string liveDbPath, string backupStorageDir, SyncQueueDbContext queueContext)
    {
        _liveDbPath = liveDbPath;
        _backupStorageDir = backupStorageDir;
        _queueContext = queueContext;

        if (!Directory.Exists(_backupStorageDir))
        {
            Directory.CreateDirectory(_backupStorageDir);
        }
    }

    public async Task<(string BackupPath, BackupManifest Manifest)> CreateVerifiedBackupAsync(string? password = null, string? externalDrivePath = null)
    {
        var tempRawBackup = Path.Combine(Path.GetTempPath(), $"ict_raw_bk_{Guid.NewGuid():N}.db");

        try
        {
            // 1. Checkpoint WAL to make sure all transactions are in the main database file
            using (var liveConn = new SqliteConnection($"Data Source={_liveDbPath};Pooling=False"))
            {
                await liveConn.OpenAsync();
                using var cmd = liveConn.CreateCommand();
                cmd.CommandText = "PRAGMA wal_checkpoint(TRUNCATE);";
                await cmd.ExecuteNonQueryAsync();

                // 2. Perform SQLite Online Backup API snapshot
                using var destConn = new SqliteConnection($"Data Source={tempRawBackup};Pooling=False");
                await destConn.OpenAsync();
                liveConn.BackupDatabase(destConn);
            }

            SqliteConnection.ClearAllPools();

            // 3. Verify snapshot integrity
            using (var verifyConn = new SqliteConnection($"Data Source={tempRawBackup};Mode=ReadOnly;Pooling=False"))
            {
                await verifyConn.OpenAsync();
                using var checkCmd = verifyConn.CreateCommand();
                checkCmd.CommandText = "PRAGMA integrity_check;";
                var result = (string?)await checkCmd.ExecuteScalarAsync();
                if (result != "ok")
                {
                    throw new InvalidOperationException($"Backup snapshot failed integrity check: {result}");
                }
            }

            SqliteConnection.ClearAllPools();

            // 4. Encrypt and produce .icbak + manifest
            var (icBakPath, manifest) = await AesBackupEncryptor.EncryptAndPackageAsync(tempRawBackup, _backupStorageDir, password);

            // 5. Enqueue into sync-queue.db for automatic Google Drive background upload
            _queueContext.Jobs.Add(new SyncUploadJob
            {
                BackupFilePath = icBakPath,
                Sha256Checksum = manifest.Sha256,
                Md5Checksum = manifest.Md5,
                FileSizeBytes = manifest.FileSizeBytes,
                Status = "Pending",
                AttemptsCount = 0,
                CreatedAtUtc = DateTime.UtcNow
            });
            await _queueContext.SaveChangesAsync();

            // 6. Optional write-once copy to external drive
            if (!string.IsNullOrWhiteSpace(externalDrivePath) && Directory.Exists(externalDrivePath))
            {
                try
                {
                    var extDest = Path.Combine(externalDrivePath, Path.GetFileName(icBakPath));
                    File.Copy(icBakPath, extDest, overwrite: false);
                    var fileInfo = new FileInfo(extDest);
                    fileInfo.IsReadOnly = true;
                }
                catch
                {
                    // Non-fatal if external drive write fails
                }
            }

            // 7. Retain last 7 local backups (never prune unuploaded backups)
            PruneOldUploadedBackups();

            return (icBakPath, manifest);
        }
        finally
        {
            if (File.Exists(tempRawBackup))
            {
                try { File.Delete(tempRawBackup); } catch { }
            }
        }
    }

    public async Task<bool> RestoreVerifiedBackupAsync(string backupFilePath, string? password = null)
    {
        var tempDecrypted = Path.Combine(Path.GetTempPath(), $"ict_restore_{Guid.NewGuid():N}.db");
        var safetyCopy = Path.Combine(_backupStorageDir, $"Safety_Before_Restore_{DateTime.UtcNow:yyyyMMdd_HHmmss}.bak");

        try
        {
            // Decrypt candidate backup
            await AesBackupEncryptor.DecryptBackupAsync(backupFilePath, tempDecrypted, password);

            // Verify integrity of candidate
            using (var verifyConn = new SqliteConnection($"Data Source={tempDecrypted};Mode=ReadOnly;Pooling=False"))
            {
                await verifyConn.OpenAsync();
                using var checkCmd = verifyConn.CreateCommand();
                checkCmd.CommandText = "PRAGMA integrity_check;";
                var result = (string?)await checkCmd.ExecuteScalarAsync();
                if (result != "ok")
                {
                    throw new InvalidOperationException($"Candidate restore file failed SQLite integrity check: {result}");
                }
            }

            SqliteConnection.ClearAllPools();

            // Create safety snapshot of live database
            if (File.Exists(_liveDbPath))
            {
                File.Copy(_liveDbPath, safetyCopy, overwrite: true);
            }

            // Replace live database
            File.Copy(tempDecrypted, _liveDbPath, overwrite: true);

            // Verify live database health post-swap
            using (var liveConn = new SqliteConnection($"Data Source={_liveDbPath}"))
            {
                await liveConn.OpenAsync();
                using var postCheck = liveConn.CreateCommand();
                postCheck.CommandText = "PRAGMA quick_check;";
                var postResult = (string?)await postCheck.ExecuteScalarAsync();
                if (postResult != "ok")
                {
                    // Rollback to safety copy
                    File.Copy(safetyCopy, _liveDbPath, overwrite: true);
                    throw new InvalidOperationException($"Post-restore check failed: {postResult}. Reverted to safety copy.");
                }
            }

            return true;
        }
        finally
        {
            if (File.Exists(tempDecrypted))
            {
                try { File.Delete(tempDecrypted); } catch { }
            }
        }
    }

    private void PruneOldUploadedBackups()
    {
        try
        {
            var uploadedSha256s = _queueContext.Jobs
                .Where(j => j.Status == "Done")
                .Select(j => j.Sha256Checksum)
                .ToHashSet();

            var backupFiles = Directory.GetFiles(_backupStorageDir, "ICT_Backup_*.icbak")
                .OrderByDescending(File.GetCreationTimeUtc)
                .Skip(7)
                .ToList();

            foreach (var file in backupFiles)
            {
                var manifestPath = $"{file}.manifest.json";
                if (File.Exists(manifestPath))
                {
                    var manifestJson = File.ReadAllText(manifestPath);
                    var manifest = System.Text.Json.JsonSerializer.Deserialize<BackupManifest>(manifestJson);
                    if (manifest != null && uploadedSha256s.Contains(manifest.Sha256))
                    {
                        File.Delete(file);
                        File.Delete(manifestPath);
                    }
                }
            }
        }
        catch
        {
            // Silently continue
        }
    }
}
