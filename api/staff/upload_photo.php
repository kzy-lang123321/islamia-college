<?php
/**
 * Staff Profile Photo Upload API
 * Islamia College of Technology College Management System
 */
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/auth.php";

if (!empty($_SESSION['user_id']) && !empty($_SESSION['must_change_password'])) {
    jsonResponse(['error' => 'Please change your initial password before continuing.'], 403);
}

header("Content-Type: application/json");

if (empty($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}
if (!hasRole(["admin"])) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Forbidden"]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    jsonResponse(['error' => 'Method not allowed'], 405);
}
validateCsrfRequest();

$staffId = (int)($_POST["staff_id"] ?? 0);
if ($staffId <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid staff ID."]);
    exit;
}

if (empty($_FILES["photo"]) || $_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
    $codes = [1=>"File too large (server limit).",2=>"File too large.",3=>"Partial upload.",4=>"No file uploaded.",6=>"No temp folder.",7=>"Write failed.",8=>"Extension blocked."];
    $code  = $_FILES["photo"]["error"] ?? 4;
    echo json_encode(["success" => false, "error" => $codes[$code] ?? "Upload error."]);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $_FILES["photo"]["tmp_name"]);
finfo_close($finfo);

$allowed = ["image/jpeg","image/jpg","image/png","image/webp"];
if (!in_array($mime, $allowed, true)) {
    echo json_encode(["success" => false, "error" => "Only JPG, PNG, and WebP images are allowed."]);
    exit;
}

if ($_FILES["photo"]["size"] > 3 * 1024 * 1024) {
    echo json_encode(["success" => false, "error" => "Maximum file size is 3 MB."]);
    exit;
}

$uploadDir = __DIR__ . "/../../assets/images/staff/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

try {
    $pdo  = Database::getConnection();
    $stmt = $pdo->prepare("SELECT profile_photo FROM staff_members WHERE id = ?");
    $stmt->execute([$staffId]);
    $row  = $stmt->fetch();
} catch (Exception $e) {
    error_log("Upload photo DB: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error."]);
    exit;
}
if (!$row) {
    echo json_encode(["success" => false, "error" => "Staff member not found."]);
    exit;
}

if (!empty($row["profile_photo"])) {
    $old = $uploadDir . basename($row["profile_photo"]);
    if (file_exists($old)) @unlink($old);
}

$extMap   = ["image/png"=>"png","image/webp"=>"webp"];
$ext      = $extMap[$mime] ?? "jpg";
$filename = "staff_" . $staffId . "_" . bin2hex(random_bytes(8)) . "." . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $dest)) {
    echo json_encode(["success" => false, "error" => "Failed to save file."]);
    exit;
}

try {
    $upd = $pdo->prepare("UPDATE staff_members SET profile_photo = ? WHERE id = ?");
    $upd->execute([$filename, $staffId]);
} catch (Exception $e) {
    error_log("Upload photo update: " . $e->getMessage());
    @unlink($dest);
    echo json_encode(["success" => false, "error" => "DB update failed."]);
    exit;
}

$base = strpos($_SERVER["REQUEST_URI"], "/islamia%20college") !== false ? "/islamia%20college" : "";
echo json_encode(["success" => true, "photo_url" => $base . "/assets/images/staff/" . $filename, "filename" => $filename]);

