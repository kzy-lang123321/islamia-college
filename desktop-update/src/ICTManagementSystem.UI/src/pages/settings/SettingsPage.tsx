import React, { useState, useEffect } from 'react';
import {
  Settings,
  Cloud,
  HardDrive,
  FileUp,
  RefreshCw,
  CheckCircle2,
  AlertTriangle,
  ShieldCheck,
  FolderOpen,
  Users,
  UserPlus,
  KeyRound,
  Lock,
  Unlock,
  Trash2,
  Usb,
  Database
} from 'lucide-react';
import { api } from '../../services/api';
import { useAppStore } from '../../services/store';
import { DataImportPreview } from '../../types';

export const SettingsPage: React.FC = () => {
  const { user: currentUser, addToast } = useAppStore();
  const [activeTab, setActiveTab] = useState<'roles' | 'drive' | 'backup' | 'import'>('roles');

  // RBAC / User Roles Management
  const [usersList, setUsersList] = useState<any[]>([]);
  const [loadingUsers, setLoadingUsers] = useState(false);
  const [isAddUserModalOpen, setIsAddUserModalOpen] = useState(false);
  const [newUserData, setNewUserData] = useState({
    username: '',
    fullName: '',
    phone: '',
    email: '',
    password: '',
    role: 'Staff'
  });
  const [resetPassModal, setResetPassModal] = useState<{ isOpen: boolean; userId: number; username: string; newPass: string }>({
    isOpen: false,
    userId: 0,
    username: '',
    newPass: ''
  });

  // Google Drive & Auto-Sync
  const [syncing, setSyncing] = useState(false);

  // Manual Backup & Restore
  const [backupPassword, setBackupPassword] = useState('');
  const [restoreFilePath, setRestoreFilePath] = useState('');
  const [targetDir, setTargetDir] = useState('F:\\ICT_College_Backups');
  const [creatingBackup, setCreatingBackup] = useState(false);

  // Data Import Tool from Old Web System
  const [sqlDumpPath, setSqlDumpPath] = useState('c:\\xampp\\htdocs\\islamia college\\database.sql');
  const [preview, setPreview] = useState<DataImportPreview | null>(null);
  const [previewing, setPreviewing] = useState(false);
  const [importing, setImporting] = useState(false);
  const [importResult, setImportResult] = useState<string | null>(null);

  useEffect(() => {
    if (activeTab === 'roles') {
      loadUsers();
    }
  }, [activeTab]);

  const loadUsers = async () => {
    try {
      setLoadingUsers(true);
      const res = await api.getUsers();
      setUsersList(res || []);
    } catch (err: any) {
      addToast('error', err.message || 'Failed to load user accounts');
    } finally {
      setLoadingUsers(false);
    }
  };

  const handleCreateUser = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newUserData.username.trim() || !newUserData.fullName.trim()) {
      addToast('error', 'Username and Full Name are required.');
      return;
    }

    try {
      await api.createUser({
        username: newUserData.username.trim().toLowerCase(),
        fullName: newUserData.fullName.trim(),
        phone: newUserData.phone.trim() || undefined,
        email: newUserData.email.trim() || undefined,
        password: newUserData.password.trim() || undefined,
        role: newUserData.role
      });
      addToast('success', `User '${newUserData.username}' with role '${newUserData.role}' created successfully!`);
      setIsAddUserModalOpen(false);
      setNewUserData({
        username: '',
        fullName: '',
        phone: '',
        email: '',
        password: '',
        role: 'Staff'
      });
      loadUsers();
    } catch (err: any) {
      addToast('error', err.message || 'Failed to create user account');
    }
  };

  const handleToggleStatus = async (userId: number, currentStatus: boolean) => {
    try {
      await api.toggleUserStatus(userId);
      addToast('info', `User account ${currentStatus ? 'locked/deactivated' : 'activated'}.`);
      loadUsers();
    } catch (err: any) {
      addToast('error', err.message || 'Failed to change user status');
    }
  };

  const handleResetPassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!resetPassModal.newPass.trim()) {
      addToast('error', 'Please enter a new password.');
      return;
    }
    try {
      await api.resetUserPassword(resetPassModal.userId, resetPassModal.newPass.trim());
      addToast('success', `Password for user '${resetPassModal.username}' updated.`);
      setResetPassModal({ isOpen: false, userId: 0, username: '', newPass: '' });
    } catch (err: any) {
      addToast('error', err.message || 'Failed to reset password');
    }
  };

  const handleDeleteUser = async (userId: number, username: string) => {
    if (!confirm(`Are you sure you want to permanently delete user account '${username}'?`)) return;
    try {
      await api.deleteUser(userId);
      addToast('info', `User '${username}' deleted.`);
      loadUsers();
    } catch (err: any) {
      addToast('error', err.message || 'Failed to delete user');
    }
  };

  const handleSyncNow = async () => {
    setSyncing(true);
    try {
      await api.triggerSyncNow();
      addToast('success', 'Automatic background sync triggered successfully.');
    } catch (err: any) {
      addToast('error', err.message);
    } finally {
      setSyncing(false);
    }
  };

  const handleCreateBackup = async () => {
    setCreatingBackup(true);
    try {
      const path = await api.createBackup(targetDir || undefined, backupPassword || undefined);
      addToast('success', `Verified snapshot saved: ${path}`);
    } catch (err: any) {
      addToast('error', `Backup creation failed: ${err.message}`);
    } finally {
      setCreatingBackup(false);
    }
  };

  const handleRestoreBackup = async () => {
    if (!restoreFilePath) {
      addToast('error', 'Please enter backup file path to restore.');
      return;
    }
    if (!confirm('Are you sure you want to restore? A safety backup of your current live database will be made first.')) {
      return;
    }

    try {
      await api.restoreBackup(restoreFilePath, backupPassword || undefined);
      addToast('success', 'Database successfully restored and verified!');
    } catch (err: any) {
      addToast('error', `Restore failed: ${err.message}`);
    }
  };

  const handlePreviewImport = async () => {
    if (!sqlDumpPath.trim()) return;
    setPreviewing(true);
    setPreview(null);
    setImportResult(null);

    try {
      const res = await api.previewSqlDump(sqlDumpPath.trim());
      setPreview(res);
      addToast('info', `Preview generated: ${res.totalStudents} student(s) found in dump.`);
    } catch (err: any) {
      addToast('error', err.message || 'Failed to read SQL dump file.');
    } finally {
      setPreviewing(false);
    }
  };

  const handleExecuteImport = async () => {
    if (!sqlDumpPath.trim()) return;
    setImporting(true);

    try {
      const res = await api.executeImport(sqlDumpPath.trim());
      if (res.success) {
        setImportResult(res.message);
        addToast('success', res.message);
      } else {
        addToast('error', res.message);
      }
    } catch (err: any) {
      addToast('error', err.message || 'Import execution failed.');
    } finally {
      setImporting(false);
    }
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
          <ShieldCheck className="w-5 h-5 text-blue-600" />
          System Administration &amp; Security
        </h1>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          User Role-Based Access Control (RBAC), automatic cloud backups &amp; disaster recovery
        </p>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-slate-200 dark:border-slate-800 pb-2 text-xs font-semibold">
        <button
          onClick={() => setActiveTab('roles')}
          className={`px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 ${
            activeTab === 'roles'
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
          }`}
        >
          <Users className="w-4 h-4" />
          <span>User Roles &amp; Staff (RBAC)</span>
        </button>
        <button
          onClick={() => setActiveTab('backup')}
          className={`px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 ${
            activeTab === 'backup'
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
          }`}
        >
          <HardDrive className="w-4 h-4" />
          <span>Backups &amp; USB Storage</span>
        </button>
        <button
          onClick={() => setActiveTab('drive')}
          className={`px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 ${
            activeTab === 'drive'
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
          }`}
        >
          <Cloud className="w-4 h-4" />
          <span>Automatic Google Drive Sync</span>
        </button>
        <button
          onClick={() => setActiveTab('import')}
          className={`px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 ${
            activeTab === 'import'
              ? 'bg-blue-600 text-white'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
          }`}
        >
          <FileUp className="w-4 h-4" />
          <span>Import from Old System</span>
        </button>
      </div>

      {/* TAB 1: USER ROLES MANAGEMENT (RBAC) */}
      {activeTab === 'roles' && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Users className="w-4 h-4 text-blue-600" />
                <span>User Roles &amp; Security Permissions</span>
              </h2>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Admin can create system accounts, assign permission roles, and manage access credentials.
              </p>
            </div>

            <button
              onClick={() => setIsAddUserModalOpen(true)}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
            >
              <UserPlus className="w-4 h-4" />
              <span>+ Add System User &amp; Role</span>
            </button>
          </div>

          {/* Users Table */}
          <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
            {loadingUsers ? (
              <div className="p-8 text-center text-xs text-slate-400">Loading user directory...</div>
            ) : (
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                  <tr>
                    <th className="py-2.5 px-3">Username</th>
                    <th className="py-2.5 px-3">Full Name</th>
                    <th className="py-2.5 px-3">Phone / Default Pass</th>
                    <th className="py-2.5 px-3">Institutional Email</th>
                    <th className="py-2.5 px-3">Assigned Role</th>
                    <th className="py-2.5 px-3">Status</th>
                    <th className="py-2.5 px-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                  {usersList.map((u) => (
                    <tr key={u.id} className="hover:bg-slate-50/70 transition">
                      <td className="py-2.5 px-3 font-mono font-bold text-blue-600 dark:text-blue-400">
                        {u.username}
                      </td>
                      <td className="py-2.5 px-3 font-medium text-slate-900 dark:text-white">{u.fullName}</td>
                      <td className="py-2.5 px-3 font-mono text-slate-500">
                        {u.phone ? `📞 ${u.phone}` : '—'}
                      </td>
                      <td className="py-2.5 px-3 text-slate-500">{u.email || '—'}</td>
                      <td className="py-2.5 px-3">
                        <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                          {u.role}
                        </span>
                      </td>
                      <td className="py-2.5 px-3">
                        <span
                          className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ${
                            u.isActive
                              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                              : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                          }`}
                        >
                          <span
                            className={`w-1.5 h-1.5 rounded-full ${u.isActive ? 'bg-emerald-500' : 'bg-rose-500'}`}
                          ></span>
                          {u.isActive ? 'Active' : 'Locked'}
                        </span>
                      </td>
                      <td className="py-2.5 px-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            onClick={() =>
                              setResetPassModal({
                                isOpen: true,
                                userId: u.id,
                                username: u.username,
                                newPass: ''
                              })
                            }
                            className="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded text-[11px] font-medium transition"
                          >
                            Password
                          </button>
                          <button
                            onClick={() => handleToggleStatus(u.id, u.isActive)}
                            className="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded text-[11px] font-medium transition"
                          >
                            {u.isActive ? 'Lock' : 'Unlock'}
                          </button>
                          <button
                            onClick={() => handleDeleteUser(u.id, u.username)}
                            className="px-2 py-1 bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white rounded text-[11px] font-medium transition"
                          >
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {/* TAB 2: LOCAL BACKUP & USB STORAGE */}
      {activeTab === 'backup' && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
          <div>
            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <HardDrive className="w-4 h-4 text-blue-600" />
              <span>Offline Database Snapshots &amp; External USB Backup</span>
            </h2>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Zero-lock SQLite database engine snapshot with AES-256 encryption. Automatically saves to attached USB drive.
            </p>
          </div>

          {/* Auto USB Status Indicator */}
          <div className="p-3.5 bg-blue-50 dark:bg-slate-800/60 rounded-xl border border-blue-200 dark:border-slate-700 flex items-center justify-between text-xs">
            <div className="flex items-center gap-2">
              <Usb className="w-5 h-5 text-blue-600" />
              <div>
                <strong className="text-slate-900 dark:text-white block">Detected External USB Storage</strong>
                <span className="text-[11px] text-slate-500">Destination: <code className="text-blue-700 font-bold">{targetDir}</code></span>
              </div>
            </div>
            <div className="flex gap-2">
              <button
                onClick={() => setTargetDir('F:\\ICT_College_Backups')}
                className="px-2.5 py-1 bg-blue-600 text-white rounded text-[11px] font-semibold"
              >
                Use USB (F:)
              </button>
              <button
                onClick={() => setTargetDir('C:\\Users\\Tariq Laptops\\Desktop')}
                className="px-2.5 py-1 bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 rounded text-[11px] font-semibold"
              >
                Use Desktop
              </button>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Create Snapshot */}
            <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-3 text-xs">
              <h3 className="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                <Database className="w-4 h-4 text-emerald-600" />
                <span>Save Database Snapshot Now</span>
              </h3>
              <p className="text-slate-500 text-[11px]">
                Writes verified snapshot with SHA-256 integrity checksum into target directory.
              </p>
              <input
                type="text"
                placeholder="Target Directory (e.g. F:\ICT_College_Backups)"
                value={targetDir}
                onChange={(e) => setTargetDir(e.target.value)}
                className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs font-mono"
              />
              <input
                type="password"
                placeholder="Optional backup password"
                value={backupPassword}
                onChange={(e) => setBackupPassword(e.target.value)}
                className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs"
              />
              <button
                onClick={handleCreateBackup}
                disabled={creatingBackup}
                className="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-sm transition"
              >
                {creatingBackup ? 'Writing Snapshot...' : 'Save Snapshot to USB / Drive'}
              </button>
            </div>

            {/* Restore Backup */}
            <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-3 text-xs">
              <h3 className="font-bold text-slate-900 dark:text-white">Restore from Backup</h3>
              <p className="text-slate-500 text-[11px]">
                Validates candidate backup with <code className="text-blue-600">PRAGMA integrity_check</code> and makes a safety copy first.
              </p>
              <input
                type="text"
                placeholder="Path to .icbak file"
                value={restoreFilePath}
                onChange={(e) => setRestoreFilePath(e.target.value)}
                className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-xs font-mono"
              />
              <button
                onClick={handleRestoreBackup}
                className="w-full py-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-lg shadow-sm transition"
              >
                Verify &amp; Restore Database
              </button>
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: AUTOMATIC GOOGLE DRIVE SYNC */}
      {activeTab === 'drive' && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
          <div className="flex items-start justify-between">
            <div>
              <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Cloud className="w-4 h-4 text-blue-600" />
                <span>Automatic Google Drive Cloud Sync</span>
              </h2>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Background worker automatically uploads encrypted snapshots whenever online.
              </p>
            </div>

            <button
              onClick={handleSyncNow}
              disabled={syncing}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
            >
              <RefreshCw className={`w-4 h-4 ${syncing ? 'animate-spin' : ''}`} />
              <span>Check &amp; Sync Now</span>
            </button>
          </div>

          <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-xs space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-slate-500">Auto-Sync Status:</span>
              <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Active (Checks every 5 minutes)
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Target Google Account:</span>
              <strong className="text-slate-900 dark:text-white">aminullahameen@gmail.com</strong>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Destination Folder:</span>
              <strong className="text-slate-900 dark:text-white">ICT Management System Backups</strong>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Offline Resilience:</span>
              <span className="text-emerald-600 font-semibold">Active (`sync-queue.db` persistent local queue)</span>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: DATA IMPORT TOOL */}
      {activeTab === 'import' && (
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
          <div>
            <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <FileUp className="w-4 h-4 text-blue-600" />
              <span>One-Time Data Import Tool (From Web System / MySQL)</span>
            </h2>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Select MySQL dump (<code className="text-blue-600">database.sql</code>) or export from the old XAMPP web system.
            </p>
          </div>

          <div className="space-y-3 max-w-2xl">
            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300">
              MySQL Dump File Path (.sql)
            </label>
            <div className="flex gap-2">
              <input
                type="text"
                value={sqlDumpPath}
                onChange={(e) => setSqlDumpPath(e.target.value)}
                placeholder="c:\xampp\htdocs\islamia college\database.sql"
                className="flex-1 p-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white font-mono"
              />
              <button
                onClick={handlePreviewImport}
                disabled={previewing}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
              >
                {previewing ? 'Analyzing...' : 'Scan & Preview'}
              </button>
            </div>
          </div>

          {preview && (
            <div className="p-5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-4">
              <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                <h3 className="text-xs font-bold text-slate-900 dark:text-white">
                  Validation Report: {preview.sourceFileName}
                </h3>
                <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">
                  Ready to Load
                </span>
              </div>

              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                <div className="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800">
                  <span className="text-slate-500 block text-[10px]">Academic Departments</span>
                  <strong className="text-sm text-slate-900 dark:text-white">{preview.totalDepartments}</strong>
                </div>
                <div className="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800">
                  <span className="text-slate-500 block text-[10px]">Course Programs</span>
                  <strong className="text-sm text-slate-900 dark:text-white">{preview.totalCourses}</strong>
                </div>
                <div className="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800">
                  <span className="text-slate-500 block text-[10px]">Student Profiles</span>
                  <strong className="text-sm text-blue-600">{preview.totalStudents}</strong>
                </div>
                <div className="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800">
                  <span className="text-slate-500 block text-[10px]">Fee Receipts</span>
                  <strong className="text-sm text-emerald-600">{preview.totalFeeTransactions}</strong>
                </div>
              </div>

              <div className="flex justify-end pt-2">
                <button
                  onClick={handleExecuteImport}
                  disabled={importing}
                  className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-md transition flex items-center gap-2"
                >
                  <CheckCircle2 className="w-4 h-4" />
                  <span>{importing ? 'Importing Records into SQLite...' : 'Confirm & Execute Import'}</span>
                </button>
              </div>
            </div>
          )}

          {importResult && (
            <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs flex items-center gap-2">
              <CheckCircle2 className="w-5 h-5 flex-shrink-0" />
              <span>{importResult}</span>
            </div>
          )}
        </div>
      )}

      {/* Add User & Role Modal */}
      {isAddUserModalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 animate-in zoom-in-95">
            <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-1">
              Add System User &amp; Assign Role
            </h3>
            <p className="text-xs text-slate-500 mb-4">
              Configure access credentials and RBAC permission role.
            </p>

            <form onSubmit={handleCreateUser} className="space-y-3 text-xs">
              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Username *
                </label>
                <input
                  type="text"
                  required
                  value={newUserData.username}
                  onChange={(e) => setNewUserData({ ...newUserData, username: e.target.value })}
                  placeholder="e.g. cashier1"
                  className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white font-mono"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Full Name *
                </label>
                <input
                  type="text"
                  required
                  value={newUserData.fullName}
                  onChange={(e) => setNewUserData({ ...newUserData, fullName: e.target.value })}
                  placeholder="e.g. Irfan Ullah"
                  className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Phone (Default Password)
                </label>
                <input
                  type="text"
                  value={newUserData.phone}
                  onChange={(e) => setNewUserData({ ...newUserData, phone: e.target.value })}
                  placeholder="0345-XXXXXXX"
                  className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white font-mono"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Institutional Email
                </label>
                <input
                  type="email"
                  value={newUserData.email}
                  onChange={(e) => setNewUserData({ ...newUserData, email: e.target.value })}
                  placeholder="staff@ictswat.edu.pk"
                  className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Access Role *
                </label>
                <select
                  value={newUserData.role}
                  onChange={(e) => setNewUserData({ ...newUserData, role: e.target.value })}
                  className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white font-bold"
                >
                  <option value="Admin">Admin (Full System Access)</option>
                  <option value="Director">Director (CEO &amp; Executive Oversight)</option>
                  <option value="Registrar">Registrar (Admissions &amp; Cohorts)</option>
                  <option value="Accountant">Accountant (Finance &amp; Ledgers)</option>
                  <option value="Cashier">Cashier (Fee Counter Operations)</option>
                  <option value="Teacher">Teacher / Instructor (Attendance &amp; Marks)</option>
                </select>
              </div>

              <div>
                <label className="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  Custom Password (optional, uses phone if blank)
                </label>
                <input
                  type="password"
                  value={newUserData.password}
                  onChange={(e) => setNewUserData({ ...newUserData, password: e.target.value })}
                  placeholder="Leave empty to use phone number"
                  className="w-full p-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white"
                />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <button
                  type="button"
                  onClick={() => setIsAddUserModalOpen(false)}
                  className="px-3.5 py-1.5 border rounded-lg hover:bg-slate-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg"
                >
                  Create User
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Reset Password Modal */}
      {resetPassModal.isOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-sm w-full p-6 animate-in zoom-in-95">
            <h3 className="text-sm font-bold text-slate-900 dark:text-white mb-1">
              Reset Password for '{resetPassModal.username}'
            </h3>
            <p className="text-xs text-slate-500 mb-3">
              Enter a new secure password for this user.
            </p>

            <form onSubmit={handleResetPassword} className="space-y-3 text-xs">
              <input
                type="password"
                required
                placeholder="New password (min 4 chars)"
                value={resetPassModal.newPass}
                onChange={(e) => setResetPassModal({ ...resetPassModal, newPass: e.target.value })}
                className="w-full p-2 bg-slate-50 dark:bg-slate-800 border rounded-lg text-slate-900 dark:text-white"
              />
              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setResetPassModal({ isOpen: false, userId: 0, username: '', newPass: '' })}
                  className="px-3 py-1.5 border rounded-lg"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg"
                >
                  Update Password
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
