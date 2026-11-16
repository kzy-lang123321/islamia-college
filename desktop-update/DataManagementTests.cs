using System.Text;
using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using ICTManagementSystem.Data;
using ICTManagementSystem.Data.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace ICTManagementSystem.Tests;

public class DataManagementTests : IDisposable
{
    private readonly string folder = Path.Combine(Path.GetTempPath(), "ICT-data-tests-" + Guid.NewGuid().ToString("N"));
    private readonly AppDbContext db;
    private readonly int adminId, cashierId;
    private const string Password = "Test-only-strong-password";
    public DataManagementTests()
    {
        Directory.CreateDirectory(folder);
        db = new(new DbContextOptionsBuilder<AppDbContext>().UseSqlite($"Data Source={Path.Combine(folder, "fixture.db")};Pooling=False").Options);
        db.Database.EnsureCreated();
        var hash = BCrypt.Net.BCrypt.HashPassword(Password, 4);
        var admin = new User { Username = "fixture-admin", FullName = "Fixture Admin", Role = UserRole.Admin, PasswordHash = hash };
        var cashier = new User { Username = "fixture-cashier", FullName = "Fixture Cashier", Role = UserRole.Cashier, PasswordHash = hash };
        db.Users.AddRange(admin, cashier); db.SaveChanges(); adminId = admin.Id; cashierId = cashier.Id;
        db.ChangeTracker.Clear();
    }
    private DataManagementService Management(string? storage = null) => new(db, storage ?? folder);
    private Task<string> Unlock() => Management().AuthorizeAsync(new(adminId, Password));
    private async Task SeedCollege()
    {
        var course = new Course { CourseCode = "DIT", CourseName = "DIT", StandardFee = 500 };
        var batch = new Batch { Course = course, BatchCode = "ICT-DIT-B6", BatchName = "Old" };
        var enrollment = new Enrollment { Batch = batch, Student = new Student { FullName = "Fixture Student", StudentRegNo = "ICT-DIT-611" }, TotalAgreedFee = 500 };
        db.FeeTransactions.Add(new FeeTransaction { Enrollment = enrollment, ReceiptNo = "FIXTURE-REC-1", AmountPaid = 100 });
        db.AuditLogs.Add(new AuditLog { Username = "fixture-admin", Action = "fixture.history", EntityName = "Test" });
        await db.SaveChangesAsync(); db.ChangeTracker.Clear();
    }
    [Fact]
    public async Task AdministratorCheck_RejectsCashierAndLoginShortcutPasswords()
    {
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => Management().AuthorizeAsync(new(cashierId, Password)));
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => Management().AuthorizeAsync(new(adminId, "admin123")));
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => Management().OverviewAsync("forged-token"));
        Assert.NotEmpty(await db.Users.ToListAsync());
    }
    [Fact]
    public async Task Wipe_RequiresTokenFreshPasswordAndExactPhrase()
    {
        await SeedCollege(); var token = await Unlock();
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => Management().WipeAsync(new("forged", Password, "WIPE COLLEGE DATA")));
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => Management().WipeAsync(new(token, "wrong", "WIPE COLLEGE DATA")));
        await Assert.ThrowsAsync<InvalidOperationException>(() => Management().WipeAsync(new(token, Password, "wipe")));
        Assert.Equal(1, await db.Students.CountAsync()); Assert.Equal(1, await db.Courses.CountAsync());
    }
    [Fact]
    public async Task Wipe_CreatesVerifiedBackup_ClearsAllCollegeTables_PreservesAccountsHistoryAndManualMode()
    {
        await db.Database.OpenConnectionAsync();
        await db.Database.ExecuteSqlRawAsync("PRAGMA journal_mode=WAL;");
        await SeedCollege(); var token = await Unlock();
        var result = await Management().WipeAsync(new(token, Password, "WIPE COLLEGE DATA")); db.ChangeTracker.Clear();
        Assert.True(File.Exists(result.BackupPath));
        Assert.Equal(0, await db.Students.IgnoreQueryFilters().CountAsync());
        Assert.Equal(0, await db.Courses.IgnoreQueryFilters().CountAsync());
        Assert.Equal(0, await db.Batches.CountAsync()); Assert.Equal(0, await db.FeeTransactions.CountAsync());
        Assert.Equal(2, await db.Users.CountAsync());
        var dashboard = await new DashboardService(db).GetDashboardSummaryAsync();
        Assert.Equal(0, dashboard.TotalStudents); Assert.Equal(0, dashboard.ActiveCoursesCount);
        Assert.Equal(0, dashboard.TotalBatches); Assert.Equal(0, dashboard.StaffCount); Assert.Equal(0, dashboard.TotalFeesCollected);
        Assert.Contains(await db.AuditLogs.ToListAsync(), a => a.Action == "fixture.history");
        Assert.Contains(await db.AuditLogs.ToListAsync(), a => a.Action == "admin.wipe");
        Assert.Equal("true", (await db.SystemSettings.SingleAsync(s => s.Key == "DataManagement:ManualMode")).Value);
        await using var backup = new SqliteConnection($"Data Source={result.BackupPath};Mode=ReadOnly;Pooling=False"); await backup.OpenAsync();
        await using var check = backup.CreateCommand(); check.CommandText = "PRAGMA integrity_check"; Assert.Equal("ok", await check.ExecuteScalarAsync());
        check.CommandText = "SELECT COUNT(*) FROM Students"; Assert.Equal(1L, await check.ExecuteScalarAsync());
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => Management().OverviewAsync(token));
    }
    [Fact]
    public async Task FailedBackup_PreventsAnyDeletion()
    {
        await SeedCollege(); var token = await Unlock(); var blockedFolder = Path.Combine(folder, "file-not-directory"); await File.WriteAllTextAsync(blockedFolder, "fixture");
        await Assert.ThrowsAnyAsync<Exception>(() => Management(blockedFolder).WipeAsync(new(token, Password, "WIPE COLLEGE DATA")));
        db.ChangeTracker.Clear(); Assert.Equal(1, await db.Students.CountAsync()); Assert.Equal(1, await db.FeeTransactions.CountAsync());
    }
    [Fact]
    public async Task AdministratorAccess_IsRecheckedWhenRolesChange()
    {
        await SeedCollege(); var token = await Unlock();
        (await db.Users.SingleAsync(u => u.Id == adminId)).Role = UserRole.Cashier;
        await db.SaveChangesAsync(); db.ChangeTracker.Clear();
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => Management().WipeAsync(new(token, Password, "WIPE COLLEGE DATA")));
        Assert.Equal(1, await db.Students.CountAsync());
    }
    [Fact]
    public async Task QueuedWipe_RechecksAdministratorAccessBeforeChangingData()
    {
        await SeedCollege(); var token = await Unlock();
        await DataMaintenance.Gate.WaitAsync();
        Task<DataWipeResult> pending;
        try
        {
            pending = Management().WipeAsync(new(token, Password, "WIPE COLLEGE DATA"));
            (await db.Users.SingleAsync(u => u.Id == adminId)).Role = UserRole.Cashier;
            await db.SaveChangesAsync(); db.ChangeTracker.Clear();
        }
        finally { DataMaintenance.Gate.Release(); }
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => pending);
        Assert.Equal(1, await db.Students.CountAsync());
    }
    private WorkbookImportRequest Workbook(string token, bool changed = false) => new(token, "fixture.xlsx",
        Convert.ToBase64String(new byte[] { 0x50, 0x4b, 3, 4, 0, 0, 0, changed ? (byte)1 : (byte)0 }), new() {
            new("ICT-DIT-B6", "students", "DIT", "DIT", "ICT-DIT-B6", 500,
                new() { new("ICT-DIT-611", "Fixture DIT Student", "Father", "03001234567", null, "Address", "611", 500, 100, null, "old-slip") },
                new() { new() { "Reg No", "Name", "Fee", "Paid" }, new() { "ICT-DIT-611", "Fixture DIT Student", "500", "100" } }),
            new("ELL-B25", "students", "ELL", "English", "ELL-B25", 300,
                new() { new("ELL-2511", "Fixture English Student", "Father", null, null, null, "2511", 300, 50, null, null) },
                new() { new() { "STD-ID", "Name" }, new() { "ELL-2511", "Fixture English Student" } }),
            new("ICT-STAFF", "reference", "STAFF", "Staff", "STAFF", 0, new(), new() { new() { "Month", "Salary" }, new() { "November", "1000" } })
        });
    [Fact]
    public async Task Workbook_ImportsEverySheet_PreservesReferenceTabsAndOriginal_AndContinuesNumbering()
    {
        var token = await Unlock(); var result = await new WorkbookImportService(db, folder).ImportAsync(Workbook(token)); db.ChangeTracker.Clear();
        Assert.Equal(3, result.Worksheets); Assert.True(File.Exists(result.OriginalFilePath));
        Assert.Equal(2, await db.Students.CountAsync()); Assert.Equal(2, await db.Enrollments.CountAsync());
        Assert.Equal(2, await db.FeeTransactions.CountAsync()); Assert.All(await db.Batches.ToListAsync(), b => Assert.Equal("completed", b.Status));
        Assert.Equal(611, int.Parse((await db.Students.SingleAsync(s => s.FullName == "Fixture DIT Student")).StudentRegNo.Split('-').Last()));
        Assert.Equal("ICT-DIT-612", await new StudentService(db).GetNextRegistrationNoAsync("DIT"));
        Assert.Equal("ICT-ELL-2512", await new StudentService(db).GetNextRegistrationNoAsync("ELL"));
        var course = await db.Courses.SingleAsync(c => c.CourseCode == "DIT");
        Assert.Equal("ICT-DIT-B7", await new CourseService(db).GetNextBatchCodeAsync(course.Id));
        var sheet = await new WorkbookImportService(db, folder).SheetAsync(token, result.Hash, 2);
        Assert.Equal("ICT-STAFF", sheet.GetProperty("Name").GetString());
        Assert.Equal("Salary", sheet.GetProperty("SourceRows")[0][1].GetString());
        var batch = await new CourseService(db).CreateBatchAsync(new("ICT-DIT-B7", "New admissions", course.Id, null));
        var admitted = await new StudentService(db).CreateStudentAsync(new("", "New admission", null, null, null, null, null, null, null, batch.Id, null, null));
        Assert.Equal("ICT-DIT-612", admitted.StudentRegNo);
    }
    [Fact]
    public async Task Workbook_RepeatedUploadsAndRevisedCopies_DoNotDuplicateStudentsOrOpeningPayments()
    {
        var token = await Unlock(); await new WorkbookImportService(db, folder).ImportAsync(Workbook(token)); db.ChangeTracker.Clear();
        Assert.True((await new WorkbookImportService(db, folder).ImportAsync(Workbook(token))).AlreadyImported); db.ChangeTracker.Clear();
        await new WorkbookImportService(db, folder).ImportAsync(Workbook(token, true)); db.ChangeTracker.Clear();
        Assert.Equal(2, await db.Students.CountAsync()); Assert.Equal(2, await db.Enrollments.CountAsync()); Assert.Equal(2, await db.FeeTransactions.CountAsync());
    }
    [Fact]
    public async Task Workbook_DeniesUnauthenticatedImportWithoutWritingFilesOrRecords()
    {
        await Assert.ThrowsAsync<UnauthorizedAccessException>(() => new WorkbookImportService(db, folder).ImportAsync(Workbook("forged")));
        Assert.Equal(0, await db.Students.CountAsync()); Assert.False(Directory.Exists(Path.Combine(folder, "imports")));
    }
    [Fact]
    public async Task Workbook_ConflictingLegacyRegistrations_GetDistinctStudents_AndRemainStableAcrossRevisions()
    {
        var token = await Unlock(); var request = Workbook(token);
        request.Sheets[1] = request.Sheets[1] with { CourseCode = "DIT", CourseName = "DIT", Students = new() {
            new("ICT-DIT-611", "Different person", "Different father", null, null, null, "611", 500, 0, null, null) } };
        var result = await new WorkbookImportService(db, folder).ImportAsync(request); db.ChangeTracker.Clear();
        Assert.Equal(2, await db.Students.CountAsync()); Assert.Equal(2, (await db.Students.Select(s => s.StudentRegNo).ToListAsync()).Distinct().Count());
        Assert.NotEmpty(result.Sheets[1].Warnings);
        await new WorkbookImportService(db, folder).ImportAsync(request with { FileBase64 = Workbook(token, true).FileBase64 }); db.ChangeTracker.Clear();
        Assert.Equal(2, await db.Students.CountAsync());
    }
    public void Dispose()
    {
        db.Dispose();
        var resolved = Path.GetFullPath(folder);
        if (resolved.StartsWith(Path.GetFullPath(Path.GetTempPath()), StringComparison.OrdinalIgnoreCase) && Path.GetFileName(resolved).StartsWith("ICT-data-tests-"))
            try { Directory.Delete(resolved, true); } catch { }
    }
}
