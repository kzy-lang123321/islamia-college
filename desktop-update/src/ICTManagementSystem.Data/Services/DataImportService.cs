using System.Globalization;
using System.Text.RegularExpressions;
using ICTManagementSystem.Core.DTOs;
using ICTManagementSystem.Core.Entities;
using ICTManagementSystem.Core.Enums;
using ICTManagementSystem.Core.Interfaces;
using Microsoft.EntityFrameworkCore;

namespace ICTManagementSystem.Data.Services;

public class DataImportService : IDataImportService
{
    private readonly AppDbContext _context;

    public DataImportService(AppDbContext context)
    {
        _context = context;
    }

    public async Task<DataImportPreviewDto> PreviewSqlDumpAsync(string sqlFilePath)
    {
        if (!File.Exists(sqlFilePath))
        {
            throw new FileNotFoundException($"SQL dump file not found at '{sqlFilePath}'");
        }

        var fullSql = await File.ReadAllTextAsync(sqlFilePath);
        var statements = SplitSqlStatements(fullSql);
        var preview = new DataImportPreviewDto
        {
            SourceFileName = Path.GetFileName(sqlFilePath)
        };

        var existingRegNos = (await _context.Students.Select(s => s.StudentRegNo.ToLower()).ToListAsync()).ToHashSet();
        var existingReceiptNos = (await _context.FeeTransactions.Select(f => f.ReceiptNo.ToLower()).ToListAsync()).ToHashSet();

        int duplicateStudents = 0;
        int duplicateReceipts = 0;

        foreach (var stmt in statements)
        {
            if (!stmt.StartsWith("INSERT INTO", StringComparison.OrdinalIgnoreCase)) continue;
            var (tableName, cols) = ParseInsertHeader(stmt);

            if (tableName == "departments")
                preview.TotalDepartments += CountRowTuples(stmt);
            else if (tableName == "courses")
                preview.TotalCourses += CountRowTuples(stmt);
            else if (tableName == "batches")
                preview.TotalBatches += CountRowTuples(stmt);
            else if (tableName == "students")
            {
                var tuples = ExtractTuples(stmt);
                preview.TotalStudents += tuples.Count;
                foreach (var t in tuples)
                {
                    var fields = ParseCsvLine(t);
                    var regNo = GetField(fields, cols, "student_reg_no", 1)?.ToLower();
                    if (regNo != null && existingRegNos.Contains(regNo)) duplicateStudents++;
                }
            }
            else if (tableName == "enrollments")
                preview.TotalEnrollments += CountRowTuples(stmt);
            else if (tableName == "fee_transactions")
            {
                var tuples = ExtractTuples(stmt);
                preview.TotalFeeTransactions += tuples.Count;
                foreach (var t in tuples)
                {
                    var fields = ParseCsvLine(t);
                    var receiptNo = (GetField(fields, cols, "receipt_no", 4) ?? GetField(fields, cols, "receipt_voucher_no", 10))?.ToLower();
                    if (receiptNo != null && existingReceiptNos.Contains(receiptNo)) duplicateReceipts++;
                }
            }
            else if (tableName == "users")
                preview.TotalUsers += CountRowTuples(stmt);
        }

        if (duplicateStudents > 0)
        {
            preview.ValidationWarnings.Add($"{duplicateStudents} student(s) already exist in local database and will be merged/skipped.");
        }
        if (duplicateReceipts > 0)
        {
            preview.ValidationWarnings.Add($"{duplicateReceipts} receipt(s) already exist and will be preserved.");
        }

        return preview;
    }

    public async Task<DataImportResultDto> ExecuteImportAsync(string sqlFilePath)
    {
        if (!File.Exists(sqlFilePath))
        {
            return new DataImportResultDto { Success = false, Message = "SQL dump file not found." };
        }

        var fullSql = await File.ReadAllTextAsync(sqlFilePath);
        var statements = SplitSqlStatements(fullSql);
        int importedCount = 0;

        // Maps legacy MySQL auto-increment IDs to new SQLite entity IDs
        var deptIdMap = new Dictionary<int, int>();
        var courseIdMap = new Dictionary<int, int>();
        var batchIdMap = new Dictionary<int, int>();
        var studentIdMap = new Dictionary<int, int>();
        var enrollmentIdMap = new Dictionary<int, int>();

        int deptSeq = 0;
        int courseSeq = 0;
        int batchSeq = 0;
        int studentSeq = 0;
        int enrollmentSeq = 0;
        int txSequence = 0;

        // Separate and order statements topologically to satisfy all FK dependencies:
        var deptStatements = new List<string>();
        var courseStatements = new List<string>();
        var batchStatements = new List<string>();
        var staffStatements = new List<string>();
        var userStatements = new List<string>();
        var studentStatements = new List<string>();
        var enrollmentStatements = new List<string>();
        var feeStatements = new List<string>();
        var expenseStatements = new List<string>();
        var academicPosStatements = new List<string>();

        foreach (var stmt in statements)
        {
            if (!stmt.StartsWith("INSERT INTO", StringComparison.OrdinalIgnoreCase)) continue;
            var (tableName, _) = ParseInsertHeader(stmt);

            if (tableName == "departments") deptStatements.Add(stmt);
            else if (tableName == "courses") courseStatements.Add(stmt);
            else if (tableName == "batches") batchStatements.Add(stmt);
            else if (tableName == "staff_members") staffStatements.Add(stmt);
            else if (tableName == "users") userStatements.Add(stmt);
            else if (tableName == "students") studentStatements.Add(stmt);
            else if (tableName == "enrollments") enrollmentStatements.Add(stmt);
            else if (tableName == "fee_transactions") feeStatements.Add(stmt);
            else if (tableName == "campus_expenses") expenseStatements.Add(stmt);
            else if (tableName == "academic_positions") academicPosStatements.Add(stmt);
        }

        using var transaction = await _context.Database.BeginTransactionAsync();
        try
        {
            // 1. Departments
            var existingDepts = await _context.Departments.ToDictionaryAsync(d => d.DeptCode.ToLower());
            var newDepts = new List<(int oldId, Department entity)>();
            foreach (var stmt in deptStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    var fields = ParseCsvLine(tuple);
                    deptSeq++;
                    int oldId = int.TryParse(GetField(fields, cols, "id", 0), out var oid) ? oid : deptSeq;
                    var name = GetField(fields, cols, "dept_name", 1) ?? "Department";
                    var code = GetField(fields, cols, "dept_code", 2) ?? $"DEPT-{deptSeq}";
                    var desc = GetField(fields, cols, "description", 3);

                    if (existingDepts.TryGetValue(code.ToLower(), out var ex))
                    {
                        if (oldId > 0) deptIdMap[oldId] = ex.Id;
                    }
                    else
                    {
                        var dept = new Department { DeptName = name, DeptCode = code, Description = desc };
                        newDepts.Add((oldId, dept));
                        existingDepts[code.ToLower()] = dept;
                    }
                }
            }
            if (newDepts.Count > 0)
            {
                _context.Departments.AddRange(newDepts.Select(x => x.entity));
                await _context.SaveChangesAsync();
                foreach (var (oldId, entity) in newDepts)
                {
                    if (oldId > 0) deptIdMap[oldId] = entity.Id;
                    importedCount++;
                }
            }

            // 2. Courses
            var existingCourses = await _context.Courses.ToDictionaryAsync(c => c.CourseCode.ToLower());
            var newCourses = new List<(int oldId, Course entity)>();
            foreach (var stmt in courseStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    var fields = ParseCsvLine(tuple);
                    courseSeq++;
                    int oldCourseId = int.TryParse(GetField(fields, cols, "id", 0), out var cid) ? cid : courseSeq;
                    var code = GetField(fields, cols, "course_code", 1) ?? $"CRS-{oldCourseId}";
                    var name = GetField(fields, cols, "course_name", 2) ?? code;
                    var cat = GetField(fields, cols, "category", 4) ?? "General";
                    var dur = GetField(fields, cols, "duration", 5) ?? "Variable";
                    decimal stdFee = decimal.TryParse(GetField(fields, cols, "standard_fee", 6), NumberStyles.Any, CultureInfo.InvariantCulture, out var f) ? f : 0m;
                    decimal ttbFee = decimal.TryParse(GetField(fields, cols, "ttb_exam_fee", 7), NumberStyles.Any, CultureInfo.InvariantCulture, out var tf) ? tf : 0m;
                    var desc = GetField(fields, cols, "description", 8);

                    if (existingCourses.TryGetValue(code.ToLower(), out var ex))
                    {
                        if (oldCourseId > 0) courseIdMap[oldCourseId] = ex.Id;
                    }
                    else
                    {
                        var c = new Course
                        {
                            CourseCode = code,
                            CourseName = name,
                            Category = cat,
                            Duration = dur,
                            StandardFee = stdFee,
                            TtbExamFee = ttbFee,
                            Description = desc
                        };
                        newCourses.Add((oldCourseId, c));
                        existingCourses[code.ToLower()] = c;
                    }
                }
            }
            if (newCourses.Count > 0)
            {
                _context.Courses.AddRange(newCourses.Select(x => x.entity));
                await _context.SaveChangesAsync();
                foreach (var (oldId, entity) in newCourses)
                {
                    if (oldId > 0) courseIdMap[oldId] = entity.Id;
                    importedCount++;
                }
            }

            var firstCourseId = (await _context.Courses.Select(c => c.Id).FirstOrDefaultAsync());
            if (firstCourseId == 0) firstCourseId = 1;

            // 3. Batches
            var existingBatches = await _context.Batches.ToDictionaryAsync(b => b.BatchCode.ToLower());
            var newBatches = new List<(int oldId, Batch entity)>();
            foreach (var stmt in batchStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    var fields = ParseCsvLine(tuple);
                    batchSeq++;
                    int oldBatchId = int.TryParse(GetField(fields, cols, "id", 0), out var bid) ? bid : batchSeq;
                    var code = GetField(fields, cols, "batch_code", 1) ?? $"BATCH-{oldBatchId}";
                    var name = GetField(fields, cols, "batch_name", 2) ?? code;
                    int oldCourseId = int.TryParse(GetField(fields, cols, "course_id", 3), out var ocid) ? ocid : 0;
                    int targetCourseId = courseIdMap.TryGetValue(oldCourseId, out var mappedCId) ? mappedCId : firstCourseId;
                    var startDate = GetField(fields, cols, "start_date", 4);
                    var endDate = GetField(fields, cols, "end_date", 5);
                    var status = GetField(fields, cols, "status", 6) ?? "active";

                    if (existingBatches.TryGetValue(code.ToLower(), out var ex))
                    {
                        if (oldBatchId > 0) batchIdMap[oldBatchId] = ex.Id;
                    }
                    else
                    {
                        var b = new Batch
                        {
                            BatchCode = code,
                            BatchName = name,
                            CourseId = targetCourseId,
                            StartDate = startDate,
                            EndDate = endDate,
                            Status = status
                        };
                        newBatches.Add((oldBatchId, b));
                        existingBatches[code.ToLower()] = b;
                    }
                }
            }
            if (newBatches.Count > 0)
            {
                _context.Batches.AddRange(newBatches.Select(x => x.entity));
                await _context.SaveChangesAsync();
                foreach (var (oldId, entity) in newBatches)
                {
                    if (oldId > 0) batchIdMap[oldId] = entity.Id;
                    importedCount++;
                }
            }

            // 4. Staff Members
            var existingStaffCodes = (await _context.StaffMembers.Select(s => s.StaffCode.ToLower()).ToListAsync()).ToHashSet();
            var newStaff = new List<StaffMember>();
            foreach (var stmt in staffStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    var fields = ParseCsvLine(tuple);
                    var code = GetField(fields, cols, "staff_code", 1) ?? "STF";
                    var fullName = GetField(fields, cols, "full_name", 2) ?? "Staff Member";
                    var desig = GetField(fields, cols, "designation", 3) ?? "Staff";
                    var phone = GetField(fields, cols, "phone", 4);
                    decimal baseSalary = decimal.TryParse(GetField(fields, cols, "base_salary", 6), NumberStyles.Any, CultureInfo.InvariantCulture, out var sal) ? sal : 0m;

                    if (!existingStaffCodes.Contains(code.ToLower()))
                    {
                        newStaff.Add(new StaffMember
                        {
                            StaffCode = code,
                            FullName = fullName,
                            Designation = desig,
                            Phone = phone,
                            BaseSalary = baseSalary,
                            IsActive = true
                        });
                        existingStaffCodes.Add(code.ToLower());
                        importedCount++;
                    }
                }
            }
            if (newStaff.Count > 0)
            {
                _context.StaffMembers.AddRange(newStaff);
                await _context.SaveChangesAsync();
            }

            // 5. Users
            var existingUsers = await _context.Users.ToDictionaryAsync(u => u.Username.ToLower());
            var newUsers = new List<User>();
            var defaultPasswordHash = BCrypt.Net.BCrypt.HashPassword("islamia123");
            foreach (var stmt in userStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    var fields = ParseCsvLine(tuple);
                    var username = GetField(fields, cols, "username", 1)?.Trim().ToLower() ?? "";
                    var hash = GetField(fields, cols, "password_hash", 2) ?? "";
                    var fullName = GetField(fields, cols, "full_name", 3) ?? username;
                    var email = GetField(fields, cols, "email", 4);
                    var phone = GetField(fields, cols, "phone", 5);
                    var roleStr = GetField(fields, cols, "role", 11) ?? "staff";
                    var isActiveStr = GetField(fields, cols, "is_active", 12);
                    bool isActive = isActiveStr != "0";

                    if (string.IsNullOrWhiteSpace(username)) continue;

                    var role = roleStr.ToLower() switch
                    {
                        "admin" => UserRole.Admin,
                        "accountant" => UserRole.Accountant,
                        _ => UserRole.Teacher
                    };

                    if (existingUsers.TryGetValue(username, out var existing))
                    {
                        existing.FullName = fullName;
                        existing.Role = role;
                        existing.Phone = phone ?? existing.Phone;
                        existing.Email = email ?? existing.Email;
                        existing.IsActive = isActive;
                        if (hash.StartsWith("$2"))
                        {
                            existing.PasswordHash = hash;
                        }
                    }
                    else
                    {
                        var u = new User
                        {
                            Username = username,
                            PasswordHash = hash.StartsWith("$2") ? hash : defaultPasswordHash,
                            FullName = fullName,
                            Phone = phone,
                            Email = email,
                            Role = role,
                            IsActive = isActive
                        };
                        newUsers.Add(u);
                        existingUsers[username] = u;
                        importedCount++;
                    }
                }
            }
            if (newUsers.Count > 0)
            {
                _context.Users.AddRange(newUsers);
                await _context.SaveChangesAsync();
            }

            // 6. Students
            var existingStudents = await _context.Students.ToDictionaryAsync(s => s.StudentRegNo.ToLower());
            var newStudents = new List<(int oldId, Student entity)>();
            foreach (var stmt in studentStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    var fields = ParseCsvLine(tuple);
                    studentSeq++;
                    int oldStudentId = int.TryParse(GetField(fields, cols, "id", 0), out var sid) ? sid : studentSeq;
                    var regNo = GetField(fields, cols, "student_reg_no", 1) ?? $"ICT-STD-{oldStudentId}";
                    var fullName = GetField(fields, cols, "full_name", 2) ?? "Student";
                    var father = GetField(fields, cols, "father_name", 3);
                    var phone = GetField(fields, cols, "contact_number", 4);
                    var secPhone = GetField(fields, cols, "secondary_contact", 5);
                    var cnic = GetField(fields, cols, "cnic", 6);
                    var dob = GetField(fields, cols, "date_of_birth", 7);
                    var email = GetField(fields, cols, "email", 8);
                    var address = GetField(fields, cols, "address", 9);

                    if (existingStudents.TryGetValue(regNo.ToLower(), out var existing))
                    {
                        if (oldStudentId > 0) studentIdMap[oldStudentId] = existing.Id;
                    }
                    else
                    {
                        var s = new Student
                        {
                            StudentRegNo = regNo,
                            FullName = fullName,
                            FatherName = father,
                            ContactNumber = phone,
                            SecondaryContact = secPhone,
                            Cnic = cnic,
                            DateOfBirth = dob,
                            Email = email,
                            Address = address
                        };
                        newStudents.Add((oldStudentId, s));
                        existingStudents[regNo.ToLower()] = s;
                    }
                }
            }
            if (newStudents.Count > 0)
            {
                _context.Students.AddRange(newStudents.Select(x => x.entity));
                await _context.SaveChangesAsync();
                foreach (var (oldId, entity) in newStudents)
                {
                    if (oldId > 0) studentIdMap[oldId] = entity.Id;
                    importedCount++;
                }
            }

            var defaultBatchId = (await _context.Batches.Select(b => b.Id).FirstOrDefaultAsync());
            if (defaultBatchId == 0) defaultBatchId = 1;

            // 7. Enrollments
            var newEnrollments = new List<(int oldId, Enrollment entity)>();
            foreach (var stmt in enrollmentStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    var fields = ParseCsvLine(tuple);
                    enrollmentSeq++;
                    int oldEnrollmentId = int.TryParse(GetField(fields, cols, "id", 0), out var eid) ? eid : enrollmentSeq;
                    int oldStudentId = int.TryParse(GetField(fields, cols, "student_id", 1), out var sid) ? sid : 0;
                    int oldBatchId = int.TryParse(GetField(fields, cols, "batch_id", cols != null ? 0 : 3), out var bid) ? bid : 0;
                    var rollNumber = GetField(fields, cols, "roll_number", cols != null ? 0 : 4);
                    var enrollmentDate = GetField(fields, cols, "enrollment_date", cols != null ? 0 : 5) ?? DateTime.UtcNow.ToString("yyyy-MM-dd");
                    var academicSession = GetField(fields, cols, "academic_session", cols != null ? 0 : 6);
                    var classLevel = GetField(fields, cols, "class_level", cols != null ? 0 : 7);
                    decimal agreedFee = decimal.TryParse(GetField(fields, cols, "total_agreed_fee", cols != null ? 0 : 8), NumberStyles.Any, CultureInfo.InvariantCulture, out var af) ? af : 0m;
                    decimal admissionFee = decimal.TryParse(GetField(fields, cols, "admission_fee", cols != null ? 0 : 9), NumberStyles.Any, CultureInfo.InvariantCulture, out var adm) ? adm : 0m;
                    decimal discountAmount = decimal.TryParse(GetField(fields, cols, "discount_amount", cols != null ? 0 : 10), NumberStyles.Any, CultureInfo.InvariantCulture, out var disc) ? disc : 0m;
                    var receiptSlipNo = GetField(fields, cols, "receipt_slip_no", cols != null ? 0 : 11);
                    var tuitionClass = GetField(fields, cols, "tuition_class", cols != null ? 0 : 12);
                    var tuitionSubjects = GetField(fields, cols, "tuition_subjects", cols != null ? 0 : 13);
                    var statusStr = GetField(fields, cols, "status", cols != null ? 0 : 14) ?? "active";

                    var status = statusStr.ToLower() switch
                    {
                        "completed" => EnrollmentStatus.Completed,
                        "dropped" => EnrollmentStatus.Dropped,
                        _ => EnrollmentStatus.Active
                    };

                    if (studentIdMap.TryGetValue(oldStudentId, out var targetStudentId))
                    {
                        int targetBatchId = batchIdMap.TryGetValue(oldBatchId, out var mappedBId) ? mappedBId : defaultBatchId;

                        var en = new Enrollment
                        {
                            StudentId = targetStudentId,
                            BatchId = targetBatchId,
                            RollNumber = rollNumber,
                            EnrollmentDate = enrollmentDate,
                            AcademicSession = academicSession,
                            ClassLevel = classLevel,
                            TotalAgreedFee = agreedFee,
                            AdmissionFee = admissionFee,
                            DiscountAmount = discountAmount,
                            ReceiptSlipNo = receiptSlipNo,
                            TuitionClass = tuitionClass,
                            TuitionSubjects = tuitionSubjects,
                            Status = status
                        };
                        newEnrollments.Add((oldEnrollmentId, en));
                    }
                }
            }
            if (newEnrollments.Count > 0)
            {
                _context.Enrollments.AddRange(newEnrollments.Select(x => x.entity));
                await _context.SaveChangesAsync();
                foreach (var (oldId, entity) in newEnrollments)
                {
                    if (oldId > 0) enrollmentIdMap[oldId] = entity.Id;
                    importedCount++;
                }
            }

            var firstEnrollmentId = (await _context.Enrollments.Select(e => e.Id).FirstOrDefaultAsync());

            // 8. Fee Transactions
            var newTxs = new List<FeeTransaction>();
            foreach (var stmt in feeStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    txSequence++;
                    var fields = ParseCsvLine(tuple);
                    int oldTxId = int.TryParse(GetField(fields, cols, "id", 0), out var tid) ? tid : txSequence;
                    int oldEnrollmentId = int.TryParse(GetField(fields, cols, "enrollment_id", 1), out var eid) ? eid : 0;
                    int semester = int.TryParse(GetField(fields, cols, "semester", 2), out var sem) ? sem : 1;
                    int installmentNo = int.TryParse(GetField(fields, cols, "installment_no", 3), out var inst) ? inst : 1;
                    var receiptNo = GetField(fields, cols, "receipt_no", 4);
                    decimal paid = decimal.TryParse(GetField(fields, cols, "amount_paid", 5), NumberStyles.Any, CultureInfo.InvariantCulture, out var p) ? p : 0m;
                    decimal dues = decimal.TryParse(GetField(fields, cols, "due_amount", 6), NumberStyles.Any, CultureInfo.InvariantCulture, out var d) ? d : 0m;
                    var paymentDate = GetField(fields, cols, "payment_date", 7) ?? DateTime.UtcNow.ToString("yyyy-MM-dd");
                    var paymentMethodStr = GetField(fields, cols, "payment_method", 8) ?? "cash";
                    var paymentMode = GetField(fields, cols, "payment_mode", 9) ?? "cash";
                    var receiptVoucherNo = GetField(fields, cols, "receipt_voucher_no", 10);
                    var receivedBy = GetField(fields, cols, "received_by", 11) ?? "System Import";
                    var remarks = GetField(fields, cols, "remarks", 12);
                    decimal fineAmount = decimal.TryParse(GetField(fields, cols, "fine_amount", 14), NumberStyles.Any, CultureInfo.InvariantCulture, out var fa) ? fa : 0m;
                    decimal discountAmount = decimal.TryParse(GetField(fields, cols, "discount_amount", 15), NumberStyles.Any, CultureInfo.InvariantCulture, out var da) ? da : 0m;
                    var refNo = GetField(fields, cols, "reference_no", 16);
                    var bankName = GetField(fields, cols, "bank_name", 17);
                    var chequeDate = GetField(fields, cols, "cheque_date", 18);
                    bool isCancelled = GetField(fields, cols, "is_cancelled", 19) == "1";

                    var finalReceiptNo = !string.IsNullOrWhiteSpace(receiptNo)
                        ? receiptNo
                        : (!string.IsNullOrWhiteSpace(receiptVoucherNo) ? receiptVoucherNo : $"REC-LEG-{txSequence:D5}");

                    int targetEnrollmentId = 0;
                    if (oldEnrollmentId > 0 && enrollmentIdMap.TryGetValue(oldEnrollmentId, out var mappedEid))
                    {
                        targetEnrollmentId = mappedEid;
                    }
                    else if (firstEnrollmentId > 0)
                    {
                        targetEnrollmentId = firstEnrollmentId;
                    }

                    if (targetEnrollmentId <= 0) continue; // skip if no enrollment exists

                    var tx = new FeeTransaction
                    {
                        EnrollmentId = targetEnrollmentId,
                        Semester = semester,
                        InstallmentNo = installmentNo,
                        ReceiptNo = finalReceiptNo,
                        ReceiptVoucherNo = receiptVoucherNo,
                        AmountPaid = paid,
                        DueAmount = dues,
                        PaymentDate = paymentDate,
                        PaymentMethod = PaymentMethod.Cash,
                        PaymentMode = paymentMode,
                        ReceivedBy = receivedBy,
                        Remarks = remarks,
                        FineAmount = fineAmount,
                        DiscountAmount = discountAmount,
                        ReferenceNo = refNo,
                        BankName = bankName,
                        ChequeDate = chequeDate,
                        IsCancelled = isCancelled
                    };
                    newTxs.Add(tx);
                    importedCount++;
                }
            }
            if (newTxs.Count > 0)
            {
                _context.FeeTransactions.AddRange(newTxs);
                await _context.SaveChangesAsync();
            }

            // 9. Campus Expenses
            var newExpenses = new List<CampusExpense>();
            foreach (var stmt in expenseStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    var fields = ParseCsvLine(tuple);
                    var expTypeStr = GetField(fields, cols, "expense_type", 1) ?? "rent";
                    var monthName = GetField(fields, cols, "month_name", 2) ?? "";
                    int yearNum = int.TryParse(GetField(fields, cols, "year_num", 3), out var yn) ? yn : DateTime.UtcNow.Year;
                    decimal due = decimal.TryParse(GetField(fields, cols, "amount_due", 4), NumberStyles.Any, CultureInfo.InvariantCulture, out var ad) ? ad : 0m;
                    decimal paid = decimal.TryParse(GetField(fields, cols, "amount_paid", 5), NumberStyles.Any, CultureInfo.InvariantCulture, out var ep) ? ep : 0m;
                    var date = GetField(fields, cols, "payment_date", 6) ?? DateTime.UtcNow.ToString("yyyy-MM-dd");
                    decimal balanceDues = decimal.TryParse(GetField(fields, cols, "balance_dues", 7), NumberStyles.Any, CultureInfo.InvariantCulture, out var bd) ? bd : 0m;
                    var payee = GetField(fields, cols, "payee_name", 8) ?? "Vendor";
                    var notes = GetField(fields, cols, "notes", 9);

                    var expType = expTypeStr.ToLower() switch
                    {
                        "utility" => ExpenseType.Utility,
                        "maintenance" => ExpenseType.Maintenance,
                        _ => ExpenseType.Rent
                    };

                    newExpenses.Add(new CampusExpense
                    {
                        ExpenseType = expType,
                        MonthName = monthName,
                        YearNum = yearNum,
                        AmountDue = due,
                        AmountPaid = paid,
                        PaymentDate = date,
                        BalanceDues = balanceDues,
                        PayeeName = payee,
                        Notes = notes
                    });
                    importedCount++;
                }
            }
            if (newExpenses.Count > 0)
            {
                _context.CampusExpenses.AddRange(newExpenses);
                await _context.SaveChangesAsync();
            }

            // 10. Academic Positions
            var newPositions = new List<AcademicPosition>();
            foreach (var stmt in academicPosStatements)
            {
                var (_, cols) = ParseInsertHeader(stmt);
                var tuples = ExtractTuples(stmt);
                foreach (var tuple in tuples)
                {
                    var fields = ParseCsvLine(tuple);
                    int oldStudentId = int.TryParse(GetField(fields, cols, "student_id", 1), out var sid) ? sid : 0;
                    int? targetStudentId = studentIdMap.TryGetValue(oldStudentId, out var mappedSid) ? mappedSid : null;
                    var session = GetField(fields, cols, "session_name", 2) ?? "";
                    var batch = GetField(fields, cols, "batch_name", 3) ?? "";
                    var rank = GetField(fields, cols, "position_rank", 4) ?? "1st";
                    var sName = GetField(fields, cols, "student_name", 5) ?? "";
                    int? marks = int.TryParse(GetField(fields, cols, "marks_obtained", 6), out var m) ? m : null;
                    int? totMarks = int.TryParse(GetField(fields, cols, "total_marks", 7), out var tm) ? tm : null;
                    var award = GetField(fields, cols, "award_title", 8);

                    newPositions.Add(new AcademicPosition
                    {
                        StudentId = targetStudentId,
                        SessionName = session,
                        BatchName = batch,
                        PositionRank = rank,
                        StudentName = sName,
                        MarksObtained = marks,
                        TotalMarks = totMarks,
                        AwardTitle = award
                    });
                    importedCount++;
                }
            }
            if (newPositions.Count > 0)
            {
                _context.AcademicPositions.AddRange(newPositions);
                await _context.SaveChangesAsync();
            }

            // 11. Copy asset files (student photos, logos, seals)
            try
            {
                CopyWebAssets();
            }
            catch { }

            await transaction.CommitAsync();

            return new DataImportResultDto
            {
                Success = true,
                Message = $"Data import completed successfully. {importedCount} record(s) loaded from MySQL dump.",
                RecordsImported = importedCount
            };
        }
        catch (Exception ex)
        {
            await transaction.RollbackAsync();
            return new DataImportResultDto
            {
                Success = false,
                Message = $"Import failed with error: {ex.Message}",
                Errors = { ex.ToString() }
            };
        }
    }

    private static (string tableName, List<string>? columnNames) ParseInsertHeader(string stmt)
    {
        int valIdx = stmt.IndexOf("VALUES", StringComparison.OrdinalIgnoreCase);
        if (valIdx < 0) return ("", null);

        var header = stmt.Substring(0, valIdx).Trim();
        int parenOpen = header.IndexOf('(');
        int parenClose = header.LastIndexOf(')');

        string tablePart = parenOpen > 0 ? header.Substring(0, parenOpen).Trim() : header;
        var tableMatch = Regex.Match(tablePart, @"(?:INSERT\s+INTO\s+[`""]?([a-zA-Z0-9_]+)[`""]?)", RegexOptions.IgnoreCase);
        string tableName = tableMatch.Success ? tableMatch.Groups[1].Value.ToLower() : "";

        List<string>? columnNames = null;
        if (parenOpen > 0 && parenClose > parenOpen)
        {
            var colsPart = header.Substring(parenOpen + 1, parenClose - parenOpen - 1);
            columnNames = colsPart.Split(',')
                .Select(c => c.Trim('`', '"', '\'', ' ', '\r', '\n', '\t').ToLower())
                .ToList();
        }

        return (tableName, columnNames);
    }

    private static string? GetField(List<string> fields, List<string>? cols, string targetCol, int defaultIndexIfNoCols)
    {
        if (cols != null)
        {
            int idx = cols.IndexOf(targetCol.ToLower());
            if (idx >= 0 && idx < fields.Count)
            {
                return CleanField(fields[idx]);
            }
            return null;
        }

        if (defaultIndexIfNoCols >= 0 && defaultIndexIfNoCols < fields.Count)
        {
            return CleanField(fields[defaultIndexIfNoCols]);
        }
        return null;
    }

    private static void CopyWebAssets()
    {
        var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
        var targetFilesDir = Path.Combine(localAppData, "ICTManagementSystem", "files");
        Directory.CreateDirectory(targetFilesDir);

        var webAssetsDir = @"c:\xampp\htdocs\islamia college\assets\images";
        if (Directory.Exists(webAssetsDir))
        {
            foreach (var file in Directory.GetFiles(webAssetsDir, "*.*", SearchOption.AllDirectories))
            {
                var relPath = Path.GetRelativePath(webAssetsDir, file);
                var destFile = Path.Combine(targetFilesDir, relPath);
                var destDir = Path.GetDirectoryName(destFile);
                if (destDir != null) Directory.CreateDirectory(destDir);
                File.Copy(file, destFile, true);
            }
        }
    }

    private static string? CleanField(string? val)
    {
        if (string.IsNullOrWhiteSpace(val)) return null;
        val = val.Trim();
        if (val.Equals("NULL", StringComparison.OrdinalIgnoreCase)) return null;
        if (val.StartsWith("'") && val.EndsWith("'") && val.Length >= 2)
            val = val.Substring(1, val.Length - 2);
        else if (val.StartsWith("\"") && val.EndsWith("\"") && val.Length >= 2)
            val = val.Substring(1, val.Length - 2);

        val = val.Replace(@"\'", "'")
                 .Replace(@"\""", "\"")
                 .Replace(@"\\", @"\")
                 .Replace(@"\r", "\r")
                 .Replace(@"\n", "\n")
                 .Replace(@"\t", "\t");

        return val.Trim();
    }

    private static List<string> SplitSqlStatements(string sqlText)
    {
        var statements = new List<string>();
        var sb = new System.Text.StringBuilder();
        bool inQuotes = false;
        char quoteChar = '\0';

        for (int i = 0; i < sqlText.Length; i++)
        {
            char c = sqlText[i];
            if ((c == '\'' || c == '"') && (i == 0 || sqlText[i - 1] != '\\'))
            {
                if (!inQuotes)
                {
                    inQuotes = true;
                    quoteChar = c;
                }
                else if (quoteChar == c)
                {
                    inQuotes = false;
                }
            }

            if (c == ';' && !inQuotes)
            {
                var stmt = sb.ToString().Trim();
                if (!string.IsNullOrWhiteSpace(stmt)) statements.Add(stmt);
                sb.Clear();
            }
            else
            {
                sb.Append(c);
            }
        }

        if (sb.Length > 0)
        {
            var remaining = sb.ToString().Trim();
            if (!string.IsNullOrWhiteSpace(remaining)) statements.Add(remaining);
        }

        return statements;
    }

    private static int CountRowTuples(string sqlStatement) => ExtractTuples(sqlStatement).Count;

    private static List<string> ExtractTuples(string sqlStatement)
    {
        var results = new List<string>();
        int idx = sqlStatement.IndexOf("VALUES", StringComparison.OrdinalIgnoreCase);
        if (idx < 0) return results;

        var sub = sqlStatement[(idx + 6)..].Trim();
        bool inQuotes = false;
        char quoteChar = '\0';
        bool inTuple = false;
        var sb = new System.Text.StringBuilder();

        for (int i = 0; i < sub.Length; i++)
        {
            char c = sub[i];
            if ((c == '\'' || c == '"') && (i == 0 || sub[i - 1] != '\\'))
            {
                if (!inQuotes)
                {
                    inQuotes = true;
                    quoteChar = c;
                }
                else if (quoteChar == c)
                {
                    inQuotes = false;
                }
            }

            if (!inQuotes)
            {
                if (c == '(')
                {
                    inTuple = true;
                    sb.Clear();
                    continue;
                }
                if (c == ')' && inTuple)
                {
                    inTuple = false;
                    results.Add(sb.ToString().Trim());
                    sb.Clear();
                    continue;
                }
            }

            if (inTuple)
            {
                sb.Append(c);
            }
        }

        return results;
    }

    private static List<string> ParseCsvLine(string line)
    {
        var list = new List<string>();
        bool inQuotes = false;
        char quoteChar = '\0';
        var cur = new System.Text.StringBuilder();

        for (int i = 0; i < line.Length; i++)
        {
            char c = line[i];
            if ((c == '\'' || c == '"') && (i == 0 || line[i - 1] != '\\'))
            {
                if (!inQuotes)
                {
                    inQuotes = true;
                    quoteChar = c;
                }
                else if (quoteChar == c)
                {
                    inQuotes = false;
                }
                cur.Append(c);
            }
            else if (c == ',' && !inQuotes)
            {
                list.Add(cur.ToString().Trim());
                cur.Clear();
            }
            else
            {
                cur.Append(c);
            }
        }
        list.Add(cur.ToString().Trim());
        return list;
    }
}
