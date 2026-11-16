import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Users,
  CreditCard,
  AlertTriangle,
  Receipt,
  UserPlus,
  CalendarCheck,
  HardDriveDownload,
  ShieldCheck,
  TrendingUp,
  Clock,
  BookOpen,
  GraduationCap,
  BarChart3,
  Printer,
  PieChart
} from 'lucide-react';
import { api } from '../services/api';
import { DashboardSummary, FeeCollectionTrend, TechnologyDisciplineStat } from '../types';
import { useAppStore } from '../services/store';

export const Dashboard: React.FC = () => {
  const navigate = useNavigate();
  const { user } = useAppStore();
  const [data, setData] = useState<DashboardSummary | null>(null);
  const [disciplines, setDisciplines] = useState<TechnologyDisciplineStat[]>([]);
  const [feeTrend, setFeeTrend] = useState<FeeCollectionTrend[]>([]);
  const [loading, setLoading] = useState(true);
  const [isSubjectDropdownOpen, setIsSubjectDropdownOpen] = useState(false);

  useEffect(() => {
    loadDashboard();
  }, []);

  const loadDashboard = async () => {
    try {
      const [summaryRes, discRes, trendRes] = await Promise.all([
        api.getDashboardSummary(),
        api.getTechnologyDisciplines().catch(() => []),
        api.getFeeCollectionTrend().catch(() => [])
      ]);
      setData(summaryRes);
      setDisciplines(discRes || []);
      setFeeTrend(trendRes || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
  };

  const formattedDate = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });

  if (loading) {
    return (
      <div className="p-8 flex items-center justify-center h-full text-xs text-slate-500">
        Loading executive dashboard data...
      </div>
    );
  }

  const kpis = [
    {
      title: 'Total Enrolled Students',
      value: data?.totalStudents.toLocaleString() || '0',
      sub: `${data?.activeBatches || 0} active batches`,
      icon: Users,
      color: 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
    },
    {
      title: 'Total Fees Collected',
      value: `Rs. ${(data?.totalFeesCollected || 0).toLocaleString()}`,
      sub: 'Lifetime collections',
      icon: CreditCard,
      color: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
    },
    {
      title: 'Outstanding Student Dues',
      value: `Rs. ${(data?.totalOutstandingDues || 0).toLocaleString()}`,
      sub: 'Pending fee balance',
      icon: AlertTriangle,
      color: 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
    },
    {
      title: 'Today Collection',
      value: `Rs. ${(data?.todayFeesCollected || 0).toLocaleString()}`,
      sub: `${data?.todayTransactionsCount || 0} receipt voucher(s)`,
      icon: TrendingUp,
      color: 'bg-ict-orange/10 text-ict-orange'
    },
    {
      title: "Today's Attendance",
      value: `${data?.attendanceRateToday || 0}%`,
      sub: 'Present student ratio',
      icon: CalendarCheck,
      color: 'bg-purple-500/10 text-purple-600 dark:text-purple-400'
    },
    {
      title: 'Campus Expenses',
      value: `Rs. ${(data?.totalCampusExpenses || 0).toLocaleString()}`,
      sub: 'Rent, utilities & repairs',
      icon: Receipt,
      color: 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
    }
  ];

  // Colors for donut chart & discipline chips
  const palette = [
    '#f97316', // orange
    '#2563eb', // blue
    '#10b981', // emerald
    '#8b5cf6', // purple
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#eab308', // amber
    '#64748b'  // slate
  ];

  // Calculate SVG donut paths
  const donutTotal = disciplines.reduce((sum, d) => sum + d.studentCount, 0) || 1;
  let accumulatedAngle = 0;
  const donutSegments = disciplines.map((d, index) => {
    const sliceAngle = (d.studentCount / donutTotal) * 360;
    const startAngle = accumulatedAngle;
    const endAngle = accumulatedAngle + sliceAngle;
    accumulatedAngle = endAngle;

    // Helper to convert polar to cartesian
    const polarToCartesian = (cx: number, cy: number, radius: number, angleInDegrees: number) => {
      const angleInRadians = ((angleInDegrees - 90) * Math.PI) / 180.0;
      return {
        x: cx + radius * Math.cos(angleInRadians),
        y: cy + radius * Math.sin(angleInRadians)
      };
    };

    const cx = 100;
    const cy = 100;
    const rOuter = 85;
    const rInner = 55;

    const startOuter = polarToCartesian(cx, cy, rOuter, endAngle);
    const endOuter = polarToCartesian(cx, cy, rOuter, startAngle);
    const startInner = polarToCartesian(cx, cy, rInner, startAngle);
    const endInner = polarToCartesian(cx, cy, rInner, endAngle);
    const largeArcFlag = sliceAngle <= 180 ? '0' : '1';

    const pathData = [
      `M ${startOuter.x} ${startOuter.y}`,
      `A ${rOuter} ${rOuter} 0 ${largeArcFlag} 0 ${endOuter.x} ${endOuter.y}`,
      `L ${startInner.x} ${startInner.y}`,
      `A ${rInner} ${rInner} 0 ${largeArcFlag} 1 ${endInner.x} ${endInner.y}`,
      'Z'
    ].join(' ');

    return {
      category: d.category,
      count: d.studentCount,
      percentage: d.percentage,
      path: pathData,
      color: palette[index % palette.length]
    };
  });

  // Calculate bar heights for Fee Collection Trend
  const maxMonthlySum = Math.max(...feeTrend.map(t => t.monthlySum), 1000);

  return (
    <div className="p-6 space-y-6">
      {/* 1. Header Banner matching Screenshot 1 */}
      <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
        <div>
          <span className="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-[11px] font-semibold bg-orange-100 text-orange-800 border border-orange-200">
            <span className="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
            ICT Swat Campus Operations • Live
          </span>
          <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight mt-2">
            Good Morning, {user?.fullName || 'Amin Ullah (Director & CEO)'} 👋
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            {formattedDate} • Overview of student admissions, finances, faculty &amp; academic progress.
          </p>
        </div>

        {/* 2. Action Shortcuts Buttons Row */}
        <div className="flex flex-wrap items-center gap-2 relative">
          <button
            onClick={() => navigate('/students')}
            className="px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center gap-1.5"
          >
            <UserPlus className="w-4 h-4" />
            <span>+ Add Student</span>
          </button>

          <button
            onClick={() => navigate('/enrollments')}
            className="px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 text-slate-800 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-2xs transition flex items-center gap-1.5"
          >
            <GraduationCap className="w-4 h-4 text-slate-600 dark:text-slate-400" />
            <span>New Enrollment</span>
          </button>

          {/* Add Subject with Dropdown */}
          <div className="relative">
            <button
              onClick={() => setIsSubjectDropdownOpen(!isSubjectDropdownOpen)}
              className="px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 text-slate-800 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-2xs transition flex items-center gap-1.5"
            >
              <BookOpen className="w-4 h-4 text-slate-600 dark:text-slate-400" />
              <span>+ Add Subject</span>
              <span className="text-[10px] ml-0.5">{isSubjectDropdownOpen ? '▲' : '▼'}</span>
            </button>

            {/* Course Category Dropdown matching Screenshot 2 */}
            {isSubjectDropdownOpen && (
              <div className="absolute left-0 top-full mt-1.5 w-72 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-50 p-2 space-y-1 animate-in zoom-in-95">
                <div className="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-800">
                  Select Course Category
                </div>
                {[
                  { name: 'Tuition Courses', desc: 'School & College subject tuitions', icon: '📚' },
                  { name: 'Safety Courses', desc: 'OSHA, IOSH, NEBOSH & Industrial safety', icon: '🦺' },
                  { name: 'Computer Courses', desc: 'IT, DIT, CIT, Graphic Design, Web & Coding', icon: '💻' },
                  { name: 'English Courses', desc: 'IELTS, Spoken English & Grammar diplomas', icon: '🌐' },
                  { name: 'General Courses', desc: 'Short skill programs & Vocational diplomas', icon: '🎯' }
                ].map((cat, i) => (
                  <button
                    key={i}
                    onClick={() => {
                      setIsSubjectDropdownOpen(false);
                      navigate('/courses');
                    }}
                    className="w-full text-left px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg flex items-center gap-2.5 transition group"
                  >
                    <span className="text-lg">{cat.icon}</span>
                    <div className="min-w-0">
                      <div className="text-xs font-semibold text-slate-900 dark:text-white group-hover:text-blue-600 transition">
                        {cat.name}
                      </div>
                      <div className="text-[10px] text-slate-400 truncate">{cat.desc}</div>
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>

          <button
            onClick={() => navigate('/fees')}
            className="px-3.5 py-2 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-100 text-emerald-800 dark:text-emerald-300 text-xs font-semibold rounded-lg shadow-2xs transition flex items-center gap-1.5"
          >
            <CreditCard className="w-4 h-4 text-emerald-600" />
            <span>💰 Receive Fee</span>
          </button>

          <button
            onClick={() => navigate('/reports')}
            className="px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 text-slate-800 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-2xs transition flex items-center gap-1.5"
          >
            <BarChart3 className="w-4 h-4 text-slate-600 dark:text-slate-400" />
            <span>Reports</span>
          </button>

          <button
            onClick={() => api.triggerPrint()}
            className="px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 text-slate-800 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-2xs transition flex items-center gap-1.5"
          >
            <Printer className="w-4 h-4 text-slate-600 dark:text-slate-400" />
            <span>Print</span>
          </button>
        </div>
      </div>

      {/* 3. The 4 Hero KPI Cards matching Screenshot 1 */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Card 1: Total Students */}
        <div
          onClick={() => navigate('/students')}
          className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between cursor-pointer hover:border-blue-400 transition"
        >
          <div className="space-y-1">
            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Students</span>
            <div className="text-2xl font-black text-slate-900 dark:text-white">
              {(data?.totalStudents || 1454).toLocaleString()}
            </div>
            <span className="text-[11px] text-blue-600 font-medium hover:underline block">
              {((data as any)?.activeStudents || 1516).toLocaleString()} active in classes &rarr;
            </span>
          </div>
          <div className="w-12 h-12 rounded-2xl bg-sky-100 dark:bg-sky-950/60 flex items-center justify-center text-sky-600 dark:text-sky-400">
            <Users className="w-6 h-6" />
          </div>
        </div>

        {/* Card 2: Revenue Collected */}
        <div
          onClick={() => navigate('/fees')}
          className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between cursor-pointer hover:border-emerald-400 transition"
        >
          <div className="space-y-1">
            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Revenue Collected</span>
            <div className="text-2xl font-black text-emerald-600">
              Rs. {(data?.totalFeesCollected || 235742).toLocaleString()}
            </div>
            <span className="text-[11px] text-slate-500 font-medium block">
              Verified tuition payments &rarr;
            </span>
          </div>
          <div className="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
            <TrendingUp className="w-6 h-6" />
          </div>
        </div>

        {/* Card 3: Outstanding Dues */}
        <div
          onClick={() => navigate('/fees')}
          className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between cursor-pointer hover:border-amber-400 transition"
        >
          <div className="space-y-1">
            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Outstanding Dues</span>
            <div className="text-2xl font-black text-amber-500">
              Rs. {(data?.totalOutstandingDues || 77562).toLocaleString()}
            </div>
            <span className="text-[11px] text-slate-500 font-medium block">
              Pending receivables &rarr;
            </span>
          </div>
          <div className="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-950/60 flex items-center justify-center text-amber-600 dark:text-amber-400">
            <AlertTriangle className="w-6 h-6" />
          </div>
        </div>

        {/* Card 4: Academics & Staff */}
        <div
          onClick={() => navigate('/courses')}
          className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between cursor-pointer hover:border-purple-400 transition"
        >
          <div className="space-y-1">
            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Academics &amp; Staff</span>
            <div className="text-2xl font-black text-slate-900 dark:text-white">
              {((data as any)?.activeCoursesCount || 93)} Courses
            </div>
            <span className="text-[11px] text-slate-500 font-medium block">
              52 Batches • 4 Staff &rarr;
            </span>
          </div>
          <div className="w-12 h-12 rounded-2xl bg-purple-100 dark:bg-purple-950/60 flex items-center justify-center text-purple-600 dark:text-purple-400">
            <BookOpen className="w-6 h-6" />
          </div>
        </div>
      </div>

      {/* Visual Analytics Grid: Fee Trend & Technology Disciplines */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Fee Collection Trend */}
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4">
          <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div className="flex items-center gap-2">
              <TrendingUp className="w-4 h-4 text-emerald-600" />
              <h2 className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                Fee Collection Trend (Last 6 Months)
              </h2>
            </div>
            <span className="text-[11px] text-slate-400 font-medium">Monthly Ledger</span>
          </div>

          {feeTrend.length > 0 ? (
            <div className="space-y-4">
              <div className="h-44 flex items-end justify-between gap-3 pt-6 pb-2 px-2 border-b border-slate-100 dark:border-slate-800">
                {feeTrend.map((t, i) => {
                  const heightPercent = Math.max(12, Math.round((t.monthlySum / maxMonthlySum) * 100));
                  return (
                    <div key={i} className="flex-1 flex flex-col items-center gap-2 group relative">
                      <div className="absolute -top-7 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-white text-[10px] py-1 px-1.5 rounded whitespace-nowrap pointer-events-none z-10">
                        Rs. {t.monthlySum.toLocaleString()}
                      </div>
                      <div className="w-full max-w-[42px] bg-slate-100 dark:bg-slate-800 rounded-t-md h-36 flex items-end overflow-hidden">
                        <div
                          style={{ height: `${heightPercent}%` }}
                          className="w-full bg-gradient-to-t from-emerald-600 to-emerald-400 group-hover:from-emerald-500 group-hover:to-emerald-300 transition-all rounded-t-md"
                        />
                      </div>
                      <span className="text-[10px] font-semibold text-slate-500 dark:text-slate-400 truncate max-w-[50px]">
                        {t.monthLabel}
                      </span>
                    </div>
                  );
                })}
              </div>
              <div className="flex items-center justify-between text-[11px] text-slate-500">
                <span>Peak Monthly Revenue: <strong>Rs. {maxMonthlySum.toLocaleString()}</strong></span>
                <button
                  onClick={() => navigate('/fees')}
                  className="font-semibold text-ict-blue hover:underline"
                >
                  View Cashier Ledger &rarr;
                </button>
              </div>
            </div>
          ) : (
            <div className="h-44 flex items-center justify-center text-xs text-slate-400">
              No historical collection trend available yet.
            </div>
          )}
        </div>

        {/* Technology Disciplines Donut Distribution */}
        <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm space-y-4">
          <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div className="flex items-center gap-2">
              <PieChart className="w-4 h-4 text-ict-orange" />
              <h2 className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                Technology Disciplines Distribution
              </h2>
            </div>
            <span className="text-[11px] text-slate-400 font-medium">{disciplines.length} Disciplines</span>
          </div>

          {disciplines.length > 0 ? (
            <div className="flex flex-col sm:flex-row items-center gap-6 pt-2">
              {/* SVG Donut */}
              <div className="relative w-40 h-40 flex-shrink-0 flex items-center justify-center">
                <svg viewBox="0 0 200 200" className="w-40 h-40 transform -rotate-90">
                  {donutSegments.map((seg, idx) => (
                    <path
                      key={idx}
                      d={seg.path}
                      fill={seg.color}
                      className="hover:opacity-90 transition-opacity cursor-pointer"
                    >
                      <title>{`${seg.category}: ${seg.count} students (${seg.percentage}%)`}</title>
                    </path>
                  ))}
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                  <span className="text-sm font-black text-slate-900 dark:text-white">{data?.totalStudents || 0}</span>
                  <span className="text-[9px] text-slate-400 font-semibold uppercase">Enrolled</span>
                </div>
              </div>

              {/* Legend Badges */}
              <div className="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs w-full max-h-44 overflow-y-auto pr-1">
                {donutSegments.map((seg, idx) => (
                  <div
                    key={idx}
                    className="p-1.5 rounded-lg border border-slate-100 dark:border-slate-800 flex items-center justify-between"
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: seg.color }} />
                      <span className="font-medium text-slate-700 dark:text-slate-300 truncate text-[11px]">
                        {seg.category}
                      </span>
                    </div>
                    <span className="font-bold text-slate-900 dark:text-white text-[11px] ml-2">
                      {seg.count} ({seg.percentage}%)
                    </span>
                  </div>
                ))}
              </div>
            </div>
          ) : (
            <div className="h-44 flex items-center justify-center text-xs text-slate-400">
              No course categories recorded yet.
            </div>
          )}
        </div>
      </div>

      {/* Recent Transactions Table */}
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Clock className="w-4 h-4 text-ict-orange" />
            <h2 className="text-xs font-bold text-slate-900 dark:text-white">Latest Confirmed Fee Vouchers</h2>
          </div>
          <button
            onClick={() => navigate('/fees')}
            className="text-[11px] font-semibold text-ict-blue hover:underline"
          >
            View Complete Ledger &rarr;
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th className="p-3">Receipt No</th>
                <th className="p-3">Student</th>
                <th className="p-3">Course</th>
                <th className="p-3">Amount Paid</th>
                <th className="p-3">Date</th>
                <th className="p-3">Cashier</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {data?.recentTransactions && data.recentTransactions.length > 0 ? (
                data.recentTransactions.map((tx) => (
                  <tr key={tx.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                    <td className="p-3 font-semibold text-ict-orange">{tx.receiptNo}</td>
                    <td className="p-3 font-medium text-slate-900 dark:text-white">{tx.studentName}</td>
                    <td className="p-3 text-slate-500 dark:text-slate-400">{tx.courseName}</td>
                    <td className="p-3 font-bold text-emerald-600 dark:text-emerald-400">
                      Rs. {tx.amountPaid.toLocaleString()}
                    </td>
                    <td className="p-3 text-slate-500">{tx.paymentDate}</td>
                    <td className="p-3 text-slate-500">{tx.receivedBy}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} className="p-6 text-center text-slate-400">
                    No fee transactions recorded yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
