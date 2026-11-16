import React from 'react';
import { PrintableReceipt } from '../types';
import collegeLogo from '../assets/logo.png';

interface DualCopyReceiptProps {
  receipt: PrintableReceipt;
}

export const DualCopyReceipt: React.FC<DualCopyReceiptProps> = ({ receipt }) => {
  const renderSlip = (copyType: 'INSTITUTE COPY' | 'STUDENT COPY') => (
    <div className="flex-1 p-4 border border-slate-300 rounded bg-white text-slate-900 text-[11px] leading-tight">
      {/* Header */}
      <div className="flex items-start justify-between border-b border-slate-300 pb-2 mb-2">
        <div className="flex items-center gap-2">
          <img
            src={collegeLogo}
            alt="ICT Logo"
            className="w-9 h-9 rounded object-contain border border-slate-200 p-0.5"
          />
          <div>
            <h2 className="font-bold text-xs leading-none text-slate-900">{receipt.institutionName}</h2>
            <p className="text-[9px] text-slate-600">{receipt.campusAddress}</p>
            <p className="text-[8px] text-slate-500">{receipt.affiliation}</p>
          </div>
        </div>
        <div className="text-right">
          <span className="inline-block px-1.5 py-0.5 bg-slate-100 border border-slate-300 text-[9px] font-bold tracking-wider rounded">
            {copyType}
          </span>
          <div className="font-bold text-ict-orange mt-1">{receipt.receiptNo}</div>
          <div className="text-[8px] text-slate-500">{receipt.date}</div>
        </div>
      </div>

      {/* Student Details Grid */}
      <div className="grid grid-cols-2 gap-x-4 gap-y-1 py-1 text-[10px]">
        <div><span className="text-slate-500">Reg No:</span> <strong>{receipt.studentRegNo}</strong></div>
        <div><span className="text-slate-500">Roll No:</span> <strong>{receipt.rollNumber || '—'}</strong></div>
        <div className="col-span-2"><span className="text-slate-500">Student:</span> <strong>{receipt.studentName}</strong></div>
        <div className="col-span-2"><span className="text-slate-500">Father's Name:</span> {receipt.fatherName}</div>
        <div className="col-span-2"><span className="text-slate-500">Course / Batch:</span> <strong>{receipt.courseName}</strong> ({receipt.batchCode})</div>
      </div>

      {/* Financial Table */}
      <table className="w-full my-2 border border-slate-300 text-[10px]">
        <tbody>
          <tr className="border-b border-slate-200 bg-slate-50">
            <td className="p-1 text-slate-600">Total Agreed Fee:</td>
            <td className="p-1 text-right font-medium">Rs. {receipt.totalAgreedFee.toLocaleString()}</td>
          </tr>
          <tr className="border-b border-slate-200">
            <td className="p-1 text-slate-600">Previous Amount Paid:</td>
            <td className="p-1 text-right font-medium">Rs. {receipt.previousPaid.toLocaleString()}</td>
          </tr>
          <tr className="border-b border-slate-200 bg-amber-50 font-bold">
            <td className="p-1 text-slate-800">Amount Received Now ({receipt.paymentMethod}):</td>
            <td className="p-1 text-right text-emerald-700">Rs. {receipt.currentPaid.toLocaleString()}</td>
          </tr>
          <tr className="bg-slate-50 font-bold">
            <td className="p-1 text-slate-800">Remaining Balance Dues:</td>
            <td className="p-1 text-right text-red-600">Rs. {receipt.remainingBalance.toLocaleString()}</td>
          </tr>
        </tbody>
      </table>

      {/* Signatures */}
      <div className="mt-4 pt-2 border-t border-slate-200 flex justify-between items-end text-[8px] text-slate-500">
        <div>Cashier: <strong>{receipt.receivedBy}</strong></div>
        <div className="text-center">
          <div className="w-24 border-b border-slate-400 mb-1"></div>
          <div>Authorized Signature & Stamp</div>
        </div>
      </div>
    </div>
  );

  return (
    <div className="print-dual-receipt flex flex-col md:flex-row gap-4 p-2 bg-white">
      {renderSlip('INSTITUTE COPY')}
      <div className="hidden md:flex flex-col items-center justify-center border-l-2 border-dashed border-slate-300 px-1 text-[9px] text-slate-400 select-none">
        ✂ PERFORATION LINE ✂
      </div>
      {renderSlip('STUDENT COPY')}
    </div>
  );
};

