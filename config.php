<?php
/**
 * Secure Configuration File
 * Store database credentials securely (NOT in URL or code repository)
 */

// Database connection settings
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');  // Use environment variables in production
define('DB_NAME', 'swaps');

// Security settings
define('REQUIRE_HTTPS', false);  // Set to true in production
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_REPORT_LIMIT', 100); // Maximum reports to display

// Error logging (don't expose errors to users)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => REQUIRE_HTTPS,
        'samesite' => 'Strict'
    ]);
    session_start();
}
?>
