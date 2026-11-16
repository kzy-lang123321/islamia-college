using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Interfaces;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;

namespace ICTManagementSystem.App.Services.Backup;

public class BackupService : IBackupService
{
    private readonly SqliteBackupEngine _engine;
    private readonly SyncQueueDbContext _queueContext;
    private readonly IServiceProvider _serviceProvider;
    private readonly IGoogleDriveClient _driveClient;

    public BackupService(
        SqliteBackupEngine engine,
        SyncQueueDbContext queueContext,
        IServiceProvider serviceProvider,
        IGoogleDriveClient driveClient)
    {
        _engine = engine;
        _queueContext = queueContext;
        _serviceProvider = serviceProvider;
        _driveClient = driveClient;
    }

    public async Task<string> CreateManualBackupAsync(string targetDirectory, string? password = null)
    {
        var (backupPath, manifest) = await _engine.CreateVerifiedBackupAsync(password, targetDirectory);
        return backupPath;
    }

    public async Task<bool> RestoreBackupAsync(string backupFilePath, string? password = null)
    {
        return await _engine.RestoreVerifiedBackupAsync(backupFilePath, password);
    }

    public async Task<SyncStatusDto> GetSyncStatusAsync()
    {
        var pendingCount = await _queueContext.Jobs.CountAsync(j => j.Status == "Pending" || j.Status == "Failed");
        var lastCompleted = await _queueContext.Jobs
            .Where(j => j.Status == "Done")
            .OrderByDescending(j => j.CompletedAtUtc)
            .FirstOrDefaultAsync();

        bool hasInternet = await _driveClient.CheckGoogleConnectivityAsync();

        string state;
        if (!hasInternet)
        {
            state = "Offline";
        }
        else if (pendingCount > 0)
        {
            state = "Pending";
        }
        else
        {
            state = "Synced";
        }

        string? warning = null;
        if (lastCompleted != null && (DateTime.UtcNow - lastCompleted.CompletedAtUtc!.Value).TotalDays > 7)
        {
            warning = "Warning: The newest backup has been unsynced for more than 7 days. Please check internet connection.";
        }

        return new SyncStatusDto
        {
            State = state,
            LastSyncTime = lastCompleted?.CompletedAtUtc?.ToString("dd MMM yyyy, hh:mm tt") ?? "Never",
            PendingCount = pendingCount,
            DriveAccount = "aminullahameen@gmail.com",
            WarningMessage = warning
        };
    }

    public async Task TriggerSyncNowAsync()
    {
        using var scope = _serviceProvider.CreateScope();
        var worker = scope.ServiceProvider.GetService<SyncQueueWorker>();
        if (worker != null)
        {
            await worker.ProcessPendingJobsAsync();
        }
    }

    public Task<string> ConnectGoogleDriveAsync()
    {
        // Instructions or URL for admin authorization
        return Task.FromResult("https://accounts.google.com/o/oauth2/v2/auth?login_hint=aminullahameen@gmail.com");
    }
}

