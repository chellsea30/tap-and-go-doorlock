<?php
session_start();
require_once '../backend/config/config.php';
require_once '../backend/helpers/functions.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: pages/login.php');
    exit();
}

// Redirect to dashboard
header('Location: pages/dashboard.php');
exit();
?>