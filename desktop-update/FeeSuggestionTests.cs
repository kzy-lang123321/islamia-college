using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Data;
using ICTManagementSystem.Data.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace ICTManagementSystem.Tests;

public class FeeSuggestionTests : IDisposable
{
    private readonly SqliteConnection connection = new("DataSource=:memory:");
    private readonly AppDbContext db;
    private readonly FeeService service;
    public FeeSuggestionTests()
    {
        connection.Open();
        db = new(new DbContextOptionsBuilder<AppDbContext>().UseSqlite(connection).Options);
        db.Database.EnsureCreated();
        var course = new Course { CourseCode = "DIT", CourseName = "DIT", Category = "IT", Duration = "1 Year" };
        var morning = new Batch { BatchCode = "DIT-AM", BatchName = "Morning", Course = course };
        var evening = new Batch { BatchCode = "DIT-PM", BatchName = "Evening", Course = course };
        var a = new Student { StudentRegNo = "ICT-DIT-001", FullName = "Ali Khan", FatherName = "Akbar", ContactNumber = "+92-3001234567", Cnic = "12345-1234567-1" };
        var b = new Student { StudentRegNo = "ICT-DIT-002", FullName = "Imran Khan", FatherName = "Bashir" };
        db.Enrollments.AddRange(
            new Enrollment { Student = a, Batch = morning, RollNumber = "ROLL-A", TotalAgreedFee = 10000 },
            new Enrollment { Student = a, Batch = evening, RollNumber = "ROLL-B", TotalAgreedFee = 20000 },
            new Enrollment { Student = b, Batch = morning, RollNumber = "ROLL-C", TotalAgreedFee = 30000 });
        db.SaveChanges(); service = new(db);
    }
    [Fact]
    public async Task CommonName_ReturnsAllEnrollmentChoices_AndSelectedEnrollmentHasCorrectFee()
    {
        var matches = await service.SuggestStudentsForFeeAsync("khan");
        Assert.Equal(3, matches.Count);
        var evening = matches.Single(m => m.BatchCode == "DIT-PM");
        var details = await service.GetStudentFeeDetailsAsync(evening.EnrollmentId);
        Assert.Equal("Ali Khan", details!.FullName);
        Assert.Equal("DIT-PM", details.BatchCode);
        Assert.Equal(20000m, details.TotalAgreedFee);
    }
    [Fact]
    public async Task Lookup_SupportsRegistrationFatherPhoneCnicRollAndUid()
    {
        Assert.Single(await service.SuggestStudentsForFeeAsync("ICT-DIT-002"));
        Assert.Single(await service.SuggestStudentsForFeeAsync("Bashir"));
        Assert.Equal(2, (await service.SuggestStudentsForFeeAsync("3001234567")).Count);
        Assert.Equal(2, (await service.SuggestStudentsForFeeAsync("1234512345671")).Count);
        Assert.Single(await service.SuggestStudentsForFeeAsync("ROLL-B"));
        var uid = (await db.Students.SingleAsync(s => s.FullName == "Ali Khan")).Id;
        Assert.Contains(await service.SuggestStudentsForFeeAsync(uid.ToString()), m => m.StudentId == uid);
    }
    [Fact]
    public async Task DeletedStudentsAndEnrollments_AreNotSuggested()
    {
        (await db.Students.SingleAsync(s => s.FullName == "Imran Khan")).IsDeleted = true;
        (await db.Enrollments.SingleAsync(e => e.RollNumber == "ROLL-B")).IsDeleted = true;
        await db.SaveChangesAsync();
        var matches = await service.SuggestStudentsForFeeAsync("khan");
        Assert.Single(matches); Assert.Equal("ROLL-A", matches[0].RollNumber);
        Assert.Empty(await service.SuggestStudentsForFeeAsync("not-found"));
        Assert.Empty(await service.SuggestStudentsForFeeAsync(" "));
    }
    [Fact]
    public async Task Suggestions_AreLimitedToTen()
    {
        var batch = await db.Batches.FirstAsync();
        for (var i = 0; i < 15; i++) db.Enrollments.Add(new Enrollment {
            BatchId = batch.Id, Student = new Student { StudentRegNo = $"TEST-{i}", FullName = $"Khan {i}" } });
        await db.SaveChangesAsync();
        Assert.Equal(10, (await service.SuggestStudentsForFeeAsync("khan")).Count);
    }
    public void Dispose() { db.Dispose(); connection.Dispose(); }
}
