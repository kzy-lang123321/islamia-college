using System.Globalization;
using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using ICTManagementSystem.Core.Interfaces;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public class StudentService : IStudentService
{
    private static readonly SemaphoreSlim AdmissionLock = new(1, 1);
    private readonly AppDbContext _context;

    public StudentService(AppDbContext context)
    {
        _context = context;
    }

    public async Task<PagedResult<StudentListItemDto>> GetStudentsPagedAsync(
        string? search = null,
        int? batchId = null,
        int pageNumber = 1,
        int pageSize = 25,
        string? sortBy = null,
        bool sortDesc = false)
    {
        if (pageNumber < 1) pageNumber = 1;
        if (pageSize < 1) pageSize = 25;
        if (pageSize > 200) pageSize = 200;

        var query = _context.Students
            .AsNoTracking()
            .Where(s => !s.IsDeleted);

        if (batchId.HasValue && batchId.Value > 0)
        {
            query = query.Where(s => s.Enrollments.Any(e => !e.IsDeleted && e.BatchId == batchId.Value));
        }

        if (!string.IsNullOrWhiteSpace(search))
        {
            var term = search.Trim().ToLower();
            query = query.Where(s =>
                s.StudentRegNo.ToLower().Contains(term) ||
                s.FullName.ToLower().Contains(term) ||
                (s.FatherName != null && s.FatherName.ToLower().Contains(term)) ||
                (s.ContactNumber != null && s.ContactNumber.Contains(term)) ||
                s.Enrollments.Any(e => !e.IsDeleted && e.RollNumber != null && e.RollNumber.ToLower().Contains(term)));
        }

        var totalCount = await query.CountAsync();

        // Project directly to DTO to guarantee no cyclic references
        var projection = query.Select(s => new
        {
            s.Id,
            s.StudentRegNo,
            s.FullName,
            s.FatherName,
            s.ContactNumber,
            s.SecondaryContact,
            s.Cnic,
            s.DateOfBirth,
            s.Email,
            s.Address,
            LatestEnrollment = s.Enrollments
                .Where(e => !e.IsDeleted)
                .OrderByDescending(e => e.Id)
                .Select(e => new
                {
                    CourseName = e.Batch != null && e.Batch.Course != null ? e.Batch.Course.CourseName : null,
                    BatchCode = e.Batch != null ? e.Batch.BatchCode : null,
                    e.RollNumber,
                    e.TotalAgreedFee,
                    TotalPaid = e.FeeTransactions.Where(f => !f.IsDeleted && !f.IsCancelled).Sum(f => f.AmountPaid),
                    e.Status
                })
                .FirstOrDefault()
        });

        if (sortDesc)
        {
            projection = sortBy?.ToLower() switch
            {
                "regno" => projection.OrderByDescending(s => s.StudentRegNo),
                "name" => projection.OrderByDescending(s => s.FullName),
                _ => projection.OrderByDescending(s => s.Id)
            };
        }
        else
        {
            projection = sortBy?.ToLower() switch
            {
                "regno" => projection.OrderBy(s => s.StudentRegNo),
                "name" => projection.OrderBy(s => s.FullName),
                _ => projection.OrderBy(s => s.Id)
            };
        }

        var pagedData = await projection
            .Skip((pageNumber - 1) * pageSize)
            .Take(pageSize)
            .ToListAsync();

        var items = pagedData.Select(x =>
        {
            var agreed = x.LatestEnrollment?.TotalAgreedFee ?? 0m;
            var paid = x.LatestEnrollment?.TotalPaid ?? 0m;
            var due = Math.Max(0m, agreed - paid);
            var statusStr = x.LatestEnrollment != null
                ? (x.LatestEnrollment.Status == EnrollmentStatus.Active ? "Active" : x.LatestEnrollment.Status.ToString())
                : "Registered";

            return new StudentListItemDto(
                x.Id,
                x.StudentRegNo,
                x.FullName,
                x.FatherName,
                x.ContactNumber,
                x.SecondaryContact,
                x.Cnic,
                x.DateOfBirth,
                x.Email,
                x.Address,
                x.LatestEnrollment?.CourseName,
                x.LatestEnrollment?.BatchCode,
                x.LatestEnrollment?.RollNumber,
                agreed,
                paid,
                due,
                statusStr
            );
        }).ToList();

        var totalPages = (int)Math.Ceiling(totalCount / (double)pageSize);
        return new PagedResult<StudentListItemDto>(items, totalCount, pageNumber, pageSize, totalPages);
    }

    public async Task<List<StudentListItemDto>> GetAllStudentsAsync(string? search = null, int? batchId = null)
    {
        var paged = await GetStudentsPagedAsync(search, batchId, 1, 100);
        return paged.Items;
    }

    public async Task<StudentDetailDto?> GetStudentByIdAsync(int id)
    {
        var student = await _context.Students
            .AsNoTracking()
            .Where(s => s.Id == id && !s.IsDeleted)
            .Select(s => new
            {
                s.Id,
                s.StudentRegNo,
                s.FullName,
                s.FatherName,
                s.ContactNumber,
                s.SecondaryContact,
                s.Cnic,
                s.DateOfBirth,
                s.Email,
                s.Address,
                Enrollments = s.Enrollments
                    .Where(e => !e.IsDeleted)
                    .OrderByDescending(e => e.Id)
                    .Select(e => new StudentEnrollmentDto(
                        e.Id,
                        e.BatchId,
                        e.Batch != null ? e.Batch.BatchCode : "",
                        e.Batch != null ? e.Batch.BatchName : "",
                        e.Batch != null ? e.Batch.CourseId : 0,
                        e.Batch != null && e.Batch.Course != null ? e.Batch.Course.CourseName : "",
                        e.RollNumber,
                        e.EnrollmentDate,
                        e.TotalAgreedFee,
                        e.DiscountAmount,
                        e.Status.ToString()
                    )).ToList(),
                FeeTransactions = s.Enrollments
                    .Where(e => !e.IsDeleted)
                    .SelectMany(e => e.FeeTransactions)
                    .Where(f => !f.IsDeleted)
                    .OrderByDescending(f => f.PaymentDate)
                    .Select(f => new StudentFeeTxDto(
                        f.Id,
                        f.ReceiptNo,
                        f.AmountPaid,
                        f.DueAmount,
                        f.PaymentDate,
                        f.PaymentMethod.ToString(),
                        f.ReceivedBy,
                        f.Remarks,
                        f.IsCancelled
                    )).ToList(),
                Attendance = s.AttendanceRecords
                    .Where(a => !a.IsDeleted)
                    .OrderByDescending(a => a.Date)
                    .Take(30)
                    .Select(a => new StudentAttendanceSummaryDto(
                        a.Date,
                        a.Status.ToString(),
                        a.Remarks
                    )).ToList(),
                Results = s.ExamResults
                    .Where(r => !r.IsDeleted)
                    .Select(r => new StudentResultDto(
                        r.ExaminationId,
                        r.Examination != null ? r.Examination.Name : "",
                        r.SubjectName,
                        r.TotalMarks,
                        r.ObtainedMarks,
                        r.Grade,
                        r.Gpa
                    )).ToList(),
                Certificates = s.Certificates
                    .Where(c => !c.IsDeleted)
                    .Select(c => new StudentCertificateDto(
                        c.Id,
                        c.CertificateNo,
                        c.CertificateType.ToString(),
                        c.IssueDate,
                        c.CourseName,
                        c.GradeOrRemarks,
                        c.QrCodeData
                    )).ToList()
            })
            .FirstOrDefaultAsync();

        if (student == null) return null;

        return new StudentDetailDto(
            student.Id,
            student.StudentRegNo,
            student.FullName,
            student.FatherName,
            student.ContactNumber,
            student.SecondaryContact,
            student.Cnic,
            student.DateOfBirth,
            student.Email,
            student.Address,
            student.Enrollments,
            student.FeeTransactions,
            student.Attendance,
            student.Results,
            student.Certificates
        );
    }

    public async Task<StudentDetailDto?> GetStudentByRegNoAsync(string regNo)
    {
        var id = await _context.Students
            .AsNoTracking()
            .Where(s => s.StudentRegNo.ToLower() == regNo.Trim().ToLower() && !s.IsDeleted)
            .Select(s => s.Id)
            .FirstOrDefaultAsync();

        return id > 0 ? await GetStudentByIdAsync(id) : null;
    }

    public async Task<StudentDetailDto> CreateStudentAsync(CreateStudentRequest request)
    {
        await AdmissionLock.WaitAsync();
        try
        {
            await using var transaction = await _context.Database.BeginTransactionAsync();
            Batch? batch = null;
            if (request.BatchId is > 0)
            {
                batch = await _context.Batches.Include(b => b.Course)
                    .FirstOrDefaultAsync(b => b.Id == request.BatchId && !b.IsDeleted);
                if (batch == null || !string.Equals(batch.Status, "active", StringComparison.OrdinalIgnoreCase) || batch.Course == null || batch.Course.IsDeleted)
                    throw new InvalidOperationException("Select an active academic batch before admission.");
            }
            if (string.IsNullOrWhiteSpace(request.FullName))
                throw new InvalidOperationException("Student name is required.");
            // Preview numbers are advisory. Allocate the final number inside this transaction.
            var regNo = string.IsNullOrWhiteSpace(request.StudentRegNo)
                ? await GetNextRegistrationNoAsync(batch?.Course?.CourseCode ?? "DIT")
                : request.StudentRegNo.Trim().ToUpperInvariant();
            if (await _context.Students.IgnoreQueryFilters().AnyAsync(s => s.StudentRegNo.ToUpper() == regNo.ToUpper()))
                throw new InvalidOperationException($"Registration No '{regNo}' is already assigned. Import rows are never overwritten.");
            var student = new Student
            {
                StudentRegNo = regNo, FullName = request.FullName.Trim(),
                FatherName = request.FatherName?.Trim(),
                ContactNumber = PakistanIdentity.Phone(request.ContactNumber),
                SecondaryContact = PakistanIdentity.Phone(request.SecondaryContact),
                Cnic = PakistanIdentity.Cnic(request.Cnic), DateOfBirth = request.DateOfBirth,
                Email = request.Email?.Trim(), Address = request.Address?.Trim()
            };
            _context.Students.Add(student);
            await _context.SaveChangesAsync();
            if (batch != null)
            {
                _context.Enrollments.Add(new Enrollment
                {
                    StudentId = student.Id, BatchId = batch.Id,
                    RollNumber = request.RollNumber ?? regNo.Split('-').Last(),
                    TotalAgreedFee = request.AgreedFee ?? batch.Course!.StandardFee,
                    EnrollmentDate = DateTime.Today.ToString("yyyy-MM-dd"),
                    AcademicSession = $"{DateTime.Today.Year}-{DateTime.Today.Year + 1}",
                    Status = EnrollmentStatus.Active
                });
            }
            _context.AuditLogs.Add(new AuditLog
            {
                Action = "Create", EntityName = "Student", EntityId = student.Id.ToString(), Username = "Admin",
                NewValuesJson = System.Text.Json.JsonSerializer.Serialize(new { student.Id, student.StudentRegNo, student.FullName })
            });
            await _context.SaveChangesAsync();
            await transaction.CommitAsync();
            return (await GetStudentByIdAsync(student.Id))!;
        }
        finally { AdmissionLock.Release(); }
    }

    public async Task<StudentDetailDto> UpdateStudentAsync(UpdateStudentRequest request)
    {
        var student = await _context.Students.FirstOrDefaultAsync(s => s.Id == request.Id && !s.IsDeleted);
        if (student == null)
        {
            throw new InvalidOperationException($"Student with ID {request.Id} not found.");
        }

        if (!string.Equals(student.StudentRegNo, request.StudentRegNo, StringComparison.OrdinalIgnoreCase))
        {
            var exists = await _context.Students.IgnoreQueryFilters().AnyAsync(s => s.Id != request.Id && s.StudentRegNo.ToLower() == request.StudentRegNo.Trim().ToLower());
            if (exists)
            {
                throw new InvalidOperationException($"Registration No '{request.StudentRegNo}' is already assigned to another student.");
            }
            student.StudentRegNo = request.StudentRegNo.Trim();
        }

        student.FullName = request.FullName.Trim();
        student.FatherName = request.FatherName?.Trim();
        student.ContactNumber = PakistanIdentity.Phone(request.ContactNumber);
        student.SecondaryContact = PakistanIdentity.Phone(request.SecondaryContact);
        student.Cnic = PakistanIdentity.Cnic(request.Cnic);
        student.DateOfBirth = request.DateOfBirth;
        student.Email = request.Email?.Trim();
        student.Address = request.Address?.Trim();

        await _context.SaveChangesAsync();
        return (await GetStudentByIdAsync(student.Id))!;
    }

    public async Task<bool> DeleteStudentAsync(int id, string deletedBy)
    {
        var student = await _context.Students.FirstOrDefaultAsync(s => s.Id == id && !s.IsDeleted);
        if (student == null) return false;

        student.IsDeleted = true;
        student.DeletedAtUtc = DateTime.UtcNow;
        student.DeletedBy = deletedBy;

        _context.AuditLogs.Add(new AuditLog
        {
            Action = "SoftDelete",
            EntityName = "Student",
            EntityId = id.ToString(),
            Username = deletedBy
        });

        await _context.SaveChangesAsync();
        return true;
    }

    public async Task<string> GetNextRegistrationNoAsync(string category = "DIT")
    {
        category = System.Text.RegularExpressions.Regex.Match((category ?? "DIT").ToUpperInvariant().Trim(), @"^[A-Z]+").Value;
        if (category.Length == 0) category = "DIT";
        var prefix = $"ICT-{category}-";
        var numbers = await _context.Students.IgnoreQueryFilters().AsNoTracking()
            .Where(s => s.StudentRegNo.ToUpper().StartsWith(prefix))
            .Select(s => s.StudentRegNo).ToListAsync();
        long max = 0;
        foreach (var reg in numbers)
            if (long.TryParse(reg[prefix.Length..], out var number)) max = Math.Max(max, number);
        return $"{prefix}{checked(max + 1):D3}";
    }
}
