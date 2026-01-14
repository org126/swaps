<?php
declare(strict_types=1);

require_once __DIR__ . '/../components/auth.php';
require_once __DIR__ . '/../config/db.php';

require_login();
require_role(['admin', 'technician', 'equipment_user']);

header('Content-Type: application/json; charset=utf-8');

$q      = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$sql = "
  SELECT
    m.machine_id,
    m.machine_name,
    m.part_name,
    m.status,
    m.updated_at
  FROM machines m
  WHERE 1=1
";

$params = [];

if ($q !== '') {
    // Search by machine_name OR part_name (prepared statement)
    $sql .= " AND (m.machine_name LIKE :q OR m.part_name LIKE :q) ";
    $params[':q'] = '%' . $q . '%';
}

if ($status !== '' && in_array($status, ['Pending', 'In Progress', 'Completed', 'Under Maintenance', 'Unknown'], true)) {
    $sql .= " AND m.status = :status ";
    $params[':status'] = $status;
}

$sql .= " ORDER BY m.updated_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode([
    'ok' => true,
    'data' => $stmt->fetchAll(),
], JSON_UNESCAPED_UNICODE);
