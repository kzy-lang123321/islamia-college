<?php
// Uses only a dedicated temporary MySQL instance. Never point this at college data.
if (getenv('DB_PORT') !== '33317' || getenv('DB_NAME') !== 'islamia_college_check') {
    fwrite(STDERR, "Set DB_PORT=33317 and DB_NAME=islamia_college_check for the isolated test database.\n");
    exit(1);
}
ini_set('session.save_path', __DIR__ . '/../.local-checks');
$_SERVER['REQUEST_URI'] = '/islamia%20college/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/validation.php';
$checks = 0;
function expect($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33317;charset=utf8mb4', 'root', getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$sql = file_get_contents(__DIR__ . '/../database/schema.sql');
$sql = str_replace('islamia_college_db', 'islamia_college_check', $sql);
$sql = str_replace('ON DELETE RESTRICT', 'ON DELETE CASCADE', $sql);
$pdo->exec($sql);
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$property = new ReflectionProperty(Database::class, 'instance');
$property->setAccessible(true);
$property->setValue(null, $pdo);
$before = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
ob_start();
require __DIR__ . '/../database/upgrade.php';
require __DIR__ . '/../database/upgrade.php';
ob_end_clean();
expect((int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() === $before, 'Upgrade must preserve students');
$pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, phone) VALUES ('check_admin', ?, 'Test Administrator', 'admin', '03001234567')")->execute([password_hash('changed-secret-2026', PASSWORD_BCRYPT)]);
foreach (['staff', 'accountant', 'registrar'] as $role) {
    $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)")->execute(['check_' . $role, password_hash('test-secret-2026', PASSWORD_BCRYPT), 'Test ' . $role, $role]);
}
$pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, must_change_password) VALUES ('check_forced', ?, 'Test Initial Password', 'admin', 1)")->execute([password_hash('test-secret-2026', PASSWORD_BCRYPT)]);
expect(loginUser('check_admin', '03001234567')['success'] === false, 'Phone number must not bypass a changed password');
expect(loginUser('check_admin', 'changed-secret-2026')['success'] === true, 'Actual password must work');
expect(loginUser('0300-1234567', 'changed-secret-2026')['success'] === true, 'Phone identifier should still work');
expect(!Validator::validateFee(['enrollment_id' => 1, 'amount_paid' => 100]), 'Positive fee accepted');
expect((bool)Validator::validateFee(['enrollment_id' => 1, 'amount_paid' => 0]), 'Zero fee rejected');
expect((bool)Validator::validateFee(['enrollment_id' => -1, 'amount_paid' => 100]), 'Negative enrollment rejected');
$token = generateCsrfToken();
expect(verifyCsrfToken($token), 'Valid CSRF token accepted');
expect(!verifyCsrfToken('invalid'), 'Invalid CSRF token rejected');
foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
    foreach ([true, false] as $valid) {
        $code = '$_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . '; require ' . var_export(realpath(__DIR__ . '/../includes/functions.php'), true) . '; $_SESSION["csrf_token"]="correct"; $_SERVER["HTTP_X_CSRF_TOKEN"]=' . ($valid ? '"correct"' : '"incorrect"') . '; register_shutdown_function(function(){echo "STATUS=".(http_response_code() ?: 200);}); validateCsrfRequest();';
        $pipes = [];
        $proc = proc_open([PHP_BINARY, '-d', 'session.save_path=' . __DIR__ . '/../.local-checks', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $result = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        expect(proc_close($proc) === 0 && strpos($result, 'STATUS=' . ($valid ? 200 : 403)) !== false, "$method CSRF protection failed: $error");
    }
}
// Read the actual dashboard queries so fixture results verify application behavior.
$source = file_get_contents(__DIR__ . '/../index.php');
$pdo->beginTransaction();
$enrollmentId = (int)$pdo->query('SELECT id FROM enrollments LIMIT 1')->fetchColumn();
$pdo->exec('UPDATE fee_transactions SET is_cancelled=1');
$pdo->exec('UPDATE enrollments SET total_agreed_fee=0');
$pdo->exec("UPDATE enrollments SET total_agreed_fee=1000 WHERE id=$enrollmentId");
$pdo->exec("INSERT INTO fee_transactions (enrollment_id, amount_paid, fine_amount, is_cancelled) VALUES ($enrollmentId,400,100,0),($enrollmentId,500,0,1)");
preg_match('/\$totalFeeCollected = .*?query\("([^"]+)"\)/', $source, $m);
expect((float)$pdo->query($m[1])->fetchColumn() === 400.0, 'Cancelled payments must not count as collected');
preg_match('/\$totalOutstandingDues = .*?query\("([^"]+)"\)/', $source, $m);
expect((float)$pdo->query($m[1])->fetchColumn() === 700.0, 'Outstanding includes fines and ignores cancelled payments');
foreach (["DELETE FROM enrollments WHERE id=$enrollmentId", 'DELETE FROM students WHERE id=(SELECT student_id FROM enrollments WHERE id=' . $enrollmentId . ')'] as $delete) {
    $blocked = false;
    try { $pdo->exec($delete); } catch (PDOException $e) { $blocked = $e->getCode() === '23000'; }
    expect($blocked, 'Linked financial records must block deletion');
}
$pdo->rollBack();
echo "$checks integration checks passed. Imported $before students in isolated database.\n";
