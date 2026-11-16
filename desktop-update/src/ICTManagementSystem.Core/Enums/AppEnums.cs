namespace ICTManagementSystem.Core.Enums;

public enum UserRole
{
    Admin,
    Cashier,
    Teacher,
    Registrar,
    Accountant
}

public enum EnrollmentStatus
{
    Active,
    Completed,
    Dropped
}

public enum PaymentMethod
{
    Cash,
    Bank,
    Cheque,
    Online,
    Slip
}

public enum ExpenseType
{
    Rent,
    Utility,
    Maintenance,
    Printing,
    Operational,
    Other
}

public enum AttendanceStatus
{
    Present,
    Absent,
    Leave
}

public enum CertificateType
{
    Character,
    Leaving,
    Bonafide,
    CourseCompletion
}

public enum ExamStatus
{
    Draft,
    Active,
    Completed,
    Archived
}

public enum BoardExamStatus
{
    Enrolled,
    Pass,
    Fail,
    Absent
}

public enum SyncStatus
{
    Pending,
    Uploading,
    Done,
    Failed
}

