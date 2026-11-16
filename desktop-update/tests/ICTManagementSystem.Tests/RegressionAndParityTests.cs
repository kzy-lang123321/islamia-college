using System.Text.Json;
using ICTManagementSystem.App.Bridge;
using ICTManagementSystem.App.Services.Backup;
using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using ICTManagementSystem.Core.Interfaces;
using ICTManagementSystem.Data;
using ICTManagementSystem.Data.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging.Abstractions;
using Xunit;

namespace ICTManagementSystem.Tests;

public class RegressionAndParityTests : IDisposable
{
    private readonly SqliteConnection _connection;
    private readonly AppDbContext _context;
    private readonly ServiceProvider _serviceProvider;

    public RegressionAndParityTests()
    {
        _connection = new SqliteConnection("DataSource=:memory:");
        _connection.Open();

        var services = new ServiceCollection();
        services.AddDbContext<AppDbContext>(options =>
        {
            options.UseSqlite(_connection);
        });

        services.AddScoped<IStudentService, StudentService>();
        services.AddScoped<ICourseService, CourseService>();
        services.AddScoped<IFeeService, FeeService>();
        services.AddScoped<IDashboardService, DashboardService>();
        services.AddScoped<IAuthService, AuthService>();
        services.AddScoped<IAttendanceService, AttendanceService>();
        services.AddScoped<IResultService, ResultService>();
        services.AddScoped<ICertificateService, CertificateService>();
        services.AddScoped<IDataImportService, DataImportService>();
        services.AddScoped<IBackupService, BackupService>();

        services.AddSingleton(new JsBridge(services.BuildServiceProvider(), NullLogger<JsBridge>.Instance));

        _serviceProvider = services.BuildServiceProvider();
        _context = _serviceProvider.GetRequiredService<AppDbContext>();
        _context.Database.EnsureCreated();

        SeedTestData();
    }

    private void SeedTestData()
    {
        var dept = new Department { DeptCode = "IT", DeptName = "Information Technology" };
        _context.Departments.Add(dept);
        _context.SaveChanges();

        var course = new Course
        {
            CourseCode = "DIT",
            CourseName = "Diploma in Information Technology",
            Category = "Information Technology",
            Duration = "1 Year",
            StandardFee = 18000m,
            TtbExamFee = 2500m
        };
        _context.Courses.Add(course);
        _context.SaveChanges();

        var batch = new Batch
        {
            BatchCode = "DIT-2024-A",
            BatchName = "DIT Batch 2024 (Spring)",
            CourseId = course.Id,
            StartDate = "2024-01-15",
            Status = "active"
        };
        _context.Batches.Add(batch);
        _context.SaveChanges();

        var student = new Student
        {
            StudentRegNo = "ICT-DIT-411",
            FullName = "Muhammad Tariq Khan",
            FatherName = "Fazal Khan",
            ContactNumber = "03451234567",
            Address = "Matta, Swat"
        };
        _context.Students.Add(student);
        _context.SaveChanges();

        var enrollment = new Enrollment
        {
            StudentId = student.Id,
            BatchId = batch.Id,
            RollNumber = "DIT-411",
            TotalAgreedFee = 18000m,
            EnrollmentDate = "2024-01-20",
            Status = EnrollmentStatus.Active
        };
        _context.Enrollments.Add(enrollment);
        _context.SaveChanges();

        var feeTx = new FeeTransaction
        {
            EnrollmentId = enrollment.Id,
            ReceiptNo = "REC-202401-0001",
            AmountPaid = 8000m,
            DueAmount = 10000m,
            PaymentDate = "2024-01-20",
            PaymentMethod = PaymentMethod.Cash,
            ReceivedBy = "Cashier"
        };
        _context.FeeTransactions.Add(feeTx);
        _context.SaveChanges();
    }

    [Fact]
    public async Task JsonCycleRegression_LoadStudentWithEnrollmentCoursesFees_SerializesWithoutCycleException()
    {
        // Act: Invoke student retrieval through the bridge exactly as the React frontend does
        var bridge = new JsBridge(_serviceProvider, NullLogger<JsBridge>.Instance);
        var jsonResponse = await bridge.InvokeAsync("students.getById", "{\"id\":1}");

        // Assert: Response must be valid JSON with success=true and contain non-cyclic DTO
        Assert.NotNull(jsonResponse);
        Assert.DoesNotContain("object cycle", jsonResponse, StringComparison.OrdinalIgnoreCase);

        using var doc = JsonDocument.Parse(jsonResponse);
        var root = doc.RootElement;
        Assert.True(root.GetProperty("success").GetBoolean());

        var data = root.GetProperty("data");
        Assert.Equal("ICT-DIT-411", data.GetProperty("studentRegNo").GetString());
        Assert.Equal("Muhammad Tariq Khan", data.GetProperty("fullName").GetString());

        var enrollments = data.GetProperty("enrollments");
        Assert.True(enrollments.GetArrayLength() > 0);
        Assert.Equal("Diploma in Information Technology", enrollments[0].GetProperty("courseName").GetString());

        var feeTxs = data.GetProperty("feeTransactions");
        Assert.True(feeTxs.GetArrayLength() > 0);
        Assert.Equal("REC-202401-0001", feeTxs[0].GetProperty("receiptNo").GetString());
        Assert.Equal(8000m, feeTxs[0].GetProperty("amountPaid").GetDecimal());
    }

    [Fact]
    public async Task CourseCRUD_CreateReadUpdateArchive_OperatesCleanly()
    {
        var courseService = _serviceProvider.GetRequiredService<ICourseService>();

        // 1. Create
        var newCourse = await courseService.CreateCourseAsync(new CreateCourseRequest(
            "AI-ML",
            "Artificial Intelligence & Machine Learning",
            "Advanced Computing",
            "6 Months",
            25000m,
            3000m,
            "Hands-on AI course"
        ));
        Assert.True(newCourse.Id > 0);
        Assert.Equal("AI-ML", newCourse.CourseCode);

        // 2. Read
        var detail = await courseService.GetCourseByIdAsync(newCourse.Id);
        Assert.NotNull(detail);
        Assert.Equal("Artificial Intelligence & Machine Learning", detail.CourseName);

        // 3. Update
        var updated = await courseService.UpdateCourseAsync(new UpdateCourseRequest(
            newCourse.Id,
            "AI-ML",
            "Applied AI & Deep Learning",
            "Advanced Computing",
            "6 Months",
            28000m,
            3500m,
            "Updated syllabus"
        ));
        Assert.Equal("Applied AI & Deep Learning", updated.CourseName);
        Assert.Equal(28000m, updated.StandardFee);

        // 4. Archive (Soft Delete)
        var archived = await courseService.ArchiveCourseAsync(newCourse.Id);
        Assert.True(archived);

        var afterArchive = await courseService.GetCourseByIdAsync(newCourse.Id);
        Assert.Null(afterArchive);
    }

    [Fact]
    public async Task NumberingAndValidation_GeneratesSequentialRegAndReceiptNumbers()
    {
        var studentService = _serviceProvider.GetRequiredService<IStudentService>();
        var feeService = _serviceProvider.GetRequiredService<IFeeService>();

        var nextReg = await studentService.GetNextRegistrationNoAsync("DIT");
        Assert.StartsWith("ICT-DIT-", nextReg);

        var nextReceipt = await feeService.GetNextReceiptNumberAsync();
        var currentMonth = DateTime.UtcNow.ToString("yyyyMM");
        Assert.StartsWith($"REC-{currentMonth}-", nextReceipt);
    }

    [Fact]
    public async Task Task2_AcceptanceNumbers_LiveMySQLDumpImport_MatchesWebSystemExactMetrics()
    {
        var dumpPath = @"E:\ICTManagementSystem\data\live_mysql_dump.sql";
        if (!File.Exists(dumpPath)) return;

        using var testConn = new SqliteConnection("DataSource=:memory:");
        await testConn.OpenAsync();

        var services = new ServiceCollection();
        services.AddDbContext<AppDbContext>(opts => opts.UseSqlite(testConn));
        services.AddScoped<IDataImportService, DataImportService>();
        using var sp = services.BuildServiceProvider();

        using (var scope = sp.CreateScope())
        {
            var db = scope.ServiceProvider.GetRequiredService<AppDbContext>();
            await db.Database.EnsureCreatedAsync();

            var importer = scope.ServiceProvider.GetRequiredService<IDataImportService>();
            var res = await importer.ExecuteImportAsync(dumpPath);
            Assert.True(res.Success, res.Message);

            var totalStudents = await db.Students.CountAsync();
            var activeEnrollments = await db.Enrollments.CountAsync(e => e.Status == EnrollmentStatus.Active);
            var courses = await db.Courses.CountAsync();
            var batches = await db.Batches.CountAsync();
            var staff = await db.StaffMembers.CountAsync();
            var collected = await db.FeeTransactions.SumAsync(t => t.AmountPaid);
            var agreed = await db.Enrollments.SumAsync(e => e.TotalAgreedFee);
            var dues = Math.Round(agreed - collected, 0);

            Assert.Equal(1454, totalStudents);
            Assert.Equal(1516, activeEnrollments);
            Assert.Equal(93, courses);
            Assert.Equal(52, batches);
            Assert.Equal(4, staff);
            Assert.Equal(235742m, Math.Round(collected, 0));
            Assert.Equal(77562m, dues);
        }
    }

    [Fact]
    public void SupplierModule_EnsuresNoSupplierEntitiesExistInModel()
    {
        // Task 4 verification: verify that no entity with name 'Supplier' exists in AppDbContext
        var entityTypes = _context.Model.GetEntityTypes().Select(e => e.ClrType.Name).ToList();
        Assert.DoesNotContain("Supplier", entityTypes);
        Assert.DoesNotContain("SupplierBill", entityTypes);
        Assert.DoesNotContain("SupplierPayment", entityTypes);
    }

    public void Dispose()
    {
        _context.Dispose();
        _connection.Dispose();
        _serviceProvider.Dispose();
    }
}
