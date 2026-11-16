using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Interfaces;
using ICTManagementSystem.Core.Services;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public class ResultService : IResultService
{
    private readonly AppDbContext _context;

    public ResultService(AppDbContext context)
    {
        _context = context;
    }

    public async Task<bool> SaveMarksAsync(EnterMarksDto dto)
    {
        if (dto.TotalMarks <= 0 || dto.ObtainedMarks < 0 || dto.ObtainedMarks > dto.TotalMarks || string.IsNullOrWhiteSpace(dto.SubjectName))
            throw new InvalidOperationException("Subject, positive total marks and valid obtained marks are required.");
        var (grade, gpa) = AcademicCalculations.CalculateGradeAndGpa((dto.ObtainedMarks / dto.TotalMarks) * 100m);

        var existing = await _context.ExamResults
            .FirstOrDefaultAsync(r => r.ExaminationId == dto.ExaminationId && r.StudentId == dto.StudentId && r.SubjectName.ToLower() == dto.SubjectName.Trim().ToLower());

        if (existing != null)
        {
            existing.TotalMarks = dto.TotalMarks;
            existing.ObtainedMarks = dto.ObtainedMarks;
            existing.Grade = grade;
            existing.Gpa = gpa;
            existing.Remarks = dto.Remarks;
        }
        else
        {
            _context.ExamResults.Add(new ExamResult
            {
                ExaminationId = dto.ExaminationId,
                StudentId = dto.StudentId,
                SubjectName = dto.SubjectName.Trim(),
                TotalMarks = dto.TotalMarks,
                ObtainedMarks = dto.ObtainedMarks,
                Grade = grade,
                Gpa = gpa,
                Remarks = dto.Remarks
            });
        }

        await _context.SaveChangesAsync();
        return true;
    }

    public async Task<ResultCardDto?> GetStudentResultCardAsync(int examinationId, int studentId)
    {
        var exam = await _context.Examinations.FindAsync(examinationId);
        var student = await _context.Students
            .Include(s => s.Enrollments)
                .ThenInclude(e => e.Batch)
                    .ThenInclude(b => b!.Course)
            .FirstOrDefaultAsync(s => s.Id == studentId);

        if (exam == null || student == null) return null;

        var results = await _context.ExamResults
            .Where(r => r.ExaminationId == examinationId && r.StudentId == studentId)
            .ToListAsync();

        if (!results.Any()) return null;

        var grandTotal = results.Sum(r => r.TotalMarks);
        var grandObtained = results.Sum(r => r.ObtainedMarks);
        var pct = grandTotal > 0 ? (grandObtained / grandTotal) * 100m : 0m;
        var (overallGrade, overallGpa) = AcademicCalculations.CalculateGradeAndGpa(pct);

        var enrollment = student.Enrollments.FirstOrDefault();

        return new ResultCardDto
        {
            StudentRegNo = student.StudentRegNo,
            StudentName = student.FullName,
            FatherName = student.FatherName ?? "N/A",
            ExamName = exam.Name,
            AcademicSession = exam.AcademicSession,
            CourseName = enrollment?.Batch?.Course?.CourseName ?? "Diploma / Academic Program",
            Subjects = results.Select(r => new SubjectResultDto
            {
                SubjectName = r.SubjectName,
                TotalMarks = r.TotalMarks,
                ObtainedMarks = r.ObtainedMarks,
                Grade = r.Grade,
                Gpa = r.Gpa
            }).ToList(),
            GrandTotalMarks = grandTotal,
            GrandObtainedMarks = grandObtained,
            Percentage = Math.Round(pct, 2),
            OverallGrade = overallGrade,
            OverallGpa = overallGpa
        };
    }

    public async Task<List<ResultCardDto>> GetClassResultSheetAsync(int examinationId, int batchId)
    {
        var enrollments = await _context.Enrollments
            .Include(e => e.Student)
            .Where(e => e.BatchId == batchId)
            .ToListAsync();

        var cards = new List<ResultCardDto>();

        foreach (var en in enrollments)
        {
            var card = await GetStudentResultCardAsync(examinationId, en.StudentId);
            if (card != null)
            {
                cards.Add(card);
            }
        }

        // Calculate positions/ranks
        var ranked = cards.OrderByDescending(c => c.GrandObtainedMarks).ToList();
        for (int i = 0; i < ranked.Count; i++)
        {
            ranked[i].ClassRank = i + 1;
        }

        return ranked;
    }
}

