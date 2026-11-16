using System.Text.RegularExpressions;

namespace ICTManagementSystem.Data.Services;

public static class PakistanIdentity
{
    public static string? Phone(string? value)
    {
        if (string.IsNullOrWhiteSpace(value)) return null;
        if (!Regex.IsMatch(value, @"^[+\d\s()-]+$")) throw new InvalidOperationException("Phone must contain digits and Pakistan country code only.");
        var digits = Regex.Replace(value, @"\D", "");
        if (digits.StartsWith("0092")) digits = digits[4..];
        else if (digits.StartsWith("92")) digits = digits[2..];
        else if (digits.StartsWith('0')) digits = digits[1..];
        if (digits.Length != 10 || digits[0] == '0')
            throw new InvalidOperationException("Pakistan phone number must contain 10 digits after +92 (e.g. +92-3001234567).");
        return "+92-" + digits;
    }

    public static string? Cnic(string? value)
    {
        if (string.IsNullOrWhiteSpace(value)) return null;
        if (!Regex.IsMatch(value, @"^[\d\s-]+$")) throw new InvalidOperationException("CNIC must contain 13 digits.");
        var digits = Regex.Replace(value, @"\D", "");
        if (digits.Length != 13) throw new InvalidOperationException("CNIC / B-Form must contain exactly 13 digits.");
        return $"{digits[..5]}-{digits[5..12]}-{digits[12]}";
    }
}
