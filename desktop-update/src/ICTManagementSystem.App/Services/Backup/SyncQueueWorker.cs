using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace ICTManagementSystem.App.Services.Backup;

public class SyncQueueWorker : BackgroundService
{
    private readonly IServiceProvider _serviceProvider;
    private readonly ILogger<SyncQueueWorker> _logger;
    private static readonly TimeSpan[] BackoffDelays =
    [
        TimeSpan.FromMinutes(1),
        TimeSpan.FromMinutes(2),
        TimeSpan.FromMinutes(5),
        TimeSpan.FromMinutes(15),
        TimeSpan.FromMinutes(60)
    ];

    public SyncQueueWorker(IServiceProvider serviceProvider, ILogger<SyncQueueWorker> logger)
    {
        _serviceProvider = serviceProvider;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("Google Drive Backup Sync Queue Worker initialized for account aminullahameen@gmail.com.");

        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                await ProcessPendingJobsAsync(stoppingToken);
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error occurred during backup queue sync execution.");
            }

            // Wait 5 minutes before next polling cycle
            try
            {
                await Task.Delay(TimeSpan.FromMinutes(5), stoppingToken);
            }
            catch (TaskCanceledException)
            {
                break;
            }
        }
    }

    public async Task ProcessPendingJobsAsync(CancellationToken cancellationToken = default)
    {
        using var scope = _serviceProvider.CreateScope();
        var queueContext = scope.ServiceProvider.GetRequiredService<SyncQueueDbContext>();
        var driveClient = scope.ServiceProvider.GetRequiredService<IGoogleDriveClient>();

        var now = DateTime.UtcNow;

        // Fetch oldest pending/failed jobs whose retry time has arrived
        var jobs = await queueContext.Jobs
            .Where(j => j.Status == "Pending" || (j.Status == "Failed" && (j.NextRetryUtc == null || j.NextRetryUtc <= now)))
            .OrderBy(j => j.CreatedAtUtc)
            .ToListAsync(cancellationToken);

        if (!jobs.Any()) return;

        bool hasInternet = await driveClient.CheckGoogleConnectivityAsync();
        if (!hasInternet)
        {
            _logger.LogDebug("Sync worker paused: no active Google connectivity.");
            return;
        }

        foreach (var job in jobs)
        {
            if (cancellationToken.IsCancellationRequested) break;

            job.Status = "Uploading";
            job.AttemptsCount++;
            await queueContext.SaveChangesAsync(cancellationToken);

            _logger.LogInformation("Starting backup upload for {File} (Attempt #{Attempt})...", Path.GetFileName(job.BackupFilePath), job.AttemptsCount);

            var (success, driveFileId, error) = await driveClient.UploadBackupAsync(
                job.BackupFilePath,
                job.Sha256Checksum,
                job.Md5Checksum,
                job.FileSizeBytes);

            if (success)
            {
                job.Status = "Done";
                job.DriveFileId = driveFileId;
                job.LastError = null;
                job.CompletedAtUtc = DateTime.UtcNow;
                _logger.LogInformation("Backup {File} successfully synced to Google Drive (ID: {DriveId}).", Path.GetFileName(job.BackupFilePath), driveFileId);
            }
            else
            {
                job.Status = "Failed";
                job.LastError = error;

                // Calculate exponential backoff
                int delayIdx = Math.Min(job.AttemptsCount - 1, BackoffDelays.Length - 1);
                job.NextRetryUtc = DateTime.UtcNow.Add(delayIdx >= 0 ? BackoffDelays[delayIdx] : TimeSpan.FromHours(1));

                _logger.LogWarning("Backup upload failed for {File}: {Error}. Next retry at {RetryUtc}.", Path.GetFileName(job.BackupFilePath), error, job.NextRetryUtc);
            }

            await queueContext.SaveChangesAsync(cancellationToken);
        }
    }
}

