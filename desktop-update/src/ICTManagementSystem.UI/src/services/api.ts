import {
  Certificate,
  DashboardSummary,
  DataImportPreview,
  FeeLedgerItem,
  OutstandingDuesItem,
  PagedResult,
  PrintableReceipt,
  ResultCard,
  StudentDetail,
  StudentListItem,
  StudentFeeDetails,
  CourseListItem,
  CourseDetail,
  Batch,
  Subject,
  EnrollmentListItem,
  TechnologyDisciplineStat,
  FeeCollectionTrend
} from '../types';

declare global {
  interface Window {
    chrome?: {
      webview?: {
        hostObjects?: {
          bridge?: {
            Invoke(method: string, payloadJson: string): Promise<string> | string;
            InvokeAsync?(method: string, payloadJson: string): Promise<string>;
          };
          sync?: {
            bridge?: {
              Invoke(method: string, payloadJson: string): string;
            };
          };
        };
        postMessage(message: any): void;
        addEventListener?(type: string, listener: (event: any) => void): void;
      };
    };
  }
}

// Two-way WebMessage RPC registry
let rpcIdCounter = 0;
const pendingRpcs = new Map<string, { resolve: (val: any) => void; reject: (err: any) => void }>();

function handleIncomingWebMessage(event: any) {
  try {
    const raw = event.data;
    if (!raw) return;
    const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
    if (data && data.rpcId && pendingRpcs.has(data.rpcId)) {
      const { resolve, reject } = pendingRpcs.get(data.rpcId)!;
      pendingRpcs.delete(data.rpcId);
      if (data.error) {
        reject(new Error(data.error));
      } else {
        const res = typeof data.response === 'string' ? JSON.parse(data.response) : data.response;
        if (!res.success) {
          reject(new Error(res.error || res.message || 'Operation failed'));
        } else {
          resolve(res.data);
        }
      }
    }
  } catch (e) {
    console.error('Failed to parse WebMessage response:', e);
  }
}

if (typeof window !== 'undefined') {
  if (window.chrome?.webview?.addEventListener) {
    window.chrome.webview.addEventListener('message', handleIncomingWebMessage);
  }
  window.addEventListener('message', handleIncomingWebMessage);
}

function postMessageRpc<T>(method: string, payload: any): Promise<T> {
  return new Promise((resolve, reject) => {
    const rpcId = `rpc_${++rpcIdCounter}_${Date.now()}`;
    const timer = setTimeout(() => {
      if (pendingRpcs.has(rpcId)) {
        pendingRpcs.delete(rpcId);
        reject(new Error(`Bridge request timed out for [${method}]`));
      }
    }, method.startsWith('academic.import') ? 600000 : 30000);

    pendingRpcs.set(rpcId, {
      resolve: (val) => {
        clearTimeout(timer);
        resolve(val as T);
      },
      reject: (err) => {
        clearTimeout(timer);
        reject(err);
      }
    });

    const msg = JSON.stringify({
      type: 'RPC',
      rpcId,
      method,
      payload
    });
    window.chrome!.webview!.postMessage(msg);
  });
}

// Check if running inside WebView2 host
const isWebView2 = () => !!window.chrome?.webview;

async function callBridge<T>(method: string, payload: any = {}): Promise<T> {
  const payloadJson = typeof payload === 'string' ? payload : JSON.stringify(payload);

  if (isWebView2()) {
    // 1. Primary channel: Native WebMessage postMessage
    if (window.chrome?.webview?.postMessage) {
      // A rejected or timed-out mutation may already have committed. Never replay it through COM.
      return await postMessageRpc<T>(method, payload);
    }

    // 2. Fallback channel: COM hostObject bridge
    try {
      const hostObjs = window.chrome?.webview?.hostObjects;
      const bridge = hostObjs?.bridge;
      const syncBridge = (hostObjs as any)?.sync?.bridge;

      let responseRaw: any;
      if (bridge && typeof bridge.Invoke === 'function') {
        responseRaw = await bridge.Invoke(method, payloadJson);
      } else if (syncBridge && typeof syncBridge.Invoke === 'function') {
        responseRaw = syncBridge.Invoke(method, payloadJson);
      } else {
        throw new Error('WebView2 bridge host object is not accessible');
      }

      let res: any;
      if (typeof responseRaw === 'string') {
        res = JSON.parse(responseRaw);
      } else if (typeof responseRaw === 'object' && responseRaw !== null) {
        res = responseRaw;
      } else {
        throw new Error(`Invalid bridge response: ${String(responseRaw)}`);
      }

      if (!res.success) {
        throw new Error(res.error || res.message || 'Operation failed');
      }
      return res.data as T;
    } catch (err: any) {
      console.error(`Bridge Error [${method}]:`, err);
      throw err;
    }
  }

  // Standalone browser dev mock
  console.log(`[Dev Mock Bridge] Method: ${method}`, payload);
  await new Promise(r => setTimeout(r, 100));

  if (method === 'auth.login') {
    return {
      success: true,
      message: 'Logged in as Admin (Browser Mode)',
      userId: 1,
      username: payload.username || 'admin',
      fullName: 'System Administrator',
      role: 'Admin',
      token: 'mock-dev-token'
    } as any;
  }

  if (method === 'dashboard.getSummary') {
    return {
      totalStudents: 1453,
      activeBatches: 50,
      totalFeesCollected: 235192,
      totalOutstandingDues: 76912,
      totalCampusExpenses: 65000,
      todayFeesCollected: 0,
      todayTransactionsCount: 0,
      attendanceRateToday: 91.5,
      syncStatus: {
        state: 'Synced',
        lastSyncTime: 'Live Local DB',
        pendingCount: 0,
        driveAccount: 'aminullahameen@gmail.com'
      },
      recentTransactions: []
    } as any;
  }

  return {} as T;
}

export const api = {
  // Auth
  login: (username: string, password: string) => callBridge<any>('auth.login', { username, password }),
  changePassword: (userId: number, oldPassword: string, newPassword: string) =>
    callBridge<boolean>('auth.changePassword', { userId, oldPassword, newPassword }),

  // Dashboard
  getDashboardSummary: () => callBridge<DashboardSummary>('dashboard.getSummary'),
  getTechnologyDisciplines: () => callBridge<TechnologyDisciplineStat[]>('dashboard.getDisciplines'),
  getFeeCollectionTrend: () => callBridge<FeeCollectionTrend[]>('dashboard.getFeeTrend'),

  // Students
  getStudentsPaged: (params?: { search?: string; batchId?: number; pageNumber?: number; pageSize?: number; sortBy?: string; sortDesc?: boolean }) =>
    callBridge<PagedResult<StudentListItem>>('students.getPaged', params || {}),
  getAllStudents: (search?: string, batchId?: number) =>
    callBridge<StudentListItem[]>('students.getAll', { search, batchId }),
  getStudentById: (id: number) => callBridge<StudentDetail>('students.getById', { id }),
  getStudentByRegNo: (regNo: string) => callBridge<StudentDetail>('students.getByRegNo', { regNo }),
  createStudent: (student: any) =>
    callBridge<StudentDetail>('students.create', student),
  updateStudent: (student: any) => callBridge<StudentDetail>('students.update', student),
  deleteStudent: (id: number, deletedBy: string = 'Admin') =>
    callBridge<boolean>('students.delete', { id, deletedBy }),
  getNextRegistrationNo: (category: string = 'DIT') =>
    callBridge<string>('students.getNextRegNo', { category }),

  // Courses & Academic Modules
  getCoursesPaged: (params?: { search?: string; category?: string; pageNumber?: number; pageSize?: number; activeOnly?: boolean }) =>
    callBridge<PagedResult<CourseListItem>>('courses.getPaged', params || {}),
  getAllCourses: (activeOnly?: boolean) => callBridge<CourseListItem[]>('courses.getAll', { activeOnly }),
  getCourseById: (id: number) => callBridge<CourseDetail>('courses.getById', { id }),
  createCourse: (data: any) => callBridge<CourseListItem>('courses.create', data),
  updateCourse: (data: any) => callBridge<CourseListItem>('courses.update', data),
  archiveCourse: (id: number) => callBridge<boolean>('courses.archive', { id }),
  setBatchActive: (id: number, active: boolean) => callBridge<boolean>('courses.setBatchActive', { id, active }),
  importAcademic: (mode: 'students' | 'marks', data: any) => callBridge<{ imported: number; skipped: number; errors: string[] }>(mode === 'students' ? 'academic.importStudents' : 'academic.importMarks', data),
  getAcademicExams: () => callBridge<{ id: number; name: string; academicSession: string; classesIncluded?: string }[]>('academic.getExams', {}),
  getBatches: (courseId?: number) => callBridge<Batch[]>('courses.getBatches', { courseId }),
  createBatch: (data: { batchCode: string; batchName: string; courseId: number; startDate?: string }) =>
    callBridge<Batch>('courses.createBatch', data),
  getSubjects: (courseId?: number) => callBridge<Subject[]>('courses.getSubjects', { courseId }),
  createSubject: (data: { subjectCode: string; subjectName: string; courseId: number; totalMarks: number; passingMarks: number }) =>
    callBridge<Subject>('courses.createSubject', data),
  getEnrollments: (courseId?: number, batchId?: number) =>
    callBridge<EnrollmentListItem[]>('courses.getEnrollments', { courseId, batchId }),
  createEnrollment: (data: any) => callBridge<EnrollmentListItem>('courses.createEnrollment', data),
  getDisciplinesDistribution: () => callBridge<TechnologyDisciplineStat[]>('courses.getDisciplinesDistribution'),

  // Fees
  getStudentFeeDetails: (enrollmentId: number) => callBridge<StudentFeeDetails>('fees.getStudentDetails', { enrollmentId }),
  searchStudentForFee: (query: string) => callBridge<StudentFeeDetails | null>('fees.searchStudent', { query }),
  receivePayment: (request: {
    enrollmentId: number;
    amountPaid: number;
    fineAmount?: number;
    discountAmount?: number;
    paymentMethod: string;
    referenceNo?: string;
    bankName?: string;
    remarks?: string;
    receivedBy?: string;
  }) => callBridge<{ success: boolean; message: string; receiptNo: string; receiptData?: PrintableReceipt }>('fees.receivePayment', request),
  cancelPayment: (transactionId: number, reason: string, cancelledBy: string) =>
    callBridge<boolean>('fees.cancelPayment', { transactionId, reason, cancelledBy }),
  getPaymentLedger: (startDate?: string, endDate?: string, batchId?: number) =>
    callBridge<FeeLedgerItem[]>('fees.getPaymentLedger', { startDate, endDate, batchId }),
  getOutstandingDues: (batchId?: number) => callBridge<OutstandingDuesItem[]>('fees.getOutstandingDues', { batchId }),
  getDailyCollection: (date?: string) => callBridge<Record<string, number>>('fees.getDailyCollection', { date }),
  getNextReceiptNo: () => callBridge<string>('fees.getNextReceiptNo'),

  // Attendance
  getAttendance: (batchId: number, date: string) => callBridge<any[]>('attendance.getByBatchAndDate', { batchId, date }),
  recordAttendance: (data: { batchId: number; date: string; items: any[]; recordedBy: string }) =>
    callBridge<boolean>('attendance.record', data),
  getMonthlyAttendanceSheet: (batchId: number, year: number, month: number) =>
    callBridge<any[]>('attendance.getMonthlySheet', { batchId, year, month }),

  // Results
  saveMarks: (data: { examinationId: number; studentId: number; subjectName: string; totalMarks: number; obtainedMarks: number; remarks?: string }) =>
    callBridge<boolean>('results.saveMarks', data),
  getResultCard: (examinationId: number, studentId: number) =>
    callBridge<ResultCard>('results.getCard', { examinationId, studentId }),
  getClassResultSheet: (examinationId: number, batchId: number) =>
    callBridge<ResultCard[]>('results.getClassSheet', { examinationId, batchId }),

  // Certificates
  generateCertificate: (data: { studentId: number; certificateType: string; courseName: string; gradeOrRemarks?: string; issuedBy: string }) =>
    callBridge<Certificate>('certificates.generate', data),
  getCertificateById: (id: number) => callBridge<Certificate>('certificates.getById', { id }),

  // Users & Roles (RBAC)
  getUsers: () => callBridge<any[]>('users.getAll'),
  createUser: (data: { username: string; fullName: string; phone?: string; email?: string; password?: string; role: string }) =>
    callBridge<any>('users.create', data),
  toggleUserStatus: (userId: number) => callBridge<boolean>('users.toggleStatus', { userId }),
  resetUserPassword: (userId: number, newPassword: string) =>
    callBridge<boolean>('users.resetPassword', { userId, newPassword }),
  deleteUser: (userId: number) => callBridge<boolean>('users.delete', { userId }),

  // Backup & Drive Sync
  createBackup: (targetDir?: string, password?: string) => callBridge<string>('backup.create', { targetDir, password }),
  restoreBackup: (filePath: string, password?: string) => callBridge<boolean>('backup.restore', { filePath, password }),
  getSyncStatus: () => callBridge<any>('backup.getStatus'),
  triggerSyncNow: () => callBridge<boolean>('backup.triggerSync'),

  // Data Import
  previewSqlDump: (filePath: string) => callBridge<DataImportPreview>('import.preview', { filePath }),
  executeImport: (filePath: string) => callBridge<{ success: boolean; message: string; recordsImported: number }>('import.execute', { filePath }),

  // Native WebView2 printing
  triggerPrint: () => {
    if (window.chrome?.webview?.postMessage) {
      window.chrome.webview.postMessage(JSON.stringify({ type: 'PRINT' }));
    } else {
      window.print();
    }
  }
};
