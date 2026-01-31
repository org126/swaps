<?php
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/config.php';

// Check user role for conditional display
$isAdminOrTech = !empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'technician'], true);

// Force HTTPS in production
if (defined('REQUIRE_HTTPS') && REQUIRE_HTTPS && empty($_SERVER['HTTPS']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Validate input parameters
$part_number = isset($_GET['part_number']) ? trim((string)$_GET['part_number']) : '';
$machine_number = isset($_GET['machine_number']) ? trim((string)$_GET['machine_number']) : '';
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], defined('MAX_REPORT_LIMIT') ? MAX_REPORT_LIMIT : 20) : 20;

$machine = null;
$machineParts = [];
$reports = [];
$error = null;

if ($part_number !== '' || $machine_number !== '') {
    try {
        $conn = new mysqli(DB_HOST . ':' . DB_PORT, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log('Database connection error: ' . $conn->connect_error);
            $error = 'Unable to connect to database';
        } else {
            if ($part_number !== '') {
                $stmt = $conn->prepare('SELECT id, part_number, machine_number, state, next_maintenance_date, notes, created_at FROM machines WHERE part_number = ? LIMIT 1');
                $stmt->bind_param('s', $part_number);
            } else {
                $stmt = $conn->prepare('SELECT id, part_number, machine_number, state, next_maintenance_date, notes, created_at FROM machines WHERE machine_number = ? LIMIT 1');
                $stmt->bind_param('s', $machine_number);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $machine = $result->fetch_assoc();
            $stmt->close();

            if ($machine) {
                $machineNumber = $machine['machine_number'];

                $stmtParts = $conn->prepare('SELECT id, part_number, machine_number, state, next_maintenance_date, notes, created_at FROM machines WHERE machine_number = ? ORDER BY part_number ASC');
                $stmtParts->bind_param('s', $machineNumber);
                $stmtParts->execute();
                $machineParts = $stmtParts->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmtParts->close();

                $stmt2 = $conn->prepare('
                    SELECT r.issue_id, r.part_number, r.issue, r.severity, r.urgency, r.created_at, u.username
                    FROM reports r
                    JOIN machines m ON r.part_number = m.part_number
                    LEFT JOIN users u ON r.performed_by = u.user_id
                    WHERE m.machine_number = ?
                    ORDER BY r.created_at DESC
                    LIMIT ?
                ');
                $stmt2->bind_param('si', $machineNumber, $limit);
                $stmt2->execute();
                $reports = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt2->close();
            } else {
                $error = 'Machine not found';
            }
            $conn->close();
        }
    } catch (Throwable $e) {
        error_log('Machine page exception: ' . $e->getMessage());
        $error = 'An error occurred while retrieving machine information';
    }
} else {
    $error = 'Part number or machine number not provided';
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $machine ? h($machine['machine_number']) : 'Machine Info' ?></title>
    <link rel="stylesheet" href="/swaps_project/styles.css">
</head>
<body>
    <div class="container">
        <a href="/swaps_project/search.php" class="btn-back">← Back to Search</a>

        <?php if ($error): ?>
            <div class="header">
                <h1>Machine Information</h1>
            </div>
            <div class="content">
                <div class="error">
                    <h3>⚠️ Error</h3>
                    <p><?= h($error) ?></p>
                </div>

                <div class="usage-example">
                    <strong>Usage:</strong><br><br>
                    Display machine information:<br>
                    <code>/swaps_project/machine_page.php?part_number=PN-1001</code>
                </div>
            </div>
        <?php else: ?>
            <div class="header">
                <h1>Machine Information</h1>
                <p>Details and status for <?= h($machine['machine_number']) ?></p>
            </div>

            <div class="content">
                <div class="machine-card">
                    <div class="machine-title"><?= h($machine['machine_number']) ?></div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Machine Number</div>
                            <div class="info-value"><?= h($machine['machine_number']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Parts</div>
                            <div class="info-value"><?= h((string)count($machineParts)) ?></div>
                        </div>
                    </div>
                </div>

                <div class="reports-section">
                    <div class="reports-title">Machine Parts</div>
                    <?php if (!$machineParts): ?>
                        <div class="no-reports">No parts found for this machine</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Part Number</th>
                                    <th>State</th>
                                    <th>Next Maintenance</th>
                                    <th>Created</th>
                                    <?php if ($isAdminOrTech): ?>
                                        <th>Notes</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machineParts as $p): ?>
                                    <tr>
                                        <td><?= h((string)$p['part_number']) ?></td>
                                        <td><span class="status-badge"><?= h((string)$p['state']) ?></span></td>
                                        <td><?= h((string)$p['next_maintenance_date']) ?></td>
                                        <td><?= h((string)$p['created_at']) ?></td>
                                        <?php if ($isAdminOrTech): ?>
                                            <td><?= nl2br(h((string)($p['notes'] ?? ''))) ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if ($isAdminOrTech): ?>
                    <div class="reports-section">
                        <div class="reports-title">Related Reports (<?= count($reports) ?>)</div>
                        <?php if (!$reports): ?>
                            <div class="no-reports">No reports found for this machine</div>
                        <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                                <div class="report-item">
                                    <div class="report-date"><?= h(date('F d, Y \a\t H:i', strtotime($report['created_at']))) ?></div>
                                    <div class="report-issue"><?= h($report['issue']) ?></div>
                                    <div class="small">
                                        Reported by: <?= h($report['username'] ?? 'Unknown') ?>
                                    </div>
                                    <div class="small">
                                        Severity: <?= h($report['severity']) ?> / 10 | Urgency: <?= h($report['urgency']) ?> / 10
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="footer small">
                <p>Machine Number: <?= h($machine['machine_number']) ?> | Last Updated: <?= h(date('F d, Y H:i')) ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
