using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Interfaces;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public record StudentImportRequest(int BatchId, List<StudentImportRow> Rows);
public record StudentImportRow(string? StudentRegNo, string FullName, string? FatherName, string? ContactNumber, string? Cnic, string? Address);
public record MarksImportRequest(int BatchId, int Semester, string AcademicSession, List<MarksImportRow> Rows);
public record MarksImportRow(string StudentRegNo, string SubjectName, decimal TotalMarks, decimal ObtainedMarks);
public record AcademicImportResult(int Imported, int Skipped, List<string> Errors);
public record ExamOption(int Id, string Name, string AcademicSession, string? ClassesIncluded);

public class AcademicImportService(AppDbContext context)
{
    private static readonly SemaphoreSlim ImportLock = new(1, 1);
    public Task<List<ExamOption>> GetExamsAsync() => context.Examinations.AsNoTracking()
        .Where(e => !e.IsDeleted).OrderByDescending(e => e.Id)
        .Select(e => new ExamOption(e.Id, e.Name, e.AcademicSession, e.ClassesIncluded)).ToListAsync();

    public async Task<AcademicImportResult> ImportStudentsAsync(StudentImportRequest request)
    {
        if (request.Rows == null || request.Rows.Count is < 1 or > 5000)
            throw new InvalidOperationException("Import between 1 and 5,000 rows at a time.");
        var batch = await context.Batches.Include(b => b.Course).FirstOrDefaultAsync(b => b.Id == request.BatchId && !b.IsDeleted);
        if (batch == null || batch.Status.ToLower() != "active" || batch.Course == null || batch.Course.IsDeleted)
            throw new InvalidOperationException("Choose an active batch for the student import.");
        await ImportLock.WaitAsync();
        try
        {
            int imported = 0, skipped = 0;
            var errors = new List<string>();
            for (var i = 0; i < request.Rows.Count; i++)
            {
                var row = request.Rows[i];
                try
                {
                    if (!string.IsNullOrWhiteSpace(row.StudentRegNo) && await context.Students.IgnoreQueryFilters()
                        .AnyAsync(s => s.StudentRegNo.ToUpper() == row.StudentRegNo.Trim().ToUpper()))
                    { skipped++; continue; }
                    await new StudentService(context).CreateStudentAsync(new CreateStudentRequest(
                        row.StudentRegNo ?? "", row.FullName, row.FatherName, row.ContactNumber, null,
                        row.Cnic, null, null, row.Address, batch.Id, null, null));
                    imported++;
                }
                catch (Exception ex)
                {
                    errors.Add($"Row {i + 2}: {ex.Message}");
                    context.ChangeTracker.Clear();
                }
            }
            return new(imported, skipped, errors);
        }
        finally { ImportLock.Release(); }
    }

    public async Task<AcademicImportResult> ImportMarksAsync(MarksImportRequest request)
    {
        if (request.Rows == null || request.Rows.Count is < 1 or > 10000)
            throw new InvalidOperationException("Import between 1 and 10,000 marks rows at a time.");
        if (request.Semester is < 1 or > 2 || string.IsNullOrWhiteSpace(request.AcademicSession))
            throw new InvalidOperationException("Select a semester and enter the academic session.");
        var batch = await context.Batches.Include(b => b.Course).FirstOrDefaultAsync(b => b.Id == request.BatchId && !b.IsDeleted);
        if (batch == null || batch.Course == null) throw new InvalidOperationException("Batch not found.");
        if (request.Semester == 2 && !batch.Course.CourseCode.StartsWith("DIT", StringComparison.OrdinalIgnoreCase))
            throw new InvalidOperationException("Semester 2 is only available for DIT.");
        await ImportLock.WaitAsync();
        try
        {
            // Validate the whole worksheet before changing any results.
            var enrollment = await context.Enrollments.Include(e => e.Student)
                .Where(e => e.BatchId == batch.Id && !e.IsDeleted && e.Student != null && !e.Student.IsDeleted)
                .ToListAsync();
            var students = enrollment.GroupBy(e => e.Student!.StudentRegNo.ToUpper()).ToDictionary(g => g.Key, g => g.First().StudentId);
            var errors = new List<string>();
            var seen = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            for (int i = 0; i < request.Rows.Count; i++)
            {
                var r = request.Rows[i];
                if (string.IsNullOrWhiteSpace(r.StudentRegNo) || !students.ContainsKey(r.StudentRegNo.Trim().ToUpper()))
                    errors.Add($"Row {i + 2}: student is not enrolled in the selected batch.");
                if (string.IsNullOrWhiteSpace(r.SubjectName) || r.TotalMarks <= 0 || r.ObtainedMarks < 0 || r.ObtainedMarks > r.TotalMarks)
                    errors.Add($"Row {i + 2}: enter a subject and valid obtained/total marks.");
                if (!seen.Add($"{r.StudentRegNo?.Trim()}|{r.SubjectName?.Trim()}")) errors.Add($"Row {i + 2}: duplicate student/subject in this worksheet.");
            }
            if (errors.Count > 0) return new(0, 0, errors);
            await using var tx = await context.Database.BeginTransactionAsync();
            var key = $"Batch:{batch.Id};Semester:{request.Semester}";
            var exam = await context.Examinations.FirstOrDefaultAsync(e => !e.IsDeleted && e.ClassesIncluded == key && e.AcademicSession == request.AcademicSession.Trim());
            if (exam == null)
            {
                exam = new Examination { Name = $"{batch.BatchCode} — Semester {request.Semester}", AcademicSession = request.AcademicSession.Trim(), ClassesIncluded = key };
                context.Examinations.Add(exam);
                await context.SaveChangesAsync();
            }
            var service = new ResultService(context);
            foreach (var r in request.Rows)
                await service.SaveMarksAsync(new EnterMarksDto { ExaminationId = exam.Id, StudentId = students[r.StudentRegNo.Trim().ToUpper()], SubjectName = r.SubjectName.Trim(), TotalMarks = r.TotalMarks, ObtainedMarks = r.ObtainedMarks });
            await tx.CommitAsync();
            return new(request.Rows.Count, 0, new());
        }
        finally { ImportLock.Release(); }
    }
}
