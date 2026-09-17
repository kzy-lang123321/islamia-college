<?php
/**
 * Fee Transactions API - Secured with RBAC and CSRF Protection
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/validation.php';

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
                   GREATEST(0, e.total_agreed_fee - COALESCE(SUM(f.amount_paid), 0)) as remaining_dues
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            JOIN batches b ON e.batch_id = b.id
            LEFT JOIN fee_transactions f ON f.enrollment_id = e.id
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
        if (empty($receiptNo)) {
            $receiptNo = 'RCP-' . date('Y') . '-' . rand(1000, 9999);
        }

        $stmt = $pdo->prepare("
            INSERT INTO fee_transactions (enrollment_id, receipt_no, amount_paid, payment_date, payment_method, remarks)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$input['enrollment_id'],
            $receiptNo,
            (float)$input['amount_paid'],
            $input['payment_date'] ?? date('Y-m-d'),
            $input['payment_method'] ?? 'cash',
            trim($input['remarks'] ?? '')
        ]);

        jsonResponse(['status' => 'success', 'message' => 'Fee transaction recorded', 'id' => (int)$pdo->lastInsertId(), 'receipt_no' => $receiptNo], 201);
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}
