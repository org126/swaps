<?php
declare(strict_types=1);

// Force browser to not cache this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/**
 * report.php
 * - Anyone can submit a maintenance report
 * - On submit:
 *   1) INSERT into reports
 *   2) Update machine part status -> out_of_order
 *   3) Log event (A09)
 *
 * Security: A03 Injection (PDO prepared statements + validation)
 */

//////////////////////////////
// DATABASE: Uses main database (database.sql schema)
//////////////////////////////
require_once __DIR__ . '/config.php';

// Main database schema: machines table
$MACHINE_PARTS_TABLE = "machines";
$PART_COL            = "part_number";
$STATUS_COL          = "state";

// Status strings
$STATUS_OUT_OF_ORDER = "out_of_order";
$STATUS_UNDER_MAINTENANCE = "in_maintenance";
$STATUS_AVAILABLE = "ready";
$STATUS_UNAVAILABLE = "out_of_order"; // Using out_of_order as unavailable status

function pdo_conn(): PDO {
  return getPDOConnection();
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

function log_event(PDO $pdo, string $eventType, ?int $reportId, string $actorRole, ?int $actorId, ?string $details = null): void {
  // Note: Main database doesn't have audit_logs table, log to error_log instead
  error_log("LOG EVENT: $eventType | report_id=$reportId | actor=$actorRole/$actorId | details=$details");
}

$errors = [];
$successMsg = null;
$machineAddedMsg = null;

try {
  $pdo = pdo_conn();
} catch (Throwable $e) {
  http_response_code(500);
  error_log('Main_Report DB connection error: ' . $e->getMessage());
  echo "DB connection failed. Check DB settings in config.php.";
  exit;
}

// Machine initialization removed - machines should be added via add_machines.php or admin interface

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $part_number = trim((string)($_POST["part_number"] ?? ""));
  $issue = trim((string)($_POST["issue"] ?? ""));
  $severity = (int)($_POST["severity"] ?? 0);
  $urgency = (int)($_POST["urgency"] ?? 0);

  // Validation (server-side)
  if ($part_number === "" || strlen($part_number) > 100) $errors[] = "Part number is required (max 100 chars).";
  if ($issue === "" || strlen($issue) > 2000) $errors[] = "Issue is required (max 2000 chars).";
  if ($severity < 1 || $severity > 10) $errors[] = "Severity must be between 1 and 10.";
  if ($urgency < 1 || $urgency > 10) $errors[] = "Urgency must be between 1 and 10.";

  // Check if machine part exists
  if ($part_number !== "") {
    try {
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$MACHINE_PARTS_TABLE} WHERE {$PART_COL} = ?");
      $stmt->execute([$part_number]);
      $count = (int)$stmt->fetchColumn();
      if ($count === 0) {
        $errors[] = "Machine part '{$part_number}' not found in system. Please check the part number.";
      }
    } catch (Throwable $e) {
      $errors[] = "Error validating machine part: " . $e->getMessage();
    }
  }

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // 1) Insert report (A03: prepared statement)
      $stmt = $pdo->prepare("
        INSERT INTO reports (part_number, issue, severity, urgency)
        VALUES (?, ?, ?, ?)
      ");
      $stmt->execute([$part_number, $issue, $severity, $urgency]);
      $newReportId = (int)$pdo->lastInsertId();

      // 2) Update machine part status -> unavailable (out_of_order)
      $stmt2 = $pdo->prepare("
        UPDATE {$MACHINE_PARTS_TABLE}
        SET {$STATUS_COL} = ?
        WHERE {$PART_COL} = ?
      ");
      $stmt2->execute([$STATUS_UNAVAILABLE, $part_number]);
      // Check if machine was updated (0 rows = machine doesn't exist)
      if ($stmt2->rowCount() === 0) {
        throw new Exception("Machine not found. Please use an existing part number like PN-1001, PN-1002, or PN-1003.");
      }

      // 3) Log (A09)
      log_event($pdo, "REPORT_CREATED", $newReportId, "reporter", null, "Report submitted");

      $pdo->commit();
      $successMsg = "Report submitted successfully. Issue ID: {$newReportId}";
    } catch (Throwable $ex) {
      $pdo->rollBack();
      log_event($pdo, "REPORT_CREATE_FAILED", null, "reporter", null, "Error: " . substr($ex->getMessage(), 0, 200));
      $errors[] = "Failed to submit report: " . $ex->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>Report Issue</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="/swaps_project/styles.css">
</head>
<body>
  <div class="card">
    <a href="/swaps_project/search.php" class="btn-back">← Back to Search</a>
    <h1>Report Maintenance Issue</h1>
    <p class="muted">Welcome to our Report Maintenance Issue page. How can we help you today?</p>
    <p class="muted">Technician view: <a href="/swaps_project/technician.php?tech_id=1">Technician Page</a></p>

    <?php if ($successMsg): ?>
      <div class="alert ok"><?= e($successMsg) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert err">
        <b>Error:</b>
        <ul>
          <?php foreach ($errors as $er): ?>
            <li><?= e($er) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

   <form method="post" action="/swaps_project/Main_Report.php?v=2" autocomplete="off">
      <div class="row">
        <div class="col">
          <label for="part_number">Machine Part Number</label>
          <input type="text" id="part_number" name="part_number" placeholder="e.g., PN-1001" required />
        </div>
        <div class="col">
          <label>Severity (1-10)</label>
          <input name="severity" type="number" min="1" max="10" required />
        </div>
        <div class="col">
          <label>Urgency (1-10)</label>
          <input name="urgency" type="number" min="1" max="10" required />
        </div>
      </div>

      <label>Issue (description)</label>
      <textarea name="issue" placeholder="Describe the problem clearly..." required maxlength="2000"></textarea>

      <button class="btn" type="submit">Submit Report</button>
    </form>
  </div>
</body>
</html>

