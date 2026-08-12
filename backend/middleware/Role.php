<?php
/**
 * Role-based Access Control Middleware
 */

// Check if user is logged in
function isLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && isSessionValid();
}

// Check if user has specific role
function hasRole(string $role): bool {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $role;
}

// Check if user has at least one of the roles
function hasAnyRole(array $roles): bool {
    if (!isLoggedIn()) {
        return false;
    }
    return in_array($_SESSION['role'], $roles);
}

// Require specific role - redirect if not
function requireRole(string $role): void {
    if (!hasRole($role)) {
        header('Location: ' . SITE_URL . 'frontend/pages/dashboard.php');
        exit();
    }
}

// Require any of the roles
function requireAnyRole(array $roles): void {
    if (!hasAnyRole($roles)) {
        header('Location: ' . SITE_URL . 'frontend/pages/dashboard.php');
        exit();
    }
}

// Get current user role
function getCurrentRole(): string {
    return $_SESSION['role'] ?? 'guest';
}

// Check if user is admin
function isAdmin(): bool {
    return hasRole('administrator');
}

// Check if user is staff
function isStaff(): bool {
    return hasRole('staff');
}

// Check if user is student
function isStudent(): bool {
    return hasRole('student');
}