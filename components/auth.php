<?php
declare(strict_types=1);

/**
 * Auth + Access Control Helpers
 * Member 3 (Sagana): Protect stakeholder dashboard with server-side RBAC.
 * Also enforce HTTPS (best effort; your XAMPP SSL setup comes later).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Basic output escaping to prevent XSS */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Best-effort HTTPS enforcement (works when HTTPS is configured) */
function require_https(): void {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    if (!$isHttps) {
        // Avoid redirect loops in dev if you're not ready; comment this out if needed temporarily.
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: https://{$host}{$uri}", true, 302);
        exit;
    }
}

function require_login(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header('Location: /login.php'); // adjust to your actual login path
        exit;
    }
}

/**
 * Allow ONLY listed roles.
 * Your SQL screenshot shows roles like: admin, technician, equipment_user.
 * Stakeholders (instructors/lab managers) can be represented as equipment_user.
 */
function require_role(array $allowedRoles): void {
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        header('Location: /stakeholder/forbidden.php');
        exit;
    }
}
