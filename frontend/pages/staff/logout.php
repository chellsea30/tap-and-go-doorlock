<?php
/**
 * Tap-and-Go Doorlock - Staff Logout
 */

// Start session
session_start();

// ============================================================
// CLEAR ALL SESSION VARIABLES
// ============================================================
$_SESSION = array();

// ============================================================
// DELETE SESSION COOKIE
// ============================================================
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// ============================================================
// DESTROY SESSION
// ============================================================
session_destroy();

// ============================================================
// REDIRECT TO LOGIN PAGE
// ============================================================
header('Location: ../login.php');
exit();
?>