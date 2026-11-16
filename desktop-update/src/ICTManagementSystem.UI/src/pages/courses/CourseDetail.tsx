import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { BookOpen, Layers, Users, ArrowLeft, Plus, DollarSign, Calendar } from 'lucide-react';
import { api } from '../../services/api';
import { CourseDetail as CourseDetailType } from '../../types';
import { useAppStore } from '../../services/store';

export const CourseDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { addToast } = useAppStore();
  const [course, setCourse] = useState<CourseDetailType | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'batches' | 'subjects' | 'students'>('batches');

  // Modals
  const [isAddBatchOpen, setIsAddBatchOpen] = useState(false);
  const [isAddSubjectOpen, setIsAddSubjectOpen] = useState(false);

  // Batch Form
  const [batchForm, setBatchForm] = useState({
    batchCode: '',
    batchName: '',
    startDate: new Date().toISOString().split('T')[0]
  });

  // Subject Form
  const [subjectForm, setSubjectForm] = useState({
    subjectCode: '',
    subjectName: '',
    totalMarks: 100,
    passingMarks: 40
  });

  useEffect(() => {
    if (id) loadCourse(parseInt(id, 10));
  }, [id]);

  const loadCourse = async (courseId: number) => {
    try {
      setLoading(true);
      const res = await api.getCourseById(courseId);
      setCourse(res);
      // Pre-fill batch code suggestion
      if (res) {
        setBatchForm((f) => ({
          ...f,
          batchCode: `${res.courseCode}-${new Date().getFullYear()}-A`,
          batchName: `${res.courseName} - Batch ${new Date().getFullYear()}`
        }));
        setSubjectForm((s) => ({
          ...s,
          subjectCode: `${res.courseCode}-101`
        }));
      }
    } catch (err: any) {
      addToast('error', err.message || 'Failed to load course details');
    } finally {
      setLoading(false);
    }
  };

  const handleCreateBatch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!course) return;
    try {
      await api.createBatch({
        ...batchForm,
        courseId: course.id
      });
      addToast('success', `Batch '${batchForm.batchCode}' added successfully!`);
      setIsAddBatchOpen(false);
      loadCourse(course.id);
    } catch (err: any) {
      addToast('error', err.message || 'Failed to create batch');
    }
  };

  const handleCreateSubject = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!course) return;
    try {
      await api.createSubject({
        ...subjectForm,
        courseId: course.id
      });
      addToast('success', `Subject '${subjectForm.subjectName}' added to curriculum!`);
      setIsAddSubjectOpen(false);
      loadCourse(course.id);
    } catch (err: any) {
      addToast('error', err.message || 'Failed to create subject');
    }
  };

  if (loading) {
    return <div className="p-12 text-center text-xs text-slate-400">Loading course profile...</div>;
  }

  if (!course) {
    return (
      <div className="p-12 text-center text-xs text-red-500">
        Course not found.{' '}
        <button onClick={() => navigate('/courses')} className="underline text-ict-orange ml-2">
          Back to list
        </button>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      {/* Top Navigation */}
      <button
        onClick={() => navigate('/courses')}
        className="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 dark:hover:text-white transition"
      >
        <ArrowLeft className="w-4 h-4" />
        <span>Back to Courses</span>
      </button>

      {/* Hero Card */}
      <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div className="space-y-1.5">
          <div className="flex items-center gap-2">
            <span className="font-mono text-sm px-2.5 py-0.5 rounded font-bold bg-ict-orange/10 text-ict-orange border border-ict-orange/20">
              {course.courseCode}
            </span>
            <span className="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-medium">
              {course.category}
            </span>
          </div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
            {course.courseName}
          </h1>
          <p className="text-xs text-slate-500 max-w-2xl">
            {course.description || 'Comprehensive professional training curriculum tailored for employment readiness.'}
          </p>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 border-t md:border-t-0 md:border-l border-slate-200 dark:border-slate-800 pt-4 md:pt-0 md:pl-6 text-xs">
          <div>
            <div className="text-slate-400">Standard Fee</div>
            <div className="font-bold text-slate-900 dark:text-white mt-0.5">
              Rs. {course.standardFee.toLocaleString()}
            </div>
          </div>
          <div>
            <div className="text-slate-400">TTB Exam Fee</div>
            <div className="font-bold text-slate-900 dark:text-white mt-0.5">
              Rs. {course.ttbExamFee.toLocaleString()}
            </div>
          </div>
          <div>
            <div className="text-slate-400">Collected</div>
            <div className="font-bold text-emerald-600 mt-0.5">
              Rs. {course.totalFeesCollected.toLocaleString()}
            </div>
          </div>
          <div>
            <div className="text-slate-400">Outstanding Dues</div>
            <div className="font-bold text-amber-600 mt-0.5">
              Rs. {course.totalOutstandingDues.toLocaleString()}
            </div>
          </div>
        </div>
      </div>

      <form className="no-print p-4 rounded-xl bg-sky-50 border border-sky-200 flex flex-wrap gap-3 items-end text-xs" onSubmit={async e => {
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
      {/* Tabs */}
      <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800">
        <div className="flex items-center gap-1">
          <button
            onClick={() => setActiveTab('batches')}
            className={`px-4 py-2.5 text-xs font-semibold border-b-2 transition flex items-center gap-1.5 ${
              activeTab === 'batches'
                ? 'border-ict-orange text-ict-orange'
                : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <Layers className="w-4 h-4" />
            <span>Academic Batches ({course.batches.length})</span>
          </button>
          <button
            onClick={() => setActiveTab('subjects')}
            className={`px-4 py-2.5 text-xs font-semibold border-b-2 transition flex items-center gap-1.5 ${
              activeTab === 'subjects'
                ? 'border-ict-orange text-ict-orange'
                : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <BookOpen className="w-4 h-4" />
            <span>Curriculum Subjects ({course.subjects.length})</span>
          </button>
          <button
            onClick={() => setActiveTab('students')}
            className={`px-4 py-2.5 text-xs font-semibold border-b-2 transition flex items-center gap-1.5 ${
              activeTab === 'students'
                ? 'border-ict-orange text-ict-orange'
                : 'border-transparent text-slate-500 hover:text-slate-900 dark:hover:text-white'
            }`}
          >
            <Users className="w-4 h-4" />
            <span>Enrolled Students ({course.enrollments.length})</span>
          </button>
        </div>

        <div>
          {activeTab === 'batches' && (
            <button
              onClick={() => setIsAddBatchOpen(true)}
              className="px-3 py-1.5 bg-ict-orange hover:bg-ict-orange-hover text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>New Batch</span>
            </button>
          )}
          {activeTab === 'subjects' && (
            <button
              onClick={() => setIsAddSubjectOpen(true)}
              className="px-3 py-1.5 bg-ict-orange hover:bg-ict-orange-hover text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Add Subject</span>
            </button>
          )}
        </div>
      </div>

      {/* Tab Panels */}
      {activeTab === 'batches' && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {course.batches.length === 0 ? (
            <div className="md:col-span-3 p-8 text-center text-xs text-slate-400 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
              No batches registered yet for this course. Click "New Batch" to launch one.
            </div>
          ) : (
            course.batches.map((b) => (
              <div
                key={b.id}
                className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3"
              >
                <div className="flex items-center justify-between">
                  <span className="font-mono text-xs font-bold text-ict-orange">{b.batchCode}</span>
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600">
                    {b.status}
                  </span>
                  <button className="no-print text-xs text-sky-700 underline" onClick={async () => {
                    try { await api.setBatchActive(b.id, b.status.toLowerCase() !== 'active'); await loadCourse(course.id); }
                    catch (err: any) { addToast('error', err.message); }
                  }}>{b.status.toLowerCase() === 'active' ? 'Deactivate' : 'Activate'}</button>
                </div>
                <div>
                  <h3 className="text-xs font-bold text-slate-900 dark:text-white">{b.batchName}</h3>
                  <p className="text-[11px] text-slate-400 flex items-center gap-1 mt-1">
                    <Calendar className="w-3 h-3" /> Started: {b.startDate || 'N/A'}
                  </p>
                </div>
                <div className="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
                  <span>Enrolled Students</span>
                  <span className="font-bold text-slate-900 dark:text-white">{b.activeStudentsCount}</span>
                </div>
              </div>
            ))
          )}
        </div>
      )}

      {activeTab === 'subjects' && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="py-2.5 px-4">Subject Code</th>
                <th className="py-2.5 px-4">Subject Title</th>
                <th className="py-2.5 px-4 text-center">Total Marks</th>
                <th className="py-2.5 px-4 text-center">Passing Marks</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
              {course.subjects.map((s) => (
                <tr key={s.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td className="py-2.5 px-4 font-mono font-semibold text-ict-orange">{s.subjectCode}</td>
                  <td className="py-2.5 px-4 font-medium text-slate-900 dark:text-white">{s.subjectName}</td>
                  <td className="py-2.5 px-4 text-center">{s.totalMarks}</td>
                  <td className="py-2.5 px-4 text-center">{s.passingMarks}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {activeTab === 'students' && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-2.5 px-4">Reg No</th>
                  <th className="py-2.5 px-4">Student Name</th>
                  <th className="py-2.5 px-4">Batch</th>
                  <th className="py-2.5 px-4">Roll No</th>
                  <th className="py-2.5 px-4 text-right">Agreed Fee</th>
                  <th className="py-2.5 px-4 text-right">Paid</th>
                  <th className="py-2.5 px-4 text-right">Dues</th>
                  <th className="py-2.5 px-4 text-center">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                {course.enrollments.map((e) => (
                  <tr
                    key={e.id}
                    className="hover:bg-slate-50 dark:hover:bg-slate-800/40 cursor-pointer"
                    onClick={() => navigate(`/students/${e.studentId}`)}
                  >
                    <td className="py-2.5 px-4 font-mono font-bold text-ict-orange">{e.studentRegNo}</td>
                    <td className="py-2.5 px-4 font-semibold text-slate-900 dark:text-white">{e.studentName}</td>
                    <td className="py-2.5 px-4 font-mono text-[11px]">{e.batchCode}</td>
                    <td className="py-2.5 px-4 font-mono text-[11px]">{e.rollNumber || '—'}</td>
                    <td className="py-2.5 px-4 text-right font-medium">Rs. {e.totalAgreedFee.toLocaleString()}</td>
                    <td className="py-2.5 px-4 text-right text-emerald-600 font-medium">Rs. {e.totalPaid.toLocaleString()}</td>
                    <td className="py-2.5 px-4 text-right font-bold text-amber-600">
                      {e.balanceDue > 0 ? `Rs. ${e.balanceDue.toLocaleString()}` : 'Nil'}
                    </td>
                    <td className="py-2.5 px-4 text-center">
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600">
                        {e.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Add Batch Modal */}
      {isAddBatchOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 animate-in zoom-in-95">
            <h2 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              Create New Batch
            </h2>
            <p className="text-xs text-slate-500 mb-4">
              Add a student cohort for {course.courseName}.
            </p>
            <form onSubmit={handleCreateBatch} className="space-y-3">
              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Batch Code *
                </label>
                <input
                  type="text"
                  required
                  value={batchForm.batchCode}
                  onChange={(e) => setBatchForm({ ...batchForm, batchCode: e.target.value.toUpperCase() })}
                  className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
                />
              </div>
              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Batch Title *
                </label>
                <input
                  type="text"
                  required
                  value={batchForm.batchName}
                  onChange={(e) => setBatchForm({ ...batchForm, batchName: e.target.value })}
                  className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
                />
              </div>
              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Commencement Date
                </label>
                <input
                  type="date"
                  value={batchForm.startDate}
                  onChange={(e) => setBatchForm({ ...batchForm, startDate: e.target.value })}
                  className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
                />
              </div>
              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsAddBatchOpen(false)}
                  className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300 hover:bg-slate-100 rounded-lg"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-1.5 text-xs bg-ict-orange hover:bg-ict-orange-hover text-white font-semibold rounded-lg shadow-sm"
                >
                  Create Batch
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Add Subject Modal */}
      {isAddSubjectOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 animate-in zoom-in-95">
            <h2 className="text-base font-bold text-slate-900 dark:text-white mb-1">
              Add Subject to Course
            </h2>
            <p className="text-xs text-slate-500 mb-4">
              Add a paper/module to {course.courseName} curriculum.
            </p>
            <form onSubmit={handleCreateSubject} className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Subject Code *
                  </label>
                  <input
                    type="text"
                    required
                    value={subjectForm.subjectCode}
                    onChange={(e) => setSubjectForm({ ...subjectForm, subjectCode: e.target.value.toUpperCase() })}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Subject Title *
                  </label>
                  <input
                    type="text"
                    required
                    value={subjectForm.subjectName}
                    onChange={(e) => setSubjectForm({ ...subjectForm, subjectName: e.target.value })}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Total Marks
                  </label>
                  <input
                    type="number"
                    min="10"
                    value={subjectForm.totalMarks}
                    onChange={(e) => setSubjectForm({ ...subjectForm, totalMarks: parseFloat(e.target.value) || 100 })}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Passing Marks
                  </label>
                  <input
                    type="number"
                    min="1"
                    value={subjectForm.passingMarks}
                    onChange={(e) => setSubjectForm({ ...subjectForm, passingMarks: parseFloat(e.target.value) || 40 })}
                    className="w-full px-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                  />
                </div>
              </div>
              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsAddSubjectOpen(false)}
                  className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300 hover:bg-slate-100 rounded-lg"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-1.5 text-xs bg-ict-orange hover:bg-ict-orange-hover text-white font-semibold rounded-lg shadow-sm"
                >
                  Add Subject
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
