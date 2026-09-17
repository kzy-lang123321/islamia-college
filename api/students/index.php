<?php
/**
 * Students API - Secured CRUD with RBAC & CSRF Protection
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

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Check single student fetch
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([(int)$_GET['id']]);
            $student = $stmt->fetch();
            if (!$student) {
                jsonResponse(['status' => 'error', 'error' => 'Student not found'], 404);
            }
            jsonResponse(['status' => 'success', 'data' => $student]);
        }

        // Pagination and Search
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        $whereClause = "";
        $params = [];
        if (!empty($search)) {
            $whereClause = "WHERE full_name LIKE ? OR student_reg_no LIKE ? OR contact_number LIKE ? OR address LIKE ?";
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM students {$whereClause}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        $sql = "SELECT * FROM students {$whereClause} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        jsonResponse([
            'status' => 'success',
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_records' => $totalRows,
                'total_pages' => ceil($totalRows / $limit)
            ],
            'data' => $records
        ]);
        break;

    case 'POST':
        // Enforce RBAC & CSRF
        if (!hasRole(['admin', 'registrar'])) {
            jsonResponse(['status' => 'error', 'error' => '403 Forbidden: Insufficient permissions'], 403);
        }
        validateCsrfRequest();

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?? $_POST;

        $errors = Validator::validateStudent($input);
        if (!empty($errors)) {
            jsonResponse(['status' => 'error', 'errors' => $errors], 400);
        }

        $regNo = trim($input['student_reg_no'] ?? '');
        if (empty($regNo)) {
            $regNo = 'ICT-STD-' . rand(3000, 9999);
        }

        $stmt = $pdo->prepare("
            INSERT INTO students (student_reg_no, full_name, father_name, contact_number, cnic, date_of_birth, email, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $regNo,
            trim($input['full_name']),
            trim($input['father_name'] ?? ''),
            trim($input['contact_number'] ?? ''),
            trim($input['cnic'] ?? ''),
            $input['date_of_birth'] ?? null,
            trim($input['email'] ?? ''),
            trim($input['address'] ?? '')
        ]);

        $newId = (int)$pdo->lastInsertId();
        jsonResponse(['status' => 'success', 'message' => 'Student registered successfully', 'id' => $newId], 201);
        break;

    case 'PUT':
        if (!hasRole(['admin', 'registrar'])) {
            jsonResponse(['status' => 'error', 'error' => '403 Forbidden: Insufficient permissions'], 403);
        }
        validateCsrfRequest();

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?? [];
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Valid Student ID required'], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE students 
            SET full_name = ?, father_name = ?, contact_number = ?, cnic = ?, email = ?, address = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($input['full_name'] ?? ''),
            trim($input['father_name'] ?? ''),
            trim($input['contact_number'] ?? ''),
            trim($input['cnic'] ?? ''),
            trim($input['email'] ?? ''),
            trim($input['address'] ?? ''),
            $id
        ]);

        jsonResponse(['status' => 'success', 'message' => 'Student record updated successfully']);
        break;

    case 'DELETE':
        if (!hasRole(['admin', 'registrar'])) {
            jsonResponse(['status' => 'error', 'error' => '403 Forbidden: Insufficient permissions'], 403);
        }
        validateCsrfRequest();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Valid Student ID required'], 400);
        }

        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['status' => 'success', 'message' => 'Student record deleted successfully']);
        break;

    default:
        jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}
