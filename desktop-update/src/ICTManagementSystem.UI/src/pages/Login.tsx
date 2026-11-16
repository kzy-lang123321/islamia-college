import React, { useState } from 'react';
import { GraduationCap, Lock, User, AlertCircle, ArrowRight } from 'lucide-react';
import { useAppStore } from '../services/store';
import { api } from '../services/api';
import collegeLogo from '../assets/logo.png';

export const Login: React.FC = () => {
  const { setUser, addToast } = useAppStore();
  const [username, setUsername] = useState('admin');
  const [password, setPassword] = useState('admin123');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const res = await api.login(username, password);
      if (res.success) {
        setUser(
          {
            id: res.userId,
            username: res.username,
            fullName: res.fullName,
            role: res.role,
            isActive: true
          },
          res.token
        );
        addToast('success', `Welcome back, ${res.fullName}!`);
      } else {
        setError(res.message || 'Invalid username or password.');
      }
    } catch (err: any) {
      setError(err.message || 'Failed to connect to authentication service.');
    } finally {
      setLoading(false);
    }
  };

  const handleQuickFill = (u: string, p: string) => {
    setUsername(u);
    setPassword(p);
    setError(null);
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-ict-navy-dark via-ict-navy to-slate-900 flex items-center justify-center p-4">
      <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-white/20 p-8">
        {/* Brand */}
        <div className="text-center mb-8">
          <img
            src={collegeLogo}
            alt="Islamia College of Technology Logo"
            className="w-16 h-16 mx-auto rounded-2xl shadow-lg bg-white p-1 mb-3 object-contain border border-slate-100"
          />
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
            ISLAMIA COLLEGE OF TECHNOLOGY
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Matta Swat • Management Information System
          </p>
        </div>

        {error && (
          <div className="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-xs flex items-center gap-2">
            <AlertCircle className="w-4 h-4 flex-shrink-0" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Username
            </label>
            <div className="relative">
              <User className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                required
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="Enter username"
                className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Password
            </label>
            <div className="relative">
              <Lock className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter password"
                className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-ict-orange"
              />
            </div>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full py-2.5 bg-ict-orange hover:bg-ict-orange-hover text-white text-xs font-semibold rounded-lg shadow-md transition flex items-center justify-center gap-2"
          >
            {loading ? 'Authenticating...' : 'Sign In to Portal'}
            <ArrowRight className="w-4 h-4" />
          </button>
        </form>

        {/* Demo Fast Fill Buttons */}
        <div className="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 text-center">
          <p className="text-[11px] text-slate-400 mb-2">Default Accounts for Testing:</p>
          <div className="flex justify-center gap-2 text-[10px]">
            <button
              type="button"
              onClick={() => handleQuickFill('admin', 'admin123')}
              className="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-medium transition"
            >
              Admin
            </button>
            <button
              type="button"
              onClick={() => handleQuickFill('accountant', 'accountant123')}
              className="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-medium transition"
            >
              Accountant
            </button>
            <button
              type="button"
              onClick={() => handleQuickFill('teacher', 'teacher123')}
              className="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-medium transition"
            >
              Teacher
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

