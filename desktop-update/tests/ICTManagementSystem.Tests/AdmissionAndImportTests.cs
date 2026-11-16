using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Data;
using ICTManagementSystem.Data.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace ICTManagementSystem.Tests;

public class AdmissionAndImportTests : IDisposable
{
    private readonly SqliteConnection connection = new("DataSource=:memory:");
    private readonly AppDbContext db;
    private readonly int batchId;
    public AdmissionAndImportTests()
    {
        connection.Open();
        db = new(new DbContextOptionsBuilder<AppDbContext>().UseSqlite(connection).Options);
        db.Database.EnsureCreated();
        var course = new Course { CourseCode = "DIT", CourseName = "DIT", Category = "IT", Duration = "2 Semesters", StandardFee = 22000 };
        db.Courses.Add(course); db.SaveChanges();
        var batch = new Batch { BatchCode = "DIT-2026", BatchName = "Morning", CourseId = course.Id };
        db.Batches.Add(batch); db.SaveChanges(); batchId = batch.Id;
    }
    private CreateStudentRequest Request(string reg = "", int? batch = null, string? cnic = null) => new(reg, "Test Student", "Father", "03001234567", null, cnic, null, null, null, batch ?? batchId, null, null);

    [Fact]
    public async Task AutomaticAdmission_CommitsStudentEnrollmentAndAudit_UsesCourseFee()
    {
        var service = new StudentService(db);
        var a = await service.CreateStudentAsync(Request());
        var b = await service.CreateStudentAsync(Request());
        Assert.Equal("ICT-DIT-001", a.StudentRegNo);
        Assert.Equal("ICT-DIT-002", b.StudentRegNo);
        Assert.Equal("+92-3001234567", a.ContactNumber);
        Assert.Equal(22000m, a.Enrollments.Single().TotalAgreedFee);
        Assert.Equal(2, await db.AuditLogs.CountAsync());
    }
    [Fact]
    public async Task DeletedRegistration_IsNeverReused_AndDifferentCourseHasOwnSequence()
    {
        db.Students.Add(new Student { StudentRegNo = "ict-dit-099", FullName = "Deleted", IsDeleted = true }); await db.SaveChangesAsync();
        var service = new StudentService(db);
        Assert.Equal("ICT-DIT-100", await service.GetNextRegistrationNoAsync("DIT"));
        Assert.Equal("ICT-ELL-001", await service.GetNextRegistrationNoAsync("ELL"));
    }
    [Fact]
    public async Task InvalidAdmission_DoesNotLeaveHalfSavedStudent()
    {
        await Assert.ThrowsAsync<InvalidOperationException>(() => new StudentService(db).CreateStudentAsync(Request(cnic: "123")));
        Assert.Equal(0, await db.Students.CountAsync());
        Assert.Equal(0, await db.Enrollments.CountAsync());
    }
    [Fact]
    public async Task InactiveBatch_BlocksAdmissions_AndReactivationWorks()
    {
        var course = new CourseService(db);
        await course.SetBatchActiveAsync(batchId, false);
        await Assert.ThrowsAsync<InvalidOperationException>(() => new StudentService(db).CreateStudentAsync(Request()));
        await course.SetBatchActiveAsync(batchId, true);
        Assert.NotNull(await new StudentService(db).CreateStudentAsync(Request()));
    }
    [Fact]
    public async Task DuplicateImportedRegistration_IsSkippedWithoutOverwriting()
    {
        await new StudentService(db).CreateStudentAsync(Request("ICT-DIT-123"));
        var outcome = await new AcademicImportService(db).ImportStudentsAsync(new(batchId, new() {
            new("ICT-DIT-123", "Changed Name", null, null, null, null),
            new("", "New Import", null, "+92-3111234567", "12345-1234567-1", null) }));
        Assert.Equal(1, outcome.Imported); Assert.Equal(1, outcome.Skipped); Assert.Empty(outcome.Errors);
        Assert.Equal("Test Student", (await db.Students.SingleAsync(s => s.StudentRegNo == "ICT-DIT-123")).FullName);
    }
    [Fact]
    public async Task MarksImport_SeparatesSemesters_AndRejectsForeignBatchStudent()
    {
        var student = await new StudentService(db).CreateStudentAsync(Request());
        var service = new AcademicImportService(db);
        var rows = new List<MarksImportRow> { new(student.StudentRegNo, "ICT", 100, 80) };
        Assert.Equal(1, (await service.ImportMarksAsync(new(batchId, 1, "2026-2027", rows))).Imported);
        Assert.Equal(1, (await service.ImportMarksAsync(new(batchId, 2, "2026-2027", rows))).Imported);
        Assert.Equal(2, await db.Examinations.CountAsync());
        Assert.Equal(2, await db.ExamResults.CountAsync());
        var invalid = await service.ImportMarksAsync(new(batchId, 1, "2026-2027", new() { new("NOT-ENROLLED", "ICT", 100, 90) }));
        Assert.Equal(0, invalid.Imported); Assert.NotEmpty(invalid.Errors);
        Assert.Equal(2, await db.ExamResults.CountAsync());
    }
    [Theory]
    [InlineData("03001234567", "+92-3001234567")]
    [InlineData("00923001234567", "+92-3001234567")]
    [InlineData("+92-3001234567", "+92-3001234567")]
    public void PakistanPhone_NormalizesLocalAndInternational(string input, string expected) => Assert.Equal(expected, PakistanIdentity.Phone(input));
    [Fact]
    public void PakistanIdentity_RejectsIncorrectLengths_FormatsCnic()
    {
        Assert.Throws<InvalidOperationException>(() => PakistanIdentity.Phone("0300123"));
        Assert.Throws<InvalidOperationException>(() => PakistanIdentity.Cnic("123456"));
        Assert.Equal("12345-1234567-1", PakistanIdentity.Cnic("1234512345671"));
    }
    [Fact]
    public async Task CourseDelete_ClosesAdmissionsAndPreservesExistingRecords()
    {
        var service = new CourseService(db);
        var student = await new StudentService(db).CreateStudentAsync(Request());
        Assert.True(await service.ArchiveCourseAsync(db.Courses.Single().Id));
        Assert.Equal("inactive", (await db.Batches.SingleAsync()).Status);
        Assert.Equal(1, await db.Students.CountAsync());
        Assert.Equal(1, await db.Enrollments.CountAsync());
        await Assert.ThrowsAsync<InvalidOperationException>(() => new StudentService(db).CreateStudentAsync(Request()));
        var course = await service.CreateCourseAsync(new("", "English Language", "Custom category", "3 Months", 5000, 0, null));
        Assert.Equal("ELL", course.CourseCode);
        Assert.True(await service.ArchiveCourseAsync(course.Id));
    }
    public void Dispose() { db.Dispose(); connection.Dispose(); }
}
