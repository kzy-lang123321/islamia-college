import React, { useEffect, useState } from 'react';
import { History, RotateCcw, RefreshCw } from 'lucide-react';
import { api } from '../../services/api';
import { useAppStore } from '../../services/store';

export type ActivityItem = { id: number; timestampUtc: string; username: string; action: string; description: string; entityName: string; entityId?: string; canUndo: boolean; undone: boolean };
export type ActivityPage = { items: ActivityItem[]; totalCount: number; pageNumber: number; totalPages: number };
export const ActivityHistory: React.FC = () => {
  const { addToast } = useAppStore();
  const [date, setDate] = useState(() => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Karachi', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date()));
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('');
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [data, setData] = useState<ActivityPage>({ items: [], totalCount: 0, pageNumber: 1, totalPages: 0 });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [busyId, setBusyId] = useState<number | null>(null);
  useEffect(() => {
    let cancelled = false;
    setLoading(true); setError('');
    api.getDailyActivity(date, filter, page).then(result => { if (!cancelled) setData(result); })
      .catch(e => { if (!cancelled) { setError(e.message || 'Unable to load activity.'); setData({ items: [], totalCount: 0, pageNumber: page, totalPages: 0 }); } })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [date, filter, page, revision]);
  const undo = async (item: ActivityItem) => {
    if (busyId !== null || !window.confirm(`Undo "${item.description}" for ${item.entityName} ${item.entityId || ''}?\n\nPrevious settings will be restored. Student enrollments and payment records are retained. Newer conflicting changes will block Undo.`)) return;
    setBusyId(item.id);
    try { await api.undoActivity(item.id); addToast('success', 'Change undone. The reversal was added to activity history.'); setRevision(r => r + 1); }
    catch (e: any) { addToast('error', e.message || 'Unable to undo this change.'); }
    finally { setBusyId(null); }
  };
  const latest = data.items.find(item => item.canUndo);
  return <div className="p-6 space-y-5">
    <div className="flex flex-wrap justify-between items-center gap-3"><div>
      <h1 className="text-xl font-bold flex items-center gap-2"><History className="w-5 h-5 text-sky-600" />Daily Activity &amp; Recent Changes</h1>
      <p className="text-xs text-slate-500 mt-1">Activity by date, recorded user, and record. Times shown in Pakistan time.</p>
    </div><div className="flex gap-2">
      <button disabled={loading || busyId !== null} onClick={() => setRevision(r => r + 1)} className="border rounded-lg px-3 py-2 text-xs flex gap-2 items-center"><RefreshCw className="w-4 h-4" />Refresh</button>
      <button disabled={loading || busyId !== null || !latest} onClick={() => latest && void undo(latest)} className="bg-sky-600 text-white rounded-lg px-3 py-2 text-xs flex gap-2 items-center disabled:opacity-50"><RotateCcw className="w-4 h-4" />Undo latest listed change</button>
    </div></div>
    <form className="bg-white dark:bg-slate-900 border border-sky-200 rounded-xl p-4 flex flex-wrap items-end gap-3 text-xs" onSubmit={e => { e.preventDefault(); setFilter(search.trim()); setPage(1); }}>
      <label>Activity date<input aria-label="Activity date" type="date" required value={date} onChange={e => { if (e.target.value) { setDate(e.target.value); setPage(1); } }} className="block border rounded-lg p-2 mt-1 bg-transparent" /></label>
      <label className="flex-1">Search user, action or record<input aria-label="Search activity" maxLength={100} value={search} onChange={e => setSearch(e.target.value)} placeholder="Username, students, fees, courses…" className="block w-full border rounded-lg p-2 mt-1 bg-transparent" /></label>
      <button className="px-4 py-2 rounded-lg bg-sky-600 text-white">Search history</button>
    </form>
    <p className="text-xs text-slate-500">Undo supports newly recorded course edits, course/student removals, and batch activation changes. Payments use receipt cancellation. Admissions, imports and other changes require confirmation before saving.</p>
    {error && <p role="alert" className="text-red-600 text-xs">{error}</p>}
    <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-auto">
      <table className="w-full text-xs text-left"><thead className="bg-sky-50 dark:bg-slate-800"><tr>{['Time (Pakistan)', 'User', 'Activity', 'Record', 'Action'].map(title => <th key={title} className="px-4 py-3">{title}</th>)}</tr></thead>
        <tbody>{loading ? <tr><td colSpan={5} className="p-8 text-center text-slate-500">Loading activity…</td></tr> : data.items.length === 0 ? <tr><td colSpan={5} className="p-8 text-center text-slate-500">No recorded activity for this date and filter.</td></tr> : data.items.map(item => <tr key={item.id} className="border-t border-slate-100 dark:border-slate-800">
          <td className="px-4 py-3 whitespace-nowrap">{new Date(item.timestampUtc.endsWith('Z') ? item.timestampUtc : item.timestampUtc + 'Z').toLocaleTimeString('en-PK', { timeZone: 'Asia/Karachi' })}</td>
          <td className="px-4 py-3">{item.username}</td><td className="px-4 py-3 font-medium">{item.description}</td><td className="px-4 py-3">{item.entityName === 'AuditLog' ? 'Activity' : item.entityName} {item.entityId || ''}</td>
          <td className="px-4 py-3">{item.undone ? <span className="text-slate-500">Undone</span> : item.canUndo ? <button disabled={busyId !== null} onClick={() => void undo(item)} className="text-sky-700 dark:text-sky-300 font-semibold flex items-center gap-1"><RotateCcw className="w-3 h-3" />{busyId === item.id ? 'Undoing…' : 'Undo'}</button> : <span className="text-slate-400">—</span>}</td>
        </tr>)}</tbody></table>
    </div>
    <div className="flex items-center justify-between text-xs"><span>{data.totalCount} activities · Page {page} of {Math.max(1, data.totalPages)}</span><div className="flex gap-2"><button disabled={loading || page <= 1} onClick={() => setPage(p => p - 1)} className="border px-3 py-1 rounded disabled:opacity-50">Previous</button><button disabled={loading || page >= data.totalPages} onClick={() => setPage(p => p + 1)} className="border px-3 py-1 rounded disabled:opacity-50">Next</button></div></div>
  </div>;
};
