<?php
/**
 * Islamia College of Technology (ICT) - College Management System Dashboard
 * User-First, Live Database-Driven Operations & Insights
 */
$pageTitle = "Dashboard";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';

$pdo = Database::getConnection();

// Human-centered time greeting
$hour = (int)date('H');
if ($hour < 12) {
    $timeGreeting = "Good Morning";
} elseif ($hour < 17) {
    $timeGreeting = "Good Afternoon";
} else {
    $timeGreeting = "Good Evening";
}

// 1. Core Real Database Metrics
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$activeEnrollments = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalBatches = (int)$pdo->query("SELECT COUNT(*) FROM batches")->fetchColumn();
$totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM staff_members")->fetchColumn();

// 2. Financial Metrics
$totalFeeCollected = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_transactions WHERE is_cancelled = 0")->fetchColumn();
$totalFeeAgreed = (float)$pdo->query("SELECT COALESCE(SUM(total_agreed_fee), 0) FROM enrollments")->fetchColumn();
$totalOutstandingDues = (float)$pdo->query("SELECT COALESCE(SUM(GREATEST(0, e.total_agreed_fee + COALESCE(f.fines, 0) - COALESCE(f.paid, 0))), 0) FROM enrollments e LEFT JOIN (SELECT enrollment_id, SUM(amount_paid) AS paid, SUM(fine_amount) AS fines FROM fee_transactions WHERE is_cancelled = 0 GROUP BY enrollment_id) f ON f.enrollment_id = e.id")->fetchColumn();

// 3. Needs Your Attention: Outstanding Student Fees
$outstandingStudents = $pdo->query("
    SELECT e.id as enrollment_id, s.id as student_id, s.full_name, s.student_reg_no, c.course_name, b.batch_code,
           e.total_agreed_fee,
           COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) as paid_amount,
           (e.total_agreed_fee + COALESCE((SELECT SUM(fine_amount) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0)) as balance_due
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN batches b ON e.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    WHERE e.status = 'active'
    HAVING balance_due > 0
    ORDER BY balance_due DESC
    LIMIT 5
")->fetchAll();

// 4. Needs Your Attention: Pending Staff Payroll
$pendingPayroll = $pdo->query("
    SELECT p.id, s.full_name, s.designation, p.month_name, p.year_num, p.salary_agreed, p.total_paid, p.dues
    FROM staff_payroll p
    JOIN staff_members s ON p.staff_id = s.id
    WHERE p.dues > 0
    ORDER BY p.dues DESC
    LIMIT 5
")->fetchAll();

// 5. Category Distribution for Doughnut Chart
$catStats = $pdo->query("
    SELECT c.category, COUNT(e.id) as student_count
    FROM courses c
    JOIN batches b ON b.course_id = c.id
    JOIN enrollments e ON e.batch_id = b.id
    GROUP BY c.category
    ORDER BY student_count DESC
    LIMIT 5
")->fetchAll();
$chartCategories = array_column($catStats, 'category');
$chartCatCounts = array_column($catStats, 'student_count');

// 6. Monthly Fee Collection Trend (Real Transactions)
$feeTrend = $pdo->query("
    SELECT DATE_FORMAT(payment_date, '%b %Y') as month_label, SUM(amount_paid) as monthly_sum
    FROM fee_transactions
    WHERE is_cancelled = 0 AND payment_date IS NOT NULL
    GROUP BY YEAR(payment_date), MONTH(payment_date)
    ORDER BY YEAR(payment_date) ASC, MONTH(payment_date) ASC
    LIMIT 8
")->fetchAll();
$chartFeeMonths = array_column($feeTrend, 'month_label');
$chartFeeSums = array_column($feeTrend, 'monthly_sum');

// 7. Recent Student Enrollments
$recentEnrollments = $pdo->query("
    SELECT e.id, s.id as student_id, s.full_name, s.student_reg_no, s.father_name, b.batch_code, c.course_name, e.total_agreed_fee, e.enrollment_date, e.status
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN batches b ON e.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    ORDER BY e.id DESC
    LIMIT 5
")->fetchAll();

// 8. KPK TTB Board Examination Top Position Achievers
$topPositions = $pdo->query("
    SELECT p.*, s.student_reg_no
    FROM academic_positions p
    LEFT JOIN students s ON p.student_id = s.id
    ORDER BY p.position_rank ASC
    LIMIT 5
")->fetchAll();
?>

<style>
/* Standardized Dashboard Layout & KPI System */
.dash-hero {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 12px;
    padding: 16px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
    position: relative;
}

.dash-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(235, 98, 38, 0.1);
    border: 1px solid rgba(235, 98, 38, 0.25);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    color: #eb6226;
    margin-bottom: 6px;
    letter-spacing: 0.02em;
}

.pulse-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #eb6226;
    box-shadow: 0 0 8px rgba(235, 98, 38, 0.6);
    animation: pulseGlow 2s infinite;
}

@keyframes pulseGlow {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

.dash-hero-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px 0;
    letter-spacing: -0.01em;
}

.dash-hero-sub {
    font-size: 0.82rem;
    color: var(--text-muted);
    margin: 0;
}

.dash-hero-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

/* 4-Card Standardized Hero KPI Grid */
.dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 18px;
    width: 100%;
    box-sizing: border-box;
}

.dash-kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 10px;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 118px;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    text-decoration: none;
    color: inherit;
    min-width: 0;
    box-sizing: border-box;
    box-shadow: var(--shadow-sm);
}

.dash-kpi-card:hover {
    transform: translateY(-2px);
    border-color: rgba(235, 98, 38, 0.4);
    box-shadow: var(--shadow-md);
}

.dash-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.dash-kpi-label {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.dash-kpi-icon {
    width: 38px;
    height: 38px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dash-kpi-val {
    font-size: 1.55rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.15;
    margin-bottom: 4px;
    letter-spacing: -0.02em;
}

.dash-kpi-sub {
    font-size: 11.5px;
    color: var(--text-dim);
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Charts Grid */
.dash-charts-grid {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 16px;
    margin-bottom: 18px;
    width: 100%;
    box-sizing: border-box;
}

.dash-charts-grid > * {
    min-width: 0;
    box-sizing: border-box;
}

@media (max-width: 960px) {
    .dash-charts-grid {
        grid-template-columns: 1fr;
    }
}

/* Operations 2-Column Split */
.dash-ops-grid {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 16px;
    width: 100%;
    box-sizing: border-box;
}

.dash-ops-grid > * {
    min-width: 0;
    box-sizing: border-box;
}

@media (max-width: 1024px) {
    .dash-ops-grid {
        grid-template-columns: 1fr;
    }
}

.card-header-clean {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-card);
}

.card-header-title {
    font-size: 14.5px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.card-header-sub {
    font-size: 11.5px;
    color: var(--text-muted);
    margin: 2px 0 0 0;
}

/* Rank badges for honor roll */
.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 800;
}
.rank-1 { background: rgba(251, 191, 36, 0.2); color: #f59e0b; border: 1px solid rgba(251, 191, 36, 0.5); }
.rank-2 { background: rgba(148, 163, 184, 0.2); color: #64748b; border: 1px solid rgba(148, 163, 184, 0.5); }
.rank-3 { background: rgba(249, 115, 22, 0.2); color: #eb6226; border: 1px solid rgba(249, 115, 22, 0.5); }
</style>

<?php
// Universal Print Letterhead (Rendered in Print View)
renderPrintHeader('Campus Executive Operations Summary', 'Live Institutional KPI Dashboard & Academic Operations');
?>

<!-- 1. Executive Welcome & Quick Actions Hero -->
<div class="dash-hero">
    <div class="dash-hero-info">
        <div class="dash-hero-badge">
            <span class="pulse-dot"></span>
            <span>ICT Swat Campus Operations &bull; Live</span>
        </div>
        <h1 class="dash-hero-title"><?= $timeGreeting ?>, <?= htmlspecialchars($currentUser['full_name'] ?: 'Administrator') ?> 👋</h1>
        <p class="dash-hero-sub"><?= date('l, d F Y') ?> &bull; Overview of student admissions, finances, faculty &amp; academic progress.</p>
    </div>
    <div class="dash-hero-actions">
        <?php if (in_array($userRole, ['admin', 'registrar'])): ?>
            <a href="<?= $baseUri ?>/modules/students/index.php?new=1" class="btn btn-primary btn-sm">
                <span>➕ Add Student</span>
            </a>
            <a href="<?= $baseUri ?>/modules/enrollments/index.php?new=1" class="btn btn-glass btn-sm">
                <span>📝 New Enrollment</span>
            </a>
            <!-- Add Subject Dropdown Quick Action -->
            <div class="dropdown-container" id="dashSubjectDropdown">
                <button type="button" class="btn btn-glass btn-sm dropdown-trigger" id="dashAddSubjectBtn" aria-expanded="false" aria-haspopup="true" style="display: inline-flex; align-items: center; gap: 8px;">
                    <span>➕ Add Subject</span>
                    <span class="dropdown-chevron">▼</span>
                </button>
                <div class="dropdown-menu-custom">
                    <div class="dropdown-header-label">Select Course Category</div>
                    <a href="<?= $baseUri ?>/modules/courses/index.php?new=1&category=<?= urlencode('Tuition Courses') ?>" class="dropdown-category-item" data-category="Tuition Courses">
                        <span class="dropdown-category-icon" style="background: rgba(56, 189, 248, 0.15); color: #0284c7;">📚</span>
                        <div class="dropdown-category-text">
                            <span class="dropdown-category-title">Tuition Courses</span>
                            <span class="dropdown-category-desc">School &amp; College subject tuitions</span>
                        </div>
                        <span class="dropdown-category-arrow">&rarr;</span>
                    </a>
                    <a href="<?= $baseUri ?>/modules/courses/index.php?new=1&category=<?= urlencode('Safety Courses') ?>" class="dropdown-category-item" data-category="Safety Courses">
                        <span class="dropdown-category-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">🦺</span>
                        <div class="dropdown-category-text">
                            <span class="dropdown-category-title">Safety Courses</span>
                            <span class="dropdown-category-desc">OSHA, IOSH, NEBOSH &amp; Industrial safety</span>
                        </div>
                        <span class="dropdown-category-arrow">&rarr;</span>
                    </a>
                    <a href="<?= $baseUri ?>/modules/courses/index.php?new=1&category=<?= urlencode('Computer Courses') ?>" class="dropdown-category-item" data-category="Computer Courses">
                        <span class="dropdown-category-icon" style="background: rgba(168, 85, 247, 0.15); color: #9333ea;">💻</span>
                        <div class="dropdown-category-text">
                            <span class="dropdown-category-title">Computer Courses</span>
                            <span class="dropdown-category-desc">IT, DIT, CIT, Graphic Design, Web &amp; Coding</span>
                        </div>
                        <span class="dropdown-category-arrow">&rarr;</span>
                    </a>
                    <a href="<?= $baseUri ?>/modules/courses/index.php?new=1&category=<?= urlencode('English Courses') ?>" class="dropdown-category-item" data-category="English Courses">
                        <span class="dropdown-category-icon" style="background: rgba(52, 211, 153, 0.15); color: #059669;">🌐</span>
                        <div class="dropdown-category-text">
                            <span class="dropdown-category-title">English Courses</span>
                            <span class="dropdown-category-desc">IELTS, Spoken English &amp; Grammar diplomas</span>
                        </div>
                        <span class="dropdown-category-arrow">&rarr;</span>
                    </a>
                    <a href="<?= $baseUri ?>/modules/courses/index.php?new=1&category=<?= urlencode('General Courses') ?>" class="dropdown-category-item" data-category="General Courses">
                        <span class="dropdown-category-icon" style="background: rgba(244, 114, 182, 0.15); color: #db2777;">🎯</span>
                        <div class="dropdown-category-text">
                            <span class="dropdown-category-title">General Courses</span>
                            <span class="dropdown-category-desc">Short skill programs &amp; Vocational diplomas</span>
                        </div>
                        <span class="dropdown-category-arrow">&rarr;</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
        <?php if (in_array($userRole, ['admin', 'accountant'])): ?>
            <a href="<?= $baseUri ?>/modules/fees/index.php?new=1" class="btn btn-glass btn-sm" style="background: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.35); color: #059669;">
                <span>💰 Receive Fee</span>
            </a>
        <?php endif; ?>
        <a href="<?= $baseUri ?>/modules/reports/index.php" class="btn btn-glass btn-sm" title="View Detailed Reports">
            <span>📊 Reports</span>
        </a>
        <button type="button" class="btn btn-glass btn-sm" onclick="window.print()" title="Print Dashboard Overview">
            <span>🖨️ Print</span>
        </button>
    </div>
</div>

<!-- 2. Core 4-Card Hero Metric Grid -->
<div class="dash-kpi-grid">
    <!-- Students KPI -->
    <a href="<?= $baseUri ?>/modules/students/index.php" class="dash-kpi-card">
        <div>
            <div class="dash-kpi-top">
                <span class="dash-kpi-label">Total Students</span>
                <span class="dash-kpi-icon" style="background: rgba(56, 189, 248, 0.12); color: #0284c7; border: 1px solid rgba(56, 189, 248, 0.25);">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
                </span>
            </div>
            <div class="dash-kpi-val"><?= number_format($totalStudents) ?></div>
        </div>
        <div class="dash-kpi-sub">
            <span style="color: #0284c7; font-weight: 600;"><?= number_format($activeEnrollments) ?></span> active in classes &rarr;
        </div>
    </a>

    <!-- Fee Revenue Collected KPI -->
    <a href="<?= $baseUri ?>/modules/fees/index.php" class="dash-kpi-card">
        <div>
            <div class="dash-kpi-top">
                <span class="dash-kpi-label">Revenue Collected</span>
                <span class="dash-kpi-icon" style="background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25);">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z"/></svg>
                </span>
            </div>
            <div class="dash-kpi-val" style="color: #059669;">Rs. <?= number_format($totalFeeCollected, 0) ?></div>
        </div>
        <div class="dash-kpi-sub">
            <span>Verified tuition payments &rarr;</span>
        </div>
    </a>

    <!-- Outstanding Dues KPI -->
    <a href="<?= $baseUri ?>/modules/fees/index.php?view=outstanding" class="dash-kpi-card">
        <div>
            <div class="dash-kpi-top">
                <span class="dash-kpi-label">Outstanding Dues</span>
                <span class="dash-kpi-icon" style="background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.25);">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                </span>
            </div>
            <div class="dash-kpi-val" style="color: #d97706;">Rs. <?= number_format($totalOutstandingDues, 0) ?></div>
        </div>
        <div class="dash-kpi-sub">
            <span>Pending receivables &rarr;</span>
        </div>
    </a>

    <!-- Academics & Faculty KPI -->
    <a href="<?= $baseUri ?>/modules/courses/index.php" class="dash-kpi-card">
        <div>
            <div class="dash-kpi-top">
                <span class="dash-kpi-label">Academics &amp; Staff</span>
                <span class="dash-kpi-icon" style="background: rgba(168, 85, 247, 0.12); color: #9333ea; border: 1px solid rgba(168, 85, 247, 0.25);">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/></svg>
                </span>
            </div>
            <div class="dash-kpi-val"><?= number_format($totalCourses) ?> Courses</div>
        </div>
        <div class="dash-kpi-sub">
            <span><?= number_format($totalBatches) ?> Batches &bull; <?= number_format($totalStaff) ?> Staff &rarr;</span>
        </div>
    </a>
</div>

<!-- 3. Visual Charts Grid (Monthly Trend & Technology Share) -->
<div class="dash-charts-grid">
    <!-- Fee Collection Trend Chart -->
    <div class="glass-card">
        <div class="card-header-clean">
            <div>
                <h2 class="card-header-title">Fee Collection Trend</h2>
                <p class="card-header-sub">Monthly institutional tuition revenue (PKR)</p>
            </div>
            <?php if (in_array($userRole, ['admin', 'accountant'])): ?>
                <a href="<?= $baseUri ?>/modules/fees/index.php" class="btn btn-glass btn-sm">Fee Ledger &rarr;</a>
            <?php endif; ?>
        </div>
        <div style="height: 260px; position: relative; width: 100%; max-width: 100%;">
            <canvas id="feeChart"></canvas>
        </div>
    </div>

    <!-- Academic Enrollment Share Chart -->
    <div class="glass-card">
        <div class="card-header-clean">
            <div>
                <h2 class="card-header-title">Technology Disciplines</h2>
                <p class="card-header-sub">Enrollment distribution by field</p>
            </div>
            <a href="<?= $baseUri ?>/modules/courses/index.php" class="btn btn-glass btn-sm">Courses &rarr;</a>
        </div>
        <div style="height: 260px; position: relative; width: 100%; max-width: 100%;">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<!-- 4. Operations Center (Side-by-Side Clean Layout) -->
<div class="dash-ops-grid">
    <!-- Left Column: Recent Student Admissions & Board Honors -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Recent Admissions -->
        <div class="glass-card">
            <div class="card-header-clean">
                <div>
                    <h2 class="card-header-title">Recent Student Admissions</h2>
                    <p class="card-header-sub">Latest students registered in campus batches</p>
                </div>
                <a href="<?= $baseUri ?>/modules/students/index.php" class="btn btn-glass btn-sm">All Students (<?= number_format($totalStudents) ?>) &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Batch</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentEnrollments)): ?>
                            <tr><td colspan="5" class="empty-state">No recent admissions recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentEnrollments as $enr): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #f8fafc;"><?= htmlspecialchars($enr['full_name']) ?></div>
                                        <div style="font-size: 11px; color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($enr['student_reg_no']) ?></div>
                                    </td>
                                    <td style="font-size: 12.5px;"><?= htmlspecialchars($enr['course_name']) ?></td>
                                    <td><span style="font-size: 11px; background: rgba(255,255,255,0.06); padding: 2px 7px; border-radius: 4px;"><?= htmlspecialchars($enr['batch_code']) ?></span></td>
                                    <td>
                                        <span class="status-badge <?= $enr['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= ucfirst(htmlspecialchars($enr['status'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="<?= $baseUri ?>/modules/students/view.php?id=<?= $enr['student_id'] ?>" class="btn btn-glass btn-sm">Profile</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- KPK Board Achievers Honor Roll -->
        <div class="glass-card">
            <div class="card-header-clean">
                <div>
                    <h2 class="card-header-title">🏆 KPK Board Merit Positions</h2>
                    <p class="card-header-sub">Trade Testing Board Peshawar (TTB) honor roll</p>
                </div>
                <a href="<?= $baseUri ?>/modules/academic-positions/index.php" class="btn btn-glass btn-sm">Gazette &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Rank</th>
                            <th>Student</th>
                            <th>Discipline</th>
                            <th>Session</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topPositions)): ?>
                            <tr><td colspan="4" class="empty-state">No merit records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($topPositions as $pos): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge rank-<?= (int)$pos['position_rank'] ?>">
                                            #<?= (int)$pos['position_rank'] ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($pos['student_name']) ?></strong></td>
                                    <td style="font-size: 12.5px; color: var(--text-muted);"><?= htmlspecialchars($pos['batch_name'] ?: ($pos['award_title'] ?? 'Merit Award')) ?></td>
                                    <td style="font-size: 12.5px;"><?= htmlspecialchars($pos['session_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Financial Attention & Staff Payroll -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Outstanding Fees Alert Card -->
        <div class="glass-card">
            <div class="card-header-clean">
                <div>
                    <h2 class="card-header-title" style="color: #fde68a;">⚠️ Outstanding Tuition Fees</h2>
                    <p class="card-header-sub">Pending balances requiring collection</p>
                </div>
                <a href="<?= $baseUri ?>/modules/fees/index.php?view=outstanding" class="btn btn-glass btn-sm" style="color: #fbbf24;">All Dues &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Due</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($outstandingStudents)): ?>
                            <tr><td colspan="3" class="empty-state" style="color: #34d399;">✓ All tuition fees are up to date.</td></tr>
                        <?php else: ?>
                            <?php foreach ($outstandingStudents as $out): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #f8fafc;"><?= htmlspecialchars($out['full_name']) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($out['course_name']) ?></div>
                                    </td>
                                    <td>
                                        <strong style="color: #f87171;">Rs. <?= number_format($out['balance_due'], 0) ?></strong>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if (in_array($userRole, ['admin', 'accountant'])): ?>
                                            <a href="<?= $baseUri ?>/modules/fees/index.php?enrollment_id=<?= $out['enrollment_id'] ?>" class="btn btn-primary btn-sm" style="padding: 4px 10px; font-size: 11.5px;">💰 Receive Payment</a>
                                        <?php else: ?>
                                            <a href="<?= $baseUri ?>/modules/students/view.php?id=<?= $out['student_id'] ?>" class="btn btn-glass btn-sm">View</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Staff Payroll Status Card -->
        <div class="glass-card">
            <div class="card-header-clean">
                <div>
                    <h2 class="card-header-title">👔 Staff &amp; Faculty Compensation</h2>
                    <p class="card-header-sub">Salary installments &amp; payroll disbursements</p>
                </div>
                <?php if ($userRole === 'admin'): ?>
                    <a href="<?= $baseUri ?>/modules/payroll/index.php" class="btn btn-glass btn-sm" style="color: #38bdf8;">Ledger &rarr;</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Faculty / Staff</th>
                            <th>Month</th>
                            <th>Pending</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingPayroll)): ?>
                            <tr><td colspan="4" class="empty-state" style="color: #34d399;">✓ All faculty salaries are fully disbursed.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pendingPayroll as $pay): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #f8fafc;"><?= htmlspecialchars($pay['full_name']) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($pay['designation']) ?></div>
                                    </td>
                                    <td style="font-size: 12px;"><?= htmlspecialchars($pay['month_name']) ?> <?= (int)$pay['year_num'] ?></td>
                                    <td>
                                        <strong style="color: #fbbf24;">Rs. <?= number_format($pay['dues'], 0) ?></strong>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($userRole === 'admin'): ?>
                                            <a href="<?= $baseUri ?>/modules/payroll/index.php" class="btn btn-glass btn-sm" style="padding: 4px 10px; font-size: 11.5px;">Disburse</a>
                                        <?php else: ?>
                                            <span class="status-badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<?php
// Official verification signatures for printable audit/executive review
renderPrintSignatures();
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        return;
    }
    // 1. Fee Collection Trend Chart (Smooth Line with Gradient Fill)
    const feeCtx = document.getElementById('feeChart');
    if (feeCtx) {
        new Chart(feeCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartFeeMonths) ?>,
                datasets: [{
                    label: 'Collections (PKR)',
                    data: <?= json_encode($chartFeeSums) ?>,
                    borderColor: '#38bdf8',
                    backgroundColor: 'rgba(56, 189, 248, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#38bdf8',
                    pointBorderColor: '#0f172a',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#131d35',
                        titleColor: '#f8fafc',
                        bodyColor: '#94a3b8',
                        borderColor: 'rgba(56, 189, 248, 0.3)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Rs. ' + Number(context.parsed.y).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.04)' },
                        ticks: { color: '#64748b', font: { size: 11 } }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.04)' },
                        ticks: {
                            color: '#64748b',
                            font: { size: 11 },
                            callback: function(value) {
                                return 'Rs. ' + Number(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Academic Enrollment Category Distribution (Clean Doughnut)
    const catCtx = document.getElementById('categoryChart');
    if (catCtx) {
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chartCategories) ?>,
                datasets: [{
                    data: <?= json_encode($chartCatCounts) ?>,
                    backgroundColor: [
                        '#38bdf8',
                        '#34d399',
                        '#a78bfa',
                        '#fbbf24',
                        '#f472b6'
                    ],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 12,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#131d35',
                        titleColor: '#f8fafc',
                        bodyColor: '#94a3b8',
                        borderColor: 'rgba(56, 189, 248, 0.3)',
                        borderWidth: 1,
                        padding: 10
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

