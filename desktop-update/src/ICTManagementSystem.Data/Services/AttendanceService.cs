using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Interfaces;
using ICTManagementSystem.Core.Services;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public class AttendanceService : IAttendanceService
{
    private readonly AppDbContext _context;

    public AttendanceService(AppDbContext context)
    {
        _context = context;
    }

    public async Task<List<AttendanceRecordDto>> GetAttendanceByBatchAndDateAsync(int batchId, string date)
    {
        return await _context.AttendanceRecords
            .AsNoTracking()
            .Where(a => a.BatchId == batchId && a.Date == date && !a.IsDeleted)
            .Select(a => new AttendanceRecordDto(
                a.Id,
                a.StudentId,
                a.Student != null ? a.Student.StudentRegNo : "",
                a.Student != null ? a.Student.FullName : "",
                a.BatchId,
                a.Date,
                a.Status.ToString(),
                a.Remarks,
                a.RecordedBy
            ))
            .ToListAsync();
    }

    public async Task<bool> RecordAttendanceAsync(MarkAttendanceDto dto)
    {
        foreach (var item in dto.Items)
        {
            var existing = await _context.AttendanceRecords
                .FirstOrDefaultAsync(a => a.BatchId == dto.BatchId && a.StudentId == item.StudentId && a.Date == dto.Date);

            if (existing != null)
            {
                existing.Status = item.Status;
                existing.Remarks = item.Remarks;
                existing.RecordedBy = dto.RecordedBy;
            }
            else
            {
                _context.AttendanceRecords.Add(new AttendanceRecord
                {
                    BatchId = dto.BatchId,
                    StudentId = item.StudentId,
                    Date = dto.Date,
                    Status = item.Status,
                    Remarks = item.Remarks,
                    RecordedBy = dto.RecordedBy
                });
            }
        }

        await _context.SaveChangesAsync();
        return true;
    }

    public async Task<List<dynamic>> GetMonthlyAttendanceSheetAsync(int batchId, int year, int month)
    {
        var prefix = $"{year:D4}-{month:D2}";
        var records = await _context.AttendanceRecords
            .Include(a => a.Student)
            .Where(a => a.BatchId == batchId && a.Date.StartsWith(prefix))
            .ToListAsync();

        var enrollments = await _context.Enrollments
            .Include(e => e.Student)
            .Where(e => e.BatchId == batchId)
            .ToListAsync();

        var distinctDates = records.Select(r => r.Date).Distinct().OrderBy(d => d).ToList();
        var totalDays = distinctDates.Count;

        var result = new List<dynamic>();

        foreach (var en in enrollments)
        {
            if (en.Student == null) continue;

            var studentRecords = records.Where(r => r.StudentId == en.StudentId).ToList();
            var presentCount = studentRecords.Count(r => r.Status == Core.Enums.AttendanceStatus.Present);
            var leaveCount = studentRecords.Count(r => r.Status == Core.Enums.AttendanceStatus.Leave);
            var absentCount = studentRecords.Count(r => r.Status == Core.Enums.AttendanceStatus.Absent);

            var pct = AcademicCalculations.CalculateAttendancePercentage(presentCount + leaveCount, totalDays > 0 ? totalDays : 1);

            result.Add(new
            {
                StudentId = en.StudentId,
                StudentRegNo = en.Student.StudentRegNo,
                FullName = en.Student.FullName,
                RollNumber = en.RollNumber ?? "—",
                TotalDays = totalDays,
                Present = presentCount,
                Leave = leaveCount,
                Absent = absentCount,
                Percentage = pct,
                IsShortAttendance = pct < 75m
            });
        }

        return result;
    }
}

