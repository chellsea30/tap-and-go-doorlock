<?php
require_once __DIR__ . '/../helpers/functions.php';

/**
 * Check if user is authenticated
 * 
 * @return bool True if authenticated
 */
function isAuthenticated(): bool {
    if (!isset($_SESSION[SESSION_ADMIN_ID]) || !isSessionValid()) {
        return false;
    }
    return true;
}

/**
 * Require authentication - redirect to login if not authenticated
 * 
 * @return void
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        header('Location: ' . SITE_URL . 'frontend/pages/login.php');
        exit();
    }
}

/**
 * Require specific role - deny access if role doesn't match
 * 
 * @param string $role Required role (e.g., 'administrator', 'staff')
 * @return void
 * @throws Exception If user doesn't have required role
 */
function requireRole(string $role): void {
    requireAuth();
    if ($_SESSION[SESSION_ROLE] !== $role && $_SESSION[SESSION_ROLE] !== ROLE_ADMIN) {
        header('HTTP/1.0 403 Forbidden');
        die('Access Denied: Insufficient permissions.');
    }
}

/**
 * Check if user has permission for a specific action
 * 
 * @param string $requiredRole The permission/role to check
 * @return bool True if user has permission
 */
function hasPermission(string $requiredRole): bool {
    if (!isAuthenticated()) {
        return false;
    }
    
    $userRole = $_SESSION[SESSION_ROLE];
    $allowedRoles = ['administrator', 'manager', 'staff'];
    
    // Administrator has all permissions
    if ($userRole === ROLE_ADMIN) {
        return true;
    }
    
    // Permission mapping
    $permissions = [
        'view_dashboard' => ['administrator', 'manager', 'staff'],
        'manage_residents' => ['administrator', 'manager'],
        'manage_cards' => ['administrator', 'manager'],
        'manage_visitors' => ['administrator', 'manager'],
        'view_logs' => ['administrator', 'manager', 'staff'],
        'view_reports' => ['administrator', 'manager'],
        'manage_settings' => ['administrator'],
        'manage_alerts' => ['administrator', 'manager']
    ];
    
    if (isset($permissions[$requiredRole])) {
        return in_array($userRole, $permissions[$requiredRole]);
    }
    
    return false;
}

/**
 * Get current authenticated user info
 * 
 * @return array<string, mixed>|null User data or null if not authenticated
 */
function getCurrentUser(): ?array {
    if (!isAuthenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION[SESSION_ADMIN_ID],
        'username' => $_SESSION[SESSION_USERNAME],
        'full_name' => $_SESSION[SESSION_FULL_NAME],
        'role' => $_SESSION[SESSION_ROLE]
    ];
}

// Auto-require authentication for protected pages
if (strpos($_SERVER['PHP_SELF'], '/frontend/pages/') !== false) {
    // Skip login page
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        requireAuth();
    }
}
?>