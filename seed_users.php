<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function add_user(PDO $pdo, string $username, string $password, string $role): void {
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $st = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (:u, :p, :r)");
  $st->execute([':u' => $username, ':p' => $hash, ':r' => $role]);
}

try {
  add_user($pdo, 'stakeholder1', 'Passw0rd!', 'equipment_user');
  add_user($pdo, 'tech1', 'Passw0rd!', 'technician');
  add_user($pdo, 'admin1', 'Passw0rd!', 'admin');
  echo "Seeded users successfully. Delete this file after use.";
} catch (Throwable $e) {
  echo "Seeding failed (maybe users already exist).";
}
