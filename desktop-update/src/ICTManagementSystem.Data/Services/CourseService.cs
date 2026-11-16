using System.Globalization;
using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using ICTManagementSystem.Core.Interfaces;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public class CourseService : ICourseService
{
    private readonly AppDbContext _context;

    public CourseService(AppDbContext context)
    {
        _context = context;
    }

    public async Task<PagedResult<CourseListItemDto>> GetCoursesPagedAsync(
        string? search = null,
        string? category = null,
        int pageNumber = 1,
        int pageSize = 25,
        bool? activeOnly = null)
    {
        if (pageNumber < 1) pageNumber = 1;
        if (pageSize < 1) pageSize = 25;

        var query = _context.Courses
            .AsNoTracking()
            .Where(c => !c.IsDeleted);

        if (!string.IsNullOrWhiteSpace(search))
        {
            var term = search.Trim().ToLower();
            query = query.Where(c => c.CourseCode.ToLower().Contains(term) || c.CourseName.ToLower().Contains(term));
        }

        if (!string.IsNullOrWhiteSpace(category) && category != "All")
        {
            query = query.Where(c => c.Category.ToLower() == category.Trim().ToLower());
        }

        var totalCount = await query.CountAsync();

        var pagedItems = await query
            .OrderBy(c => c.CourseName)
            .Skip((pageNumber - 1) * pageSize)
            .Take(pageSize)
            .Select(c => new CourseListItemDto(
                c.Id,
                c.CourseCode,
                c.CourseName,
                c.Category,
                c.Duration,
                c.StandardFee,
                c.TtbExamFee,
                c.Batches.Count(b => !b.IsDeleted),
                c.Batches.Where(b => !b.IsDeleted).SelectMany(b => b.Enrollments).Count(e => !e.IsDeleted),
                c.Batches.Where(b => !b.IsDeleted).SelectMany(b => b.Enrollments).SelectMany(e => e.FeeTransactions).Where(f => !f.IsDeleted && !f.IsCancelled).Sum(f => f.AmountPaid),
                Math.Max(0m,
                    c.Batches.Where(b => !b.IsDeleted).SelectMany(b => b.Enrollments).Where(e => !e.IsDeleted).Sum(e => e.TotalAgreedFee) -
                    c.Batches.Where(b => !b.IsDeleted).SelectMany(b => b.Enrollments).SelectMany(e => e.FeeTransactions).Where(f => !f.IsDeleted && !f.IsCancelled).Sum(f => f.AmountPaid)),
                true
            ))
            .ToListAsync();

        var totalPages = (int)Math.Ceiling(totalCount / (double)pageSize);
        return new PagedResult<CourseListItemDto>(pagedItems, totalCount, pageNumber, pageSize, totalPages);
    }

    public async Task<List<CourseListItemDto>> GetAllCoursesAsync(bool? activeOnly = null)
    {
        var result = await GetCoursesPagedAsync(null, null, 1, 200, activeOnly);
        return result.Items;
    }

    public async Task<CourseDetailDto?> GetCourseByIdAsync(int id)
    {
        var course = await _context.Courses
            .AsNoTracking()
            .Where(c => c.Id == id && !c.IsDeleted)
            .FirstOrDefaultAsync();

        if (course == null) return null;

        var batches = await _context.Batches
            .AsNoTracking()
            .Where(b => b.CourseId == id && !b.IsDeleted)
            .Select(b => new BatchDto(
                b.Id,
                b.BatchCode,
                b.BatchName,
                b.CourseId,
                course.CourseName,
                b.StartDate,
                b.EndDate,
                b.Enrollments.Count(e => !e.IsDeleted && e.Status == EnrollmentStatus.Active),
                b.Status.ToString()
            ))
            .ToListAsync();

        var enrollments = await _context.Enrollments
            .AsNoTracking()
            .Where(e => !e.IsDeleted && e.Batch != null && e.Batch.CourseId == id)
            .Select(e => new EnrollmentListItemDto(
                e.Id,
                e.StudentId,
                e.Student != null ? e.Student.StudentRegNo : "",
                e.Student != null ? e.Student.FullName : "",
                e.Student != null ? e.Student.FatherName : null,
                e.BatchId,
                e.Batch != null ? e.Batch.BatchCode : "",
                e.Batch != null ? e.Batch.BatchName : "",
                id,
                course.CourseName,
                e.RollNumber,
                e.EnrollmentDate,
                e.TotalAgreedFee,
                e.FeeTransactions.Where(f => !f.IsDeleted && !f.IsCancelled).Sum(f => f.AmountPaid),
                Math.Max(0m, e.TotalAgreedFee - e.FeeTransactions.Where(f => !f.IsDeleted && !f.IsCancelled).Sum(f => f.AmountPaid)),
                e.Status.ToString()
            ))
            .ToListAsync();

        decimal totalCollected = enrollments.Sum(e => e.TotalPaid);
        decimal totalAgreed = enrollments.Sum(e => e.TotalAgreedFee);
        decimal totalDues = Math.Max(0m, totalAgreed - totalCollected);

        return new CourseDetailDto(
            course.Id,
            course.CourseCode,
            course.CourseName,
            course.Category,
            course.Duration,
            course.StandardFee,
            course.TtbExamFee,
            course.Description,
            true,
            batches,
            new List<SubjectDto>(),
            enrollments,
            totalCollected,
            totalDues
        );
    }

    public async Task<CourseListItemDto> CreateCourseAsync(CreateCourseRequest request)
    {
        if (string.IsNullOrWhiteSpace(request.CourseName) || request.StandardFee < 0 || request.TtbExamFee < 0)
            throw new InvalidOperationException("Course title is required and fees cannot be negative.");
        var code = request.CourseCode?.Trim().ToUpperInvariant();
        if (string.IsNullOrWhiteSpace(code))
        {
            var title = request.CourseName.ToUpperInvariant();
            var stem = title.Contains("DIT") || title.Contains("DIPLOMA IN INFORMATION") ? "DIT"
                : title.Contains("ENGLISH") || title == "ELL" ? "ELL"
                : string.Concat(title.Split(' ', StringSplitOptions.RemoveEmptyEntries).Select(w => w[0]));
            stem = new string(stem.Where(char.IsLetterOrDigit).Take(12).ToArray());
            if (stem.Length == 0) stem = "COURSE";
            code = stem;
            var suffix = 1;
            while (await _context.Courses.IgnoreQueryFilters().AnyAsync(c => c.CourseCode == code)) code = $"{stem}{suffix++}";
        }
        var exists = await _context.Courses.IgnoreQueryFilters().AnyAsync(c => c.CourseCode.ToUpper() == code);
        if (exists)
        {
            throw new InvalidOperationException($"Course code '{request.CourseCode}' is already registered.");
        }

        var course = new Course
        {
            CourseCode = code,
            CourseName = request.CourseName.Trim(),
            Category = string.IsNullOrWhiteSpace(request.Category) ? "Information Technology" : request.Category.Trim(),
            Duration = string.IsNullOrWhiteSpace(request.Duration) ? "1 Year" : request.Duration.Trim(),
            StandardFee = request.StandardFee,
            TtbExamFee = request.TtbExamFee,
            Description = request.Description?.Trim()
        };

        _context.Courses.Add(course);
        await _context.SaveChangesAsync();

        return new CourseListItemDto(
            course.Id,
            course.CourseCode,
            course.CourseName,
            course.Category,
            course.Duration,
            course.StandardFee,
            course.TtbExamFee,
            0,
            0,
            0m,
            0m,
            true
        );
    }

    public async Task<CourseListItemDto> UpdateCourseAsync(UpdateCourseRequest request)
    {
        var course = await _context.Courses.FirstOrDefaultAsync(c => c.Id == request.Id && !c.IsDeleted);
        if (course == null) throw new InvalidOperationException($"Course ID {request.Id} not found.");

        if (string.IsNullOrWhiteSpace(request.CourseName) || request.StandardFee < 0 || request.TtbExamFee < 0)
            throw new InvalidOperationException("Course title is required and fees cannot be negative.");
        // Keep the internal prefix stable when staff edit course fees or titles.
        course.CourseName = request.CourseName.Trim();
        course.Category = request.Category.Trim();
        course.Duration = request.Duration.Trim();
        course.StandardFee = request.StandardFee;
        course.TtbExamFee = request.TtbExamFee;
        course.Description = request.Description?.Trim();

        await _context.SaveChangesAsync();

        return (await GetCourseByIdAsync(course.Id)) is { } detail
            ? new CourseListItemDto(detail.Id, detail.CourseCode, detail.CourseName, detail.Category, detail.Duration, detail.StandardFee, detail.TtbExamFee, detail.Batches.Count, detail.Enrollments.Count, detail.TotalFeesCollected, detail.TotalOutstandingDues, true)
            : throw new InvalidOperationException("Failed to reload course");
    }

    public async Task<bool> ArchiveCourseAsync(int id)
    {
        var course = await _context.Courses.FirstOrDefaultAsync(c => c.Id == id && !c.IsDeleted);
        if (course == null) return false;

        // Hide the course and close admissions while retaining student and financial history.
        var batches = await _context.Batches.Where(b => b.CourseId == id && !b.IsDeleted).ToListAsync();
        foreach (var batch in batches) batch.Status = "inactive";
        course.IsDeleted = true;
        course.DeletedAtUtc = DateTime.UtcNow;
        await _context.SaveChangesAsync();
        return true;
    }

    public async Task<List<BatchDto>> GetBatchesAsync(int? courseId = null)
    {
        var query = _context.Batches
            .AsNoTracking()
            .Where(b => !b.IsDeleted);

        if (courseId.HasValue && courseId.Value > 0)
        {
            query = query.Where(b => b.CourseId == courseId.Value);
        }

        return await query
            .OrderByDescending(b => b.Id)
            .Select(b => new BatchDto(
                b.Id,
                b.BatchCode,
                b.BatchName,
                b.CourseId,
                b.Course != null ? b.Course.CourseName : "",
                b.StartDate,
                b.EndDate,
                b.Enrollments.Count(e => !e.IsDeleted && e.Status == EnrollmentStatus.Active),
                b.Status.ToString()
            ))
            .ToListAsync();
    }

    public async Task<BatchDto> CreateBatchAsync(CreateBatchRequest request)
    {
        if (string.IsNullOrWhiteSpace(request.BatchCode) || string.IsNullOrWhiteSpace(request.BatchName))
            throw new InvalidOperationException("Batch code and title are required.");
        if (!string.IsNullOrWhiteSpace(request.StartDate) &&
            !DateTime.TryParseExact(request.StartDate, "yyyy-MM-dd", CultureInfo.InvariantCulture, DateTimeStyles.None, out _))
            throw new InvalidOperationException("Batch start date must use yyyy-MM-dd format.");
        var exists = await _context.Batches.AnyAsync(b => b.BatchCode.ToLower() == request.BatchCode.Trim().ToLower() && !b.IsDeleted);
        if (exists) throw new InvalidOperationException($"Batch code '{request.BatchCode}' already exists.");

        var course = await _context.Courses.FirstOrDefaultAsync(c => c.Id == request.CourseId && !c.IsDeleted);
        if (course == null) throw new InvalidOperationException("Referenced course not found.");

        var batch = new Batch
        {
            BatchCode = request.BatchCode.Trim().ToUpper(),
            BatchName = request.BatchName.Trim(),
            CourseId = request.CourseId,
            StartDate = string.IsNullOrWhiteSpace(request.StartDate) ? DateTime.UtcNow.ToString("yyyy-MM-dd") : request.StartDate,
            Status = "active"
        };

        _context.Batches.Add(batch);
        await _context.SaveChangesAsync();

        return new BatchDto(
            batch.Id,
            batch.BatchCode,
            batch.BatchName,
            batch.CourseId,
            course.CourseName,
            batch.StartDate,
            batch.EndDate,
            0,
            "Active"
        );
    }

    public async Task<bool> SetBatchActiveAsync(int id, bool active)
    {
        var batch = await _context.Batches.Include(b => b.Course).FirstOrDefaultAsync(b => b.Id == id && !b.IsDeleted);
        if (batch == null || batch.Course == null || batch.Course.IsDeleted) throw new InvalidOperationException("Batch or course not found.");
        batch.Status = active ? "active" : "inactive";
        await _context.SaveChangesAsync();
        return true;
    }

    public async Task<List<SubjectDto>> GetSubjectsAsync(int? courseId = null)
    {
        // Return standard curriculum subjects
        var subjects = new List<SubjectDto>
        {
            new(1, "DIT-101", "Information & Communication Technology", courseId ?? 1, "DIT", 100m, 40m),
            new(2, "DIT-102", "Office Automation (Word, Excel, PowerPoint)", courseId ?? 1, "DIT", 100m, 40m),
            new(3, "DIT-103", "Computer Networks & Internet", courseId ?? 1, "DIT", 100m, 40m),
            new(4, "DIT-104", "Programming Fundamentals (C / C++)", courseId ?? 1, "DIT", 100m, 40m),
            new(5, "DIT-201", "Relational Database Management (MySQL)", courseId ?? 1, "DIT", 100m, 40m),
            new(6, "DIT-202", "Web Design & Development (HTML/CSS/JS)", courseId ?? 1, "DIT", 100m, 40m),
            new(7, "DIT-203", "Graphic Design & Digital Publishing", courseId ?? 1, "DIT", 100m, 40m)
        };
        return await Task.FromResult(subjects);
    }

    public async Task<SubjectDto> CreateSubjectAsync(CreateSubjectRequest request)
    {
        var course = await _context.Courses.FirstOrDefaultAsync(c => c.Id == request.CourseId && !c.IsDeleted);
        return new SubjectDto(
            new Random().Next(100, 999),
            request.SubjectCode.Trim().ToUpper(),
            request.SubjectName.Trim(),
            request.CourseId,
            course?.CourseName ?? "",
            request.TotalMarks,
            request.PassingMarks
        );
    }

    public async Task<List<EnrollmentListItemDto>> GetEnrollmentsAsync(int? courseId = null, int? batchId = null)
    {
        var query = _context.Enrollments
            .AsNoTracking()
            .Where(e => !e.IsDeleted);

        if (batchId.HasValue && batchId.Value > 0)
        {
            query = query.Where(e => e.BatchId == batchId.Value);
        }

        if (courseId.HasValue && courseId.Value > 0)
        {
            query = query.Where(e => e.Batch != null && e.Batch.CourseId == courseId.Value);
        }

        return await query
            .OrderByDescending(e => e.Id)
            .Take(150)
            .Select(e => new EnrollmentListItemDto(
                e.Id,
                e.StudentId,
                e.Student != null ? e.Student.StudentRegNo : "",
                e.Student != null ? e.Student.FullName : "",
                e.Student != null ? e.Student.FatherName : null,
                e.BatchId,
                e.Batch != null ? e.Batch.BatchCode : "",
                e.Batch != null ? e.Batch.BatchName : "",
                e.Batch != null ? e.Batch.CourseId : 0,
                e.Batch != null && e.Batch.Course != null ? e.Batch.Course.CourseName : "",
                e.RollNumber,
                e.EnrollmentDate,
                e.TotalAgreedFee,
                e.FeeTransactions.Where(f => !f.IsDeleted && !f.IsCancelled).Sum(f => f.AmountPaid),
                Math.Max(0m, e.TotalAgreedFee - e.FeeTransactions.Where(f => !f.IsDeleted && !f.IsCancelled).Sum(f => f.AmountPaid)),
                e.Status.ToString()
            ))
            .ToListAsync();
    }

    public async Task<EnrollmentListItemDto> CreateEnrollmentAsync(CreateEnrollmentRequest request)
    {
        var student = await _context.Students.FirstOrDefaultAsync(s => s.Id == request.StudentId && !s.IsDeleted);
        if (student == null) throw new InvalidOperationException("Student record not found.");

        var batch = await _context.Batches.Include(b => b.Course).FirstOrDefaultAsync(b => b.Id == request.BatchId && !b.IsDeleted);
        if (batch == null) throw new InvalidOperationException("Selected batch not found.");

        if (!string.Equals(batch.Status, "active", StringComparison.OrdinalIgnoreCase) || batch.Course == null || batch.Course.IsDeleted)
            throw new InvalidOperationException("Enrollment is only available for active academic batches.");
        var exists = await _context.Enrollments.AnyAsync(e => e.StudentId == request.StudentId && e.BatchId == request.BatchId && !e.IsDeleted);
        if (exists) throw new InvalidOperationException("This student is already enrolled in the selected batch.");

        var en = new Enrollment
        {
            StudentId = request.StudentId,
            BatchId = request.BatchId,
            RollNumber = request.RollNumber,
            TotalAgreedFee = (batch.Course?.StandardFee ?? 0m),
            AdmissionFee = request.AdmissionFee,
            DiscountAmount = request.DiscountAmount,
            EnrollmentDate = string.IsNullOrWhiteSpace(request.EnrollmentDate) ? DateTime.UtcNow.ToString("yyyy-MM-dd") : request.EnrollmentDate,
            AcademicSession = $"{DateTime.UtcNow.Year}-{DateTime.UtcNow.Year + 1}",
            Status = EnrollmentStatus.Active
        };

        _context.Enrollments.Add(en);
        await _context.SaveChangesAsync();

        return new EnrollmentListItemDto(
            en.Id,
            student.Id,
            student.StudentRegNo,
            student.FullName,
            student.FatherName,
            batch.Id,
            batch.BatchCode,
            batch.BatchName,
            batch.CourseId,
            batch.Course?.CourseName ?? "",
            en.RollNumber,
            en.EnrollmentDate,
            en.TotalAgreedFee,
            0m,
            en.TotalAgreedFee,
            "Active"
        );
    }

    public async Task<List<TechnologyDisciplineStatDto>> GetTechnologyDisciplinesDistributionAsync()
    {
        var categories = await _context.Courses
            .AsNoTracking()
            .Where(c => !c.IsDeleted)
            .GroupBy(c => c.Category)
            .Select(g => new
            {
                Category = g.Key,
                Count = g.SelectMany(c => c.Batches).SelectMany(b => b.Enrollments).Count(e => !e.IsDeleted)
            })
            .OrderByDescending(x => x.Count)
            .ToListAsync();

        var total = categories.Sum(c => c.Count);
        if (total == 0) total = 1;

        return categories.Select(c => new TechnologyDisciplineStatDto(
            string.IsNullOrWhiteSpace(c.Category) ? "General Technologies" : c.Category,
            c.Count,
            Math.Round((c.Count / (decimal)total) * 100m, 1)
        )).ToList();
    }
}
