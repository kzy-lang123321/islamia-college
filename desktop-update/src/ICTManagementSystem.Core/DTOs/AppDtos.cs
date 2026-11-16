using ICTManagementSystem.Core.Enums;

namespace ICTManagementSystem.Core.DTOs;

public class LoginRequest
{
    public string Username { get; set; } = string.Empty;
    public string Password { get; set; } = string.Empty;
}

public class LoginResponse
{
    public bool Success { get; set; }
    public string Message { get; set; } = string.Empty;
    public int? UserId { get; set; }
    public string Username { get; set; } = string.Empty;
    public string FullName { get; set; } = string.Empty;
    public string Role { get; set; } = string.Empty;
    public string Token { get; set; } = string.Empty;
}

public class StudentFeeDetailsDto
{
    public int EnrollmentId { get; set; }
    public int StudentId { get; set; }
    public string StudentRegNo { get; set; } = string.Empty;
    public string FullName { get; set; } = string.Empty;
    public string FatherName { get; set; } = string.Empty;
    public string CourseName { get; set; } = string.Empty;
    public string BatchCode { get; set; } = string.Empty;
    public string? RollNumber { get; set; }
    public decimal TotalAgreedFee { get; set; }
    public decimal TotalPaid { get; set; }
    public decimal CurrentDues { get; set; }
    public decimal PendingFines { get; set; }
    public decimal AvailableDiscounts { get; set; }
    public decimal TotalPayable { get; set; }
    public List<FeeHistoryItemDto> PaymentHistory { get; set; } = new();
}

public class FeeHistoryItemDto
{
    public int Id { get; set; }
    public string ReceiptNo { get; set; } = string.Empty;
    public decimal AmountPaid { get; set; }
    public decimal DueAmount { get; set; }
    public string PaymentDate { get; set; } = string.Empty;
    public string PaymentMethod { get; set; } = string.Empty;
    public string ReceivedBy { get; set; } = string.Empty;
    public bool IsCancelled { get; set; }
}

public class ReceivePaymentRequest
{
    public int EnrollmentId { get; set; }
    public decimal AmountPaid { get; set; }
    public decimal FineAmount { get; set; } = 0m;
    public decimal DiscountAmount { get; set; } = 0m;
    public PaymentMethod PaymentMethod { get; set; } = PaymentMethod.Cash;
    public string? ReferenceNo { get; set; }
    public string? BankName { get; set; }
    public string? ChequeDate { get; set; }
    public string? Remarks { get; set; }
    public string ReceivedBy { get; set; } = "Cashier";
}

public class ReceivePaymentResult
{
    public bool Success { get; set; }
    public string Message { get; set; } = string.Empty;
    public int TransactionId { get; set; }
    public string ReceiptNo { get; set; } = string.Empty;
    public decimal AmountPaid { get; set; }
    public decimal RemainingDues { get; set; }
    public string PaymentDate { get; set; } = string.Empty;
    public PrintableReceiptDto? ReceiptData { get; set; }
}

public class PrintableReceiptDto
{
    public string ReceiptNo { get; set; } = string.Empty;
    public string Date { get; set; } = string.Empty;
    public string StudentRegNo { get; set; } = string.Empty;
    public string StudentName { get; set; } = string.Empty;
    public string FatherName { get; set; } = string.Empty;
    public string CourseName { get; set; } = string.Empty;
    public string BatchCode { get; set; } = string.Empty;
    public string? RollNumber { get; set; }
    public decimal TotalAgreedFee { get; set; }
    public decimal PreviousPaid { get; set; }
    public decimal CurrentPaid { get; set; }
    public decimal RemainingBalance { get; set; }
    public string PaymentMethod { get; set; } = string.Empty;
    public string ReceivedBy { get; set; } = string.Empty;
    public string InstitutionName { get; set; } = "ISLAMIA COLLEGE OF TECHNOLOGY";
    public string CampusAddress { get; set; } = "Matta Swat, Khyber Pakhtunkhwa";
    public string Affiliation { get; set; } = "Affiliated with KPK Trade Testing Board (TTB)";
    public string Phone { get; set; } = "+92 946 795000";
    public string Website { get; set; } = "www.ictswat.edu.pk";
}

public class DashboardSummaryDto
{
    public int TotalStudents { get; set; }
    public int ActiveBatches { get; set; }
    public decimal TotalFeesCollected { get; set; }
    public decimal TotalOutstandingDues { get; set; }
    public decimal TotalCampusExpenses { get; set; }
    public decimal TodayFeesCollected { get; set; }
    public int TodayTransactionsCount { get; set; }
    public decimal AttendanceRateToday { get; set; }
    public SyncStatusDto SyncStatus { get; set; } = new();
    public List<RecentTransactionDto> RecentTransactions { get; set; } = new();
}

public class RecentTransactionDto
{
    public int Id { get; set; }
    public string ReceiptNo { get; set; } = string.Empty;
    public string StudentName { get; set; } = string.Empty;
    public string CourseName { get; set; } = string.Empty;
    public decimal AmountPaid { get; set; }
    public string PaymentDate { get; set; } = string.Empty;
    public string ReceivedBy { get; set; } = string.Empty;
}

public class SyncStatusDto
{
    public string State { get; set; } = "Synced"; // Synced, Pending, Offline, Uploading, NeedsSignIn
    public string? LastSyncTime { get; set; }
    public int PendingCount { get; set; }
    public string? DriveAccount { get; set; } = "aminullahameen@gmail.com";
    public string? WarningMessage { get; set; }
}

public class MarkAttendanceDto
{
    public int BatchId { get; set; }
    public string Date { get; set; } = DateTime.UtcNow.ToString("yyyy-MM-dd");
    public List<StudentAttendanceItemDto> Items { get; set; } = new();
    public string RecordedBy { get; set; } = "Teacher";
}

public class StudentAttendanceItemDto
{
    public int StudentId { get; set; }
    public AttendanceStatus Status { get; set; }
    public string? Remarks { get; set; }
}

public class EnterMarksDto
{
    public int ExaminationId { get; set; }
    public int StudentId { get; set; }
    public string SubjectName { get; set; } = string.Empty;
    public decimal TotalMarks { get; set; } = 100m;
    public decimal ObtainedMarks { get; set; }
    public string? Remarks { get; set; }
}

public class ResultCardDto
{
    public string StudentRegNo { get; set; } = string.Empty;
    public string StudentName { get; set; } = string.Empty;
    public string FatherName { get; set; } = string.Empty;
    public string ExamName { get; set; } = string.Empty;
    public string AcademicSession { get; set; } = string.Empty;
    public string CourseName { get; set; } = string.Empty;
    public List<SubjectResultDto> Subjects { get; set; } = new();
    public decimal GrandTotalMarks { get; set; }
    public decimal GrandObtainedMarks { get; set; }
    public decimal Percentage { get; set; }
    public string OverallGrade { get; set; } = string.Empty;
    public decimal OverallGpa { get; set; }
    public int? ClassRank { get; set; }
}

public class SubjectResultDto
{
    public string SubjectName { get; set; } = string.Empty;
    public decimal TotalMarks { get; set; }
    public decimal ObtainedMarks { get; set; }
    public string Grade { get; set; } = string.Empty;
    public decimal Gpa { get; set; }
}

public class GenerateCertificateDto
{
    public int StudentId { get; set; }
    public CertificateType CertificateType { get; set; }
    public string CourseName { get; set; } = string.Empty;
    public string? GradeOrRemarks { get; set; }
    public string IssuedBy { get; set; } = "Principal";
}

public class DataImportPreviewDto
{
    public string SourceFileName { get; set; } = string.Empty;
    public int TotalDepartments { get; set; }
    public int TotalCourses { get; set; }
    public int TotalBatches { get; set; }
    public int TotalStudents { get; set; }
    public int TotalEnrollments { get; set; }
    public int TotalFeeTransactions { get; set; }
    public int TotalCampusExpenses { get; set; }
    public int TotalStaffMembers { get; set; }
    public int TotalUsers { get; set; }
    public decimal TotalFeesValue { get; set; }
    public List<string> ValidationWarnings { get; set; } = new();
}

public class DataImportResultDto
{
    public bool Success { get; set; }
    public string Message { get; set; } = string.Empty;
    public int RecordsImported { get; set; }
    public List<string> Errors { get; set; } = new();
}

// --------------------------------------------------------------------------
// Non-circular Paged and Projection DTOs for Bridge Endpoints
// --------------------------------------------------------------------------

public record PagedResult<T>(
    List<T> Items,
    int TotalCount,
    int PageNumber,
    int PageSize,
    int TotalPages
);

public record StudentListItemDto(
    int Id,
    string StudentRegNo,
    string FullName,
    string? FatherName,
    string? ContactNumber,
    string? SecondaryContact,
    string? Cnic,
    string? DateOfBirth,
    string? Email,
    string? Address,
    string? CurrentCourse,
    string? CurrentBatch,
    string? RollNumber,
    decimal TotalAgreedFee,
    decimal TotalPaid,
    decimal BalanceDue,
    string Status
);

public record StudentDetailDto(
    int Id,
    string StudentRegNo,
    string FullName,
    string? FatherName,
    string? ContactNumber,
    string? SecondaryContact,
    string? Cnic,
    string? DateOfBirth,
    string? Email,
    string? Address,
    List<StudentEnrollmentDto> Enrollments,
    List<StudentFeeTxDto> FeeTransactions,
    List<StudentAttendanceSummaryDto> Attendance,
    List<StudentResultDto> Results,
    List<StudentCertificateDto> Certificates
);

public record StudentEnrollmentDto(
    int Id,
    int BatchId,
    string BatchCode,
    string BatchName,
    int CourseId,
    string CourseName,
    string? RollNumber,
    string? EnrollmentDate,
    decimal TotalAgreedFee,
    decimal DiscountAmount,
    string Status
);

public record StudentFeeTxDto(
    int Id,
    string ReceiptNo,
    decimal AmountPaid,
    decimal DueAmount,
    string PaymentDate,
    string PaymentMethod,
    string ReceivedBy,
    string? Remarks,
    bool IsCancelled
);

public record StudentAttendanceSummaryDto(
    string Date,
    string Status,
    string? Remarks
);

public record StudentResultDto(
    int ExaminationId,
    string ExamName,
    string SubjectName,
    decimal TotalMarks,
    decimal ObtainedMarks,
    string Grade,
    decimal Gpa
);

public record StudentCertificateDto(
    int Id,
    string CertificateNo,
    string CertificateType,
    string IssueDate,
    string CourseName,
    string? GradeOrRemarks,
    string QrCodeData
);

public record CreateStudentRequest(
    string StudentRegNo,
    string FullName,
    string? FatherName,
    string? ContactNumber,
    string? SecondaryContact,
    string? Cnic,
    string? DateOfBirth,
    string? Email,
    string? Address,
    int? BatchId,
    decimal? AgreedFee,
    string? RollNumber
);

public record UpdateStudentRequest(
    int Id,
    string StudentRegNo,
    string FullName,
    string? FatherName,
    string? ContactNumber,
    string? SecondaryContact,
    string? Cnic,
    string? DateOfBirth,
    string? Email,
    string? Address
);

// Course & Academic DTOs
public record CourseListItemDto(
    int Id,
    string CourseCode,
    string CourseName,
    string Category,
    string Duration,
    decimal StandardFee,
    decimal TtbExamFee,
    int TotalBatches,
    int TotalStudentsEnrolled,
    decimal TotalFeesCollected,
    decimal OutstandingDues,
    bool IsActive
);

public record CourseDetailDto(
    int Id,
    string CourseCode,
    string CourseName,
    string Category,
    string Duration,
    decimal StandardFee,
    decimal TtbExamFee,
    string? Description,
    bool IsActive,
    List<BatchDto> Batches,
    List<SubjectDto> Subjects,
    List<EnrollmentListItemDto> Enrollments,
    decimal TotalFeesCollected,
    decimal TotalOutstandingDues
);

public record BatchDto(
    int Id,
    string BatchCode,
    string BatchName,
    int CourseId,
    string? CourseName,
    string? StartDate,
    string? EndDate,
    int ActiveStudentsCount,
    string Status
);

public record SubjectDto(
    int Id,
    string SubjectCode,
    string SubjectName,
    int CourseId,
    string? CourseName,
    decimal TotalMarks,
    decimal PassingMarks
);

public record EnrollmentListItemDto(
    int Id,
    int StudentId,
    string StudentRegNo,
    string StudentName,
    string? FatherName,
    int BatchId,
    string BatchCode,
    string BatchName,
    int CourseId,
    string CourseName,
    string? RollNumber,
    string? EnrollmentDate,
    decimal TotalAgreedFee,
    decimal TotalPaid,
    decimal BalanceDue,
    string Status
);

public record CreateCourseRequest(
    string CourseCode,
    string CourseName,
    string Category,
    string Duration,
    decimal StandardFee,
    decimal TtbExamFee,
    string? Description
);

public record UpdateCourseRequest(
    int Id,
    string CourseCode,
    string CourseName,
    string Category,
    string Duration,
    decimal StandardFee,
    decimal TtbExamFee,
    string? Description
);

public record CreateBatchRequest(
    string BatchCode,
    string BatchName,
    int CourseId,
    string? StartDate
);

public record CreateSubjectRequest(
    string SubjectCode,
    string SubjectName,
    int CourseId,
    decimal TotalMarks,
    decimal PassingMarks
);

public record CreateEnrollmentRequest(
    int StudentId,
    int BatchId,
    string? RollNumber,
    decimal TotalAgreedFee,
    decimal AdmissionFee,
    decimal DiscountAmount,
    string? EnrollmentDate
);

public record TechnologyDisciplineStatDto(
    string Category,
    int StudentCount,
    decimal Percentage
);

public record FeeCollectionTrendDto(
    string MonthLabel,
    decimal MonthlySum
);

public record FeeLedgerItemDto(
    int Id,
    string ReceiptNo,
    int EnrollmentId,
    string StudentRegNo,
    string StudentName,
    string CourseName,
    string BatchCode,
    decimal AmountPaid,
    decimal DueAmount,
    string PaymentDate,
    string PaymentMethod,
    string ReceivedBy,
    string? Remarks,
    bool IsCancelled
);

public record OutstandingDuesItemDto(
    int EnrollmentId,
    int StudentId,
    string StudentRegNo,
    string StudentName,
    string? FatherName,
    string CourseName,
    string BatchCode,
    decimal TotalAgreedFee,
    decimal TotalPaid,
    decimal BalanceDue,
    string? ContactNumber
);

public record AttendanceRecordDto(
    int Id,
    int StudentId,
    string StudentRegNo,
    string StudentName,
    int BatchId,
    string Date,
    string Status,
    string? Remarks,
    string RecordedBy
);

public record CertificateDto(
    int Id,
    string CertificateNo,
    int StudentId,
    string StudentRegNo,
    string StudentName,
    string FatherName,
    string CertificateType,
    string IssueDate,
    string CourseName,
    string? GradeOrRemarks,
    string QrCodeData,
    int ReprintCount,
    string IssuedBy
);

public record UserDto(
    int Id,
    string Username,
    string FullName,
    string? Phone,
    string? Email,
    string Role,
    bool IsActive,
    DateTime CreatedAt
);

public class CreateUserRequest
{
    public string Username { get; set; } = string.Empty;
    public string FullName { get; set; } = string.Empty;
    public string? Phone { get; set; }
    public string? Email { get; set; }
    public string? Password { get; set; }
    public string Role { get; set; } = "Staff";
}



