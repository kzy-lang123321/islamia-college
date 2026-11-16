using Microsoft.EntityFrameworkCore.Storage.ValueConversion;

namespace ICTManagementSystem.Data.Converters;

/// <summary>
/// EF Core ValueConverter that persists monetary amounts as integer minor units (paisa)
/// in SQLite, preventing any floating-point representation or rounding inaccuracies.
/// 1 Rupee = 100 Paisa.
/// </summary>
public class MoneyValueConverter : ValueConverter<decimal, long>
{
    public MoneyValueConverter() : base(
        v => (long)Math.Round(v * 100m, MidpointRounding.AwayFromZero),
        v => (decimal)v / 100m)
    {
    }
}

