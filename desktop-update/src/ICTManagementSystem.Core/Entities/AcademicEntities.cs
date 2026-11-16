using ICTManagementSystem.Core.Enums;

namespace ICTManagementSystem.Core.Entities;

public class User : BaseEntity
{
    public string Username { get; set; } = string.Empty;
    public string PasswordHash { get; set; } = string.Empty;
    public string FullName { get; set; } = string.Empty;
    public string? Phone { get; set; }
    public string? Email { get; set; }
    public UserRole Role { get; set; } = UserRole.Cashier;
    public bool IsActive { get; set; } = true;
}

public class Department : BaseEntity
{
    public string DeptCode { get; set; } = string.Empty;
    public string DeptName { get; set; } = string.Empty;
    public string? Description { get; set; }
}

public class Course : BaseEntity
{
    public string CourseCode { get; set; } = string.Empty;
    public string CourseName { get; set; } = string.Empty;
    public string Category { get; set; } = string.Empty;
    public string Duration { get; set; } = string.Empty;
    public decimal StandardFee { get; set; }
    public decimal TtbExamFee { get; set; }
    public string? Description { get; set; }

    public ICollection<Batch> Batches { get; set; } = new List<Batch>();
}

public class Batch : BaseEntity
{
    public string BatchCode { get; set; } = string.Empty;
    public string BatchName { get; set; } = string.Empty;
    public int CourseId { get; set; }
    public Course? Course { get; set; }
    public string? StartDate { get; set; } // yyyy-MM-dd
    public string? EndDate { get; set; }   // yyyy-MM-dd
    public string Status { get; set; } = "active"; // active, completed, upcoming

    public ICollection<Enrollment> Enrollments { get; set; } = new List<Enrollment>();
}

public class Student : BaseEntity
{
    public string StudentRegNo { get; set; } = string.Empty;
    public string FullName { get; set; } = string.Empty;
    public string? FatherName { get; set; }
    public string? ContactNumber { get; set; }
    public string? SecondaryContact { get; set; }
    public string? Cnic { get; set; }
    public string? DateOfBirth { get; set; } // yyyy-MM-dd
    public string? Email { get; set; }
    public string? Address { get; set; }

    public ICollection<Enrollment> Enrollments { get; set; } = new List<Enrollment>();
    public ICollection<AttendanceRecord> AttendanceRecords { get; set; } = new List<AttendanceRecord>();
    public ICollection<ExamResult> ExamResults { get; set; } = new List<ExamResult>();
    public ICollection<Certificate> Certificates { get; set; } = new List<Certificate>();
}

public class Enrollment : BaseEntity
{
    public int StudentId { get; set; }
    public Student? Student { get; set; }

    public int BatchId { get; set; }
    public Batch? Batch { get; set; }

    public string? RollNumber { get; set; }
    public string? AcademicSession { get; set; }
    public string? ClassLevel { get; set; }
    public string? EnrollmentDate { get; set; } // yyyy-MM-dd

    public decimal TotalAgreedFee { get; set; }
    public decimal AdmissionFee { get; set; }
    public decimal DiscountAmount { get; set; }
    public string? ReceiptSlipNo { get; set; }
    public string? TuitionClass { get; set; }
    public string? TuitionSubjects { get; set; }
    public EnrollmentStatus Status { get; set; } = EnrollmentStatus.Active;

    public ICollection<FeeTransaction> FeeTransactions { get; set; } = new List<FeeTransaction>();
}

public class AcademicPosition : BaseEntity
{
    public int? StudentId { get; set; }
    public Student? Student { get; set; }
    public string SessionName { get; set; } = string.Empty;
    public string BatchName { get; set; } = string.Empty;
    public string PositionRank { get; set; } = string.Empty; // 1st, 2nd, 3rd, Appreciation
    public string StudentName { get; set; } = string.Empty;
    public int? MarksObtained { get; set; }
    public int? TotalMarks { get; set; }
    public string? AwardTitle { get; set; }
}

public class BoardExamination : BaseEntity
{
    public int StudentId { get; set; }
    public Student? Student { get; set; }
    public string BoardName { get; set; } = "Trade Testing Board (TTB) Peshawar";
    public string? BoardRegistrationNo { get; set; }
    public string? BoardRollNo { get; set; }
    public string TradeCourseName { get; set; } = string.Empty;
    public string? RegistrationDate { get; set; }
    public decimal ExamFeePaid { get; set; }
    public BoardExamStatus ExamStatus { get; set; } = BoardExamStatus.Enrolled;
    public decimal? MarksObtained { get; set; }
    public decimal? TotalMarks { get; set; }
}

