<?php
/**
 * Global Search REST API Endpoint
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure user is authenticated
if (empty($_SESSION['user_id'])) {
    jsonResponse(['status' => 'error', 'error' => 'Unauthorized'], 401);
}

if (!hasRole(['admin', 'accountant', 'registrar', 'staff'])) {
    jsonResponse(['error' => 'Forbidden'], 403);
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    jsonResponse(['status' => 'success', 'results' => [], 'data' => []]);
}

$pdo = Database::getConnection();
$searchTerm = "%{$query}%";
$results = [];
$base = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';

if (hasRole(['admin', 'registrar', 'staff'])) {
// 1. Search Students
$stmt = $pdo->prepare("
    SELECT id, student_reg_no, full_name, father_name, contact_number 
    FROM students 
    WHERE full_name LIKE ? OR student_reg_no LIKE ? OR father_name LIKE ? OR contact_number LIKE ?
    LIMIT 6
");
$stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
while ($row = $stmt->fetch()) {
    $results[] = [
        'category' => 'Student',
        'icon'     => '🎓',
        'title'    => $row['full_name'],
        'subtitle' => $row['student_reg_no'] . ' • Father: ' . ($row['father_name'] ?: '—'),
        'url'      => "{$base}/modules/students/view.php?id=" . (int)$row['id']
    ];
}

}

if (hasRole(['admin'])) {
// 2. Search Staff
$stmt = $pdo->prepare("
    SELECT id, staff_code, full_name, designation 
    FROM staff_members 
    WHERE full_name LIKE ? OR staff_code LIKE ? 
    LIMIT 4
");
$stmt->execute([$searchTerm, $searchTerm]);
while ($row = $stmt->fetch()) {
    $results[] = [
        'category' => 'Staff',
        'icon'     => '👔',
        'title'    => $row['full_name'],
        'subtitle' => $row['staff_code'] . ' • ' . $row['designation'],
        'url'      => "{$base}/modules/staff/index.php"
    ];
}

}

if (hasRole(['admin', 'registrar', 'staff'])) {
// 3. Search Courses
$stmt = $pdo->prepare("
    SELECT id, course_code, course_name, category 
    FROM courses 
    WHERE course_name LIKE ? OR course_code LIKE ? 
    LIMIT 4
");
$stmt->execute([$searchTerm, $searchTerm]);
while ($row = $stmt->fetch()) {
    $results[] = [
        'category' => 'Course',
        'icon'     => '📚',
        'title'    => $row['course_name'],
        'subtitle' => $row['course_code'] . ' • ' . $row['category'],
        'url'      => "{$base}/modules/courses/index.php?search=" . urlencode($row['course_code'])
    ];
}

}

if (hasRole(['admin', 'registrar', 'staff'])) {
// 4. Search Batches
$stmt = $pdo->prepare("
    SELECT b.id, b.batch_code, b.batch_name, c.course_name 
    FROM batches b 
    JOIN courses c ON b.course_id = c.id
    WHERE b.batch_code LIKE ? OR b.batch_name LIKE ?
    LIMIT 4
");
$stmt->execute([$searchTerm, $searchTerm]);
while ($row = $stmt->fetch()) {
    $results[] = [
        'category' => 'Batch',
        'icon'     => '👥',
        'title'    => $row['batch_code'],
        'subtitle' => $row['batch_name'] . ' (' . $row['course_name'] . ')',
        'url'      => "{$base}/modules/students/index.php?batch_id=" . (int)$row['id']
    ];
}

}

if (hasRole(['admin', 'registrar', 'staff'])) {
// 5. Search Board Roll Numbers
$stmt = $pdo->prepare("
    SELECT b.id, b.board_roll_no, b.board_registration_no, b.trade_course_name, s.full_name, s.id as student_id
    FROM board_examinations b
    JOIN students s ON b.student_id = s.id
    WHERE b.board_roll_no LIKE ? OR b.board_registration_no LIKE ?
    LIMIT 4
");
$stmt->execute([$searchTerm, $searchTerm]);
while ($row = $stmt->fetch()) {
    $results[] = [
        'category' => 'Board Exam',
        'icon'     => '📋',
        'title'    => 'Roll: ' . $row['board_roll_no'] . ' — ' . $row['full_name'],
        'subtitle' => $row['trade_course_name'] . ' • Reg: ' . $row['board_registration_no'],
        'url'      => "{$base}/modules/students/view.php?id=" . (int)$row['student_id']
    ];
}

}

if (hasRole(['admin', 'accountant'])) {
// 6. Search Capital Assets
$stmt = $pdo->prepare("
    SELECT id, asset_name, specification 
    FROM capital_assets 
    WHERE asset_name LIKE ? OR specification LIKE ?
    LIMIT 2
");
$stmt->execute([$searchTerm, $searchTerm]);
while ($row = $stmt->fetch()) {
    $results[] = [
        'category' => 'Asset',
        'icon'     => '⚡',
        'title'    => $row['asset_name'],
        'subtitle' => $row['specification'] ?: 'Capital Equipment',
        'url'      => "{$base}/modules/assets/index.php"
    ];
}

}

if (hasRole(['admin', 'accountant', 'registrar', 'staff'])) {
// 7. Search Fee Receipts
$stmt = $pdo->prepare("
    SELECT f.id, f.receipt_no, f.amount_paid, s.full_name, s.id as student_id
    FROM fee_transactions f
    JOIN enrollments e ON f.enrollment_id = e.id
    JOIN students s ON e.student_id = s.id
    WHERE f.receipt_no LIKE ? OR f.receipt_voucher_no LIKE ?
    LIMIT 3
");
$stmt->execute([$searchTerm, $searchTerm]);
while ($row = $stmt->fetch()) {
    $results[] = [
        'category' => 'Receipt',
        'icon'     => '💳',
        'title'    => 'Receipt #' . ($row['receipt_no'] ?: $row['id']) . ' (Rs. ' . number_format((float)$row['amount_paid'], 0) . ')',
        'subtitle' => 'Paid by: ' . $row['full_name'],
        'url'      => "{$base}/modules/fees/receipt.php?id=" . (int)$row['id']
    ];
}

}

jsonResponse(['status' => 'success', 'results' => $results, 'data' => $results]);
