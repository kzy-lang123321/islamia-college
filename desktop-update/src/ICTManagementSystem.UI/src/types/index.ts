export type UserRole = 'Admin' | 'Cashier' | 'Teacher' | 'Registrar';

export interface User {
  id: number;
  username: string;
  fullName: string;
  role: UserRole;
  isActive: boolean;
}

export interface PagedResult<T> {
  items: T[];
  totalCount: number;
  pageNumber: number;
  pageSize: number;
  totalPages: number;
}

export interface StudentListItem {
  id: number;
  studentRegNo: string;
  fullName: string;
  fatherName?: string;
  contactNumber?: string;
  secondaryContact?: string;
  cnic?: string;
  dateOfBirth?: string;
  email?: string;
  address?: string;
  currentCourse?: string;
  currentBatch?: string;
  rollNumber?: string;
  totalAgreedFee: number;
  totalPaid: number;
  balanceDue: number;
  status: string;
}

export interface StudentDetail {
  id: number;
  studentRegNo: string;
  fullName: string;
  fatherName?: string;
  contactNumber?: string;
  secondaryContact?: string;
  cnic?: string;
  dateOfBirth?: string;
  email?: string;
  address?: string;
  enrollments: StudentEnrollment[];
  feeTransactions: StudentFeeTx[];
  attendance: StudentAttendanceSummary[];
  results: StudentResult[];
  certificates: StudentCertificate[];
}

export interface StudentEnrollment {
  id: number;
  batchId: number;
  batchCode: string;
  batchName: string;
  courseId: number;
  courseName: string;
  rollNumber?: string;
  enrollmentDate?: string;
  totalAgreedFee: number;
  discountAmount: number;
  status: string;
}

export interface StudentFeeTx {
  id: number;
  receiptNo: string;
  amountPaid: number;
  dueAmount: number;
  paymentDate: string;
  paymentMethod: string;
  receivedBy: string;
  remarks?: string;
  isCancelled: boolean;
}

export interface StudentAttendanceSummary {
  date: string;
  status: string;
  remarks?: string;
}

export interface StudentResult {
  examinationId: number;
  examName: string;
  subjectName: string;
  totalMarks: number;
  obtainedMarks: number;
  grade: string;
  gpa: number;
}

export interface StudentCertificate {
  id: number;
  certificateNo: string;
  certificateType: string;
  issueDate: string;
  courseName: string;
  gradeOrRemarks?: string;
  qrCodeData: string;
}

// Backward compatibility alias
export type Student = StudentListItem;

export interface CourseListItem {
  id: number;
  courseCode: string;
  courseName: string;
  category: string;
  duration: string;
  standardFee: number;
  ttbExamFee: number;
  totalBatches: number;
  totalStudentsEnrolled: number;
  totalFeesCollected: number;
  outstandingDues: number;
  isActive: boolean;
}

// Backward compatibility alias
export type Course = CourseListItem;

export interface CourseDetail {
  id: number;
  courseCode: string;
  courseName: string;
  category: string;
  duration: string;
  standardFee: number;
  ttbExamFee: number;
  description?: string;
  isActive: boolean;
  batches: Batch[];
  subjects: Subject[];
  enrollments: EnrollmentListItem[];
  totalFeesCollected: number;
  totalOutstandingDues: number;
}

export interface Batch {
  id: number;
  batchCode: string;
  batchName: string;
  courseId: number;
  courseName?: string;
  startDate?: string;
  endDate?: string;
  activeStudentsCount: number;
  status: string;
}

export interface Subject {
  id: number;
  subjectCode: string;
  subjectName: string;
  courseId: number;
  courseName?: string;
  totalMarks: number;
  passingMarks: number;
}

export interface EnrollmentListItem {
  id: number;
  studentId: number;
  studentRegNo: string;
  studentName: string;
  fatherName?: string;
  batchId: number;
  batchCode: string;
  batchName: string;
  courseId: number;
  courseName: string;
  rollNumber?: string;
  enrollmentDate?: string;
  totalAgreedFee: number;
  totalPaid: number;
  balanceDue: number;
  status: string;
}

export type Enrollment = EnrollmentListItem;

export interface FeeTransaction {
  id: number;
  receiptNo: string;
  amountPaid: number;
  dueAmount: number;
  fineAmount: number;
  discountAmount: number;
  paymentDate: string;
  paymentMethod: string;
  receivedBy: string;
  remarks?: string;
  isCancelled: boolean;
  enrollment?: Enrollment;
}

export interface StudentFeeDetails {
  enrollmentId: number;
  studentId: number;
  studentRegNo: string;
  fullName: string;
  fatherName: string;
  courseName: string;
  batchCode: string;
  rollNumber?: string;
  totalAgreedFee: number;
  totalPaid: number;
  currentDues: number;
  pendingFines: number;
  availableDiscounts: number;
  totalPayable: number;
  paymentHistory: FeeHistoryItem[];
}

export interface FeeHistoryItem {
  id: number;
  receiptNo: string;
  amountPaid: number;
  dueAmount: number;
  paymentDate: string;
  paymentMethod: string;
  receivedBy: string;
  isCancelled: boolean;
}

export interface PrintableReceipt {
  receiptNo: string;
  date: string;
  studentRegNo: string;
  studentName: string;
  fatherName: string;
  courseName: string;
  batchCode: string;
  rollNumber?: string;
  totalAgreedFee: number;
  previousPaid: number;
  currentPaid: number;
  remainingBalance: number;
  paymentMethod: string;
  receivedBy: string;
  institutionName: string;
  campusAddress: string;
  affiliation: string;
  phone: string;
  website: string;
}

export interface SyncStatus {
  state: string;
  lastSyncTime?: string;
  pendingCount: number;
  driveAccount?: string;
  warningMessage?: string;
}

export interface DashboardSummary {
  totalStudents: number;
  activeBatches: number;
  totalFeesCollected: number;
  totalOutstandingDues: number;
  totalCampusExpenses: number;
  todayFeesCollected: number;
  todayTransactionsCount: number;
  attendanceRateToday: number;
  syncStatus: SyncStatus;
  recentTransactions: Array<{
    id: number;
    receiptNo: string;
    studentName: string;
    courseName: string;
    amountPaid: number;
    paymentDate: string;
    receivedBy: string;
  }>;
}

export interface TechnologyDisciplineStat {
  category: string;
  studentCount: number;
  percentage: number;
}

export interface FeeCollectionTrend {
  monthLabel: string;
  monthlySum: number;
}

export interface FeeLedgerItem {
  id: number;
  receiptNo: string;
  enrollmentId: number;
  studentRegNo: string;
  studentName: string;
  courseName: string;
  batchCode: string;
  amountPaid: number;
  dueAmount: number;
  paymentDate: string;
  paymentMethod: string;
  receivedBy: string;
  remarks?: string;
  isCancelled: boolean;
}

export interface OutstandingDuesItem {
  enrollmentId: number;
  studentId: number;
  studentRegNo: string;
  studentName: string;
  fatherName?: string;
  courseName: string;
  batchCode: string;
  totalAgreedFee: number;
  totalPaid: number;
  balanceDue: number;
  contactNumber?: string;
}

export interface AttendanceRecord {
  id: number;
  studentId: number;
  studentRegNo: string;
  studentName: string;
  batchId: number;
  date: string;
  status: string;
  remarks?: string;
  recordedBy: string;
}

export interface ResultCard {
  studentRegNo: string;
  studentName: string;
  fatherName: string;
  examName: string;
  academicSession: string;
  courseName: string;
  subjects: Array<{
    subjectName: string;
    totalMarks: number;
    obtainedMarks: number;
    grade: string;
    gpa: number;
  }>;
  grandTotalMarks: number;
  grandObtainedMarks: number;
  percentage: number;
  overallGrade: string;
  overallGpa: number;
  classRank?: number;
}

export interface Certificate {
  id: number;
  certificateNo: string;
  studentId: number;
  studentRegNo: string;
  studentName: string;
  fatherName: string;
  certificateType: string;
  issueDate: string;
  courseName: string;
  gradeOrRemarks?: string;
  qrCodeData: string;
  reprintCount: number;
  issuedBy: string;
}

export interface DataImportPreview {
  sourceFileName: string;
  totalDepartments: number;
  totalCourses: number;
  totalBatches: number;
  totalStudents: number;
  totalEnrollments: number;
  totalFeeTransactions: number;
  totalCampusExpenses: number;
  totalStaffMembers: number;
  totalUsers: number;
  totalFeesValue: number;
  validationWarnings: string[];
}
