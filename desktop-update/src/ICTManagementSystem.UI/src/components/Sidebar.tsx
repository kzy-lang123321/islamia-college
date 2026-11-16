import React from 'react';
import { NavLink } from 'react-router-dom';
import {
  LayoutDashboard,
  Users,
  BookOpen,
  CreditCard,
  CalendarCheck,
  Award,
  FileText,
  BarChart3,
  Settings,
  LogOut,
  GraduationCap
} from 'lucide-react';
import { useAppStore } from '../services/store';
import collegeLogo from '../assets/logo.png';

export const Sidebar: React.FC = () => {
  const { user, setUser } = useAppStore();

  const navItems = [
    { label: 'Dashboard', path: '/', icon: LayoutDashboard },
    { label: 'Students', path: '/students', icon: Users },
    { label: 'Courses & Batches', path: '/courses', icon: BookOpen },
    { label: 'Class Enrollments', path: '/enrollments', icon: GraduationCap },
    { label: 'Finance & Fees', path: '/fees', icon: CreditCard },
    { label: 'Attendance', path: '/attendance', icon: CalendarCheck },
    { label: 'Exams & Results', path: '/results', icon: Award },
    { label: 'Certificates', path: '/certificates', icon: FileText },
    { label: 'Reports Hub', path: '/reports', icon: BarChart3 },
    { label: 'Administration', path: '/settings', icon: Settings },
  ];

  return (
    <aside className="no-print w-60 bg-sky-50 text-slate-700 flex flex-col h-screen select-none border-r border-sky-200 shadow-sm flex-shrink-0 z-30">
      {/* Brand Header */}
      <div className="h-16 flex items-center px-4 bg-white border-b border-sky-100 gap-3">
        <img
          src={collegeLogo}
          alt="Islamia College Logo"
          className="w-10 h-10 rounded-lg object-contain bg-white p-0.5 shadow-md flex-shrink-0"
        />
        <div className="overflow-hidden min-w-0">
          <h1 className="font-bold text-xs tracking-wide text-slate-900 truncate uppercase">ICT Matta Swat</h1>
          <p className="text-[10px] text-slate-500 truncate">Islamia College of Tech</p>
        </div>
      </div>

      {/* Navigation Menu */}
      <nav className="flex-1 py-3 px-2 space-y-0.5 overflow-y-auto">
        {navItems.map((item) => {
          const Icon = item.icon;
          return (
            <NavLink
              key={item.path}
              to={item.path}
              end={item.path === '/'}
              className={({ isActive }) =>
                `flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-all ${
                  isActive
                    ? 'bg-sky-600 text-white shadow-sm font-semibold'
                    : 'text-slate-600 hover:bg-sky-100 hover:text-sky-800'
                }`
              }
            >
              <Icon className="w-4 h-4 flex-shrink-0 text-current" />
              <span className="truncate">{item.label}</span>
            </NavLink>
          );
        })}
      </nav>

      {/* User Footer */}
      <div className="p-3 bg-white border-t border-sky-100 flex items-center justify-between">
        <div className="flex items-center gap-2 overflow-hidden">
          <div className="w-8 h-8 rounded-full bg-ict-blue flex items-center justify-center text-xs font-bold text-white uppercase flex-shrink-0">
            {user?.fullName?.charAt(0) || 'U'}
          </div>
          <div className="overflow-hidden text-left min-w-0">
            <p className="text-xs font-semibold text-slate-900 truncate">{user?.fullName || 'User'}</p>
            <p className="text-[10px] text-slate-400 capitalize">{user?.role || 'Staff'}</p>
          </div>
        </div>
        <button
          onClick={() => setUser(null, null)}
          title="Sign Out"
          className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-white/5 rounded-md transition"
        >
          <LogOut className="w-4 h-4" />
        </button>
      </div>
    </aside>
  );
};
