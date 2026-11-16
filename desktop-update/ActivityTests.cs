using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Data;
using ICTManagementSystem.Data.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using Xunit;

namespace ICTManagementSystem.Tests;

public class ActivityTests : IDisposable
{
    private readonly SqliteConnection connection = new("DataSource=:memory:");
    private readonly AppDbContext db;
    private readonly int courseId, batchId, studentId;
    public ActivityTests()
    {
        connection.Open(); db = new(new DbContextOptionsBuilder<AppDbContext>().UseSqlite(connection).Options); db.Database.EnsureCreated();
        var course = new Course { CourseCode = "DIT", CourseName = "DIT", Category = "IT", Duration = "1 Year", StandardFee = 1000 };
        var batch = new Batch { BatchCode = "DIT-AM", BatchName = "Morning", Course = course };
        var student = new Student { StudentRegNo = "ICT-DIT-001", FullName = "Test Student" };
        db.Enrollments.Add(new Enrollment { Student = student, Batch = batch, TotalAgreedFee = 1000 }); db.SaveChanges();
        courseId = course.Id; batchId = batch.Id; studentId = student.Id; db.ChangeTracker.Clear();
    }
    private async Task<int> ChangeFee(decimal amount)
    {
        await new ActivityService(db).ExecuteAsync("courses.update", $"{{\"id\":{courseId}}}", "operator",
            async () => await new CourseService(db).UpdateCourseAsync(new(courseId, "DIT", "DIT", "IT", "1 Year", amount, 0, null)));
        db.ChangeTracker.Clear(); return await db.AuditLogs.MaxAsync(a => a.Id);
    }
    [Fact]
    public async Task UndoFeeEdit_RestoresPreviousSetting_PreservesEnrollment_AndRecordsActor()
    {
        var id = await ChangeFee(2200);
        Assert.Equal("operator", (await db.AuditLogs.SingleAsync(a => a.Id == id)).Username); db.ChangeTracker.Clear();
        await new ActivityService(db).UndoAsync(id, "supervisor"); db.ChangeTracker.Clear();
        Assert.Equal(1000, (await db.Courses.SingleAsync()).StandardFee);
        Assert.Equal(1000, (await db.Enrollments.SingleAsync()).TotalAgreedFee);
        Assert.Equal("supervisor", (await db.AuditLogs.SingleAsync(a => a.Action == "Undo")).Username);
        await Assert.ThrowsAsync<InvalidOperationException>(() => new ActivityService(db).UndoAsync(id, "supervisor"));
    }
    [Fact]
    public async Task UndoOlderChange_BlocksNewerChanges_AllowsReverseOrder()
    {
        var first = await ChangeFee(2000); var second = await ChangeFee(3000);
        await Assert.ThrowsAsync<InvalidOperationException>(() => new ActivityService(db).UndoAsync(first, "admin"));
        db.ChangeTracker.Clear(); await new ActivityService(db).UndoAsync(second, "admin");
        db.ChangeTracker.Clear(); await new ActivityService(db).UndoAsync(first, "admin");
        db.ChangeTracker.Clear(); Assert.Equal(1000, (await db.Courses.SingleAsync()).StandardFee);
    }
    [Fact]
    public async Task UndoCourseRemoval_RestoresCourseAndBatchStatus_WithoutDeletingStudents()
    {
        await new ActivityService(db).ExecuteAsync("courses.archive", $"{{\"id\":{courseId}}}", "operator",
            async () => await new CourseService(db).ArchiveCourseAsync(courseId));
        db.ChangeTracker.Clear(); var id = await db.AuditLogs.MaxAsync(a => a.Id);
        Assert.Equal("inactive", (await db.Batches.SingleAsync()).Status); db.ChangeTracker.Clear();
        await new ActivityService(db).UndoAsync(id, "operator"); db.ChangeTracker.Clear();
        Assert.False((await db.Courses.IgnoreQueryFilters().SingleAsync()).IsDeleted);
        Assert.Equal("active", (await db.Batches.SingleAsync()).Status);
        Assert.Equal(1, await db.Students.CountAsync());
    }
    [Fact]
    public async Task UndoStudentRemoval_RestoresStudentAndKeepsEnrollment()
    {
        await new ActivityService(db).ExecuteAsync("students.delete", $"{{\"id\":{studentId}}}", "operator",
            async () => await new StudentService(db).DeleteStudentAsync(studentId, "operator"));
        db.ChangeTracker.Clear(); Assert.Equal(0, await db.Students.CountAsync());
        var id = await db.AuditLogs.MaxAsync(a => a.Id);
        await new ActivityService(db).UndoAsync(id, "operator"); db.ChangeTracker.Clear();
        Assert.Equal(1, await db.Students.CountAsync()); Assert.Equal(1, await db.Enrollments.CountAsync());
    }
    [Fact]
    public async Task UndoBatchActivation_RestoresStatus()
    {
        await new ActivityService(db).ExecuteAsync("courses.setBatchActive", $"{{\"id\":{batchId}}}", "operator",
            async () => await new CourseService(db).SetBatchActiveAsync(batchId, false));
        db.ChangeTracker.Clear(); var id = await db.AuditLogs.MaxAsync(a => a.Id);
        await new ActivityService(db).UndoAsync(id, "operator"); db.ChangeTracker.Clear();
        Assert.Equal("active", (await db.Batches.SingleAsync()).Status);
    }
    [Fact]
    public async Task DailyHistory_UsesPakistanDayBoundaries_AndFiltersUser()
    {
        db.AuditLogs.AddRange(
            new AuditLog { TimestampUtc = new DateTime(2026, 11, 4, 18, 59, 59, DateTimeKind.Utc), Username = "outside", Action = "Create" },
            new AuditLog { TimestampUtc = new DateTime(2026, 11, 4, 19, 0, 0, DateTimeKind.Utc), Username = "operator", Action = "Create" },
            new AuditLog { TimestampUtc = new DateTime(2026, 11, 5, 18, 59, 59, DateTimeKind.Utc), Username = "admin", Action = "Create" },
            new AuditLog { TimestampUtc = new DateTime(2026, 11, 5, 19, 0, 0, DateTimeKind.Utc), Username = "outside", Action = "Create" });
        await db.SaveChangesAsync(); db.ChangeTracker.Clear();
        Assert.Equal(2, (await new ActivityService(db).GetDailyAsync("2026-11-05", null)).TotalCount);
        Assert.Equal(1, (await new ActivityService(db).GetDailyAsync("2026-11-05", "operator")).TotalCount);
    }
    [Fact]
    public async Task PaymentsCannotBeUndoneByGeneralUndo_SecretsAreNotRecorded()
    {
        await new ActivityService(db).ExecuteAsync("users.resetPassword", "{\"newPassword\":\"do-not-log\"}", "operator", () => Task.FromResult<object?>(true));
        var row = await db.AuditLogs.SingleAsync(); Assert.DoesNotContain("do-not-log", row.NewValuesJson!); db.ChangeTracker.Clear();
        await Assert.ThrowsAsync<InvalidOperationException>(() => new ActivityService(db).UndoAsync(row.Id, "operator"));
        db.AuditLogs.Add(new AuditLog { Action = "fees.receivePayment", EntityName = "FeeTransaction", OldValuesJson = "{}", NewValuesJson = "{}" });
        await db.SaveChangesAsync(); var paymentId = await db.AuditLogs.MaxAsync(a => a.Id); db.ChangeTracker.Clear();
        await Assert.ThrowsAsync<InvalidOperationException>(() => new ActivityService(db).UndoAsync(paymentId, "operator"));
    }
    [Fact]
    public async Task UndoableChange_AndAudit_AreAtomic()
    {
        await db.Database.ExecuteSqlRawAsync("CREATE TRIGGER RejectAudit BEFORE INSERT ON AuditLogs BEGIN SELECT RAISE(ABORT, 'test audit failure'); END;");
        await Assert.ThrowsAnyAsync<Exception>(() => ChangeFee(9999));
        db.ChangeTracker.Clear(); Assert.Equal(1000, (await db.Courses.SingleAsync()).StandardFee);
    }
    [Fact]
    public async Task DesktopBridge_RecordsChangesAndExposesHistoryAndUndo()
    {
        var services = new ServiceCollection();
        services.AddScoped(_ => new AppDbContext(new DbContextOptionsBuilder<AppDbContext>().UseSqlite(connection).Options));
        services.AddScoped<ICTManagementSystem.Core.Interfaces.ICourseService, CourseService>();
        using var provider = services.BuildServiceProvider();
        var bridge = new ICTManagementSystem.App.Bridge.JsBridge(provider, Microsoft.Extensions.Logging.Abstractions.NullLogger<ICTManagementSystem.App.Bridge.JsBridge>.Instance);
        var payload = System.Text.Json.JsonSerializer.Serialize(new { id = courseId, courseCode = "DIT", courseName = "DIT", category = "IT", duration = "1 Year", standardFee = 2500, ttbExamFee = 0, __actor = "operator" });
        using var update = System.Text.Json.JsonDocument.Parse(await bridge.InvokeAsync("courses.update", payload));
        Assert.True(update.RootElement.GetProperty("success").GetBoolean());
        var id = await db.AuditLogs.MaxAsync(a => a.Id);
        using var history = System.Text.Json.JsonDocument.Parse(await bridge.InvokeAsync("activity.getDaily", System.Text.Json.JsonSerializer.Serialize(new { date = DateTime.UtcNow.AddHours(5).ToString("yyyy-MM-dd") })));
        Assert.True(history.RootElement.GetProperty("success").GetBoolean());
        Assert.Equal("operator", history.RootElement.GetProperty("data").GetProperty("items")[0].GetProperty("username").GetString());
        using var undo = System.Text.Json.JsonDocument.Parse(await bridge.InvokeAsync("activity.undo", System.Text.Json.JsonSerializer.Serialize(new { id, __actor = "operator" })));
        Assert.True(undo.RootElement.GetProperty("success").GetBoolean());
        db.ChangeTracker.Clear(); Assert.Equal(1000, (await db.Courses.SingleAsync()).StandardFee);
    }
    public void Dispose() { db.Dispose(); connection.Dispose(); }
}
