<?php
require_once 'session_check.php';
// Require admin role to run this script
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}

/**
 * Add more machines to database
 * Visit: http://localhost/swaps/add_machines.php
 */
require_once __DIR__ . '/config.php';

try {
  $pdo = getPDOConnection();
  echo "<h2>Adding More Machines...</h2>";
} catch (Throwable $e) {
  die("<p style='color:red;'><strong>ERROR:</strong> Database connection failed. Please check .env configuration.</p>");
}

// Add PN-1004 to PN-1009
$stmt = $pdo->prepare("INSERT IGNORE INTO machines (part_number, description, state) VALUES (?, ?, 'available')");

$machines = [
  ['PN-1004', 'Widget D'],
  ['PN-1005', 'Widget E'],
  ['PN-1006', 'Widget F'],
  ['PN-1007', 'Widget G'],
  ['PN-1008', 'Widget H'],
  ['PN-1009', 'Widget I'],
];

$count = 0;
foreach ($machines as $m) {
  try {
    $stmt->execute($m);
    echo "<p style='color:green;'>✓ Added {$m[0]} - {$m[1]}</p>";
    $count++;
  } catch (Throwable $e) {
    echo "<p style='color:orange;'>⚠ {$m[0]} already exists</p>";
  }
}

echo "<hr>";
echo "<h3 style='color:green;'>Done! Added {$count} new machines</h3>";

// Show all machines
try {
  $stmt = $pdo->query("SELECT part_number, description, state FROM machines ORDER BY part_number");
  $rows = $stmt->fetchAll();
  echo "<h3>All Machines Now Available:</h3><ul>";
  foreach ($rows as $row) {
    echo "<li><strong>" . htmlspecialchars($row['part_number']) . "</strong> - " . htmlspecialchars($row['description']) . " (State: " . htmlspecialchars($row['state']) . ")</li>";
  }
  echo "</ul>";
} catch (Throwable $e) {
  echo "<p style='color:red;'>Error fetching machines: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='/swap/Main_Report.php' style='font-size:16px; padding:10px 20px; background:#4f7cff; color:white; text-decoration:none; border-radius:5px;'>→ Back to Report Page</a></p>";
?>
