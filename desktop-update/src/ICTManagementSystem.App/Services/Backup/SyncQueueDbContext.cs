using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.App.Services.Backup;

public class SyncUploadJob
{
    public int Id { get; set; }
    public string BackupFilePath { get; set; } = string.Empty;
    public string Sha256Checksum { get; set; } = string.Empty;
    public string Md5Checksum { get; set; } = string.Empty;
    public long FileSizeBytes { get; set; }
    public string Status { get; set; } = "Pending"; // Pending, Uploading, Done, Failed
    public int AttemptsCount { get; set; } = 0;
    public DateTime? NextRetryUtc { get; set; }
    public string? LastError { get; set; }
    public string? DriveFileId { get; set; }
    public DateTime CreatedAtUtc { get; set; } = DateTime.UtcNow;
    public DateTime? CompletedAtUtc { get; set; }
}

public class SyncQueueDbContext : DbContext
{
    public SyncQueueDbContext(DbContextOptions<SyncQueueDbContext> options) : base(options)
    {
    }

    public DbSet<SyncUploadJob> Jobs => Set<SyncUploadJob>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        base.OnModelCreating(modelBuilder);
        modelBuilder.Entity<SyncUploadJob>(b =>
        {
            b.HasIndex(j => j.Status);
            b.HasIndex(j => j.Sha256Checksum);
        });
    }
}

