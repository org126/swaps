<?php
declare(strict_types=1);

/**
 * technician.php
 * - Technician reads queue
 * - Accept: assigns technician.id + status under_maintenance + machine status under_maintenance
 * - Finish: sets report finished (disappears) + machine status available
 * - Logs every action (A09)
 * Security: A03 Injection (prepared statements + validation)
 */

//////////////////////////////
// EDIT THESE 3 SETTINGS ONLY
//////////////////////////////
$dbHost = "127.0.0.1";
$dbName = "swaps";
$dbUser = "root";
$dbPass = "";

// Your EXISTING machine parts table + columns (adjust if needed)
$MACHINE_PARTS_TABLE = "machines"; // use existing `machines` table
$PART_COL = "part_number";             // <-- change if yours is different
$STATUS_COL = "state";                // <-- change if yours is different

$STATUS_OUT_OF_ORDER = "out_of_order";
$STATUS_UNDER_MAINTENANCE = "under_maintenance";
$STATUS_AVAILABLE = "available"; // change to "in_order" if your table uses that

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

$techId = isset($_GET['tech_id']) ? (int)$_GET['tech_id'] : 0;
if ($techId <= 0) {
  http_response_code(400);
  echo "Missing tech_id. Example: /swap/technician.php?tech_id=1";
  exit;
}

try {
  $pdo = pdo_conn($dbHost, $dbName, $dbUser, $dbPass);
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB connection failed. Check DB settings in technician.php.";
  exit;
}

$msg = null;
$err = null;

// Handle actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = (string)($_POST["action"] ?? "");
  $issueId = (int)($_POST["issue_id"] ?? 0);

  if ($issueId <= 0) {
    $err = "Invalid issue_id.";
  } else {
    try {
      $pdo->beginTransaction();

      // Get report row (needed for part_number + status)
      $stmt = $pdo->prepare("SELECT issue_id, part_number, status FROM reports WHERE issue_id = ?");
      $stmt->execute([$issueId]);
      $r = $stmt->fetch();

      if (!$r) {
        throw new Exception("Report not found.");
      }

      $partNumber = $r["part_number"];
      $currentStatus = $r["status"];

      if ($action === "accept") {
        // Accept only if still out_of_order
        if ($currentStatus !== "out_of_order") {
          throw new Exception("Cannot accept. Current status: {$currentStatus}");
        }

        $stmt1 = $pdo->prepare("
          UPDATE reports
          SET status='under_maintenance', technician_id=?, accepted_at=NOW()
          WHERE issue_id=? AND status='out_of_order'
        ");
        $stmt1->execute([$techId, $issueId]);

        $stmt2 = $pdo->prepare("
          UPDATE {$MACHINE_PARTS_TABLE}
          SET {$STATUS_COL} = ?
          WHERE {$PART_COL} = ?
        ");
        $stmt2->execute([$STATUS_UNDER_MAINTENANCE, $partNumber]);

        log_event($pdo, "REPORT_ACCEPTED", $issueId, "technician", $techId, "Accepted report");
        $msg = "Accepted Issue ID {$issueId}.";
      }

      elseif ($action === "finish") {
        // Finish only if under_maintenance AND assigned to this technician
        $stmt1 = $pdo->prepare("
          UPDATE reports
          SET status='finished', finished_at=NOW()
          WHERE issue_id=? AND status='under_maintenance' AND technician_id=?
        ");
        $stmt1->execute([$issueId, $techId]);

        if ($stmt1->rowCount() === 0) {
          throw new Exception("Cannot finish: must be under_maintenance and assigned to you.");
        }

        $stmt2 = $pdo->prepare("
          UPDATE {$MACHINE_PARTS_TABLE}
          SET {$STATUS_COL} = ?
          WHERE {$PART_COL} = ?
        ");
        $stmt2->execute([$STATUS_AVAILABLE, $partNumber]);

        log_event($pdo, "REPORT_FINISHED", $issueId, "technician", $techId, "Finished report");
        $msg = "Finished Issue ID {$issueId}. (It will disappear from the list.)";
      }

      else {
        throw new Exception("Invalid action.");
      }

      $pdo->commit();
    } catch (Throwable $ex) {
      $pdo->rollBack();
      log_event($pdo, "TECH_ACTION_FAILED", $issueId ?: null, "technician", $techId, "Error: " . substr($ex->getMessage(), 0, 200));
      $err = $ex->getMessage();
    }
  }
}

// Fetch visible queue (not finished)
$stmt = $pdo->prepare("
  SELECT issue_id, part_number, issue, severity, urgency, status, created_at, technician_id
  FROM reports
  WHERE status IN ('out_of_order', 'under_maintenance')
  ORDER BY created_at DESC
");
$stmt->execute();
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Technician Reports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: Arial, sans-serif; background:#0b1220; color:#e7eefc; padding: 28px; }
    .wrap { max-width: 1100px; margin:0 auto; background:#111b2e; border:1px solid #24304a; border-radius: 14px; padding: 18px; }
    h1 { margin: 0 0 10px; }
    .bar { display:flex; justify-content: space-between; align-items:center; gap: 12px; flex-wrap: wrap; }
    .pill { display:inline-block; padding:6px 10px; border-radius: 999px; background:#0c1426; border:1px solid #2b3a5a; color:#a8b6d8; }
    .ok { margin-top: 10px; padding: 10px 12px; background:#0f2a1b; border:1px solid #1f6b3b; color:#bff3d0; border-radius: 10px; }
    .err { margin-top: 10px; padding: 10px 12px; background:#2a1111; border:1px solid #6b1f1f; color:#f3bfbf; border-radius: 10px; }
    table { width:100%; border-collapse: collapse; margin-top: 14px; overflow:hidden; border-radius: 12px; }
    th, td { padding: 10px; border-bottom: 1px solid #24304a; vertical-align: top; }
    th { background:#0c1426; text-align:left; color:#cfe0ff; }
    tr:hover td { background:#0f1a30; }
    .btn { padding: 7px 10px; border-radius: 10px; border:0; cursor:pointer; font-weight:800; }
    .accept { background:#4f7cff; color:#fff; }
    .finish { background:#20c997; color:#072015; }
    .disabled { opacity: .5; cursor:not-allowed; }
    a { color:#8fb1ff; }
    .small { color:#a8b6d8; font-size: 13px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="bar">
      <div>
        <h1>Technician Report Queue</h1>
        <div class="small">Technician ID: <span class="pill"><?= e((string)$techId) ?></span></div>
        <div class="small">Reporter page: <a href="/swap/Main_Report.php">/swap/Main_Report.php</a></div>
      </div>
      <div class="pill">A03 Injection + A09 Logging Enabled</div>
    </div>

    <?php if ($msg): ?><div class="ok"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>Issue ID</th>
          <th>Part Number</th>
          <th>Issue</th>
          <th>Severity</th>
          <th>Urgency</th>
          <th>Status</th>
          <th>Created</th>
          <th>Performed By (tech_id)</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9">No active reports.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $canAccept = ($r["status"] === "out_of_order");
            $canFinish = ($r["status"] === "under_maintenance" && (int)($r["technician_id"] ?? 0) === $techId);
          ?>
          <tr>
            <td><?= e((string)$r["issue_id"]) ?></td>
            <td><?= e((string)$r["part_number"]) ?></td>
            <td><?= e((string)$r["issue"]) ?></td>
            <td><?= e((string)$r["severity"]) ?></td>
            <td><?= e((string)$r["urgency"]) ?></td>
            <td><?= e((string)$r["status"]) ?></td>
            <td><?= e((string)$r["created_at"]) ?></td>
            <td><?= e((string)($r["technician_id"] ?? "")) ?></td>
            <td>
              <form method="post" style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="hidden" name="issue_id" value="<?= e((string)$r["issue_id"]) ?>" />

                <button class="btn accept <?= $canAccept ? "" : "disabled" ?>"
                        type="submit" name="action" value="accept" <?= $canAccept ? "" : "disabled" ?>>
                  Accept
                </button>

                <button class="btn finish <?= $canFinish ? "" : "disabled" ?>"
                        type="submit" name="action" value="finish" <?= $canFinish ? "" : "disabled" ?>>
                  Finish
                </button>
              </form>
              <div class="small">
                <?php if ($r["status"] === "under_maintenance" && (int)($r["technician_id"] ?? 0) !== $techId): ?>
                  Assigned to another technician.
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
