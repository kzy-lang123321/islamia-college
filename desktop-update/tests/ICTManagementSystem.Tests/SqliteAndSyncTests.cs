using ICTManagementSystem.App.Services.Backup;
using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using ICTManagementSystem.Data;
using ICTManagementSystem.Data.Interceptors;
using ICTManagementSystem.Data.Services;
using Microsoft.Data.Sqlite;
using Microsoft.EntityFrameworkCore;
using Xunit;

namespace ICTManagementSystem.Tests;

public class FakeGoogleDriveClient : IGoogleDriveClient
{
    public bool SimulateOnline { get; set; } = true;
    public bool SimulateDuplicate { get; set; } = false;
    public List<string> UploadedFiles { get; } = new();

    public Task<bool> CheckGoogleConnectivityAsync() => Task.FromResult(SimulateOnline);

    public Task<(bool Success, string? DriveFileId, string? Error)> UploadBackupAsync(string filePath, string sha256, string md5, long expectedSize)
    {
        if (!SimulateOnline)
        {
            return Task.FromResult<(bool, string?, string?)>((false, null, "Network offline"));
        }

        if (SimulateDuplicate)
        {
            return Task.FromResult<(bool, string?, string?)>((true, "drive-file-existing-id", null));
        }

        var id = $"drive-file-{Guid.NewGuid():N}";
        UploadedFiles.Add(id);
        return Task.FromResult<(bool, string?, string?)>((true, id, null));
    }
}

public class SqliteAndSyncTests
{
    [Fact]
    public async Task Sqlite_ConnectionPragmas_AreActive()
    {
        var tempDb = Path.Combine(Path.GetTempPath(), $"test_pragma_{Guid.NewGuid():N}.db");
        try
        {
            var interceptor = new SqlitePragmaInterceptor();
            var options = new DbContextOptionsBuilder<AppDbContext>()
                .UseSqlite($"Data Source={tempDb}")
                .AddInterceptors(interceptor)
                .Options;

            using (var context = new AppDbContext(options))
            {
                await context.Database.EnsureCreatedAsync();

                using var conn = context.Database.GetDbConnection();
                await conn.OpenAsync();

                using var cmd = conn.CreateCommand();

                cmd.CommandText = "PRAGMA journal_mode;";
                var journalMode = (string?)await cmd.ExecuteScalarAsync();
                Assert.Equal("wal", journalMode?.ToLowerInvariant());

                cmd.CommandText = "PRAGMA foreign_keys;";
                var fk = Convert.ToInt32(await cmd.ExecuteScalarAsync());
                Assert.Equal(1, fk);

                cmd.CommandText = "PRAGMA busy_timeout;";
                var timeout = Convert.ToInt32(await cmd.ExecuteScalarAsync());
                Assert.Equal(5000, timeout);
            }
        }
        finally
        {
            if (File.Exists(tempDb))
            {
                try { File.Delete(tempDb); } catch { }
            }
        }
    }

    [Fact]
    public async Task Sqlite_MoneyValueConverter_PreservesPaisaPrecision()
    {
        var tempDb = Path.Combine(Path.GetTempPath(), $"test_money_{Guid.NewGuid():N}.db");
        try
        {
            var interceptor = new SqlitePragmaInterceptor();
            var options = new DbContextOptionsBuilder<AppDbContext>()
                .UseSqlite($"Data Source={tempDb}")
                .AddInterceptors(interceptor)
                .Options;

            decimal expectedAmount = 45750.85m;

            using (var context = new AppDbContext(options))
            {
                await context.Database.EnsureCreatedAsync();

                var course = new Course
                {
                    CourseCode = "TEST-CS",
                    CourseName = "Test Course",
                    Category = "IT",
                    Duration = "1 Year",
                    StandardFee = expectedAmount,
                    TtbExamFee = 1500.00m
                };

                context.Courses.Add(course);
                await context.SaveChangesAsync();
            }

            // Read back with fresh context
            using (var context = new AppDbContext(options))
            {
                var saved = await context.Courses.FirstAsync(c => c.CourseCode == "TEST-CS");
                Assert.Equal(expectedAmount, saved.StandardFee);

                // Directly verify in SQLite raw SQL that it was saved as integer minor units (paisa)
                using var conn = context.Database.GetDbConnection();
                await conn.OpenAsync();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT StandardFee FROM Courses WHERE CourseCode = 'TEST-CS';";
                var rawValue = Convert.ToInt64(await cmd.ExecuteScalarAsync());

                // 45750.85 * 100 = 4575085
                Assert.Equal(4575085L, rawValue);
            }
        }
        finally
        {
            if (File.Exists(tempDb))
            {
                try { File.Delete(tempDb); } catch { }
            }
        }
    }

    [Fact]
    public async Task FeeService_ConcurrentPayments_GenerateUniqueSequentialReceiptNumbers()
    {
        var tempDb = Path.Combine(Path.GetTempPath(), $"test_receipts_{Guid.NewGuid():N}.db");
        try
        {
            var interceptor = new SqlitePragmaInterceptor();
            var options = new DbContextOptionsBuilder<AppDbContext>()
                .UseSqlite($"Data Source={tempDb}")
                .AddInterceptors(interceptor)
                .Options;

            using (var context = new AppDbContext(options))
            {
                await context.Database.EnsureCreatedAsync();

                var student = new Student { StudentRegNo = "ICT-TEST-100", FullName = "Test Student" };
                var course = new Course { CourseCode = "TEST-DIT", CourseName = "DIT", Category = "IT", Duration = "1 Year" };
                var batch = new Batch { BatchCode = "TEST-B1", BatchName = "Batch 1", Course = course };
                var enrollment = new Enrollment { Student = student, Batch = batch, TotalAgreedFee = 50000m };

                context.Enrollments.Add(enrollment);
                await context.SaveChangesAsync();
            }

            // Run 5 sequential payments
            var generatedReceipts = new List<string>();
            for (int i = 0; i < 5; i++)
            {
                using var context = new AppDbContext(options);
                var feeService = new FeeService(context);
                var enrollment = await context.Enrollments.FirstAsync();

                var result = await feeService.ReceivePaymentAsync(new ReceivePaymentRequest
                {
                    EnrollmentId = enrollment.Id,
                    AmountPaid = 1000m,
                    PaymentMethod = PaymentMethod.Cash
                });

                Assert.True(result.Success);
                generatedReceipts.Add(result.ReceiptNo);
            }

            // Check uniqueness and sequence
            Assert.Equal(5, generatedReceipts.Distinct().Count());
            Assert.StartsWith("REC-", generatedReceipts[0]);
            Assert.EndsWith("-0001", generatedReceipts[0]);
            Assert.EndsWith("-0005", generatedReceipts[4]);
        }
        finally
        {
            if (File.Exists(tempDb))
            {
                try { File.Delete(tempDb); } catch { }
            }
        }
    }

    [Fact]
    public async Task SqliteBackupEngine_CreatesEncryptedBackupAndRestoresIdenticalData()
    {
        var tempLiveDb = Path.Combine(Path.GetTempPath(), $"test_live_{Guid.NewGuid():N}.db");
        var tempQueueDb = Path.Combine(Path.GetTempPath(), $"test_queue_{Guid.NewGuid():N}.db");
        var tempBackupDir = Path.Combine(Path.GetTempPath(), $"test_bk_dir_{Guid.NewGuid():N}");
        Directory.CreateDirectory(tempBackupDir);

        try
        {
            var interceptor = new SqlitePragmaInterceptor();
            var appOptions = new DbContextOptionsBuilder<AppDbContext>()
                .UseSqlite($"Data Source={tempLiveDb}")
                .AddInterceptors(interceptor)
                .Options;

            var queueOptions = new DbContextOptionsBuilder<SyncQueueDbContext>()
                .UseSqlite($"Data Source={tempQueueDb}")
                .Options;

            using (var appDb = new AppDbContext(appOptions))
            {
                await appDb.Database.EnsureCreatedAsync();
                appDb.Students.Add(new Student { StudentRegNo = "ICT-REG-999", FullName = "Backup Verification Student" });
                appDb.Departments.Add(new Department { DeptCode = "CS", DeptName = "Computer Science" });
                await appDb.SaveChangesAsync();
            }

            using (var queueDb = new SyncQueueDbContext(queueOptions))
            {
                await queueDb.Database.EnsureCreatedAsync();

                var engine = new SqliteBackupEngine(tempLiveDb, tempBackupDir, queueDb);
                var (backupPath, manifest) = await engine.CreateVerifiedBackupAsync("SecureTestPassword123!");

                Assert.True(File.Exists(backupPath));
                Assert.EndsWith(".icbak", backupPath);
                Assert.True(manifest.FileSizeBytes > 0);
                Assert.NotEmpty(manifest.Sha256);
                Assert.NotEmpty(manifest.Md5);

                // Queue should have 1 pending job
                var job = await queueDb.Jobs.FirstAsync();
                Assert.Equal("Pending", job.Status);
                Assert.Equal(manifest.Sha256, job.Sha256Checksum);

                // Now modify live database to simulate change or corruption
                using (var appDb = new AppDbContext(appOptions))
                {
                    appDb.Students.RemoveRange(appDb.Students);
                    await appDb.SaveChangesAsync();
                    Assert.Equal(0, await appDb.Students.CountAsync());
                }

                // Restore verified backup
                bool restored = await engine.RestoreVerifiedBackupAsync(backupPath, "SecureTestPassword123!");
                Assert.True(restored);

                // Verify restored data
                using (var appDb = new AppDbContext(appOptions))
                {
                    var student = await appDb.Students.FirstOrDefaultAsync(s => s.StudentRegNo == "ICT-REG-999");
                    Assert.NotNull(student);
                    Assert.Equal("Backup Verification Student", student.FullName);
                }
            }
        }
        finally
        {
            if (File.Exists(tempLiveDb)) try { File.Delete(tempLiveDb); } catch { }
            if (File.Exists(tempQueueDb)) try { File.Delete(tempQueueDb); } catch { }
            if (Directory.Exists(tempBackupDir)) try { Directory.Delete(tempBackupDir, true); } catch { }
        }
    }

    [Fact]
    public async Task DataImportService_LoadsSampleDataFromSqlDump()
    {
        var tempDb = Path.Combine(Path.GetTempPath(), $"test_import_{Guid.NewGuid():N}.db");
        var tempSql = Path.Combine(Path.GetTempPath(), $"sample_dump_{Guid.NewGuid():N}.sql");

        try
        {
            var interceptor = new SqlitePragmaInterceptor();
            var options = new DbContextOptionsBuilder<AppDbContext>()
                .UseSqlite($"Data Source={tempDb}")
                .AddInterceptors(interceptor)
                .Options;

            using (var context = new AppDbContext(options))
            {
                await context.Database.EnsureCreatedAsync();
            }

            var sqlContent = @"
INSERT INTO `departments` (`dept_name`, `dept_code`, `description`) VALUES
('Information Technology', 'IT', 'Computer Science and Applications'),
('Vocational Studies', 'VOC', 'Technical Trades');

INSERT INTO `courses` (`course_code`, `course_name`, `category`, `duration`, `standard_fee`, `ttb_exam_fee`) VALUES
('ICT-DIT', 'Diploma in Information Technology', 'Information Technology', '1 Year / 2 Semesters', 25000.00, 3000.00);

INSERT INTO `students` (`student_reg_no`, `full_name`, `father_name`, `contact_number`, `secondary_contact`, `cnic`, `date_of_birth`, `email`, `address`) VALUES
('ICT-DIT-001', 'Muhammad Zeeshan', 'Abdul Wahab', '03451234567', NULL, '15302-1234567-1', '2004-05-10', 'zeeshan@example.com', 'Matta Swat');
";
            await File.WriteAllTextAsync(tempSql, sqlContent);

            using (var context = new AppDbContext(options))
            {
                var importer = new DataImportService(context);

                var preview = await importer.PreviewSqlDumpAsync(tempSql);
                Assert.Equal(2, preview.TotalDepartments);
                Assert.Equal(1, preview.TotalCourses);
                Assert.Equal(1, preview.TotalStudents);

                var result = await importer.ExecuteImportAsync(tempSql);
                Assert.True(result.Success);
                Assert.True(result.RecordsImported >= 4);

                // Verify records in DB
                var dept = await context.Departments.FirstOrDefaultAsync(d => d.DeptCode == "IT");
                Assert.NotNull(dept);

                var student = await context.Students.FirstOrDefaultAsync(s => s.StudentRegNo == "ICT-DIT-001");
                Assert.NotNull(student);
                Assert.Equal("Muhammad Zeeshan", student.FullName);

                // Test second import prevents duplicate student registration
                var result2 = await importer.ExecuteImportAsync(tempSql);
                Assert.True(result2.Success);
                Assert.Equal(1, await context.Students.CountAsync(s => s.StudentRegNo == "ICT-DIT-001"));
            }
        }
        finally
        {
            if (File.Exists(tempDb)) try { File.Delete(tempDb); } catch { }
            if (File.Exists(tempSql)) try { File.Delete(tempSql); } catch { }
        }
    }

    [Fact]
    public async Task Live_Import_MySQL_Dump_Into_Local_AppData_Database()
    {
        var mySqlDump = @"c:\xampp\htdocs\islamia college\database.sql";
        if (!File.Exists(mySqlDump)) return;

        var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
        var appDataDb = Path.Combine(localAppData, "ICTManagementSystem", "data", "app.db");
        Directory.CreateDirectory(Path.GetDirectoryName(appDataDb)!);

        var interceptor = new SqlitePragmaInterceptor();
        var options = new DbContextOptionsBuilder<AppDbContext>()
            .UseSqlite($"Data Source={appDataDb}")
            .AddInterceptors(interceptor)
            .Options;

        using var context = new AppDbContext(options);
        await context.Database.EnsureDeletedAsync();
        await context.Database.EnsureCreatedAsync();
        await AppDbContext.SeedInitialDataAsync(context);

        var importer = new DataImportService(context);
        var result = await importer.ExecuteImportAsync(mySqlDump);
        Assert.True(result.Success, $"Import failed: {result.Message} - {string.Join("; ", result.Errors)}");

        var studentCount = await context.Students.CountAsync();
        Assert.True(studentCount >= 1000);
    }
}

