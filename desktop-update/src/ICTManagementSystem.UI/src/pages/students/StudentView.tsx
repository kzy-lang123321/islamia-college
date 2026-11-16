import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Printer, User, Phone, MapPin, Calendar, Award, Receipt } from 'lucide-react';
import { api } from '../../services/api';
import { StudentDetail } from '../../types';
import { PrintHeader } from '../../components/PrintHeader';
import { PrintSignatures } from '../../components/PrintSignatures';

export const StudentView: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [student, setStudent] = useState<StudentDetail | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (id) loadStudent(parseInt(id, 10));
  }, [id]);

  const loadStudent = async (studentId: number) => {
    try {
      const res = await api.getStudentById(studentId);
      setStudent(res);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handlePrint = () => {
    api.triggerPrint();
  };

  if (loading || !student) {
    return <div className="p-8 text-center text-xs text-slate-400">Loading student profile...</div>;
  }

  const enrollments = student.enrollments || [];
  const feeTxs = student.feeTransactions || [];
  const totalAgreed = enrollments.reduce((sum, e) => sum + (e.totalAgreedFee || 0), 0);
  const totalPaid = feeTxs.filter((t) => !t.isCancelled).reduce((sum, t) => sum + t.amountPaid, 0);
  const totalDues = Math.max(0, totalAgreed - totalPaid);

  return (
    <div className="p-6 space-y-6">
      {/* Universal Official Letterhead (Print-Only) */}
      <PrintHeader
        title="Student Profile & Academic Record"
        subtitle={`Registration No: ${student.studentRegNo} • ${student.fullName}`}
        filters={{
          'Reg No': student.studentRegNo,
          UID: String(student.id),
          'Full Name': student.fullName,
          'Father Name': student.fatherName || 'N/A',
          Contact: student.contactNumber || 'N/A',
          CNIC: student.cnic || 'N/A'
        }}
      />

      {/* Screen Toolbar */}
      <div className="no-print flex items-center justify-between">
        <button
          onClick={() => navigate('/students')}
          className="flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-ict-orange transition"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Back to Directory</span>
        </button>

        <button
          onClick={handlePrint}
          className="px-3.5 py-1.5 bg-ict-orange hover:bg-ict-orange-hover text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
        >
          <Printer className="w-4 h-4" />
          <span>Print Official Profile</span>
        </button>
      </div>

      {/* Profile Overview Card */}
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
        <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
          <div className="flex items-center gap-4">
            <div className="w-16 h-16 rounded-xl bg-ict-navy text-white flex items-center justify-center font-bold text-2xl uppercase shadow-md">
              <User className="w-8 h-8" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-lg font-bold text-slate-900 dark:text-white">{student.fullName} (UID: {student.id})</h1>
                <span className="px-2 py-0.5 bg-ict-orange/10 text-ict-orange font-bold text-[10px] rounded">
                  {student.studentRegNo}
                </span>
              </div>
              <p className="text-xs text-slate-500 mt-0.5">
                Father's Name: <strong className="text-slate-700 dark:text-slate-300">{student.fatherName || 'N/A'}</strong>
              </p>
              <div className="flex flex-wrap gap-4 mt-2 text-[11px] text-slate-500">
                {student.contactNumber && (
                  <span className="flex items-center gap-1">
                    <Phone className="w-3.5 h-3.5 text-slate-400" />
                    {student.contactNumber}
                  </span>
                )}
                {student.address && (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-3.5 h-3.5 text-slate-400" />
                    {student.address}
                  </span>
                )}
                {student.cnic && <span>CNIC: {student.cnic}</span>}
              </div>
            </div>
          </div>

          {/* Quick Balance Snapshot */}
          <div className="flex gap-4 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
            <div>
              <p className="text-[10px] text-slate-500">Total Agreed Fee</p>
              <p className="font-bold text-slate-900 dark:text-white">Rs. {totalAgreed.toLocaleString()}</p>
            </div>
            <div className="border-l border-slate-200 dark:border-slate-700 pl-4">
              <p className="text-[10px] text-slate-500">Total Paid</p>
              <p className="font-bold text-emerald-600">Rs. {totalPaid.toLocaleString()}</p>
            </div>
            <div className="border-l border-slate-200 dark:border-slate-700 pl-4">
              <p className="text-[10px] text-slate-500">Remaining Balance</p>
              <p className="font-bold text-red-600">Rs. {totalDues.toLocaleString()}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Enrollments & Batches */}
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
          <Award className="w-4 h-4 text-ict-orange" />
          <h2 className="text-xs font-bold text-slate-900 dark:text-white">Academic Cohorts &amp; Enrolled Programs</h2>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="p-3">Program / Course</th>
                <th className="p-3">Batch Code</th>
                <th className="p-3">Roll Number</th>
                <th className="p-3">Agreed Fee</th>
                <th className="p-3">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {enrollments.length > 0 ? (
                enrollments.map((en) => (
                  <tr key={en.id}>
                    <td className="p-3 font-semibold text-ict-navy dark:text-slate-200">
                      {en.courseName || 'General Program'}
                    </td>
                    <td className="p-3 text-slate-500">{en.batchCode || '—'}</td>
                    <td className="p-3 font-mono font-medium">{en.rollNumber || '—'}</td>
                    <td className="p-3 font-bold">Rs. {en.totalAgreedFee.toLocaleString()}</td>
                    <td className="p-3">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700">
                        {en.status}
                      </span>
                    </td>
                  </tr>
                ))
              ) : (
                <tr><td colSpan={5} className="p-4 text-center text-slate-400">No enrollments recorded.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Fee Receipts & Ledger History */}
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
          <Receipt className="w-4 h-4 text-emerald-600" />
          <h2 className="text-xs font-bold text-slate-900 dark:text-white">Fee Receipts &amp; Payment History</h2>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="p-3">Receipt No</th>
                <th className="p-3">Payment Date</th>
                <th className="p-3">Method</th>
                <th className="p-3 text-right">Amount Paid</th>
                <th className="p-3 text-right">Balance After Payment</th>
                <th className="p-3">Received By</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {feeTxs.length > 0 ? (
                feeTxs.map((tx) => (
                  <tr key={tx.id} className={tx.isCancelled ? 'opacity-40 line-through' : ''}>
                    <td className="p-3 font-mono font-bold text-ict-orange">{tx.receiptNo}</td>
                    <td className="p-3">{tx.paymentDate}</td>
                    <td className="p-3">{tx.paymentMethod}</td>
                    <td className="p-3 text-right font-bold text-emerald-600">Rs. {tx.amountPaid.toLocaleString()}</td>
                    <td className="p-3 text-right font-medium">Rs. {tx.dueAmount.toLocaleString()}</td>
                    <td className="p-3 text-slate-500">{tx.receivedBy}</td>
                  </tr>
                ))
              ) : (
                <tr><td colSpan={6} className="p-4 text-center text-slate-400">No payment receipts issued.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Official Signatures (Print-Only) */}
      <PrintSignatures signers={['Prepared By (Registrar)', 'Accounts Officer', 'Principal / Director ICT']} />
    </div>
  );
};
