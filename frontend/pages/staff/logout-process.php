<?php
/**
 * Tap-and-Go Doorlock - Staff Logout Process
 * Location: /frontend/pages/staff/logout-process.php
 */

// Start session
session_start();

// Check if logout was confirmed via POST
if (!isset($_POST['confirm_logout'])) {
    // If not confirmed, redirect back to logout page
    header('Location: logout.php');
    exit();
}

// Log the logout activity (optional - if you have logging function)
if (isset($_SESSION['staff_id']) && function_exists('logStaffAudit')) {
    logStaffAudit($_SESSION['staff_id'], 'Logout', 'Staff logged out');
}

// Clear all session variables
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ../login.php');
exit();
?>