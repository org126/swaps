<?php
/**
 * Debug page to diagnose database issues
 */
$dbHost = "127.0.0.1";
$dbName = "secure_web";
$dbUser = "root";
$dbPass = "";

try {
  $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (Throwable $e) {
  die("DB connection failed: " . $e->getMessage());
}

echo "<h2>Database Diagnostic Report</h2>";

// 1) Check if machines table exists and show columns
echo "<h3>Machines Table</h3>";
try {
  $stmt = $pdo->query("DESCRIBE machines");
  $cols = $stmt->fetchAll();
  echo "<p><strong>Columns:</strong></p><pre>";
  foreach ($cols as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
  }
  echo "</pre>";
} catch (Throwable $e) {
  echo "<p style='color:red;'><strong>ERROR: machines table doesn't exist!</strong><br>" . $e->getMessage() . "</p>";
}

// 2) Check data in machines
echo "<h3>Machines Data</h3>";
try {
  $stmt = $pdo->query("SELECT * FROM machines");
  $rows = $stmt->fetchAll();
  if (empty($rows)) {
    echo "<p style='color:red;'><strong>ERROR: No data in machines table!</strong></p>";
  } else {
    echo "<p>Found " . count($rows) . " rows:</p><pre>";
    foreach ($rows as $row) {
      echo "Part: " . $row['part_number'] . " | State: " . $row['state'] . "\n";
    }
    echo "</pre>";
  }
} catch (Throwable $e) {
  echo "<p style='color:red;'><strong>ERROR:</strong> " . $e->getMessage() . "</p>";
}

// 3) Check reports table columns
echo "<h3>Reports Table</h3>";
try {
  $stmt = $pdo->query("DESCRIBE reports");
  $cols = $stmt->fetchAll();
  echo "<p><strong>Columns:</strong></p><pre>";
  foreach ($cols as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
  }
  echo "</pre>";
} catch (Throwable $e) {
  echo "<p style='color:red;'><strong>ERROR: reports table doesn't exist!</strong><br>" . $e->getMessage() . "</p>";
}

// 4) Test INSERT into reports
echo "<h3>Test INSERT into reports</h3>";
try {
  $stmt = $pdo->prepare("
    INSERT INTO reports (part_number, issue, severity, urgency, status)
    VALUES (?, ?, ?, ?, 'out_of_order')
  ");
  $stmt->execute(['PN-TEST-1', 'Test issue', 5, 5]);
  echo "<p style='color:green;'><strong>SUCCESS:</strong> INSERT works! Test ID: " . $pdo->lastInsertId() . "</p>";
  
  // Clean up test
  $pdo->exec("DELETE FROM reports WHERE part_number = 'PN-TEST-1'");
} catch (Throwable $e) {
  echo "<p style='color:red;'><strong>ERROR on INSERT:</strong> " . $e->getMessage() . "</p>";
}

// 5) Test UPDATE on machines
echo "<h3>Test UPDATE on machines</h3>";
try {
  $stmt = $pdo->prepare("UPDATE machines SET state = ? WHERE part_number = ?");
  $stmt->execute(['out_of_order', 'PN-1001']);
  if ($stmt->rowCount() > 0) {
    echo "<p style='color:green;'><strong>SUCCESS:</strong> UPDATE works! Affected rows: " . $stmt->rowCount() . "</p>";
    // Reset back
    $stmt->execute(['available', 'PN-1001']);
  } else {
    echo "<p style='color:red;'><strong>ERROR:</strong> UPDATE ran but 0 rows matched. Part PN-1001 doesn't exist!</p>";
  }
} catch (Throwable $e) {
  echo "<p style='color:red;'><strong>ERROR on UPDATE:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr><p><a href='/swap/Main_Report.php'>Back to Report Page</a></p>";
?>
