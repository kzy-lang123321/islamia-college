import React, { useState } from 'react';
import { BarChart3, Printer, Download, Calendar } from 'lucide-react';
import { api } from '../../services/api';
import { PrintHeader } from '../../components/PrintHeader';
import { PrintSignatures } from '../../components/PrintSignatures';

export const ReportsHub: React.FC = () => {
  const [reportType, setReportType] = useState('fees');
  const [startDate, setStartDate] = useState('2026-10-01');
  const [endDate, setEndDate] = useState('2026-10-28');

  const handlePrint = () => {
    api.triggerPrint();
  };

  return (
    <div className="p-6 space-y-6">
      {/* Universal Print Header */}
      <PrintHeader
        title="Institutional Financial & Operational Report"
        subtitle={`Period: ${startDate} to ${endDate}`}
        filters={{
          'Report Category': 'Comprehensive Fee Collection & Dues',
          'Date Range': `${startDate} to ${endDate}`
        }}
      />

      {/* Screen Toolbar */}
      <div className="no-print flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
            Central Reports & Financial Analytics Hub
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Audit-ready financial reports, fee collections, outstanding dues & board exams statistics
          </p>
        </div>

        <div className="flex gap-2">
          <button
            onClick={handlePrint}
            className="px-4 py-1.5 bg-ict-orange hover:bg-ict-orange-hover text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
          >
            <Printer className="w-4 h-4" />
            <span>Print Official Report</span>
          </button>
        </div>
      </div>

      {/* Sample Financial Summary Matrix */}
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
        <h2 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
          Financial Position Summary (October 2026)
        </h2>

        <table className="w-full text-left text-xs border border-slate-200 dark:border-slate-800">
          <thead className="bg-slate-50 dark:bg-slate-800/60 font-semibold text-slate-600 dark:text-slate-400">
            <tr className="border-b border-slate-200 dark:border-slate-800">
              <th className="p-3">Program / Department</th>
              <th className="p-3 text-right">Agreed Tuition</th>
              <th className="p-3 text-right">Received</th>
              <th className="p-3 text-right">Outstanding Dues</th>
              <th className="p-3 text-center">Recovery %</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
            <tr>
              <td className="p-3 font-medium">Diploma in Information Technology (DIT)</td>
              <td className="p-3 text-right">Rs. 1,850,000</td>
              <td className="p-3 text-right font-bold text-emerald-600">Rs. 1,620,000</td>
              <td className="p-3 text-right font-bold text-red-600">Rs. 230,000</td>
              <td className="p-3 text-center font-bold text-ict-orange">87.5%</td>
            </tr>
            <tr>
              <td className="p-3 font-medium">Certificate in Information Technology (CIT)</td>
              <td className="p-3 text-right">Rs. 720,000</td>
              <td className="p-3 text-right font-bold text-emerald-600">Rs. 640,000</td>
              <td className="p-3 text-right font-bold text-red-600">Rs. 80,000</td>
              <td className="p-3 text-center font-bold text-ict-orange">88.8%</td>
            </tr>
            <tr>
              <td className="p-3 font-medium">English Language Program (ELL)</td>
              <td className="p-3 text-right">Rs. 450,000</td>
              <td className="p-3 text-right font-bold text-emerald-600">Rs. 380,000</td>
              <td className="p-3 text-right font-bold text-red-600">Rs. 70,000</td>
              <td className="p-3 text-center font-bold text-ict-orange">84.4%</td>
            </tr>
          </tbody>
          <tfoot className="bg-slate-50 dark:bg-slate-800/70 font-bold border-t border-slate-300 dark:border-slate-700">
            <tr>
              <td className="p-3">Institutional Total:</td>
              <td className="p-3 text-right">Rs. 3,020,000</td>
              <td className="p-3 text-right text-emerald-600">Rs. 2,640,000</td>
              <td className="p-3 text-right text-red-600">Rs. 380,000</td>
              <td className="p-3 text-center text-ict-orange">87.4%</td>
            </tr>
          </tfoot>
        </table>
      </div>

      {/* Official Signatures (Print-Only) */}
      <PrintSignatures signers={['Accounts Officer', 'Internal Auditor', 'Principal / Director ICT']} />
    </div>
  );
};

