using System.Runtime.InteropServices;
using System.Text.Json;
using System.Text.Json.Serialization;
using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Interfaces;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;

namespace ICTManagementSystem.App.Bridge;

[ComVisible(true)]
[InterfaceType(ComInterfaceType.InterfaceIsIDispatch)]
public interface IJsBridge
{
    string Invoke(string method, string payloadJson);
}

[ComVisible(true)]
[ClassInterface(ClassInterfaceType.None)]
public class JsBridge : IJsBridge
{
    private readonly IServiceProvider _serviceProvider;
    private readonly ILogger<JsBridge> _logger;

    public static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNameCaseInsensitive = true,
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
        ReferenceHandler = ReferenceHandler.IgnoreCycles,
        DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull
    };

    public JsBridge(IServiceProvider serviceProvider, ILogger<JsBridge> logger)
    {
        _serviceProvider = serviceProvider;
        _logger = logger;
    }

    /// <summary>
    /// Universal asynchronous invocation bridge called from TypeScript via window.chrome.webview.hostObjects.bridge.Invoke
    /// </summary>
    public string Invoke(string method, string payloadJson)
    {
        try
        {
            return Task.Run(async () => await InvokeAsync(method, payloadJson)).GetAwaiter().GetResult();
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Bridge synchronous invocation failure for method {Method}", method);
            var friendly = GetFriendlyErrorMessage(ex);
            return JsonSerializer.Serialize(new { success = false, error = friendly }, JsonOptions);
        }
    }

    public async Task<string> InvokeAsync(string method, string payloadJson)
    {
        using var scope = _serviceProvider.CreateScope();
        var sp = scope.ServiceProvider;

        try
        {
            _logger.LogDebug("Bridge dispatching method: {Method}", method);

            object? result = method switch
            {
                // Auth & User Management (RBAC)
                "auth.login" => await sp.GetRequiredService<IAuthService>().LoginAsync(
                    ExtractOptionalString(payloadJson, "username") ?? "",
                    ExtractOptionalString(payloadJson, "password") ?? ""),
                "auth.changePassword" => await sp.GetRequiredService<IAuthService>().ChangePasswordAsync(
                    ExtractOptionalInt(payloadJson, "userId") ?? 0,
                    ExtractOptionalString(payloadJson, "oldPassword") ?? "",
                    ExtractOptionalString(payloadJson, "newPassword") ?? ""),
                "users.getAll" => await sp.GetRequiredService<IAuthService>().GetUsersAsync(),
                "users.create" => await sp.GetRequiredService<IAuthService>().CreateUserAsync(
                    JsonSerializer.Deserialize<CreateUserRequest>(payloadJson, JsonOptions)!),
                "users.toggleStatus" => await sp.GetRequiredService<IAuthService>().ToggleUserStatusAsync(
                    ExtractOptionalInt(payloadJson, "userId") ?? 0),
                "users.resetPassword" => await sp.GetRequiredService<IAuthService>().ResetUserPasswordAsync(
                    ExtractOptionalInt(payloadJson, "userId") ?? 0,
                    ExtractOptionalString(payloadJson, "newPassword") ?? ""),
                "users.delete" => await sp.GetRequiredService<IAuthService>().DeleteUserAsync(
                    ExtractOptionalInt(payloadJson, "userId") ?? 0),

                // Dashboard
                "dashboard.getSummary" => await sp.GetRequiredService<IDashboardService>().GetDashboardSummaryAsync(),
                "dashboard.getDisciplines" => await sp.GetRequiredService<IDashboardService>().GetTechnologyDisciplinesAsync(),
                "dashboard.getFeeTrend" => await sp.GetRequiredService<IDashboardService>().GetFeeCollectionTrendAsync(),

                // Students
                "students.getPaged" => await sp.GetRequiredService<IStudentService>().GetStudentsPagedAsync(
                    ExtractOptionalString(payloadJson, "search"),
                    ExtractOptionalInt(payloadJson, "batchId"),
                    ExtractOptionalInt(payloadJson, "pageNumber") ?? 1,
                    ExtractOptionalInt(payloadJson, "pageSize") ?? 25,
                    ExtractOptionalString(payloadJson, "sortBy"),
                    ExtractOptionalBool(payloadJson, "sortDesc") ?? false),
                "students.getAll" => await sp.GetRequiredService<IStudentService>().GetAllStudentsAsync(
                    ExtractOptionalString(payloadJson, "search"),
                    ExtractOptionalInt(payloadJson, "batchId")),
                "students.getById" => await sp.GetRequiredService<IStudentService>().GetStudentByIdAsync(
                    ExtractOptionalInt(payloadJson, "id") ?? 0),
                "students.getByRegNo" => await sp.GetRequiredService<IStudentService>().GetStudentByRegNoAsync(
                    ExtractOptionalString(payloadJson, "regNo") ?? ""),
                "students.create" => await sp.GetRequiredService<IStudentService>().CreateStudentAsync(
                    JsonSerializer.Deserialize<CreateStudentRequest>(payloadJson, JsonOptions)!),
                "students.update" => await sp.GetRequiredService<IStudentService>().UpdateStudentAsync(
                    JsonSerializer.Deserialize<UpdateStudentRequest>(payloadJson, JsonOptions)!),
                "students.delete" => await sp.GetRequiredService<IStudentService>().DeleteStudentAsync(
                    ExtractOptionalInt(payloadJson, "id") ?? 0,
                    ExtractOptionalString(payloadJson, "deletedBy") ?? "Admin"),
                "students.getNextRegNo" => await sp.GetRequiredService<IStudentService>().GetNextRegistrationNoAsync(
                    ExtractOptionalString(payloadJson, "category") ?? "DIT"),

                // Courses & Academic Modules
                "courses.getPaged" => await sp.GetRequiredService<ICourseService>().GetCoursesPagedAsync(
                    ExtractOptionalString(payloadJson, "search"),
                    ExtractOptionalString(payloadJson, "category"),
                    ExtractOptionalInt(payloadJson, "pageNumber") ?? 1,
                    ExtractOptionalInt(payloadJson, "pageSize") ?? 25,
                    ExtractOptionalBool(payloadJson, "activeOnly")),
                "courses.getAll" => await sp.GetRequiredService<ICourseService>().GetAllCoursesAsync(
                    ExtractOptionalBool(payloadJson, "activeOnly")),
                "courses.getById" => await sp.GetRequiredService<ICourseService>().GetCourseByIdAsync(
                    ExtractOptionalInt(payloadJson, "id") ?? 0),
                "courses.create" => await sp.GetRequiredService<ICourseService>().CreateCourseAsync(
                    JsonSerializer.Deserialize<CreateCourseRequest>(payloadJson, JsonOptions)!),
                "courses.update" => await sp.GetRequiredService<ICourseService>().UpdateCourseAsync(
                    JsonSerializer.Deserialize<UpdateCourseRequest>(payloadJson, JsonOptions)!),
                "courses.archive" => await sp.GetRequiredService<ICourseService>().ArchiveCourseAsync(
                    ExtractOptionalInt(payloadJson, "id") ?? 0),
                "courses.setBatchActive" => await sp.GetRequiredService<ICourseService>().SetBatchActiveAsync(
                    ExtractOptionalInt(payloadJson, "id") ?? 0, ExtractOptionalBool(payloadJson, "active") ?? false),
                "academic.importStudents" => await new ICTManagementSystem.Data.Services.AcademicImportService(sp.GetRequiredService<ICTManagementSystem.Data.AppDbContext>()).ImportStudentsAsync(
                    JsonSerializer.Deserialize<ICTManagementSystem.Data.Services.StudentImportRequest>(payloadJson, JsonOptions)!),
                "academic.importMarks" => await new ICTManagementSystem.Data.Services.AcademicImportService(sp.GetRequiredService<ICTManagementSystem.Data.AppDbContext>()).ImportMarksAsync(
                    JsonSerializer.Deserialize<ICTManagementSystem.Data.Services.MarksImportRequest>(payloadJson, JsonOptions)!),
                "academic.getExams" => await new ICTManagementSystem.Data.Services.AcademicImportService(sp.GetRequiredService<ICTManagementSystem.Data.AppDbContext>()).GetExamsAsync(),
                "courses.getBatches" => await sp.GetRequiredService<ICourseService>().GetBatchesAsync(
                    ExtractOptionalInt(payloadJson, "courseId")),
                "courses.createBatch" => await sp.GetRequiredService<ICourseService>().CreateBatchAsync(
                    JsonSerializer.Deserialize<CreateBatchRequest>(payloadJson, JsonOptions)!),
                "courses.getSubjects" => await sp.GetRequiredService<ICourseService>().GetSubjectsAsync(
                    ExtractOptionalInt(payloadJson, "courseId")),
                "courses.createSubject" => await sp.GetRequiredService<ICourseService>().CreateSubjectAsync(
                    JsonSerializer.Deserialize<CreateSubjectRequest>(payloadJson, JsonOptions)!),
                "courses.getEnrollments" => await sp.GetRequiredService<ICourseService>().GetEnrollmentsAsync(
                    ExtractOptionalInt(payloadJson, "courseId"),
                    ExtractOptionalInt(payloadJson, "batchId")),
                "courses.createEnrollment" => await sp.GetRequiredService<ICourseService>().CreateEnrollmentAsync(
                    JsonSerializer.Deserialize<CreateEnrollmentRequest>(payloadJson, JsonOptions)!),
                "courses.getDisciplinesDistribution" => await sp.GetRequiredService<ICourseService>().GetTechnologyDisciplinesDistributionAsync(),

                // Fees
                "fees.getStudentDetails" => await sp.GetRequiredService<IFeeService>().GetStudentFeeDetailsAsync(
                    ExtractOptionalInt(payloadJson, "enrollmentId") ?? 0),
                "fees.searchStudent" => await sp.GetRequiredService<IFeeService>().SearchStudentForFeeAsync(
                    ExtractOptionalString(payloadJson, "query") ?? ""),
                "fees.receivePayment" => await sp.GetRequiredService<IFeeService>().ReceivePaymentAsync(
                    JsonSerializer.Deserialize<ReceivePaymentRequest>(payloadJson, JsonOptions)!),
                "fees.cancelPayment" => await sp.GetRequiredService<IFeeService>().CancelPaymentAsync(
                    ExtractOptionalInt(payloadJson, "transactionId") ?? 0,
                    ExtractOptionalString(payloadJson, "reason") ?? "",
                    ExtractOptionalString(payloadJson, "cancelledBy") ?? "Admin"),
                "fees.getPaymentLedger" => await sp.GetRequiredService<IFeeService>().GetPaymentLedgerAsync(
                    ExtractOptionalString(payloadJson, "startDate"),
                    ExtractOptionalString(payloadJson, "endDate"),
                    ExtractOptionalInt(payloadJson, "batchId")),
                "fees.getOutstandingDues" => await sp.GetRequiredService<IFeeService>().GetOutstandingDuesListAsync(
                    ExtractOptionalInt(payloadJson, "batchId")),
                "fees.getDailyCollection" => await sp.GetRequiredService<IFeeService>().GetDailyCollectionSummaryAsync(
                    ExtractOptionalString(payloadJson, "date") ?? DateTime.UtcNow.ToString("yyyy-MM-dd")),
                "fees.getNextReceiptNo" => await sp.GetRequiredService<IFeeService>().GetNextReceiptNumberAsync(),

                // Attendance
                "attendance.getByBatchAndDate" => await sp.GetRequiredService<IAttendanceService>().GetAttendanceByBatchAndDateAsync(
                    ExtractOptionalInt(payloadJson, "batchId") ?? 0,
                    ExtractOptionalString(payloadJson, "date") ?? ""),
                "attendance.record" => await sp.GetRequiredService<IAttendanceService>().RecordAttendanceAsync(
                    JsonSerializer.Deserialize<MarkAttendanceDto>(payloadJson, JsonOptions)!),
                "attendance.getMonthlySheet" => await sp.GetRequiredService<IAttendanceService>().GetMonthlyAttendanceSheetAsync(
                    ExtractOptionalInt(payloadJson, "batchId") ?? 0,
                    ExtractOptionalInt(payloadJson, "year") ?? DateTime.UtcNow.Year,
                    ExtractOptionalInt(payloadJson, "month") ?? DateTime.UtcNow.Month),

                // Results
                "results.saveMarks" => await sp.GetRequiredService<IResultService>().SaveMarksAsync(
                    JsonSerializer.Deserialize<EnterMarksDto>(payloadJson, JsonOptions)!),
                "results.getCard" => await sp.GetRequiredService<IResultService>().GetStudentResultCardAsync(
                    ExtractOptionalInt(payloadJson, "examinationId") ?? 0,
                    ExtractOptionalInt(payloadJson, "studentId") ?? 0),
                "results.getClassSheet" => await sp.GetRequiredService<IResultService>().GetClassResultSheetAsync(
                    ExtractOptionalInt(payloadJson, "examinationId") ?? 0,
                    ExtractOptionalInt(payloadJson, "batchId") ?? 0),

                // Certificates
                "certificates.generate" => await sp.GetRequiredService<ICertificateService>().GenerateCertificateAsync(
                    JsonSerializer.Deserialize<GenerateCertificateDto>(payloadJson, JsonOptions)!),
                "certificates.getById" => await sp.GetRequiredService<ICertificateService>().GetCertificateByIdAsync(
                    ExtractOptionalInt(payloadJson, "id") ?? 0),
                "certificates.verifyQr" => await sp.GetRequiredService<ICertificateService>().VerifyCertificateByQrAsync(
                    ExtractOptionalString(payloadJson, "qrCode") ?? ""),

                // Backup & Drive Sync
                "backup.create" => await sp.GetRequiredService<IBackupService>().CreateManualBackupAsync(
                    ExtractOptionalString(payloadJson, "targetDir") ?? Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), "Desktop"),
                    ExtractOptionalString(payloadJson, "password")),
                "backup.restore" => await sp.GetRequiredService<IBackupService>().RestoreBackupAsync(
                    ExtractOptionalString(payloadJson, "filePath") ?? "",
                    ExtractOptionalString(payloadJson, "password")),
                "backup.getStatus" => await sp.GetRequiredService<IBackupService>().GetSyncStatusAsync(),
                "backup.triggerSync" => await TriggerSyncWrapper(sp),

                // Data Import from Web System
                "import.preview" => await sp.GetRequiredService<IDataImportService>().PreviewSqlDumpAsync(
                    ExtractOptionalString(payloadJson, "filePath") ?? ""),
                "import.execute" => await sp.GetRequiredService<IDataImportService>().ExecuteImportAsync(
                    ExtractOptionalString(payloadJson, "filePath") ?? ""),

                _ => throw new NotSupportedException($"Bridge method '{method}' is not registered.")
            };

            return JsonSerializer.Serialize(new { success = true, data = result }, JsonOptions);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Bridge execution exception on {Method}", method);
            var friendly = GetFriendlyErrorMessage(ex);
            return JsonSerializer.Serialize(new { success = false, error = friendly }, JsonOptions);
        }
    }

    private static string GetFriendlyErrorMessage(Exception ex)
    {
        var msg = ex.InnerException != null ? ex.InnerException.Message : ex.Message;

        // Clean out EF Core / JSON / Internal technical prefixes
        if (msg.Contains("object cycle", StringComparison.OrdinalIgnoreCase))
            return "Unable to serialize data structure. Please retry or contact technical support.";
        if (msg.Contains("UNIQUE constraint failed", StringComparison.OrdinalIgnoreCase))
            return "A record with this identifier already exists in the system.";
        if (msg.Contains("NOT NULL constraint failed", StringComparison.OrdinalIgnoreCase))
            return "Please provide all required fields before saving.";

        if (msg.Length > 120)
        {
            return msg.Substring(0, 117) + "...";
        }
        return msg;
    }

    private static async Task<bool> TriggerSyncWrapper(IServiceProvider sp)
    {
        await sp.GetRequiredService<IBackupService>().TriggerSyncNowAsync();
        return true;
    }

    private static string? ExtractOptionalString(string json, string prop)
    {
        if (string.IsNullOrWhiteSpace(json)) return null;
        try
        {
            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.TryGetProperty(prop, out var val) && val.ValueKind == JsonValueKind.String)
                return val.GetString();
        }
        catch { }
        return null;
    }

    private static int? ExtractOptionalInt(string json, string prop)
    {
        if (string.IsNullOrWhiteSpace(json)) return null;
        try
        {
            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.TryGetProperty(prop, out var val))
            {
                if (val.ValueKind == JsonValueKind.Number && val.TryGetInt32(out var i)) return i;
                if (val.ValueKind == JsonValueKind.String && int.TryParse(val.GetString(), out var si)) return si;
            }
        }
        catch { }
        return null;
    }

    private static decimal? ExtractOptionalDecimal(string json, string prop)
    {
        if (string.IsNullOrWhiteSpace(json)) return null;
        try
        {
            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.TryGetProperty(prop, out var val))
            {
                if (val.ValueKind == JsonValueKind.Number && val.TryGetDecimal(out var d)) return d;
                if (val.ValueKind == JsonValueKind.String && decimal.TryParse(val.GetString(), out var sd)) return sd;
            }
        }
        catch { }
        return null;
    }

    private static bool? ExtractOptionalBool(string json, string prop)
    {
        if (string.IsNullOrWhiteSpace(json)) return null;
        try
        {
            using var doc = JsonDocument.Parse(json);
            if (doc.RootElement.TryGetProperty(prop, out var val))
            {
                if (val.ValueKind == JsonValueKind.True) return true;
                if (val.ValueKind == JsonValueKind.False) return false;
            }
        }
        catch { }
        return null;
    }
}
