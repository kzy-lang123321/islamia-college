<?php
/** Run from the project root: C:\xampp\php\php.exe database\upgrade.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();
$columns = ['courses' => ['department_id' => 'INT NULL'], 'users' => ['email' => "VARCHAR(100) NULL",
'phone' => "VARCHAR(30) NULL",
'must_change_password' => "TINYINT(1) NOT NULL DEFAULT 0",
'student_id' => "INT NULL",
'profile_photo' => "VARCHAR(255) NULL",
'privacy_hide_phone' => "TINYINT(1) NOT NULL DEFAULT 0",
'privacy_hide_email' => "TINYINT(1) NOT NULL DEFAULT 0",
'last_login_at' => "DATETIME NULL",
'last_login_ip' => "VARCHAR(45) NULL"],
'staff_members' => ['profile_photo' => "VARCHAR(255) NULL",
'salary_type' => "VARCHAR(20) NOT NULL DEFAULT 'fixed'",
'salary_percentage' => "DECIMAL(5,2) NOT NULL DEFAULT 0.00"],
'enrollments' => ['course_id' => "INT NULL",
'academic_session' => "VARCHAR(100) NULL",
'class_level' => "VARCHAR(100) NULL"]];
foreach ($columns as $table => $definitions) {
    $existing = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($definitions as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
            echo "Added $table.$name\n";
        }
    }
}
$authSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL,
    INDEX `idx_login_identifier_time` (`identifier`, `attempted_at`),
    INDEX `idx_login_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token_hash` CHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_reset_user` (`user_id`),
    CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
foreach (explode(";", $authSql) as $sql) { if (trim($sql) !== "") $pdo->exec($sql); }
// Keep historic fee and payroll records even when a parent is deleted.
$constraints = [
    ['batches', 'fk_batch_course', 'course_id', 'courses'],
    ['enrollments', 'fk_enroll_student', 'student_id', 'students'],
    ['enrollments', 'fk_enroll_batch', 'batch_id', 'batches'],
    ['fee_transactions', 'fk_tx_enrollment', 'enrollment_id', 'enrollments'],
    ['staff_payroll', 'fk_payroll_staff', 'staff_id', 'staff_members'],
];
foreach ($constraints as [$table, $name, $column, $parent]) {
    $stmt = $pdo->prepare("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME = ?");
    $stmt->execute([$table, $parent]);
    $fk = $stmt->fetch();
    if ($fk && in_array($fk['DELETE_RULE'], ['RESTRICT', 'NO ACTION'], true)) continue;
    $drop = $fk ? "DROP FOREIGN KEY `" . str_replace('`', '``', $fk['CONSTRAINT_NAME']) . "`, " : '';
    $pdo->exec("ALTER TABLE `$table` {$drop}ADD CONSTRAINT `{$name}_restrict` FOREIGN KEY (`$column`) REFERENCES `$parent` (`id`) ON DELETE RESTRICT");
}
echo "Database upgrade complete. Existing records preserved.\n";
