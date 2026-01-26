<?php
// Database connection
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'secure_web';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get machine_number from URL
$machine_number = isset($_GET['machine_number']) ? $_GET['machine_number'] : null;

$machine = null;
$reports = [];
$error = null;

if ($machine_number) {
    // Fetch machine data
    $stmt = $conn->prepare("SELECT * FROM machines WHERE machine_number = ?");
    $stmt->bind_param("s", $machine_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $machine = $result->fetch_assoc();
        
        // Fetch related reports
        $stmt = $conn->prepare("SELECT r.*, u.username FROM reports r LEFT JOIN users u ON r.performed_by = u.user_id WHERE r.part_number = ? ORDER BY r.created_at DESC");
        $stmt->bind_param("s", $machine['part_number']);
        $stmt->execute();
        $reports_result = $stmt->get_result();
        $reports = $reports_result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Machine not found";
    }
} else {
    $error = "Machine number not provided";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $machine ? htmlspecialchars($machine['machine_number']) : 'Machine Info'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #333;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .content {
            background: white;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #c33;
            text-align: center;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-section h2 {
            color: #333;
            font-size: 18px;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .info-value {
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-ready {
            background: #d4edda;
            color: #155724;
        }

        .status-in_use {
            background: #cce5ff;
            color: #004085;
        }

        .status-in_maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .status-out_of_order {
            background: #f8d7da;
            color: #721c24;
        }

        .notes-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
            white-space: pre-wrap;
            line-height: 1.6;
        }

        .reports-container {
            margin-top: 30px;
        }

        .report-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #f39c12;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .report-title {
            color: #333;
            font-weight: 600;
        }

        .report-meta {
            color: #999;
            font-size: 12px;
        }

        .report-issue {
            color: #555;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .report-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-severity {
            background: #ffe5e5;
            color: #c33;
        }

        .badge-urgency {
            background: #fff3cd;
            color: #856404;
        }

        .badge-status {
            background: #cce5ff;
            color: #004085;
        }

        .no-reports {
            color: #999;
            text-align: center;
            padding: 20px;
        }

        .footer {
            background: white;
            padding: 15px 30px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            color: #666;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="header">
                <h1>Machine Information</h1>
            </div>
            <div class="content">
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            </div>
            <div class="footer">
                <p>Usage: machine_page.php?machine_number=YOUR_MACHINE_NUMBER</p>
            </div>
        <?php elseif ($machine): ?>
            <div class="header">
                <h1><?php echo htmlspecialchars($machine['machine_number']); ?></h1>
                <p>Part #: <?php echo htmlspecialchars($machine['part_number']); ?></p>
            </div>

            <div class="content">
                <!-- Basic Information Section -->
                <div class="info-section">
                    <h2>Basic Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Machine Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($machine['machine_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Part Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($machine['part_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Current State</div>
                            <div class="info-value">
                                <span class="status-badge status-<?php echo $machine['state']; ?>">
                                    <?php echo str_replace('_', ' ', ucfirst($machine['state'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Information Section -->
                <div class="info-section">
                    <h2>Maintenance Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Next Maintenance Date</div>
                            <div class="info-value">
                                <?php 
                                if ($machine['next_maintenance_date']) {
                                    echo date('F d, Y', strtotime($machine['next_maintenance_date']));
                                } else {
                                    echo 'Not scheduled';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Created On</div>
                            <div class="info-value">
                                <?php echo date('F d, Y \a\t H:i', strtotime($machine['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                <?php if ($machine['notes']): ?>
                <div class="info-section">
                    <h2>Notes</h2>
                    <div class="notes-box"><?php echo htmlspecialchars($machine['notes']); ?></div>
                </div>
                <?php endif; ?>

                <!-- Related Reports Section -->
                <div class="info-section">
                    <h2>Related Reports (<?php echo count($reports); ?>)</h2>
                    <div class="reports-container">
                        <?php if (empty($reports)): ?>
                            <div class="no-reports">No reports found for this machine</div>
                        <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                            <div class="report-card">
                                <div class="report-header">
                                    <div>
                                        <div class="report-title">Report #<?php echo $report['issue_id']; ?></div>
                                        <div class="report-meta">
                                            Reported by: <?php echo htmlspecialchars($report['username'] ?? 'Unknown'); ?> | 
                                            <?php echo date('F d, Y \a\t H:i', strtotime($report['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="report-issue"><?php echo htmlspecialchars($report['issue']); ?></div>
                                <div class="report-badges">
                                    <?php if ($report['severity']): ?>
                                    <span class="badge badge-severity">Severity: <?php echo $report['severity']; ?>/10</span>
                                    <?php endif; ?>
                                    <?php if ($report['urgency']): ?>
                                    <span class="badge badge-urgency">Urgency: <?php echo $report['urgency']; ?>/10</span>
                                    <?php endif; ?>
                                    <span class="badge badge-status">Status: <?php echo htmlspecialchars($report['availability']); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="footer">
                <p>Machine ID: <?php echo $machine['id']; ?> | Last Updated: <?php echo date('F d, Y H:i'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
