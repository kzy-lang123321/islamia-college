import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Database, ShieldCheck, Upload, Trash2 } from 'lucide-react';
import { api } from '../../services/api';
import { useAppStore } from '../../services/store';
import { loadExcelReader } from '../../components/ExcelImport';
import { parseWorkbookSheets, WorkbookSheetPlan } from '../../services/workbookParser';

type SheetOutcome = { name: string; kind: string; imported: number; linked: number; skipped: number; batchCode?: string; courseId?: number; warnings: string[] };
export type WorkbookOutcome = { hash: string; fileName: string; originalFilePath: string; worksheets: number; sheets: SheetOutcome[]; alreadyImported: boolean };
export type DataOverview = { tables: Record<string, number>; administrator: string };
export type WipeResult = { backupPath: string; deletedRecords: Record<string, number> };
export type ArchiveSummary = { hash: string; fileName: string; worksheets: number; importedAt: string };

export const DataManagement: React.FC = () => {
  const { user, addToast } = useAppStore(); const navigate = useNavigate();
  const [token, setToken] = useState(''), [password, setPassword] = useState('');
  const [tab, setTab] = useState<'workbook' | 'archive' | 'wipe' | 'overview'>('workbook');
  const [overview, setOverview] = useState<DataOverview>(), [revision, setRevision] = useState(0);
  const [busy, setBusy] = useState(false), [error, setError] = useState('');
  const [plans, setPlans] = useState<WorkbookSheetPlan[]>([]), [file, setFile] = useState<{ name: string; base64: string }>();
  const [preview, setPreview] = useState(0), [postPaid, setPostPaid] = useState(true);
  const [result, setResult] = useState<WorkbookOutcome>(), [nextCourseId, setNextCourseId] = useState(0);
  const [confirmation, setConfirmation] = useState(''), [wipePassword, setWipePassword] = useState(''), [wipeResult, setWipeResult] = useState<WipeResult>();
  const [archives, setArchives] = useState<ArchiveSummary[]>([]), [archiveHash, setArchiveHash] = useState('');
  const [manifest, setManifest] = useState<WorkbookOutcome>(), [archiveSheet, setArchiveSheet] = useState(0), [sourceRows, setSourceRows] = useState<string[][]>([]), [sourcePage, setSourcePage] = useState(0);
  useEffect(() => {
    if (!token) return; let cancelled = false;
    api.getDataOverview(token).then(data => { if (!cancelled) setOverview(data); }).catch(e => { if (!cancelled) setError(e.message); });
    if (tab === 'archive') api.getWorkbookArchives(token).then(items => { if (!cancelled) { setArchives(items); if (!archiveHash && items.length) setArchiveHash(items[0].hash); } }).catch(e => { if (!cancelled) setError(e.message); });
    return () => { cancelled = true; };
  }, [token, revision, tab]);
  useEffect(() => {
    if (!token || !archiveHash) return; let cancelled = false;
    setManifest(undefined); setSourceRows([]); setArchiveSheet(0);
    api.getWorkbookManifest(token, archiveHash).then(data => { if (!cancelled) setManifest(data); }).catch(e => { if (!cancelled) setError(e.message); });
    return () => { cancelled = true; };
  }, [token, archiveHash]);
  useEffect(() => {
    if (!token || !manifest) return; let cancelled = false;
    setSourcePage(0); setSourceRows([]);
    api.getArchivedSheet(token, manifest.hash, archiveSheet).then(sheet => { if (!cancelled) setSourceRows(sheet.sourceRows || sheet.SourceRows || []); }).catch(e => { if (!cancelled) setError(e.message); });
    return () => { cancelled = true; };
  }, [token, manifest, archiveSheet]);
  if (user?.role !== 'Admin') return <p className="p-8 text-red-600">Administrator access is required.</p>;
  const unlock = async (e: React.FormEvent) => {
    e.preventDefault(); setBusy(true); setError('');
    try { const access = await api.authorizeDataManagement(user.id, password); setToken(access); setPassword(''); }
    catch (e: any) { setError(e.message); } finally { setBusy(false); }
  };
  const loadFile = async (input?: File) => {
    if (!input) return; setBusy(true); setError(''); setResult(undefined); setPlans([]); setFile(undefined);
    try {
      if (input.size > 15 * 1024 * 1024) throw new Error('Choose a workbook under 15 MB.');
      const X = await loadExcelReader();
      const book = X.read(await input.arrayBuffer(), { type: 'array', sheetRows: 20001, cellDates: false });
      const parsed = parseWorkbookSheets(X, book);
      if (parsed.length > 200) throw new Error('Import up to 200 worksheets at a time.');
      const base64 = await new Promise<string>((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result).split(',')[1]); reader.onerror = () => reject(new Error('Unable to read workbook.')); reader.readAsDataURL(input); });
      const courseDefaults = new Map<string, { standardFee: number; courseName: string }>();
      parsed.filter(p => p.kind === 'students').forEach(p => { if (!courseDefaults.has(p.courseCode)) courseDefaults.set(p.courseCode, { standardFee: p.standardFee, courseName: p.courseName }); });
      setPlans(parsed.map(p => p.kind === 'students' ? { ...p, ...courseDefaults.get(p.courseCode) } : p)); setPreview(0); setFile({ name: input.name, base64 });
    } catch (e: any) { setError(e.message); } finally { setBusy(false); }
  };
  const changePlan = (index: number, changes: Partial<WorkbookSheetPlan>) => setPlans(all => all.map((p, i) => {
    if (i === index) return { ...p, ...changes };
    if (p.kind === 'students' && p.courseCode === all[index].courseCode && (changes.standardFee !== undefined || changes.courseName !== undefined))
      return { ...p, ...(changes.standardFee !== undefined ? { standardFee: changes.standardFee } : {}), ...(changes.courseName !== undefined ? { courseName: changes.courseName } : {}) };
    return p;
  }));
  const importAll = async () => {
    if (!file || busy || !plans.length) return;
    if (!window.confirm(`Import all ${plans.length} worksheets from ${file.name}?\n\nRecognized student sheets will create or match courses and batches. New imported batches are closed for new admissions. Every worksheet and the original workbook will be preserved. Review the opening fee amounts before continuing.`)) return;
    setBusy(true); setError('');
    try {
      const outcome = await api.importWorkbook({ adminToken: token, fileName: file.name, fileBase64: file.base64, sheets: plans.map(p => ({ ...p, students: p.students.map(s => postPaid ? s : { ...s, paidAmount: null }) })) });
      setResult(outcome); setNextCourseId(outcome.sheets.find(s => s.courseId)?.courseId || 0); setRevision(r => r + 1);
      addToast(outcome.alreadyImported ? 'info' : 'success', outcome.alreadyImported ? 'This workbook is already imported. No duplicate records were added.' : `All ${outcome.worksheets} worksheets were preserved and processed.`);
    } catch (e: any) { setError(e.message); } finally { setBusy(false); }
  };
  const wipe = async (e: React.FormEvent) => {
    e.preventDefault(); if (busy || confirmation !== 'WIPE COLLEGE DATA') return;
    if (!window.confirm('Clear ALL college records, including students, enrollments, fees, courses, batches, staff and other college data?\n\nA verified database backup will be made first. User accounts, settings and activity history will remain. This operation cannot be undone with the Undo button.')) return;
    setBusy(true); setError('');
    try { const outcome = await api.wipeCollegeData(token, wipePassword, confirmation); setWipeResult(outcome); setWipePassword(''); setConfirmation(''); setToken(''); setOverview(undefined); setPlans([]); setResult(undefined); setArchives([]); setManifest(undefined); addToast('success', 'College records cleared. Safety backup saved.'); }
    catch (e: any) { setError(e.message); } finally { setBusy(false); }
  };
  const previewPlan = plans[preview];
  const courseOptions = result ? [...new Map(result.sheets.filter(s => s.courseId).map(s => [s.courseId!, s])).values()] : [];
  return <div className="p-6 space-y-5 text-xs">
    <h1 className="text-xl font-bold flex items-center gap-2"><Database className="w-5 h-5 text-sky-600" />Administrator Data Management</h1>
    <p className="text-slate-500">Organize an existing multi-sheet workbook, review the original worksheets, or clear college records after a safety backup.</p>
    {error && <p role="alert" className="p-3 bg-red-50 text-red-700 rounded-lg">{error}</p>}
    {wipeResult && <section role="status" className="p-4 border border-emerald-200 bg-emerald-50 rounded-xl space-y-2"><strong>College records cleared. Administrator accounts and activity history were retained.</strong><p>Verified backup: <span className="break-all">{wipeResult.backupPath}</span></p><button className="bg-sky-600 text-white rounded px-4 py-2" onClick={() => window.location.reload()}>Reload application</button></section>}
    {!token ? <form onSubmit={unlock} className="max-w-lg bg-white dark:bg-slate-900 border border-sky-200 rounded-xl p-5 space-y-3"><h2 className="font-bold flex gap-2 items-center"><ShieldCheck className="w-4 h-4" />Unlock administrator controls</h2><p>Enter the password for administrator {user.username || user.fullName}. Access expires after ten minutes.</p><label className="block">Administrator password<input aria-label="Administrator password" type="password" autoComplete="current-password" required value={password} onChange={e => setPassword(e.target.value)} className="block w-full mt-1 border rounded p-2 bg-transparent" /></label><button disabled={busy} className="bg-sky-600 text-white rounded-lg px-4 py-2">{busy ? 'Checking…' : 'Unlock Data Management'}</button></form> : <>
      <nav className="flex flex-wrap gap-2">{(['workbook', 'archive', 'overview', 'wipe'] as const).map(t => <button key={t} disabled={busy} onClick={() => { setTab(t); setError(''); }} className={`rounded-lg px-4 py-2 ${tab === t ? 'bg-sky-600 text-white' : 'bg-white dark:bg-slate-800 border'}`}>{({ workbook: 'Import all worksheets', archive: 'Original worksheet archive', overview: 'Current records', wipe: 'Clear college data' })[t]}</button>)}<button disabled={busy} onClick={() => setToken('')} className="ml-auto underline">Lock controls</button></nav>
      {tab === 'overview' && <section className="bg-white dark:bg-slate-900 border rounded-xl p-4"><h2 className="font-bold mb-3">Current college records</h2><div className="grid grid-cols-2 md:grid-cols-4 gap-2">{Object.entries(overview?.tables || {}).map(([name, count]) => <p key={name} className="p-2 border rounded">{name}: <strong>{count.toLocaleString()}</strong></p>)}</div></section>}
      {tab === 'workbook' && <section className="bg-white dark:bg-slate-900 border border-sky-200 rounded-xl p-4 space-y-4">
        <h2 className="font-bold flex gap-2"><Upload className="w-4 h-4" />Import one workbook with all its tabs</h2>
        <p>Student-batch tabs are recognized automatically. Staff, rent, loans, results and other reference tabs are kept in the worksheet archive. Original values and the original file are preserved. New imported batches are closed; use “Create next admission batch” after importing.</p>
        <input aria-label="Select multi-sheet workbook" type="file" accept=".xls,.xlsx" disabled={busy} onChange={e => { void loadFile(e.target.files?.[0]); e.target.value = ''; }} />
        {plans.length > 0 && <><p><strong>{plans.length} worksheets</strong> · {plans.filter(p => p.kind === 'students').length} student-batch tabs · {plans.filter(p => p.kind === 'students').reduce((n, p) => n + p.students.length, 0)} recognized student rows</p>
          <div className="max-h-80 overflow-auto border rounded-lg"><table className="w-full text-left"><thead className="bg-sky-50 dark:bg-slate-800 sticky top-0"><tr>{['Worksheet', 'Handling', 'Rows', 'Course prefix', 'Course title', 'Batch code', 'New course fee'].map(h => <th className="p-2" key={h}>{h}</th>)}</tr></thead><tbody>{plans.map((p, i) => <tr key={i} className="border-t"><td className="p-2"><button onClick={() => setPreview(i)} className="underline text-sky-700">{p.name}</button></td><td className="p-2"><select aria-label={`Handling ${p.name}`} disabled={busy} value={p.kind} onChange={e => changePlan(i, { kind: e.target.value as WorkbookSheetPlan['kind'] })} className="border rounded p-1 bg-transparent"><option value="students">Student batch</option><option value="reference">Reference archive</option></select></td><td className="p-2">{p.kind === 'students' ? p.students.length : p.sourceRows.length}</td><td className="p-2"><input aria-label={`Course prefix ${p.name}`} disabled={busy || p.kind !== 'students'} maxLength={16} value={p.courseCode} onChange={e => changePlan(i, { courseCode: e.target.value.toUpperCase().replace(/[^A-Z]/g, '') })} className="border rounded p-1 w-24 bg-transparent" /></td><td className="p-2"><input disabled={busy || p.kind !== 'students'} value={p.courseName} onChange={e => changePlan(i, { courseName: e.target.value })} className="border rounded p-1 w-44 bg-transparent" /></td><td className="p-2"><input disabled={busy || p.kind !== 'students'} value={p.batchCode} onChange={e => changePlan(i, { batchCode: e.target.value })} className="border rounded p-1 w-40 bg-transparent" /></td><td className="p-2"><input aria-label={`New course fee ${p.name}`} disabled={busy || p.kind !== 'students'} type="number" min="0" step="0.01" value={p.standardFee} onChange={e => changePlan(i, { standardFee: Number(e.target.value) })} className="border rounded p-1 w-24 bg-transparent" /></td></tr>)}</tbody></table></div>
          <label className="flex gap-2 items-center"><input type="checkbox" disabled={busy} checked={postPaid} onChange={e => setPostPaid(e.target.checked)} />Import recognized paid amounts as undated legacy opening amounts</label>
          <p className="text-slate-500">Existing course fees are preserved. A new course title or fee applies to all its worksheets. Check the fee/paid preview and warnings before importing.</p>
          {previewPlan && <div className="p-3 bg-sky-50 dark:bg-slate-800 rounded-lg space-y-2"><strong>Preview: {previewPlan.name}</strong><p>Header row: {previewPlan.headerRow >= 0 ? previewPlan.headerRow + 1 : 'reference sheet'} · {previewPlan.detectionWarnings.length} balance warnings</p><div className="overflow-auto"><table className="w-full text-left"><thead><tr>{['Source registration', 'Name', 'Father', 'Fee', 'Paid'].map(h => <th className="p-2" key={h}>{h}</th>)}</tr></thead><tbody>{previewPlan.students.slice(0, 5).map((s, i) => <tr key={i}><td className="p-2">{s.registration || 'Automatic'}</td><td className="p-2">{s.fullName}</td><td className="p-2">{s.fatherName}</td><td className="p-2">{s.agreedFee ?? 'Course fee'}</td><td className="p-2">{s.paidAmount ?? '—'}</td></tr>)}</tbody></table></div>{previewPlan.detectionWarnings.slice(0, 5).map((w, i) => <p key={i} className="text-amber-800">{w}</p>)}</div>}
          <button disabled={busy || !!result} onClick={importAll} className="px-4 py-2 rounded-lg bg-sky-600 text-white disabled:opacity-50">{busy ? 'Importing all worksheets…' : `Import all ${plans.length} worksheets`}</button>
        </>}
        {result && <div role="status" className="border rounded-xl p-4 space-y-3"><strong>{result.alreadyImported ? 'Workbook was already imported' : 'Workbook import complete'} · {result.worksheets} worksheets preserved</strong><p>Original file: <span className="break-all">{result.originalFilePath}</span></p><div className="max-h-64 overflow-auto"><table className="w-full text-left"><thead><tr>{['Worksheet', 'New students', 'New enrollments', 'Existing / skipped', 'Warnings'].map(h => <th className="p-2" key={h}>{h}</th>)}</tr></thead><tbody>{result.sheets.map((s, i) => <tr key={i} className="border-t"><td className="p-2">{s.name} {s.kind === 'reference' && '(reference archive)'}</td><td className="p-2">{s.imported}</td><td className="p-2">{s.linked}</td><td className="p-2">{s.skipped}</td><td className="p-2">{s.warnings.length > 0 ? <details><summary>{s.warnings.length} warnings</summary>{s.warnings.map((w, j) => <p key={j} className="py-1 text-amber-800">{w}</p>)}</details> : '0'}</td></tr>)}</tbody></table></div>{courseOptions.length > 0 && <div className="flex gap-2 items-center"><select aria-label="Course for new admission batch" value={nextCourseId} onChange={e => setNextCourseId(Number(e.target.value))} className="border rounded-lg p-2 bg-transparent">{courseOptions.map(s => <option key={s.courseId} value={s.courseId}>{s.batchCode}</option>)}</select><button className="bg-sky-600 text-white rounded-lg px-4 py-2" onClick={() => navigate(`/courses?newBatchCourseId=${nextCourseId}`)}>Create next admission batch</button></div>}</div>}
      </section>}
      {tab === 'archive' && <section className="bg-white dark:bg-slate-900 border rounded-xl p-4 space-y-3"><h2 className="font-bold">Original worksheet archive</h2><div className="flex flex-wrap gap-2"><select aria-label="Archived workbook" value={archiveHash} onChange={e => setArchiveHash(e.target.value)} className="border rounded p-2 bg-transparent"><option value="">Select workbook</option>{archives.map(a => <option key={a.hash} value={a.hash}>{a.fileName} ({a.worksheets} tabs)</option>)}</select>{manifest && <select aria-label="Archived worksheet" value={archiveSheet} onChange={e => setArchiveSheet(Number(e.target.value))} className="border rounded p-2 bg-transparent">{manifest.sheets.map((s, i) => <option key={i} value={i}>{s.name}</option>)}</select>}</div>{manifest && <p className="break-all">Original file: {manifest.originalFilePath}</p>}<div className="overflow-auto max-h-[500px] border rounded"><table className="text-left whitespace-nowrap"><tbody>{sourceRows.slice(sourcePage * 50, sourcePage * 50 + 50).map((r, i) => <tr key={i} className="border-b"><th className="p-2 bg-sky-50 text-slate-500">{sourcePage * 50 + i + 1}</th>{r.map((cell, j) => <td key={j} className="p-2 border-r">{cell}</td>)}</tr>)}</tbody></table></div><div className="flex gap-3 items-center"><button disabled={sourcePage === 0} onClick={() => setSourcePage(p => p - 1)} className="border rounded px-3 py-1">Previous rows</button><span>{sourceRows.length} source rows</span><button disabled={(sourcePage + 1) * 50 >= sourceRows.length} onClick={() => setSourcePage(p => p + 1)} className="border rounded px-3 py-1">Next rows</button></div></section>}
      {tab === 'wipe' && <form onSubmit={wipe} className="max-w-2xl border border-red-200 bg-white dark:bg-slate-900 rounded-xl p-5 space-y-3"><h2 className="font-bold text-red-700 flex gap-2"><Trash2 className="w-4 h-4" />Clear complete college data</h2><p>Clears students, enrollments, fees, courses, batches, examinations, certificates, staff, payroll, expenses, assets, loans and worksheet archive entries. User accounts, settings, numbering counters, activity history and backup files are retained.</p><p>A verified database backup is required before any records are deleted. The wipe cannot be reversed with Undo.</p><p><strong>{Object.values(overview?.tables || {}).reduce((a, b) => a + b, 0).toLocaleString()} college records currently listed.</strong></p><label className="block">Re-enter administrator password<input aria-label="Wipe administrator password" type="password" required autoComplete="current-password" value={wipePassword} onChange={e => setWipePassword(e.target.value)} className="block border rounded p-2 mt-1 w-full bg-transparent" /></label><label className="block">Type WIPE COLLEGE DATA<input aria-label="Wipe confirmation phrase" autoComplete="off" required value={confirmation} onChange={e => setConfirmation(e.target.value)} className="block border rounded p-2 mt-1 w-full bg-transparent font-mono" /></label><button disabled={busy || confirmation !== 'WIPE COLLEGE DATA' || !wipePassword} className="bg-red-600 text-white rounded-lg px-4 py-2 disabled:opacity-50">{busy ? 'Creating safety backup and clearing…' : 'Back up and clear college data'}</button></form>}
    </>}
  </div>;
};
