<?php
/**
 * Faculty & Staff Payroll Management Module
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['admin']);

$pdo = Database::getConnection();
$currentUser = getCurrentUser();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $month   = strtoupper(trim($_POST['month_name'] ?? ''));
        $year    = (int)($_POST['year_num'] ?? date('Y'));
        $salary  = (float)($_POST['salary_agreed'] ?? 0);
        $p1      = (float)($_POST['installment_p1'] ?? 0);
        $d1      = !empty($_POST['date_p1']) ? $_POST['date_p1'] : null;
        $p2      = (float)($_POST['installment_p2'] ?? 0);
        $d2      = !empty($_POST['date_p2']) ? $_POST['date_p2'] : null;
        $p3      = (float)($_POST['installment_p3'] ?? 0);
        $d3      = !empty($_POST['date_p3']) ? $_POST['date_p3'] : null;
        $totalPaid = $p1 + $p2 + $p3;
        $dues    = max(0.0, $salary - $totalPaid);

        if ($staffId <= 0 || empty($month) || $salary <= 0) {
            $error = "Staff member selection, month, and agreed salary are required.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO staff_payroll (staff_id, month_name, year_num, salary_agreed, installment_p1, date_p1, installment_p2, date_p2, installment_p3, date_p3, total_paid, dues)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$staffId, $month, $year, $salary, $p1, $d1, $p2, $d2, $p3, $d3, $totalPaid, $dues]);
                $message = "Payroll record for {$month} {$year} added successfully.";
            } catch (PDOException $e) {
                error_log("Create payroll error: " . $e->getMessage());
                $error = "An error occurred while saving the payroll record.";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM staff_payroll WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Payroll entry removed successfully.";
        } catch (PDOException $e) {
            error_log("Delete payroll error: " . $e->getMessage());
            $error = "An error occurred while removing the payroll entry.";
        }
    }
}

// Filters & Query Parameters
$staffFilter = (int)($_GET['staff_id'] ?? 0);
$yearFilter  = (int)($_GET['year'] ?? 0);

$whereConditions = [];
$params = [];

if ($staffFilter > 0) {
    $whereConditions[] = "p.staff_id = ?";
    $params[] = $staffFilter;
}
if ($yearFilter > 0) {
    $whereConditions[] = "p.year_num = ?";
    $params[] = $yearFilter;
}

$where = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Financial Summary Totals across all filtered records
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total_count,
           COALESCE(SUM(p.salary_agreed), 0) as total_budget,
           COALESCE(SUM(p.total_paid), 0) as total_paid_out,
           COALESCE(SUM(p.dues), 0) as total_outstanding
    FROM staff_payroll p
    {$where}
");
$countStmt->execute($params);
$totals = $countStmt->fetch(PDO::FETCH_ASSOC);

$totalRecords     = (int)($totals['total_count'] ?? 0);
$totalBudget      = (float)($totals['total_budget'] ?? 0);
$totalPaidOut     = (float)($totals['total_paid_out'] ?? 0);
$totalOutstanding = (float)($totals['total_outstanding'] ?? 0);

// Desktop-Fitted Pagination: default 10 records per page to fit screens without endless scrolling
$perPage = max(5, min(50, (int)($_GET['per_page'] ?? 10)));
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Paginated Records
$stmt = $pdo->prepare("
    SELECT p.*, s.full_name, s.staff_code, s.designation
    FROM staff_payroll p
    JOIN staff_members s ON p.staff_id = s.id
    {$where}
    ORDER BY p.year_num DESC, p.id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$payrollRecords = $stmt->fetchAll();

// Supporting Data
$staffList = $pdo->query("SELECT id, staff_code, full_name, base_salary, salary_type, salary_percentage, designation FROM staff_members ORDER BY full_name ASC")->fetchAll();
$yearsList = $pdo->query("SELECT DISTINCT year_num FROM staff_payroll ORDER BY year_num DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($yearsList)) {
    $yearsList = [(int)date('Y')];
}

$pageTitle = "Staff Payroll Management";
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* Streamlined Desktop-Fit Styling */
.payroll-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.payroll-metrics-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}

.metric-box {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 10px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    box-shadow: var(--shadow-sm);
    min-width: 0;
}

.metric-box .val {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.15;
}

.metric-box .lbl {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.03em;
    margin-bottom: 2px;
}

.metric-box .sub {
    font-size: 10.5px;
    color: var(--text-dim);
}

.metric-box .icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
}

.payroll-controls-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 14px;
}

.payroll-filter-form {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 0;
}

.payroll-filter-form select {
    min-height: 36px;
    padding: 6px 12px;
    font-size: 13px;
    border-radius: var(--radius-sm);
}

.payroll-card-fit {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    width: 100%;
    box-sizing: border-box;
}

.payroll-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
}

.payroll-table th {
    background: var(--bg-surface);
    color: var(--text-muted);
    font-weight: 600;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 11px 14px;
    border-bottom: 1px solid var(--border-card);
    white-space: nowrap;
}

.payroll-table td {
    padding: 11px 14px;
    border-bottom: 1px solid var(--border-card);
    color: var(--text-primary);
    vertical-align: middle;
}

.payroll-table tr:hover td {
    background: var(--bg-card-hover);
}

.payroll-table tr:last-child td {
    border-bottom: none;
}

.stage-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(21, 94, 239, 0.08);
    border: 1px solid rgba(21, 94, 239, 0.2);
    color: var(--primary);
    padding: 2px 7px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

[data-theme="dark"] .stage-pill {
    background: rgba(56, 189, 248, 0.1);
    border-color: rgba(56, 189, 248, 0.25);
    color: #38bdf8;
}

.payroll-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    border-top: 1px solid var(--border-card);
    background: var(--bg-surface);
    flex-wrap: wrap;
    gap: 10px;
    font-size: 12px;
}

.pg-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-card);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
}

.pg-link:hover:not(.active):not(.disabled) {
    background: var(--bg-card-hover);
    border-color: var(--primary);
    color: var(--primary);
}

.pg-link.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}

.pg-link.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
}
</style>

<!-- 1. Streamlined Header & Action -->
<div class="payroll-hero">
    <div>
        <h1 class="page-title" style="font-size:22px;margin:0 0 2px 0;">Faculty &amp; Staff Payroll</h1>
        <p class="page-breadcrumb" style="margin:0;">Disbursements, multi-stage installments &amp; arrears &bull; <strong><?= $totalRecords ?></strong> recorded entries</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addPayrollModal')">
            <span>➕ Record Monthly Payroll</span>
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding:10px 14px; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:#10b981; border-radius:8px; margin-bottom:12px; font-size:13px;">
        ✓ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:10px 14px; background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); color:#f43f5e; border-radius:8px; margin-bottom:12px; font-size:13px;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- 2. Compact 3-Metric Summary Bar -->
<div class="payroll-metrics-bar">
    <div class="metric-box">
        <div>
            <div class="lbl">Salary Budget</div>
            <div class="val">Rs. <?= number_format($totalBudget, 0) ?></div>
            <div class="sub">Total Agreed Compensation</div>
        </div>
        <div class="icon-wrap" style="background:rgba(21,94,239,0.1);color:var(--primary);">💰</div>
    </div>
    <div class="metric-box">
        <div>
            <div class="lbl" style="color:var(--accent-emerald);">Total Disbursed</div>
            <div class="val" style="color:var(--accent-emerald);">Rs. <?= number_format($totalPaidOut, 0) ?></div>
            <div class="sub">Paid out via P1, P2 &amp; P3</div>
        </div>
        <div class="icon-wrap" style="background:rgba(16,185,129,0.12);color:var(--accent-emerald);">✔</div>
    </div>
    <div class="metric-box">
        <div>
            <div class="lbl" style="color:var(--accent-amber);">Outstanding Dues</div>
            <div class="val" style="color:var(--accent-amber);">Rs. <?= number_format($totalOutstanding, 0) ?></div>
            <div class="sub">Pending Faculty Balances</div>
        </div>
        <div class="icon-wrap" style="background:rgba(245,158,11,0.12);color:var(--accent-amber);">⏳</div>
    </div>
</div>

<!-- 3. Sleek Single-Line Filter Bar -->
<div class="payroll-controls-bar">
    <form method="GET" action="" class="payroll-filter-form">
        <select name="staff_id" class="form-control" style="width:230px;">
            <option value="">All Faculty &amp; Staff (<?= count($staffList) ?>)</option>
            <?php foreach ($staffList as $st): ?>
                <option value="<?= $st['id'] ?>" <?= $staffFilter === (int)$st['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($st['staff_code']) ?> &bull; <?= htmlspecialchars($st['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="year" class="form-control" style="width:110px;">
            <option value="">All Years</option>
            <?php foreach ($yearsList as $yr): ?>
                <option value="<?= (int)$yr ?>" <?= $yearFilter === (int)$yr ? 'selected' : '' ?>><?= (int)$yr ?></option>
            <?php endforeach; ?>
        </select>

        <select name="per_page" class="form-control" style="width:110px;" onchange="this.form.submit()">
            <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10 / page</option>
            <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20 / page</option>
            <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50 / page</option>
        </select>

        <button type="submit" class="btn btn-glass btn-sm">Filter</button>
        <?php if ($staffFilter > 0 || $yearFilter > 0 || $perPage !== 10): ?>
            <a href="<?= $baseUri ?>/modules/payroll/index.php" class="btn btn-glass btn-sm" style="color:var(--text-muted);">Reset</a>
        <?php endif; ?>
    </form>
    <div style="font-size:12px;color:var(--text-muted);">
        Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalRecords ?> total)
    </div>
</div>

<!-- 4. Streamlined 6-Column Desktop-Fit Ledger Table -->
<div class="payroll-card-fit">
    <div class="table-responsive" style="border:none;border-radius:0;">
        <table class="payroll-table">
            <thead>
                <tr>
                    <th>Faculty Member</th>
                    <th>Period</th>
                    <th>Salary</th>
                    <th>Disbursed &amp; Stages</th>
                    <th>Dues / Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payrollRecords)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted);">
                            No payroll records found matching the criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payrollRecords as $p): ?>
                        <?php
                            $hasP1 = (float)$p['installment_p1'] > 0;
                            $hasP2 = (float)$p['installment_p2'] > 0;
                            $hasP3 = (float)$p['installment_p3'] > 0;
                            $isCleared = (float)$p['dues'] <= 0;
                        ?>
                        <tr>
                            <!-- 1. Faculty Member -->
                            <td>
                                <div style="font-weight:700;color:var(--text-primary);font-size:13.5px;"><?= htmlspecialchars($p['full_name']) ?></div>
                                <div style="display:flex;gap:6px;align-items:center;margin-top:2px;">
                                    <span style="font-family:monospace;font-size:11px;color:var(--primary);font-weight:600;"><?= htmlspecialchars($p['staff_code']) ?></span>
                                    <span style="font-size:11px;color:var(--text-dim);">&bull; <?= htmlspecialchars($p['designation'] ?: 'Faculty') ?></span>
                                </div>
                            </td>

                            <!-- 2. Disbursement Period -->
                            <td>
                                <strong style="color:var(--primary-light);font-size:13px;"><?= htmlspecialchars($p['month_name']) ?></strong>
                                <div style="font-size:11.5px;color:var(--text-dim);"><?= (int)$p['year_num'] ?></div>
                            </td>

                            <!-- 3. Agreed Salary -->
                            <td>
                                <div style="font-weight:600;">Rs. <?= number_format((float)$p['salary_agreed'], 0) ?></div>
                            </td>

                            <!-- 4. Disbursed & Consolidated Installments (Removed 3 separate wide columns) -->
                            <td>
                                <div style="font-weight:700;color:var(--accent-emerald);font-size:13.5px;margin-bottom:3px;">
                                    Rs. <?= number_format((float)$p['total_paid'], 0) ?>
                                </div>
                                <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;">
                                    <?php if ($hasP1): ?>
                                        <span class="stage-pill" title="P1: Rs. <?= number_format($p['installment_p1'], 0) ?><?= $p['date_p1'] ? ' on ' . date('d M Y', strtotime($p['date_p1'])) : '' ?>">
                                            P1: Rs. <?= number_format($p['installment_p1'], 0) ?>
                                            <?php if ($p['date_p1']): ?><span style="opacity:0.75;font-weight:normal;">(<?= date('M d', strtotime($p['date_p1'])) ?>)</span><?php endif; ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($hasP2): ?>
                                        <span class="stage-pill" title="P2: Rs. <?= number_format($p['installment_p2'], 0) ?><?= $p['date_p2'] ? ' on ' . date('d M Y', strtotime($p['date_p2'])) : '' ?>">
                                            P2: Rs. <?= number_format($p['installment_p2'], 0) ?>
                                            <?php if ($p['date_p2']): ?><span style="opacity:0.75;font-weight:normal;">(<?= date('M d', strtotime($p['date_p2'])) ?>)</span><?php endif; ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($hasP3): ?>
                                        <span class="stage-pill" title="P3: Rs. <?= number_format($p['installment_p3'], 0) ?><?= $p['date_p3'] ? ' on ' . date('d M Y', strtotime($p['date_p3'])) : '' ?>">
                                            P3: Rs. <?= number_format($p['installment_p3'], 0) ?>
                                            <?php if ($p['date_p3']): ?><span style="opacity:0.75;font-weight:normal;">(<?= date('M d', strtotime($p['date_p3'])) ?>)</span><?php endif; ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!$hasP1 && !$hasP2 && !$hasP3): ?>
                                        <span style="font-size:11.5px;color:var(--text-dim);">No disbursement</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- 5. Remaining Dues / Status -->
                            <td>
                                <?php if ($isCleared): ?>
                                    <span class="status-badge badge-active" style="font-size:11px;">✓ Fully Cleared</span>
                                <?php else: ?>
                                    <div style="font-weight:700;color:var(--accent-amber);font-size:13px;">
                                        Rs. <?= number_format((float)$p['dues'], 0) ?>
                                    </div>
                                    <span style="font-size:10.5px;color:var(--accent-amber);font-weight:600;">Pending Arrears</span>
                                <?php endif; ?>
                            </td>

                            <!-- 6. Actions -->
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeletePayroll(<?= $p['id'] ?>)" title="Delete Payroll Record" style="padding:4px 9px;min-height:28px;font-size:11.5px;">
                                    🗑 Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 5. Desktop-Fitted Pagination Controls -->
    <?php if ($totalRecords > 0): ?>
        <div class="payroll-pagination">
            <div style="color:var(--text-muted);">
                Showing <strong><?= $offset + 1 ?></strong>–<strong><?= min($offset + $perPage, $totalRecords) ?></strong> of <strong><?= $totalRecords ?></strong>
            </div>

            <?php if ($totalPages > 1): ?>
                <?php
                    $qsParams = $_GET;
                    $makePageUrl = function($pageNum) use ($qsParams) {
                        $qsParams['page'] = $pageNum;
                        return '?' . http_build_query($qsParams);
                    };
                ?>
                <div style="display:flex;gap:4px;align-items:center;">
                    <!-- Prev -->
                    <a href="<?= $page > 1 ? $makePageUrl($page - 1) : '#' ?>" class="pg-link <?= $page <= 1 ? 'disabled' : '' ?>">
                        &lsaquo;
                    </a>

                    <!-- Page Numbers -->
                    <?php
                        $startP = max(1, $page - 2);
                        $endP   = min($totalPages, $page + 2);
                        if ($startP > 1) {
                            echo '<a href="' . $makePageUrl(1) . '" class="pg-link">1</a>';
                            if ($startP > 2) echo '<span style="color:var(--text-dim);padding:0 2px;">&hellip;</span>';
                        }
                        for ($pNum = $startP; $pNum <= $endP; $pNum++) {
                            $activeClass = $pNum === $page ? 'active' : '';
                            echo '<a href="' . $makePageUrl($pNum) . '" class="pg-link ' . $activeClass . '">' . $pNum . '</a>';
                        }
                        if ($endP < $totalPages) {
                            if ($endP < $totalPages - 1) echo '<span style="color:var(--text-dim);padding:0 2px;">&hellip;</span>';
                            echo '<a href="' . $makePageUrl($totalPages) . '" class="pg-link">' . $totalPages . '</a>';
                        }
                    ?>

                    <!-- Next -->
                    <a href="<?= $page < $totalPages ? $makePageUrl($page + 1) : '#' ?>" class="pg-link <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        &rsaquo;
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Record Payroll -->
<div class="modal-overlay" id="addPayrollModal">
    <div class="modal-dialog" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">Record Monthly Payroll Disbursement</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addPayrollModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="pay_staff_id">Faculty / Staff Member <span class="required">*</span></label>
                    <select id="pay_staff_id" name="staff_id" class="form-control" required onchange="autoFillSalary(this)">
                        <option value="">Select staff member...</option>
                        <?php foreach ($staffList as $st): ?>
                            <?php
                                $salLabel = $st['salary_type'] === 'percentage'
                                    ? number_format((float)$st['salary_percentage'], 2) . '% of fees'
                                    : 'Rs. ' . number_format((float)$st['base_salary'], 0) . ' fixed';
                            ?>
                            <option value="<?= $st['id'] ?>"
                                data-salary="<?= (float)$st['base_salary'] ?>"
                                data-salary-type="<?= htmlspecialchars($st['salary_type']) ?>"
                                data-salary-pct="<?= (float)$st['salary_percentage'] ?>">
                                <?= htmlspecialchars($st['staff_code']) ?> — <?= htmlspecialchars($st['full_name']) ?> (<?= $salLabel ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="pay_month">Disbursement Month <span class="required">*</span></label>
                        <input type="text" id="pay_month" name="month_name" class="form-control" required placeholder="e.g. JANUARY, FEBRUARY" style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pay_year">Year <span class="required">*</span></label>
                        <input type="number" id="pay_year" name="year_num" class="form-control" required value="<?= date('Y') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pay_salary">Agreed Monthly Salary (PKR) <span class="required">*</span></label>
                    <input type="number" id="pay_salary" name="salary_agreed" class="form-control" required step="500">
                </div>

                <div style="border-top: 1px solid var(--border-card); padding-top: 16px; margin-top: 16px;">
                    <h4 style="font-size: 14px; color: var(--primary-light); margin-bottom: 12px;">Disbursement Installments (P1, P2, P3)</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Stage 1 Amount (P1)</label>
                            <input type="number" name="installment_p1" class="form-control" step="100" placeholder="0">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">P1 Payment Date</label>
                            <input type="date" name="date_p1" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Stage 2 Amount (P2)</label>
                            <input type="number" name="installment_p2" class="form-control" step="100" placeholder="0">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">P2 Payment Date</label>
                            <input type="date" name="date_p2" class="form-control">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Stage 3 Amount (P3)</label>
                            <input type="number" name="installment_p3" class="form-control" step="100" placeholder="0">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">P3 Payment Date</label>
                            <input type="date" name="date_p3" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addPayrollModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payroll Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-payroll-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_payroll_id">
</form>

<script>
function autoFillSalary(select) {
    const selectedOption = select.options[select.selectedIndex];
    const salary = selectedOption.getAttribute('data-salary');
    if (salary) {
        document.getElementById('pay_salary').value = salary;
    }
}

function confirmDeletePayroll(id) {
    confirmAction(
        'Delete Payroll Entry?',
        'Are you sure you want to remove this payroll record?',
        () => {
            document.getElementById('delete_payroll_id').value = id;
            document.getElementById('delete-payroll-form').submit();
        }
    );
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
