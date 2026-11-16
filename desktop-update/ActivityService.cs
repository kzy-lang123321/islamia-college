using System.Globalization;
using System.Text.Json;
using ICTManagementSystem.Core.Entities;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public record ActivityItem(int Id, DateTime TimestampUtc, string Username, string Action, string Description,
    string EntityName, string? EntityId, bool CanUndo, bool Undone);
public record ActivityPage(List<ActivityItem> Items, int TotalCount, int PageNumber, int TotalPages);
public record CourseUndoState(int Id, string CourseCode, string CourseName, string Category, string Duration,
    decimal StandardFee, decimal TtbExamFee, string? Description, bool IsDeleted, DateTime? DeletedAtUtc, string? DeletedBy);
public record BatchUndoState(int Id, string Status);
public record StudentUndoState(int Id, bool IsDeleted, DateTime? DeletedAtUtc, string? DeletedBy);
public record ChangeState(CourseUndoState? Course, List<BatchUndoState>? Batches, StudentUndoState? Student);

public class ActivityService(AppDbContext context, Action<Exception>? auditFailure = null)
{
    private static readonly SemaphoreSlim UndoLock = new(1, 1);
    private static readonly HashSet<string> Undoable = new() { "courses.update", "courses.archive", "courses.setBatchActive", "students.delete" };
    private static readonly Dictionary<string, string> Descriptions = new()
    {
        ["students.create"] = "Student admitted", ["students.update"] = "Student details updated", ["students.delete"] = "Student removed",
        ["courses.create"] = "Course created", ["courses.update"] = "Course details / fees updated", ["courses.archive"] = "Course removed",
        ["courses.createBatch"] = "Academic batch created", ["courses.setBatchActive"] = "Batch activation changed",
        ["courses.createEnrollment"] = "Student enrolled in batch", ["courses.createSubject"] = "Subject created",
        ["fees.receivePayment"] = "Fee payment received", ["fees.cancelPayment"] = "Receipt cancelled",
        ["academic.importStudents"] = "Excel student import", ["academic.importMarks"] = "Excel exam marks import",
        ["results.saveMarks"] = "Exam marks saved", ["certificates.generate"] = "Certificate generated",
        ["users.create"] = "User account created", ["users.toggleStatus"] = "User access changed",
        ["users.resetPassword"] = "User password reset", ["users.delete"] = "User account removed",
        ["auth.changePassword"] = "Password changed", ["auth.login"] = "Signed in",
        ["backup.create"] = "Backup created", ["backup.restore"] = "Backup restored", ["import.execute"] = "Legacy data import",
        ["Undo"] = "Previous change undone"
    };
    public async Task<object?> ExecuteAsync(string method, string payload, string actor, Func<Task<object?>> operation)
    {
        if (!Descriptions.ContainsKey(method)) return await operation();
        actor = string.IsNullOrWhiteSpace(actor) ? "system" : actor.Trim()[..Math.Min(actor.Trim().Length, 100)];
        if (Undoable.Contains(method))
        {
            await UndoLock.WaitAsync();
            try
            {
                await using var tx = await context.Database.BeginTransactionAsync();
                using var json = JsonDocument.Parse(payload);
                var id = json.RootElement.TryGetProperty("id", out var prop) ? prop.GetInt32() : 0;
                var before = await CaptureAsync(method, id);
                var result = await operation();
                if (result is not false)
                {
                    var after = await CaptureAsync(method, id);
                    var entry = context.ChangeTracker.Entries<AuditLog>().Select(e => e.Entity).FirstOrDefault() ?? new AuditLog();
                    entry.Action = method; entry.EntityName = method.StartsWith("students") ? "Student" : method == "courses.setBatchActive" ? "Batch" : "Course";
                    entry.EntityId = id.ToString(); entry.Username = actor; entry.TimestampUtc = DateTime.UtcNow;
                    entry.OldValuesJson = before; entry.NewValuesJson = after;
                    if (entry.Id == 0) context.AuditLogs.Add(entry);
                    await context.SaveChangesAsync();
                }
                await tx.CommitAsync();
                return result;
            }
            finally { UndoLock.Release(); }
        }
        var outcome = await operation();
        if (outcome is false || outcome == null) return outcome;
        var success = outcome.GetType().GetProperty("Success")?.GetValue(outcome);
        if (success is false) return outcome;
        if (method == "auth.login") actor = outcome.GetType().GetProperty("Username")?.GetValue(outcome)?.ToString() ?? actor;
        // Existing domain actions commit their own transactions. A history failure must never replay a committed payment.
        try
        {
            var existing = context.ChangeTracker.Entries<AuditLog>().Select(e => e.Entity).ToList();
            foreach (var entry in existing) entry.Username = actor;
            if (existing.Count == 1 && !method.Contains("import", StringComparison.OrdinalIgnoreCase)) existing[0].Action = method;
            else context.AuditLogs.Add(new AuditLog { Username = actor, Action = method,
                EntityName = method.Split('.')[0], EntityId = outcome.GetType().GetProperty("Id")?.GetValue(outcome)?.ToString(),
                // Record a label only: never serialize passwords, tokens, or a complete request.
                NewValuesJson = JsonSerializer.Serialize(new { Description = Descriptions[method] }) });
            await context.SaveChangesAsync();
        }
        catch (Exception ex) { auditFailure?.Invoke(ex); }
        return outcome;
    }

    private async Task<string> CaptureAsync(string method, int id)
    {
        if (method.StartsWith("courses.") && method != "courses.setBatchActive")
        {
            var c = await context.Courses.IgnoreQueryFilters().AsNoTracking().SingleOrDefaultAsync(c => c.Id == id)
                ?? throw new InvalidOperationException("Course no longer exists.");
            List<BatchUndoState>? batches = method == "courses.archive" ? await context.Batches.IgnoreQueryFilters().AsNoTracking()
                .Where(b => b.CourseId == id && !b.IsDeleted).OrderBy(b => b.Id).Select(b => new BatchUndoState(b.Id, b.Status)).ToListAsync() : null;
            return JsonSerializer.Serialize(new ChangeState(new(c.Id, c.CourseCode, c.CourseName, c.Category, c.Duration,
                c.StandardFee, c.TtbExamFee, c.Description, c.IsDeleted, c.DeletedAtUtc, c.DeletedBy), batches, null));
        }
        if (method == "courses.setBatchActive")
        {
            var b = await context.Batches.AsNoTracking().SingleOrDefaultAsync(b => b.Id == id && !b.IsDeleted)
                ?? throw new InvalidOperationException("Batch no longer exists.");
            return JsonSerializer.Serialize(new ChangeState(null, new() { new(b.Id, b.Status) }, null));
        }
        var student = await context.Students.IgnoreQueryFilters().AsNoTracking().SingleOrDefaultAsync(s => s.Id == id)
            ?? throw new InvalidOperationException("Student no longer exists.");
        return JsonSerializer.Serialize(new ChangeState(null, null, new(student.Id, student.IsDeleted, student.DeletedAtUtc, student.DeletedBy)));
    }

    public async Task<bool> UndoAsync(int activityId, string actor)
    {
        await UndoLock.WaitAsync();
        try
        {
            await using var tx = await context.Database.BeginTransactionAsync();
            var entry = await context.AuditLogs.AsNoTracking().SingleOrDefaultAsync(a => a.Id == activityId)
                ?? throw new InvalidOperationException("Activity not found.");
            if (!Undoable.Contains(entry.Action) || entry.OldValuesJson == null || entry.NewValuesJson == null)
                throw new InvalidOperationException("This activity cannot be undone. Use its normal correction workflow.");
            if (await context.AuditLogs.AnyAsync(a => a.Action == "Undo" && a.EntityName == "AuditLog" && a.EntityId == activityId.ToString()))
                throw new InvalidOperationException("This change has already been undone.");
            var newer = await context.AuditLogs.AsNoTracking().Where(a => a.Id > entry.Id && a.EntityName == entry.EntityName &&
                a.EntityId == entry.EntityId && a.OldValuesJson != null).Select(a => a.Id.ToString()).ToListAsync();
            var reversed = await context.AuditLogs.AsNoTracking().Where(a => a.Action == "Undo" && a.EntityName == "AuditLog" && newer.Contains(a.EntityId!))
                .Select(a => a.EntityId).ToListAsync();
            if (newer.Any(id => !reversed.Contains(id)))
                throw new InvalidOperationException("Undo the newer change to this record first.");
            if (!int.TryParse(entry.EntityId, out var id) || await CaptureAsync(entry.Action, id) != entry.NewValuesJson)
                throw new InvalidOperationException("This record changed again. Undo is blocked to protect newer changes.");
            var state = JsonSerializer.Deserialize<ChangeState>(entry.OldValuesJson)!;
            if (state.Course is { } c)
            {
                var course = await context.Courses.IgnoreQueryFilters().SingleAsync(x => x.Id == c.Id);
                course.CourseCode = c.CourseCode; course.CourseName = c.CourseName; course.Category = c.Category; course.Duration = c.Duration;
                course.StandardFee = c.StandardFee; course.TtbExamFee = c.TtbExamFee; course.Description = c.Description;
                course.IsDeleted = c.IsDeleted; course.DeletedAtUtc = c.DeletedAtUtc; course.DeletedBy = c.DeletedBy;
            }
            if (state.Batches != null)
                foreach (var b in state.Batches)
                {
                    var batch = await context.Batches.IgnoreQueryFilters().SingleAsync(x => x.Id == b.Id);
                    if (b.Status.Equals("active", StringComparison.OrdinalIgnoreCase) && state.Course == null &&
                        !await context.Courses.AnyAsync(c => c.Id == batch.CourseId && !c.IsDeleted))
                        throw new InvalidOperationException("Restore the course before reactivating this batch.");
                    batch.Status = b.Status;
                }
            if (state.Student is { } s)
            {
                var student = await context.Students.IgnoreQueryFilters().SingleAsync(x => x.Id == s.Id);
                student.IsDeleted = s.IsDeleted; student.DeletedAtUtc = s.DeletedAtUtc; student.DeletedBy = s.DeletedBy;
            }
            context.AuditLogs.Add(new AuditLog { Username = actor, Action = "Undo", EntityName = "AuditLog", EntityId = activityId.ToString(),
                NewValuesJson = JsonSerializer.Serialize(new { OriginalAction = entry.Action, OriginalEntityId = entry.EntityId }) });
            await context.SaveChangesAsync(); await tx.CommitAsync(); return true;
        }
        finally { UndoLock.Release(); }
    }

    public async Task<ActivityPage> GetDailyAsync(string date, string? search, int pageNumber = 1)
    {
        if (!DateTime.TryParseExact(date, "yyyy-MM-dd", CultureInfo.InvariantCulture, DateTimeStyles.None, out var day))
            throw new InvalidOperationException("Choose a valid activity date.");
        // College activity days use Pakistan time (UTC+05:00).
        var start = DateTime.SpecifyKind(day.AddHours(-5), DateTimeKind.Utc); var end = start.AddDays(1);
        var query = context.AuditLogs.AsNoTracking().Where(a => a.TimestampUtc >= start && a.TimestampUtc < end);
        if (!string.IsNullOrWhiteSpace(search))
        {
            var term = search.Trim().ToLower();
            query = query.Where(a => a.Username.ToLower().Contains(term) || a.Action.ToLower().Contains(term) ||
                a.EntityName.ToLower().Contains(term) || (a.EntityId != null && a.EntityId.Contains(term)));
        }
        var count = await query.CountAsync(); pageNumber = Math.Max(1, pageNumber);
        var rows = await query.OrderByDescending(a => a.Id).Skip((pageNumber - 1) * 50).Take(50).ToListAsync();
        var ids = rows.Select(r => r.Id.ToString()).ToList();
        var undone = await context.AuditLogs.AsNoTracking().Where(a => a.Action == "Undo" && a.EntityName == "AuditLog" && ids.Contains(a.EntityId!))
            .Select(a => a.EntityId).ToListAsync();
        return new(rows.Select(a => new ActivityItem(a.Id, a.TimestampUtc, a.Username, a.Action,
            Describe(a), a.EntityName, a.EntityId,
            Undoable.Contains(a.Action) && a.OldValuesJson != null && a.NewValuesJson != null && !undone.Contains(a.Id.ToString()),
            undone.Contains(a.Id.ToString()))).ToList(), count, pageNumber, (int)Math.Ceiling(count / 50d));
    }

    private static string Describe(AuditLog entry)
    {
        var label = Descriptions.GetValueOrDefault(entry.Action, entry.Action);
        if (!Undoable.Contains(entry.Action) || entry.NewValuesJson == null) return label;
        try
        {
            var current = JsonSerializer.Deserialize<ChangeState>(entry.NewValuesJson);
            var previous = entry.OldValuesJson == null ? null : JsonSerializer.Deserialize<ChangeState>(entry.OldValuesJson);
            if (current?.Course is { } course)
            {
                label += $": {course.CourseName}";
                if (entry.Action == "courses.update" && previous?.Course is { } old)
                {
                    if (old.StandardFee != course.StandardFee) label += $" · Tuition Rs. {old.StandardFee:N0} → {course.StandardFee:N0}";
                    if (old.TtbExamFee != course.TtbExamFee) label += $" · Exam fee Rs. {old.TtbExamFee:N0} → {course.TtbExamFee:N0}";
                }
            }
            else if (entry.Action == "courses.setBatchActive" && current?.Batches?.FirstOrDefault() is { } batch)
                label += $": {batch.Status}";
        }
        catch (JsonException) { /* Older audit rows may use a different format. */ }
        return label;
    }
}
