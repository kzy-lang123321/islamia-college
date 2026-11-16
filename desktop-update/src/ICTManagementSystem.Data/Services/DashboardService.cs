using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using ICTManagementSystem.Core.Interfaces;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public class DashboardService : IDashboardService
{
    private readonly AppDbContext _context;

    public DashboardService(AppDbContext context)
    {
        _context = context;
    }

    public async Task<DashboardSummaryDto> GetDashboardSummaryAsync()
    {
        var totalStudents = await _context.Students.AsNoTracking().CountAsync(s => !s.IsDeleted);
        var activeBatches = await _context.Batches.AsNoTracking().CountAsync(b => !b.IsDeleted);

        var totalCollected = await _context.FeeTransactions
            .AsNoTracking()
            .Where(t => !t.IsCancelled && !t.IsDeleted)
            .SumAsync(t => t.AmountPaid);

        var totalAgreed = await _context.Enrollments
            .AsNoTracking()
            .Where(e => !e.IsDeleted)
            .SumAsync(e => e.TotalAgreedFee);

        var totalDues = Math.Max(0m, totalAgreed - totalCollected);

        var totalExpenses = await _context.CampusExpenses
            .AsNoTracking()
            .Where(e => !e.IsDeleted)
            .SumAsync(e => e.AmountPaid);

        var today = DateTime.UtcNow.ToString("yyyy-MM-dd");
        var todayTxs = await _context.FeeTransactions
            .AsNoTracking()
            .Where(t => t.PaymentDate == today && !t.IsCancelled && !t.IsDeleted)
            .ToListAsync();
        var todayCollected = todayTxs.Sum(t => t.AmountPaid);

        // Today's attendance
        var todayAttendance = await _context.AttendanceRecords
            .AsNoTracking()
            .Where(a => a.Date == today && !a.IsDeleted)
            .ToListAsync();

        var attendanceRate = 0m;
        if (todayAttendance.Any())
        {
            var presentCount = todayAttendance.Count(a => a.Status == AttendanceStatus.Present);
            attendanceRate = Math.Round(((decimal)presentCount / todayAttendance.Count) * 100m, 1);
        }

        var recent = await _context.FeeTransactions
            .AsNoTracking()
            .Where(t => !t.IsCancelled && !t.IsDeleted)
            .OrderByDescending(t => t.Id)
            .Take(7)
            .Select(t => new RecentTransactionDto
            {
                Id = t.Id,
                ReceiptNo = t.ReceiptNo,
                StudentName = t.Enrollment != null && t.Enrollment.Student != null ? t.Enrollment.Student.FullName : "Student",
                CourseName = t.Enrollment != null && t.Enrollment.Batch != null && t.Enrollment.Batch.Course != null ? t.Enrollment.Batch.Course.CourseName : "Course",
                AmountPaid = t.AmountPaid,
                PaymentDate = t.PaymentDate,
                ReceivedBy = t.ReceivedBy
            })
            .ToListAsync();

        return new DashboardSummaryDto
        {
            TotalStudents = totalStudents,
            ActiveBatches = activeBatches,
            TotalFeesCollected = totalCollected,
            TotalOutstandingDues = totalDues,
            TotalCampusExpenses = totalExpenses,
            TodayFeesCollected = todayCollected,
            TodayTransactionsCount = todayTxs.Count,
            AttendanceRateToday = attendanceRate,
            RecentTransactions = recent,
            SyncStatus = new SyncStatusDto
            {
                State = "Synced",
                LastSyncTime = DateTime.UtcNow.ToString("dd MMM yyyy, hh:mm tt"),
                PendingCount = 0,
                DriveAccount = "aminullahameen@gmail.com"
            }
        };
    }

    public async Task<List<TechnologyDisciplineStatDto>> GetTechnologyDisciplinesAsync()
    {
        var raw = await _context.Courses
            .AsNoTracking()
            .Where(c => !c.IsDeleted)
            .Select(c => new
            {
                c.Category,
                Count = c.Batches.Where(b => !b.IsDeleted).SelectMany(b => b.Enrollments).Count(e => !e.IsDeleted)
            })
            .ToListAsync();

        var grouped = raw
            .GroupBy(x => string.IsNullOrWhiteSpace(x.Category) ? "Other" : x.Category)
            .Select(g => new { Category = g.Key, Count = g.Sum(x => x.Count) })
            .OrderByDescending(x => x.Count)
            .Take(6)
            .ToList();

        var total = grouped.Sum(x => x.Count);
        if (total == 0) total = 1;

        return grouped.Select(x => new TechnologyDisciplineStatDto(
            x.Category,
            x.Count,
            Math.Round((x.Count / (decimal)total) * 100m, 1)
        )).ToList();
    }

    public async Task<List<FeeCollectionTrendDto>> GetFeeCollectionTrendAsync()
    {
        var txs = await _context.FeeTransactions
            .AsNoTracking()
            .Where(t => !t.IsCancelled && !t.IsDeleted && !string.IsNullOrEmpty(t.PaymentDate))
            .Select(t => new { t.PaymentDate, t.AmountPaid })
            .ToListAsync();

        var monthly = txs
            .GroupBy(t => t.PaymentDate.Length >= 7 ? t.PaymentDate.Substring(0, 7) : t.PaymentDate)
            .OrderBy(g => g.Key)
            .TakeLast(8)
            .Select(g =>
            {
                DateTime.TryParseExact(g.Key + "-01", "yyyy-MM-dd", System.Globalization.CultureInfo.InvariantCulture, System.Globalization.DateTimeStyles.None, out var dt);
                var label = dt != default ? dt.ToString("MMM yyyy") : g.Key;
                return new FeeCollectionTrendDto(label, g.Sum(x => x.AmountPaid));
            })
            .ToList();

        return monthly;
    }
}
