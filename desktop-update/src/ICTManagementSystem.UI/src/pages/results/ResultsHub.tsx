import React, { useState, useEffect } from 'react';
import { Award, Printer, Plus } from 'lucide-react';
import { api } from '../../services/api';
import { useAppStore } from '../../services/store';
import { ExcelImport } from '../../components/ExcelImport';
import { Batch, CourseListItem, EnrollmentListItem, ResultCard } from '../../types';
import { PrintHeader } from '../../components/PrintHeader';
import { PrintSignatures } from '../../components/PrintSignatures';

export const ResultsHub: React.FC = () => {
  const { addToast } = useAppStore();
  const [selectedExam, setSelectedExam] = useState(0);
  const [studentId, setStudentId] = useState(0);
  const [resultCard, setResultCard] = useState<ResultCard | null>(null);
  const [batches, setBatches] = useState<Batch[]>([]);
  const [courses, setCourses] = useState<CourseListItem[]>([]);
  const [batchId, setBatchId] = useState(0);
  const [semester, setSemester] = useState(1);
  const [session, setSession] = useState(`${new Date().getFullYear()}-${new Date().getFullYear() + 1}`);
  const [exams, setExams] = useState<{ id: number; name: string; academicSession: string; classesIncluded?: string }[]>([]);
  const [students, setStudents] = useState<EnrollmentListItem[]>([]);
  const [loading, setLoading] = useState(false);
  const isDit = courses.find(c => c.id === batches.find(b => b.id === batchId)?.courseId)?.courseCode.startsWith('DIT');
  const loadExams = () => api.getAcademicExams().then(setExams).catch(e => addToast('error', e.message));
  useEffect(() => { Promise.all([api.getBatches(), api.getAllCourses()]).then(([b, c]) => { setBatches(b); setCourses(c); }).catch(e => addToast('error', e.message)); void loadExams(); }, []);
  useEffect(() => {
    setStudentId(0); setResultCard(null); setSelectedExam(0); setStudents([]);
    if (batchId) api.getEnrollments(undefined, batchId).then(setStudents).catch(e => addToast('error', e.message));
  }, [batchId]);
  const availableExams = exams.filter(e => e.classesIncluded === `Batch:${batchId};Semester:${semester}` && e.academicSession === session);
  const viewResult = async () => {
    setLoading(true); setResultCard(null);
    try { const result = await api.getResultCard(selectedExam, studentId); setResultCard(result); if (!result) addToast('info', 'No saved marks found for this student and exam.'); }
    catch (e: any) { addToast('error', e.message); }
    finally { setLoading(false); }
  };

  const handlePrint = () => {
    api.triggerPrint();
  };

  return (
    <div className="p-6 space-y-6">
      {/* Universal Print Header */}
      <PrintHeader
        title="Detailed Marks Certificate (DMC)"
        subtitle={`${resultCard?.examName} • Session: ${resultCard?.academicSession}`}
        filters={{
          'Registration #': resultCard?.studentRegNo || '',
          Student: resultCard?.studentName || '',
          Father: resultCard?.fatherName || '',
          Course: resultCard?.courseName || ''
        }}
      />

      {/* Screen Toolbar */}
      <div className="no-print flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
            Examinations & Academic Results
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Marks entry, HEC standard grading & Printable Detailed Marks Certificate (DMC)
          </p>
        </div>

        <button
          disabled={!resultCard} onClick={handlePrint}
          className="px-4 py-1.5 bg-ict-orange hover:bg-ict-orange-hover text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
        >
          <Printer className="w-4 h-4" />
          <span>Print Result Card / DMC</span>
        </button>
      </div>

      <section className="no-print bg-white dark:bg-slate-900 border border-sky-200 rounded-xl p-4 grid grid-cols-2 md:grid-cols-3 gap-3 text-xs">
        <label>Academic batch<select className="block w-full border rounded p-2 bg-transparent" value={batchId} onChange={e => { setBatchId(Number(e.target.value)); setSemester(1); }}><option value={0}>Choose batch</option>{batches.map(b => <option key={b.id} value={b.id}>{b.batchCode} — {b.batchName}</option>)}</select></label>
        <label>Semester<select className="block w-full border rounded p-2 bg-transparent" value={semester} onChange={e => { setSemester(Number(e.target.value)); setSelectedExam(0); setResultCard(null); }}><option value={1}>Semester 1</option>{isDit && <option value={2}>Semester 2</option>}</select></label>
        <label>Academic session<input maxLength={30} className="block w-full border rounded p-2 bg-transparent" value={session} onChange={e => { setSession(e.target.value); setSelectedExam(0); setResultCard(null); }} /></label>
        <label>Saved examination<select className="block w-full border rounded p-2 bg-transparent" value={selectedExam} onChange={e => { setSelectedExam(Number(e.target.value)); setResultCard(null); }}><option value={0}>Choose examination</option>{availableExams.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}</select></label>
        <label>Student<select className="block w-full border rounded p-2 bg-transparent" value={studentId} onChange={e => { setStudentId(Number(e.target.value)); setResultCard(null); }}><option value={0}>Choose student</option>{students.map(s => <option key={s.id} value={s.studentId}>{s.studentRegNo} — {s.studentName}</option>)}</select></label>
        <button disabled={!selectedExam || !studentId || loading} onClick={viewResult} className="self-end bg-sky-600 text-white rounded-lg px-4 py-2 disabled:opacity-50">{loading ? 'Loading…' : 'View saved result'}</button>
      </section>
      <ExcelImport key={`${batchId}-${semester}-${session}`} mode="marks" batchId={batchId} semester={semester} session={session} onComplete={() => { setResultCard(null); void loadExams(); }} />
      {!resultCard && <p className="text-xs text-slate-500">Select a batch, semester, examination and student to view their saved result.</p>}
      {/* DMC Result Card */}
      {resultCard && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
            <div><span className="text-slate-500 block">Registration #:</span> <strong>{resultCard.studentRegNo}</strong></div>
            <div><span className="text-slate-500 block">Student:</span> <strong>{resultCard.studentName}</strong></div>
            <div><span className="text-slate-500 block">Father:</span> {resultCard.fatherName}</div>
            <div><span className="text-slate-500 block">Session:</span> {resultCard.academicSession}</div>
          </div>

          <table className="w-full text-left text-xs border border-slate-200 dark:border-slate-800">
            <thead className="bg-slate-50 dark:bg-slate-800/60 font-semibold text-slate-600 dark:text-slate-400">
              <tr className="border-b border-slate-200 dark:border-slate-800">
                <th className="p-3">Subject / Paper</th>
                <th className="p-3 text-center">Total Marks</th>
                <th className="p-3 text-center">Obtained Marks</th>
                <th className="p-3 text-center">Grade</th>
                <th className="p-3 text-center">GPA</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {resultCard.subjects.map((sub, i) => (
                <tr key={i} className="hover:bg-slate-50 dark:hover:bg-slate-800/20">
                  <td className="p-3 font-medium text-slate-900 dark:text-white">{sub.subjectName}</td>
                  <td className="p-3 text-center">{sub.totalMarks}</td>
                  <td className="p-3 text-center font-bold">{sub.obtainedMarks}</td>
                  <td className="p-3 text-center font-bold text-ict-orange">{sub.grade}</td>
                  <td className="p-3 text-center font-mono">{sub.gpa.toFixed(1)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot className="bg-slate-50 dark:bg-slate-800/70 font-bold border-t border-slate-300 dark:border-slate-700">
              <tr>
                <td className="p-3">Grand Total:</td>
                <td className="p-3 text-center">{resultCard.grandTotalMarks}</td>
                <td className="p-3 text-center text-emerald-600">{resultCard.grandObtainedMarks}</td>
                <td className="p-3 text-center text-ict-orange">{resultCard.overallGrade}</td>
                <td className="p-3 text-center font-mono">{resultCard.overallGpa.toFixed(2)}</td>
              </tr>
            </tfoot>
          </table>

          <div className="flex flex-wrap items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
            <div>
              <span>Percentage:</span> <strong className="text-sm text-slate-900 dark:text-white ml-1">{resultCard.percentage}%</strong>
            </div>
            <div>
              <span>Overall Grade:</span> <strong className="text-sm text-ict-orange ml-1">{resultCard.overallGrade}</strong>
            </div>
            <div>
              <span>Class Merit Rank:</span> <strong className="text-sm text-emerald-600 ml-1">#{resultCard.classRank || '—'}</strong>
            </div>
          </div>
        </div>
      )}

      {/* Verification Signatures (Print-Only) */}
      <PrintSignatures signers={['Controller of Examinations', 'Academic Dean', 'Principal / Director ICT']} />
    </div>
  );
};

