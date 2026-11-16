<?php
/**
 * Fee Transactions API - Secured with RBAC and CSRF Protection
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['must_change_password'])) {
    jsonResponse(['error' => 'Please change your initial password before continuing.'], 403);
}

header('Content-Type: application/json; charset=utf-8');

// Ensure user is authenticated
if (empty($_SESSION['user_id'])) {
    jsonResponse(['status' => 'error', 'error' => '401 Unauthorized'], 401);
}

// Restricted to Admin and Accountant
if (!hasRole(['admin', 'accountant'])) {
    jsonResponse(['status' => 'error', 'error' => '403 Forbidden: Insufficient financial permissions'], 403);
}

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $enrollmentId = (int)($_GET['enrollment_id'] ?? 0);
        if ($enrollmentId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM fee_transactions WHERE enrollment_id = ? ORDER BY id ASC");
            $stmt->execute([$enrollmentId]);
            jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
        }

        // Summary report
        $stmt = $pdo->query("
            SELECT e.id as enrollment_id, s.student_reg_no, s.full_name, b.batch_code, e.total_agreed_fee,
                   COALESCE(SUM(f.amount_paid), 0) as total_paid,
                   GREATEST(0, e.total_agreed_fee + COALESCE(SUM(f.fine_amount), 0) - COALESCE(SUM(f.amount_paid), 0)) as remaining_dues
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            JOIN batches b ON e.batch_id = b.id
            LEFT JOIN fee_transactions f ON f.enrollment_id = e.id AND f.is_cancelled = 0
            GROUP BY e.id
            LIMIT 50
        ");
        jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'POST':
        validateCsrfRequest();

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?? $_POST;

        $errors = Validator::validateFee($input);
        if (!empty($errors)) {
            jsonResponse(['status' => 'error', 'errors' => $errors], 400);
        }

        $receiptNo = trim($input['receipt_no'] ?? '');
        if ($receiptNo === '') {
            $receiptNo = 'REC-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(12)));
        }
        $paymentDate = $input['payment_date'] ?? date('Y-m-d');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
        if (!$date || $date->format('Y-m-d') !== $paymentDate) {
            jsonResponse(['error' => 'Valid payment date required.'], 400);
        }
        try {
            $pdo->beginTransaction();
            $enrollment = $pdo->prepare("SELECT id, total_agreed_fee FROM enrollments WHERE id = ? FOR UPDATE");
            $enrollment->execute([(int)$input['enrollment_id']]);
            $record = $enrollment->fetch();
            if (!$record) {
                $pdo->rollBack();
                jsonResponse(['error' => 'Enrollment not found.'], 404);
            }
            $duplicate = $pdo->prepare("SELECT id FROM fee_transactions WHERE receipt_no = ? OR receipt_voucher_no = ? LIMIT 1");
            $duplicate->execute([$receiptNo, $receiptNo]);
            if ($duplicate->fetch()) {
                $pdo->rollBack();
                jsonResponse(['error' => 'Receipt number already exists.'], 409);
            }
            $totals = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) AS paid, COALESCE(SUM(fine_amount), 0) AS fines FROM fee_transactions WHERE enrollment_id = ? AND is_cancelled = 0");
            $totals->execute([$record['id']]);
            $ledger = $totals->fetch();
            $due = max(0, (float)$record['total_agreed_fee'] + (float)$ledger['fines'] - (float)$ledger['paid'] - (float)$input['amount_paid']);
            $cashier = getCurrentUser();
            $stmt = $pdo->prepare("INSERT INTO fee_transactions (enrollment_id, receipt_no, receipt_voucher_no, amount_paid, due_amount, payment_date, payment_method, payment_mode, received_by, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $methodName = $input['payment_method'] ?? 'cash';
            $stmt->execute([(int)$input['enrollment_id'], $receiptNo, $receiptNo, (float)$input['amount_paid'], $due, $paymentDate, $methodName, $methodName, $cashier['full_name'], trim($input['remarks'] ?? '')]);
            $id = (int)$pdo->lastInsertId();
            $pdo->commit();
            jsonResponse(['status' => 'success', 'message' => 'Fee transaction recorded', 'id' => $id, 'receipt_no' => $receiptNo, 'due_amount' => $due], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Fee API error: ' . $e->getMessage());
            jsonResponse(['error' => 'Unable to record payment. Please try again.'], 500);
        }
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}
