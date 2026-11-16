import React, { useState } from 'react';
import { api } from '../services/api';
import { useAppStore } from '../services/store';

type Sheet = { name: string; rows: string[][] };
type ImportResult = { imported: number; skipped: number; errors: string[] };
const studentFields = ['studentRegNo', 'fullName', 'fatherName', 'contactNumber', 'cnic', 'address'];
const marksFields = ['studentRegNo', 'subjectName', 'totalMarks', 'obtainedMarks'];
const labels: Record<string, string> = { studentRegNo: 'Registration No', fullName: 'Student Name', fatherName: 'Father Name', contactNumber: 'Phone', cnic: 'CNIC / B-Form', address: 'Address', subjectName: 'Subject', totalMarks: 'Total Marks', obtainedMarks: 'Obtained Marks' };
const aliases: Record<string, string[]> = { studentRegNo: ['registrationno', 'regno', 'registrationnumber', 'studentregno'], fullName: ['fullname', 'studentname', 'name'], fatherName: ['fathername', 'fathersname'], contactNumber: ['phone', 'contact', 'contactnumber', 'mobileno', 'mobile'], cnic: ['cnic', 'bform', 'cnicno'], address: ['address'], subjectName: ['subject', 'subjectname', 'paper'], totalMarks: ['total', 'totalmarks', 'maximum'], obtainedMarks: ['obtained', 'obtainedmarks', 'marks'] };
const normalize = (s: string) => s.toLowerCase().replace(/[^a-z0-9]/g, '');
let loader: Promise<any> | undefined;
async function excel() {
  const w = window as any;
  if (w.XLSX) return w.XLSX;
  loader ??= new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = './xlsx.full.min.js';
    script.onload = () => resolve(w.XLSX);
    script.onerror = () => { loader = undefined; reject(new Error('Excel reader could not load. Please reinstall the updated application.')); };
    document.head.appendChild(script);
  });
  return loader;
}

export const ExcelImport: React.FC<{ mode: 'students' | 'marks'; batchId: number; semester?: number; session?: string; onComplete?: () => void }> = ({ mode, batchId, semester = 1, session = '', onComplete }) => {
  const [sheets, setSheets] = useState<Sheet[]>([]);
  const [sheetIndex, setSheetIndex] = useState(0);
  const [headerRow, setHeaderRow] = useState(0);
  const [mapping, setMapping] = useState<Record<string, number>>({});
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<ImportResult>();
  const { addToast } = useAppStore();
  const fields = mode === 'students' ? studentFields : marksFields;
  const sheet = sheets[sheetIndex];
  const headers = sheet?.rows[headerRow] ?? [];
  const rows = sheet?.rows.slice(headerRow + 1).filter(row => row.some(cell => cell.trim())) ?? [];
  const required = mode === 'students' ? ['fullName'] : marksFields;
  const ready = batchId > 0 && rows.length > 0 && required.every(f => mapping[f] >= 0);
  const choose = (s: Sheet, index: number, header = 0) => {
    setSheetIndex(index); setHeaderRow(header); setResult(undefined);
    setMapping(Object.fromEntries(fields.map(f => [f, (s.rows[header] ?? []).findIndex(v => aliases[f].includes(normalize(v)))])));
  };
  const load = async (file?: File) => {
    if (!file) return;
    setBusy(true); setResult(undefined); setSheets([]);
    try {
      if (file.size > 15 * 1024 * 1024) throw new Error('Choose a workbook smaller than 15 MB.');
      const xlsx = await excel();
      const book = xlsx.read(await file.arrayBuffer(), { type: 'array', cellDates: false, sheetRows: 10002 });
      const parsed = book.SheetNames.map((name: string) => ({ name, rows: xlsx.utils.sheet_to_json(book.Sheets[name], { header: 1, raw: false, defval: '' }).map((r: any[]) => r.map(v => String(v ?? ''))) }));
      if (!parsed.length) throw new Error('No worksheets found.');
      setSheets(parsed); choose(parsed[0], 0);
    } catch (e: any) { addToast('error', e.message); }
    finally { setBusy(false); }
  };
  const save = async () => {
    if (!ready || busy) return;
    const limit = mode === 'students' ? 5000 : 10000;
    if (rows.length > limit) { addToast('error', `Import up to ${limit} rows at a time.`); return; }
    setBusy(true);
    try {
      const input = rows.map(row => Object.fromEntries(fields.map(f => [f, row[mapping[f]]?.trim() || ''])));
      const payload = mode === 'students' ? input : input.map(r => {
        const number = (v: string) => v.trim() ? Number(v.replace(/,/g, '')) : NaN;
        const totalMarks = number(r.totalMarks), obtainedMarks = number(r.obtainedMarks);
        if (!Number.isFinite(totalMarks) || !Number.isFinite(obtainedMarks)) throw new Error('Every marks row needs numeric total and obtained marks.');
        return { ...r, totalMarks, obtainedMarks };
      });
      const outcome = await api.importAcademic(mode, { batchId, semester, academicSession: session, rows: payload });
      setResult(outcome);
      addToast(outcome.errors.length ? 'info' : 'success', `${outcome.imported} imported; ${outcome.skipped} existing registrations skipped; ${outcome.errors.length} errors.`);
      if (outcome.imported) { setSheets([]); onComplete?.(); }
    } catch (e: any) { addToast('error', e.message); }
    finally { setBusy(false); }
  };
  return <section className="no-print rounded-xl border border-sky-200 bg-white dark:bg-slate-900 p-4 space-y-3 text-xs">
    <h2 className="font-bold text-sky-800 dark:text-sky-300">Import {mode === 'students' ? 'students' : 'exam marks'} from Excel</h2>
    <p className="text-slate-500">Select a batch, upload an existing .xls or .xlsx workbook, choose its tab and map the columns. {mode === 'students' ? 'Blank registration numbers are generated automatically. Existing registrations are skipped.' : 'Use one row per student and subject. Existing marks for this batch, session and semester are updated.'}</p>
    <input disabled={busy} type="file" accept=".xls,.xlsx" onChange={e => { void load(e.target.files?.[0]); e.target.value = ''; }} />
    {sheet && <>
      <div className="flex gap-3"><label>Worksheet <select disabled={busy} className="border rounded p-2 bg-transparent" value={sheetIndex} onChange={e => choose(sheets[Number(e.target.value)], Number(e.target.value))}>{sheets.map((s, i) => <option key={i} value={i}>{s.name}</option>)}</select></label>
      <label>Header row <input disabled={busy} className="border rounded p-2 w-20 bg-transparent" type="number" min="1" max={sheet.rows.length} value={headerRow + 1} onChange={e => choose(sheet, sheetIndex, Math.max(0, Number(e.target.value) - 1))} /></label></div>
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3">{fields.map(f => <label key={f}>{labels[f]}{required.includes(f) ? ' *' : ''}<select disabled={busy} value={mapping[f] ?? -1} onChange={e => setMapping(m => ({ ...m, [f]: Number(e.target.value) }))} className="block w-full border rounded p-2 bg-transparent"><option value={-1}>Not in file</option>{headers.map((h, i) => <option key={i} value={i}>{h || `Column ${i + 1}`}</option>)}</select></label>)}</div>
      <p>{rows.length} data rows. Preview of first 5:</p>
      <div className="overflow-auto"><table className="w-full text-left"><thead><tr>{fields.map(f => <th className="p-2" key={f}>{labels[f]}</th>)}</tr></thead><tbody>{rows.slice(0, 5).map((row, i) => <tr key={i}>{fields.map(f => <td className="p-2 border-t" key={f}>{row[mapping[f]] || '—'}</td>)}</tr>)}</tbody></table></div>
      <button disabled={!ready || busy} onClick={save} className="rounded-lg bg-sky-600 text-white px-4 py-2 disabled:opacity-50">{busy ? 'Importing…' : `Import ${rows.length} rows into selected batch`}</button>
    </>}
    {result && <div role="status"><p>{result.imported} saved · {result.skipped} skipped · {result.errors.length} errors</p><ul className="max-h-48 overflow-auto text-red-600">{result.errors.map((error, i) => <li key={i}>{error}</li>)}</ul></div>}
  </section>;
};
