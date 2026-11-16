import React, { useEffect, useState } from 'react';
import {
  Search,
  CreditCard,
  Printer,
  CheckCircle2,
  Calendar,
  X,
  FileSpreadsheet,
  AlertOctagon,
  Receipt
} from 'lucide-react';
import { api } from '../../services/api';
import { FeeLedgerItem, OutstandingDuesItem, PrintableReceipt, StudentFeeDetails } from '../../types';
import { useAppStore } from '../../services/store';
import { DualCopyReceipt } from '../../components/DualCopyReceipt';

export const FeeCounter: React.FC = () => {
  const { user, addToast } = useAppStore();
  const [activeTab, setActiveTab] = useState<'collect' | 'ledger' | 'dues' | 'daily'>('collect');

  // Step 1: Student Search
  const [searchQuery, setSearchQuery] = useState('');
  const [searching, setSearching] = useState(false);
  const [feeDetails, setFeeDetails] = useState<StudentFeeDetails | null>(null);
  const [nextReceiptNo, setNextReceiptNo] = useState<string>('');

  // Step 2 & 3: Payment Entry
  const [payingAmount, setPayingAmount] = useState<number>(0);
  const [paymentMethod, setPaymentMethod] = useState('Cash');
  const [fineAmount, setFineAmount] = useState<number>(0);
  const [discountAmount, setDiscountAmount] = useState<number>(0);
  const [referenceNo, setReferenceNo] = useState('');
  const [remarks, setRemarks] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Step 6: Receipt Modal
  const [generatedReceipt, setGeneratedReceipt] = useState<PrintableReceipt | null>(null);

  // Ledger & Dues Data
  const [ledger, setLedger] = useState<FeeLedgerItem[]>([]);
  const [duesList, setDuesList] = useState<OutstandingDuesItem[]>([]);
  const [dailySummary, setDailySummary] = useState<Record<string, number>>({});

  useEffect(() => {
    loadNextReceiptNo();
    if (activeTab === 'ledger') loadLedger();
    if (activeTab === 'dues') loadDues();
    if (activeTab === 'daily') loadDaily();
  }, [activeTab]);

  const loadNextReceiptNo = async () => {
    try {
      const nextNo = await api.getNextReceiptNo();
      if (nextNo) setNextReceiptNo(nextNo);
    } catch {
      // fallback
    }
  };

  const loadLedger = async () => {
    try {
      const res = await api.getPaymentLedger();
      setLedger(res || []);
    } catch (err: any) {
      addToast('error', err.message);
    }
  };

  const loadDues = async () => {
    try {
      const res = await api.getOutstandingDues();
      setDuesList(res || []);
    } catch (err: any) {
      addToast('error', err.message);
    }
  };

  const loadDaily = async () => {
    try {
      const res = await api.getDailyCollection();
      setDailySummary(res || {});
    } catch (err: any) {
      addToast('error', err.message);
    }
  };

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!searchQuery.trim()) return;

    setSearching(true);
    setFeeDetails(null);
    try {
      const res = await api.searchStudentForFee(searchQuery);
      if (res) {
        setFeeDetails(res);
        setPayingAmount(res.currentDues);
      } else {
        addToast('info', 'No matching student with active enrollment found.');
      }
    } catch (err: any) {
      addToast('error', err.message || 'Lookup failed');
    } finally {
      setSearching(false);
    }
  };

  const handleConfirmPayment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!feeDetails) return;

    if (payingAmount <= 0) {
      addToast('error', 'Payment amount must be greater than zero.');
      return;
    }

    setSubmitting(true);
    try {
      const res = await api.receivePayment({
        enrollmentId: feeDetails.enrollmentId,
        amountPaid: payingAmount,
        fineAmount,
        discountAmount,
        paymentMethod,
        referenceNo: referenceNo.trim() || undefined,
        remarks: remarks.trim() || undefined,
        receivedBy: user?.fullName || 'Cashier'
      });

      if (res.success && res.receiptData) {
        addToast('success', res.message);
        setGeneratedReceipt(res.receiptData);
        setFeeDetails(null);
        setSearchQuery('');
        setPayingAmount(0);
        setReferenceNo('');
        setRemarks('');
        loadNextReceiptNo();
      } else {
        addToast('error', res.message || 'Payment recording failed.');
      }
    } catch (err: any) {
      addToast('error', err.message || 'Transaction error');
    } finally {
      setSubmitting(false);
    }
  };

  const handlePrintReceipt = () => {
    api.triggerPrint();
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header & Tabs */}
      <div className="no-print flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
            Institutional Fee Collection & Accounts Counter
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            7-Step Sequential Ledger Workflow • Real-time Balance Calculation & Dual-Copy Print
          </p>
        </div>

        <div className="flex gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-semibold">
          <button
            onClick={() => setActiveTab('collect')}
            className={`px-3 py-1.5 rounded-lg transition ${
              activeTab === 'collect'
                ? 'bg-ict-orange text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
            }`}
          >
            Fee Counter
          </button>
          <button
            onClick={() => setActiveTab('ledger')}
            className={`px-3 py-1.5 rounded-lg transition ${
              activeTab === 'ledger'
                ? 'bg-ict-orange text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
            }`}
          >
            Payment Ledger
          </button>
          <button
            onClick={() => setActiveTab('dues')}
            className={`px-3 py-1.5 rounded-lg transition ${
              activeTab === 'dues'
                ? 'bg-ict-orange text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
            }`}
          >
            Outstanding Dues
          </button>
          <button
            onClick={() => setActiveTab('daily')}
            className={`px-3 py-1.5 rounded-lg transition ${
              activeTab === 'daily'
                ? 'bg-ict-orange text-white shadow-sm'
                : 'text-slate-600 dark:text-slate-300 hover:text-slate-900'
            }`}
          >
            Daily Collection
          </button>
        </div>
      </div>

      {/* TAB 1: FEE COUNTER */}
      {activeTab === 'collect' && (
        <div className="no-print space-y-6">
          {/* Step 1: Student Search Form */}
          <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
            <h2 className="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
              Step 1: Select Student for Fee Collection
            </h2>
            <form onSubmit={handleSearch} className="flex gap-2">
              <div className="relative flex-1">
                <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  required
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Enter Student Reg No (e.g. ICT-DIT-411), Roll Number, or Full Name..."
                  className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
                />
              </div>
              <button
                type="submit"
                disabled={searching}
                className="px-5 py-2 bg-ict-orange hover:bg-ict-orange-hover text-white text-xs font-semibold rounded-lg shadow-sm transition"
              >
                {searching ? 'Searching...' : 'Lookup Student'}
              </button>
            </form>
          </div>

          {/* Step 2 to 5: Fee Details & Payment Formulation */}
          {feeDetails && (
            <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
              {/* Student Overview Strip */}
              <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                <div>
                  <div className="flex items-center gap-2">
                    <h2 className="text-sm font-bold text-slate-900 dark:text-white">{feeDetails.fullName}</h2>
                    <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-ict-orange/10 text-ict-orange">
                      {feeDetails.studentRegNo}
                    </span>
                    {nextReceiptNo && (
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                        Voucher: {nextReceiptNo}
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-slate-500 mt-0.5">
                    Father: <strong>{feeDetails.fatherName}</strong> • Program:{' '}
                    <strong>{feeDetails.courseName}</strong> ({feeDetails.batchCode})
                  </p>
                </div>

                <div className="flex gap-4 text-xs">
                  <div>
                    <span className="text-[10px] text-slate-500 block">Total Agreed</span>
                    <strong className="text-slate-900 dark:text-white">Rs. {feeDetails.totalAgreedFee.toLocaleString()}</strong>
                  </div>
                  <div className="border-l border-slate-300 dark:border-slate-700 pl-4">
                    <span className="text-[10px] text-slate-500 block">Total Paid</span>
                    <strong className="text-emerald-600">Rs. {feeDetails.totalPaid.toLocaleString()}</strong>
                  </div>
                  <div className="border-l border-slate-300 dark:border-slate-700 pl-4">
                    <span className="text-[10px] text-slate-500 block">Net Payable Balance</span>
                    <strong className="text-red-600 text-sm">Rs. {feeDetails.currentDues.toLocaleString()}</strong>
                  </div>
                </div>
              </div>

              {/* Payment Entry Form */}
              <form onSubmit={handleConfirmPayment} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                  <div>
                    <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                      Amount Received (Rs.) *
                    </label>
                    <input
                      type="number"
                      required
                      min={1}
                      max={feeDetails.currentDues + fineAmount}
                      value={payingAmount}
                      onChange={(e) => setPayingAmount(parseFloat(e.target.value) || 0)}
                      className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white font-bold text-sm focus:ring-2 focus:ring-ict-orange"
                    />
                    <p className="text-[10px] text-slate-400 mt-1">
                      Remaining after payment: Rs.{' '}
                      {Math.max(0, feeDetails.currentDues + fineAmount - discountAmount - payingAmount).toLocaleString()}
                    </p>
                  </div>

                  <div>
                    <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                      Payment Method
                    </label>
                    <select
                      value={paymentMethod}
                      onChange={(e) => setPaymentMethod(e.target.value)}
                      className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs"
                    >
                      <option value="Cash">Cash Counter</option>
                      <option value="Bank">Bank Deposit / Transfer</option>
                      <option value="Cheque">Bank Cheque</option>
                      <option value="Online">Online / EasyPaisa / JazzCash</option>
                      <option value="Slip">Bank Deposit Slip</option>
                    </select>
                  </div>

                  <div>
                    <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                      Reference / Slip / Cheque No
                    </label>
                    <input
                      type="text"
                      placeholder="Optional reference number"
                      value={referenceNo}
                      onChange={(e) => setReferenceNo(e.target.value)}
                      className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs"
                    />
                  </div>
                </div>

                <div className="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800">
                  <span className="text-[11px] text-slate-500">
                    Rule Enforced: Accidental full-paid flags prevented while balance remains.
                  </span>
                  <button
                    type="submit"
                    disabled={submitting || payingAmount <= 0}
                    className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg shadow-md transition flex items-center gap-2"
                  >
                    <CheckCircle2 className="w-4 h-4" />
                    <span>Confirm & Generate Receipt Voucher</span>
                  </button>
                </div>
              </form>
            </div>
          )}
        </div>
      )}

      {/* TAB 2: PAYMENT LEDGER */}
      {activeTab === 'ledger' && (
        <div className="no-print bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
          <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 className="text-xs font-bold text-slate-900 dark:text-white">Fee Transaction & Receipt Ledger</h2>
            <button onClick={loadLedger} className="text-xs font-semibold text-ict-blue hover:underline">
              Refresh
            </button>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="p-3">Receipt No</th>
                  <th className="p-3">Student Name</th>
                  <th className="p-3">Program / Batch</th>
                  <th className="p-3">Amount Paid</th>
                  <th className="p-3">Remaining Dues</th>
                  <th className="p-3">Method</th>
                  <th className="p-3">Date</th>
                  <th className="p-3">Cashier</th>
                  <th className="p-3">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {ledger.length > 0 ? (
                  ledger.map((tx) => (
                    <tr key={tx.id} className={tx.isCancelled ? 'opacity-50 line-through' : ''}>
                      <td className="p-3 font-semibold text-ict-orange">{tx.receiptNo}</td>
                      <td className="p-3 font-medium text-slate-900 dark:text-white">
                        {tx.studentName || '—'}
                      </td>
                      <td className="p-3 text-slate-500 dark:text-slate-400">
                        {tx.courseName} {tx.batchCode ? `(${tx.batchCode})` : ''}
                      </td>
                      <td className="p-3 font-bold text-emerald-600">Rs. {tx.amountPaid.toLocaleString()}</td>
                      <td className="p-3 text-red-600 font-medium">Rs. {tx.dueAmount.toLocaleString()}</td>
                      <td className="p-3">{tx.paymentMethod}</td>
                      <td className="p-3 text-slate-500">{tx.paymentDate}</td>
                      <td className="p-3 text-slate-500">{tx.receivedBy}</td>
                      <td className="p-3">
                        {tx.isCancelled ? (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">
                            Cancelled
                          </span>
                        ) : (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">
                            Valid
                          </span>
                        )}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr><td colSpan={9} className="p-6 text-center text-slate-400">No records found.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 3: OUTSTANDING DUES */}
      {activeTab === 'dues' && (
        <div className="no-print bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
          <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 className="text-xs font-bold text-slate-900 dark:text-white">Active Outstanding Student Balances</h2>
            <button onClick={loadDues} className="text-xs font-semibold text-ict-blue hover:underline">
              Refresh
            </button>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="p-3">Reg #</th>
                  <th className="p-3">Student Name</th>
                  <th className="p-3">Program / Batch</th>
                  <th className="p-3">Total Agreed</th>
                  <th className="p-3">Total Paid</th>
                  <th className="p-3">Balance Due</th>
                  <th className="p-3">Contact</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {duesList.length > 0 ? (
                  duesList.map((d, i) => (
                    <tr key={i}>
                      <td className="p-3 font-semibold text-ict-orange">{d.studentRegNo}</td>
                      <td className="p-3 font-medium text-slate-900 dark:text-white">
                        {d.studentName}
                        {d.fatherName ? <span className="block text-[10px] text-slate-400">S/O {d.fatherName}</span> : null}
                      </td>
                      <td className="p-3 text-slate-500">
                        {d.courseName} {d.batchCode ? `(${d.batchCode})` : ''}
                      </td>
                      <td className="p-3 font-medium text-slate-700 dark:text-slate-300">
                        Rs. {d.totalAgreedFee.toLocaleString()}
                      </td>
                      <td className="p-3 font-medium text-emerald-600">Rs. {d.totalPaid.toLocaleString()}</td>
                      <td className="p-3 font-bold text-red-600">Rs. {d.balanceDue.toLocaleString()}</td>
                      <td className="p-3 text-slate-500">{d.contactNumber || '—'}</td>
                    </tr>
                  ))
                ) : (
                  <tr><td colSpan={7} className="p-6 text-center text-slate-400">No outstanding dues recorded.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 4: DAILY COLLECTION */}
      {activeTab === 'daily' && (
        <div className="no-print grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <p className="text-xs text-slate-500">Total Today Collection</p>
            <h3 className="text-xl font-bold text-emerald-600 mt-1">
              Rs. {(dailySummary.TotalCollected || 0).toLocaleString()}
            </h3>
          </div>
          <div className="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <p className="text-xs text-slate-500">Cash Counter Received</p>
            <h3 className="text-xl font-bold text-slate-900 dark:text-white mt-1">
              Rs. {(dailySummary.Cash || 0).toLocaleString()}
            </h3>
          </div>
          <div className="p-5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <p className="text-xs text-slate-500">Bank & Online Transfers</p>
            <h3 className="text-xl font-bold text-slate-900 dark:text-white mt-1">
              Rs. {((dailySummary.Bank || 0) + (dailySummary.Online || 0)).toLocaleString()}
            </h3>
          </div>
        </div>
      )}

      {/* RECEIPT POPUP MODAL (Screen & Print) */}
      {generatedReceipt && (
        <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div className="no-print flex items-center justify-between border-b pb-3">
              <div>
                <h2 className="text-sm font-bold text-slate-900">
                  Confirmed Fee Receipt Voucher: {generatedReceipt.receiptNo}
                </h2>
                <p className="text-[11px] text-slate-500">Dual-Copy A4 Print Layout (Institute Copy + Student Copy)</p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={handlePrintReceipt}
                  className="px-4 py-1.5 bg-ict-orange hover:bg-ict-orange-hover text-white font-bold text-xs rounded-lg shadow-sm transition flex items-center gap-1.5"
                >
                  <Printer className="w-4 h-4" />
                  <span>Print Receipt Voucher</span>
                </button>
                <button
                  onClick={() => setGeneratedReceipt(null)}
                  className="p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 transition"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* Dual Copy Receipt Rendering */}
            <DualCopyReceipt receipt={generatedReceipt} />
          </div>
        </div>
      )}
    </div>
  );
};

