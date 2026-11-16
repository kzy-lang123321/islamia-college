import React, { useEffect } from 'react';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useAppStore } from './services/store';
import { Sidebar } from './components/Sidebar';
import { TopBar } from './components/TopBar';
import { ToastContainer } from './components/ToastContainer';
import { Login } from './pages/Login';
import { Dashboard } from './pages/Dashboard';
import { StudentsList } from './pages/students/StudentsList';
import { StudentView } from './pages/students/StudentView';
import { CoursesList } from './pages/courses/CoursesList';
import { CourseDetail } from './pages/courses/CourseDetail';
import { EnrollmentsList } from './pages/courses/EnrollmentsList';
import { FeeCounter } from './pages/fees/FeeCounter';
import { AttendanceSheet } from './pages/attendance/AttendanceSheet';
import { ResultsHub } from './pages/results/ResultsHub';
import { CertificatesList } from './pages/certificates/CertificatesList';
import { ReportsHub } from './pages/reports/ReportsHub';
import { SettingsPage } from './pages/settings/SettingsPage';

export function App() {
  const { user, theme } = useAppStore();

  useEffect(() => {
    if (theme === 'dark') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }, [theme]);

  if (!user) {
    return (
      <div className="min-h-screen bg-slate-900">
        <Login />
        <ToastContainer />
      </div>
    );
  }

  return (
    <HashRouter>
      <div className="flex h-screen bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 overflow-hidden font-sans">
        <Sidebar />
        <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
          <TopBar />
          <main className="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50 dark:bg-slate-900">
            <Routes>
              <Route path="/" element={<Dashboard />} />
              <Route path="/students" element={<StudentsList />} />
              <Route path="/students/:id" element={<StudentView />} />
              <Route path="/courses" element={<CoursesList />} />
              <Route path="/courses/:id" element={<CourseDetail />} />
              <Route path="/enrollments" element={<EnrollmentsList />} />
              <Route path="/fees" element={<FeeCounter />} />
              <Route path="/attendance" element={<AttendanceSheet />} />
              <Route path="/results" element={<ResultsHub />} />
              <Route path="/certificates" element={<CertificatesList />} />
              <Route path="/reports" element={<ReportsHub />} />
              <Route path="/settings" element={<SettingsPage />} />
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </main>
        </div>
        <ToastContainer />
      </div>
    </HashRouter>
  );
}

export default App;
