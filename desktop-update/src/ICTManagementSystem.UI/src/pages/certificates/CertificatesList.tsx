import React, { useState } from 'react';
import { Award, Plus, Printer, QrCode } from 'lucide-react';
import { api } from '../../services/api';
import { useAppStore } from '../../services/store';
import { Certificate } from '../../types';
import { PrintHeader } from '../../components/PrintHeader';
import { PrintSignatures } from '../../components/PrintSignatures';

export const CertificatesList: React.FC = () => {
  const { addToast } = useAppStore();
  const [certificates, setCertificates] = useState<Certificate[]>([
    {
      id: 1,
      certificateNo: 'CERT-2026-0001',
      studentId: 1,
      studentRegNo: 'ICT-DIT-411',
      studentName: 'Muhammad Bilal',
      fatherName: 'Ahmad Khan',
      certificateType: 'Character',
      issueDate: '2026-10-28',
      courseName: 'Diploma in Information Technology',
      qrCodeData: 'ICT-VERIFY:CERT-2026-0001:ICT-DIT-411:Muhammad Bilal',
      reprintCount: 0,
      issuedBy: 'Principal'
    }
  ]);

  const [activeCert, setActiveCert] = useState<Certificate | null>(null);

  const handlePrint = (cert: Certificate) => {
    setActiveCert(cert);
    setTimeout(() => api.triggerPrint(), 100);
  };

  return (
    <div className="p-6 space-y-6">
      {/* Universal Print Header for Certificates */}
      {activeCert && (
        <PrintHeader
          title={`${activeCert.certificateType} Certificate`}
          subtitle={`Certificate Serial: ${activeCert.certificateNo}`}
        />
      )}

      {/* Screen View */}
      <div className="no-print flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
            Institutional Certificates & Attestations
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Character, Leaving, Bonafide & Course Completion Certificates with QR Verification
          </p>
        </div>
      </div>

      {/* Certificates Table */}
      <div className="no-print bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <table className="w-full text-left text-xs">
          <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
            <tr>
              <th className="p-3">Serial #</th>
              <th className="p-3">Certificate Type</th>
              <th className="p-3">Program / Course</th>
              <th className="p-3">Issue Date</th>
              <th className="p-3">Issued By</th>
              <th className="p-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
            {certificates.map((cert) => (
              <tr key={cert.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                <td className="p-3 font-semibold text-ict-orange">{cert.certificateNo}</td>
                <td className="p-3 font-medium text-slate-900 dark:text-white">{cert.certificateType}</td>
                <td className="p-3 text-slate-500">{cert.courseName}</td>
                <td className="p-3 text-slate-500">{cert.issueDate}</td>
                <td className="p-3 text-slate-500">{cert.issuedBy}</td>
                <td className="p-3 text-right">
                  <button
                    onClick={() => handlePrint(cert)}
                    className="px-3 py-1 bg-ict-orange/10 hover:bg-ict-orange text-ict-orange hover:text-white rounded text-xs font-semibold transition inline-flex items-center gap-1"
                  >
                    <Printer className="w-3.5 h-3.5" />
                    <span>Print Certificate</span>
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Printable Certificate Template */}
      {activeCert && (
        <div className="print-only border-4 border-double border-slate-700 p-8 my-6 text-center text-slate-900 bg-white">
          <h2 className="text-2xl font-bold uppercase tracking-widest text-ict-navy mb-4">
            {activeCert.certificateType} Certificate
          </h2>

          <p className="text-sm leading-relaxed max-w-xl mx-auto my-6 text-justify">
            This is to certify that Mr./Ms. <strong>Muhammad Bilal</strong> S/O <strong>Sher Afzal</strong>,
            Registration Number <strong>ICT-DIT-411</strong>, has been a bonafide student of{' '}
            <strong>Islamia College of Technology, Matta Swat</strong> enrolled in the course{' '}
            <strong>{activeCert.courseName}</strong>. During the tenure at this institution, their conduct and moral character were found to be <strong>Excellent</strong>.
          </p>

          <div className="flex items-center justify-between mt-12 pt-6 border-t border-slate-300">
            <div className="text-left text-xs text-slate-600">
              <div>Certificate #: <strong>{activeCert.certificateNo}</strong></div>
              <div>Issue Date: {activeCert.issueDate}</div>
            </div>
            <div className="text-center">
              <div className="w-36 border-b border-slate-800 mb-1"></div>
              <div className="text-xs font-bold">Principal / Director ICT</div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

