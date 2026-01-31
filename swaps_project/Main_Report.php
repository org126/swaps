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
        INSERT INTO reports (part_number, issue, severity, urgency, status)
        VALUES (?, ?, ?, ?, 'out_of_order')
      ");
      $stmt->execute([$part_number, $issue, $severity, $urgency]);
      $newReportId = (int)$pdo->lastInsertId();

      // 2) Update machine part status -> out_of_order (adjust table/columns if needed)
      $stmt2 = $pdo->prepare("
        UPDATE {$MACHINE_PARTS_TABLE}
        SET {$STATUS_COL} = ?
        WHERE {$PART_COL} = ?
      ");
      $stmt2->execute([$STATUS_OUT_OF_ORDER, $part_number]);
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
  <style>
    body { font-family: Arial, sans-serif; background: #0b1220; color: #e7eefc; padding: 28px; }
    .card { max-width: 900px; margin: 0 auto; background: #111b2e; border: 1px solid #24304a; border-radius: 14px; padding: 20px; }
    h1 { margin: 0 0 10px; }
    .muted { color: #a8b6d8; }
    .row { display:flex; gap: 14px; flex-wrap: wrap; }
    label { display:block; font-weight: 700; margin: 12px 0 6px; }
    input, textarea, select { width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #2b3a5a; background: #0c1426; color: #e7eefc; font-size: 16px; }
    select { 
      cursor: pointer; 
      appearance: none; 
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%234f7cff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 10px center;
      background-size: 20px;
      padding-right: 40px;
    }
    select:hover { border-color: #4f7cff; }
    select:focus { outline: none; border-color: #4f7cff; box-shadow: 0 0 0 3px rgba(79, 124, 255, 0.1); }
    select option { background: #111b2e; color: #e7eefc; padding: 8px; }
    textarea { min-height: 120px; resize: vertical; }
    .col { flex: 1; min-width: 220px; }
    .btn { margin-top: 14px; padding: 10px 14px; border: 0; border-radius: 10px; background: #4f7cff; color: #fff; font-weight: 800; cursor: pointer; }
    .btn:hover { filter: brightness(1.08); }
    .alert { margin-top: 12px; padding: 10px 12px; border-radius: 10px; }
    .ok { background: #0f2a1b; border: 1px solid #1f6b3b; color: #bff3d0; }
    .err { background: #2a1111; border: 1px solid #6b1f1f; color: #f3bfbf; }
    a { color: #8fb1ff; }
  </style>
</head>
<body>
  <div class="card">
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
          <input type="text" id="part_number" name="part_number" placeholder="e.g., PN-1001" required style="width: 100%;" />
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

