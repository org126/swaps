<?php
declare(strict_types=1);

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
// EDIT THESE 3 SETTINGS ONLY
//////////////////////////////
$dbHost = "127.0.0.1";
$dbName = "secure_web";
$dbUser = "root";
$dbPass = "";

// Your EXISTING machine parts table + columns (adjust if your project uses different names)
$MACHINE_PARTS_TABLE = "machines";   // NOT machine_parts
$PART_COL            = "part_number"; // your column name is part_number
$STATUS_COL          = "state";       // your column name is state      

// Status strings used in your machine_parts table (adjust to match your existing schema values)
$STATUS_OUT_OF_ORDER = "out_of_order";       // must exist in your machine table data
$STATUS_UNDER_MAINTENANCE = "under_maintenance";
$STATUS_AVAILABLE = "available";             // change to "in_order" if your table uses that

function pdo_conn(string $host, string $db, string $user, string $pass): PDO {
  $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

function log_event(PDO $pdo, string $eventType, ?int $reportId, string $actorRole, ?int $actorId, ?string $details = null): void {
  $stmt = $pdo->prepare("
    INSERT INTO audit_logs (event_type, report_id, actor_role, actor_id, ip_address, user_agent, details)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
  $stmt->execute([$eventType, $reportId, $actorRole, $actorId, $ip, $ua, $details]);
}

$errors = [];
$successMsg = null;

try {
  $pdo = pdo_conn($dbHost, $dbName, $dbUser, $dbPass);
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB connection failed. Check DB settings in report.php.";
  exit;
}

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
  <title>Report Issue</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: Arial, sans-serif; background: #0b1220; color: #e7eefc; padding: 28px; }
    .card { max-width: 900px; margin: 0 auto; background: #111b2e; border: 1px solid #24304a; border-radius: 14px; padding: 20px; }
    h1 { margin: 0 0 10px; }
    .muted { color: #a8b6d8; }
    .row { display:flex; gap: 14px; flex-wrap: wrap; }
    label { display:block; font-weight: 700; margin: 12px 0 6px; }
    input, textarea { width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #2b3a5a; background: #0c1426; color: #e7eefc; }
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
    <p class="muted">Submit a report for a machine part. A report ID will be assigned automatically. Status will be set to <b>out_of_order</b>.</p>
    <p class="muted">Technician view: <a href="/swap/technician.php?tech_id=1">Technician Page</a></p>

    <?php if ($successMsg): ?>
      <div class="alert ok"><?= e($successMsg) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert err">
        <b>Fix these:</b>
        <ul>
          <?php foreach ($errors as $er): ?>
            <li><?= e($er) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

   <form method="post" action="/swap/Main_Report.php" autocomplete="off">
      <div class="row">
        <div class="col">
          <label>Machine Part Number</label>
          <input name="part_number" placeholder="e.g., PN-1001" required maxlength="100" />
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
