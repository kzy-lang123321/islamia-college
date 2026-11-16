namespace ICTManagementSystem.Core.Services;

public static class AcademicCalculations
{
    /// <summary>
    /// Calculates Grade and GPA from percentage marks (0-100)
    /// Standard Higher Education Commission (HEC) / Board grading scale
    /// </summary>
    public static (string Grade, decimal Gpa) CalculateGradeAndGpa(decimal percentage)
    {
        if (percentage >= 85m) return ("A+", 4.0m);
        if (percentage >= 80m) return ("A", 3.7m);
        if (percentage >= 75m) return ("B+", 3.3m);
        if (percentage >= 70m) return ("B", 3.0m);
        if (percentage >= 65m) return ("C+", 2.7m);
        if (percentage >= 60m) return ("C", 2.3m);
        if (percentage >= 50m) return ("D", 2.0m);
        return ("F", 0.0m);
    }

    /// <summary>
    /// Calculates Attendance percentage
    /// </summary>
    public static decimal CalculateAttendancePercentage(int presentDays, int totalWorkingDays)
    {
        if (totalWorkingDays <= 0) return 0m;
        var pct = ((decimal)presentDays / totalWorkingDays) * 100m;
        return Math.Round(pct, 1);
    }

    /// <summary>
    /// Formats a sequential receipt number according to institutional rule: REC-YYYYMM-XXXX
    /// </summary>
    public static string FormatReceiptNumber(string yearMonth, int sequenceNumber)
    {
        return $"REC-{yearMonth}-{sequenceNumber:D4}";
    }

    /// <summary>
    /// Formats a sequential certificate number: CERT-YYYY-XXXX
    /// </summary>
    public static string FormatCertificateNumber(string year, int sequenceNumber)
    {
        return $"CERT-{year}-{sequenceNumber:D4}";
    }

    /// <summary>
    /// Validates payment amount against remaining balance.
    /// Strict institutional rule: Never marks as fully paid if remaining dues > 0.
    /// </summary>
    public static (bool IsValid, string? Error, decimal RemainingBalance) CalculatePaymentBalance(
        decimal totalAgreedFee,
        decimal previousPaid,
        decimal amountPaying,
        decimal fineAmount,
        decimal discountAmount)
    {
        if (amountPaying <= 0m)
            return (false, "Payment amount must be greater than zero.", 0m);

        var netPayable = Math.Max(0m, (totalAgreedFee - previousPaid) + fineAmount - discountAmount);

        if (amountPaying > netPayable)
        {
            return (false, $"Payment amount (Rs. {amountPaying:N0}) exceeds maximum payable dues (Rs. {netPayable:N0}).", netPayable);
        }

        var newBalance = netPayable - amountPaying;
        return (true, null, newBalance);
    }
}

