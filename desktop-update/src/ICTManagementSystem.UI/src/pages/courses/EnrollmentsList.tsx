import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Users, Search, Plus, ArrowLeft, CheckCircle2 } from 'lucide-react';
import { api } from '../../services/api';
import { EnrollmentListItem, Batch, CourseListItem } from '../../types';
import { useAppStore } from '../../services/store';

export const EnrollmentsList: React.FC = () => {
  const navigate = useNavigate();
  const { addToast } = useAppStore();
  const [enrollments, setEnrollments] = useState<EnrollmentListItem[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);
  const [courses, setCourses] = useState<CourseListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedBatchId, setSelectedBatchId] = useState<number | undefined>();
  const [isNewEnrollmentOpen, setIsNewEnrollmentOpen] = useState(false);

  // Form State
  const [form, setForm] = useState({
    studentSearch: '',
    selectedStudentId: 0,
    selectedStudentName: '',
    selectedStudentRegNo: '',
    batchId: 0,
    rollNumber: '',
    totalAgreedFee: 18000,
    admissionFee: 0,
    discountAmount: 0,
    enrollmentDate: new Date().toISOString().split('T')[0]
  });
  const [studentSearchResults, setStudentSearchResults] = useState<any[]>([]);

  useEffect(() => {
    loadMetadata();
    loadEnrollments();
  }, [selectedBatchId]);

  const loadMetadata = async () => {
    try {
      const [bRes, cRes] = await Promise.all([
        api.getBatches(),
        api.getAllCourses()
      ]);
      setBatches(bRes);
      setCourses(cRes);
      if (bRes.length > 0 && form.batchId === 0) {
        setForm((f) => ({ ...f, batchId: bRes.find(b => b.status.toLowerCase() === 'active')?.id ?? 0 }));
      }
    } catch { }
  };

  const loadEnrollments = async () => {
    try {
      setLoading(true);
      const res = await api.getEnrollments(undefined, selectedBatchId);
      setEnrollments(res);
    } catch (err: any) {
      addToast('error', err.message || 'Failed to load enrollments');
    } finally {
      setLoading(false);
    }
  };

  const searchStudent = async (term: string) => {
    setForm((f) => ({ ...f, studentSearch: term }));
    if (term.trim().length < 2) {
      setStudentSearchResults([]);
      return;
    }
    try {
      const res = await api.getAllStudents(term);
      setStudentSearchResults(res.slice(0, 5));
    } catch { }
  };

  const handleSelectStudent = (s: any) => {
    setForm((f) => ({
      ...f,
      selectedStudentId: s.id,
      selectedStudentName: s.fullName,
      selectedStudentRegNo: s.studentRegNo,
      studentSearch: `${s.studentRegNo} - ${s.fullName}`,
      rollNumber: s.studentRegNo.replace(/[^0-9]/g, '')
    }));
    setStudentSearchResults([]);
  };

  const handleCreateEnrollment = async (saveAndNew: boolean = false) => {
    if (!form.selectedStudentId) {
      addToast('error', 'Please search and select a student.');
      return;
    }
    if (!form.batchId) {
      addToast('error', 'Please select a batch.');
      return;
    }

    try {
      await api.createEnrollment({
        studentId: form.selectedStudentId,
        batchId: form.batchId,
        rollNumber: form.rollNumber.trim() || undefined,
        totalAgreedFee: form.totalAgreedFee,
        admissionFee: form.admissionFee,
        discountAmount: form.discountAmount,
        enrollmentDate: form.enrollmentDate
      });
      addToast('success', `Student '${form.selectedStudentName}' enrolled successfully!`);

      if (saveAndNew) {
        setForm((f) => ({
          ...f,
          studentSearch: '',
          selectedStudentId: 0,
          selectedStudentName: '',
          selectedStudentRegNo: '',
          rollNumber: ''
        }));
      } else {
        setIsNewEnrollmentOpen(false);
      }
      loadEnrollments();
    } catch (err: any) {
      addToast('error', err.message || 'Failed to create enrollment');
    }
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <button
            onClick={() => navigate('/courses')}
            className="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-900 dark:hover:text-white mb-1 transition"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Courses</span>
          </button>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <Users className="w-5 h-5 text-ict-orange" />
            Active Class Enrollments
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Student batch allocations, agreed tuition fee ledgers, and academic roll assignments
          </p>
        </div>

        <button
          onClick={() => setIsNewEnrollmentOpen(true)}
          className="px-3.5 py-2 bg-ict-orange hover:bg-ict-orange-hover text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
        >
          <Plus className="w-4 h-4" />
          <span>New Enrollment</span>
        </button>
      </div>

      {/* Filter Bar */}
      <div className="flex items-center gap-3 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Filter by Batch:</label>
        <select
          value={selectedBatchId || ''}
          onChange={(e) => setSelectedBatchId(e.target.value ? parseInt(e.target.value, 10) : undefined)}
          className="py-1 px-3 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
        >
          <option value="">All Batches &amp; Cohorts</option>
          {batches.map((b) => (
            <option key={b.id} value={b.id}>
              {b.batchCode} - {b.batchName}
            </option>
          ))}
        </select>
        <span className="text-xs text-slate-400 ml-auto">
          Showing {enrollments.length} enrollment record(s)
        </span>
      </div>

      {/* Table */}
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-xs text-slate-400">Loading enrollment records...</div>
        ) : enrollments.length === 0 ? (
          <div className="p-12 text-center text-xs text-slate-500">
            No student enrollments found for the selected filter.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-3 px-4">Reg No</th>
                  <th className="py-3 px-4">Student Name</th>
                  <th className="py-3 px-4">Course</th>
                  <th className="py-3 px-4">Batch</th>
                  <th className="py-3 px-4">Roll No</th>

                  <th className="py-3 px-4 text-right">Agreed Fee</th>
                  <th className="py-3 px-4 text-right">Paid</th>
                  <th className="py-3 px-4 text-right">Balance</th>
                  <th className="py-3 px-4 text-center">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                {enrollments.map((e) => (
                  <tr
                    key={e.id}
                    className="hover:bg-slate-50 dark:hover:bg-slate-800/40 cursor-pointer"
                    onClick={() => navigate(`/students/${e.studentId}`)}
                  >
                    <td className="py-3 px-4 font-mono font-bold text-ict-orange">{e.studentRegNo}</td>
                    <td className="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                      {e.studentName}
                      {e.fatherName && (
                        <span className="block text-[11px] text-slate-400 font-normal">S/O {e.fatherName}</span>
                      )}
                    </td>
                    <td className="py-3 px-4">{e.courseName}</td>
                    <td className="py-3 px-4 font-mono text-[11px]">{e.batchCode}</td>
                    <td className="py-3 px-4 font-mono text-[11px]">{e.rollNumber || '—'}</td>

                    <td className="py-3 px-4 text-right font-medium">Rs. {e.totalAgreedFee.toLocaleString()}</td>
                    <td className="py-3 px-4 text-right text-emerald-600 font-semibold">
                      Rs. {e.totalPaid.toLocaleString()}
                    </td>
                    <td className="py-3 px-4 text-right font-bold text-amber-600">
                      {e.balanceDue > 0 ? `Rs. ${e.balanceDue.toLocaleString()}` : 'Nil'}
                    </td>
                    <td className="py-3 px-4 text-center">
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600">
                        {e.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* New Enrollment Modal */}
      {isNewEnrollmentOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-lg w-full p-6 animate-in zoom-in-95">
            <h2 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              New Course Enrollment
            </h2>
            <p className="text-xs text-slate-500 mb-4">
              Allocate a registered student to an active batch. Tuition comes from the course fee.
            </p>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleCreateEnrollment(false);
              }}
              className="space-y-3.5"
            >
              {/* Student Search Picker */}
              <div className="relative">
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Select Student *
                </label>
                <div className="relative">
                  <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="text"
                    required
                    placeholder="Type student name or reg no (e.g. ICT-DIT-411)..."
                    value={form.studentSearch}
                    onChange={(e) => searchStudent(e.target.value)}
                    className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
                  />
                </div>

                {studentSearchResults.length > 0 && (
                  <div className="absolute left-0 right-0 top-full mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl z-20 max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                    {studentSearchResults.map((s) => (
                      <div
                        key={s.id}
                        onClick={() => handleSelectStudent(s)}
                        className="p-2.5 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer flex items-center justify-between"
                      >
                        <div>
                          <div className="font-semibold text-slate-900 dark:text-white">{s.fullName}</div>
                          <div className="text-[10px] text-slate-400 font-mono">{s.studentRegNo}</div>
                        </div>
                        <span className="text-[10px] text-ict-orange font-medium">Select</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Batch Selector */}
              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Target Batch / Cohort *
                </label>
                <select
                  value={form.batchId}
                  onChange={(e) => {
                    const bid = parseInt(e.target.value, 10);
                    const bObj = batches.find((x) => x.id === bid);
                    const cObj = courses.find((c) => c.id === bObj?.courseId);
                    setForm({
                      ...form,
                      batchId: bid,
                      totalAgreedFee: cObj?.standardFee || form.totalAgreedFee
                    });
                  }}
                  className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
                >
                  {batches.filter(b => b.status.toLowerCase() === 'active').map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.batchCode} - {b.batchName} ({b.courseName})
                    </option>
                  ))}
                </select>
              </div>

              {/* Roll Number & Date */}
              <div className="grid grid-cols-2 gap-3">
                <div className="text-xs text-slate-500">UID: {form.selectedStudentId || 'Select a student'} · Enrollment date is assigned automatically.</div>
              </div>

              <p className="text-xs bg-sky-50 p-3 rounded-lg">Course tuition: Rs. {(courses.find(c => c.id === batches.find(b => b.id === form.batchId)?.courseId)?.standardFee ?? 0).toLocaleString()}. Set fees in Courses.</p>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsNewEnrollmentOpen(false)}
                  className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300 hover:bg-slate-100 rounded-lg"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={() => handleCreateEnrollment(true)}
                  className="px-3.5 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold rounded-lg"
                >
                  Save &amp; New
                </button>
                <button
                  type="submit"
                  className="px-4 py-1.5 text-xs bg-ict-orange hover:bg-ict-orange-hover text-white font-semibold rounded-lg shadow-sm"
                >
                  Enroll Student
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
