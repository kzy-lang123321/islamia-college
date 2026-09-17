<?php
/**
 * Secure Logout Endpoint
 */
require_once __DIR__ . '/includes/auth.php';

logoutUser();

$base = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
header("Location: {$base}/login.php?logged_out=1");
exit;
