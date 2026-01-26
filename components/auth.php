<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  // Secure-ish defaults for local XAMPP
  ini_set('session.use_strict_mode', '1');
  ini_set('session.cookie_httponly', '1');
  session_start();
}

function is_logged_in(): bool {
  return isset($_SESSION['user']);
}

function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}

function require_login(): void {
  if (!is_logged_in()) {
    header('Location: /swap/swaps/public/login.php');
    exit;
  }
}

function require_role(array $allowedRoles): void {
  $user = current_user();
  $role = $user['role'] ?? null;

  if (!$role || !in_array($role, $allowedRoles, true)) {
    header('Location: /swap/swaps/stakeholder/forbidden.php');
    exit;
  }
}

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
