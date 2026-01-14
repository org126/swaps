<?php
declare(strict_types=1);

/**
 * Central DB connector (PDO)
 * - Prepared statements everywhere
 * - No DB details echoed to users
 */

$DB_HOST = '127.0.0.1';
$DB_NAME = 'secure_web';   // change later if your DB name differs
$DB_USER = 'root';         // change later
$DB_PASS = '';             // change later

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Throwable $e) {
    // Do NOT leak internal errors to users
    http_response_code(500);
    exit('Internal server error.');
}
