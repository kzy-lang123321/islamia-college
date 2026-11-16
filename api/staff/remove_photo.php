<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";
if (!empty($_SESSION['user_id']) && !empty($_SESSION['must_change_password'])) {
    jsonResponse(['error' => 'Please change your initial password before continuing.'], 403);
}

header("Content-Type: application/json");
if (empty($_SESSION["user_id"])) { http_response_code(401); echo json_encode(["success"=>false,"error"=>"Unauthorized"]); exit; }
if (!hasRole(["admin"])) { http_response_code(403); echo json_encode(["success"=>false,"error"=>"Forbidden"]); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    jsonResponse(['error' => 'Method not allowed'], 405);
}
validateCsrfRequest();
$staffId = (int)($_POST["staff_id"] ?? 0);
if ($staffId <= 0) { echo json_encode(["success"=>false,"error"=>"Invalid staff ID."]); exit; }
try {
    $pdo  = Database::getConnection();
    $stmt = $pdo->prepare("SELECT profile_photo FROM staff_members WHERE id=?");
    $stmt->execute([$staffId]);
    $row  = $stmt->fetch();
    if (!$row) { echo json_encode(["success"=>false,"error"=>"Staff not found."]); exit; }
    if (!empty($row["profile_photo"])) {
        $f = __DIR__ . "/../../assets/images/staff/" . basename($row["profile_photo"]);
        if (file_exists($f)) @unlink($f);
    }
    $upd = $pdo->prepare("UPDATE staff_members SET profile_photo=NULL WHERE id=?");
    $upd->execute([$staffId]);
    echo json_encode(["success"=>true]);
} catch (Exception $e) {
    error_log("Remove photo: " . $e->getMessage());
    echo json_encode(["success"=>false,"error"=>"Database error."]);
}

