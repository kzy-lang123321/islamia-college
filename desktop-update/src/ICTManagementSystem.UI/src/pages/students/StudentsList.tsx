import React, { useEffect, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import {
  Search,
  UserPlus,
  Eye,
  ChevronLeft,
  ChevronRight,
  GraduationCap,
  Printer,
  FileText,
  X,
  CreditCard,
  TrendingUp,
  AlertTriangle,
  RotateCcw
} from 'lucide-react';
import { api } from '../../services/api';
import { StudentListItem, Batch, CourseListItem, PagedResult } from '../../types';
import { useAppStore } from '../../services/store';
import { ExcelImport } from '../../components/ExcelImport';
import { formatPhone, formatCnic, validPhone, validCnic } from '../../services/pakistan';
import collegeLogo from '../../assets/logo.png';

export const StudentsList: React.FC = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, addToast } = useAppStore();
  const [students, setStudents] = useState<StudentListItem[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [grandTotalCount, setGrandTotalCount] = useState(0);
  const [totalPages, setTotalPages] = useState(1);
  const [pageNumber, setPageNumber] = useState(1);

  // Filter Box States (exact matching web form)
  const [search, setSearch] = useState('');
  const [selectedCourseId, setSelectedCourseId] = useState<number | undefined>();
  const [selectedBatchId, setSelectedBatchId] = useState<number | undefined>();
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [rollFilter, setRollFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  // Dropdowns data
  const [batches, setBatches] = useState<Batch[]>([]);
  const [courses, setCourses] = useState<CourseListItem[]>([]);
  const [savingStudent, setSavingStudent] = useState(false);
  const [loading, setLoading] = useState(true);

  // Modals
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isPrintPreviewOpen, setIsPrintPreviewOpen] = useState(false);
  const [printPreviewTitle, setPrintPreviewTitle] = useState('Student Directory & Enrollment Roster');
  const [isInlineBatchOpen, setIsInlineBatchOpen] = useState(false);
  const [newBatchData, setNewBatchData] = useState({
    batchCode: '',
    batchName: '',
    courseId: 0
  });

  // Admission Form state
  const [formData, setFormData] = useState({
    studentRegNo: '',
    fullName: '',
    fatherName: '',
    contactNumber: '',
    secondaryContact: '',
    cnic: '',
    dateOfBirth: '',
    email: '',
    address: '',
    batchId: 0,
    agreedFee: 18000,
    rollNumber: ''
  });
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    loadBatchesAndCourses();
  }, []);

  useEffect(() => {
    loadStudents();
  }, [search, selectedBatchId, pageNumber]);

  const loadBatchesAndCourses = async () => {
    try {
      const [b, c] = await Promise.all([
        api.getBatches(),
        api.getAllCourses(true)
      ]);
      setBatches(b);
      setCourses(c);
      const params = new URLSearchParams(location.search);
      const requested = Number(params.get('selectedBatchId'));
      const target = b.find(batch => batch.id === requested && batch.status.toLowerCase() === 'active');
      const initial = target ?? b.find(batch => batch.status.toLowerCase() === 'active');
      if (initial) {
        setFormData(f => ({ ...f, batchId: initial.id,
          agreedFee: c.find(course => course.id === initial.courseId)?.standardFee ?? 0 }));
      }
      if (target) setSelectedBatchId(target.id);
      if (params.get('openAdmission') === 'true' && target) {
        await openAdmissionModal(target, c);
        navigate('/students', { replace: true });
      } else if (params.get('openAdmission') === 'true') {
        addToast('error', 'The requested batch is unavailable. Please select an active batch.');
      }
    } catch (err: any) {
      addToast('error', err.message || 'Unable to load courses and batches.');
    }
  };

  const [savingBatch, setSavingBatch] = useState(false);
  const handleInlineCreateBatch = async () => {
    if (savingBatch) return;
    if (!newBatchData.batchCode.trim() || !newBatchData.batchName.trim() || newBatchData.courseId <= 0) {
      addToast('error', 'Please select Course, and enter Batch Code & Title');
      return;
    }
    try {
      setSavingBatch(true);
      const res = await api.createBatch({
        batchCode: newBatchData.batchCode.trim().toUpperCase(),
        batchName: newBatchData.batchName.trim(),
        courseId: newBatchData.courseId
      });
      addToast('success', `Batch '${res.batchCode}' created and selected for admission!`);
      setBatches(previous => [...previous.filter(batch => batch.id !== res.id), res]);
      const cFee = courses.find((c) => c.id === newBatchData.courseId)?.standardFee ?? 0;
      setFormData((f) => ({
        ...f,
        batchId: res.id,
        agreedFee: cFee
      }));
      const prefix = courses.find(c => c.id === newBatchData.courseId)?.courseCode || 'DIT';
      const preview = await api.getNextRegistrationNo(prefix);
      setFormData(f => ({ ...f, studentRegNo: preview, rollNumber: preview.split('-').pop() || '' }));
      setIsInlineBatchOpen(false);
      setNewBatchData({ batchCode: '', batchName: '', courseId: 0 });
    } catch (err: any) {
      addToast('error', err.message || 'Failed to create batch');
    } finally {
      setSavingBatch(false);
    }
  };

  const loadStudents = async () => {
    try {
      setLoading(true);
      const res: PagedResult<StudentListItem> = await api.getStudentsPaged({
        search: search.trim() || undefined,
        batchId: selectedBatchId,
        pageNumber,
        pageSize: 25
      });
      setStudents(res.items);
      setTotalCount(res.totalCount);
      if (res.totalCount > grandTotalCount) {
        setGrandTotalCount(res.totalCount);
      }
      setTotalPages(res.totalPages);
    } catch (err: any) {
      addToast('error', err.message || 'Failed to load students');
    } finally {
      setLoading(false);
    }
  };

  // Filter apply action
  const handleApplyFilters = (e: React.FormEvent) => {
    e.preventDefault();
    setPageNumber(1);
    loadStudents();
  };

  const handleClearFilters = () => {
    setSearch('');
    setSelectedCourseId(undefined);
    setSelectedBatchId(undefined);
    setStatusFilter('');
    setRollFilter('');
    setDateFrom('');
    setDateTo('');
    setPageNumber(1);
  };

  // Compute income totals for the currently displayed or filtered batch
  const selectedBatchObj = batches.find((b) => b.id === selectedBatchId);
  const selectedCourseObj = courses.find((c) => c.id === selectedCourseId);

  const totalAgreedSum = students.reduce((sum, s) => sum + (s.totalAgreedFee || 0), 0);
  const totalPaidSum = students.reduce((sum, s) => sum + (s.totalPaid || 0), 0);
  const totalDueSum = students.reduce((sum, s) => sum + (s.balanceDue || 0), 0);
  const collectionRate = totalAgreedSum > 0 ? Math.round((totalPaidSum / totalAgreedSum) * 100) : 0;

  const openAdmissionModal = async (target?: Batch, availableCourses = courses) => {
    const batch = target ?? batches.find(b => b.id === selectedBatchId && b.status.toLowerCase() === 'active') ?? batches.find(b => b.status.toLowerCase() === 'active');
    if (!batch) { addToast('error', 'Create or activate an academic batch first.'); return; }
    try {
      const nextReg = await api.getNextRegistrationNo(availableCourses.find(c => c.id === batch?.courseId)?.courseCode || 'DIT');
      setFormData({
        studentRegNo: nextReg,
        fullName: '',
        fatherName: '',
        contactNumber: '',
        secondaryContact: '',
        cnic: '',
        dateOfBirth: '',
        email: '',
        address: '',
        batchId: batch?.id ?? 0,
        agreedFee: availableCourses.find(c => c.id === batch?.courseId)?.standardFee ?? 0,
        rollNumber: nextReg.replace(/[^0-9]/g, '')
      });
      setFormErrors({});
      setIsModalOpen(true);
    } catch {
      setIsModalOpen(true);
    }
  };

  const validateForm = () => {
    const errors: Record<string, string> = {};
    if (!formData.batchId) errors.studentRegNo = 'Select an active batch.';
    if (!formData.fullName.trim()) errors.fullName = 'Student full name is required.';
    if (!validPhone(formData.contactNumber)) errors.contactNumber = 'Enter +92 followed by 10 digits.';
    if (formData.cnic && !validCnic(formData.cnic)) errors.cnic = 'CNIC must have 13 digits (12345-1234567-1).';
    if (formData.agreedFee < 0) errors.agreedFee = 'Agreed fee cannot be negative.';
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCreate = async (saveAndNew: boolean = false) => {
    if (savingStudent || !validateForm()) return;

    try {
      setSavingStudent(true);
      const saved = await api.createStudent({
        studentRegNo: '',
        fullName: formData.fullName.trim(),
        fatherName: formData.fatherName?.trim() || undefined,
        contactNumber: formData.contactNumber?.trim() || undefined,
        secondaryContact: formData.secondaryContact?.trim() || undefined,
        cnic: formData.cnic?.trim() || undefined,
        dateOfBirth: formData.dateOfBirth || undefined,
        email: formData.email?.trim() || undefined,
        address: formData.address?.trim() || undefined,
        batchId: formData.batchId > 0 ? formData.batchId : undefined,
        agreedFee: undefined,
        rollNumber: undefined
      });

      addToast('success', `Student '${formData.fullName}' admitted successfully (${saved.studentRegNo})!`);

      if (saveAndNew) {
        const nextReg = await api.getNextRegistrationNo(courses.find(c => c.id === batches.find(b => b.id === formData.batchId)?.courseId)?.courseCode || 'DIT');
        setFormData({
          studentRegNo: nextReg,
          fullName: '',
          fatherName: '',
          contactNumber: '',
          secondaryContact: '',
          cnic: '',
          dateOfBirth: '',
          email: '',
          address: '',
          batchId: formData.batchId,
          agreedFee: 18000,
          rollNumber: nextReg.replace(/[^0-9]/g, '')
        });
        setFormErrors({});
      } else {
        setIsModalOpen(false);
      }
      loadStudents();
    } catch (err: any) {
      addToast('error', err.message || 'Admission registration failed');
    } finally { setSavingStudent(false); }
  };

  const handleDelete = async (studentId: number, name: string) => {
    if (!confirm(`Are you sure you want to remove student '${name}'? This action is tracked in audit logs.`)) return;
    try {
      await api.deleteStudent(studentId, user?.fullName || 'Admin');
      addToast('info', `Student record for '${name}' deleted.`);
      loadStudents();
    } catch (err: any) {
      addToast('error', err.message || 'Failed to delete student');
    }
  };

  const hasActiveFilters = Boolean(
    search || selectedCourseId || selectedBatchId || statusFilter || rollFilter || dateFrom || dateTo
  );

  return (
    <div className="p-6 space-y-5">
      {/* 1. Header Bar matching Screenshot 3 */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <GraduationCap className="w-5 h-5 text-blue-600" />
            Students Directory
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Academic Management • Student profiles, registrations, program enrollments, and status tracking
          </p>
        </div>

        {/* Action Buttons Top Right */}
        <div className="flex items-center gap-2">
          <button
            onClick={() => openAdmissionModal()}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
          >
            <UserPlus className="w-4 h-4" />
            <span>+ Add Student</span>
          </button>
          <button
            onClick={() => {
              setPrintPreviewTitle('Student Directory — Active View');
              setIsPrintPreviewOpen(true);
            }}
            className="px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
          >
            <Printer className="w-4 h-4 text-slate-500" />
            <span>Print View</span>
          </button>
          <button
            onClick={() => {
              setPrintPreviewTitle('Full Institutional Student Roster & Fee Status');
              setIsPrintPreviewOpen(true);
            }}
            className="px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
          >
            <FileText className="w-4 h-4 text-slate-500" />
            <span>Full Roster Print Preview</span>
          </button>
        </div>
      </div>

      {/* 2. Web Form Filter Box matching Screenshot 3 */}
      <form
        onSubmit={handleApplyFilters}
        className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 shadow-sm space-y-4"
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 text-xs">
          {/* 1: Search Record */}
          <div className="lg:col-span-1">
            <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
              Search Record
            </label>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Name, Reg #, CNIC..."
              className="w-full px-2.5 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* 2: Program / Course */}
          <div className="lg:col-span-1">
            <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
              Program / Course
            </label>
            <select
              value={selectedCourseId || ''}
              onChange={(e) => setSelectedCourseId(e.target.value ? parseInt(e.target.value, 10) : undefined)}
              className="w-full px-2.5 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 truncate"
            >
              <option value="">All Programs / Courses</option>
              {courses.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.courseName}
                </option>
              ))}
            </select>
          </div>

          {/* 3: Academic Batch (Selected Batch) */}
          <div className="lg:col-span-1">
            <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
              Academic Batch
            </label>
            <select
              value={selectedBatchId || ''}
              onChange={(e) => setSelectedBatchId(e.target.value ? parseInt(e.target.value, 10) : undefined)}
              className="w-full px-2.5 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 truncate"
            >
              <option value="">All Batches</option>
              {batches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.batchCode} ({b.batchName})
                </option>
              ))}
            </select>
          </div>

          {/* 4: Enrollment Status */}
          <div className="lg:col-span-1">
            <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
              Enrollment Status
            </label>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full px-2.5 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="dropped">Dropped / Unassigned</option>
            </select>
          </div>

          {/* 5: Roll Number */}
          <div className="lg:col-span-1">
            <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
              Roll Number
            </label>
            <input
              type="text"
              value={rollFilter}
              onChange={(e) => setRollFilter(e.target.value)}
              placeholder="Specific Roll #..."
              className="w-full px-2.5 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* 6: Admitted From */}
          <div className="lg:col-span-1">
            <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
              Admitted From
            </label>
            <input
              type="date"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              className="w-full px-2 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-[11px]"
            />
          </div>

          {/* 7: Admitted To */}
          <div className="lg:col-span-1">
            <label className="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
              Admitted To
            </label>
            <input
              type="date"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="w-full px-2 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-[11px]"
            />
          </div>
        </div>

        {/* Filter Footer: Match Count & Apply Button */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between border-t border-slate-100 dark:border-slate-800 pt-3 gap-2">
          <div className="text-xs text-slate-500 dark:text-slate-400">
            Showing <strong className="text-slate-900 dark:text-white">{students.length}</strong> of{' '}
            <strong className="text-slate-900 dark:text-white">{totalCount.toLocaleString()}</strong> matching students{' '}
            ({grandTotalCount.toLocaleString()} total in database)
          </div>

          <div className="flex items-center gap-2">
            <button
              type="submit"
              className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
            >
              <Search className="w-3.5 h-3.5" />
              <span>Apply Filters</span>
            </button>
            {hasActiveFilters && (
              <button
                type="button"
                onClick={handleClearFilters}
                className="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg transition flex items-center gap-1"
              >
                <RotateCcw className="w-3.5 h-3.5" />
                <span>Clear Filters</span>
              </button>
            )}
          </div>
        </div>
      </form>

      <ExcelImport mode="students" batchId={selectedBatchId || 0} onComplete={() => { loadStudents(); loadBatchesAndCourses(); }} />
      {/* 3. Selected Batch & Income Total Summary Box */}
      <div className="bg-gradient-to-r from-blue-50 via-indigo-50 to-emerald-50 dark:from-slate-900 dark:via-slate-800/80 dark:to-slate-900 rounded-xl border border-blue-100 dark:border-slate-800 p-4 shadow-sm">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="space-y-0.5">
            <span className="text-[10px] font-bold tracking-wider uppercase text-blue-700 dark:text-blue-400">
              Active Cohort Financial Scope
            </span>
            <h2 className="text-sm font-bold text-slate-900 dark:text-white">
              {selectedBatchObj
                ? `Batch ${selectedBatchObj.batchCode} — ${selectedBatchObj.batchName}`
                : selectedCourseObj
                ? `Course: ${selectedCourseObj.courseName}`
                : 'All Enrolled Academic Batches'}
            </h2>
            <p className="text-[11px] text-slate-500 dark:text-slate-400">
              {students.length} student(s) currently displayed in roster • Recovery Rate:{' '}
              <strong className="text-emerald-700 dark:text-emerald-400">{collectionRate}%</strong>
            </p>
          </div>

          {/* KPI Badges */}
          <div className="grid grid-cols-3 gap-3 text-xs">
            <div className="p-2.5 bg-white dark:bg-slate-800/90 rounded-lg border border-slate-200 dark:border-slate-700 shadow-2xs">
              <span className="text-[10px] text-slate-500 block">Total Agreed Fees</span>
              <strong className="text-sm text-slate-900 dark:text-white">Rs. {totalAgreedSum.toLocaleString()}</strong>
            </div>
            <div className="p-2.5 bg-white dark:bg-slate-800/90 rounded-lg border border-slate-200 dark:border-slate-700 shadow-2xs">
              <span className="text-[10px] text-emerald-600 font-semibold block flex items-center gap-1">
                <TrendingUp className="w-3 h-3" /> Collected Income
              </span>
              <strong className="text-sm text-emerald-600">Rs. {totalPaidSum.toLocaleString()}</strong>
            </div>
            <div className="p-2.5 bg-white dark:bg-slate-800/90 rounded-lg border border-slate-200 dark:border-slate-700 shadow-2xs">
              <span className="text-[10px] text-amber-600 font-semibold block flex items-center gap-1">
                <AlertTriangle className="w-3 h-3" /> Outstanding Dues
              </span>
              <strong className="text-sm text-amber-600">Rs. {totalDueSum.toLocaleString()}</strong>
            </div>
          </div>
        </div>
      </div>

      {/* 4. Student Roster Table matching Screenshot 3 */}
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-xs text-slate-400">Loading student directory...</div>
        ) : students.length === 0 ? (
          <div className="p-12 text-center text-xs text-slate-500">
            No students found matching your criteria.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-3 px-4">REG NO.</th>
                  <th className="py-3 px-4">ROLL NO.</th>
                  <th className="py-3 px-4">STUDENT NAME</th>
                  <th className="py-3 px-4">FATHER NAME</th>
                  <th className="py-3 px-4">PROGRAM &amp; BATCH</th>
                  <th className="py-3 px-4">CONTACT</th>
                  <th className="py-3 px-4 text-right">AGREED FEE</th>
                  <th className="py-3 px-4 text-right">PAID</th>
                  <th className="py-3 px-4 text-right">BALANCE</th>
                  <th className="py-3 px-4">STATUS</th>
                  <th className="py-3 px-4 text-right">ACTIONS</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                {students.map((s) => (
                  <tr
                    key={s.id}
                    className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition cursor-pointer"
                    onClick={() => navigate(`/students/${s.id}`)}
                  >
                    <td className="py-3 px-4 font-mono font-bold text-blue-600 dark:text-blue-400">
                      {s.studentRegNo}<span className="block text-[10px] text-slate-400">UID: {s.id}</span>
                    </td>
                    <td className="py-3 px-4 font-mono text-slate-500">
                      {s.rollNumber || '—'}
                    </td>
                    <td className="py-3 px-4">
                      <div className="font-semibold text-slate-900 dark:text-white">{s.fullName}</div>
                      {s.cnic && <div className="text-[10px] text-slate-400 font-mono">{s.cnic}</div>}
                    </td>
                    <td className="py-3 px-4 text-slate-600 dark:text-slate-400">{s.fatherName || '—'}</td>
                    <td className="py-3 px-4">
                      <div className="font-medium text-slate-800 dark:text-slate-200">{s.currentCourse || 'Unassigned'}</div>
                      {s.currentBatch && (
                        <div className="text-[10px] font-mono text-slate-400">{s.currentBatch}</div>
                      )}
                    </td>
                    <td className="py-3 px-4 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                      {s.contactNumber || '—'}
                    </td>
                    <td className="py-3 px-4 text-right font-medium text-slate-800 dark:text-slate-200">
                      Rs. {s.totalAgreedFee.toLocaleString()}
                    </td>
                    <td className="py-3 px-4 text-right text-emerald-600 font-semibold">
                      Rs. {s.totalPaid.toLocaleString()}
                    </td>
                    <td className="py-3 px-4 text-right font-bold text-amber-600">
                      {s.balanceDue > 0 ? `Rs. ${s.balanceDue.toLocaleString()}` : 'Nil'}
                    </td>
                    <td className="py-3 px-4">
                      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        {s.status}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-right" onClick={(e) => e.stopPropagation()}>
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => navigate(`/students/${s.id}`)}
                          className="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-200 text-[11px] font-medium rounded transition"
                        >
                          Profile
                        </button>
                        <button
                          onClick={() => handleDelete(s.id, s.fullName)}
                          className="px-2 py-1 bg-red-500/10 hover:bg-red-500 text-red-600 hover:text-white text-[11px] font-medium rounded transition"
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination Bar */}
        {totalPages > 1 && (
          <div className="p-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
            <span>
              Page {pageNumber} of {totalPages} ({totalCount.toLocaleString()} total matching students)
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={pageNumber <= 1}
                onClick={() => setPageNumber((p) => Math.max(1, p - 1))}
                className="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 rounded disabled:opacity-40 flex items-center gap-1"
              >
                <ChevronLeft className="w-3.5 h-3.5" /> Previous
              </button>
              <button
                disabled={pageNumber >= totalPages}
                onClick={() => setPageNumber((p) => Math.min(totalPages, p + 1))}
                className="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 rounded disabled:opacity-40 flex items-center gap-1"
              >
                Next <ChevronRight className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* 5. Print Preview Modal */}
      {isPrintPreviewOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white text-slate-900 rounded-2xl shadow-2xl max-w-4xl w-full max-h-[92vh] overflow-y-auto flex flex-col p-6 animate-in zoom-in-95">
            {/* Modal Controls Bar */}
            <div className="no-print flex items-center justify-between border-b pb-3 mb-4">
              <div className="flex items-center gap-2">
                <Printer className="w-5 h-5 text-blue-600" />
                <h3 className="font-bold text-sm text-slate-900">Document Print Preview</h3>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => api.triggerPrint()}
                  className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-lg shadow-sm transition flex items-center gap-1.5"
                >
                  <Printer className="w-4 h-4" />
                  <span>Print Document</span>
                </button>
                <button
                  onClick={() => setIsPrintPreviewOpen(false)}
                  className="p-1.5 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-700 transition"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* Printable Content Area */}
            <div className="p-4 border rounded-xl bg-white space-y-4 text-xs font-sans">
              {/* Institution Header */}
              <div className="flex items-center justify-between border-b pb-3">
                <div className="flex items-center gap-3">
                  <img
                    src={collegeLogo}
                    alt="ICT Logo"
                    className="w-14 h-14 rounded-lg object-contain border p-0.5"
                  />
                  <div>
                    <h1 className="text-base font-extrabold tracking-tight text-slate-900 leading-tight">
                      ISLAMIA COLLEGE OF TECHNOLOGY
                    </h1>
                    <p className="text-[10px] text-slate-600">
                      Matta Swat, Khyber Pakhtunkhwa • Affiliated with KPK Trade Testing Board (TTB)
                    </p>
                    <p className="text-[9px] text-slate-500">
                      Phone: +92 946 795000 • Web: www.ictswat.edu.pk • Management Information System
                    </p>
                  </div>
                </div>
                <div className="text-right text-[10px] text-slate-600 leading-tight">
                  <div className="font-bold text-xs text-blue-700 uppercase">{printPreviewTitle}</div>
                  <div><strong>Printed:</strong> {new Date().toLocaleString()}</div>
                  <div><strong>Operator:</strong> {user?.fullName || 'Administrator'}</div>
                </div>
              </div>

              {/* Scope & Financial Summary */}
              <div className="grid grid-cols-4 gap-2 text-center text-[10px] bg-slate-50 p-2 rounded-lg border">
                <div>
                  <span className="text-slate-500 block">Total Students</span>
                  <strong className="text-xs text-slate-900">{students.length}</strong>
                </div>
                <div>
                  <span className="text-slate-500 block">Agreed Tuition</span>
                  <strong className="text-xs text-slate-900">Rs. {totalAgreedSum.toLocaleString()}</strong>
                </div>
                <div>
                  <span className="text-slate-500 block text-emerald-600">Income Collected</span>
                  <strong className="text-xs text-emerald-700">Rs. {totalPaidSum.toLocaleString()}</strong>
                </div>
                <div>
                  <span className="text-slate-500 block text-amber-600">Outstanding Dues</span>
                  <strong className="text-xs text-amber-700">Rs. {totalDueSum.toLocaleString()}</strong>
                </div>
              </div>

              {/* Roster Table */}
              <table className="w-full text-left text-[10px] border-collapse border border-slate-300">
                <thead className="bg-slate-100 font-semibold border-b border-slate-300">
                  <tr>
                    <th className="p-1.5 border border-slate-300">#</th>
                    <th className="p-1.5 border border-slate-300">Reg No</th>
                    <th className="p-1.5 border border-slate-300">Student Name</th>
                    <th className="p-1.5 border border-slate-300">Father Name</th>
                    <th className="p-1.5 border border-slate-300">Program / Batch</th>
                    <th className="p-1.5 border border-slate-300">Contact</th>
                    <th className="p-1.5 border border-slate-300 text-right">Agreed</th>
                    <th className="p-1.5 border border-slate-300 text-right">Paid</th>
                    <th className="p-1.5 border border-slate-300 text-right">Balance</th>
                  </tr>
                </thead>
                <tbody>
                  {students.map((s, idx) => (
                    <tr key={s.id} className="border-b border-slate-200">
                      <td className="p-1 border border-slate-300 text-center">{idx + 1}</td>
                      <td className="p-1 border border-slate-300 font-mono font-bold">{s.studentRegNo}<span className="block text-[10px] text-slate-400">UID: {s.id}</span></td>
                      <td className="p-1 border border-slate-300 font-medium">{s.fullName}</td>
                      <td className="p-1 border border-slate-300">{s.fatherName || '—'}</td>
                      <td className="p-1 border border-slate-300">{s.currentCourse || '—'} ({s.currentBatch || '—'})</td>
                      <td className="p-1 border border-slate-300 font-mono">{s.contactNumber || '—'}</td>
                      <td className="p-1 border border-slate-300 text-right">Rs. {s.totalAgreedFee.toLocaleString()}</td>
                      <td className="p-1 border border-slate-300 text-right text-emerald-700 font-medium">Rs. {s.totalPaid.toLocaleString()}</td>
                      <td className="p-1 border border-slate-300 text-right font-bold text-amber-700">
                        {s.balanceDue > 0 ? `Rs. ${s.balanceDue.toLocaleString()}` : '0'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {/* Institutional Signatures */}
              <div className="pt-8 flex justify-between text-[11px] text-slate-700">
                <div className="border-t border-slate-400 pt-1 text-center w-48">
                  <strong>Prepared By</strong>
                  <div className="text-[9px] text-slate-500">Cashier / Admission Desk</div>
                </div>
                <div className="border-t border-slate-400 pt-1 text-center w-48">
                  <strong>Verified By</strong>
                  <div className="text-[9px] text-slate-500">Accountant / Registrar</div>
                </div>
                <div className="border-t border-slate-400 pt-1 text-center w-48">
                  <strong>Approved By</strong>
                  <div className="text-[9px] text-slate-500">Director &amp; CEO (ICT)</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* 6. Admission Form Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-xl w-full p-6 max-h-[90vh] overflow-y-auto animate-in zoom-in-95">
            <h2 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              New Student Admission Form
            </h2>
            <p className="text-xs text-slate-500 mb-4">
              Enter demographic profile, guardian details, and course cohort enrollment.
            </p>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleCreate(false);
              }}
              className="space-y-3.5"
            >
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Registration No (automatic preview)
                  </label>
                  <input
                    type="text"
                    required
                    value={formData.studentRegNo}
                    readOnly aria-label="Automatic registration number preview"
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white font-mono font-bold focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                  {formErrors.studentRegNo && (
                    <p className="text-[10px] text-red-500 mt-0.5">{formErrors.studentRegNo}</p>
                  )}
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Student Full Name *
                  </label>
                  <input
                    type="text"
                    required
                    value={formData.fullName}
                    onChange={(e) => setFormData({ ...formData, fullName: e.target.value })}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                  {formErrors.fullName && (
                    <p className="text-[10px] text-red-500 mt-0.5">{formErrors.fullName}</p>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Father's Name
                  </label>
                  <input
                    type="text"
                    value={formData.fatherName}
                    onChange={(e) => setFormData({ ...formData, fatherName: e.target.value })}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Primary Phone Contact *
                  </label>
                  <input
                    type="text"
                    required
                    value={formData.contactNumber}
                    maxLength={14} inputMode="tel" onChange={(e) => setFormData({ ...formData, contactNumber: formatPhone(e.target.value) })}
                    placeholder="+92-3001234567"
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                  />
                  {formErrors.contactNumber && (
                    <p className="text-[10px] text-red-500 mt-0.5">{formErrors.contactNumber}</p>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    CNIC / B-Form Number
                  </label>
                  <input
                    type="text"
                    value={formData.cnic}
                    maxLength={15} inputMode="numeric" onChange={(e) => setFormData({ ...formData, cnic: formatCnic(e.target.value) })}
                    placeholder="15302-XXXXXXX-X"
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono"
                  />
                </div>

                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                      Assign Academic Batch *
                    </label>
                    <button
                      type="button"
                      onClick={() => setIsInlineBatchOpen(!isInlineBatchOpen)}
                      className="text-[10px] text-sky-600 dark:text-sky-400 font-bold hover:underline"
                    >
                      {isInlineBatchOpen ? 'Close New Batch' : '+ New Batch'}
                    </button>
                  </div>
                  <select
                    value={formData.batchId}
                    onChange={(e) => {
                      const bid = parseInt(e.target.value, 10);
                      const selected = batches.find((b) => b.id === bid);
                      const cFee = courses.find((c) => c.id === selected?.courseId)?.standardFee ?? 0;
                      setFormData({
                        ...formData,
                        batchId: bid,
                        agreedFee: cFee
                      });
                      api.getNextRegistrationNo(courses.find(c => c.id === selected?.courseId)?.courseCode || 'DIT').then(preview => {
                        setFormData(f => f.batchId === bid ? { ...f, studentRegNo: preview, rollNumber: preview.split('-').pop() || '' } : f);
                      }).catch(err => addToast('error', err.message));
                    }}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold"
                  >
                    {batches.filter(b => b.status.toLowerCase() === 'active').map((b) => (
                      <option key={b.id} value={b.id}>
                        {b.batchCode} ({b.batchName})
                      </option>
                    ))}
                  </select>

                  {formErrors.cnic && <p className="text-red-600">{formErrors.cnic}</p>}
                  {/* Inline Quick Batch Creator */}
                  {isInlineBatchOpen && (
                    <div className="mt-2 p-3 bg-sky-50 dark:bg-slate-800/80 border border-sky-200 dark:border-slate-700 rounded-lg space-y-2 text-xs animate-in zoom-in-95">
                      <div className="font-bold text-[11px] text-sky-900 dark:text-sky-300">
                        Create New Academic Cohort
                      </div>
                      <select
                        value={newBatchData.courseId}
                        onChange={(e) => {
                          const cid = parseInt(e.target.value, 10);
                          const sel = courses.find((c) => c.id === cid);
                          const prefix = sel?.courseCode || 'BATCH';
                          setNewBatchData({
                            ...newBatchData,
                            courseId: cid,
                            batchCode: `${prefix}-${new Date().getFullYear()}-B1`,
                            batchName: `${sel?.courseName || ''} - Cohort ${new Date().getFullYear()}`
                          });
                        }}
                        className="w-full p-1.5 text-[11px] bg-white dark:bg-slate-900 border rounded"
                      >
                        <option value={0}>Select Parent Course...</option>
                        {courses.map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.courseName}
                          </option>
                        ))}
                      </select>
                      <input
                        type="text"
                        placeholder="Batch Code (e.g. DIT-2027-B1)"
                        value={newBatchData.batchCode}
                        onChange={(e) => setNewBatchData({ ...newBatchData, batchCode: e.target.value.toUpperCase() })}
                        className="w-full p-1.5 text-[11px] bg-white dark:bg-slate-900 border rounded font-mono font-bold"
                      />
                      <input
                        type="text"
                        placeholder="Batch Title (e.g. Morning Cohort)"
                        value={newBatchData.batchName}
                        onChange={(e) => setNewBatchData({ ...newBatchData, batchName: e.target.value })}
                        className="w-full p-1.5 text-[11px] bg-white dark:bg-slate-900 border rounded"
                      />
                      <div className="flex justify-end gap-1.5 pt-1">
                        <button
                          type="button"
                          onClick={() => setIsInlineBatchOpen(false)}
                          className="px-2 py-1 text-[10px] text-slate-500 hover:bg-slate-100 rounded"
                        >
                          Cancel
                        </button>
                        <button
                          type="button"
                          disabled={savingBatch} onClick={handleInlineCreateBatch}
                          className="px-2.5 py-1 text-[10px] bg-sky-600 hover:bg-sky-700 text-white font-bold rounded shadow-2xs"
                        >
                          Save &amp; Select
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              </div>

              <div className="p-3 rounded-lg bg-sky-50 text-sky-900 text-xs">
                Registration and roll number are assigned automatically when saved.
                Course tuition: Rs. {formData.agreedFee.toLocaleString()} (assigned in Courses).
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Permanent / Residential Address
                </label>
                <textarea
                  rows={2}
                  value={formData.address}
                  onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                  placeholder="Village, Tehsil, District Swat..."
                  className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  disabled={savingStudent} onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  disabled={savingStudent} onClick={() => handleCreate(true)}
                  className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-lg"
                >
                  Save &amp; Enroll Another
                </button>
                <button
                  disabled={savingStudent} type="submit"
                  className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm"
                >
                  Submit Admission
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
