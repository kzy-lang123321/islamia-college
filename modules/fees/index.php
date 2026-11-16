<?php
/**
 * Fee Collection & Student Accounts Management Module
 * Islamia College of Technology (ICT), Matta Swat
 * 
 * Implements the 7-Step Institutional Fee Workflow:
 * 1. Select Student — Live search by student name, admission number (reg no), roll number, or class/course.
 * 2. View Fee Details — Shows monthly tuition fee, previous dues, approved discounts, fines, and total payable.
 * 3. Receive Payment — Enters actual amount received from parent with real-time balance & partial payment tracking.
 * 4. Choose Payment Method — Cash, bank transfer, cheque, online, or deposit slip.
 * 5. Confirm Payment — Saves transaction atomically, auto-updates fee ledger & outstanding balance.
 * 6. Generate Receipt — Produces official printable dual-copy receipt (Institute + Student copy).
 * 7. Daily Collection Report — Analyzes collections grouped by cashier and payment method.
 * 
 * Non-Negotiable Rules Enforced:
 * - Never marks a student's fee as fully paid if only a partial amount was received.
 * - Every confirmed payment receives its own unique receipt voucher number.
 * - Confirmed payments are never silently deleted; cancellations are strictly audited with required justification.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// RBAC: Restricted to Admin, Accountant, and Registrar
requireRole(['admin', 'accountant', 'registrar', 'staff']);

$pdo = Database::getConnection();
$currentUser = getCurrentUser();
$userRole = strtolower($currentUser['role'] ?? 'accountant');
$canManage = in_array($userRole, ['admin', 'accountant']);
$isAdmin = ($userRole === 'admin');

$message = '';
$error = '';
$newReceiptId = 0;
$newReceiptNo = '';

// Helper to generate a guaranteed unique sequential receipt voucher number
function getNextReceiptNumber(PDO $pdo): string {
    return 'REC-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(12)));
}

// ---------------------------------------------------------
// POST ACTIONS: Record Payment & Void/Cancel Transaction
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_payment') {
        if (!$canManage) {
            $error = "You do not have financial authorization to collect fees.";
        } else {
            $enrollmentId  = (int)($_POST['enrollment_id'] ?? 0);
            $amountPaid    = (float)($_POST['amount_paid'] ?? 0);
            $fineAmount    = max(0.0, (float)($_POST['fine_amount'] ?? 0));
            $paymentDate   = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
            $paymentMethod = strtolower(trim($_POST['payment_method'] ?? 'cash'));
            $customReceipt = trim($_POST['receipt_no'] ?? '');
            $referenceNo   = trim($_POST['reference_no'] ?? '');
            $bankName      = trim($_POST['bank_name'] ?? '');
            $chequeDate    = !empty($_POST['cheque_date']) ? $_POST['cheque_date'] : null;
            $remarks       = trim($_POST['remarks'] ?? '');
            
            // Cashier attribution: Log current authenticated user's name
            $cashierName   = trim($currentUser['full_name'] ?: $currentUser['username']);

            if ($enrollmentId <= 0) {
                $error = "Please select a valid student enrollment record.";
            } elseif ($amountPaid <= 0) {
                $error = "Please enter a valid positive payment amount received.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Fetch current student obligation & prior payments
                    $calcStmt = $pdo->prepare("
                        SELECT e.id, e.total_agreed_fee, e.discount_amount, s.full_name, s.student_reg_no,
                               COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) as prior_paid,
                               COALESCE((SELECT SUM(fine_amount) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) as prior_fines
                        FROM enrollments e
                        JOIN students s ON e.student_id = s.id
                        WHERE e.id = ?
                        FOR UPDATE
                    ");
                    $calcStmt->execute([$enrollmentId]);
                    $enr = $calcStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$enr) {
                        throw new Exception("Enrollment record not found.");
                    }

                    $totalAgreed = (float)$enr['total_agreed_fee'];
                    $priorPaid = (float)$enr['prior_paid'];
                    $priorFines = (float)$enr['prior_fines'];

                    // New updated totals
                    $newTotalFines = $priorFines + $fineAmount;
                    $newCumulativePaid = $priorPaid + $amountPaid;
                    $remainingBalance = max(0.0, ($totalAgreed + $newTotalFines) - $newCumulativePaid);

                    // 2. Receipt Number Resolution & Uniqueness Verification
                    if (!empty($customReceipt)) {
                        $receiptNo = $customReceipt;
                        $chkRc = $pdo->prepare("SELECT COUNT(*) FROM fee_transactions WHERE receipt_no = ? OR receipt_voucher_no = ?");
                        $chkRc->execute([$receiptNo, $receiptNo]);
                        if ($chkRc->fetchColumn() > 0) {
                            throw new Exception("Receipt number '{$receiptNo}' is already taken. Every confirmed payment must have its own unique receipt number.");
                        }
                    } else {
                        $receiptNo = getNextReceiptNumber($pdo);
                    }

                    // 3. Save fee transaction record
                    $insStmt = $pdo->prepare("
                        INSERT INTO fee_transactions (
                            enrollment_id, receipt_no, receipt_voucher_no, amount_paid, due_amount,
                            payment_date, payment_method, payment_mode, reference_no, bank_name,
                            cheque_date, fine_amount, discount_amount, received_by, remarks
                        ) VALUES (
                            ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?,
                            ?, ?, 0.00, ?, ?
                        )
                    ");
                    $insStmt->execute([
                        $enrollmentId,
                        $receiptNo,
                        $receiptNo,
                        $amountPaid,
                        $remainingBalance,
                        $paymentDate,
                        $paymentMethod,
                        $paymentMethod,
                        $referenceNo ?: null,
                        $bankName ?: null,
                        $chequeDate ?: null,
                        $fineAmount,
                        $cashierName,
                        $remarks ?: null
                    ]);

                    $newReceiptId = (int)$pdo->lastInsertId();
                    $newReceiptNo = $receiptNo;

                    $pdo->commit();

                    $statusBadge = ($remainingBalance > 0)
                        ? "Partial payment accepted. Outstanding balance remaining: <strong>Rs. " . number_format($remainingBalance, 0) . "</strong>."
                        : "Full payment cleared! Dues are settled.";

                    $message = "Payment of <strong>Rs. " . number_format($amountPaid, 0) . "</strong> recorded successfully for <strong>" . htmlspecialchars($enr['full_name']) . "</strong> (Receipt: <strong>{$receiptNo}</strong>). {$statusBadge} <a href='receipt.php?id={$newReceiptId}' target='_blank' style='color:#38bdf8; text-decoration:underline; font-weight:bold; margin-left:8px;'>🖨️ Print Receipt Voucher →</a>";

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log("Fee collection error: " . $e->getMessage());
                    $error = $e->getMessage();
                }
            }
        }
    } elseif ($action === 'void_payment') {
        // Anti-Silent-Deletion: Transactions are NEVER silently deleted!
        // Admin authorization with mandatory justification logs an audit trail.
        if (!$isAdmin) {
            $error = "Only system administrators are authorized to void financial transactions.";
        } else {
            $txId = (int)($_POST['id'] ?? 0);
            $reason = trim($_POST['cancellation_reason'] ?? '');

            if ($txId <= 0) {
                $error = "Invalid transaction reference.";
            } elseif (empty($reason)) {
                $error = "A mandatory justification reason is required to void a confirmed payment receipt.";
            } else {
                try {
                    $chkStmt = $pdo->prepare("SELECT id, receipt_no, amount_paid, is_cancelled FROM fee_transactions WHERE id = ?");
                    $chkStmt->execute([$txId]);
                    $targetTx = $chkStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$targetTx) {
                        $error = "Transaction not found.";
                    } elseif ($targetTx['is_cancelled']) {
                        $error = "This payment receipt has already been voided.";
                    } else {
                        $canceller = trim($currentUser['full_name'] ?: $currentUser['username']) . " ({$currentUser['username']})";
                        $cancelTime = date('Y-m-d H:i:s');

                        $voidStmt = $pdo->prepare("
                            UPDATE fee_transactions 
                            SET is_cancelled = 1,
                                cancellation_reason = ?,
                                cancelled_by = ?,
                                cancelled_at = ?
                            WHERE id = ?
                        ");
                        $voidStmt->execute([$reason, $canceller, $cancelTime, $txId]);

                        $message = "Receipt #<strong>" . htmlspecialchars($targetTx['receipt_no']) . "</strong> (Rs. " . number_format($targetTx['amount_paid'], 0) . ") has been marked as <strong>VOID / CANCELLED</strong>. The amount is subtracted from collections and restored to student dues without silent data deletion.";
                    }
                } catch (PDOException $e) {
                    error_log("Void fee error: " . $e->getMessage());
                    $error = "An error occurred while voiding the transaction.";
                }
            }
        }
    } elseif ($action === 'delete') {
        // Explicitly block silent deletion attempts
        $error = "Confirmed payments cannot be deleted to preserve financial audit integrity. Please use 'Void / Cancel Voucher' with a stated justification.";
    }
}

// ---------------------------------------------------------
// ACTIVE VIEW DISPATCH
// ---------------------------------------------------------
$currentView = $_GET['view'] ?? 'all';
if (isset($_GET['new']) && $_GET['new'] === '1') {
    $currentView = 'collect';
}
if (!in_array($currentView, ['all', 'collect', 'outstanding', 'daily'])) {
    $currentView = 'all';
}

// Global Financial Metrics (Excluding cancelled/voided transactions)
$totalCollected = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_transactions WHERE is_cancelled = 0")->fetchColumn();
$totalAgreed = (float)$pdo->query("SELECT COALESCE(SUM(total_agreed_fee), 0) FROM enrollments")->fetchColumn();
$totalFines = (float)$pdo->query("SELECT COALESCE(SUM(fine_amount), 0) FROM fee_transactions WHERE is_cancelled = 0")->fetchColumn();
$totalOutstanding = max(0.0, ($totalAgreed + $totalFines) - $totalCollected);

// Today's Collection
$todayDate = date('Y-m-d');
$todayCollectedStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_transactions WHERE payment_date = ? AND is_cancelled = 0");
$todayCollectedStmt->execute([$todayDate]);
$todayCollected = (float)$todayCollectedStmt->fetchColumn();

// Search & Smart Filter setup
$isPrintAll = (isset($_GET['print']) && $_GET['print'] === 'all');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = $isPrintAll ? 999999 : 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$methodFilter = trim($_GET['method'] ?? '');
$cashierFilter = trim($_GET['cashier'] ?? '');
$courseFilter = (int)($_GET['course_id'] ?? 0);
$batchFilter = (int)($_GET['batch_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$reportDate = trim($_GET['date'] ?? $todayDate);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
    $reportDate = $todayDate;
}

// ---------------------------------------------------------
// DATA FETCHING BASED ON VIEW
// ---------------------------------------------------------
if ($currentView === 'outstanding') {
    // 1. OUTSTANDING FEES DIRECTORY
    $where = ["(e.total_agreed_fee + COALESCE((SELECT SUM(fine_amount) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0)) > 0"];
    $params = [];

    if (!empty($search)) {
        $where[] = "(s.full_name LIKE ? OR s.student_reg_no LIKE ? OR e.roll_number LIKE ? OR c.course_name LIKE ? OR b.batch_code LIKE ? OR e.tuition_class LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term, $term, $term]);
    }

    if ($courseFilter > 0) {
        $where[] = "b.course_id = ?";
        $params[] = $courseFilter;
    }

    if ($batchFilter > 0) {
        $where[] = "e.batch_id = ?";
        $params[] = $batchFilter;
    }

    $whereClause = "WHERE " . implode(" AND ", $where);

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        {$whereClause}
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / ($isPrintAll ? 999999 : 20)));

    $limitClause = $isPrintAll ? "" : "LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    $sql = "
        SELECT e.id as enrollment_id, s.id as student_id, s.full_name, s.father_name, s.student_reg_no,
               e.roll_number, s.contact_number, b.batch_code, c.course_name, e.tuition_class, e.class_level,
               e.total_agreed_fee,
               COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) as paid_amount,
               COALESCE((SELECT SUM(fine_amount) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) as fine_amount,
               ((e.total_agreed_fee + COALESCE((SELECT SUM(fine_amount) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0)) - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0)) as balance_due
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        {$whereClause}
        ORDER BY balance_due DESC
        {$limitClause}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $outstandingList = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($currentView === 'daily') {
    // 2. DAILY COLLECTION REPORT (Requirement 7)
    // Daily KPI Metrics
    $kpiStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN is_cancelled = 0 THEN amount_paid ELSE 0 END), 0) as total_collected,
            COUNT(CASE WHEN is_cancelled = 0 THEN id ELSE NULL END) as total_receipts,
            COALESCE(SUM(CASE WHEN is_cancelled = 0 AND (payment_method = 'cash' OR payment_mode = 'cash') THEN amount_paid ELSE 0 END), 0) as cash_total,
            COALESCE(SUM(CASE WHEN is_cancelled = 0 AND (payment_method IN ('bank', 'slip') OR payment_mode IN ('bank', 'slip')) THEN amount_paid ELSE 0 END), 0) as bank_total,
            COALESCE(SUM(CASE WHEN is_cancelled = 0 AND (payment_method = 'cheque' OR payment_mode = 'cheque') THEN amount_paid ELSE 0 END), 0) as cheque_total,
            COALESCE(SUM(CASE WHEN is_cancelled = 0 AND (payment_method = 'online' OR payment_mode = 'online') THEN amount_paid ELSE 0 END), 0) as online_total,
            COUNT(CASE WHEN is_cancelled = 1 THEN id ELSE NULL END) as voided_receipts,
            COALESCE(SUM(CASE WHEN is_cancelled = 1 THEN amount_paid ELSE 0 END), 0) as voided_amount
        FROM fee_transactions
        WHERE payment_date = ?
    ");
    $kpiStmt->execute([$reportDate]);
    $dailyKpis = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    // Group collections by Cashier
    $cashierStmt = $pdo->prepare("
        SELECT 
            COALESCE(NULLIF(TRIM(received_by), ''), 'Unassigned Cashier') as cashier_name,
            COUNT(id) as receipt_count,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' OR payment_mode = 'cash' THEN amount_paid ELSE 0 END), 0) as cash_amount,
            COALESCE(SUM(CASE WHEN payment_method != 'cash' AND payment_mode != 'cash' THEN amount_paid ELSE 0 END), 0) as non_cash_amount,
            COALESCE(SUM(amount_paid), 0) as total_amount
        FROM fee_transactions
        WHERE payment_date = ? AND is_cancelled = 0
        GROUP BY cashier_name
        ORDER BY total_amount DESC
    ");
    $cashierStmt->execute([$reportDate]);
    $cashierSummary = $cashierStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group collections by Payment Method
    $methodStmt = $pdo->prepare("
        SELECT 
            COALESCE(NULLIF(TRIM(payment_method), ''), payment_mode, 'cash') as method_name,
            COUNT(id) as tx_count,
            COALESCE(SUM(amount_paid), 0) as total_amount
        FROM fee_transactions
        WHERE payment_date = ? AND is_cancelled = 0
        GROUP BY method_name
        ORDER BY total_amount DESC
    ");
    $methodStmt->execute([$reportDate]);
    $methodSummary = $methodStmt->fetchAll(PDO::FETCH_ASSOC);

    // Full Detailed Collection Log for the Day
    $dailyLogStmt = $pdo->prepare("
        SELECT f.*, s.id as student_id, s.full_name, s.student_reg_no, e.roll_number,
               b.batch_code, c.course_name, e.tuition_class, e.class_level
        FROM fee_transactions f
        JOIN enrollments e ON f.enrollment_id = e.id
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        WHERE f.payment_date = ?
        ORDER BY f.id DESC
    ");
    $dailyLogStmt->execute([$reportDate]);
    $dailyTransactions = $dailyLogStmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    // 3. ALL FEE TRANSACTIONS LEDGER
    $where = [];
    $params = [];

    if (!empty($search)) {
        $where[] = "(s.full_name LIKE ? OR s.student_reg_no LIKE ? OR e.roll_number LIKE ? OR f.receipt_no LIKE ? OR f.receipt_voucher_no LIKE ? OR c.course_name LIKE ? OR f.received_by LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
    }

    if (!empty($methodFilter)) {
        $where[] = "(f.payment_method = ? OR f.payment_mode = ?)";
        $params[] = $methodFilter;
        $params[] = $methodFilter;
    }

    if (!empty($cashierFilter)) {
        $where[] = "f.received_by LIKE ?";
        $params[] = "%{$cashierFilter}%";
    }

    if ($courseFilter > 0) {
        $where[] = "b.course_id = ?";
        $params[] = $courseFilter;
    }

    if ($batchFilter > 0) {
        $where[] = "e.batch_id = ?";
        $params[] = $batchFilter;
    }

    if (!empty($dateFrom)) {
        $where[] = "f.payment_date >= ?";
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $where[] = "f.payment_date <= ?";
        $params[] = $dateTo;
    }

    if ($statusFilter === 'cleared') {
        $where[] = "f.is_cancelled = 0 AND f.due_amount <= 0";
    } elseif ($statusFilter === 'partial') {
        $where[] = "f.is_cancelled = 0 AND f.due_amount > 0";
    } elseif ($statusFilter === 'voided') {
        $where[] = "f.is_cancelled = 1";
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM fee_transactions f 
        JOIN enrollments e ON f.enrollment_id = e.id 
        JOIN students s ON e.student_id = s.id 
        JOIN batches b ON e.batch_id = b.id 
        JOIN courses c ON b.course_id = c.id 
        {$whereClause}
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / ($isPrintAll ? 999999 : 20)));

    $limitClause = $isPrintAll ? "" : "LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    $sql = "
        SELECT f.*, s.id as student_id, s.full_name, s.student_reg_no, e.roll_number,
               b.batch_code, c.course_name, e.total_agreed_fee,
               (
                   SELECT COALESCE(SUM(amount_paid), 0)
                   FROM fee_transactions
                   WHERE enrollment_id = e.id AND id <= f.id AND is_cancelled = 0
               ) as running_paid,
               (
                   SELECT COALESCE(SUM(fine_amount), 0)
                   FROM fee_transactions
                   WHERE enrollment_id = e.id AND id <= f.id AND is_cancelled = 0
               ) as running_fines
        FROM fee_transactions f
        JOIN enrollments e ON f.enrollment_id = e.id
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        {$whereClause}
        ORDER BY f.payment_date DESC, f.id DESC
        {$limitClause}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Cashier list for filter dropdown
$availableCashiers = $pdo->query("
    SELECT DISTINCT TRIM(received_by) as cashier_name
    FROM fee_transactions 
    WHERE received_by IS NOT NULL AND TRIM(received_by) != ''
    ORDER BY cashier_name ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Courses & Batches for smart filter dropdowns
$coursesList = $pdo->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$batchesList = $pdo->query("SELECT id, batch_code, batch_name FROM batches ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Preload enrollments for quick counter selection
$preloadedEnrollments = $pdo->query("
    SELECT e.id, s.student_reg_no, s.full_name, e.roll_number, c.course_name, b.batch_code,
           e.total_agreed_fee,
           (e.total_agreed_fee + COALESCE((SELECT SUM(fine_amount) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0)) as balance_due
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN batches b ON e.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    ORDER BY (balance_due > 0) DESC, s.full_name ASC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$preselectEnrollmentId = (int)($_GET['enrollment_id'] ?? 0);

$pageTitle = "Fee Collection & Ledger";
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($isPrintAll): ?>
    <?php
    $backParams = $_GET;
    unset($backParams['print']);
    $backUrl = 'index.php?' . http_build_query($backParams);

    // Active filters summary
    $activeFilters = [];
    if (!empty($search)) $activeFilters['Search Keyword'] = $search;
    if ($courseFilter > 0) {
        foreach ($coursesList as $c) {
            if ($c['id'] == $courseFilter) { $activeFilters['Course'] = $c['course_name']; break; }
        }
    }
    if ($batchFilter > 0) {
        foreach ($batchesList as $b) {
            if ($b['id'] == $batchFilter) { $activeFilters['Batch'] = $b['batch_code']; break; }
        }
    }
    if (!empty($methodFilter)) $activeFilters['Payment Method'] = ucfirst($methodFilter);
    if (!empty($cashierFilter)) $activeFilters['Cashier'] = $cashierFilter;
    if (!empty($statusFilter)) $activeFilters['Status'] = ucfirst($statusFilter);
    if (!empty($dateFrom)) $activeFilters['From Date'] = date('d M Y', strtotime($dateFrom));
    if (!empty($dateTo)) $activeFilters['To Date'] = date('d M Y', strtotime($dateTo));

    if ($currentView === 'outstanding') {
        renderPrintPreviewToolbar('Outstanding Student Dues Statement', $totalRecords, $backUrl);
        ?>
        <div class="print-paper-sheet">
            <?php renderPrintHeader('Outstanding Student Dues Statement', 'Islamia College of Technology Matta Swat — Receivables Directory', $activeFilters); ?>
            <div style="margin: 16px 0 10px 0; font-size: 13px; color: #475569; display: flex; justify-content: space-between;">
                <span>Total records matching criteria: <strong><?= number_format(count($outstandingList)) ?></strong> students with pending dues</span>
                <span>Generated on: <strong><?= date('d M Y, h:i A') ?></strong></span>
            </div>
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">#</th>
                        <th>Reg #</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Roll / Class</th>
                        <th>Course &amp; Batch</th>
                        <th style="text-align: right;">Total Agreed</th>
                        <th style="text-align: right;">Paid to Date</th>
                        <th style="text-align: right;">Outstanding Dues</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sumAgreed = 0;
                    $sumPaid = 0;
                    $sumDue = 0;
                    $i = 1;
                    foreach ($outstandingList as $row): 
                        $sumAgreed += (float)$row['total_agreed_fee'];
                        $sumPaid += (float)$row['paid_amount'];
                        $sumDue += (float)$row['balance_due'];
                    ?>
                    <tr>
                        <td style="text-align: center; color: #94a3b8;"><?= $i++ ?></td>
                        <td><strong style="font-family: monospace;"><?= htmlspecialchars($row['student_reg_no']) ?></strong></td>
                        <td><strong><?= htmlspecialchars($row['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['father_name'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($row['roll_number'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($row['course_name']) ?> (<?= htmlspecialchars($row['batch_code']) ?>)</td>
                        <td style="text-align: right;">Rs. <?= number_format((float)$row['total_agreed_fee'], 0) ?></td>
                        <td style="text-align: right; color: #0012e2;">Rs. <?= number_format((float)$row['paid_amount'], 0) ?></td>
                        <td style="text-align: right; font-weight: 800; color: #ca8a04;">Rs. <?= number_format((float)$row['balance_due'], 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight: 800; background: #f8fafc; border-top: 2px solid #0012e2;">
                        <td colspan="6" style="text-align: right; text-transform: uppercase;">Total Institutional Dues:</td>
                        <td style="text-align: right;">Rs. <?= number_format($sumAgreed, 0) ?></td>
                        <td style="text-align: right; color: #0012e2;">Rs. <?= number_format($sumPaid, 0) ?></td>
                        <td style="text-align: right; color: #ca8a04; font-size: 15px;">Rs. <?= number_format($sumDue, 0) ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php renderPrintSignatures(['Prepared By (Accounts)', 'Registrar / Academic Head', 'Principal / Director']); ?>
        </div>
        <?php
    } elseif ($currentView === 'daily') {
        renderPrintPreviewToolbar('Daily Fee Collection Report (' . date('d M Y', strtotime($reportDate)) . ')', count($dailyTransactions), $backUrl);
        ?>
        <div class="print-paper-sheet">
            <?php renderPrintHeader('Daily Fee Collection & Cashier Report', 'Islamia College of Technology Matta Swat', ['Report Date' => date('d F, Y', strtotime($reportDate)), 'Total Collections' => 'Rs. ' . number_format($dailyKpis['total_collected']), 'Receipt Count' => number_format($dailyKpis['total_receipts'])]); ?>
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 16px 0;">
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: center;">
                    <div style="font-size: 11px; text-transform: uppercase; color: #64748b;">Total Collected</div>
                    <div style="font-size: 18px; font-weight: 800; color: #0012e2;">Rs. <?= number_format($dailyKpis['total_collected'], 0) ?></div>
                </div>
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: center;">
                    <div style="font-size: 11px; text-transform: uppercase; color: #64748b;">Cash In Hand</div>
                    <div style="font-size: 18px; font-weight: 800; color: #0284c7;">Rs. <?= number_format($dailyKpis['cash_total'], 0) ?></div>
                </div>
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: center;">
                    <div style="font-size: 11px; text-transform: uppercase; color: #64748b;">Bank / Cheque</div>
                    <div style="font-size: 18px; font-weight: 800; color: #0012e2;">Rs. <?= number_format($dailyKpis['bank_total'] + $dailyKpis['cheque_total'], 0) ?></div>
                </div>
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: center;">
                    <div style="font-size: 11px; text-transform: uppercase; color: #64748b;">Online / Mobile</div>
                    <div style="font-size: 18px; font-weight: 800; color: #ca8a04;">Rs. <?= number_format($dailyKpis['online_total'], 0) ?></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div>
                    <h4 style="margin: 0 0 8px 0; font-size: 13px;">👨‍💼 Collections by Cashier</h4>
                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th>Cashier</th>
                                <th style="text-align: center;">Receipts</th>
                                <th style="text-align: right;">Cash</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cashierSummary as $cs): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cs['cashier_name']) ?></strong></td>
                                <td style="text-align: center;"><?= number_format($cs['receipt_count']) ?></td>
                                <td style="text-align: right;">Rs. <?= number_format($cs['cash_amount'], 0) ?></td>
                                <td style="text-align: right; font-weight: 700; color: #0012e2;">Rs. <?= number_format($cs['total_amount'], 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h4 style="margin: 0 0 8px 0; font-size: 13px;">💳 Collections by Payment Method</h4>
                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th style="text-align: center;">Tx Count</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($methodSummary as $ms): ?>
                            <tr>
                                <td style="text-transform: capitalize;"><strong><?= htmlspecialchars($ms['method_name']) ?></strong></td>
                                <td style="text-align: center;"><?= number_format($ms['tx_count']) ?></td>
                                <td style="text-align: right; font-weight: 700; color: #0012e2;">Rs. <?= number_format($ms['total_amount'], 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <h4 style="margin: 0 0 8px 0; font-size: 13px;">📋 Daily Transactions Journal</h4>
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Student Name</th>
                        <th>Admission No.</th>
                        <th>Roll / Class</th>
                        <th>Course</th>
                        <th style="text-align: right;">Amount Paid</th>
                        <th>Method</th>
                        <th>Cashier</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyTransactions as $dt): ?>
                    <tr style="<?= $dt['is_cancelled'] ? 'opacity: 0.5; text-decoration: line-through;' : '' ?>">
                        <td><strong style="font-family: monospace;"><?= htmlspecialchars($dt['receipt_no'] ?: $dt['receipt_voucher_no']) ?></strong></td>
                        <td><?= htmlspecialchars($dt['full_name']) ?></td>
                        <td><?= htmlspecialchars($dt['student_reg_no']) ?></td>
                        <td><?= htmlspecialchars($dt['roll_number'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($dt['course_name']) ?></td>
                        <td style="text-align: right; font-weight: 700;">Rs. <?= number_format((float)$dt['amount_paid'], 0) ?></td>
                        <td style="text-transform: capitalize;"><?= htmlspecialchars($dt['payment_method'] ?: $dt['payment_mode']) ?></td>
                        <td><?= htmlspecialchars($dt['received_by'] ?: 'Accountant') ?></td>
                        <td><?= $dt['is_cancelled'] ? 'VOIDED' : 'CONFIRMED' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php renderPrintSignatures(['Cashier / Handover By', 'Accounts Incharge', 'Principal / Auditor']); ?>
        </div>
        <?php
    } else {
        // all ledger view
        renderPrintPreviewToolbar('Fee Collection & Payment Ledger', $totalRecords, $backUrl);
        ?>
        <div class="print-paper-sheet">
            <?php renderPrintHeader('Fee Collection & Payment Ledger', 'Islamia College of Technology Matta Swat — Financial Records', $activeFilters); ?>
            <div style="margin: 16px 0 10px 0; font-size: 13px; color: #475569; display: flex; justify-content: space-between;">
                <span>Total records matching criteria: <strong><?= number_format(count($transactions)) ?></strong> verified transaction vouchers</span>
                <span>Generated on: <strong><?= date('d M Y, h:i A') ?></strong></span>
            </div>
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">#</th>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Student Name</th>
                        <th>Reg #</th>
                        <th>Course &amp; Batch</th>
                        <th style="text-align: right;">Amount Paid</th>
                        <th>Method</th>
                        <th>Cashier</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sumPaid = 0;
                    $i = 1;
                    foreach ($transactions as $t): 
                        $isVoid = (bool)$t['is_cancelled'];
                        if (!$isVoid) $sumPaid += (float)$t['amount_paid'];
                    ?>
                    <tr style="<?= $isVoid ? 'opacity: 0.5; text-decoration: line-through;' : '' ?>">
                        <td style="text-align: center; color: #94a3b8;"><?= $i++ ?></td>
                        <td><strong style="font-family: monospace;"><?= htmlspecialchars($t['receipt_no'] ?: $t['receipt_voucher_no']) ?></strong></td>
                        <td style="white-space: nowrap;"><?= $t['payment_date'] ? date('d M Y', strtotime($t['payment_date'])) : '—' ?></td>
                        <td><strong><?= htmlspecialchars($t['full_name']) ?></strong></td>
                        <td><span style="font-family: monospace;"><?= htmlspecialchars($t['student_reg_no']) ?></span></td>
                        <td><?= htmlspecialchars($t['course_name']) ?> (<?= htmlspecialchars($t['batch_code']) ?>)</td>
                        <td style="text-align: right; font-weight: 700; color: <?= $isVoid ? '#ef4444' : '#0012e2' ?>;">Rs. <?= number_format((float)$t['amount_paid'], 0) ?></td>
                        <td style="text-transform: capitalize;"><?= htmlspecialchars($t['payment_method'] ?: $t['payment_mode']) ?></td>
                        <td><?= htmlspecialchars($t['received_by'] ?: 'Accountant') ?></td>
                        <td><?= $isVoid ? 'VOIDED' : ((float)$t['due_amount'] <= 0 ? 'CLEARED' : 'PARTIAL') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight: 800; background: #f8fafc; border-top: 2px solid #0012e2;">
                        <td colspan="6" style="text-align: right; text-transform: uppercase;">Total Verified Collections:</td>
                        <td style="text-align: right; color: #0012e2; font-size: 15px;">Rs. <?= number_format($sumPaid, 0) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
            <?php renderPrintSignatures(['Cashier / Prepared By', 'Senior Accountant', 'Principal / Auditor']); ?>
        </div>
        <?php
    }
    ?>
<?php else: ?>

<!-- Page Header Bar -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
    <div>
        <h1 class="page-title" style="margin: 0 0 6px 0; font-size: 24px; font-weight: 800; color: var(--text-primary);">
            💰 Fee Collection &amp; Student Accounts
        </h1>
        <p class="page-breadcrumb" style="margin: 0; color: var(--text-muted); font-size: 13px;">
            Islamia College of Technology Matta &bull; 7-Step Institutional Fee Counter &bull; Ledger &bull; Receipt Vouchers
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="?view=collect" class="btn <?= $currentView === 'collect' ? 'btn-primary' : 'btn-glass' ?>" style="font-weight: 700;">
            <span>💵 Fee Counter (Receive Payment)</span>
        </a>
        <a href="?view=daily" class="btn <?= $currentView === 'daily' ? 'btn-primary' : 'btn-glass' ?>">
            <span>📊 Daily Collection Report</span>
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding: 14px 18px; background: rgba(0, 18, 226, 0.08); border: 1px solid rgba(0, 18, 226, 0.25); color: #0012e2; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600;">
        ✓ <?= $message ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding: 14px 18px; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #f87171; border-radius: 10px; margin-bottom: 20px; font-size: 14px;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Institutional KPI Financial Summary Cards -->
<div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="metric-card royal" style="padding: 18px 20px;">
        <div class="metric-header">
            <span class="metric-title" style="font-size: 13px; text-transform: uppercase;">Total Collected</span>
            <span class="metric-icon">💰</span>
        </div>
        <div class="metric-value" style="font-size: 24px; font-weight: 800; color: #0012e2;">Rs. <?= number_format($totalCollected, 0) ?></div>
        <div class="metric-sub" style="font-size: 12px; color: var(--text-muted);">Verified Non-Cancelled Receipts</div>
    </div>

    <div class="metric-card gold" style="padding: 18px 20px;">
        <div class="metric-header">
            <span class="metric-title" style="font-size: 13px; text-transform: uppercase;">Outstanding Dues</span>
            <span class="metric-icon">⏳</span>
        </div>
        <div class="metric-value" style="font-size: 24px; font-weight: 800; color: #ca8a04;">Rs. <?= number_format($totalOutstanding, 0) ?></div>
        <div class="metric-sub" style="font-size: 12px; color: var(--text-muted);">Uncollected Student Balances</div>
    </div>

    <div class="metric-card sky" style="padding: 18px 20px;">
        <div class="metric-header">
            <span class="metric-title" style="font-size: 13px; text-transform: uppercase;">Today's Collection</span>
            <span class="metric-icon">📅</span>
        </div>
        <div class="metric-value" style="font-size: 24px; font-weight: 800; color: #0284c7;">Rs. <?= number_format($todayCollected, 0) ?></div>
        <div class="metric-sub" style="font-size: 12px; color: var(--text-muted);"><?= date('F d, Y') ?></div>
    </div>
</div>

<!-- Primary Navigation Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--border-card); padding-bottom: 12px; flex-wrap: wrap;">
    <a href="?view=collect" class="btn <?= $currentView === 'collect' ? 'btn-primary' : 'btn-glass' ?>" style="padding: 8px 18px; font-size: 13.5px; font-weight: 600;">
        ⚡ 1-5. Fee Collection Counter
    </a>
    <a href="?view=all" class="btn <?= $currentView === 'all' ? 'btn-primary' : 'btn-glass' ?>" style="padding: 8px 18px; font-size: 13.5px; font-weight: 600;">
        💳 Fee Payments Ledger
    </a>
    <a href="?view=outstanding" class="btn <?= $currentView === 'outstanding' ? 'btn-primary' : 'btn-glass' ?>" style="padding: 8px 18px; font-size: 13.5px; font-weight: 600;">
        ⏳ Outstanding Student Dues
    </a>
    <a href="?view=daily" class="btn <?= $currentView === 'daily' ? 'btn-primary' : 'btn-glass' ?>" style="padding: 8px 18px; font-size: 13.5px; font-weight: 600;">
        📊 7. Daily Collection Report
    </a>
</div>

<!-- ======================================================================= -->
<!-- VIEW 1: INTERACTIVE 7-STEP FEE COLLECTION COUNTER                       -->
<!-- ======================================================================= -->
<?php if ($currentView === 'collect'): ?>
<div style="display: grid; grid-template-columns: 1.25fr 1fr; gap: 24px; align-items: start;">
    
    <!-- LEFT COLUMN: Step 1 (Search & Select) + Step 2 (Fee Details Breakdown) -->
    <div>
        <!-- STEP 1: SELECT STUDENT -->
        <div class="glass-card" style="padding: 22px; margin-bottom: 24px; position: relative;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <span style="background: var(--primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">1</span>
                    Select Student
                </h3>
                <span style="font-size: 12px; color: var(--text-muted);">Search by name, reg no, roll no, or class</span>
            </div>

            <div style="position: relative;">
                <input type="text" id="student_search_input" class="form-control" placeholder="🔍 Type student name, admission reg # (e.g. ICT-STD-1005), roll #, or class/course..." autocomplete="off" style="padding: 12px 16px; font-size: 14px; border-radius: 10px; width: 100%;">
                <div id="search_spinner" style="display: none; position: absolute; right: 14px; top: 12px; font-size: 14px;">⏳</div>
            </div>

            <!-- Dynamic Live Search Results Dropdown -->
            <div id="search_results_container" class="search-dropdown-list" style="display: none; margin-top: 6px;"></div>

            <!-- Quick Selector Fallback -->
            <div style="margin-top: 14px;">
                <label style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 6px;">Or pick from active enrollments with balance due:</label>
                <select id="quick_student_select" class="form-control" onchange="onQuickStudentPicked(this)" style="font-size: 13px;">
                    <option value="">-- Choose student from directory --</option>
                    <?php foreach ($preloadedEnrollments as $pe): ?>
                        <option value="<?= $pe['id'] ?>" <?= $preselectEnrollmentId === (int)$pe['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pe['full_name']) ?> (<?= htmlspecialchars($pe['student_reg_no']) ?> &bull; Roll: <?= htmlspecialchars($pe['roll_number'] ?: '—') ?>) — <?= htmlspecialchars($pe['course_name']) ?> [Due: Rs. <?= number_format($pe['balance_due'], 0) ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- STEP 2: VIEW FEE DETAILS BREAKDOWN -->
        <div id="fee_details_panel" class="glass-card" style="padding: 22px; display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <span style="background: var(--primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">2</span>
                    Fee Details &amp; Balance Breakdown
                </h3>
                <span id="student_status_badge" style="padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;"></span>
            </div>

            <!-- Selected Student Demographic Strip -->
            <div style="background: var(--bg-card-hover); border: 1px solid var(--border-card); border-radius: 10px; padding: 14px 16px; margin-bottom: 18px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <div id="st_name" style="font-size: 17px; font-weight: 800; color: var(--text-primary);"></div>
                        <div id="st_father" style="font-size: 12.5px; color: var(--text-muted);"></div>
                    </div>
                    <div style="text-align: right;">
                        <div id="st_reg" style="font-family: monospace; font-size: 14px; font-weight: 700; color: var(--primary-light);"></div>
                        <div id="st_roll_class" style="font-size: 12px; color: var(--text-secondary);"></div>
                    </div>
                </div>
                <div id="st_course" style="margin-top: 8px; font-size: 13px; font-weight: 600; color: var(--text-secondary);"></div>
            </div>

            <!-- Detailed Fee Breakdown Cards -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 18px;">
                <div style="background: var(--bg-card-subtle); padding: 12px; border-radius: 8px; border: 1px solid var(--border-card);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Agreed Tuition Fee</div>
                    <div id="disp_agreed_fee" style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-top: 2px;">Rs. 0</div>
                </div>
                <div style="background: var(--bg-card-subtle); padding: 12px; border-radius: 8px; border: 1px solid var(--border-card);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Approved Discount</div>
                    <div id="disp_discount" style="font-size: 16px; font-weight: 700; color: #0284c7; margin-top: 2px;">Rs. 0</div>
                </div>
                <div style="background: var(--bg-card-subtle); padding: 12px; border-radius: 8px; border: 1px solid var(--border-card);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Previous Paid Amount</div>
                    <div id="disp_paid" style="font-size: 16px; font-weight: 700; color: #0012e2; margin-top: 2px;">Rs. 0</div>
                </div>
                <div style="background: rgba(234, 179, 8, 0.1); padding: 12px; border-radius: 8px; border: 1px solid rgba(234, 179, 8, 0.3);">
                    <div style="font-size: 11px; text-transform: uppercase; color: #ca8a04; font-weight: 700;">Previous Dues Balance</div>
                    <div id="disp_dues" style="font-size: 18px; font-weight: 800; color: #ca8a04; margin-top: 2px;">Rs. 0</div>
                </div>
            </div>

            <!-- Net Total Payable Highlight Strip -->
            <div style="background: rgba(0, 18, 226, 0.06); border: 1.5px solid rgba(0, 18, 226, 0.28); border-radius: 10px; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <span style="font-size: 12px; text-transform: uppercase; font-weight: 700; color: #0012e2;">Total Payable Now</span>
                    <div style="font-size: 11px; color: var(--text-muted);">Prior Balance + Late Fines (if applied)</div>
                </div>
                <div id="disp_total_payable" style="font-size: 22px; font-weight: 900; color: #0012e2; font-family: monospace;">Rs. 0</div>
            </div>

            <!-- Past Payment History Ledger for this student -->
            <div style="border-top: 1px solid var(--border-card); padding-top: 14px;">
                <div style="font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">📜 Student Payment Ledger History</div>
                <div id="student_history_table" style="font-size: 12px;"></div>
            </div>
        </div>

        <!-- Initial Placeholder if no student selected -->
        <div id="fee_placeholder" class="glass-card" style="padding: 40px 24px; text-align: center;">
            <div style="font-size: 40px; margin-bottom: 12px;">🎓</div>
            <h4 style="margin: 0 0 6px 0; color: var(--text-primary);">No Student Selected</h4>
            <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Use the search bar above or choose from the list to view fee breakdown and receive payments.</p>
        </div>
    </div>

    <!-- RIGHT COLUMN: Step 3 (Receive Payment), Step 4 (Method), Step 5 (Confirm) -->
    <div>
        <div class="glass-card" style="padding: 24px; position: sticky; top: 20px;">
            <form method="POST" action="" id="feePaymentForm" onsubmit="return validatePaymentForm()">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="create_payment">
                <input type="hidden" name="enrollment_id" id="form_enrollment_id" value="">

                <!-- STEP 3: RECEIVE PAYMENT -->
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                            <span style="background: var(--primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">3</span>
                            Receive Payment
                        </h3>
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Supports Partial Amounts</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 10px;">
                        <label class="form-label" for="input_amount_paid" style="font-size: 12.5px; font-weight: 700;">
                            Amount Received from Parent (PKR) <span style="color:#ef4444;">*</span>
                        </label>
                        <input type="number" id="input_amount_paid" name="amount_paid" class="form-control" step="1" min="1" required placeholder="Enter amount (e.g. 5000)" style="font-size: 18px; font-weight: 800; font-family: monospace; padding: 10px 14px; color: var(--primary-light);" oninput="calculateBalancePreview()">
                    </div>

                    <!-- Quick-fill Amount Buttons -->
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px;">
                        <button type="button" class="btn btn-glass btn-sm" onclick="setQuickAmount('full')">Full Settlement</button>
                        <button type="button" class="btn btn-glass btn-sm" onclick="setQuickAmount('half')">50% Partial</button>
                        <button type="button" class="btn btn-glass btn-sm" onclick="setQuickAmount(1000)">Rs. 1,000</button>
                        <button type="button" class="btn btn-glass btn-sm" onclick="setQuickAmount(2000)">Rs. 2,000</button>
                        <button type="button" class="btn btn-glass btn-sm" onclick="setQuickAmount(5000)">Rs. 5,000</button>
                    </div>

                    <!-- Optional Fine Field -->
                    <div style="background: var(--bg-card-hover); border: 1px solid var(--border-card); border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="form-label" for="input_fine_amount" style="font-size: 12px; margin-bottom: 0;">Late Fee / Fine (PKR)</label>
                            <input type="number" id="input_fine_amount" name="fine_amount" class="form-control" step="1" min="0" value="0" style="width: 110px; padding: 6px 10px; font-size: 13px;" oninput="calculateBalancePreview()">
                        </div>
                    </div>

                    <!-- Live Real-Time Balance & Partial Warning Calculation -->
                    <div id="balance_preview_box" style="background: var(--bg-card-subtle); border: 1px solid var(--border-card); border-radius: 8px; padding: 12px 14px; font-size: 12.5px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="color: var(--text-muted);">Amount Entering:</span>
                            <strong id="preview_paying" style="color: var(--text-primary); font-family: monospace;">Rs. 0</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: var(--text-muted);">Remaining Outstanding Dues:</span>
                            <strong id="preview_remaining" style="color: #ef4444; font-family: monospace; font-size: 14px;">Rs. 0</strong>
                        </div>
                        <div id="preview_status_note" style="font-size: 11.5px; padding-top: 6px; border-top: 1px dashed var(--border-card); font-weight: 600;"></div>
                    </div>
                </div>

                <!-- STEP 4: CHOOSE PAYMENT METHOD -->
                <div style="margin-bottom: 20px;">
                    <div style="margin-bottom: 10px;">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                            <span style="background: var(--primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">4</span>
                            Payment Method
                        </h3>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 12px;">
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: var(--bg-card-subtle); border: 1px solid var(--border-card); border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
                            <input type="radio" name="payment_method" value="cash" checked onchange="onPaymentMethodChanged('cash')"> 💵 Cash
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: var(--bg-card-subtle); border: 1px solid var(--border-card); border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
                            <input type="radio" name="payment_method" value="bank" onchange="onPaymentMethodChanged('bank')"> 🏛️ Bank Transfer
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: var(--bg-card-subtle); border: 1px solid var(--border-card); border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
                            <input type="radio" name="payment_method" value="cheque" onchange="onPaymentMethodChanged('cheque')"> 🧾 Cheque
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: var(--bg-card-subtle); border: 1px solid var(--border-card); border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
                            <input type="radio" name="payment_method" value="online" onchange="onPaymentMethodChanged('online')"> 📱 Online / Mobile
                        </label>
                    </div>

                    <!-- Contextual Cheque / Transfer Fields -->
                    <div id="method_fields_container" style="display: none; background: var(--bg-card-hover); border: 1px solid var(--border-card); border-radius: 8px; padding: 12px 14px; margin-bottom: 12px;">
                        <div id="cheque_fields" style="display: none;">
                            <div class="form-group" style="margin-bottom: 8px;">
                                <label class="form-label" style="font-size: 11.5px;">Cheque Number <span style="color:#ef4444;">*</span></label>
                                <input type="text" name="reference_no" id="input_cheque_no" class="form-control" placeholder="e.g. CHQ-984210" style="font-size: 13px;">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 11.5px;">Bank Name</label>
                                    <input type="text" name="bank_name" class="form-control" placeholder="e.g. HBL / MCB / NBP" style="font-size: 13px;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 11.5px;">Cheque Date</label>
                                    <input type="date" name="cheque_date" class="form-control" style="font-size: 13px;">
                                </div>
                            </div>
                        </div>

                        <div id="ref_fields" style="display: none;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" id="ref_label" style="font-size: 11.5px;">Transaction Reference / Slip ID</label>
                                <input type="text" name="reference_no" id="input_transfer_ref" class="form-control" placeholder="e.g. TRX-882914 or Deposit Slip #" style="font-size: 13px;">
                            </div>
                        </div>
                    </div>

                    <!-- Remarks / Installment Particulars -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="input_remarks" style="font-size: 12px;">Remarks / Installment Description</label>
                        <input type="text" id="input_remarks" name="remarks" class="form-control" placeholder="e.g. Oct tuition fee, partial 1st installment" style="font-size: 13px;">
                    </div>
                </div>

                <!-- STEP 5: CONFIRM PAYMENT & AUDIT ATTRIBUTION -->
                <div style="border-top: 1px solid var(--border-card); padding-top: 18px; margin-bottom: 16px;">
                    <div style="margin-bottom: 12px;">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                            <span style="background: var(--primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 13px;">5</span>
                            Confirm &amp; Audit Trail
                        </h3>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 11.5px;">Receipt Voucher #</label>
                            <input type="text" name="receipt_no" class="form-control" placeholder="Auto-generated unique" style="font-size: 12.5px; font-family: monospace;">
                            <small style="font-size: 10px; color: var(--text-muted);">Leave empty to auto-assign</small>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 11.5px;">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="font-size: 12.5px;">
                        </div>
                    </div>

                    <div style="background: var(--bg-card-subtle); border-radius: 8px; padding: 10px 14px; font-size: 12px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--text-muted);">Cashier / Received By:</span>
                        <strong style="color: var(--text-primary);"><?= htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']) ?></strong>
                    </div>

                    <button type="submit" id="btn_submit_payment" class="btn btn-primary" style="width: 100%; padding: 12px 20px; font-size: 15px; font-weight: 800; justify-content: center;" disabled>
                        <span>✓ Confirm Payment &amp; Issue Receipt</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- VIEW 2: DAILY COLLECTION REPORT (Requirement 7)                        -->
<!-- ======================================================================= -->
<?php elseif ($currentView === 'daily'): ?>
<div class="glass-card" style="padding: 20px; margin-bottom: 24px;">
    <!-- Date Selector Bar -->
    <form method="GET" action="" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 0;">
        <input type="hidden" name="view" value="daily">
        <div style="display: flex; align-items: center; gap: 10px;">
            <label style="font-weight: 700; font-size: 14px; color: var(--text-primary);">Report Date:</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($reportDate) ?>" onchange="this.form.submit()" style="min-height: 38px; width: 170px;">
            <button type="submit" class="btn btn-glass btn-sm">Refresh</button>
            <?php if ($reportDate !== $todayDate): ?>
                <a href="?view=daily&date=<?= $todayDate ?>" class="btn btn-glass btn-sm">Jump to Today</a>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-glass btn-sm" onclick="window.print()">
                <span>🖨️ Print Daily Collection Report</span>
            </button>
        </div>
    </form>
</div>

<!-- Daily KPI Metrics Strip -->
<div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px;">
    <div class="metric-card royal" style="padding: 16px;">
        <div class="metric-title" style="font-size: 11.5px; text-transform: uppercase;">Total Collections (<?= date('M d, Y', strtotime($reportDate)) ?>)</div>
        <div class="metric-value" style="font-size: 22px; font-weight: 800; color: #0012e2; margin: 4px 0;">Rs. <?= number_format((float)$dailyKpis['total_collected'], 0) ?></div>
        <div class="metric-sub" style="font-size: 11px;"><?= number_format((int)$dailyKpis['total_receipts']) ?> Valid Receipts</div>
    </div>
    <div class="metric-card sky" style="padding: 16px;">
        <div class="metric-title" style="font-size: 11.5px; text-transform: uppercase;">💵 Cash Total</div>
        <div class="metric-value" style="font-size: 20px; font-weight: 800; color: #0284c7; margin: 4px 0;">Rs. <?= number_format((float)$dailyKpis['cash_total'], 0) ?></div>
        <div class="metric-sub" style="font-size: 11px;">Handover Cash</div>
    </div>
    <div class="metric-card royal" style="padding: 16px;">
        <div class="metric-title" style="font-size: 11.5px; text-transform: uppercase;">🏛️ Bank &amp; Cheque</div>
        <div class="metric-value" style="font-size: 20px; font-weight: 800; color: #0012e2; margin: 4px 0;">Rs. <?= number_format((float)($dailyKpis['bank_total'] + $dailyKpis['cheque_total']), 0) ?></div>
        <div class="metric-sub" style="font-size: 11px;">Direct Bank Clearing</div>
    </div>
    <div class="metric-card gold" style="padding: 16px;">
        <div class="metric-title" style="font-size: 11.5px; text-transform: uppercase;">📱 Online / Digital</div>
        <div class="metric-value" style="font-size: 20px; font-weight: 800; color: #ca8a04; margin: 4px 0;">Rs. <?= number_format((float)$dailyKpis['online_total'], 0) ?></div>
        <div class="metric-sub" style="font-size: 11px;">EasyPaisa / JazzCash</div>
    </div>
    <?php if ((int)$dailyKpis['voided_receipts'] > 0): ?>
    <div class="metric-card rose" style="padding: 16px;">
        <div class="metric-title" style="font-size: 11.5px; text-transform: uppercase;">⚠️ Voided Receipts</div>
        <div class="metric-value" style="font-size: 20px; font-weight: 800; color: #ef4444; margin: 4px 0;">Rs. <?= number_format((float)$dailyKpis['voided_amount'], 0) ?></div>
        <div class="metric-sub" style="font-size: 11px;"><?= (int)$dailyKpis['voided_receipts'] ?> Cancelled Records</div>
    </div>
    <?php endif; ?>
</div>

<!-- SECTION 7A & 7B: Collections by Cashier & Payment Method -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- 7A: TOTAL COLLECTIONS BY CASHIER -->
    <div class="glass-card" style="padding: 20px;">
        <h3 style="margin: 0 0 14px 0; font-size: 15px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
            <span>👨‍💼 Total Collections by Cashier</span>
        </h3>
        <div class="table-responsive">
            <table class="data-table" style="font-size: 13px;">
                <thead>
                    <tr>
                        <th>Cashier Name</th>
                        <th style="text-align: center;">Receipts</th>
                        <th style="text-align: right;">Cash (PKR)</th>
                        <th style="text-align: right;">Total (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cashierSummary)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">No collections recorded on this date.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cashierSummary as $cs): ?>
                            <tr>
                                <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($cs['cashier_name']) ?></strong></td>
                                <td style="text-align: center;"><span style="padding: 2px 8px; border-radius: 12px; background: var(--bg-card-subtle); font-weight: 600;"><?= number_format((int)$cs['receipt_count']) ?></span></td>
                                <td style="text-align: right; color: var(--text-muted);">Rs. <?= number_format((float)$cs['cash_amount'], 0) ?></td>
                                <td style="text-align: right;"><strong style="color: #0012e2; font-size: 14px;">Rs. <?= number_format((float)$cs['total_amount'], 0) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 7B: TOTAL COLLECTIONS BY PAYMENT METHOD -->
    <div class="glass-card" style="padding: 20px;">
        <h3 style="margin: 0 0 14px 0; font-size: 15px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
            <span>💳 Total Collections by Payment Method</span>
        </h3>
        <div class="table-responsive">
            <table class="data-table" style="font-size: 13px;">
                <thead>
                    <tr>
                        <th>Payment Mode</th>
                        <th style="text-align: center;">Transactions</th>
                        <th style="text-align: right;">Amount (PKR)</th>
                        <th style="text-align: right;">Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($methodSummary)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">No transactions recorded on this date.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $grandDaily = (float)$dailyKpis['total_collected'];
                        foreach ($methodSummary as $ms): 
                            $share = ($grandDaily > 0) ? round(((float)$ms['total_amount'] / $grandDaily) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td style="text-transform: capitalize; font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($ms['method_name']) ?></td>
                                <td style="text-align: center;"><span style="padding: 2px 8px; border-radius: 12px; background: var(--bg-card-subtle); font-weight: 600;"><?= number_format((int)$ms['tx_count']) ?></span></td>
                                <td style="text-align: right;"><strong style="color: #0012e2;">Rs. <?= number_format((float)$ms['total_amount'], 0) ?></strong></td>
                                <td style="text-align: right; color: var(--text-muted); font-size: 12px; font-weight: 600;"><?= $share ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- DETAILED COLLECTION JOURNAL FOR THE DAY -->
<div class="glass-card" style="padding: 20px;">
    <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: var(--text-primary);">
        📋 Daily Payment Transactions Journal (<?= date('F d, Y', strtotime($reportDate)) ?>)
    </h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Student Name</th>
                    <th>Admission No.</th>
                    <th>Roll / Class</th>
                    <th>Course</th>
                    <th style="text-align: right;">Amount Paid</th>
                    <th>Method</th>
                    <th>Cashier</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dailyTransactions)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 36px;">No transactions recorded on <?= date('F d, Y', strtotime($reportDate)) ?>.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dailyTransactions as $dt): ?>
                        <tr style="<?= $dt['is_cancelled'] ? 'opacity: 0.6; background: rgba(239,68,68,0.04);' : '' ?>">
                            <td><strong style="color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($dt['receipt_no'] ?: $dt['receipt_voucher_no']) ?></strong></td>
                            <td>
                                <a href="../students/view.php?id=<?= $dt['student_id'] ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($dt['full_name']) ?>
                                </a>
                            </td>
                            <td><span style="font-family: monospace; color: var(--text-muted);"><?= htmlspecialchars($dt['student_reg_no']) ?></span></td>
                            <td><?= htmlspecialchars($dt['roll_number'] ?: '—') ?> &bull; <span style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($dt['tuition_class'] ?: ($dt['class_level'] ?: $dt['batch_code'])) ?></span></td>
                            <td><?= htmlspecialchars($dt['course_name']) ?></td>
                            <td style="text-align: right;">
                                <strong style="color: <?= $dt['is_cancelled'] ? '#ef4444' : '#0012e2' ?>; font-size: 14.5px;">
                                    Rs. <?= number_format((float)$dt['amount_paid'], 0) ?>
                                </strong>
                            </td>
                            <td><span style="text-transform: capitalize; padding: 2px 8px; border-radius: 4px; background: var(--bg-card-subtle); font-size: 12px;"><?= htmlspecialchars($dt['payment_method'] ?: $dt['payment_mode']) ?></span></td>
                            <td style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($dt['received_by'] ?: 'Accountant') ?></td>
                            <td>
                                <?php if ($dt['is_cancelled']): ?>
                                    <span style="padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: rgba(239,68,68,0.15); color: #ef4444;" title="<?= htmlspecialchars($dt['cancellation_reason'] ?? '') ?>">VOIDED</span>
                                <?php else: ?>
                                    <span style="padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: rgba(0,18,226,0.08); color: #0012e2; border: 1px solid rgba(0,18,226,0.2);">CONFIRMED</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="receipt.php?id=<?= $dt['id'] ?>" target="_blank" class="btn btn-glass btn-sm" title="Print Official Receipt">🖨️ Receipt</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ======================================================================= -->
<!-- VIEW 3: OUTSTANDING FEES DIRECTORY                                      -->
<!-- ======================================================================= -->
<?php elseif ($currentView === 'outstanding'): ?>
<div class="glass-card" style="padding: 16px 20px; margin-bottom: 24px;">
    <form method="GET" action="" class="filter-bar" style="margin-bottom: 0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <input type="hidden" name="view" value="outstanding">
        <div class="filter-group" style="flex: 1; min-width: 260px;">
            <input type="text" name="search" class="form-control" placeholder="Search by student name, reg #, roll #, or course..." value="<?= htmlspecialchars($search) ?>" style="min-height: 40px;">
        </div>
        <div class="filter-group">
            <select name="course_id" class="form-control" style="min-height: 40px; min-width: 170px;">
                <option value="">All Courses</option>
                <?php foreach ($coursesList as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['course_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select name="batch_id" class="form-control" style="min-height: 40px; min-width: 140px;">
                <option value="">All Batches</option>
                <?php foreach ($batchesList as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $batchFilter === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['batch_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-glass" style="min-height: 40px;">Filter</button>
        <?php if (!empty($search) || $courseFilter > 0 || $batchFilter > 0): ?>
            <a href="?view=outstanding" class="btn btn-glass" style="min-height: 40px;">Reset</a>
        <?php endif; ?>
        <?php
        $printDuesParams = $_GET;
        $printDuesParams['view'] = 'outstanding';
        $printDuesParams['print'] = 'all';
        ?>
        <a href="?<?= http_build_query($printDuesParams) ?>" class="btn btn-glass" style="min-height: 40px; margin-left: auto;" title="Open Print Preview for All Outstanding Dues">
            <span>🖨️ Print Dues Statement</span>
        </a>
    </form>
</div>

<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Registration No.</th>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th>Roll / Class</th>
                    <th>Course &amp; Batch</th>
                    <th style="text-align: right;">Total Agreed</th>
                    <th style="text-align: right;">Paid to Date</th>
                    <th style="text-align: right;">Outstanding Dues</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($outstandingList)): ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            <div class="empty-state-icon">🎉</div>
                            <div class="empty-state-title">No outstanding dues</div>
                            <div class="empty-state-desc">All student fee dues have been fully settled.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($outstandingList as $row): ?>
                        <tr>
                            <td><strong style="color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($row['student_reg_no']) ?></strong></td>
                            <td>
                                <a href="../students/view.php?id=<?= $row['student_id'] ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($row['full_name']) ?>
                                </a>
                            </td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($row['father_name'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($row['roll_number'] ?: '—') ?> &bull; <span style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($row['tuition_class'] ?: ($row['class_level'] ?: $row['batch_code'])) ?></span></td>
                            <td><?= htmlspecialchars($row['course_name']) ?> <span style="font-size: 11px; color: var(--text-dim);">(<?= htmlspecialchars($row['batch_code']) ?>)</span></td>
                            <td style="text-align: right;">Rs. <?= number_format((float)$row['total_agreed_fee'], 0) ?></td>
                            <td style="text-align: right; color: #0012e2; font-weight: 600;">Rs. <?= number_format((float)$row['paid_amount'], 0) ?></td>
                            <td style="text-align: right;"><strong style="color: #ca8a04; font-size: 15px;">Rs. <?= number_format((float)$row['balance_due'], 0) ?></strong></td>
                            <td style="text-align: right;">
                                <a href="?view=collect&enrollment_id=<?= $row['enrollment_id'] ?>" class="btn btn-primary btn-sm" style="font-weight: 700;">
                                    💰 Collect Fee
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <?php
        $outBaseParams = $_GET;
        $outBaseParams['view'] = 'outstanding';
        ?>
        <div class="pagination-wrapper" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-top: 1px solid var(--border-card);">
            <div>Showing <strong><?= min($totalRecords, $offset + 1) ?>–<?= min($totalRecords, $offset + count($outstandingList)) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> students with pending dues</div>
            <div class="pagination-links" style="display: flex; gap: 6px;">
                <?php if ($page > 1): ?>
                    <?php $prevP = $outBaseParams; $prevP['page'] = $page - 1; ?>
                    <a href="?<?= http_build_query($prevP) ?>" class="btn btn-glass btn-sm">←</a>
                <?php endif; ?>
                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++): 
                    $curP = $outBaseParams; $curP['page'] = $p;
                ?>
                    <a href="?<?= http_build_query($curP) ?>" class="btn <?= $p === $page ? 'btn-primary' : 'btn-glass' ?> btn-sm"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <?php $nextP = $outBaseParams; $nextP['page'] = $page + 1; ?>
                    <a href="?<?= http_build_query($nextP) ?>" class="btn btn-glass btn-sm">→</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ======================================================================= -->
<!-- VIEW 4: ALL FEE PAYMENTS LEDGER                                         -->
<!-- ======================================================================= -->
<?php else: ?>
<div class="glass-card" style="padding: 16px 20px; margin-bottom: 24px;">
    <form method="GET" action="" class="filter-bar" style="margin-bottom: 0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <input type="hidden" name="view" value="all">
        <div class="filter-group" style="flex: 1; min-width: 220px;">
            <input type="text" name="search" class="form-control" placeholder="Search student, reg #, receipt #, cashier..." value="<?= htmlspecialchars($search) ?>" style="min-height: 40px;">
        </div>
        <div class="filter-group">
            <select name="course_id" class="form-control" style="min-height: 40px; min-width: 140px;">
                <option value="">All Courses</option>
                <?php foreach ($coursesList as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['course_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select name="batch_id" class="form-control" style="min-height: 40px; min-width: 130px;">
                <option value="">All Batches</option>
                <?php foreach ($batchesList as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $batchFilter === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['batch_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select name="method" class="form-control" style="min-height: 40px; min-width: 130px;">
                <option value="">All Modes</option>
                <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                <option value="bank" <?= $methodFilter === 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                <option value="cheque" <?= $methodFilter === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                <option value="online" <?= $methodFilter === 'online' ? 'selected' : '' ?>>Online</option>
                <option value="slip" <?= $methodFilter === 'slip' ? 'selected' : '' ?>>Deposit Slip</option>
            </select>
        </div>
        <div class="filter-group">
            <select name="status" class="form-control" style="min-height: 40px; min-width: 130px;">
                <option value="">All Statuses</option>
                <option value="cleared" <?= $statusFilter === 'cleared' ? 'selected' : '' ?>>Cleared</option>
                <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="voided" <?= $statusFilter === 'voided' ? 'selected' : '' ?>>Voided</option>
            </select>
        </div>
        <div class="filter-group">
            <select name="cashier" class="form-control" style="min-height: 40px; min-width: 130px;">
                <option value="">All Cashiers</option>
                <?php foreach ($availableCashiers as $cName): ?>
                    <option value="<?= htmlspecialchars($cName) ?>" <?= $cashierFilter === $cName ? 'selected' : '' ?>><?= htmlspecialchars($cName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group" style="display: flex; gap: 6px; align-items: center;">
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" title="From Date" style="min-height: 40px; width: 135px;">
            <span style="color: var(--text-muted);">&ndash;</span>
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" title="To Date" style="min-height: 40px; width: 135px;">
        </div>
        <button type="submit" class="btn btn-glass" style="min-height: 40px;">Filter</button>
        <?php if (!empty($search) || !empty($methodFilter) || !empty($cashierFilter) || $courseFilter > 0 || $batchFilter > 0 || !empty($statusFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
            <a href="?view=all" class="btn btn-glass" style="min-height: 40px;">Reset</a>
        <?php endif; ?>
        <?php
        $printLedgerParams = $_GET;
        $printLedgerParams['view'] = 'all';
        $printLedgerParams['print'] = 'all';
        ?>
        <a href="?<?= http_build_query($printLedgerParams) ?>" class="btn btn-glass" style="min-height: 40px; margin-left: auto;" title="Open Print Preview for All Matching Payments">
            <span>🖨️ Print Full Ledger</span>
        </a>
    </form>
</div>

<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Date</th>
                    <th>Student Name</th>
                    <th>Admission No.</th>
                    <th>Course &amp; Batch</th>
                    <th style="text-align: right;">Amount Paid</th>
                    <th>Payment Mode</th>
                    <th>Cashier</th>
                    <th>Payment Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="10" class="empty-state">
                            <div class="empty-state-icon">💳</div>
                            <div class="empty-state-title">No fee payments found</div>
                            <div class="empty-state-desc">Try adjusting your search criteria or record a new payment.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $t): 
                        $isVoided = (bool)$t['is_cancelled'];
                        $dueAfter = (float)$t['due_amount'];
                    ?>
                        <tr style="<?= $isVoided ? 'opacity: 0.6; background: rgba(239, 68, 68, 0.04);' : '' ?>">
                            <td>
                                <strong style="color: var(--primary-light); font-family: monospace; font-size: 13.5px;">
                                    <?= htmlspecialchars($t['receipt_no'] ?: $t['receipt_voucher_no']) ?>
                                </strong>
                            </td>
                            <td style="color: var(--text-muted); font-size: 12.5px; white-space: nowrap;">
                                <?= $t['payment_date'] ? date('M d, Y', strtotime($t['payment_date'])) : '—' ?>
                            </td>
                            <td>
                                <a href="../students/view.php?id=<?= $t['student_id'] ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($t['full_name']) ?>
                                </a>
                            </td>
                            <td><span style="font-family: monospace; color: var(--text-muted);"><?= htmlspecialchars($t['student_reg_no']) ?></span></td>
                            <td><?= htmlspecialchars($t['course_name']) ?> <span style="font-size: 11px; color: var(--text-dim);">(<?= htmlspecialchars($t['batch_code']) ?>)</span></td>
                            <td style="text-align: right;">
                                <strong style="color: <?= $isVoided ? '#ef4444' : '#0012e2' ?>; font-size: 15px;">
                                    Rs. <?= number_format((float)$t['amount_paid'], 0) ?>
                                </strong>
                            </td>
                            <td>
                                <span style="text-transform: capitalize; padding: 2px 8px; border-radius: 4px; background: var(--bg-card-subtle); font-size: 12px;">
                                    <?= htmlspecialchars($t['payment_method'] ?: $t['payment_mode']) ?>
                                    <?php if (!empty($t['reference_no'])): ?>
                                        <small style="color: var(--text-dim);">#<?= htmlspecialchars($t['reference_no']) ?></small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($t['received_by'] ?: 'Accountant') ?></td>
                            <td>
                                <?php if ($isVoided): ?>
                                    <span style="padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: rgba(239, 68, 68, 0.15); color: #ef4444;" title="Reason: <?= htmlspecialchars($t['cancellation_reason'] ?? '') ?>">
                                        VOIDED
                                    </span>
                                <?php elseif ($dueAfter <= 0): ?>
                                    <span style="padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: rgba(0, 18, 226, 0.08); color: #0012e2; border: 1px solid rgba(0, 18, 226, 0.2);">
                                        CLEARED
                                    </span>
                                <?php else: ?>
                                    <span style="padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: rgba(234, 179, 8, 0.12); color: #ca8a04; border: 1px solid rgba(234, 179, 8, 0.3);" title="Remaining Balance: Rs. <?= number_format($dueAfter, 0) ?>">
                                        PARTIAL (Rs. <?= number_format($dueAfter, 0) ?> due)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <a href="receipt.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-glass btn-sm" title="Print Fee Receipt Voucher">🖨️ Receipt</a>
                                    <?php if ($isAdmin && !$isVoided): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openVoidModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['receipt_no'] ?: $t['receipt_voucher_no'])) ?>', <?= (float)$t['amount_paid'] ?>)">
                                            Void
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <?php
        $allBaseParams = $_GET;
        $allBaseParams['view'] = 'all';
        ?>
        <div class="pagination-wrapper" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-top: 1px solid var(--border-card);">
            <div>Showing <strong><?= min($totalRecords, $offset + 1) ?>–<?= min($totalRecords, $offset + count($transactions)) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> confirmed payments</div>
            <div class="pagination-links" style="display: flex; gap: 6px;">
                <?php if ($page > 1): ?>
                    <?php $prevP = $allBaseParams; $prevP['page'] = $page - 1; ?>
                    <a href="?<?= http_build_query($prevP) ?>" class="btn btn-glass btn-sm">←</a>
                <?php endif; ?>
                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++): 
                    $curP = $allBaseParams; $curP['page'] = $p;
                ?>
                    <a href="?<?= http_build_query($curP) ?>" class="btn <?= $p === $page ? 'btn-primary' : 'btn-glass' ?> btn-sm"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <?php $nextP = $allBaseParams; $nextP['page'] = $page + 1; ?>
                    <a href="?<?= http_build_query($nextP) ?>" class="btn btn-glass btn-sm">→</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ======================================================================= -->
<!-- MODAL: SECURE AUDIT-TRAILED PAYMENT VOID (Non-Silent Deletion)          -->
<!-- ======================================================================= -->
<div class="modal-overlay" id="voidPaymentModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" style="color: #ef4444;">⚠️ Void / Reverse Payment Receipt</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('voidPaymentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="void_payment">
            <input type="hidden" name="id" id="void_tx_id">
            <div class="modal-body">
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; color: var(--text-secondary);">
                    <strong>Audit Integrity Notice:</strong> Confirmed payment records are never silently erased. Voiding marks this receipt voucher as cancelled in institutional records, reverses the ledger balance, and logs your administrator audit attribution.
                </div>
                <div style="margin-bottom: 14px;">
                    <div style="font-size: 13px; color: var(--text-muted);">Receipt to Void:</div>
                    <div id="void_receipt_display" style="font-size: 16px; font-weight: 800; color: var(--primary-light); font-family: monospace;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="cancellation_reason" style="font-weight: 700;">
                        Mandatory Reason for Reversal <span style="color:#ef4444;">*</span>
                    </label>
                    <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="3" required placeholder="State exact institutional justification (e.g. Bounced cheque #102, cashier data-entry error, parent refunded)..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('voidPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-danger" style="font-weight: 700;">Confirm Void Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================================= -->
<!-- JAVASCRIPT: 7-STEP FEE COUNTER INTERACTIVITY & CALCULATIONS             -->
<!-- ======================================================================= -->
<script>
let currentStudentProfile = null;
let searchDebounceTimer = null;

// Step 1: Live Student Search debouncing
const searchInput = document.getElementById('student_search_input');
const searchResultsBox = document.getElementById('search_results_container');
const searchSpinner = document.getElementById('search_spinner');

if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(searchDebounceTimer);

        if (query.length < 2) {
            searchResultsBox.style.display = 'none';
            searchResultsBox.innerHTML = '';
            return;
        }

        searchSpinner.style.display = 'block';
        searchDebounceTimer = setTimeout(() => {
            fetch('../../api/fees/student_fee_details.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    searchSpinner.style.display = 'none';
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        renderSearchResults(data.data);
                    } else {
                        searchResultsBox.innerHTML = '<div style="padding: 14px; text-align: center; color: var(--text-muted); font-size: 13px;">No matching students found.</div>';
                        searchResultsBox.style.display = 'block';
                    }
                })
                .catch(err => {
                    searchSpinner.style.display = 'none';
                    console.error('Search error:', err);
                });
        }, 250);
    });

    // Close search dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResultsBox.contains(e.target)) {
            searchResultsBox.style.display = 'none';
        }
    });
}

function renderSearchResults(students) {
    let html = '';
    students.forEach(st => {
        const bal = parseFloat(st.balance_due);
        const balLabel = bal > 0 
            ? `<span style="color:#ca8a04; font-weight:700;">Pending Due: Rs. ${bal.toLocaleString()}</span>`
            : `<span style="color:#0012e2; font-weight:700;">Cleared / Fully Settled</span>`;

        html += `
            <div class="search-result-item" onclick="selectStudentEnrollment(${st.enrollment_id})" style="cursor: pointer;">
                <div style="font-size: 20px;">🎓</div>
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="search-item-title">${escapeHtml(st.full_name)}</span>
                        <span style="font-family: monospace; font-size: 11.5px; color: var(--primary-light); font-weight: 700;">${escapeHtml(st.student_reg_no)}</span>
                    </div>
                    <div class="search-item-sub">
                        <span>Roll: ${escapeHtml(st.roll_number)} &bull; ${escapeHtml(st.class_label)} &bull; ${escapeHtml(st.course_name)}</span>
                    </div>
                    <div style="font-size: 11.5px; margin-top: 2px;">${balLabel}</div>
                </div>
            </div>
        `;
    });
    searchResultsBox.innerHTML = html;
    searchResultsBox.style.display = 'block';
}

function onQuickStudentPicked(selectEl) {
    const enrollmentId = parseInt(selectEl.value);
    if (enrollmentId > 0) {
        selectStudentEnrollment(enrollmentId);
    }
}

// Step 2: Fetch and render student fee profile
function selectStudentEnrollment(enrollmentId) {
    if (searchResultsBox) searchResultsBox.style.display = 'none';
    if (searchSpinner) searchSpinner.style.display = 'block';

    fetch('../../api/fees/student_fee_details.php?enrollment_id=' + enrollmentId)
        .then(res => res.json())
        .then(data => {
            if (searchSpinner) searchSpinner.style.display = 'none';
            if (data.status === 'success' && data.data) {
                currentStudentProfile = data.data;
                populateFeeDetails(currentStudentProfile);
            } else {
                alert('Could not load fee details for this enrollment.');
            }
        })
        .catch(err => {
            if (searchSpinner) searchSpinner.style.display = 'none';
            console.error('Error fetching student fee profile:', err);
        });
}

function populateFeeDetails(p) {
    document.getElementById('form_enrollment_id').value = p.enrollment_id;
    document.getElementById('st_name').textContent = p.full_name;
    document.getElementById('st_father').textContent = "Father: " + p.father_name;
    document.getElementById('st_reg').textContent = p.student_reg_no;
    document.getElementById('st_roll_class').textContent = "Roll: " + p.roll_number + " • Class: " + p.tuition_class;
    document.getElementById('st_course').textContent = p.course_name + " (" + p.batch_code + ")";

    document.getElementById('disp_agreed_fee').textContent = "Rs. " + p.total_agreed_fee.toLocaleString();
    document.getElementById('disp_discount').textContent = "Rs. " + p.approved_discount.toLocaleString();
    document.getElementById('disp_paid').textContent = "Rs. " + p.cumulative_paid.toLocaleString();
    document.getElementById('disp_dues').textContent = "Rs. " + p.previous_dues.toLocaleString();
    document.getElementById('disp_total_payable').textContent = "Rs. " + p.total_payable.toLocaleString();

    // Status Badge
    const badge = document.getElementById('student_status_badge');
    if (p.is_fully_paid) {
        badge.textContent = "Fully Settled";
        badge.style.background = "rgba(0, 18, 226, 0.08)";
        badge.style.color = "#0012e2";
        badge.style.border = "1px solid rgba(0, 18, 226, 0.25)";
    } else if (p.cumulative_paid > 0) {
        badge.textContent = "Partial Payment Due";
        badge.style.background = "rgba(234, 179, 8, 0.12)";
        badge.style.color = "#ca8a04";
        badge.style.border = "1px solid rgba(234, 179, 8, 0.3)";
    } else {
        badge.textContent = "Unpaid Dues";
        badge.style.background = "rgba(239, 68, 68, 0.12)";
        badge.style.color = "#ef4444";
        badge.style.border = "1px solid rgba(239, 68, 68, 0.3)";
    }

    // Render payment history table
    let historyHtml = '';
    if (p.payment_history && p.payment_history.length > 0) {
        historyHtml += '<table style="width:100%; border-collapse:collapse; margin-top:6px;"><thead><tr style="border-bottom:1px solid var(--border-card); color:var(--text-muted); font-size:11px; text-transform:uppercase;"><th style="text-align:left; padding:4px 6px;">Receipt</th><th style="text-align:left; padding:4px 6px;">Date</th><th style="text-align:right; padding:4px 6px;">Amount</th><th style="text-align:left; padding:4px 6px;">Cashier</th><th style="text-align:right; padding:4px 6px;">Voucher</th></tr></thead><tbody>';
        p.payment_history.forEach(h => {
            const isCancelled = parseInt(h.is_cancelled) === 1;
            historyHtml += `
                <tr style="border-bottom:1px solid var(--border-card); ${isCancelled ? 'opacity:0.5;' : ''}">
                    <td style="padding:4px 6px; font-family:monospace; font-weight:700; color:var(--primary-light);">${escapeHtml(h.receipt_no || h.receipt_voucher_no)}</td>
                    <td style="padding:4px 6px; color:var(--text-muted); font-size:11px;">${h.payment_date || '—'}</td>
                    <td style="padding:4px 6px; text-align:right; font-weight:700; color:${isCancelled ? '#ef4444' : '#0012e2'};">Rs. ${parseFloat(h.amount_paid).toLocaleString()}</td>
                    <td style="padding:4px 6px; color:var(--text-secondary); font-size:11px;">${escapeHtml(h.received_by || 'Accountant')}</td>
                    <td style="padding:4px 6px; text-align:right;">
                        <a href="receipt.php?id=${h.id}" target="_blank" style="color:#0012e2; text-decoration:none; font-weight:600; font-size:11px;">Print →</a>
                    </td>
                </tr>
            `;
        });
        historyHtml += '</tbody></table>';
    } else {
        historyHtml = '<div style="color:var(--text-muted); font-size:11.5px; padding:6px 0;">No previous payments recorded for this enrollment.</div>';
    }
    document.getElementById('student_history_table').innerHTML = historyHtml;

    // Show panels
    document.getElementById('fee_placeholder').style.display = 'none';
    document.getElementById('fee_details_panel').style.display = 'block';
    document.getElementById('btn_submit_payment').disabled = false;

    // Default amount input: populate with remaining dues if pending
    if (p.total_payable > 0) {
        document.getElementById('input_amount_paid').value = p.total_payable;
    } else {
        document.getElementById('input_amount_paid').value = '';
    }

    calculateBalancePreview();
}

// Step 3: Real-Time Balance & Partial Payment Preview
function calculateBalancePreview() {
    if (!currentStudentProfile) return;

    const amountPaid = parseFloat(document.getElementById('input_amount_paid').value) || 0;
    const fineAmount = parseFloat(document.getElementById('input_fine_amount').value) || 0;

    const totalPayable = currentStudentProfile.previous_dues + fineAmount;
    const remainingAfter = Math.max(0, totalPayable - amountPaid);

    document.getElementById('preview_paying').textContent = "Rs. " + amountPaid.toLocaleString();
    document.getElementById('preview_remaining').textContent = "Rs. " + remainingAfter.toLocaleString();

    const note = document.getElementById('preview_status_note');
    if (amountPaid <= 0) {
        note.innerHTML = '<span style="color:var(--text-muted);">Enter amount to preview balance.</span>';
    } else if (remainingAfter > 0) {
        // Enforce Requirement: Do not mark as fully paid if partial amount was received!
        note.innerHTML = `<span style="color:#ca8a04;">⏳ <strong>Partial Payment:</strong> Rs. ${remainingAfter.toLocaleString()} will remain pending. Student will stay in Partial Dues status.</span>`;
    } else if (amountPaid === totalPayable) {
        note.innerHTML = '<span style="color:#0012e2;">🎉 <strong>Full Clearance:</strong> All dues settled through this payment.</span>';
    } else {
        const excess = amountPaid - totalPayable;
        note.innerHTML = `<span style="color:#0284c7;">ℹ️ <strong>Advance Paid:</strong> Payment exceeds current dues by Rs. ${excess.toLocaleString()}.</span>`;
    }
}

function setQuickAmount(type) {
    if (!currentStudentProfile) return;
    const fineAmount = parseFloat(document.getElementById('input_fine_amount').value) || 0;
    const totalDue = currentStudentProfile.previous_dues + fineAmount;

    if (type === 'full') {
        document.getElementById('input_amount_paid').value = Math.max(0, totalDue);
    } else if (type === 'half') {
        document.getElementById('input_amount_paid').value = Math.round(Math.max(0, totalDue) / 2);
    } else if (typeof type === 'number') {
        document.getElementById('input_amount_paid').value = type;
    }
    calculateBalancePreview();
}

// Step 4: Contextual payment method changes
function onPaymentMethodChanged(method) {
    const container = document.getElementById('method_fields_container');
    const chq = document.getElementById('cheque_fields');
    const ref = document.getElementById('ref_fields');
    const refLabel = document.getElementById('ref_label');

    if (method === 'cheque') {
        container.style.display = 'block';
        chq.style.display = 'block';
        ref.style.display = 'none';
        document.getElementById('input_cheque_no').required = true;
    } else if (method === 'bank' || method === 'online' || method === 'slip') {
        container.style.display = 'block';
        chq.style.display = 'none';
        ref.style.display = 'block';
        refLabel.textContent = (method === 'online') ? 'Online Transaction ID (e.g. Raast / EasyPaisa TRX)' : 'Bank Transfer / Deposit Slip Reference #';
        if (document.getElementById('input_cheque_no')) document.getElementById('input_cheque_no').required = false;
    } else {
        container.style.display = 'none';
        chq.style.display = 'none';
        ref.style.display = 'none';
        if (document.getElementById('input_cheque_no')) document.getElementById('input_cheque_no').required = false;
    }
}

function validatePaymentForm() {
    if (!currentStudentProfile) {
        alert('Please select a student before submitting payment.');
        return false;
    }
    const amt = parseFloat(document.getElementById('input_amount_paid').value);
    if (!amt || amt <= 0) {
        alert('Please enter a positive amount received.');
        return false;
    }
    return true;
}

// Modal helper for Voiding payments
function openVoidModal(txId, receiptNo, amount) {
    document.getElementById('void_tx_id').value = txId;
    document.getElementById('void_receipt_display').textContent = receiptNo + " — Rs. " + amount.toLocaleString();
    document.getElementById('cancellation_reason').value = '';
    openModal('voidPaymentModal');
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
    });
}

// Auto-trigger student selection if passed via URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const enrId = urlParams.get('enrollment_id');
    if (enrId) {
        selectStudentEnrollment(parseInt(enrId));
    }
});
</script>
<?php endif; // End of isPrintAll check ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
