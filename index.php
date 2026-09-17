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
$totalFeeCollected = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_transactions")->fetchColumn();
$totalFeeAgreed = (float)$pdo->query("SELECT COALESCE(SUM(total_agreed_fee), 0) FROM enrollments")->fetchColumn();
$totalOutstandingDues = max(0.0, $totalFeeAgreed - $totalFeeCollected);

// 3. Needs Your Attention: Outstanding Student Fees
$outstandingStudents = $pdo->query("
    SELECT e.id as enrollment_id, s.id as student_id, s.full_name, s.student_reg_no, c.course_name, b.batch_code,
           e.total_agreed_fee,
           COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0) as paid_amount,
           (e.total_agreed_fee - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0)) as balance_due
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
    WHERE payment_date IS NOT NULL
    GROUP BY YEAR(payment_date), MONTH(payment_date)
    ORDER BY payment_date ASC
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
/* Fresh Dashboard Custom Styles */
.dash-hero {
    background: linear-gradient(135deg, rgba(19, 29, 53, 0.95) 0%, rgba(15, 23, 42, 0.85) 100%);
    border: 1px solid rgba(56, 189, 248, 0.2);
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    position: relative;
    overflow: hidden;
}

.dash-hero::after {
    content: "";
    position: absolute;
    top: -50px;
    right: -50px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.12) 0%, transparent 70%);
    pointer-events: none;
}

.dash-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(56, 189, 248, 0.1);
    border: 1px solid rgba(56, 189, 248, 0.25);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    color: #7dd3fc;
    margin-bottom: 6px;
    letter-spacing: 0.02em;
}

.pulse-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #38bdf8;
    box-shadow: 0 0 8px #38bdf8;
    animation: pulseGlow 2s infinite;
}

@keyframes pulseGlow {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

.dash-hero-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #f8fafc;
    margin: 0 0 4px 0;
    letter-spacing: -0.01em;
}

.dash-hero-sub {
    font-size: 0.82rem;
    color: #94a3b8;
    margin: 0;
}

.dash-hero-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

/* 4-Card Hero KPI Grid */
.dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 14px;
    margin-bottom: 16px;
    width: 100%;
    box-sizing: border-box;
}

.dash-kpi-card {
    background: #131d35;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    text-decoration: none;
    color: inherit;
    min-width: 0;
    box-sizing: border-box;
}

.dash-kpi-card:hover {
    transform: translateY(-2px);
    border-color: rgba(56, 189, 248, 0.35);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
}

.dash-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.dash-kpi-label {
    font-size: 12px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.dash-kpi-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.dash-kpi-val {
    font-size: 1.55rem;
    font-weight: 700;
    color: #f8fafc;
    line-height: 1.15;
    margin-bottom: 4px;
    letter-spacing: -0.02em;
}

.dash-kpi-sub {
    font-size: 11.5px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Charts Grid */
.dash-charts-grid {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
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
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.card-header-title {
    font-size: 15px;
    font-weight: 700;
    color: #f8fafc;
    margin: 0;
}

.card-header-sub {
    font-size: 12px;
    color: #94a3b8;
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
.rank-1 { background: rgba(251, 191, 36, 0.2); color: #fde68a; border: 1px solid rgba(251, 191, 36, 0.5); }
.rank-2 { background: rgba(203, 213, 225, 0.2); color: #f1f5f9; border: 1px solid rgba(203, 213, 225, 0.5); }
.rank-3 { background: rgba(249, 115, 22, 0.2); color: #fed7aa; border: 1px solid rgba(249, 115, 22, 0.5); }
</style>

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
            <a href="<?= $baseUri ?>/modules/students/index.php?new=1" class="btn btn-primary">
                <span>➕ Add Student</span>
            </a>
            <a href="<?= $baseUri ?>/modules/enrollments/index.php?new=1" class="btn btn-glass">
                <span>📝 New Enrollment</span>
            </a>
        <?php endif; ?>
        <?php if (in_array($userRole, ['admin', 'accountant'])): ?>
            <a href="<?= $baseUri ?>/modules/fees/index.php?new=1" class="btn btn-glass" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.4); color: #34d399;">
                <span>💰 Payment Received / Submit</span>
            </a>
        <?php endif; ?>
        <a href="<?= $baseUri ?>/modules/reports/index.php" class="btn btn-glass" title="View Detailed Reports">
            <span>📊 Reports Hub</span>
        </a>
    </div>
</div>

<!-- 2. Core 4-Card Hero Metric Grid -->
<div class="dash-kpi-grid">
    <!-- Students KPI -->
    <a href="<?= $baseUri ?>/modules/students/index.php" class="dash-kpi-card">
        <div>
            <div class="dash-kpi-top">
                <span class="dash-kpi-label">Students</span>
                <span class="dash-kpi-icon" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">🎓</span>
            </div>
            <div class="dash-kpi-val"><?= number_format($totalStudents) ?></div>
        </div>
        <div class="dash-kpi-sub">
            <span style="color: #38bdf8; font-weight: 600;"><?= number_format($activeEnrollments) ?></span> active in classes &rarr;
        </div>
    </a>

    <!-- Fee Revenue Collected KPI -->
    <a href="<?= $baseUri ?>/modules/fees/index.php" class="dash-kpi-card">
        <div>
            <div class="dash-kpi-top">
                <span class="dash-kpi-label">Revenue Collected</span>
                <span class="dash-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">💰</span>
            </div>
            <div class="dash-kpi-val" style="color: #6ee7b7;">Rs. <?= number_format($totalFeeCollected, 0) ?></div>
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
                <span class="dash-kpi-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">⏳</span>
            </div>
            <div class="dash-kpi-val" style="color: #fde68a;">Rs. <?= number_format($totalOutstandingDues, 0) ?></div>
        </div>
        <div class="dash-kpi-sub">
            <span>Pending student receivables &rarr;</span>
        </div>
    </a>

    <!-- Academics & Faculty KPI -->
    <a href="<?= $baseUri ?>/modules/courses/index.php" class="dash-kpi-card">
        <div>
            <div class="dash-kpi-top">
                <span class="dash-kpi-label">Academics &amp; Staff</span>
                <span class="dash-kpi-icon" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa;">🏛️</span>
            </div>
            <div class="dash-kpi-val"><?= number_format($totalCourses) ?> Courses</div>
        </div>
        <div class="dash-kpi-sub">
            <span><?= number_format($totalBatches) ?> Batches &bull; <?= number_format($totalStaff) ?> Staff Members &rarr;</span>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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

