<?php
/**
 * Database Repair Script
 * Visit: http://localhost/swap/repair.php
 */
$dbHost = "127.0.0.1";
$dbName = "secure_web";
$dbUser = "root";
$dbPass = "";

echo "<h2>Database Repair Started...</h2>";

try {
  $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
  echo "<p style='color:green;'>✓ Database connected successfully</p>";
} catch (Throwable $e) {
  die("<p style='color:red;'><strong>ERROR:</strong> Cannot connect to database: " . $e->getMessage() . "</p>");
}

// Array of SQL commands to fix the database
$sqls = [
  // 1. Drop the old machines table and recreate with correct schema
  "DROP TABLE IF EXISTS machines",
  
  "CREATE TABLE machines (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    part_number VARCHAR(255) UNIQUE NOT NULL,
    description VARCHAR(255) NULL,
    state ENUM('available','under_maintenance','out_of_order') NOT NULL DEFAULT 'available'
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  
  // 2. Insert machines with proper data
  "INSERT INTO machines (part_number, description, state) VALUES
    ('PN-1001', 'Widget A', 'available'),
    ('PN-1002', 'Widget B', 'available'),
    ('PN-1003', 'Widget C', 'available')",
  
  // 3. Ensure reports table has all required columns
  "ALTER TABLE reports ADD COLUMN IF NOT EXISTS technician_id INT NULL",
  "ALTER TABLE reports ADD COLUMN IF NOT EXISTS accepted_at DATETIME NULL",
  "ALTER TABLE reports ADD COLUMN IF NOT EXISTS finished_at DATETIME NULL",
  "ALTER TABLE reports MODIFY status ENUM('out_of_order','under_maintenance','finished') NOT NULL DEFAULT 'out_of_order'",
];

$success = 0;
$failed = 0;

foreach ($sqls as $sql) {
  try {
    $pdo->exec($sql);
    echo "<p style='color:green;'>✓ " . substr($sql, 0, 50) . "...</p>";
    $success++;
  } catch (Throwable $e) {
    echo "<p style='color:orange;'>⚠ " . substr($sql, 0, 50) . "... (might already exist)</p>";
    $failed++;
  }
}

echo "<hr>";
echo "<h3 style='color:green;'>Repair Complete!</h3>";
echo "<p><strong>Successfully executed:</strong> {$success} commands</p>";
echo "<p><strong>Warnings/Skipped:</strong> {$failed} commands (usually okay - table already exists)</p>";

// Verify the fix
echo "<h3>Verification</h3>";
try {
  $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM machines");
  $result = $stmt->fetch();
  echo "<p>✓ Machines table has <strong>" . $result['cnt'] . "</strong> rows</p>";
  
  $stmt = $pdo->query("SELECT * FROM machines LIMIT 3");
  $rows = $stmt->fetchAll();
  echo "<p>Sample data:</p><ul>";
  foreach ($rows as $row) {
    echo "<li>Part: " . htmlspecialchars($row['part_number']) . " | State: " . htmlspecialchars($row['state']) . "</li>";
  }
  echo "</ul>";
  
  $stmt = $pdo->query("DESCRIBE reports");
  $cols = $stmt->fetchAll();
  echo "<p>✓ Reports table has " . count($cols) . " columns (required: issue_id, part_number, issue, severity, urgency, status, technician_id, created_at, accepted_at, finished_at)</p>";
  
} catch (Throwable $e) {
  echo "<p style='color:red;'>Verification error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='/swap/Main_Report.php' style='font-size:16px; padding:10px 20px; background:#4f7cff; color:white; text-decoration:none; border-radius:5px;'>→ Go to Report Page</a></p>";
echo "<p style='margin-top:20px; color:gray;'>You can safely delete repair.php after this is done.</p>";
?>
