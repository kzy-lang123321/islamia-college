using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Interfaces;
using ICTManagementSystem.Core.Services;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public class CertificateService : ICertificateService
{
    private readonly AppDbContext _context;

    public CertificateService(AppDbContext context)
    {
        _context = context;
    }

    public async Task<CertificateDto> GenerateCertificateAsync(GenerateCertificateDto dto)
    {
        var student = await _context.Students.FindAsync(dto.StudentId);
        if (student == null) throw new KeyNotFoundException("Student not found.");

        using var transaction = await _context.Database.BeginTransactionAsync();
        try
        {
            var year = DateTime.UtcNow.Year.ToString();
            var counter = await _context.CertificateCounters.FirstOrDefaultAsync(c => c.Year == year);
            if (counter == null)
            {
                counter = new CertificateCounter { Year = year, LastNumber = 1 };
                _context.CertificateCounters.Add(counter);
            }
            else
            {
                counter.LastNumber++;
            }
            await _context.SaveChangesAsync();

            var certNo = AcademicCalculations.FormatCertificateNumber(year, counter.LastNumber);
            var qrData = $"ICT-VERIFY:{certNo}:{student.StudentRegNo}:{student.FullName}:{dto.CertificateType}";

            var certificate = new Certificate
            {
                CertificateNo = certNo,
                StudentId = student.Id,
                CertificateType = dto.CertificateType,
                IssueDate = DateTime.UtcNow.ToString("yyyy-MM-dd"),
                CourseName = dto.CourseName,
                GradeOrRemarks = dto.GradeOrRemarks,
                QrCodeData = qrData,
                ReprintCount = 0,
                IssuedBy = dto.IssuedBy
            };

            _context.Certificates.Add(certificate);

            _context.AuditLogs.Add(new AuditLog
            {
                Action = "GenerateCertificate",
                EntityName = "Certificate",
                EntityId = certNo,
                Username = dto.IssuedBy,
                NewValuesJson = System.Text.Json.JsonSerializer.Serialize(new { certNo, student.StudentRegNo, dto.CertificateType })
            });

            await _context.SaveChangesAsync();
            await transaction.CommitAsync();

            return new CertificateDto(
                certificate.Id,
                certificate.CertificateNo,
                student.Id,
                student.StudentRegNo,
                student.FullName,
                student.FatherName ?? "",
                certificate.CertificateType.ToString(),
                certificate.IssueDate,
                certificate.CourseName,
                certificate.GradeOrRemarks,
                certificate.QrCodeData,
                certificate.ReprintCount,
                certificate.IssuedBy
            );
        }
        catch
        {
            await transaction.RollbackAsync();
            throw;
        }
    }

    public async Task<CertificateDto?> GetCertificateByIdAsync(int id)
    {
        return await _context.Certificates
            .AsNoTracking()
            .Where(c => c.Id == id && !c.IsDeleted)
            .Select(c => new CertificateDto(
                c.Id,
                c.CertificateNo,
                c.StudentId,
                c.Student != null ? c.Student.StudentRegNo : "",
                c.Student != null ? c.Student.FullName : "",
                c.Student != null ? (c.Student.FatherName ?? "") : "",
                c.CertificateType.ToString(),
                c.IssueDate,
                c.CourseName,
                c.GradeOrRemarks,
                c.QrCodeData,
                c.ReprintCount,
                c.IssuedBy
            ))
            .FirstOrDefaultAsync();
    }

    public async Task<CertificateDto?> VerifyCertificateByQrAsync(string qrCodeData)
    {
        return await _context.Certificates
            .AsNoTracking()
            .Where(c => c.QrCodeData == qrCodeData && !c.IsDeleted)
            .Select(c => new CertificateDto(
                c.Id,
                c.CertificateNo,
                c.StudentId,
                c.Student != null ? c.Student.StudentRegNo : "",
                c.Student != null ? c.Student.FullName : "",
                c.Student != null ? (c.Student.FatherName ?? "") : "",
                c.CertificateType.ToString(),
                c.IssueDate,
                c.CourseName,
                c.GradeOrRemarks,
                c.QrCodeData,
                c.ReprintCount,
                c.IssuedBy
            ))
            .FirstOrDefaultAsync();
    }

    public async Task<bool> IncrementReprintCountAsync(int certificateId)
    {
        var cert = await _context.Certificates.FindAsync(certificateId);
        if (cert == null) return false;

        cert.ReprintCount++;
        await _context.SaveChangesAsync();
        return true;
    }
}
