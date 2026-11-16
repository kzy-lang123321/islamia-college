import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { BookOpen, Search, Plus, Layers, Users, ChevronRight, Trash2, CheckCircle2 } from 'lucide-react';
import { api } from '../../services/api';
import { CourseListItem, PagedResult } from '../../types';
import { useAppStore } from '../../services/store';

export const CoursesList: React.FC = () => {
  const navigate = useNavigate();
  const { addToast } = useAppStore();
  const [courses, setCourses] = useState<CourseListItem[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [totalPages, setTotalPages] = useState(1);
  const [pageNumber, setPageNumber] = useState(1);
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('All');
  const [loading, setLoading] = useState(true);
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [isAddBatchModalOpen, setIsAddBatchModalOpen] = useState(false);

  const [batchCourses, setBatchCourses] = useState<CourseListItem[]>([]);
  const [savingBatch, setSavingBatch] = useState(false);

  // Batch Form State
  const [batchFormData, setBatchFormData] = useState({
    batchCode: '',
    batchName: '',
    courseId: 0,
    startDate: new Date().toISOString().split('T')[0]
  });
  const [batchFormErrors, setBatchFormErrors] = useState<Record<string, string>>({});

  // Form State
  const [formData, setFormData] = useState({
    courseCode: '',
    courseName: '',
    category: 'Information Technology',
    duration: '1 Year',
    standardFee: 18000,
    ttbExamFee: 2500,
    description: ''
  });
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    loadCourses();
  }, [search, categoryFilter, pageNumber]);

  const loadCourses = async () => {
    try {
      setLoading(true);
      const res: PagedResult<CourseListItem> = await api.getCoursesPaged({
        search: search.trim() || undefined,
        category: categoryFilter !== 'All' ? categoryFilter : undefined,
        pageNumber,
        pageSize: 15
      });
      setCourses(res.items);
      setTotalCount(res.totalCount);
      setTotalPages(res.totalPages);
    } catch (err: any) {
      addToast('error', err.message || 'Failed to load courses');
    } finally {
      setLoading(false);
    }
  };

  const validateForm = () => {
    const errors: Record<string, string> = {};

    if (!formData.courseName.trim()) errors.courseName = 'Course Title is required.';
    if (formData.standardFee < 0) errors.standardFee = 'Fee cannot be negative.';
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCreateCourse = async (saveAndNew: boolean = false) => {
    if (!validateForm()) return;

    try {
      await api.createCourse(formData);
      addToast('success', `Course '${formData.courseName}' registered successfully!`);
      if (saveAndNew) {
        setFormData({
          courseCode: '',
          courseName: '',
          category: formData.category,
          duration: formData.duration,
          standardFee: 18000,
          ttbExamFee: 2500,
          description: ''
        });
        setFormErrors({});
      } else {
        setIsAddModalOpen(false);
      }
      loadCourses();
    } catch (err: any) {
      addToast('error', err.message || 'Failed to create course');
    }
  };

  const handleCreateBatch = async (startAdmissionsImmediately: boolean = false) => {
    if (savingBatch) return;
    const errs: Record<string, string> = {};
    if (!batchFormData.batchCode.trim()) errs.batchCode = 'Batch code is required (e.g. DIT-2027-B1).';
    if (!batchFormData.batchName.trim()) errs.batchName = 'Batch name is required.';
    if (batchFormData.courseId <= 0) errs.courseId = 'Please select a parent course.';
    setBatchFormErrors(errs);
    if (Object.keys(errs).length > 0) return;

    try {
      setSavingBatch(true);
      const res = await api.createBatch({
        batchCode: batchFormData.batchCode.trim().toUpperCase(),
        batchName: batchFormData.batchName.trim(),
        courseId: batchFormData.courseId,
        startDate: batchFormData.startDate
      });
      addToast('success', `New Batch '${res.batchCode}' created successfully!`);
      setIsAddBatchModalOpen(false);

      if (startAdmissionsImmediately) {
        navigate(`/students?selectedBatchId=${res.id}&openAdmission=true`);
      } else {
        loadCourses();
      }
    } catch (err: any) {
      addToast('error', err.message || 'Failed to create batch');
    } finally {
      setSavingBatch(false);
    }
  };

  const handleArchive = async (id: number, name: string) => {
    if (!window.confirm(`Delete course '${name}' from the catalog and stop new admissions? Existing student and financial records are retained.`)) return;
    try {
      await api.archiveCourse(id);
      addToast('success', `Course '${name}' deleted successfully.`);
      loadCourses();
    } catch (err: any) {
      addToast('error', err.message);
    }
  };

  const categories = [
    'All',
    'Information Technology',
    'Language & Communication',
    'Vocational & Technical',
    'Management & Commerce',
    'Tuition & Academics'
  ];

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <BookOpen className="w-5 h-5 text-ict-orange" />
            Academic Courses & Disciplines
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Registered diplomas, technical certifications, standard fee structures & batches ({totalCount} total)
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => {
              setBatchFormErrors({});
              api.getAllCourses(true).then(all => {
                setBatchCourses(all);
                setBatchFormData({ batchCode: '', batchName: '', courseId: 0,
                  startDate: new Date().toLocaleDateString('en-CA') });
                setIsAddBatchModalOpen(true);
              }).catch(err => addToast('error', err.message || 'Unable to load courses'));
            }}
            className="px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
          >
            <Plus className="w-4 h-4" />
            <span>+ Add New Batch</span>
          </button>
          <button
            onClick={() => navigate('/enrollments')}
            className="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
          >
            <Users className="w-4 h-4" />
            <span>Class Enrollments</span>
          </button>
          <button
            onClick={() => {
              setFormErrors({});
              setIsAddModalOpen(true);
            }}
            className="px-3.5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
          >
            <Plus className="w-4 h-4" />
            <span>Add Course</span>
          </button>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-3 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div className="relative md:col-span-2">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPageNumber(1);
            }}
            placeholder="Search course by title or code (e.g. DIT, Office Automation, Spoken English)..."
            className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
          />
        </div>

        <div>
          <input type="text" list="course-categories" value={categoryFilter === 'All' ? '' : categoryFilter}
            onChange={e => { setCategoryFilter(e.target.value || 'All'); setPageNumber(1); }}
            placeholder="All categories / type category" className="w-full py-1.5 px-3 text-xs border border-slate-200 rounded-lg bg-transparent" />
          <datalist id="course-categories">{categories.filter(c => c !== 'All').map(c => <option key={c} value={c} />)}</datalist>
        </div>
      </div>

      {/* Courses Grid / Table */}
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-xs text-slate-400">Loading courses catalog...</div>
        ) : courses.length === 0 ? (
          <div className="p-12 text-center text-xs text-slate-500">
            No academic courses match your search or filter.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-3 px-4">Course Title</th>
                  <th className="py-3 px-4">Category</th>
                  <th className="py-3 px-4">Duration</th>
                  <th className="py-3 px-4 text-right">Standard Fee</th>
                  <th className="py-3 px-4 text-right">TTB Exam Fee</th>
                  <th className="py-3 px-4 text-center">Batches</th>
                  <th className="py-3 px-4 text-center">Enrolled</th>
                  <th className="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                {courses.map((c) => (
                  <tr
                    key={c.id}
                    className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition cursor-pointer"
                    onClick={() => navigate(`/courses/${c.id}`)}
                  >
                    <td className="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                      {c.courseName}
                    </td>
                    <td className="py-3 px-4">
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                        {c.category}
                      </span>
                    </td>
                    <td className="py-3 px-4">{c.duration}</td>
                    <td className="py-3 px-4 text-right font-medium">Rs. {c.standardFee.toLocaleString()}</td>
                    <td className="py-3 px-4 text-right text-slate-500">Rs. {c.ttbExamFee.toLocaleString()}</td>
                    <td className="py-3 px-4 text-center">
                      <span className="inline-flex items-center gap-1 font-semibold text-slate-800 dark:text-slate-200">
                        <Layers className="w-3 h-3 text-slate-400" />
                        {c.totalBatches}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-center font-semibold text-slate-900 dark:text-white">
                      {c.totalStudentsEnrolled}
                    </td>
                    <td className="py-3 px-4 text-right" onClick={(e) => e.stopPropagation()}>
                      <div className="flex items-center justify-end gap-1.5">
                        <button
                          onClick={() => navigate(`/courses/${c.id}`)}
                          className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-500 hover:text-slate-900 dark:hover:text-white transition"
                          title="View Course Details"
                        >
                          <ChevronRight className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleArchive(c.id, c.courseName)}
                          className="p-1 hover:bg-red-50 dark:hover:bg-red-950/30 rounded text-slate-400 hover:text-red-600 transition"
                          title="Delete Course"
                        >
                          <Trash2 className="w-3.5 h-3.5" /><span className="ml-1">Delete</span>
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
              Showing page {pageNumber} of {totalPages} ({totalCount} total courses)
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={pageNumber <= 1}
                onClick={() => setPageNumber((p) => Math.max(1, p - 1))}
                className="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 rounded disabled:opacity-40"
              >
                Previous
              </button>
              <button
                disabled={pageNumber >= totalPages}
                onClick={() => setPageNumber((p) => Math.min(totalPages, p + 1))}
                className="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 rounded disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Add Course Modal */}
      {isAddModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-lg w-full p-6 animate-in zoom-in-95">
            <h2 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              Add New Academic Course
            </h2>
            <p className="text-xs text-slate-500 mb-4">
              Define the diploma/certificate curriculum, duration, and standardized fee policy.
            </p>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleCreateCourse(false);
              }}
              className="space-y-3.5"
            >
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Category *
                  </label>
                  <input type="text" required maxLength={100} value={formData.category}
                    onChange={e => setFormData({ ...formData, category: e.target.value })}
                    placeholder="Type a category" className="w-full border rounded-lg px-3 py-2 bg-transparent" />
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Course Title *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Diploma in Information Technology (DIT)"
                  value={formData.courseName}
                  onChange={(e) => setFormData({ ...formData, courseName: e.target.value })}
                  className={`w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border ${
                    formErrors.courseName ? 'border-red-500' : 'border-slate-200 dark:border-slate-700'
                  } rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange`}
                />
                {formErrors.courseName && (
                  <p className="text-[10px] text-red-500 mt-0.5">{formErrors.courseName}</p>
                )}
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Duration
                  </label>
                  <input
                    type="text"
                    placeholder="e.g. 1 Year, 6 Months"
                    value={formData.duration}
                    onChange={(e) => setFormData({ ...formData, duration: e.target.value })}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
                  />
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Standard Fee (Rs.) *
                  </label>
                  <input
                    type="number"
                    min="0"
                    step="500"
                    value={formData.standardFee}
                    onChange={(e) => setFormData({ ...formData, standardFee: parseFloat(e.target.value) || 0 })}
                    className={`w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border ${
                      formErrors.standardFee ? 'border-red-500' : 'border-slate-200 dark:border-slate-700'
                    } rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange`}
                  />
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    TTB Exam Fee (Rs.)
                  </label>
                  <input
                    type="number"
                    min="0"
                    step="500"
                    value={formData.ttbExamFee}
                    onChange={(e) => setFormData({ ...formData, ttbExamFee: parseFloat(e.target.value) || 0 })}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
                  />
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Description / Syllabus Outline
                </label>
                <textarea
                  rows={2}
                  placeholder="Key topics, prerequisites, career pathways..."
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
                />
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsAddModalOpen(false)}
                  className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={() => handleCreateCourse(true)}
                  className="px-3.5 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold rounded-lg transition"
                >
                  Save &amp; New
                </button>
                <button
                  type="submit"
                  className="px-4 py-1.5 text-xs bg-blue-700 hover:bg-blue-800 text-white font-semibold rounded-lg shadow-sm transition"
                >
                  Save Course
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Add New Batch Modal */}
      {isAddBatchModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 animate-in zoom-in-95">
            <h2 className="text-base font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
              <Layers className="w-5 h-5 text-sky-600" />
              <span>Create New Academic Batch</span>
            </h2>
            <p className="text-xs text-slate-500 mb-4">
              Add a new cohort for academic admissions and student enrollment.
            </p>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleCreateBatch(false);
              }}
              className="space-y-3.5"
            >
              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Select Parent Course *
                </label>
                <select
                  required
                  value={batchFormData.courseId}
                  onChange={(e) => {
                    const cid = parseInt(e.target.value, 10);
                    const selectedC = batchCourses.find((c) => c.id === cid);
                    const prefix = selectedC?.courseCode || 'BATCH';
                    setBatchFormData({
                      ...batchFormData,
                      courseId: cid,
                      batchCode: `${prefix}-${new Date().getFullYear()}-B1`,
                      batchName: `${selectedC?.courseName || ''} - Cohort ${new Date().getFullYear()}`
                    });
                  }}
                  className={`w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border ${
                    batchFormErrors.courseId ? 'border-red-500' : 'border-slate-200 dark:border-slate-700'
                  } rounded-lg text-slate-900 dark:text-white font-semibold`}
                >
                  <option value={0}>Choose Course...</option>
                  {batchCourses.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.courseName} ({c.courseCode})
                    </option>
                  ))}
                </select>
                {batchFormErrors.courseId && (
                  <p className="text-[10px] text-red-500 mt-0.5">{batchFormErrors.courseId}</p>
                )}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Batch Code *
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. DIT-2027-B1"
                    value={batchFormData.batchCode}
                    onChange={(e) => setBatchFormData({ ...batchFormData, batchCode: e.target.value.toUpperCase() })}
                    className={`w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border ${
                      batchFormErrors.batchCode ? 'border-red-500' : 'border-slate-200 dark:border-slate-700'
                    } rounded-lg text-slate-900 dark:text-white font-mono font-bold`}
                  />
                  {batchFormErrors.batchCode && (
                    <p className="text-[10px] text-red-500 mt-0.5">{batchFormErrors.batchCode}</p>
                  )}
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Commencement Date
                  </label>
                  <input
                    type="date"
                    value={batchFormData.startDate}
                    onChange={(e) => setBatchFormData({ ...batchFormData, startDate: e.target.value })}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                  />
                </div>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Batch Display Title *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Morning Cohort / Session 2027"
                  value={batchFormData.batchName}
                  onChange={(e) => setBatchFormData({ ...batchFormData, batchName: e.target.value })}
                  className={`w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border ${
                    batchFormErrors.batchName ? 'border-red-500' : 'border-slate-200 dark:border-slate-700'
                  } rounded-lg text-slate-900 dark:text-white`}
                />
                {batchFormErrors.batchName && (
                  <p className="text-[10px] text-red-500 mt-0.5">{batchFormErrors.batchName}</p>
                )}
              </div>

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  disabled={savingBatch} onClick={() => setIsAddBatchModalOpen(false)}
                  className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
                >
                  Cancel
                </button>
                <button
                  disabled={savingBatch} type="submit"
                  className="px-3.5 py-1.5 text-xs bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-semibold rounded-lg transition"
                >
                  Save Batch
                </button>
                <button
                  type="button"
                  disabled={savingBatch} onClick={() => handleCreateBatch(true)}
                  className="px-4 py-1.5 text-xs bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg shadow-sm transition"
                >
                  Save &amp; Start Admissions
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
