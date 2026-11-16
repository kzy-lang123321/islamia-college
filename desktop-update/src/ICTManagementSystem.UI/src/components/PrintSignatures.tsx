import React from 'react';

interface PrintSignaturesProps {
  signers?: string[];
}

export const PrintSignatures: React.FC<PrintSignaturesProps> = ({
  signers = ['Prepared By (Registrar)', 'Verified / Accounts Officer', 'Principal / Director ICT']
}) => {
  return (
    <div className="print-signatures-block print-only mt-8 pt-4 border-t border-slate-300">
      <div className="grid grid-cols-3 gap-6 text-center">
        {signers.map((title, idx) => (
          <div key={idx} className="flex flex-col items-center">
            <div className="w-40 border-b border-slate-700 mb-2"></div>
            <div className="text-[10px] font-bold text-slate-900">{title}</div>
            <div className="text-[8px] text-slate-500 uppercase tracking-wider">Signature & Official Stamp</div>
          </div>
        ))}
      </div>
      <div className="mt-4 pt-2 text-center text-[8px] text-slate-400 border-t border-slate-200">
        This is an official computer-generated document from Islamia College of Technology Management System. Valid only with authorized signatures and institutional stamp.
      </div>
    </div>
  );
};

