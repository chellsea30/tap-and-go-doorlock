<?php
session_start();
require_once '../backend/config/config.php';
require_once '../backend/helpers/functions.php';

// Check if user is logged in (any type)
if (isset($_SESSION['user_type']) && isset($_SESSION['user_id']) && isSessionValid()) {
    // Redirect based on user type
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: pages/dashboard.php');
    } elseif ($_SESSION['user_type'] === 'staff') {
        header('Location: pages/staff/dashboard.php');
    } elseif ($_SESSION['user_type'] === 'student') {
        header('Location: pages/student/dashboard.php');
    } else {
        // Fallback
        header('Location: pages/login.php');
    }
    exit();
} else {
    // Not logged in, go to login
    header('Location: pages/dashboard.php');
    exit();
}
?>
