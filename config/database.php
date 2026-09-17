<?php
/**
 * Database Configuration for Islamia College of Technology Management System
 */

// Disable direct error display to users in production; log internally
ini_set('display_errors', '0');
error_reporting(E_ALL);

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'islamia_college_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log technical error securely to server error log without leaking details to browser
                error_log("Database connection error: " . $e->getMessage());
                http_response_code(500);
                echo "<div style='background:#0f172a; color:#f8fafc; font-family:sans-serif; padding:40px; text-align:center; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;'>";
                echo "<h1 style='color:#38bdf8; margin-bottom:12px;'>Service Temporarily Unavailable</h1>";
                echo "<p style='color:#94a3b8; max-width:500px;'>The College Management System is currently unable to establish a secure database connection. Please try again in a few moments or contact the system administrator.</p>";
                echo "</div>";
                exit;
            }
        }
        return self::$instance;
    }
}
