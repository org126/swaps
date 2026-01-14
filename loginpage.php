<?php
/*
 * show_all.php
 * Simple development tool: connect to a MySQL database via PDO and display
 * table contents in HTML. Intended for local/dev usage only (do NOT use in
 * production as-is; it exposes DB contents and accepts credentials via GET).
 *
 * Query params:
 *  - host (default 127.0.0.1)
 *  - port (default 3306)
 *  - db   (database/schema name) REQUIRED to connect and list tables
 *  - user (default root)
 *  - pass (password for DB user)
 *  - limit (optional row limit per table; default 100)
 *  - table (optional specific table to display)
 *  - show_all (if set to 1, ignores limit and shows all rows for the selected table)
 */

// Escape helper for outputting HTML-safe values
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Connection and UI defaults (overridden by GET params)
$host = $_GET['host'] ?? '127.0.0.1';
$port = $_GET['port'] ?? '3306';
$db   = $_GET['db']   ?? '';
$user = $_GET['user'] ?? 'root';
$pass = $_GET['pass'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

// State holders
$error = null;
$tables = [];

// If a database/schema name is provided, attempt to connect and list tables
if($db){
  try{
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    // Create PDO with exceptions enabled and associative fetch mode
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Query information_schema for the list of tables in the requested schema
    $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME");
    $stmt->execute([':schema' => $db]);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
  }catch(Exception $e){
    // Capture error message for display in the page
    $error = $e->getMessage();
  }
}
?>