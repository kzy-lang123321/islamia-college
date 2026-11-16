using ICTManagementSystem.Core.Enums;

namespace ICTManagementSystem.Core.Entities;

public class Examination : BaseEntity
{
    public string Name { get; set; } = string.Empty; // Monthly Test, Midterm, Annual Examination
    public string AcademicSession { get; set; } = string.Empty; // 2026-2027
    public string? StartDate { get; set; }
    public string? EndDate { get; set; }
    public string? ClassesIncluded { get; set; }
    public string? FeeCollectionDeadline { get; set; }
    public ExamStatus Status { get; set; } = ExamStatus.Active;
    public string? Notes { get; set; }

    public ICollection<ExamFeeStructure> FeeStructures { get; set; } = new List<ExamFeeStructure>();
    public ICollection<StudentExamAssessment> Assessments { get; set; } = new List<StudentExamAssessment>();
    public ICollection<ExamResult> Results { get; set; } = new List<ExamResult>();
}

public class ExamFeeStructure : BaseEntity
{
    public int ExaminationId { get; set; }
    public Examination? Examination { get; set; }

    public int? CourseId { get; set; }
    public Course? Course { get; set; }

    public string ClassLevel { get; set; } = string.Empty; // 1st Year, DIT-Part 1, etc.
    public decimal ExamFee { get; set; }
    public decimal PracticalFee { get; set; }
    public decimal PaperCharges { get; set; }
}

public class StudentExamAssessment : BaseEntity
{
    public int ExaminationId { get; set; }
    public Examination? Examination { get; set; }

    public int StudentId { get; set; }
    public Student? Student { get; set; }

    public decimal TotalAmount { get; set; }
    public decimal DiscountAmount { get; set; }
    public decimal PaidAmount { get; set; }
    public decimal BalanceAmount { get; set; }
    public string Status { get; set; } = "Unpaid"; // Unpaid, Partial, Paid
}

public class AttendanceRecord : BaseEntity
{
    public int StudentId { get; set; }
    public Student? Student { get; set; }

    public int BatchId { get; set; }
    public Batch? Batch { get; set; }

    public string Date { get; set; } = DateTime.UtcNow.ToString("yyyy-MM-dd"); // yyyy-MM-dd
    public AttendanceStatus Status { get; set; } = AttendanceStatus.Present;
    public string? Remarks { get; set; }
    public string RecordedBy { get; set; } = "Teacher";
}

public class ExamResult : BaseEntity
{
    public int ExaminationId { get; set; }
    public Examination? Examination { get; set; }

    public int StudentId { get; set; }
    public Student? Student { get; set; }

    public string SubjectName { get; set; } = string.Empty;
    public decimal TotalMarks { get; set; } = 100m;
    public decimal ObtainedMarks { get; set; }
    public string Grade { get; set; } = "F";
    public decimal Gpa { get; set; } = 0.0m;
    public string? Remarks { get; set; }
}

public class Certificate : BaseEntity
{
    public string CertificateNo { get; set; } = string.Empty; // CERT-YYYY-0001
    public int StudentId { get; set; }
    public Student? Student { get; set; }

    public CertificateType CertificateType { get; set; } = CertificateType.Character;
    public string IssueDate { get; set; } = DateTime.UtcNow.ToString("yyyy-MM-dd");
    public string CourseName { get; set; } = string.Empty;
    public string? GradeOrRemarks { get; set; }
    public string QrCodeData { get; set; } = string.Empty;
    public int ReprintCount { get; set; } = 0;
    public string IssuedBy { get; set; } = "Principal";
}

public class CertificateCounter
{
    public int Id { get; set; }
    public string Year { get; set; } = DateTime.UtcNow.Year.ToString();
    public int LastNumber { get; set; } = 0;
}

public class AuditLog
{
    public int Id { get; set; }
    public int? UserId { get; set; }
    public string Username { get; set; } = "system";
    public string Action { get; set; } = string.Empty; // Create, Update, Delete, Void, Import, Backup, Restore
    public string EntityName { get; set; } = string.Empty;
    public string? EntityId { get; set; }
    public string? OldValuesJson { get; set; }
    public string? NewValuesJson { get; set; }
    public DateTime TimestampUtc { get; set; } = DateTime.UtcNow;
}

public class SystemSetting
{
    public int Id { get; set; }
    public string Key { get; set; } = string.Empty;
    public string Value { get; set; } = string.Empty;
    public string? Description { get; set; }
}

