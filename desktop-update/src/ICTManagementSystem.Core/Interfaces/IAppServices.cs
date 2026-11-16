using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;

namespace ICTManagementSystem.Core.Interfaces;

public interface IAuthService
{
    Task<LoginResponse> LoginAsync(string username, string password);
    Task<bool> ValidateTokenAsync(string token);
    Task<bool> ChangePasswordAsync(int userId, string oldPassword, string newPassword);
    Task<List<UserDto>> GetUsersAsync();
    Task<UserDto> CreateUserAsync(CreateUserRequest request);
    Task<bool> ToggleUserStatusAsync(int userId);
    Task<bool> ResetUserPasswordAsync(int userId, string newPassword);
    Task<bool> DeleteUserAsync(int userId);
}

public interface IStudentService
{
    Task<PagedResult<StudentListItemDto>> GetStudentsPagedAsync(string? search = null, int? batchId = null, int pageNumber = 1, int pageSize = 25, string? sortBy = null, bool sortDesc = false);
    Task<List<StudentListItemDto>> GetAllStudentsAsync(string? search = null, int? batchId = null);
    Task<StudentDetailDto?> GetStudentByIdAsync(int id);
    Task<StudentDetailDto?> GetStudentByRegNoAsync(string regNo);
    Task<StudentDetailDto> CreateStudentAsync(CreateStudentRequest request);
    Task<StudentDetailDto> UpdateStudentAsync(UpdateStudentRequest request);
    Task<bool> DeleteStudentAsync(int id, string deletedBy);
    Task<string> GetNextRegistrationNoAsync(string category = "DIT");
}

public interface ICourseService
{
    Task<bool> SetBatchActiveAsync(int id, bool active);
    Task<PagedResult<CourseListItemDto>> GetCoursesPagedAsync(string? search = null, string? category = null, int pageNumber = 1, int pageSize = 25, bool? activeOnly = null);
    Task<List<CourseListItemDto>> GetAllCoursesAsync(bool? activeOnly = null);
    Task<CourseDetailDto?> GetCourseByIdAsync(int id);
    Task<CourseListItemDto> CreateCourseAsync(CreateCourseRequest request);
    Task<CourseListItemDto> UpdateCourseAsync(UpdateCourseRequest request);
    Task<bool> ArchiveCourseAsync(int id);
    Task<List<BatchDto>> GetBatchesAsync(int? courseId = null);
    Task<BatchDto> CreateBatchAsync(CreateBatchRequest request);
    Task<List<SubjectDto>> GetSubjectsAsync(int? courseId = null);
    Task<SubjectDto> CreateSubjectAsync(CreateSubjectRequest request);
    Task<List<EnrollmentListItemDto>> GetEnrollmentsAsync(int? courseId = null, int? batchId = null);
    Task<EnrollmentListItemDto> CreateEnrollmentAsync(CreateEnrollmentRequest request);
    Task<List<TechnologyDisciplineStatDto>> GetTechnologyDisciplinesDistributionAsync();
}

public interface IFeeService
{
    Task<StudentFeeDetailsDto?> GetStudentFeeDetailsAsync(int enrollmentId);
    Task<StudentFeeDetailsDto?> SearchStudentForFeeAsync(string query);
    Task<ReceivePaymentResult> ReceivePaymentAsync(ReceivePaymentRequest request);
    Task<bool> CancelPaymentAsync(int transactionId, string reason, string cancelledBy);
    Task<List<FeeLedgerItemDto>> GetPaymentLedgerAsync(string? startDate = null, string? endDate = null, int? batchId = null);
    Task<List<OutstandingDuesItemDto>> GetOutstandingDuesListAsync(int? batchId = null);
    Task<Dictionary<string, decimal>> GetDailyCollectionSummaryAsync(string date);
    Task<string> GetNextReceiptNumberAsync();
}

public interface IAttendanceService
{
    Task<List<AttendanceRecordDto>> GetAttendanceByBatchAndDateAsync(int batchId, string date);
    Task<bool> RecordAttendanceAsync(MarkAttendanceDto dto);
    Task<List<dynamic>> GetMonthlyAttendanceSheetAsync(int batchId, int year, int month);
}

public interface IResultService
{
    Task<bool> SaveMarksAsync(EnterMarksDto dto);
    Task<ResultCardDto?> GetStudentResultCardAsync(int examinationId, int studentId);
    Task<List<ResultCardDto>> GetClassResultSheetAsync(int examinationId, int batchId);
}

public interface ICertificateService
{
    Task<CertificateDto> GenerateCertificateAsync(GenerateCertificateDto dto);
    Task<CertificateDto?> GetCertificateByIdAsync(int id);
    Task<CertificateDto?> VerifyCertificateByQrAsync(string qrCodeData);
    Task<bool> IncrementReprintCountAsync(int certificateId);
}

public interface IDashboardService
{
    Task<DashboardSummaryDto> GetDashboardSummaryAsync();
    Task<List<TechnologyDisciplineStatDto>> GetTechnologyDisciplinesAsync();
    Task<List<FeeCollectionTrendDto>> GetFeeCollectionTrendAsync();
}

public interface IBackupService
{
    Task<string> CreateManualBackupAsync(string targetDirectory, string? password = null);
    Task<bool> RestoreBackupAsync(string backupFilePath, string? password = null);
    Task<SyncStatusDto> GetSyncStatusAsync();
    Task TriggerSyncNowAsync();
    Task<string> ConnectGoogleDriveAsync();
}

public interface IDataImportService
{
    Task<DataImportPreviewDto> PreviewSqlDumpAsync(string sqlFilePath);
    Task<DataImportResultDto> ExecuteImportAsync(string sqlFilePath);
}
