<?php
declare(strict_types=1);

/**
 * session_check.php
 * 
 * Initializes secure session management with:
 * - Secure cookie parameters (httponly, secure, samesite)
 * - Session timeout protection
 * - Session ID validation
 * 
 * Include this file at the top of pages that require session authentication.
 */

// Start session with secure settings (matches config.php session configuration)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Validate session is active and not expired
 * Returns false if session is invalid or timed out
 */
function validateSession(): bool {
    $timeout = (int)($_ENV['SESSION_TIMEOUT'] ?? 3600); // Default 1 hour
    
    // Check if user is authenticated
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            session_destroy();
            return false;
        }
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Verify user has required role
 * Returns true if user has the specified role
 */
function hasRole(string $requiredRole): bool {
    return !empty($_SESSION['role']) && $_SESSION['role'] === $requiredRole;
}

/**
 * Verify user has any of the required roles
 * Returns true if user has one of the specified roles
 */
function hasAnyRole(array $requiredRoles): bool {
    return !empty($_SESSION['role']) && in_array($_SESSION['role'], $requiredRoles, true);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin(): void {
    if (!validateSession()) {
        header('Location: /swaps_project/loginpage.php');
        exit('Redirecting to login...');
    }
}

/**
 * Redirect to login if user doesn't have required role
 */
function requireRole(string $requiredRole): void {
    if (!validateSession() || !hasRole($requiredRole)) {
        http_response_code(403);
        exit('Access Denied');
    }
}

/**
 * Redirect to login if user doesn't have any of the required roles
 */
function requireAnyRole(array $requiredRoles): void {
    if (!validateSession() || !hasAnyRole($requiredRoles)) {
        http_response_code(403);
        exit('Access Denied');
    }
}

// Ensure session is validated on page load
requireLogin();
