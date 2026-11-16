from pathlib import Path
import re
root=Path(__file__).parent
def edit(rel,fn):
 p=root/rel; s=p.read_text(encoding='utf-8-sig'); p.write_text(fn(s),encoding='utf-8')
data='src/ICTManagementSystem.Data/Services/'
ui='src/ICTManagementSystem.UI/src/'
def student(s):
 s=s.replace('public class StudentService : IStudentService\n{','public class StudentService : IStudentService\n{\n    private static readonly SemaphoreSlim AdmissionLock = new(1, 1);')
 a=s.index('        var regNo =',s.index('public async Task<StudentDetailDto> CreateStudentAsync'))
 b=s.index('\n    public async Task<StudentDetailDto> UpdateStudentAsync',a)
 s=s[:a]+'''        await AdmissionLock.WaitAsync();
        try
        {
            await using var transaction = await _context.Database.BeginTransactionAsync();
            Batch? batch = null;
            if (request.BatchId is > 0)
            {
                batch = await _context.Batches.Include(b => b.Course)
                    .FirstOrDefaultAsync(b => b.Id == request.BatchId && !b.IsDeleted);
                if (batch == null || !string.Equals(batch.Status, "active", StringComparison.OrdinalIgnoreCase) || batch.Course == null || batch.Course.IsDeleted)
                    throw new InvalidOperationException("Select an active academic batch before admission.");
            }
            if (string.IsNullOrWhiteSpace(request.FullName))
                throw new InvalidOperationException("Student name is required.");
            // Preview numbers are advisory. Allocate the final number inside this transaction.
            var regNo = string.IsNullOrWhiteSpace(request.StudentRegNo)
                ? await GetNextRegistrationNoAsync(batch?.Course?.CourseCode ?? "DIT")
                : request.StudentRegNo.Trim().ToUpperInvariant();
            if (await _context.Students.IgnoreQueryFilters().AnyAsync(s => s.StudentRegNo.ToUpper() == regNo.ToUpper()))
                throw new InvalidOperationException($"Registration No '{regNo}' is already assigned. Import rows are never overwritten.");
            var student = new Student
            {
                StudentRegNo = regNo, FullName = request.FullName.Trim(),
                FatherName = request.FatherName?.Trim(),
                ContactNumber = PakistanIdentity.Phone(request.ContactNumber),
                SecondaryContact = PakistanIdentity.Phone(request.SecondaryContact),
                Cnic = PakistanIdentity.Cnic(request.Cnic), DateOfBirth = request.DateOfBirth,
                Email = request.Email?.Trim(), Address = request.Address?.Trim()
            };
            _context.Students.Add(student);
            await _context.SaveChangesAsync();
            if (batch != null)
            {
                _context.Enrollments.Add(new Enrollment
                {
                    StudentId = student.Id, BatchId = batch.Id,
                    RollNumber = request.RollNumber ?? regNo.Split('-').Last(),
                    TotalAgreedFee = request.AgreedFee ?? batch.Course!.StandardFee,
                    EnrollmentDate = DateTime.Today.ToString("yyyy-MM-dd"),
                    AcademicSession = $"{DateTime.Today.Year}-{DateTime.Today.Year + 1}",
                    Status = EnrollmentStatus.Active
                });
            }
            _context.AuditLogs.Add(new AuditLog
            {
                Action = "Create", EntityName = "Student", EntityId = student.Id.ToString(), Username = "Admin",
                NewValuesJson = System.Text.Json.JsonSerializer.Serialize(new { student.Id, student.StudentRegNo, student.FullName })
            });
            await _context.SaveChangesAsync();
            await transaction.CommitAsync();
            return (await GetStudentByIdAsync(student.Id))!;
        }
        finally { AdmissionLock.Release(); }
    }
''' + s[b:]
 s=s.replace('student.ContactNumber = request.ContactNumber?.Trim();','student.ContactNumber = PakistanIdentity.Phone(request.ContactNumber);').replace('student.SecondaryContact = request.SecondaryContact?.Trim();','student.SecondaryContact = PakistanIdentity.Phone(request.SecondaryContact);').replace('student.Cnic = request.Cnic?.Trim();','student.Cnic = PakistanIdentity.Cnic(request.Cnic);')
 a=s.index('        category = string.IsNullOrWhiteSpace(category)',s.index('GetNextRegistrationNoAsync(string'))
 s=s[:a]+'''        category = new string((category ?? "DIT").ToUpperInvariant().Where(char.IsLetterOrDigit).ToArray());
        if (category.Length == 0) category = "DIT";
        var prefix = $"ICT-{category}-";
        var numbers = await _context.Students.IgnoreQueryFilters().AsNoTracking()
            .Where(s => s.StudentRegNo.ToUpper().StartsWith(prefix))
            .Select(s => s.StudentRegNo).ToListAsync();
        long max = 0;
        foreach (var reg in numbers)
            if (long.TryParse(reg[prefix.Length..], out var number)) max = Math.Max(max, number);
        return $"{prefix}{checked(max + 1):D3}";
    }
}
'''
 return s
edit(data+'StudentService.cs',student)
def course(s):
 s=s.replace('var exists = await _context.Courses.AnyAsync(c => c.CourseCode.ToLower() == request.CourseCode.Trim().ToLower() && !c.IsDeleted);','''if (string.IsNullOrWhiteSpace(request.CourseName) || request.StandardFee < 0 || request.TtbExamFee < 0)
            throw new InvalidOperationException("Course title is required and fees cannot be negative.");
        var code = request.CourseCode?.Trim().ToUpperInvariant();
        if (string.IsNullOrWhiteSpace(code))
        {
            var title = request.CourseName.ToUpperInvariant();
            var stem = title.Contains("DIT") || title.Contains("DIPLOMA IN INFORMATION") ? "DIT"
                : title.Contains("ENGLISH") ? "ELL"
                : string.Concat(title.Split(' ', StringSplitOptions.RemoveEmptyEntries).Select(w => w[0]));
            stem = new string(stem.Where(char.IsLetterOrDigit).Take(12).ToArray());
            if (stem.Length == 0) stem = "COURSE";
            code = stem;
            var suffix = 1;
            while (await _context.Courses.IgnoreQueryFilters().AnyAsync(c => c.CourseCode == code)) code = $"{stem}{suffix++}";
        }
        var exists = await _context.Courses.IgnoreQueryFilters().AnyAsync(c => c.CourseCode.ToUpper() == code);''')
 s=s.replace('CourseCode = request.CourseCode.Trim().ToUpper(),','CourseCode = code,')
 s=s.replace('course.CourseCode = request.CourseCode.Trim().ToUpper();','''if (string.IsNullOrWhiteSpace(request.CourseName) || request.StandardFee < 0 || request.TtbExamFee < 0)
            throw new InvalidOperationException("Course title is required and fees cannot be negative.");
        // Keep the internal prefix stable when staff edit course fees or titles.
        course.CourseCode = course.CourseCode;''')
 s=s.replace('        course.IsDeleted = true;', '''        if (await _context.Batches.AnyAsync(b => b.CourseId == id && !b.IsDeleted))
            throw new InvalidOperationException("This course has academic batches. Deactivate its batches to stop admissions; existing records must be retained.");
        course.IsDeleted = true;''')
 s=s.replace('if (course == null) throw new InvalidOperationException("Referenced course not found.");','if (course == null) throw new InvalidOperationException("Referenced course not found.");')
 s=s.replace('        var exists = await _context.Enrollments.AnyAsync', '''        if (!string.Equals(batch.Status, "active", StringComparison.OrdinalIgnoreCase) || batch.Course == null || batch.Course.IsDeleted)
            throw new InvalidOperationException("Enrollment is only available for active academic batches.");
        var exists = await _context.Enrollments.AnyAsync''')
 # Course fee is authoritative for new enrollments; historical balances are untouched.
 s=s.replace('request.TotalAgreedFee > 0 ? request.TotalAgreedFee : (batch.Course?.StandardFee ?? 0m)', '(batch.Course?.StandardFee ?? 0m)')
 idx=s.index('    public async Task<List<SubjectDto>> GetSubjectsAsync')
 s=s[:idx]+'''    public async Task<bool> SetBatchActiveAsync(int id, bool active)
    {
        var batch = await _context.Batches.Include(b => b.Course).FirstOrDefaultAsync(b => b.Id == id && !b.IsDeleted);
        if (batch == null || batch.Course == null || batch.Course.IsDeleted) throw new InvalidOperationException("Batch or course not found.");
        batch.Status = active ? "active" : "inactive";
        await _context.SaveChangesAsync();
        return true;
    }

'''+s[idx:]
 return s
edit(data+'CourseService.cs',course)
edit('src/ICTManagementSystem.Core/Interfaces/IAppServices.cs', lambda s:s.replace('public interface ICourseService\n{','public interface ICourseService\n{\n    Task<bool> SetBatchActiveAsync(int id, bool active);'))
edit('src/ICTManagementSystem.Core/DTOs/AppDtos.cs',lambda s:s.replace('    string? RollNumber\n);','    string? RollNumber\n);',1))
edit('src/ICTManagementSystem.App/Bridge/JsBridge.cs', lambda s:s.replace('                "courses.getBatches" =>', '''                "courses.setBatchActive" => await sp.GetRequiredService<ICourseService>().SetBatchActiveAsync(
                    ExtractOptionalInt(payloadJson, "id") ?? 0, ExtractOptionalBool(payloadJson, "active") ?? false),
                "academic.importStudents" => await new ICTManagementSystem.Data.Services.AcademicImportService(sp.GetRequiredService<ICTManagementSystem.Data.AppDbContext>()).ImportStudentsAsync(
                    JsonSerializer.Deserialize<ICTManagementSystem.Data.Services.StudentImportRequest>(payloadJson, JsonOptions)!),
                "academic.importMarks" => await new ICTManagementSystem.Data.Services.AcademicImportService(sp.GetRequiredService<ICTManagementSystem.Data.AppDbContext>()).ImportMarksAsync(
                    JsonSerializer.Deserialize<ICTManagementSystem.Data.Services.MarksImportRequest>(payloadJson, JsonOptions)!),
                "academic.getExams" => await new ICTManagementSystem.Data.Services.AcademicImportService(sp.GetRequiredService<ICTManagementSystem.Data.AppDbContext>()).GetExamsAsync(),
                "courses.getBatches" =>'''))

edit(ui+'services/api.ts',lambda s:s.replace('  getBatches: (courseId?', '''  setBatchActive: (id: number, active: boolean) => callBridge<boolean>('courses.setBatchActive', { id, active }),
  importAcademic: (mode: 'students' | 'marks', data: any) => callBridge<{ imported: number; skipped: number; errors: string[] }>(mode === 'students' ? 'academic.importStudents' : 'academic.importMarks', data),
  getAcademicExams: () => callBridge<{ id: number; name: string; academicSession: string; classesIncluded?: string }[]>('academic.getExams', {}),
  getBatches: (courseId?'''))

def students_ui(s):
 s=s.replace("import collegeLogo", "import { ExcelImport } from '../../components/ExcelImport';\nimport { formatPhone, formatCnic, validPhone, validCnic } from '../../services/pakistan';\nimport collegeLogo")
 s=s.replace('  const [loading, setLoading]', '  const [savingStudent, setSavingStudent] = useState(false);\n  const [loading, setLoading]')
 s=s.replace('setBatches(b);', 'setBatches(b);')
 # Admission metadata still retains inactive batches for historical filtering.
 s=s.replace('const initial = target ?? b[0];','const initial = target ?? b.find(batch => batch.status.toLowerCase() === \'active\');')
 s=s.replace("api.getNextRegistrationNo('DIT')", "api.getNextRegistrationNo(availableCourses.find(c => c.id === batch?.courseId)?.courseCode || 'DIT')",1)
 s=s.replace('const batch = target ?? batches.find(b => b.id === selectedBatchId) ?? batches[0];', "const batch = target ?? batches.find(b => b.id === selectedBatchId && b.status.toLowerCase() === 'active') ?? batches.find(b => b.status.toLowerCase() === 'active');\n    if (!batch) { addToast('error', 'Create or activate an academic batch first.'); return; }")
 s=s.replace("    if (!formData.studentRegNo.trim()) errors.studentRegNo = 'Registration number is required.';", "    if (!formData.batchId) errors.studentRegNo = 'Select an active batch.';")
 s=s.replace("if (!formData.contactNumber.trim()) errors.contactNumber = 'Contact phone number is required.';", "if (!validPhone(formData.contactNumber)) errors.contactNumber = 'Enter +92 followed by 10 digits.';\n    if (formData.cnic && !validCnic(formData.cnic)) errors.cnic = 'CNIC must have 13 digits (12345-1234567-1).';")
 s=s.replace('    if (!validateForm()) return;', '    if (savingStudent || !validateForm()) return;')
 s=s.replace('      await api.createStudent({\n        studentRegNo: formData.studentRegNo.trim(),','      setSavingStudent(true);\n      const saved = await api.createStudent({\n        studentRegNo: \'\',')
 s=s.replace('        agreedFee: formData.agreedFee,','        agreedFee: undefined,')
 s=s.replace('        rollNumber: formData.rollNumber.trim() || undefined','        rollNumber: undefined')
 s=s.replace('(${formData.studentRegNo})!', '(${saved.studentRegNo})!')
 s=s.replace("api.getNextRegistrationNo('DIT')", "api.getNextRegistrationNo(courses.find(c => c.id === batches.find(b => b.id === formData.batchId)?.courseId)?.courseCode || 'DIT')")
 s=s.replace("addToast('error', err.message || 'Admission registration failed');\n    }", "addToast('error', err.message || 'Admission registration failed');\n    } finally { setSavingStudent(false); }")
 s=s.replace('                    onChange={(e) => setFormData({ ...formData, studentRegNo: e.target.value.toUpperCase() })}', '                    readOnly aria-label="Automatic registration number preview"')
 s=s.replace('Registration No *','Registration No (automatic preview)')
 s=s.replace('onChange={(e) => setFormData({ ...formData, contactNumber: e.target.value })}', 'maxLength={14} inputMode="tel" placeholder="+92-3001234567" onChange={(e) => setFormData({ ...formData, contactNumber: formatPhone(e.target.value) })}')
 # Remove the original placeholder to avoid duplicate JSX attributes.
 s=s.replace('placeholder="+92-3001234567" ', '')
 s=s.replace('onChange={(e) => setFormData({ ...formData, cnic: e.target.value })}', 'maxLength={15} inputMode="numeric" onChange={(e) => setFormData({ ...formData, cnic: formatCnic(e.target.value) })}')
 s=s.replace('                  {/* Inline Quick Batch Creator */}', '                  {formErrors.cnic && <p className="text-red-600">{formErrors.cnic}</p>}\n                  {/* Inline Quick Batch Creator */}')
 # Auto prefix preview when changing or creating a batch.
 s=s.replace('      setIsInlineBatchOpen(false);', "      const prefix = courses.find(c => c.id === newBatchData.courseId)?.courseCode || 'DIT';\n      const preview = await api.getNextRegistrationNo(prefix);\n      setFormData(f => ({ ...f, studentRegNo: preview, rollNumber: preview.split('-').pop() || '' }));\n      setIsInlineBatchOpen(false);")
 s=s.replace('                      const bid = parseInt(e.target.value, 10);','                      const bid = parseInt(e.target.value, 10);',1)
 s=s.replace('                        agreedFee: cFee\n                      });', '''                        agreedFee: cFee
                      });
                      api.getNextRegistrationNo(courses.find(c => c.id === selected?.courseId)?.courseCode || 'DIT').then(preview => {
                        setFormData(f => f.batchId === bid ? { ...f, studentRegNo: preview, rollNumber: preview.split('-').pop() || '' } : f);
                      }).catch(err => addToast('error', err.message));''')
 # Only admission batch select excludes inactive batches.
 a=s.index('                    value={formData.batchId}')
 s=s[:a]+s[a:].replace('{batches.map((b) => (', "{batches.filter(b => b.status.toLowerCase() === 'active').map((b) => (",1)
 # Replace manual roll and tuition inputs with assigned course information.
 a=s.index('              <div className="grid grid-cols-2 gap-3">\n                <div>\n                  <label',s.index('{/* Inline Quick Batch Creator */}'))
 b=s.index('\n              <div>\n                <label',a)
 s=s[:a]+'''              <div className="p-3 rounded-lg bg-sky-50 text-sky-900 text-xs">
                Registration and roll number are assigned automatically when saved.
                Course tuition: Rs. {formData.agreedFee.toLocaleString()} (assigned in Courses).
              </div>
'''+s[b:]
 s=s.replace('onClick={() => handleCreate(true)}','disabled={savingStudent} onClick={() => handleCreate(true)}').replace('onClick={() => setIsModalOpen(false)}','disabled={savingStudent} onClick={() => setIsModalOpen(false)}')
 s=s.replace('                  type="submit"', '                  disabled={savingStudent} type="submit"')
 # UID is the existing permanent database primary key; no migration or renumbering.
 s=s.replace('{s.studentRegNo}', "{s.studentRegNo}<span className=\"block text-[10px] text-slate-400\">UID: {s.id}</span>") if '{s.studentRegNo}' in s else s
 idx=s.index('      {/* 3. Selected Batch')
 s=s[:idx]+'''      <ExcelImport mode="students" batchId={selectedBatchId || 0} onComplete={() => { loadStudents(); loadBatchesAndCourses(); }} />
'''+s[idx:]
 return s
edit(ui+'pages/students/StudentsList.tsx',students_ui)

def courses_ui(s):
 s=s.replace("    if (!formData.courseCode.trim()) errors.courseCode = 'Course Code is required (e.g. DIT).';",'')
 s=s.replace('onClick={() => handleArchive(c.id, c.courseName)}','onClick={() => handleArchive(c.id, c.courseName)}').replace('title="Archive Course"','title="Delete Course"').replace('<Archive className=', '<Archive aria-label="Delete course" className=')
 s=s.replace("Are you sure you want to archive course '${name}'?", "Delete course '${name}'? Courses with academic batches cannot be deleted.")
 s=s.replace('archived successfully.', 'deleted successfully.')
 # Hide internal course codes and generate them in the service.
 s=re.sub(r'\s*<th className="py-3 px-4">Code</th>','',s)
 s=re.sub(r'\s*<td className="py-3 px-4 font-mono font-bold text-ict-orange">\{c.courseCode\}</td>','',s)
 a=s.index('                <div>\n                  <label',s.index('{/* Add Course Modal */}') if '{/* Add Course Modal */}' in s else s.index('{isAddModalOpen &&'))
 b=s.index('                <div>\n                  <label',a+20)
 assert 'Course Code' in s[a:b]
 s=s[:a]+s[b:]
 a=s.index('                  <select\n                    value={formData.category}')
 b=s.index('</select>',a)+len('</select>')
 s=s[:a]+'''                  <input type="text" required maxLength={100} value={formData.category}
                    onChange={e => setFormData({ ...formData, category: e.target.value })}
                    placeholder="Type a category" className="w-full border rounded-lg px-3 py-2 bg-transparent" />'''+s[b:]
 s=s.replace("if (formData.standardFee <= 0)","if (formData.standardFee < 0)").replace('Standard fee must be greater than zero.','Fee cannot be negative.')
 return s
edit(ui+'pages/courses/CoursesList.tsx',courses_ui)

def detail(s):
 idx=s.index('      {/* Tabs */}')
 s=s[:idx]+'''      <form className="no-print p-4 rounded-xl bg-sky-50 border border-sky-200 flex flex-wrap gap-3 items-end text-xs" onSubmit={async e => {
        e.preventDefault(); const form = new FormData(e.currentTarget);
        try { await api.updateCourse({ ...course, standardFee: Number(form.get('fee')), ttbExamFee: Number(form.get('examFee')) });
          addToast('success', 'Course fees saved. Existing agreed fees are preserved.'); await loadCourse(course.id);
        } catch (err: any) { addToast('error', err.message); }
      }}>
        <label>Course tuition (Rs.)<input key={`fee-${course.standardFee}`} name="fee" type="number" required min="0" step="0.01" defaultValue={course.standardFee} className="block border rounded p-2" /></label>
        <label>Exam fee (Rs.)<input key={`exam-${course.ttbExamFee}`} name="examFee" type="number" required min="0" step="0.01" defaultValue={course.ttbExamFee} className="block border rounded p-2" /></label>
        <button className="bg-sky-600 text-white px-4 py-2 rounded-lg">Save course fees</button>
      </form>
      {course.courseCode.startsWith('DIT') && <p className="text-xs text-sky-700">DIT academic structure: Semester 1 · Semester 2. Select the semester in Exams &amp; Results.</p>}
'''+s[idx:]
 s=s.replace('                    {b.status}\n                  </span>', '''                    {b.status}
                  </span>
                  <button className="no-print text-xs text-sky-700 underline" onClick={async () => {
                    try { await api.setBatchActive(b.id, b.status.toLowerCase() !== 'active'); await loadCourse(course.id); }
                    catch (err: any) { addToast('error', err.message); }
                  }}>{b.status.toLowerCase() === 'active' ? 'Deactivate' : 'Activate'}</button>''')
 return s
edit(ui+'pages/courses/CourseDetail.tsx',detail)
def enrollments(s):
 s=s.replace('Allocate a registered student to a specific batch and set the agreed tuition fee.', 'Allocate a registered student to an active batch. Tuition comes from the course fee.')
 s=s.replace('                  <th className="py-3 px-4">Enrollment Date</th>','').replace('                    <td className="py-3 px-4 text-slate-500">{e.enrollmentDate || \'N/A\'}</td>','')
 a=s.index('                <div>\n                  <label', s.index('{/* Roll Number & Date */}'))
 b=s.index('              {/* Financial Fields */}',a)
 s=s[:a]+'''                <div className="text-xs text-slate-500">UID: {form.selectedStudentId || 'Select a student'} · Enrollment date is assigned automatically.</div>
              </div>

'''+s[b:]
 a=s.index('              {/* Financial Fields */}')
 b=s.index('              <div className="flex items-center justify-end',a)
 s=s[:a]+'''              <p className="text-xs bg-sky-50 p-3 rounded-lg">Course tuition: Rs. {(courses.find(c => c.id === batches.find(b => b.id === form.batchId)?.courseId)?.standardFee ?? 0).toLocaleString()}. Set fees in Courses.</p>

'''+s[b:]
 a=s.index('                  value={form.batchId}')
 s=s[:a]+s[a:].replace('{batches.map((b) => (', "{batches.filter(b => b.status.toLowerCase() === 'active').map((b) => (",1)
 s=s.replace("admissionFee: 2000", "admissionFee: 0")
 return s
edit(ui+'pages/courses/EnrollmentsList.tsx',enrollments)
edit('installer/installer.iss',lambda s:s.replace('"1.0.0"','"1.1.0"').replace('Setup_ICTManagementSystem_v1.0.0','Setup_ICTManagementSystem_v1.1.0'))
