<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../components/auth.php';

require_login();
require_role(['admin', 'technician', 'equipment_user']); // stakeholder viewing roles

header('Content-Type: application/json; charset=utf-8');

// detect optional columns safely
function has_column(PDO $pdo, string $table, string $column): bool {
  $sql = "SELECT COUNT(*) AS c
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :t
            AND COLUMN_NAME = :c";
  $st = $pdo->prepare($sql);
  $st->execute([':t' => $table, ':c' => $column]);
  return ((int)$st->fetch()['c']) > 0;
}

$hasStatus = has_column($pdo, 'machines', 'status');
$hasUpdatedAt = has_column($pdo, 'machines', 'updated_at');

$q = trim((string)($_GET['q'] ?? ''));

$select = [
  "m.id",
  "m.machine_number",
  "m.part_number",
  "m.next_maintenance_date",
  "m.notes",
  "m.created_at"
];

if ($hasStatus) {
  $select[] = "m.status";
} else {
  $select[] = "'Unknown' AS status";
}

if ($hasUpdatedAt) {
  $select[] = "m.updated_at";
} else {
  $select[] = "m.created_at AS updated_at";
}

$sql = "SELECT " . implode(", ", $select) . " FROM machines m WHERE 1=1";
$params = [];

if ($q !== '') {
  $sql .= " AND (m.machine_number LIKE :q OR m.part_number LIKE :q)";
  $params[':q'] = '%' . $q . '%';
}

$sql .= " ORDER BY m.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
