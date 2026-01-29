<?php
// db_connect.php
// Secure PDO database connection for secure_web

$host = "localhost";
$dbname = "secure_web";
$username = "root";        // change if different
$password = "";            // change if you set one

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Do NOT expose DB details in production
    die("Database connection failed.");
}
