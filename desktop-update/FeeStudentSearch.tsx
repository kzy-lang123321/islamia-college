import React, { useEffect, useRef, useState } from 'react';
import { Search } from 'lucide-react';
import { api } from '../services/api';
import { StudentFeeDetails, StudentFeeSuggestion } from '../types';

export const FeeStudentSearch: React.FC<{
  disabled?: boolean;
  onClear: () => void;
  onSelect: (details: StudentFeeDetails) => void;
}> = ({ disabled = false, onClear, onSelect }) => {
  const [query, setQuery] = useState('');
  const [chosenLabel, setChosenLabel] = useState('');
  const [suggestions, setSuggestions] = useState<StudentFeeSuggestion[]>([]);
  const [loading, setLoading] = useState(false);
  const [choosing, setChoosing] = useState(false);
  const [open, setOpen] = useState(false);
  const [highlighted, setHighlighted] = useState(-1);
  const [error, setError] = useState('');
  const [hasSearched, setHasSearched] = useState(false);
  const requestVersion = useRef(0);
  const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
  const focused = useRef(false);
  const valid = (term: string) => term.trim().length >= 2 || /^\d+$/.test(term.trim());

  const search = async (term: string) => {
    if (!valid(term)) return;
    if (debounce.current) clearTimeout(debounce.current);
    const version = ++requestVersion.current;
    setLoading(true); setError(''); setHasSearched(false); setOpen(focused.current);
    try {
      const results = await api.suggestStudentsForFee(term.trim());
      if (version !== requestVersion.current) return;
      setSuggestions(results); setHighlighted(-1); setHasSearched(true);
    } catch (e: any) {
      if (version === requestVersion.current) { setError(e.message || 'Search failed. Try again.'); setSuggestions([]); }
    } finally { if (version === requestVersion.current) setLoading(false); }
  };

  useEffect(() => {
    if (query === chosenLabel || !valid(query)) return;
    debounce.current = setTimeout(() => { void search(query); }, 250);
    return () => { if (debounce.current) clearTimeout(debounce.current); };
  }, [query, chosenLabel]);
  useEffect(() => () => { requestVersion.current++; }, []);

  const choose = async (student: StudentFeeSuggestion) => {
    if (debounce.current) clearTimeout(debounce.current);
    const version = ++requestVersion.current;
    setChoosing(true); setOpen(false); setLoading(false); setError(''); onClear();
    try {
      const details = await api.getStudentFeeDetails(student.enrollmentId);
      if (version !== requestVersion.current) return;
      if (!details) throw new Error('This enrollment is no longer available. Search again.');
      const label = `${student.studentRegNo} — ${student.fullName} (${student.batchCode})`;
      setChosenLabel(label); setQuery(label); setSuggestions([]); onSelect(details);
    } catch (e: any) {
      if (version === requestVersion.current) { setError(e.message || 'Unable to load this enrollment.'); setOpen(true); }
    } finally { if (version === requestVersion.current) setChoosing(false); }
  };

  const change = (value: string) => {
    requestVersion.current++; setQuery(value); setChosenLabel(''); setSuggestions([]);
    setChoosing(false); setLoading(false); setHighlighted(-1); setError(''); setHasSearched(false);
    setOpen(valid(value)); onClear();
  };

  return <div onFocusCapture={() => { focused.current = true; }} onBlur={e => { if (!e.currentTarget.contains(e.relatedTarget as Node)) { focused.current = false; setOpen(false); } }}>
    <form className="flex gap-2" onSubmit={e => { e.preventDefault(); if (!disabled && !choosing && query !== chosenLabel) void search(query); }}>
      <div className="relative flex-1 min-w-0">
        <Search className="pointer-events-none w-4 h-4 absolute left-3 top-4 text-slate-400" />
        <input type="text" role="combobox" aria-label="Find student for fee collection" autoComplete="off"
          aria-autocomplete="list" aria-expanded={open} aria-controls="fee-student-suggestions"
          aria-activedescendant={open && highlighted >= 0 ? `fee-student-option-${highlighted}` : undefined}
          maxLength={query === chosenLabel ? undefined : 100} disabled={disabled} value={query}
          placeholder="Type name, registration, roll number, phone, CNIC or UID…"
          onChange={e => change(e.target.value)} onFocus={() => { if (valid(query) && query !== chosenLabel) setOpen(true); }}
          onKeyDown={e => {
            if (e.key === 'Escape') { setOpen(false); setHighlighted(-1); }
            else if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && suggestions.length) {
              e.preventDefault(); setOpen(true);
              setHighlighted(i => e.key === 'ArrowDown' ? (i + 1) % suggestions.length : (i <= 0 ? suggestions.length - 1 : i - 1));
            } else if (e.key === 'Enter' && open && highlighted >= 0 && suggestions[highlighted]) {
              e.preventDefault(); if (!choosing && !disabled) void choose(suggestions[highlighted]);
            }
          }}
          className="w-full pl-9 pr-3 py-3 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-sky-500" />
        {open && <div className="absolute left-0 right-0 top-full mt-1 z-40 rounded-lg border border-sky-200 bg-white dark:bg-slate-900 shadow-xl max-h-80 overflow-y-auto">
          <ul id="fee-student-suggestions" role="listbox" aria-label="Matching student enrollments">
            {suggestions.map((student, index) => <li key={student.enrollmentId} role="presentation">
              <button type="button" role="option" id={`fee-student-option-${index}`} aria-selected={highlighted === index}
                tabIndex={-1} disabled={disabled || choosing} onMouseDown={e => e.preventDefault()}
                onClick={() => void choose(student)}
                className={`w-full text-left p-3 border-b border-slate-100 dark:border-slate-800 text-xs ${highlighted === index ? 'bg-sky-100 dark:bg-slate-800' : 'hover:bg-sky-50 dark:hover:bg-slate-800'}`}>
                <span className="block font-semibold text-slate-900 dark:text-white">{student.fullName} <span className="text-sky-700 dark:text-sky-300">· {student.studentRegNo}</span></span>
                <span className="block mt-1 text-slate-500">Father: {student.fatherName || '—'} · UID: {student.studentId} · Roll: {student.rollNumber || '—'}</span>
                <span className="block mt-1 text-slate-600 dark:text-slate-300">{student.courseName} · Batch: {student.batchCode}</span>
              </button>
            </li>)}
          </ul>
          {loading && <p role="status" className="p-3 text-xs text-slate-500">Finding students…</p>}
          {!loading && !error && hasSearched && suggestions.length === 0 && <p role="status" className="p-3 text-xs text-slate-500">No matching enrollments. Try another name or registration number.</p>}
        </div>}
      </div>
      <button type="submit" disabled={disabled || loading || choosing || !valid(query) || query === chosenLabel}
        className="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg disabled:opacity-50">
        {choosing ? 'Loading student…' : loading ? 'Searching…' : 'Find students'}
      </button>
    </form>
    <p className="text-[11px] text-slate-500 mt-2">Type at least two characters, or enter a UID. Select the matching student and batch. Use ↑ / ↓ and Enter to choose.</p>
    {error && <p role="alert" className="text-xs text-red-600 mt-2">{error}</p>}
  </div>;
};
