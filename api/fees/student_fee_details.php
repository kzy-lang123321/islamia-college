<?php
/**
 * Student Fee Search & Calculation API
 * Islamia College of Technology College Management System
 * Supports Step 1 (Search by name, admission/reg no, roll no, class)
 * and Step 2 (Fee Details Breakdown: tuition fee, previous dues, discounts, fines, total payable)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['must_change_password'])) {
    jsonResponse(['error' => 'Please change your initial password before continuing.'], 403);
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    jsonResponse(['status' => 'error', 'error' => '401 Unauthorized'], 401);
}

if (!hasRole(['admin', 'accountant', 'registrar', 'staff'])) {
    jsonResponse(['status' => 'error', 'error' => '403 Forbidden'], 403);
}

$pdo = Database::getConnection();

$enrollmentId = (int)($_GET['enrollment_id'] ?? 0);
$studentId = (int)($_GET['student_id'] ?? 0);
$query = trim($_GET['q'] ?? '');

// Helper to calculate student fee profile
function getEnrollmentFeeProfile(PDO $pdo, int $enrollmentId): ?array {
    $sql = "
        SELECT e.id as enrollment_id, s.id as student_id, s.full_name, s.father_name, s.student_reg_no,
               s.contact_number, s.secondary_contact, s.cnic, s.address,
               e.roll_number, e.total_agreed_fee, e.admission_fee, e.discount_amount,
               e.tuition_class, e.class_level, e.tuition_subjects, e.status as enrollment_status,
               b.id as batch_id, b.batch_code, b.batch_name,
               c.id as course_id, c.course_name, c.course_code, c.standard_fee, c.duration
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        WHERE e.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enrollmentId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) return null;

    // Financial totals (excluding cancelled payments)
    $txStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as cumulative_paid,
               COALESCE(SUM(fine_amount), 0) as total_fines,
               COALESCE(SUM(discount_amount), 0) as additional_discounts
        FROM fee_transactions
        WHERE enrollment_id = ? AND is_cancelled = 0
    ");
    $txStmt->execute([$enrollmentId]);
    $fin = $txStmt->fetch(PDO::FETCH_ASSOC);

    $totalAgreed = (float)$data['total_agreed_fee'];
    $admissionFee = (float)$data['admission_fee'];
    $approvedDiscount = (float)$data['discount_amount'];
    $cumulativePaid = (float)$fin['cumulative_paid'];
    $totalFines = (float)$fin['total_fines'];

    // Net payable calculation
    $totalObligation = $totalAgreed + $totalFines;
    $previousDues = max(0.0, $totalObligation - $cumulativePaid);
    $isFullyPaid = ($previousDues <= 0.0 && $totalAgreed > 0);

    // Fetch previous confirmed transactions
    $histStmt = $pdo->prepare("
        SELECT id, receipt_no, receipt_voucher_no, amount_paid, due_amount,
               payment_date, payment_method, payment_mode, reference_no,
               received_by, remarks, created_at, is_cancelled, fine_amount
        FROM fee_transactions
        WHERE enrollment_id = ?
        ORDER BY id DESC
        LIMIT 10
    ");
    $histStmt->execute([$enrollmentId]);
    $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'enrollment_id'      => (int)$data['enrollment_id'],
        'student_id'         => (int)$data['student_id'],
        'full_name'          => $data['full_name'],
        'father_name'        => $data['father_name'] ?: '—',
        'student_reg_no'     => $data['student_reg_no'],
        'roll_number'        => $data['roll_number'] ?: '—',
        'contact_number'     => $data['contact_number'] ?: '—',
        'address'            => $data['address'] ?: '—',
        'course_name'        => $data['course_name'],
        'course_code'        => $data['course_code'],
        'batch_code'         => $data['batch_code'],
        'batch_name'         => $data['batch_name'],
        'tuition_class'      => $data['tuition_class'] ?: ($data['class_level'] ?: $data['batch_code']),
        'standard_fee'       => (float)$data['standard_fee'],
        'total_agreed_fee'   => $totalAgreed,
        'admission_fee'      => $admissionFee,
        'approved_discount'  => $approvedDiscount,
        'total_fines'        => $totalFines,
        'cumulative_paid'    => $cumulativePaid,
        'previous_dues'      => $previousDues,
        'total_payable'      => $previousDues,
        'is_fully_paid'      => $isFullyPaid,
        'payment_status'     => $isFullyPaid ? 'Fully Paid' : ($cumulativePaid > 0 ? 'Partial Payment' : 'Unpaid'),
        'payment_history'    => $history
    ];
}

// 1. Single enrollment detail request
if ($enrollmentId > 0) {
    $profile = getEnrollmentFeeProfile($pdo, $enrollmentId);
    if (!$profile) {
        jsonResponse(['status' => 'error', 'message' => 'Enrollment not found'], 404);
    }
    jsonResponse(['status' => 'success', 'data' => $profile]);
}

// 2. Search by query
if (!empty($query)) {
    $term = "%{$query}%";
    $searchSql = "
        SELECT e.id as enrollment_id, s.id as student_id, s.full_name, s.father_name, s.student_reg_no,
               e.roll_number, b.batch_code, b.batch_name, c.course_name, e.tuition_class, e.class_level,
               e.total_agreed_fee,
               COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) as cumulative_paid,
               COALESCE((SELECT SUM(fine_amount) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) as total_fines
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        WHERE s.full_name LIKE ? 
           OR s.student_reg_no LIKE ? 
           OR e.roll_number LIKE ? 
           OR c.course_name LIKE ? 
           OR b.batch_code LIKE ? 
           OR b.batch_name LIKE ?
           OR e.tuition_class LIKE ?
           OR e.class_level LIKE ?
        ORDER BY (s.student_reg_no = ?) DESC,
                 (s.full_name LIKE ?) DESC,
                 e.id DESC
        LIMIT 15
    ";
    $stmt = $pdo->prepare($searchSql);
    $exactTerm = $query;
    $startTerm = "{$query}%";
    $stmt->execute([
        $term, $term, $term, $term, $term, $term, $term, $term,
        $exactTerm, $startTerm
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $r) {
        $agreed = (float)$r['total_agreed_fee'];
        $paid = (float)$r['cumulative_paid'];
        $fines = (float)$r['total_fines'];
        $balance = max(0.0, ($agreed + $fines) - $paid);
        $isPaid = ($balance <= 0.0 && $agreed > 0);

        $results[] = [
            'enrollment_id'   => (int)$r['enrollment_id'],
            'student_id'      => (int)$r['student_id'],
            'full_name'       => $r['full_name'],
            'father_name'     => $r['father_name'] ?: '—',
            'student_reg_no'  => $r['student_reg_no'],
            'roll_number'     => $r['roll_number'] ?: '—',
            'course_name'     => $r['course_name'],
            'batch_code'      => $r['batch_code'],
            'class_label'     => $r['tuition_class'] ?: ($r['class_level'] ?: $r['batch_code']),
            'total_agreed_fee'=> $agreed,
            'cumulative_paid' => $paid,
            'balance_due'     => $balance,
            'is_fully_paid'   => $isPaid,
            'status_label'    => $isPaid ? 'Fully Paid' : ($paid > 0 ? 'Partial' : 'Unpaid')
        ];
    }

    jsonResponse(['status' => 'success', 'count' => count($results), 'data' => $results]);
}

jsonResponse(['status' => 'success', 'data' => []]);
