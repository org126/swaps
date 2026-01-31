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

// Require technician or admin role
require_once __DIR__ . '/session_check.php';
requireAnyRole(['technician', 'admin']);

//////////////////////////////
// DATABASE: Uses main database (database.sql schema)
//////////////////////////////
require_once __DIR__ . '/config.php';

// Database schema: machines table
$MACHINE_PARTS_TABLE = "machines";
$PART_COL = "part_number";
$STATUS_COL = "state";

$STATUS_OUT_OF_ORDER = "out_of_order";
$STATUS_UNDER_MAINTENANCE = "in_maintenance";
$STATUS_AVAILABLE = "ready";

function pdo_conn(): PDO {
  return getPDOConnection();
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

function formatSingaporeTime(?string $datetime): string {
  if (!$datetime) return "";
  try {
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Singapore'));
    return $dt->format('Y-m-d H:i:s');
  } catch (Throwable $e) {
    return $datetime;
  }
}

function log_event(PDO $pdo, string $eventType, ?int $reportId, string $actorRole, ?int $actorId, ?string $details = null): void {
  try {
    $stmt = $pdo->prepare("
      INSERT INTO logs (table_changed, column_changed, old_info, new_info, user_id)
      VALUES (?, ?, ?, ?, ?)
    ");
    $tableChanged = 'reports';
    $columnChanged = $eventType;
    $oldInfo = null;
    $newInfo = $details ?? "report_id={$reportId}, action={$eventType}";
    $stmt->execute([$tableChanged, $columnChanged, $oldInfo, $newInfo, $actorId]);
  } catch (Throwable $e) {
    error_log("Log event error: " . $e->getMessage());
  }
}

// Get technician ID from session (logged-in user)
$techId = $_SESSION['user_id'] ?? 0;
if ($techId <= 0) {
  http_response_code(403);
  echo "User not authenticated. Please log in first.";
  exit;
}

try {
  $pdo = pdo_conn();
} catch (Throwable $e) {
  http_response_code(500);
  error_log('Technician DB connection error: ' . $e->getMessage());
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

      // Get report row (needed for part_number)
      $stmt = $pdo->prepare("SELECT issue_id, part_number FROM reports WHERE issue_id = ?");
      $stmt->execute([$issueId]);
      $r = $stmt->fetch();

      if (!$r) {
        throw new Exception("Report not found.");
      }

      $partNumber = $r["part_number"];

      if ($action === "accept") {
        $stmt1 = $pdo->prepare("
          UPDATE reports
          SET performed_by=?
          WHERE issue_id=?
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
        // Finish only if assigned to this technician; remove report from queue
        $stmt1 = $pdo->prepare("
          DELETE FROM reports
          WHERE issue_id=? AND performed_by=?
        ");
        $stmt1->execute([$issueId, $techId]);

        if ($stmt1->rowCount() === 0) {
          throw new Exception("Cannot finish: report not assigned to you.");
        }

        $stmt2 = $pdo->prepare("
          UPDATE {$MACHINE_PARTS_TABLE}
          SET {$STATUS_COL} = ?
          WHERE {$PART_COL} = ?
        ");
        $stmt2->execute([$STATUS_AVAILABLE, $partNumber]);

        log_event($pdo, "REPORT_FINISHED", $issueId, "technician", $techId, "Finished report");
        $msg = "Finished Issue ID {$issueId}. (It will disappear from the list.)";
      } else {
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

// Fetch visible queue (all reports)
$stmt = $pdo->prepare("
  SELECT issue_id, part_number, issue, severity, urgency, created_at, performed_by
  FROM reports
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
  <link rel="stylesheet" href="/swaps_project/styles.css">
</head>
<body>
  <div class="wrap">
    <a href="/swaps_project/search.php" class="btn-back">← Back to Search</a>
    <div class="bar">
      <div>
        <h1>Technician Report Queue</h1>
        <div class="small">Technician ID: <span class="pill"><?= e((string)$techId) ?></span></div>
      </div>
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
          <th>Created</th>
          <th>Assigned To (tech_id)</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8">No reports found.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $canAccept = empty($r["performed_by"]);
            $canFinish = (int)($r["performed_by"] ?? 0) === $techId;
          ?>
          <tr>
            <td><?= e((string)$r["issue_id"]) ?></td>
            <td><?= e((string)$r["part_number"]) ?></td>
            <td><?= e((string)$r["issue"]) ?></td>
            <td><?= e((string)$r["severity"]) ?></td>
            <td><?= e((string)$r["urgency"]) ?></td>
            <td><?= e(formatSingaporeTime($r["created_at"])) ?></td>
            <td><?= e((string)($r["performed_by"] ?? "")) ?></td>
            <td>
              <form method="post" class="inline-form-flex">
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
                <?php if ((int)($r["performed_by"] ?? 0) > 0 && (int)($r["performed_by"] ?? 0) !== $techId): ?>
                  Assigned to tech ID <?= e((string)$r["performed_by"]) ?>.
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

