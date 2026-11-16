import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Cloud, CloudOff, RefreshCw, Sun, Moon, ShieldCheck, AlertCircle, Search } from 'lucide-react';
import { useAppStore } from '../services/store';
import { api } from '../services/api';
import { SyncStatus } from '../types';

export const TopBar: React.FC = () => {
  const navigate = useNavigate();
  const { theme, toggleTheme, addToast } = useAppStore();
  const [globalSearch, setGlobalSearch] = useState('');
  const [syncStatus, setSyncStatus] = useState<SyncStatus>({
    state: 'Synced',
    pendingCount: 0,
    lastSyncTime: 'Checking...'
  });
  const [syncing, setSyncing] = useState(false);

  const fetchSyncStatus = async () => {
    try {
      const status = await api.getSyncStatus();
      if (status) setSyncStatus(status);
    } catch {
      // offline fallback
    }
  };

  useEffect(() => {
    fetchSyncStatus();
    const interval = setInterval(fetchSyncStatus, 30000); // 30 sec polling
    return () => clearInterval(interval);
  }, []);

  const handleGlobalSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (!globalSearch.trim()) return;
    navigate(`/students?search=${encodeURIComponent(globalSearch.trim())}`);
  };

  const handleSyncNow = async () => {
    setSyncing(true);
    try {
      await api.triggerSyncNow();
      addToast('info', 'Google Drive sync process triggered.');
      setTimeout(fetchSyncStatus, 2000);
    } catch (err: any) {
      addToast('error', `Sync failed: ${err.message}`);
    } finally {
      setSyncing(false);
    }
  };

  const getSyncBadge = () => {
    switch (syncStatus.state) {
      case 'Synced':
        return {
          icon: ShieldCheck,
          text: `Drive Synced (${syncStatus.lastSyncTime || 'Recent'})`,
          classes: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30'
        };
      case 'Pending':
        return {
          icon: RefreshCw,
          text: `Pending Upload (${syncStatus.pendingCount} file${syncStatus.pendingCount === 1 ? '' : 's'})`,
          classes: 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30'
        };
      case 'Offline':
        return {
          icon: CloudOff,
          text: 'Drive Offline (Queued)',
          classes: 'bg-slate-500/15 text-slate-700 dark:text-slate-400 border-slate-500/30'
        };
      case 'Uploading':
        return {
          icon: Cloud,
          text: 'Uploading to Drive...',
          classes: 'bg-blue-500/15 text-blue-700 dark:text-blue-400 border-blue-500/30 animate-pulse'
        };
      default:
        return {
          icon: AlertCircle,
          text: 'Needs Sign-In',
          classes: 'bg-red-500/15 text-red-700 dark:text-red-400 border-red-500/30'
        };
    }
  };

  const badge = getSyncBadge();
  const BadgeIcon = badge.icon;

  return (
    <header className="no-print h-14 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-6 flex items-center justify-between shadow-sm flex-shrink-0">
      <div className="flex items-center gap-3">
        <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">Institutional MIS</span>
        <span className="text-slate-300 dark:text-slate-700">•</span>
        <span className="text-xs font-bold text-ict-navy dark:text-slate-200">Islamia College of Technology, Matta</span>
      </div>

      {/* Global Quick Search Bar */}
      <form onSubmit={handleGlobalSearch} className="hidden md:flex items-center relative w-72 lg:w-96">
        <Search className="w-3.5 h-3.5 absolute left-3 text-slate-400" />
        <input
          type="text"
          value={globalSearch}
          onChange={(e) => setGlobalSearch(e.target.value)}
          placeholder="Quick search student, reg # or roll #..."
          className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-ict-orange focus:border-ict-orange transition"
        />
      </form>

      <div className="flex items-center gap-3">
        {/* Google Drive Sync Indicator */}
        <div
          className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border ${badge.classes} cursor-pointer transition`}
          onClick={handleSyncNow}
          title="Click to trigger immediate Google Drive backup sync"
        >
          <BadgeIcon className={`w-3.5 h-3.5 ${syncing ? 'animate-spin' : ''}`} />
          <span>{badge.text}</span>
        </div>

        {/* Sync Now button */}
        <button
          onClick={handleSyncNow}
          disabled={syncing}
          className="p-1.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
          title="Sync Now"
        >
          <RefreshCw className={`w-4 h-4 ${syncing ? 'animate-spin' : ''}`} />
        </button>

        {/* Theme toggle */}
        <button
          onClick={toggleTheme}
          className="p-1.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
          title="Toggle Light/Dark Theme"
        >
          {theme === 'light' ? <Moon className="w-4 h-4" /> : <Sun className="w-4 h-4" />}
        </button>
      </div>
    </header>
  );
};

