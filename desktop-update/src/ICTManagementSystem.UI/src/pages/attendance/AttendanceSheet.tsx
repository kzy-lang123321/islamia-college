import React, { useState } from 'react';
import { Calendar, Save, AlertCircle, CheckCircle2 } from 'lucide-react';
import { api } from '../../services/api';
import { useAppStore } from '../../services/store';

export const AttendanceSheet: React.FC = () => {
  const { addToast } = useAppStore();
  const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
  const [batchId] = useState(1);

  const [students, setStudents] = useState([
    { studentId: 1, studentRegNo: 'ICT-DIT-411', fullName: 'Muhammad Bilal', rollNumber: '01', status: 'Present' },
    { studentId: 2, studentRegNo: 'ICT-DIT-412', fullName: 'Hamza Khan', rollNumber: '02', status: 'Present' },
    { studentId: 3, studentRegNo: 'ICT-DIT-413', fullName: 'Zeeshan Ahmad', rollNumber: '03', status: 'Absent' },
    { studentId: 4, studentRegNo: 'ICT-DIT-414', fullName: 'Abbas Ali', rollNumber: '04', status: 'Leave' }
  ]);

  const handleStatusChange = (studentId: number, status: 'Present' | 'Absent' | 'Leave') => {
    setStudents(prev => prev.map(s => (s.studentId === studentId ? { ...s, status } : s)));
  };

  const handleSave = async () => {
    try {
      await api.recordAttendance({
        batchId,
        date,
        items: students.map(s => ({ studentId: s.studentId, status: s.status })),
        recordedBy: 'Teacher'
      });
      addToast('success', `Attendance for ${date} recorded successfully.`);
    } catch (err: any) {
      addToast('error', err.message || 'Failed to record attendance');
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
            Class Attendance Management
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Daily roll call, monthly attendance sheets & short-attendance tracking
          </p>
        </div>

        <div className="flex items-center gap-3">
          <input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className="p-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg"
          />
          <button
            onClick={handleSave}
            className="px-4 py-1.5 bg-ict-orange hover:bg-ict-orange-hover text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
          >
            <Save className="w-4 h-4" />
            <span>Save Attendance</span>
          </button>
        </div>
      </div>

      {/* Attendance Table */}
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <table className="w-full text-left text-xs">
          <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
            <tr>
              <th className="p-3">Roll #</th>
              <th className="p-3">Registration #</th>
              <th className="p-3">Student Name</th>
              <th className="p-3 text-center">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
            {students.map((student) => (
              <tr key={student.studentId} className="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                <td className="p-3 font-mono font-bold">{student.rollNumber}</td>
                <td className="p-3 font-semibold text-ict-orange">{student.studentRegNo}</td>
                <td className="p-3 font-medium text-slate-900 dark:text-white">{student.fullName}</td>
                <td className="p-3 text-center">
                  <div className="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 bg-slate-50 dark:bg-slate-800">
                    <button
                      type="button"
                      onClick={() => handleStatusChange(student.studentId, 'Present')}
                      className={`px-3 py-1 rounded text-xs font-bold transition ${
                        student.status === 'Present'
                          ? 'bg-emerald-600 text-white shadow-sm'
                          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                      }`}
                    >
                      Present
                    </button>
                    <button
                      type="button"
                      onClick={() => handleStatusChange(student.studentId, 'Leave')}
                      className={`px-3 py-1 rounded text-xs font-bold transition ${
                        student.status === 'Leave'
                          ? 'bg-amber-500 text-white shadow-sm'
                          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                      }`}
                    >
                      Leave
                    </button>
                    <button
                      type="button"
                      onClick={() => handleStatusChange(student.studentId, 'Absent')}
                      className={`px-3 py-1 rounded text-xs font-bold transition ${
                        student.status === 'Absent'
                          ? 'bg-red-600 text-white shadow-sm'
                          : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                      }`}
                    >
                      Absent
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

