using System.Reflection;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Data.Converters;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data;

public class AppDbContext : DbContext
{
    public AppDbContext(DbContextOptions<AppDbContext> options) : base(options)
    {
    }

    public DbSet<User> Users => Set<User>();
    public DbSet<Department> Departments => Set<Department>();
    public DbSet<Course> Courses => Set<Course>();
    public DbSet<Batch> Batches => Set<Batch>();
    public DbSet<Student> Students => Set<Student>();
    public DbSet<Enrollment> Enrollments => Set<Enrollment>();
    public DbSet<FeeTransaction> FeeTransactions => Set<FeeTransaction>();
    public DbSet<CampusExpense> CampusExpenses => Set<CampusExpense>();
    public DbSet<StaffMember> StaffMembers => Set<StaffMember>();
    public DbSet<StaffPayroll> StaffPayrolls => Set<StaffPayroll>();
    public DbSet<CapitalAsset> CapitalAssets => Set<CapitalAsset>();
    public DbSet<AssetPaymentInstallment> AssetPaymentInstallments => Set<AssetPaymentInstallment>();
    public DbSet<LoanAndAdvance> LoansAndAdvances => Set<LoanAndAdvance>();
    public DbSet<BoardExamination> BoardExaminations => Set<BoardExamination>();
    public DbSet<AcademicPosition> AcademicPositions => Set<AcademicPosition>();
    public DbSet<Examination> Examinations => Set<Examination>();
    public DbSet<ExamFeeStructure> ExamFeeStructures => Set<ExamFeeStructure>();
    public DbSet<StudentExamAssessment> StudentExamAssessments => Set<StudentExamAssessment>();
    public DbSet<AttendanceRecord> AttendanceRecords => Set<AttendanceRecord>();
    public DbSet<ExamResult> ExamResults => Set<ExamResult>();
    public DbSet<Certificate> Certificates => Set<Certificate>();
    public DbSet<ReceiptCounter> ReceiptCounters => Set<ReceiptCounter>();
    public DbSet<CertificateCounter> CertificateCounters => Set<CertificateCounter>();
    public DbSet<AuditLog> AuditLogs => Set<AuditLog>();
    public DbSet<SystemSetting> SystemSettings => Set<SystemSetting>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        base.OnModelCreating(modelBuilder);

        var moneyConverter = new MoneyValueConverter();

        // Unique constraints & indexes
        modelBuilder.Entity<User>(b =>
        {
            b.HasIndex(u => u.Username).IsUnique();
        });

        modelBuilder.Entity<Department>(b =>
        {
            b.HasIndex(d => d.DeptCode).IsUnique();
            b.HasIndex(d => d.DeptName).IsUnique();
        });

        modelBuilder.Entity<Course>(b =>
        {
            b.HasIndex(c => c.CourseCode).IsUnique();
            b.Property(c => c.StandardFee).HasConversion(moneyConverter);
            b.Property(c => c.TtbExamFee).HasConversion(moneyConverter);
        });

        modelBuilder.Entity<Batch>(b =>
        {
            b.HasIndex(b => b.BatchCode).IsUnique();
            b.HasOne(b => b.Course)
             .WithMany(c => c.Batches)
             .HasForeignKey(b => b.CourseId)
             .OnDelete(DeleteBehavior.Restrict);
        });

        modelBuilder.Entity<Student>(b =>
        {
            b.HasIndex(s => s.StudentRegNo).IsUnique();
            b.HasIndex(s => s.FullName);
            b.HasIndex(s => s.ContactNumber);
            b.HasQueryFilter(s => !s.IsDeleted);
        });

        modelBuilder.Entity<Enrollment>(b =>
        {
            b.HasIndex(e => new { e.BatchId, e.RollNumber });
            b.Property(e => e.TotalAgreedFee).HasConversion(moneyConverter);
            b.Property(e => e.AdmissionFee).HasConversion(moneyConverter);
            b.Property(e => e.DiscountAmount).HasConversion(moneyConverter);

            b.HasOne(e => e.Student)
             .WithMany(s => s.Enrollments)
             .HasForeignKey(e => e.StudentId)
             .OnDelete(DeleteBehavior.Restrict);

            b.HasOne(e => e.Batch)
             .WithMany(b => b.Enrollments)
             .HasForeignKey(e => e.BatchId)
             .OnDelete(DeleteBehavior.Restrict);

            b.HasQueryFilter(e => !e.IsDeleted);
        });

        modelBuilder.Entity<FeeTransaction>(b =>
        {
            b.HasIndex(f => f.ReceiptNo);
            b.HasIndex(f => f.PaymentDate);
            b.Property(f => f.AmountPaid).HasConversion(moneyConverter);
            b.Property(f => f.DueAmount).HasConversion(moneyConverter);
            b.Property(f => f.FineAmount).HasConversion(moneyConverter);
            b.Property(f => f.DiscountAmount).HasConversion(moneyConverter);

            b.HasOne(f => f.Enrollment)
             .WithMany(e => e.FeeTransactions)
             .HasForeignKey(f => f.EnrollmentId)
             .OnDelete(DeleteBehavior.Restrict);

            b.HasQueryFilter(f => !f.IsDeleted);
        });

        modelBuilder.Entity<CampusExpense>(b =>
        {
            b.Property(c => c.AmountDue).HasConversion(moneyConverter);
            b.Property(c => c.AmountPaid).HasConversion(moneyConverter);
            b.Property(c => c.BalanceDues).HasConversion(moneyConverter);
            b.HasQueryFilter(c => !c.IsDeleted);
        });

        modelBuilder.Entity<StaffMember>(b =>
        {
            b.HasIndex(s => s.StaffCode).IsUnique();
            b.Property(s => s.BaseSalary).HasConversion(moneyConverter);
            b.HasQueryFilter(s => !s.IsDeleted);
        });

        modelBuilder.Entity<StaffPayroll>(b =>
        {
            b.Property(s => s.SalaryAgreed).HasConversion(moneyConverter);
            b.Property(s => s.InstallmentP1).HasConversion(moneyConverter);
            b.Property(s => s.InstallmentP2).HasConversion(moneyConverter);
            b.Property(s => s.InstallmentP3).HasConversion(moneyConverter);
            b.Property(s => s.TotalPaid).HasConversion(moneyConverter);
            b.Property(s => s.Dues).HasConversion(moneyConverter);
            b.HasQueryFilter(s => !s.IsDeleted);
        });

        modelBuilder.Entity<CapitalAsset>(b =>
        {
            b.Property(c => c.TotalCost).HasConversion(moneyConverter);
            b.Property(c => c.TotalPaid).HasConversion(moneyConverter);
            b.Property(c => c.Dues).HasConversion(moneyConverter);
            b.HasQueryFilter(c => !c.IsDeleted);
        });

        modelBuilder.Entity<AssetPaymentInstallment>(b =>
        {
            b.Property(c => c.AmountPaid).HasConversion(moneyConverter);
            b.HasQueryFilter(c => !c.IsDeleted);
        });

        modelBuilder.Entity<LoanAndAdvance>(b =>
        {
            b.Property(l => l.AmountDisbursed).HasConversion(moneyConverter);
            b.Property(l => l.AmountRepaid).HasConversion(moneyConverter);
            b.Property(l => l.BalanceDue).HasConversion(moneyConverter);
            b.HasQueryFilter(l => !l.IsDeleted);
        });

        modelBuilder.Entity<BoardExamination>(b =>
        {
            b.Property(b => b.ExamFeePaid).HasConversion(moneyConverter);
            b.HasQueryFilter(b => !b.IsDeleted);
        });

        modelBuilder.Entity<ExamFeeStructure>(b =>
        {
            b.Property(e => e.ExamFee).HasConversion(moneyConverter);
            b.Property(e => e.PracticalFee).HasConversion(moneyConverter);
            b.Property(e => e.PaperCharges).HasConversion(moneyConverter);
            b.HasQueryFilter(e => !e.IsDeleted);
        });

        modelBuilder.Entity<StudentExamAssessment>(b =>
        {
            b.Property(s => s.TotalAmount).HasConversion(moneyConverter);
            b.Property(s => s.DiscountAmount).HasConversion(moneyConverter);
            b.Property(s => s.PaidAmount).HasConversion(moneyConverter);
            b.Property(s => s.BalanceAmount).HasConversion(moneyConverter);
            b.HasIndex(s => new { s.ExaminationId, s.StudentId }).IsUnique();
            b.HasQueryFilter(s => !s.IsDeleted);
        });

        modelBuilder.Entity<AttendanceRecord>(b =>
        {
            b.HasIndex(a => new { a.StudentId, a.Date }).IsUnique();
            b.HasQueryFilter(a => !a.IsDeleted);
        });

        modelBuilder.Entity<ExamResult>(b =>
        {
            b.HasIndex(e => new { e.ExaminationId, e.StudentId, e.SubjectName }).IsUnique();
            b.HasQueryFilter(e => !e.IsDeleted);
        });

        modelBuilder.Entity<Certificate>(b =>
        {
            b.HasIndex(c => c.CertificateNo).IsUnique();
            b.HasQueryFilter(c => !c.IsDeleted);
        });

        modelBuilder.Entity<ReceiptCounter>(b =>
        {
            b.HasIndex(r => new { r.Prefix, r.YearMonth }).IsUnique();
        });

        modelBuilder.Entity<CertificateCounter>(b =>
        {
            b.HasIndex(c => c.Year).IsUnique();
        });

        modelBuilder.Entity<SystemSetting>(b =>
        {
            b.HasIndex(s => s.Key).IsUnique();
        });
    }

    /// <summary>
    /// Seeds initial admin user, system branding settings, and counters if empty
    /// </summary>
    public static async Task SeedInitialDataAsync(AppDbContext context)
    {
        if (!await context.Users.AnyAsync())
        {
            context.Users.Add(new User
            {
                Username = "admin",
                // default password 'admin123' bcrypt hashed
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("admin123"),
                FullName = "Administrator ICT",
                Role = Core.Enums.UserRole.Admin,
                IsActive = true
            });

            context.Users.Add(new User
            {
                Username = "accountant",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("accountant123"),
                FullName = "Accounts Officer",
                Role = Core.Enums.UserRole.Cashier,
                IsActive = true
            });

            context.Users.Add(new User
            {
                Username = "teacher",
                PasswordHash = BCrypt.Net.BCrypt.HashPassword("teacher123"),
                FullName = "Instructor / Faculty",
                Role = Core.Enums.UserRole.Teacher,
                IsActive = true
            });
        }

        if (!await context.SystemSettings.AnyAsync())
        {
            context.SystemSettings.AddRange(
                new SystemSetting { Key = "INSTITUTION_NAME", Value = "ISLAMIA COLLEGE OF TECHNOLOGY", Description = "College Name" },
                new SystemSetting { Key = "CAMPUS_LOCATION", Value = "Matta Swat, Khyber Pakhtunkhwa", Description = "Campus Location" },
                new SystemSetting { Key = "AFFILIATION", Value = "Affiliated with KPK Trade Testing Board (TTB)", Description = "Board Affiliation" },
                new SystemSetting { Key = "PHONE", Value = "+92 946 795000", Description = "Contact Phone" },
                new SystemSetting { Key = "WEBSITE", Value = "www.ictswat.edu.pk", Description = "Official Website" },
                new SystemSetting { Key = "DRIVE_ACCOUNT", Value = "aminullahameen@gmail.com", Description = "Google Drive Backup Account" },
                new SystemSetting { Key = "CURRENCY_SYMBOL", Value = "Rs.", Description = "Currency Indicator" },
                new SystemSetting { Key = "SIGNATORY_PREPARED_BY", Value = "Prepared By (Registrar)", Description = "First signature title" },
                new SystemSetting { Key = "SIGNATORY_VERIFIED_BY", Value = "Verified / Accounts Officer", Description = "Second signature title" },
                new SystemSetting { Key = "SIGNATORY_PRINCIPAL", Value = "Principal / Director ICT", Description = "Third signature title" }
            );
        }

        await context.SaveChangesAsync();
    }
}

