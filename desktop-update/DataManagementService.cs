using System.Collections.Concurrent;
using System.Security.Cryptography;
using System.Text.Json;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;

namespace ICTManagementSystem.Data.Services;

public record AdminAuthorizationRequest(int UserId, string Password);
public record DataWipeRequest(string AdminToken, string Password, string Confirmation);
public record DataWipeResult(string BackupPath, Dictionary<string, long> DeletedRecords);
public record DataOverview(Dictionary<string, long> Tables, string Administrator);
public static class DataMaintenance { public static readonly SemaphoreSlim Gate = new(1, 1); }

public class DataManagementService(AppDbContext context, string? storageRoot = null)
{
    private record AdminSession(int UserId, DateTime Expires, string Database);
    private static readonly ConcurrentDictionary<string, AdminSession> Sessions = new();
    private static readonly ConcurrentDictionary<int, (int Count, DateTime Since)> FailedAttempts = new();
    private static readonly HashSet<string> Preserved = new() { nameof(User), nameof(AuditLog), nameof(SystemSetting), nameof(ReceiptCounter), nameof(CertificateCounter) };
    public string StorageRoot { get; } = storageRoot ?? Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "ICTManagementSystem");
    private string DatabaseIdentity => context.Database.GetDbConnection().DataSource;

    private async Task<User> VerifyPasswordAsync(int userId, string password)
    {
        if (FailedAttempts.TryGetValue(userId, out var attempts) && attempts.Count >= 5 && attempts.Since > DateTime.UtcNow.AddMinutes(-5))
            throw new InvalidOperationException("Too many failed administrator checks. Try again in five minutes.");
        var user = await context.Users.AsNoTracking().SingleOrDefaultAsync(u => u.Id == userId && !u.IsDeleted && u.IsActive && u.Role == UserRole.Admin);
        var verified = false;
        if (user != null && !string.IsNullOrEmpty(password))
            try { verified = BCrypt.Net.BCrypt.Verify(password, user.PasswordHash); } catch { }
        if (!verified)
        {
            FailedAttempts.AddOrUpdate(userId, (1, DateTime.UtcNow), (_, old) => old.Since < DateTime.UtcNow.AddMinutes(-5) ? (1, DateTime.UtcNow) : (old.Count + 1, old.Since));
            throw new UnauthorizedAccessException("An active administrator and the correct administrator password are required.");
        }
        FailedAttempts.TryRemove(userId, out _);
        return user!;
    }

    public async Task<string> AuthorizeAsync(AdminAuthorizationRequest request)
    {
        var user = await VerifyPasswordAsync(request.UserId, request.Password);
        var token = Convert.ToHexString(RandomNumberGenerator.GetBytes(32));
        Sessions[token] = new(user.Id, DateTime.UtcNow.AddMinutes(10), DatabaseIdentity);
        return token;
    }

    public async Task<User> RequireAdminAsync(string token)
    {
        if (string.IsNullOrWhiteSpace(token) || !Sessions.TryGetValue(token, out var session) || session.Expires < DateTime.UtcNow || session.Database != DatabaseIdentity)
            throw new UnauthorizedAccessException("Administrator access has expired. Unlock Data Management again.");
        return await context.Users.AsNoTracking().SingleOrDefaultAsync(u => u.Id == session.UserId && !u.IsDeleted && u.IsActive && u.Role == UserRole.Admin)
            ?? throw new UnauthorizedAccessException("Administrator access is required.");
    }

    private List<Microsoft.EntityFrameworkCore.Metadata.IEntityType> CollegeEntities() => context.Model.GetEntityTypes()
        .Where(e => !Preserved.Contains(e.ClrType.Name) && e.GetTableName() != null).ToList();
    private static string Quote(string table) => "\"" + table.Replace("\"", "\"\"") + "\"";
    private async Task<Dictionary<string, long>> CountAsync()
    {
        await context.Database.OpenConnectionAsync();
        var counts = new Dictionary<string, long>();
        foreach (var table in CollegeEntities().Select(e => e.GetTableName()!).Distinct())
        {
            await using var cmd = context.Database.GetDbConnection().CreateCommand();
            cmd.Transaction = context.Database.CurrentTransaction?.GetDbTransaction();
            cmd.CommandText = "SELECT COUNT(*) FROM " + Quote(table);
            counts[table] = Convert.ToInt64(await cmd.ExecuteScalarAsync());
        }
        return counts;
    }
    public async Task<DataOverview> OverviewAsync(string token)
    {
        var user = await RequireAdminAsync(token);
        return new(await CountAsync(), user.Username);
    }

    public async Task<DataWipeResult> WipeAsync(DataWipeRequest request)
    {
        var admin = await RequireAdminAsync(request.AdminToken);
        await VerifyPasswordAsync(admin.Id, request.Password);
        if (request.Confirmation != "WIPE COLLEGE DATA") throw new InvalidOperationException("Type WIPE COLLEGE DATA exactly to confirm.");
        await DataMaintenance.Gate.WaitAsync();
        try
        {
            admin = await RequireAdminAsync(request.AdminToken);
            await VerifyPasswordAsync(admin.Id, request.Password);
            await using var tx = await context.Database.BeginTransactionAsync();
            var counts = await CountAsync();
            var source = context.Database.GetDbConnection() as SqliteConnection ?? throw new InvalidOperationException("SQLite database required.");
            if (source.DataSource == ":memory:" || string.IsNullOrWhiteSpace(source.DataSource))
                throw new InvalidOperationException("A persistent database is required for a verified safety backup.");
            var folder = Path.Combine(StorageRoot, "backups", "before-wipe"); Directory.CreateDirectory(folder);
            var backupPath = Path.Combine(folder, $"college-before-wipe-{DateTime.UtcNow:yyyyMMdd-HHmmss}-{Guid.NewGuid():N}.db");
            // The write transaction blocks other writers. A separate read connection captures the committed, pre-wipe database including WAL records.
            await using (var read = new SqliteConnection(new SqliteConnectionStringBuilder(source.ConnectionString) { Mode = SqliteOpenMode.ReadOnly, Pooling = false }.ToString()))
            await using (var destination = new SqliteConnection($"Data Source={backupPath};Pooling=False"))
            {
                await read.OpenAsync(); await destination.OpenAsync(); read.BackupDatabase(destination);
                await using var check = destination.CreateCommand(); check.CommandText = "PRAGMA integrity_check;";
                if (!string.Equals(Convert.ToString(await check.ExecuteScalarAsync()), "ok", StringComparison.OrdinalIgnoreCase))
                    throw new InvalidOperationException("Safety backup verification failed. No college records were cleared.");
            }
            var remaining = CollegeEntities();
            while (remaining.Count > 0)
            {
                var entity = remaining.FirstOrDefault(e => !e.GetReferencingForeignKeys().Any(f => remaining.Contains(f.DeclaringEntityType) && f.DeclaringEntityType != e))
                    ?? throw new InvalidOperationException("Data dependencies could not be cleared safely.");
                await context.Database.ExecuteSqlRawAsync("DELETE FROM " + Quote(entity.GetTableName()!));
                remaining.Remove(entity);
            }
            await context.SystemSettings.Where(s => s.Key.StartsWith("WorkbookArchive:") || s.Key.StartsWith("WorkbookRow:")).ExecuteDeleteAsync();
            var manual = await context.SystemSettings.SingleOrDefaultAsync(s => s.Key == "DataManagement:ManualMode");
            if (manual == null) context.SystemSettings.Add(new SystemSetting { Key = "DataManagement:ManualMode", Value = "true" }); else manual.Value = "true";
            context.AuditLogs.Add(new AuditLog { Username = admin.Username, UserId = admin.Id, Action = "admin.wipe", EntityName = "CollegeData",
                NewValuesJson = JsonSerializer.Serialize(new { BackupPath = backupPath, DeletedRecords = counts }) });
            await context.SaveChangesAsync(); await tx.CommitAsync();
            Sessions.TryRemove(request.AdminToken, out _);
            return new(backupPath, counts);
        }
        finally { DataMaintenance.Gate.Release(); }
    }
}
