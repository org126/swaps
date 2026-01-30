<?php
/**
 * Configuration File for Sagana Part Database (sagana_part.sql)
 * Loads environment variables from .env file
 * Store database credentials in .env (NOT in code repository)
 * Used by: Main_Report.php, technician.php
 */

// Load .env file if it exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Sagana database connection settings (from .env or defaults)
define('SAGANA_DB_HOST', $_ENV['SAGANA_DB_HOST'] ?? '127.0.0.1');
define('SAGANA_DB_PORT', $_ENV['SAGANA_DB_PORT'] ?? '3306');
define('SAGANA_DB_USER', $_ENV['SAGANA_DB_USER'] ?? 'root');
define('SAGANA_DB_PASS', $_ENV['SAGANA_DB_PASS'] ?? '');
define('SAGANA_DB_NAME', $_ENV['SAGANA_DB_NAME'] ?? 'secure_web');

// Error logging (don't expose errors to users)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Helper function to get PDO connection for Sagana database
function getSaganaPDOConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . SAGANA_DB_HOST . ";port=" . SAGANA_DB_PORT . ";dbname=" . SAGANA_DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, SAGANA_DB_USER, SAGANA_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            error_log('Sagana database connection error: ' . $e->getMessage());
            throw new Exception('Sagana database connection failed.');
        }
    }
    return $pdo;
}
?>
