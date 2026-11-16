using System.Globalization;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public record WorkbookStudentRow(string? Registration, string FullName, string? FatherName, string? Phone, string? Cnic,
    string? Address, string? RollNumber, decimal? AgreedFee, decimal? PaidAmount, string? AdmissionDate, string? SlipNumber);
public record WorkbookSheetPlan(string Name, string Kind, string CourseCode, string CourseName, string BatchCode,
    decimal StandardFee, List<WorkbookStudentRow> Students, List<List<string>> SourceRows, List<string>? DetectionWarnings = null);
public record WorkbookImportRequest(string AdminToken, string FileName, string FileBase64, List<WorkbookSheetPlan> Sheets);
public record WorkbookSheetOutcome(string Name, string Kind, int Imported, int Linked, int Skipped, string? BatchCode, int? CourseId, List<string> Warnings);
public record WorkbookImportOutcome(string Hash, string FileName, string OriginalFilePath, int Worksheets, List<WorkbookSheetOutcome> Sheets, bool AlreadyImported = false);
public record WorkbookArchiveSummary(string Hash, string FileName, int Worksheets, string ImportedAt);

public class WorkbookImportService(AppDbContext context, string? storageRoot = null)
{
    private static readonly JsonSerializerOptions Json = new() { PropertyNameCaseInsensitive = true };
    private DataManagementService Management => new(context, storageRoot);
    private static string Clean(string? s) => Regex.Replace(s?.Trim().ToUpperInvariant() ?? "", @"\s+", " ");
    private static string Prefix(string s) => Regex.Match(s.Trim().ToUpperInvariant(), @"^[A-Z]+").Value;
    private static string Code(string s) => Regex.Replace(s.Trim().ToUpperInvariant(), @"[^A-Z0-9-]+", "-").Trim('-');
    private static long Suffix(string s) => long.TryParse(Regex.Match(s, @"(\d+)$").Value, out var n) ? n : 0;
    private static string Registration(string? input, string prefix)
    {
        var text = Clean(input);
        if (Regex.IsMatch(text, @"^\d+$")) return $"ICT-{prefix}-{text}";
        if (Regex.IsMatch(text, $@"^{Regex.Escape(prefix)}-\d+$")) return "ICT-" + text;
        return Regex.IsMatch(text, @"[A-Z].*\d$") ? text : "";
    }

    public async Task<WorkbookImportOutcome> ImportAsync(WorkbookImportRequest request)
    {
        var admin = await Management.RequireAdminAsync(request.AdminToken);
        var extension = Path.GetExtension(request.FileName).ToLowerInvariant();
        if (extension is not ".xls" and not ".xlsx") throw new InvalidOperationException("Select an XLS or XLSX workbook.");
        if (request.FileBase64.Length > 22_000_000 || request.Sheets is not { Count: > 0 and <= 200 })
            throw new InvalidOperationException("Workbook must be under 15 MB with no more than 200 worksheets.");
        byte[] original;
        try { original = Convert.FromBase64String(request.FileBase64); } catch { throw new InvalidOperationException("Workbook file data is invalid."); }
        if (original.Length > 15 * 1024 * 1024 || original.Length < 8) throw new InvalidOperationException("Workbook file size is invalid.");
        if (!(original[0] == 0x50 && original[1] == 0x4b || original[0] == 0xd0 && original[1] == 0xcf))
            throw new InvalidOperationException("The selected file is not a supported Excel workbook.");
        if (request.Sheets.Sum(s => s.SourceRows.Count) > 100000 || request.Sheets.Sum(s => s.Students.Count) > 20000)
            throw new InvalidOperationException("Import up to 20,000 student rows and 100,000 source rows at a time.");
        var hash = Convert.ToHexString(SHA256.HashData(original));
        await DataMaintenance.Gate.WaitAsync();
        try
        {
            admin = await Management.RequireAdminAsync(request.AdminToken);
            var saved = await context.SystemSettings.AsNoTracking().SingleOrDefaultAsync(s => s.Key == $"WorkbookArchive:{hash}:manifest");
            if (saved != null) return JsonSerializer.Deserialize<WorkbookImportOutcome>(saved.Value, Json)! with { AlreadyImported = true };
            var folder = Path.Combine(Management.StorageRoot, "imports"); Directory.CreateDirectory(folder);
            var originalPath = Path.Combine(folder, hash + extension);
            await File.WriteAllBytesAsync(originalPath, original);
            await using var tx = await context.Database.BeginTransactionAsync();
            var courses = await context.Courses.IgnoreQueryFilters().ToListAsync();
            var batches = await context.Batches.IgnoreQueryFilters().Include(b => b.Course).ToListAsync();
            var students = await context.Students.IgnoreQueryFilters().ToListAsync();
            var studentMap = students.GroupBy(s => Clean(s.StudentRegNo)).ToDictionary(g => g.Key, g => g.First());
            var rowMappings = await context.SystemSettings.Where(s => s.Key.StartsWith("WorkbookRow:")).ToDictionaryAsync(s => s.Key, s => s.Value);
            var enrollments = await context.Enrollments.IgnoreQueryFilters().Include(e => e.Student).Include(e => e.Batch).ToListAsync();
            var knownLinks = enrollments.Where(e => e.Student != null && e.Batch != null)
                .Select(e => Clean(e.Student!.StudentRegNo) + "|" + Clean(e.Batch!.BatchCode)).ToHashSet();
            var maxima = new Dictionary<string, long>();
            foreach (var plan in request.Sheets.Where(s => s.Kind == "students"))
            {
                var prefix = Prefix(plan.CourseCode);
                if (prefix.Length == 0 || prefix.Length > 16 || string.IsNullOrWhiteSpace(plan.CourseName) || Code(plan.BatchCode).Length == 0 || plan.StandardFee < 0)
                    throw new InvalidOperationException($"Check the course, batch code and fee for worksheet '{plan.Name}'.");
                var numbers = students.Where(s => Clean(s.StudentRegNo).StartsWith($"ICT-{prefix}-") || Clean(s.StudentRegNo).StartsWith(prefix + "-"))
                    .Select(s => Suffix(s.StudentRegNo)).Concat(plan.Students.Select(r => Suffix(Registration(r.Registration, prefix))));
                maxima[prefix] = Math.Max(maxima.GetValueOrDefault(prefix), numbers.DefaultIfEmpty(0).Max());
            }
            var outcomes = new List<WorkbookSheetOutcome>();
            for (var sheetIndex = 0; sheetIndex < request.Sheets.Count; sheetIndex++)
            {
                var plan = request.Sheets[sheetIndex]; var warnings = plan.DetectionWarnings?.ToList() ?? new List<string>(); int imported = 0, linked = 0, skipped = 0;
                Course? course = null; Batch? batch = null;
                if (plan.Kind == "students")
                {
                    var prefix = Prefix(plan.CourseCode); var batchCode = Code(plan.BatchCode);
                    course = courses.FirstOrDefault(c => Clean(c.CourseCode) == prefix);
                    if (course?.IsDeleted == true) throw new InvalidOperationException($"Restore course '{prefix}' before importing its worksheets.");
                    if (course == null)
                    {
                        course = new Course { CourseCode = prefix, CourseName = plan.CourseName.Trim(), Category = "Imported workbook", Duration = prefix == "DIT" ? "2 Semesters" : "Course", StandardFee = plan.StandardFee };
                        context.Courses.Add(course); courses.Add(course);
                    }
                    batch = batches.FirstOrDefault(b => Clean(b.BatchCode) == batchCode);
                    if (batch != null && (batch.IsDeleted || batch.Course != course))
                        throw new InvalidOperationException($"Batch code '{batchCode}' belongs to another or removed course. Choose a different batch code.");
                    if (batch == null)
                    {
                        batch = new Batch { BatchCode = batchCode, BatchName = plan.Name.Trim(), Course = course, Status = "completed" };
                        context.Batches.Add(batch); batches.Add(batch);
                    }
                    foreach (var (row, index) in plan.Students.Select((row, index) => (row, index)))
                    {
                        if (string.IsNullOrWhiteSpace(row.FullName) || row.FullName.Length > 200) { skipped++; warnings.Add($"Student row {index + 1}: name is missing or too long."); continue; }
                        var identity = batchCode + "|" + Clean(row.Registration) + "|" + Clean(row.FullName) + "|" + Clean(row.FatherName);
                        var mappingKey = "WorkbookRow:" + Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(identity)));
                        var reg = rowMappings.GetValueOrDefault(mappingKey) ?? Registration(row.Registration, prefix);
                        Student? student = reg.Length == 0 ? null : studentMap.GetValueOrDefault(Clean(reg));
                        if (student != null && (student.IsDeleted || Clean(student.FullName) != Clean(row.FullName) ||
                            !string.IsNullOrWhiteSpace(student.FatherName) && !string.IsNullOrWhiteSpace(row.FatherName) && Clean(student.FatherName) != Clean(row.FatherName)))
                        {
                            warnings.Add($"{row.FullName}: source registration '{reg}' is already used by a different or removed student. A new registration was allocated."); student = null; reg = "";
                        }
                        if (reg.Length == 0)
                        {
                            do { reg = $"ICT-{prefix}-{checked(++maxima[prefix]):D3}"; } while (studentMap.ContainsKey(Clean(reg)));
                        }
                        if (student == null)
                        {
                            string? phone = row.Phone?.Trim(), cnic = row.Cnic?.Trim();
                            try { phone = PakistanIdentity.Phone(phone); } catch { warnings.Add($"{row.FullName}: original phone retained; check its Pakistan format."); }
                            try { cnic = PakistanIdentity.Cnic(cnic); } catch { warnings.Add($"{row.FullName}: original CNIC retained; check its 13 digits."); }
                            student = new Student { StudentRegNo = reg, FullName = row.FullName.Trim(), FatherName = row.FatherName?.Trim(), ContactNumber = phone, Cnic = cnic, Address = row.Address?.Trim() };
                            context.Students.Add(student); studentMap[Clean(reg)] = student; imported++;
                        }
                        if (!rowMappings.ContainsKey(mappingKey))
                        {
                            rowMappings[mappingKey] = student.StudentRegNo;
                            context.SystemSettings.Add(new SystemSetting { Key = mappingKey, Value = student.StudentRegNo });
                        }
                        var link = Clean(student.StudentRegNo) + "|" + batchCode;
                        if (!knownLinks.Add(link)) { skipped++; continue; }
                        var fee = row.AgreedFee is >= 0 ? row.AgreedFee.Value : course.StandardFee;
                        if (row.AgreedFee < 0) warnings.Add($"{row.FullName}: negative source fee was not applied; course fee used.");
                        var admissionDate = DateTime.TryParse(row.AdmissionDate, CultureInfo.InvariantCulture, DateTimeStyles.None, out var date) ? date.ToString("yyyy-MM-dd") : null;
                        var enrollment = new Enrollment { Student = student, Batch = batch, RollNumber = row.RollNumber ?? row.Registration,
                            TotalAgreedFee = fee, EnrollmentDate = admissionDate, AcademicSession = "Imported legacy workbook",
                            Status = batch.Status.Equals("active", StringComparison.OrdinalIgnoreCase) ? EnrollmentStatus.Active : EnrollmentStatus.Completed };
                        context.Enrollments.Add(enrollment); linked++;
                        if (row.PaidAmount > 0)
                        {
                            context.FeeTransactions.Add(new FeeTransaction { Enrollment = enrollment, ReceiptNo = $"LEG-{hash[..12]}-{sheetIndex + 1}-{index + 1}",
                                AmountPaid = row.PaidAmount.Value, DueAmount = Math.Max(0, fee - row.PaidAmount.Value), PaymentDate = "Undated legacy record",
                                ReceivedBy = admin.Username, PaymentMode = "legacy-opening", ReferenceNo = row.SlipNumber,
                                Remarks = $"Imported opening amount from worksheet {plan.Name}; original workbook preserved." });
                            if (row.PaidAmount > fee) warnings.Add($"{row.FullName}: source paid amount exceeds agreed fee; original opening credit retained.");
                        }
                        else if (row.PaidAmount < 0) warnings.Add($"{row.FullName}: negative source paid amount was not posted. Original value is in the archived worksheet.");
                    }
                }
                var outcome = new WorkbookSheetOutcome(plan.Name, plan.Kind == "students" ? "students" : "reference", imported, linked, skipped, batch?.BatchCode, null, warnings);
                outcomes.Add(outcome);
                context.SystemSettings.Add(new SystemSetting { Key = $"WorkbookArchive:{hash}:sheet:{sheetIndex}", Value = JsonSerializer.Serialize(new { plan.Name, plan.SourceRows }), Description = "Original workbook worksheet" });
            }
            await context.SaveChangesAsync();
            for (var i = 0; i < outcomes.Count; i++)
                if (outcomes[i].BatchCode is { } code) outcomes[i] = outcomes[i] with { CourseId = batches.Single(b => b.BatchCode == code).CourseId };
            var result = new WorkbookImportOutcome(hash, Path.GetFileName(request.FileName), originalPath, request.Sheets.Count, outcomes);
            context.SystemSettings.Add(new SystemSetting { Key = $"WorkbookArchive:{hash}:manifest", Value = JsonSerializer.Serialize(result), Description = DateTime.UtcNow.ToString("O") });
            var manual = await context.SystemSettings.SingleOrDefaultAsync(s => s.Key == "DataManagement:ManualMode");
            if (manual == null) context.SystemSettings.Add(new SystemSetting { Key = "DataManagement:ManualMode", Value = "true" }); else manual.Value = "true";
            context.AuditLogs.Add(new AuditLog { Username = admin.Username, UserId = admin.Id, Action = "workbook.import", EntityName = "Workbook", EntityId = hash[..12],
                NewValuesJson = JsonSerializer.Serialize(new { result.FileName, result.Worksheets, Students = outcomes.Sum(s => s.Imported), Enrollments = outcomes.Sum(s => s.Linked) }) });
            await context.SaveChangesAsync(); await tx.CommitAsync(); return result;
        }
        finally { DataMaintenance.Gate.Release(); }
    }

    public async Task<List<WorkbookArchiveSummary>> ArchivesAsync(string token)
    {
        await Management.RequireAdminAsync(token);
        var records = await context.SystemSettings.AsNoTracking().Where(s => s.Key.StartsWith("WorkbookArchive:") && s.Key.EndsWith(":manifest")).OrderByDescending(s => s.Id).ToListAsync();
        return records.Select(s => { var r = JsonSerializer.Deserialize<WorkbookImportOutcome>(s.Value, Json)!; return new WorkbookArchiveSummary(r.Hash, r.FileName, r.Worksheets, s.Description ?? ""); }).ToList();
    }
    public async Task<WorkbookImportOutcome> ManifestAsync(string token, string hash)
    {
        await Management.RequireAdminAsync(token);
        var s = await context.SystemSettings.AsNoTracking().SingleOrDefaultAsync(s => s.Key == $"WorkbookArchive:{hash}:manifest") ?? throw new InvalidOperationException("Workbook not found.");
        return JsonSerializer.Deserialize<WorkbookImportOutcome>(s.Value, Json)!;
    }
    public async Task<JsonElement> SheetAsync(string token, string hash, int index)
    {
        await Management.RequireAdminAsync(token);
        var s = await context.SystemSettings.AsNoTracking().SingleOrDefaultAsync(s => s.Key == $"WorkbookArchive:{hash}:sheet:{index}") ?? throw new InvalidOperationException("Worksheet not found.");
        return JsonSerializer.Deserialize<JsonElement>(s.Value);
    }
}
