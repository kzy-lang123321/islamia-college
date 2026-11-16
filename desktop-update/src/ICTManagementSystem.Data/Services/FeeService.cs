using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Interfaces;
using ICTManagementSystem.Core.Services;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public class FeeService : IFeeService
{
    private readonly AppDbContext _context;

    public FeeService(AppDbContext context)
    {
        _context = context;
    }

    public async Task<StudentFeeDetailsDto?> GetStudentFeeDetailsAsync(int enrollmentId)
    {
        var enrollment = await _context.Enrollments
            .Include(e => e.Student)
            .Include(e => e.Batch)
                .ThenInclude(b => b!.Course)
            .Include(e => e.FeeTransactions)
            .FirstOrDefaultAsync(e => e.Id == enrollmentId);

        if (enrollment == null || enrollment.Student == null) return null;

        var validTransactions = enrollment.FeeTransactions.Where(t => !t.IsCancelled && !t.IsDeleted).ToList();
        var totalPaid = validTransactions.Sum(t => t.AmountPaid);
        var totalFines = validTransactions.Sum(t => t.FineAmount);
        var totalDiscounts = enrollment.DiscountAmount + validTransactions.Sum(t => t.DiscountAmount);

        var netPayable = Math.Max(0m, (enrollment.TotalAgreedFee - totalPaid) + totalFines - totalDiscounts);

        return new StudentFeeDetailsDto
        {
            EnrollmentId = enrollment.Id,
            StudentId = enrollment.StudentId,
            StudentRegNo = enrollment.Student.StudentRegNo,
            FullName = enrollment.Student.FullName,
            FatherName = enrollment.Student.FatherName ?? "N/A",
            CourseName = enrollment.Batch?.Course?.CourseName ?? "General Course",
            BatchCode = enrollment.Batch?.BatchCode ?? "N/A",
            RollNumber = enrollment.RollNumber,
            TotalAgreedFee = enrollment.TotalAgreedFee,
            TotalPaid = totalPaid,
            CurrentDues = netPayable,
            PendingFines = totalFines,
            AvailableDiscounts = totalDiscounts,
            TotalPayable = netPayable,
            PaymentHistory = enrollment.FeeTransactions.OrderByDescending(t => t.Id).Select(t => new FeeHistoryItemDto
            {
                Id = t.Id,
                ReceiptNo = t.ReceiptNo,
                AmountPaid = t.AmountPaid,
                DueAmount = t.DueAmount,
                PaymentDate = t.PaymentDate,
                PaymentMethod = t.PaymentMethod.ToString(),
                ReceivedBy = t.ReceivedBy,
                IsCancelled = t.IsCancelled
            }).ToList()
        };
    }

    public async Task<StudentFeeDetailsDto?> SearchStudentForFeeAsync(string query)
    {
        if (string.IsNullOrWhiteSpace(query)) return null;
        var term = query.Trim().ToLower();

        var enrollment = await _context.Enrollments
            .Include(e => e.Student)
            .Include(e => e.Batch)
                .ThenInclude(b => b!.Course)
            .Include(e => e.FeeTransactions)
            .Where(e =>
                e.Student!.StudentRegNo.ToLower().Contains(term) ||
                (e.RollNumber != null && e.RollNumber.ToLower().Contains(term)) ||
                e.Student.FullName.ToLower().Contains(term) ||
                (e.Student.ContactNumber != null && e.Student.ContactNumber.Contains(term)))
            .OrderByDescending(e => e.Id)
            .FirstOrDefaultAsync();

        if (enrollment == null) return null;
        return await GetStudentFeeDetailsAsync(enrollment.Id);
    }

    public async Task<ReceivePaymentResult> ReceivePaymentAsync(ReceivePaymentRequest request)
    {
        // Enforce atomic database transaction
        using var transaction = await _context.Database.BeginTransactionAsync();
        try
        {
            var enrollment = await _context.Enrollments
                .Include(e => e.Student)
                .Include(e => e.Batch)
                    .ThenInclude(b => b!.Course)
                .Include(e => e.FeeTransactions)
                .FirstOrDefaultAsync(e => e.Id == request.EnrollmentId);

            if (enrollment == null || enrollment.Student == null)
            {
                return new ReceivePaymentResult { Success = false, Message = "Student enrollment record not found." };
            }

            var previousPaid = enrollment.FeeTransactions.Where(t => !t.IsCancelled && !t.IsDeleted).Sum(t => t.AmountPaid);

            // Invariant Check
            var (isValid, error, remainingBalance) = AcademicCalculations.CalculatePaymentBalance(
                enrollment.TotalAgreedFee,
                previousPaid,
                request.AmountPaid,
                request.FineAmount,
                request.DiscountAmount);

            if (!isValid)
            {
                return new ReceivePaymentResult { Success = false, Message = error ?? "Payment validation failed." };
            }

            // Generate atomic sequential receipt number
            var yearMonth = DateTime.UtcNow.ToString("yyyyMM");
            var counter = await _context.ReceiptCounters.FirstOrDefaultAsync(r => r.Prefix == "REC" && r.YearMonth == yearMonth);
            if (counter == null)
            {
                counter = new ReceiptCounter { Prefix = "REC", YearMonth = yearMonth, LastNumber = 1 };
                _context.ReceiptCounters.Add(counter);
            }
            else
            {
                counter.LastNumber++;
            }
            await _context.SaveChangesAsync();

            var receiptNo = AcademicCalculations.FormatReceiptNumber(yearMonth, counter.LastNumber);

            var feeTx = new FeeTransaction
            {
                EnrollmentId = enrollment.Id,
                ReceiptNo = receiptNo,
                ReceiptVoucherNo = receiptNo,
                AmountPaid = request.AmountPaid,
                DueAmount = remainingBalance,
                FineAmount = request.FineAmount,
                DiscountAmount = request.DiscountAmount,
                PaymentDate = DateTime.UtcNow.ToString("yyyy-MM-dd"),
                PaymentMethod = request.PaymentMethod,
                PaymentMode = request.PaymentMethod.ToString().ToLower(),
                ReferenceNo = request.ReferenceNo,
                BankName = request.BankName,
                ChequeDate = request.ChequeDate,
                ReceivedBy = string.IsNullOrWhiteSpace(request.ReceivedBy) ? "Cashier" : request.ReceivedBy,
                Remarks = request.Remarks
            };

            _context.FeeTransactions.Add(feeTx);

            // Update enrollment status strictly based on remaining balance
            if (remainingBalance == 0m)
            {
                // Completed only when exactly 0 remaining balance
                enrollment.Status = Core.Enums.EnrollmentStatus.Completed;
            }
            else
            {
                // Stays active if any balance remains
                enrollment.Status = Core.Enums.EnrollmentStatus.Active;
            }

            _context.AuditLogs.Add(new AuditLog
            {
                Action = "FeePayment",
                EntityName = "FeeTransaction",
                EntityId = receiptNo,
                Username = feeTx.ReceivedBy,
                NewValuesJson = System.Text.Json.JsonSerializer.Serialize(new
                {
                    feeTx.ReceiptNo,
                    feeTx.AmountPaid,
                    feeTx.DueAmount,
                    Student = enrollment.Student.FullName
                })
            });

            await _context.SaveChangesAsync();
            await transaction.CommitAsync();

            var printableReceipt = new PrintableReceiptDto
            {
                ReceiptNo = receiptNo,
                Date = DateTime.UtcNow.ToString("dd MMMM yyyy, hh:mm tt"),
                StudentRegNo = enrollment.Student.StudentRegNo,
                StudentName = enrollment.Student.FullName,
                FatherName = enrollment.Student.FatherName ?? "N/A",
                CourseName = enrollment.Batch?.Course?.CourseName ?? "Diploma / Academic Course",
                BatchCode = enrollment.Batch?.BatchCode ?? "N/A",
                RollNumber = enrollment.RollNumber,
                TotalAgreedFee = enrollment.TotalAgreedFee,
                PreviousPaid = previousPaid,
                CurrentPaid = request.AmountPaid,
                RemainingBalance = remainingBalance,
                PaymentMethod = request.PaymentMethod.ToString(),
                ReceivedBy = feeTx.ReceivedBy
            };

            return new ReceivePaymentResult
            {
                Success = true,
                Message = $"Payment of Rs. {request.AmountPaid:N0} received successfully.",
                TransactionId = feeTx.Id,
                ReceiptNo = receiptNo,
                AmountPaid = request.AmountPaid,
                RemainingDues = remainingBalance,
                PaymentDate = feeTx.PaymentDate,
                ReceiptData = printableReceipt
            };
        }
        catch (Exception ex)
        {
            await transaction.RollbackAsync();
            return new ReceivePaymentResult { Success = false, Message = $"Transaction error: {ex.Message}" };
        }
    }

    public async Task<bool> CancelPaymentAsync(int transactionId, string reason, string cancelledBy)
    {
        if (string.IsNullOrWhiteSpace(reason))
        {
            throw new ArgumentException("A mandatory justification reason is required to void a payment.");
        }

        using var transaction = await _context.Database.BeginTransactionAsync();
        try
        {
            var tx = await _context.FeeTransactions
                .Include(t => t.Enrollment)
                .FirstOrDefaultAsync(t => t.Id == transactionId);

            if (tx == null || tx.IsCancelled) return false;

            tx.IsCancelled = true;
            tx.CancellationReason = reason;
            tx.CancelledBy = cancelledBy;
            tx.CancelledAtUtc = DateTime.UtcNow;

            if (tx.Enrollment != null)
            {
                // Re-open active enrollment if it was marked completed
                tx.Enrollment.Status = Core.Enums.EnrollmentStatus.Active;
            }

            _context.AuditLogs.Add(new AuditLog
            {
                Action = "CancelFeeTransaction",
                EntityName = "FeeTransaction",
                EntityId = tx.ReceiptNo,
                Username = cancelledBy,
                OldValuesJson = System.Text.Json.JsonSerializer.Serialize(new { tx.ReceiptNo, tx.AmountPaid }),
                NewValuesJson = System.Text.Json.JsonSerializer.Serialize(new { Reason = reason, CancelledBy = cancelledBy })
            });

            await _context.SaveChangesAsync();
            await transaction.CommitAsync();
            return true;
        }
        catch
        {
            await transaction.RollbackAsync();
            return false;
        }
    }

    public async Task<List<FeeLedgerItemDto>> GetPaymentLedgerAsync(string? startDate = null, string? endDate = null, int? batchId = null)
    {
        var query = _context.FeeTransactions
            .AsNoTracking()
            .Where(t => !t.IsDeleted);

        if (batchId.HasValue && batchId.Value > 0)
        {
            query = query.Where(t => t.Enrollment != null && t.Enrollment.BatchId == batchId.Value);
        }

        if (!string.IsNullOrWhiteSpace(startDate))
        {
            query = query.Where(t => string.Compare(t.PaymentDate, startDate) >= 0);
        }

        if (!string.IsNullOrWhiteSpace(endDate))
        {
            query = query.Where(t => string.Compare(t.PaymentDate, endDate) <= 0);
        }

        return await query
            .OrderByDescending(t => t.Id)
            .Take(200)
            .Select(t => new FeeLedgerItemDto(
                t.Id,
                t.ReceiptNo,
                t.EnrollmentId,
                t.Enrollment != null && t.Enrollment.Student != null ? t.Enrollment.Student.StudentRegNo : "",
                t.Enrollment != null && t.Enrollment.Student != null ? t.Enrollment.Student.FullName : "",
                t.Enrollment != null && t.Enrollment.Batch != null && t.Enrollment.Batch.Course != null ? t.Enrollment.Batch.Course.CourseName : "",
                t.Enrollment != null && t.Enrollment.Batch != null ? t.Enrollment.Batch.BatchCode : "",
                t.AmountPaid,
                t.DueAmount,
                t.PaymentDate,
                t.PaymentMethod.ToString(),
                t.ReceivedBy,
                t.Remarks,
                t.IsCancelled
            ))
            .ToListAsync();
    }

    public async Task<List<OutstandingDuesItemDto>> GetOutstandingDuesListAsync(int? batchId = null)
    {
        var query = _context.Enrollments
            .AsNoTracking()
            .Where(e => !e.IsDeleted && e.Status == Core.Enums.EnrollmentStatus.Active);

        if (batchId.HasValue && batchId.Value > 0)
        {
            query = query.Where(e => e.BatchId == batchId.Value);
        }

        var list = await query
            .Select(e => new
            {
                e.Id,
                e.StudentId,
                StudentRegNo = e.Student != null ? e.Student.StudentRegNo : "",
                StudentName = e.Student != null ? e.Student.FullName : "",
                FatherName = e.Student != null ? e.Student.FatherName : null,
                CourseName = e.Batch != null && e.Batch.Course != null ? e.Batch.Course.CourseName : "",
                BatchCode = e.Batch != null ? e.Batch.BatchCode : "",
                e.TotalAgreedFee,
                e.DiscountAmount,
                TotalPaid = e.FeeTransactions.Where(f => !f.IsDeleted && !f.IsCancelled).Sum(f => f.AmountPaid),
                ContactNumber = e.Student != null ? e.Student.ContactNumber : null
            })
            .ToListAsync();

        return list
            .Select(x => new OutstandingDuesItemDto(
                x.Id,
                x.StudentId,
                x.StudentRegNo,
                x.StudentName,
                x.FatherName,
                x.CourseName,
                x.BatchCode,
                x.TotalAgreedFee,
                x.TotalPaid,
                Math.Max(0m, x.TotalAgreedFee - x.TotalPaid - x.DiscountAmount),
                x.ContactNumber
            ))
            .Where(x => x.BalanceDue > 0m)
            .OrderByDescending(x => x.BalanceDue)
            .Take(200)
            .ToList();
    }

    public async Task<string> GetNextReceiptNumberAsync()
    {
        var yearMonth = DateTime.UtcNow.ToString("yyyyMM");
        var prefix = $"REC-{yearMonth}-";

        var count = await _context.FeeTransactions
            .AsNoTracking()
            .CountAsync(t => t.ReceiptNo.StartsWith(prefix));

        return $"{prefix}{count + 1:D4}";
    }

    public async Task<Dictionary<string, decimal>> GetDailyCollectionSummaryAsync(string date)
    {
        var targetDate = string.IsNullOrWhiteSpace(date) ? DateTime.UtcNow.ToString("yyyy-MM-dd") : date;
        var transactions = await _context.FeeTransactions
            .Where(t => t.PaymentDate == targetDate && !t.IsCancelled && !t.IsDeleted)
            .ToListAsync();

        var summary = new Dictionary<string, decimal>
        {
            { "TotalCollected", transactions.Sum(t => t.AmountPaid) },
            { "Cash", transactions.Where(t => t.PaymentMethod == Core.Enums.PaymentMethod.Cash).Sum(t => t.AmountPaid) },
            { "Bank", transactions.Where(t => t.PaymentMethod == Core.Enums.PaymentMethod.Bank).Sum(t => t.AmountPaid) },
            { "Cheque", transactions.Where(t => t.PaymentMethod == Core.Enums.PaymentMethod.Cheque).Sum(t => t.AmountPaid) },
            { "Online", transactions.Where(t => t.PaymentMethod == Core.Enums.PaymentMethod.Online).Sum(t => t.AmountPaid) }
        };

        return summary;
    }
}

