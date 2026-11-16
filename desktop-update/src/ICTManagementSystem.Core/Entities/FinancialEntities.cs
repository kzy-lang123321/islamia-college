using ICTManagementSystem.Core.Enums;

namespace ICTManagementSystem.Core.Entities;

public class FeeTransaction : BaseEntity
{
    public int EnrollmentId { get; set; }
    public Enrollment? Enrollment { get; set; }

    public int Semester { get; set; } = 1;
    public int InstallmentNo { get; set; } = 1;

    public string ReceiptNo { get; set; } = string.Empty; // Unique formatted: REC-YYYYMM-0001
    public string? ReceiptVoucherNo { get; set; }

    public decimal AmountPaid { get; set; }
    public decimal DueAmount { get; set; }
    public decimal FineAmount { get; set; }
    public decimal DiscountAmount { get; set; }

    public string PaymentDate { get; set; } = DateTime.UtcNow.ToString("yyyy-MM-dd");
    public PaymentMethod PaymentMethod { get; set; } = PaymentMethod.Cash;
    public string PaymentMode { get; set; } = "cash";
    public string? ReferenceNo { get; set; }
    public string? BankName { get; set; }
    public string? ChequeDate { get; set; }
    public string ReceivedBy { get; set; } = "Cashier";
    public string? Remarks { get; set; }

    public bool IsCancelled { get; set; } = false;
    public string? CancellationReason { get; set; }
    public string? CancelledBy { get; set; }
    public DateTime? CancelledAtUtc { get; set; }
}

public class CampusExpense : BaseEntity
{
    public ExpenseType ExpenseType { get; set; } = ExpenseType.Rent;
    public string MonthName { get; set; } = string.Empty;
    public int YearNum { get; set; } = DateTime.UtcNow.Year;
    public decimal AmountDue { get; set; }
    public decimal AmountPaid { get; set; }
    public string? PaymentDate { get; set; }
    public decimal BalanceDues { get; set; }
    public string PayeeName { get; set; } = string.Empty;
    public string? Notes { get; set; }
}

public class StaffMember : BaseEntity
{
    public string StaffCode { get; set; } = string.Empty;
    public string FullName { get; set; } = string.Empty;
    public string Designation { get; set; } = string.Empty;
    public string? Phone { get; set; }
    public decimal BaseSalary { get; set; }
    public bool IsActive { get; set; } = true;

    public ICollection<StaffPayroll> Payrolls { get; set; } = new List<StaffPayroll>();
}

public class StaffPayroll : BaseEntity
{
    public int StaffId { get; set; }
    public StaffMember? StaffMember { get; set; }

    public string MonthName { get; set; } = string.Empty;
    public int YearNum { get; set; } = DateTime.UtcNow.Year;
    public decimal SalaryAgreed { get; set; }
    public decimal InstallmentP1 { get; set; }
    public string? DateP1 { get; set; }
    public decimal InstallmentP2 { get; set; }
    public string? DateP2 { get; set; }
    public decimal InstallmentP3 { get; set; }
    public string? DateP3 { get; set; }
    public decimal TotalPaid { get; set; }
    public decimal Dues { get; set; }
}

public class CapitalAsset : BaseEntity
{
    public string AssetName { get; set; } = string.Empty;
    public string? Specification { get; set; }
    public int UnitsCount { get; set; } = 1;
    public decimal TotalCost { get; set; }
    public decimal TotalPaid { get; set; }
    public decimal Dues { get; set; }
    public string? PurchaseDate { get; set; }
    public string? VendorName { get; set; }
    public string Status { get; set; } = "Active";

    public ICollection<AssetPaymentInstallment> Installments { get; set; } = new List<AssetPaymentInstallment>();
}

public class AssetPaymentInstallment : BaseEntity
{
    public int AssetId { get; set; }
    public CapitalAsset? Asset { get; set; }
    public string PaymentDate { get; set; } = DateTime.UtcNow.ToString("yyyy-MM-dd");
    public decimal AmountPaid { get; set; }
    public string? Remarks { get; set; }
}

public class LoanAndAdvance : BaseEntity
{
    public string BorrowerName { get; set; } = string.Empty;
    public decimal AmountDisbursed { get; set; }
    public decimal AmountRepaid { get; set; }
    public decimal BalanceDue { get; set; }
    public string? Purpose { get; set; }
    public string? DisbursementDate { get; set; }
    public string Status { get; set; } = "Pending"; // Pending, Partially_Paid, Settled
}

public class ReceiptCounter
{
    public int Id { get; set; }
    public string Prefix { get; set; } = "REC";
    public string YearMonth { get; set; } = DateTime.UtcNow.ToString("yyyyMM");
    public int LastNumber { get; set; } = 0;
}

