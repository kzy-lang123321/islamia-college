using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using ICTManagementSystem.Core.Services;
using Xunit;

namespace ICTManagementSystem.Tests;

public class AcademicAndFeeTests
{
    [Theory]
    [InlineData(88.5, "A+", 4.0)]
    [InlineData(82.0, "A", 3.7)]
    [InlineData(76.0, "B+", 3.3)]
    [InlineData(71.0, "B", 3.0)]
    [InlineData(67.5, "C+", 2.7)]
    [InlineData(61.0, "C", 2.3)]
    [InlineData(54.0, "D", 2.0)]
    [InlineData(42.0, "F", 0.0)]
    public void CalculateGradeAndGpa_MatchesBoardStandards(decimal percentage, string expectedGrade, decimal expectedGpa)
    {
        var (grade, gpa) = AcademicCalculations.CalculateGradeAndGpa(percentage);
        Assert.Equal(expectedGrade, grade);
        Assert.Equal(expectedGpa, gpa);
    }

    [Fact]
    public void CalculateAttendancePercentage_ComputesAccurately()
    {
        // 21 present days out of 26 total days = 80.8%
        var pct = AcademicCalculations.CalculateAttendancePercentage(21, 26);
        Assert.Equal(80.8m, pct);

        // 18 present days out of 26 total days = 69.2% (Short attendance)
        var shortPct = AcademicCalculations.CalculateAttendancePercentage(18, 26);
        Assert.True(shortPct < 75m);
        Assert.Equal(69.2m, shortPct);
    }

    [Fact]
    public void FormatReceiptNumber_AppliesInstitutionalFourDigitPadding()
    {
        var receipt = AcademicCalculations.FormatReceiptNumber("202610", 7);
        Assert.Equal("REC-202610-0007", receipt);

        var receiptLarge = AcademicCalculations.FormatReceiptNumber("202610", 1234);
        Assert.Equal("REC-202610-1234", receiptLarge);
    }

    [Fact]
    public void FormatCertificateNumber_AppliesStandardFormatting()
    {
        var cert = AcademicCalculations.FormatCertificateNumber("2026", 42);
        Assert.Equal("CERT-2026-0042", cert);
    }

    [Fact]
    public void CalculatePaymentBalance_RejectsZeroOrNegativePayment()
    {
        var (isValid, error, _) = AcademicCalculations.CalculatePaymentBalance(15000m, 0m, 0m, 0m, 0m);
        Assert.False(isValid);
        Assert.Contains("greater than zero", error);
    }

    [Fact]
    public void CalculatePaymentBalance_RejectsOverpayment()
    {
        // Agreed 10,000, previously paid 4,000, remaining dues 6,000. Paying 7,000.
        var (isValid, error, _) = AcademicCalculations.CalculatePaymentBalance(10000m, 4000m, 7000m, 0m, 0m);
        Assert.False(isValid);
        Assert.Contains("exceeds maximum payable dues", error);
    }

    [Fact]
    public void CalculatePaymentBalance_PartialPayment_MaintainsActiveBalanceInvariant()
    {
        // Agreed 25,000, paying 10,000 -> remaining dues 15,000
        var (isValid, error, remaining) = AcademicCalculations.CalculatePaymentBalance(25000m, 0m, 10000m, 0m, 0m);
        Assert.True(isValid);
        Assert.Null(error);
        Assert.Equal(15000m, remaining);
        Assert.True(remaining > 0m, "Must never mark zero balance on partial payment.");
    }

    [Fact]
    public void CalculatePaymentBalance_FullPayment_ReachesExactZero()
    {
        // Agreed 18,000, previously paid 10,000, paying remaining 8,000 -> exactly 0
        var (isValid, error, remaining) = AcademicCalculations.CalculatePaymentBalance(18000m, 10000m, 8000m, 0m, 0m);
        Assert.True(isValid);
        Assert.Null(error);
        Assert.Equal(0m, remaining);
    }
}

