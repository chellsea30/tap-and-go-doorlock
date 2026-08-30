<?php
/**
 * Tap-and-Go Doorlock - Settings
 * COMPLETE WORKING VERSION - With Audit Trail Pagination
 * WITH SAVED LOGINS - PURE DARK MODE
 * WITH WORKING BACKUP & RESTORE
 * WITH WORKING EXPORT DATA - NO HTML, NO BOM - FIXED
 * FIXED LAYOUT: Navbar, Sidebar, Footer aligned with dashboard
 */

// START: Clear any output buffering
if (ob_get_length()) {
    ob_end_clean();
}
ob_start();

// Start session
session_start();

// Load config and functions FIRST
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}
// Include header
include '../includes/header.php'; 
$conn = getDBConnection();

// ============================================================
// CREATE TABLES IF NOT EXISTS
// ============================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS audit_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(50),
        user_agent VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
        INDEX idx_admin_id (admin_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
    CREATE TABLE IF NOT EXISTS user_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        setting_key VARCHAR(50) NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
        UNIQUE KEY unique_setting (admin_id, setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ============================================================
// CREATE SAVED LOGINS TABLE IF NOT EXISTS
// ============================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS saved_logins (
        saved_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        device_name VARCHAR(100) NOT NULL,
        ip_address VARCHAR(50),
        user_agent VARCHAR(255),
        login_time DATETIME NOT NULL,
        last_activity DATETIME NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
        INDEX idx_admin_id (admin_id),
        INDEX idx_last_activity (last_activity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ============================================================
// CREATE BACKUP FOLDER
// ============================================================
$backup_dir = '../../backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// ============================================================
// GET ACTIVE TAB
// ============================================================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
$allowed_tabs = [
    'profile', 'change-password', 'twofa', 'saved-login', 'dark-mode', 
    'security', 'notifications', 'backup', 'system', 'language', 
    'activity-log', 'export-data', 'audit-trail'
];
if (!in_array($active_tab, $allowed_tabs)) {
    $active_tab = 'profile';
}

// ============================================================
// LOAD USER SETTINGS
// ============================================================
$userSettings = [
    'dark_mode' => getUserSetting((int)$_SESSION['admin_id'], 'dark_mode', 'false'),
    'twofa_enabled' => getUserSetting((int)$_SESSION['admin_id'], 'twofa_enabled', 'false'),
    'notifications' => getUserSetting((int)$_SESSION['admin_id'], 'notifications', 'true'),
    'auto_backup' => getUserSetting((int)$_SESSION['admin_id'], 'auto_backup', 'false'),
    'language' => getUserSetting((int)$_SESSION['admin_id'], 'language', 'english'),
    'session_timeout' => getUserSetting((int)$_SESSION['admin_id'], 'session_timeout', '30'),
    'max_login_attempts' => getUserSetting((int)$_SESSION['admin_id'], 'max_login_attempts', '5'),
    'login_notifications' => getUserSetting((int)$_SESSION['admin_id'], 'login_notifications', 'true'),
    'email_notifications' => getUserSetting((int)$_SESSION['admin_id'], 'email_notifications', 'true'),
    'sms_notifications' => getUserSetting((int)$_SESSION['admin_id'], 'sms_notifications', 'false'),
    'alert_notifications' => getUserSetting((int)$_SESSION['admin_id'], 'alert_notifications', 'true'),
];

// ============================================================
// HANDLE SAVED LOGINS
// ============================================================

// Save current login session
function saveCurrentLogin($admin_id, $conn) {
    $device_name = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Clean device name
    if (strpos($device_name, 'Chrome') !== false) {
        $device_name = 'Chrome Browser';
    } elseif (strpos($device_name, 'Firefox') !== false) {
        $device_name = 'Firefox Browser';
    } elseif (strpos($device_name, 'Safari') !== false && strpos($device_name, 'Chrome') === false) {
        $device_name = 'Safari Browser';
    } elseif (strpos($device_name, 'Edge') !== false) {
        $device_name = 'Edge Browser';
    } elseif (strpos($device_name, 'Mobile') !== false) {
        $device_name = 'Mobile Device';
    } else {
        $device_name = substr($device_name, 0, 30);
    }
    
    // Check if already exists (same IP and user agent)
    $check = $conn->prepare("SELECT saved_id FROM saved_logins WHERE admin_id = ? AND ip_address = ? AND user_agent = ? AND is_active = 1");
    $check->bind_param("iss", $admin_id, $ip_address, $user_agent);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing
        $stmt = $conn->prepare("UPDATE saved_logins SET last_activity = NOW() WHERE admin_id = ? AND ip_address = ? AND user_agent = ?");
        $stmt->bind_param("iss", $admin_id, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new
        $stmt = $conn->prepare("
            INSERT INTO saved_logins (admin_id, device_name, ip_address, user_agent, login_time, last_activity, is_active)
            VALUES (?, ?, ?, ?, NOW(), NOW(), 1)
        ");
        $stmt->bind_param("isss", $admin_id, $device_name, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
    $check->close();
}

// Save current login on page load
if (isset($_SESSION['admin_id'])) {
    saveCurrentLogin($_SESSION['admin_id'], $conn);
}

// Handle logout from saved session
if (isset($_GET['logout_session']) && is_numeric($_GET['logout_session'])) {
    $saved_id = (int)$_GET['logout_session'];
    $stmt = $conn->prepare("UPDATE saved_logins SET is_active = 0 WHERE saved_id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $saved_id, $_SESSION['admin_id']);
    if ($stmt->execute()) {
        $success = "✅ Session logged out successfully!";
        logAudit((int)$_SESSION['admin_id'], 'Logout Session', "Logged out saved session ID: $saved_id");
    } else {
        $error = "Failed to logout session.";
    }
    $stmt->close();
}

// ============================================================
// SAVED LOGINS PAGINATION SETTINGS
// ============================================================
$savedPerPage = isset($_GET['saved_per_page']) ? (int)$_GET['saved_per_page'] : 10;
$savedPage = isset($_GET['saved_page']) ? (int)$_GET['saved_page'] : 1;
$perPageOptions = [10, 25, 50, 100];
if (!in_array($savedPerPage, $perPageOptions)) {
    $savedPerPage = 10;
}

// Get saved logins count
$savedTotalResult = $conn->prepare("SELECT COUNT(*) as total FROM saved_logins WHERE admin_id = ?");
$savedTotalResult->bind_param("i", $_SESSION['admin_id']);
$savedTotalResult->execute();
$savedCountResult = $savedTotalResult->get_result();
$savedTotal = 0;
if ($savedCountResult && $row = $savedCountResult->fetch_assoc()) {
    $savedTotal = (int)$row['total'];
}
$savedTotalResult->close();

$savedTotalPages = ceil($savedTotal / $savedPerPage);
if ($savedTotalPages < 1) $savedTotalPages = 1;
if ($savedPage > $savedTotalPages) $savedPage = $savedTotalPages;
if ($savedPage < 1) $savedPage = 1;
$savedOffset = ($savedPage - 1) * $savedPerPage;

// Get saved logins with pagination
$savedLogins = [];
$stmt = $conn->prepare("
    SELECT * FROM saved_logins 
    WHERE admin_id = ? 
    ORDER BY last_activity DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $_SESSION['admin_id'], $savedPerPage, $savedOffset);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $savedLogins[] = $row;
}
$stmt->close();

// ============================================================
// HANDLE BACKUP & RESTORE
// ============================================================

// Create backup
if (isset($_GET['create_backup'])) {
    $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $output = "-- Tap-and-Go Doorlock Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Tables: " . implode(', ', $tables) . "\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // Create table structure
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $row[1] . ";\n\n";
        
        // Get data
        $dataResult = $conn->query("SELECT * FROM `$table`");
        if ($dataResult && $dataResult->num_rows > 0) {
            $fields = [];
            $fieldResult = $conn->query("SHOW COLUMNS FROM `$table`");
            while ($field = $fieldResult->fetch_row()) {
                $fields[] = $field[0];
            }
            
            while ($rowData = $dataResult->fetch_assoc()) {
                $values = [];
                foreach ($fields as $field) {
                    $value = $rowData[$field];
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                $output .= "INSERT INTO `$table` (`" . implode("`, `", $fields) . "`) VALUES (" . implode(", ", $values) . ");\n";
            }
            $output .= "\n";
        }
    }
    
    $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Save file
    if (file_put_contents($backup_file, $output)) {
        logAudit((int)$_SESSION['admin_id'], 'Create Backup', "Created backup: " . basename($backup_file));
        $success = "✅ Backup created successfully!";
        
        // Download immediately
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup_file));
        readfile($backup_file);
        exit();
    } else {
        $error = "Failed to create backup. Please check folder permissions.";
    }
}

// Restore from backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['backup_file']['tmp_name'];
        $file_name = $_FILES['backup_file']['name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($file_extension !== 'sql') {
            $error = "Invalid file type. Please upload a .sql file.";
        } elseif ($_FILES['backup_file']['size'] > 10485760) { // 10MB max
            $error = "File too large. Maximum size is 10MB.";
        } else {
            $sql = file_get_contents($file_tmp);
            
            // Split SQL into individual queries
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            
            $conn->query("SET FOREIGN_KEY_CHECKS=0");
            
            $success_count = 0;
            $error_count = 0;
            
            foreach ($queries as $query) {
                if (!empty($query)) {
                    if ($conn->query($query)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        error_log("Backup restore error: " . $conn->error);
                    }
                }
            }
            
            $conn->query("SET FOREIGN_KEY_CHECKS=1");
            
            if ($error_count == 0) {
                logAudit((int)$_SESSION['admin_id'], 'Restore Backup', "Restored backup: $file_name ($success_count queries)");
                $success = "✅ Backup restored successfully! $success_count queries executed.";
            } else {
                $success = "⚠️ Backup restored with $error_count errors. $success_count queries executed successfully.";
            }
            
            echo '<meta http-equiv="refresh" content="2;url=settings.php?tab=backup">';
        }
    } else {
        $error = "Please select a backup file to restore.";
    }
}

// Delete backup file
if (isset($_GET['delete_backup']) && !empty($_GET['delete_backup'])) {
    $file_name = basename($_GET['delete_backup']);
    $file_path = $backup_dir . $file_name;
    
    if (file_exists($file_path) && is_file($file_path)) {
        if (unlink($file_path)) {
            $success = "✅ Backup file deleted successfully!";
            logAudit((int)$_SESSION['admin_id'], 'Delete Backup', "Deleted backup: $file_name");
        } else {
            $error = "Failed to delete backup file.";
        }
    } else {
        $error = "Backup file not found.";
    }
}

// ============================================================
// HANDLE FORM SUBMISSIONS
// ============================================================

// 1. Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("UPDATE admin_users SET full_name = ?, email = ? WHERE admin_id = ?");
        $stmt->bind_param("ssi", $full_name, $email, $_SESSION['admin_id']);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            logAudit((int)$_SESSION['admin_id'], 'Profile Update', "Updated profile: $full_name");
            $success = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// 2. Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        $stmt = $conn->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (password_verify($current_password, $row['password_hash'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE admin_id = ?");
            $stmt->bind_param("si", $new_hash, $_SESSION['admin_id']);
            
            if ($stmt->execute()) {
                logAudit((int)$_SESSION['admin_id'], 'Password Change', 'Password changed successfully');
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

// 3. Update Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings = $_POST['settings'] ?? [];
    foreach ($settings as $key => $value) {
        setUserSetting((int)$_SESSION['admin_id'], $key, $value);
    }
    logAudit((int)$_SESSION['admin_id'], 'Settings Update', 'Updated system settings');
    $success = 'Settings updated successfully!';
}

// ============================================================
// EXPORT DATA - FIXED: Added $escape parameter for PHP 8.1+
// ============================================================
if (isset($_GET['export']) && $_GET['export'] == 'true') {
    $export_type = isset($_GET['type']) ? $_GET['type'] : 'residents';
    
    // CLEAN: Remove any output buffering and clear previous output
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $export_type . '_export_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    header('Pragma: public');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // NO BOM - removed to avoid ï»¿ characters
    
    if ($export_type == 'residents') {
        // Headers
        fputcsv($output, ['ID', 'Name', 'Student ID', 'Course', 'Year Level', 'Room', 'Contact', 'Status', 'Created At'], ',', '"', '\\');
        
        // Data
        $result = $conn->query("
            SELECT u.user_id, u.full_name, u.student_id, rp.course, rp.year_level, 
                   u.room_number, u.contact_number, u.status, u.created_at
            FROM users u
            LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
            WHERE u.status != 'deleted'
            ORDER BY u.full_name
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['user_id'],
                    $row['full_name'],
                    $row['student_id'],
                    $row['course'] ?? 'N/A',
                    $row['year_level'] ?? 'N/A',
                    $row['room_number'] ?? 'N/A',
                    $row['contact_number'] ?? 'N/A',
                    $row['status'],
                    $row['created_at']
                ], ',', '"', '\\');
            }
        }
        
    } elseif ($export_type == 'access_logs') {
        // Headers
        fputcsv($output, ['Log ID', 'Card UID', 'User', 'Access Type', 'Status', 'Timestamp', 'Power Source'], ',', '"', '\\');
        
        // Data - Get access logs with user info
        $result = $conn->query("
            SELECT al.log_id, al.card_uid, 
                   COALESCE(u.full_name, c.visitor_name, 'Unknown') as user_name,
                   al.access_type, al.access_status, al.timestamp, al.power_source
            FROM access_logs al
            LEFT JOIN rfid_cards c ON al.card_uid = c.card_uid
            LEFT JOIN users u ON c.user_id = u.user_id
            ORDER BY al.timestamp DESC
            LIMIT 1000
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['log_id'],
                    $row['card_uid'] ?? 'N/A',
                    $row['user_name'] ?? 'Unknown',
                    $row['access_type'] ?? 'N/A',
                    $row['access_status'] ?? 'N/A',
                    $row['timestamp'],
                    $row['power_source'] ?? 'N/A'
                ], ',', '"', '\\');
            }
        }
        
    } elseif ($export_type == 'visitors') {
        // Headers
        fputcsv($output, ['ID', 'Visitor Name', 'Resident Visited', 'Purpose', 'Entry Time', 'Exit Time', 'Status'], ',', '"', '\\');
        
        // Data
        $result = $conn->query("
            SELECT v.visitor_log_id, v.visitor_name, u.full_name as resident, 
                   v.purpose_of_visit, v.entry_timestamp, v.exit_timestamp, v.access_status
            FROM visitor_logs v
            LEFT JOIN users u ON v.resident_visited = u.user_id
            ORDER BY v.created_at DESC
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['visitor_log_id'],
                    $row['visitor_name'],
                    $row['resident'] ?? 'N/A',
                    $row['purpose_of_visit'] ?? 'N/A',
                    $row['entry_timestamp'] ?? 'N/A',
                    $row['exit_timestamp'] ?? 'N/A',
                    $row['access_status'] ?? 'N/A'
                ], ',', '"', '\\');
            }
        }
        
    } elseif ($export_type == 'audit_logs') {
        // Headers
        fputcsv($output, ['Log ID', 'Admin', 'Action', 'Details', 'IP Address', 'Date/Time'], ',', '"', '\\');
        
        // Data
        $result = $conn->query("
            SELECT al.log_id, au.full_name, al.action, al.details, al.ip_address, al.created_at
            FROM audit_logs al
            LEFT JOIN admin_users au ON al.admin_id = au.admin_id
            ORDER BY al.created_at DESC
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['log_id'],
                    $row['full_name'] ?? 'Unknown',
                    $row['action'],
                    $row['details'] ?? '',
                    $row['ip_address'] ?? '',
                    $row['created_at']
                ], ',', '"', '\\');
            }
        }
        
    } elseif ($export_type == 'alerts') {
        // Headers
        fputcsv($output, ['Alert ID', 'Card UID', 'User', 'Alert Type', 'Status', 'Reason', 'Timestamp'], ',', '"', '\\');
        
        // Data
        $result = $conn->query("
            SELECT alog.alert_id, alog.card_uid, 
                   COALESCE(u.full_name, c.visitor_name, 'Unknown') as user_name,
                   alog.alert_type, alog.delivery_status, alog.reason, alog.timestamp
            FROM alert_logs alog
            LEFT JOIN rfid_cards c ON alog.card_uid = c.card_uid
            LEFT JOIN users u ON c.user_id = u.user_id
            ORDER BY alog.timestamp DESC
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['alert_id'],
                    $row['card_uid'] ?? 'N/A',
                    $row['user_name'] ?? 'Unknown',
                    $row['alert_type'] ?? 'N/A',
                    $row['delivery_status'] ?? 'N/A',
                    $row['reason'] ?? '',
                    $row['timestamp']
                ], ',', '"', '\\');
            }
        }
        
    } elseif ($export_type == 'rfid_cards') {
        // Headers
        fputcsv($output, ['Card UID', 'User', 'Card Type', 'Status', 'Issued Date', 'Expiry Date'], ',', '"', '\\');
        
        // Data
        $result = $conn->query("
            SELECT c.card_uid, 
                   COALESCE(u.full_name, c.visitor_name, 'Unassigned') as user_name,
                   c.card_type, c.status, c.issued_date, c.expiry_date
            FROM rfid_cards c
            LEFT JOIN users u ON c.user_id = u.user_id
            ORDER BY c.created_at DESC
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['card_uid'],
                    $row['user_name'] ?? 'Unassigned',
                    $row['card_type'] ?? 'N/A',
                    $row['status'] ?? 'N/A',
                    $row['issued_date'] ?? 'N/A',
                    $row['expiry_date'] ?? 'N/A'
                ], ',', '"', '\\');
            }
        }
    }
    
    fclose($output);
    logAudit((int)$_SESSION['admin_id'], 'Export Data', "Exported $export_type as CSV");
    exit();
}

// 5. Clear Audit Logs
if (isset($_GET['clear_audit'])) {
    $conn->query("DELETE FROM audit_logs");
    logAudit((int)$_SESSION['admin_id'], 'Clear Audit Logs', 'Cleared all audit logs');
    $success = 'Audit logs cleared successfully!';
    header('Location: settings.php?tab=audit-trail&msg=cleared');
    exit();
}

// ============================================================
// GET USER DATA
// ============================================================
$userData = [];
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// Get activity logs summary
$activityStats = [];
$result = $conn->query("
    SELECT DATE(timestamp) as date, 
           COUNT(*) as total,
           SUM(CASE WHEN access_status = 'granted' THEN 1 ELSE 0 END) as granted,
           SUM(CASE WHEN access_status = 'denied' THEN 1 ELSE 0 END) as denied
    FROM access_logs 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(timestamp)
    ORDER BY date DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $activityStats[] = $row;
    }
}

// ============================================================
// GET BACKUP FILES
// ============================================================
$backupFiles = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $filepath = $backup_dir . $file;
            $backupFiles[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'modified' => date('Y-m-d H:i:s', filemtime($filepath))
            ];
        }
    }
    // Sort by modified date (newest first)
    usort($backupFiles, function($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
    });
}

// ============================================================
// AUDIT TRAIL PAGINATION SETTINGS
// ============================================================
$auditPerPage = isset($_GET['audit_per_page']) ? (int)$_GET['audit_per_page'] : 10;
$auditPage = isset($_GET['audit_page']) ? (int)$_GET['audit_page'] : 1;
if (!in_array($auditPerPage, $perPageOptions)) {
    $auditPerPage = 10;
}

// Get audit trail count
$auditTotalResult = $conn->query("SELECT COUNT(*) as total FROM audit_logs");
$auditTotal = 0;
if ($auditTotalResult && $row = $auditTotalResult->fetch_assoc()) {
    $auditTotal = (int)$row['total'];
}
$auditTotalPages = ceil($auditTotal / $auditPerPage);
if ($auditTotalPages < 1) $auditTotalPages = 1;
if ($auditPage > $auditTotalPages) $auditPage = $auditTotalPages;
if ($auditPage < 1) $auditPage = 1;
$auditOffset = ($auditPage - 1) * $auditPerPage;

// Get audit logs with pagination
$auditLogs = [];
$stmt = $conn->prepare("
    SELECT al.*, au.full_name 
    FROM audit_logs al
    LEFT JOIN admin_users au ON al.admin_id = au.admin_id
    ORDER BY al.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $auditPerPage, $auditOffset);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $auditLogs[] = $row;
    }
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME - MATCHES DASHBOARD
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
            padding-top: 70px !important;
        }
        
        .container-fluid {
            padding-top: 10px !important;
        }
        
        main {
            padding-top: 10px !important;
            margin-top: 0 !important;
        }
        
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1050 !important;
            height: 70px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            padding-top: 80px !important;
            min-height: calc(100vh - 70px) !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar-footer { border-top-color: #1a2a4a !important; }
        .sidebar-footer .text-muted { color: #606070 !important; }
        
        .settings-sidebar {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 15px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            width: 260px;
            flex-shrink: 0;
        }
        .settings-sidebar .nav-link {
            padding: 10px 18px;
            color: #9090a0 !important;
            border-radius: 0;
            border-left: 3px solid transparent;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .settings-sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .settings-sidebar .nav-link.active {
            background: rgba(102,126,234,0.15) !important;
            color: #8b5cf6 !important;
            border-left-color: #667eea !important;
            font-weight: 600;
        }
        .settings-sidebar .nav-link i { width: 20px; text-align: center; margin-right: 10px; color: #606070 !important; }
        .settings-sidebar .nav-link.active i { color: #8b5cf6 !important; }
        .settings-sidebar .nav-link .badge {
            float: right;
            margin-top: 2px;
            font-size: 10px;
        }
        
        .settings-content {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            flex: 1;
            min-height: 500px;
        }
        .settings-content h4 {
            color: #93c5fd !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }
        .settings-container {
            display: flex;
            gap: 25px;
            align-items: flex-start;
        }
        
        .form-control, .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: #e0e0e0 !important;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
            background: #1a1a2e !important;
            color: #e0e0e0 !important;
        }
        .form-control::placeholder { color: #606070 !important; }
        .form-control:disabled { opacity: 0.5; cursor: not-allowed; }
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #b0b0c0 !important;
        }
        .required { color: #f87171 !important; }
        .text-muted { color: #808090 !important; }
        .text-success { color: #34d399 !important; }
        .text-danger { color: #f87171 !important; }
        .text-warning { color: #fbbf24 !important; }
        
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            padding: 10px 35px;
            border-radius: 12px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26,58,106,0.4);
            color: white !important;
        }
        .btn-primary {
            background: #1a3a6a !important;
            border-color: #1a3a6a !important;
            color: white !important;
        }
        .btn-primary:hover {
            background: #2a5a9a !important;
            border-color: #2a5a9a !important;
            color: white !important;
        }
        .btn-danger {
            background: #7a2a2a !important;
            border-color: #7a2a2a !important;
            color: #f87171 !important;
        }
        .btn-danger:hover {
            background: #8a3a3a !important;
            border-color: #8a3a3a !important;
        }
        .btn-success {
            background: #065f46 !important;
            border-color: #065f46 !important;
            color: #34d399 !important;
        }
        .btn-success:hover {
            background: #0a7a5a !important;
            border-color: #0a7a5a !important;
            color: #6ee7b7 !important;
        }
        .btn-sm { font-size: 12px !important; }
        .btn-remove-backup {
            background: #7a2a2a !important;
            color: #f87171 !important;
            border: none !important;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11px;
            transition: all 0.3s ease;
        }
        .btn-remove-backup:hover {
            background: #8a3a3a !important;
            color: #fca5a5 !important;
        }
        .btn-download-backup {
            background: #1a3a6a !important;
            color: #93c5fd !important;
            border: none !important;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11px;
            transition: all 0.3s ease;
        }
        .btn-download-backup:hover {
            background: #2a5a9a !important;
            color: #bfdbfe !important;
        }
        
        .export-card {
            border: 2px dashed #2a2a4a !important;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .export-card:hover {
            border-color: #667eea !important;
            background: rgba(255,255,255,0.03) !important;
        }
        .export-card i { font-size: 40px; color: #93c5fd; margin-bottom: 15px; }
        .export-card h6 { color: #e0e0e0 !important; }
        .export-card .text-muted { color: #808090 !important; }
        .export-card .btn-primary {
            background: #1a3a6a !important;
            border-color: #1a3a6a !important;
            color: white !important;
        }
        .export-card .btn-primary:hover {
            background: #2a5a9a !important;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 28px;
            display: inline-block;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #2a2a4a !important;
            transition: .4s;
            border-radius: 34px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background: #808090 !important;
            transition: .4s;
            border-radius: 50%;
        }
        .toggle-switch input:checked + .toggle-slider {
            background: #1a3a6a !important;
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
            background: white !important;
        }
        
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #1a2a4a !important;
        }
        .setting-item:last-child { border-bottom: none !important; }
        .setting-item .setting-info h6 {
            margin: 0;
            font-weight: 600;
            color: #e0e0e0 !important;
        }
        .setting-item .setting-info p {
            margin: 0;
            font-size: 13px;
            color: #808090 !important;
        }
        
        .badge-status { padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-enabled { background: #065f46 !important; color: #34d399 !important; }
        .badge-disabled { background: #2a2a3a !important; color: #808090 !important; }
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .badge-info { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-secondary { background: #2a2a3a !important; color: #808090 !important; }
        .badge-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        
        .saved-login-item {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 12px !important;
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        .saved-login-item:hover {
            border-color: #667eea !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
        }
        .saved-login-item .device-icon {
            font-size: 28px;
            color: #93c5fd;
            margin-right: 15px;
            width: 40px;
            text-align: center;
        }
        .saved-login-item .device-name {
            font-weight: 600;
            color: #e0e0e0;
        }
        .saved-login-item .device-detail {
            font-size: 12px;
            color: #808090;
        }
        .saved-login-item .device-time {
            font-size: 11px;
            color: #606070;
        }
        .saved-login-item .status-badge {
            font-size: 10px;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .saved-login-item .status-active {
            background: #065f46 !important;
            color: #34d399 !important;
        }
        .saved-login-item .status-inactive {
            background: #2a2a3a !important;
            color: #808090 !important;
        }
        .saved-login-item .btn-logout-session {
            background: #7a2a2a !important;
            color: #f87171 !important;
            border: none !important;
            border-radius: 8px;
            padding: 4px 12px;
            font-size: 11px;
            transition: all 0.3s ease;
        }
        .saved-login-item .btn-logout-session:hover {
            background: #8a3a3a !important;
            color: #fca5a5 !important;
        }
        
        .backup-card {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 12px !important;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .backup-card:hover {
            border-color: #667eea !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3) !important;
        }
        .backup-card .backup-name {
            font-weight: 600;
            color: #e0e0e0;
        }
        .backup-card .backup-size {
            font-size: 12px;
            color: #808090;
        }
        .backup-card .backup-date {
            font-size: 11px;
            color: #606070;
        }
        .backup-file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: #111827 !important;
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px solid #1a2a4a;
            transition: all 0.3s ease;
        }
        .backup-file-item:hover {
            border-color: #2a5a9a;
        }
        
        .table {
            color: #e0e0e0 !important;
        }
        .table th {
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
        }
        .table td {
            border-bottom: 1px solid #1a2a4a !important;
        }
        .table-hover tbody tr:hover {
            background: rgba(255,255,255,0.02) !important;
        }
        .table .text-success { color: #34d399 !important; }
        .table .text-danger { color: #f87171 !important; }
        .table-sm td, .table-sm th { padding: 6px 10px; }
        
        .alert-success {
            background: #065f46 !important;
            border-color: #065f46 !important;
            color: #6ee7b7 !important;
        }
        .alert-danger {
            background: #7a2a2a !important;
            border-color: #7a2a2a !important;
            color: #f87171 !important;
        }
        .alert .btn-close { filter: invert(1) !important; }
        
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: 1px solid #2a2a4a !important;
            color: #b0b0c0 !important;
            background: transparent !important;
            font-weight: 500;
            padding: 6px 12px;
            transition: all 0.3s ease;
            font-size: 13px;
        }
        .pagination .page-link:hover {
            background: #2a2a4a !important;
            border-color: #667eea !important;
            color: #e0e0e0 !important;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border-color: #1a3a6a !important;
            color: white !important;
        }
        .pagination .page-item.disabled .page-link {
            color: #4a4a5a !important;
        }
        .pagination .page-item .page-link i { font-size: 12px; }
        
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        .border { border-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .form-text { color: #808090 !important; }
        .bg-primary { background: #1a3a6a !important; }
        .bg-secondary { background: #1a2a4a !important; }
        .bg-success { background: #065f46 !important; color: #34d399 !important; }
        .bg-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .bg-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        .bg-info { background: #1a3a6a !important; color: #93c5fd !important; }
        
        .form-select-sm {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 13px;
        }
        .form-select-sm:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        
        .border.rounded {
            border-color: #2a2a4a !important;
        }
        
        footer {
            color: #808090 !important;
            border-top: 1px solid #1a2a4a !important;
            padding: 15px 0 !important;
            margin-top: 30px !important;
        }
        footer .text-muted {
            color: #606070 !important;
        }
        
        @media (max-width: 992px) {
            .settings-container { flex-direction: column; }
            .settings-sidebar { width: 100%; }
        }
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .navbar { height: 60px !important; }
            .sidebar {
                padding-top: 70px !important;
                position: fixed;
                top: 60px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
                min-height: calc(100vh - 60px) !important;
            }
            .sidebar.show { left: 0; }
            .settings-content { padding: 20px; }
            .setting-item { flex-direction: column; align-items: flex-start; gap: 10px; }
            .saved-login-item {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
            }
            .saved-login-item .text-end {
                text-align: left !important;
                width: 100%;
            }
            .backup-file-item {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 8px;
            }
            .backup-file-item .text-end {
                text-align: left !important;
                width: 100%;
            }
        }
    </style>
    <script>
        (function() {
            const isDarkFromStorage = localStorage.getItem('darkMode') === 'true';
            <?php 
            $darkModeFromDb = getUserSetting((int)$_SESSION['admin_id'], 'dark_mode', 'false');
            ?>
            const isDarkFromDb = <?php echo ($darkModeFromDb == 'true') ? 'true' : 'false'; ?>;
            if (isDarkFromStorage || isDarkFromDb) {
                document.documentElement.classList.add('dark-mode');
                document.body.classList.add('dark-mode');
            }
            window.__dbDarkMode = isDarkFromDb;
        })();
    </script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-cog me-2" style="color: #1a3a6a;"></i>Settings</h1>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['msg']) && $_GET['msg'] == 'cleared'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> Audit logs cleared successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="settings-container">
                    
                    <!-- SIDEBAR -->
                    <div class="settings-sidebar">
                        <ul class="nav flex-column">
                            <li><a class="nav-link <?php echo $active_tab == 'change-password' ? 'active' : ''; ?>" href="?tab=change-password"><i class="fas fa-key"></i> Change Password</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'saved-login' ? 'active' : ''; ?>" href="?tab=saved-login"><i class="fas fa-history"></i> Saved Logins <span class="badge bg-primary"><?php echo $savedTotal; ?></span></a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'security' ? 'active' : ''; ?>" href="?tab=security"><i class="fas fa-lock"></i> Security</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'notifications' ? 'active' : ''; ?>" href="?tab=notifications"><i class="fas fa-bell"></i> Notifications <span class="badge <?php echo ($userSettings['notifications'] == 'true') ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ($userSettings['notifications'] == 'true') ? 'ON' : 'OFF'; ?></span></a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'activity-log' ? 'active' : ''; ?>" href="?tab=activity-log"><i class="fas fa-chart-line"></i> Activity Log</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'export-data' ? 'active' : ''; ?>" href="?tab=export-data"><i class="fas fa-download"></i> Export Data</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'audit-trail' ? 'active' : ''; ?>" href="?tab=audit-trail"><i class="fas fa-clipboard-list"></i> Audit Trail <span class="badge bg-primary"><?php 
                                $countResult = $conn->query("SELECT COUNT(*) as count FROM audit_logs");
                                $countRow = $countResult->fetch_assoc();
                                echo $countRow['count'] ?? 0;
                            ?></span></a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'backup' ? 'active' : ''; ?>" href="?tab=backup"><i class="fas fa-database"></i> Backup & Restore</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'system' ? 'active' : ''; ?>" href="?tab=system"><i class="fas fa-server"></i> System Info</a></li>
                        </ul>
                    </div>

                    <!-- CONTENT -->
                    <div class="settings-content">
                        
                        <!-- ========== CHANGE PASSWORD ========== -->
                        <?php if ($active_tab == 'change-password'): ?>
                        <h4><i class="fas fa-key me-2"></i>Change Password</h4>
                        <p class="text-muted">Update your password to keep your account secure.</p>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-12"><label class="form-label">Current Password <span class="required">*</span></label><input type="password" class="form-control" name="current_password" placeholder="Enter current password" required></div>
                                <div class="col-md-6"><label class="form-label">New Password <span class="required">*</span></label><input type="password" class="form-control" name="new_password" placeholder="Enter new password" required><small class="text-muted">Minimum 8 characters with uppercase, lowercase, and number</small></div>
                                <div class="col-md-6"><label class="form-label">Confirm Password <span class="required">*</span></label><input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password" required></div>
                            </div>
                            <div class="mt-4"><button type="submit" name="change_password" class="btn btn-submit"><i class="fas fa-key"></i> Change Password</button></div>
                        </form>
                        <?php endif; ?>

                        <!-- ========== SAVED LOGINS WITH SHOW ENTRIES ========== -->
                        <?php if ($active_tab == 'saved-login'): ?>
                        <h4><i class="fas fa-history me-2"></i>Saved Logins</h4>
                        <p class="text-muted">Manage your active login sessions across different devices.</p>
                        
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <span class="text-muted small">Total: <?php echo $savedTotal; ?> session(s)</span>
                        </div>

                        <div class="mt-3">
                            <?php if (empty($savedLogins)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                    <p>No saved login sessions found.</p>
                                    <p class="small">Your current session will appear here after login.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($savedLogins as $login): 
                                    $isActive = $login['is_active'] == 1;
                                    $isCurrent = ($login['ip_address'] == $_SERVER['REMOTE_ADDR'] && $login['user_agent'] == $_SERVER['HTTP_USER_AGENT']);
                                    
                                    // Determine icon
                                    $icon = 'fa-desktop';
                                    $deviceType = 'Computer';
                                    if (strpos($login['device_name'], 'Mobile') !== false) {
                                        $icon = 'fa-mobile-alt';
                                        $deviceType = 'Mobile';
                                    } elseif (strpos($login['device_name'], 'Chrome') !== false) {
                                        $icon = 'fa-chrome';
                                    } elseif (strpos($login['device_name'], 'Firefox') !== false) {
                                        $icon = 'fa-firefox';
                                    } elseif (strpos($login['device_name'], 'Safari') !== false) {
                                        $icon = 'fa-safari';
                                    } elseif (strpos($login['device_name'], 'Edge') !== false) {
                                        $icon = 'fa-edge';
                                    }
                                ?>
                                    <div class="saved-login-item">
                                        <div class="d-flex align-items-center">
                                            <div class="device-icon">
                                                <i class="fab <?php echo $icon; ?>"></i>
                                            </div>
                                            <div>
                                                <div class="device-name">
                                                    <?php echo htmlspecialchars($login['device_name']); ?>
                                                    <?php if ($isCurrent): ?>
                                                        <span class="badge bg-primary ms-2" style="font-size:9px;">Current Session</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="device-detail">
                                                    <i class="fas fa-map-pin me-1"></i>
                                                    <?php echo htmlspecialchars($login['ip_address']); ?>
                                                    <span class="mx-1">|</span>
                                                    <i class="fas fa-desktop me-1"></i>
                                                    <?php echo $deviceType; ?>
                                                </div>
                                                <div class="device-time">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Last active: <?php echo date('M d, Y h:i A', strtotime($login['last_activity'])); ?>
                                                    <span class="mx-1">|</span>
                                                    <i class="fas fa-sign-in-alt me-1"></i>
                                                    Login: <?php echo date('M d, Y h:i A', strtotime($login['login_time'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
                                                <i class="fas <?php echo $isActive ? 'fa-circle' : 'fa-circle'; ?> me-1"></i>
                                                <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                            </span>
                                            <?php if ($isActive && !$isCurrent): ?>
                                                <a href="?tab=saved-login&logout_session=<?php echo $login['saved_id']; ?>" 
                                                   class="btn-logout-session"
                                                   onclick="return confirm('Logout this session? This will force logout from that device.')">
                                                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($isCurrent): ?>
                                                <span class="badge bg-success" style="font-size:9px;">
                                                    <i class="fas fa-check-circle me-1"></i> This device
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <!-- Saved Logins Pagination with Show Entries -->
                                <?php if ($savedTotal > $savedPerPage): ?>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3 pt-3 border-top">
                                    <div class="text-muted small">
                                        <i class="fas fa-database me-1"></i>
                                        Showing <?php echo $savedOffset + 1; ?> to <?php echo min($savedOffset + $savedPerPage, $savedTotal); ?> of <?php echo $savedTotal; ?> entries
                                    </div>
                                    
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <!-- Per Page Selector -->
                                        <div class="d-flex align-items-center gap-2">
                                            <label class="text-muted small mb-0">Show:</label>
                                            <select class="form-select form-select-sm" style="width: auto;" 
                                                    onchange="changeSavedPerPage(this.value)">
                                                <?php foreach ($perPageOptions as $option): ?>
                                                    <option value="<?php echo $option; ?>" <?php echo $option == $savedPerPage ? 'selected' : ''; ?>>
                                                        <?php echo $option; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <nav aria-label="Saved logins pagination">
                                            <ul class="pagination pagination-sm mb-0">
                                                <li class="page-item <?php echo $savedPage <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?tab=saved-login&saved_page=1&saved_per_page=<?php echo $savedPerPage; ?>">
                                                        <i class="fas fa-angle-double-left"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo $savedPage <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?tab=saved-login&saved_page=<?php echo $savedPage - 1; ?>&saved_per_page=<?php echo $savedPerPage; ?>">
                                                        <i class="fas fa-angle-left"></i>
                                                    </a>
                                                </li>
                                                
                                                <?php
                                                $startSavedPage = max(1, $savedPage - 2);
                                                $endSavedPage = min($savedTotalPages, $savedPage + 2);
                                                
                                                if ($startSavedPage > 1) {
                                                    echo '<li class="page-item"><a class="page-link" href="?tab=saved-login&saved_page=1&saved_per_page=' . $savedPerPage . '">1</a></li>';
                                                    if ($startSavedPage > 2) {
                                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                    }
                                                }
                                                
                                                for ($i = $startSavedPage; $i <= $endSavedPage; $i++) {
                                                    $active = $i == $savedPage ? 'active' : '';
                                                    echo '<li class="page-item ' . $active . '">
                                                            <a class="page-link" href="?tab=saved-login&saved_page=' . $i . '&saved_per_page=' . $savedPerPage . '">' . $i . '</a>
                                                          </li>';
                                                }
                                                
                                                if ($endSavedPage < $savedTotalPages) {
                                                    if ($endSavedPage < $savedTotalPages - 1) {
                                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                    }
                                                    echo '<li class="page-item"><a class="page-link" href="?tab=saved-login&saved_page=' . $savedTotalPages . '&saved_per_page=' . $savedPerPage . '">' . $savedTotalPages . '</a></li>';
                                                }
                                                ?>
                                                
                                                <li class="page-item <?php echo $savedPage >= $savedTotalPages ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?tab=saved-login&saved_page=<?php echo $savedPage + 1; ?>&saved_per_page=<?php echo $savedPerPage; ?>">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo $savedPage >= $savedTotalPages ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?tab=saved-login&saved_page=<?php echo $savedTotalPages; ?>&saved_per_page=<?php echo $savedPerPage; ?>">
                                                        <i class="fas fa-angle-double-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-3 text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Showing <?php echo count($savedLogins); ?> session<?php echo count($savedLogins) > 1 ? 's' : ''; ?> on this page.
                                    <?php 
                                        $activeCount = 0;
                                        foreach ($savedLogins as $login) {
                                            if ($login['is_active'] == 1) $activeCount++;
                                        }
                                    ?>
                                    <span class="ms-2"><?php echo $activeCount; ?> active, <?php echo count($savedLogins) - $activeCount; ?> inactive on this page.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <script>
                            function changeSavedPerPage(value) {
                                const urlParams = new URLSearchParams(window.location.search);
                                urlParams.set('tab', 'saved-login');
                                urlParams.set('saved_per_page', value);
                                urlParams.set('saved_page', 1);
                                window.location.href = '?' + urlParams.toString();
                            }
                        </script>
                        <?php endif; ?>

                        <!-- ========== SECURITY ========== -->
                        <?php if ($active_tab == 'security'): ?>
                        <h4><i class="fas fa-lock me-2"></i>Security Settings</h4>
                        <p class="text-muted">Manage your account security settings.</p>
                        <form method="POST">
                            <div class="setting-item"><div class="setting-info"><h6>Login Notifications</h6><p>Get email notifications for new logins</p></div><div><label class="toggle-switch"><input type="checkbox" name="settings[login_notifications]" <?php echo ($userSettings['login_notifications'] == 'true') ? 'checked' : ''; ?>><span class="toggle-slider"></span></label></div></div>
                            <div class="setting-item"><div class="setting-info"><h6>Session Timeout</h6><p>Automatically log out after inactivity</p></div><div><select class="form-select form-select-sm" name="settings[session_timeout]" style="width:150px;"><option value="15" <?php echo ($userSettings['session_timeout'] == '15') ? 'selected' : ''; ?>>15 minutes</option><option value="30" <?php echo ($userSettings['session_timeout'] == '30') ? 'selected' : ''; ?>>30 minutes</option><option value="60" <?php echo ($userSettings['session_timeout'] == '60') ? 'selected' : ''; ?>>1 hour</option><option value="120" <?php echo ($userSettings['session_timeout'] == '120') ? 'selected' : ''; ?>>2 hours</option></select></div></div>
                            <div class="setting-item"><div class="setting-info"><h6>Max Login Attempts</h6><p>Lock account after failed attempts</p></div><div><select class="form-select form-select-sm" name="settings[max_login_attempts]" style="width:150px;"><option value="3" <?php echo ($userSettings['max_login_attempts'] == '3') ? 'selected' : ''; ?>>3 attempts</option><option value="5" <?php echo ($userSettings['max_login_attempts'] == '5') ? 'selected' : ''; ?>>5 attempts</option><option value="10" <?php echo ($userSettings['max_login_attempts'] == '10') ? 'selected' : ''; ?>>10 attempts</option></select></div></div>
                            <div class="mt-4"><button type="submit" name="update_settings" class="btn btn-submit"><i class="fas fa-save"></i> Save Security Settings</button></div>
                        </form>
                        <?php endif; ?>

                        <!-- ========== NOTIFICATIONS ========== -->
                        <?php if ($active_tab == 'notifications'): ?>
                        <h4><i class="fas fa-bell me-2"></i>Notifications</h4>
                        <p class="text-muted">Manage your notification preferences.</p>
                        <form method="POST">
                            <div class="setting-item"><div class="setting-info"><h6><i class="fas fa-bell text-primary me-2"></i>Push Notifications</h6><p>Receive browser notifications for alerts</p></div><div><label class="toggle-switch"><input type="checkbox" name="settings[notifications]" <?php echo ($userSettings['notifications'] == 'true') ? 'checked' : ''; ?>><span class="toggle-slider"></span></label></div></div>
                            <div class="setting-item"><div class="setting-info"><h6><i class="fas fa-envelope text-success me-2"></i>Email Notifications</h6><p>Receive email notifications for important events</p></div><div><label class="toggle-switch"><input type="checkbox" name="settings[email_notifications]" <?php echo ($userSettings['email_notifications'] == 'true') ? 'checked' : ''; ?>><span class="toggle-slider"></span></label></div></div>
                            <div class="setting-item"><div class="setting-info"><h6><i class="fas fa-sms text-warning me-2"></i>SMS Notifications</h6><p>Receive SMS alerts for security breaches</p></div><div><label class="toggle-switch"><input type="checkbox" name="settings[sms_notifications]" <?php echo ($userSettings['sms_notifications'] == 'true') ? 'checked' : ''; ?>><span class="toggle-slider"></span></label></div></div>
                            <div class="setting-item"><div class="setting-info"><h6><i class="fas fa-bell-slash text-danger me-2"></i>Alert Notifications</h6><p>Receive dashboard alerts for unauthorized access</p></div><div><label class="toggle-switch"><input type="checkbox" name="settings[alert_notifications]" <?php echo ($userSettings['alert_notifications'] == 'true') ? 'checked' : ''; ?>><span class="toggle-slider"></span></label></div></div>
                            <div class="mt-4"><button type="submit" name="update_settings" class="btn btn-submit"><i class="fas fa-save"></i> Save Preferences</button></div>
                        </form>
                        <?php endif; ?>

                        <!-- ========== ACTIVITY LOG ========== -->
                        <?php if ($active_tab == 'activity-log'): ?>
                        <h4><i class="fas fa-chart-line me-2"></i>Activity Log</h4>
                        <p class="text-muted">View system activity and access statistics.</p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Date</th><th>Total</th><th>Granted</th><th>Denied</th><th>Success Rate</th></tr></thead>
                                <tbody>
                                    <?php foreach ($activityStats as $stat): ?>
                                    <tr><td><?php echo date('M d, Y', strtotime($stat['date'])); ?></td><td><?php echo $stat['total']; ?></td><td class="text-success"><?php echo $stat['granted']; ?></td><td class="text-danger"><?php echo $stat['denied']; ?></td><td><span class="badge <?php echo ($stat['total'] > 0 && ($stat['granted'] / $stat['total']) >= 0.9) ? 'bg-success' : (($stat['total'] > 0 && ($stat['granted'] / $stat['total']) >= 0.7) ? 'bg-warning' : 'bg-danger'); ?>"><?php echo $stat['total'] > 0 ? round(($stat['granted'] / $stat['total']) * 100) : 0; ?>%</span></td></tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($activityStats)): ?><tr><td colspan="5" class="text-center text-muted py-3">No activity data available</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <!-- ========== EXPORT DATA - FIXED ========== -->
                        <?php if ($active_tab == 'export-data'): ?>
                        <h4><i class="fas fa-download me-2"></i>Export Data</h4>
                        <p class="text-muted">Export your data in CSV format for backup or analysis.</p>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="export-card">
                                    <i class="fas fa-users"></i>
                                    <h6>Residents Data</h6>
                                    <p class="text-muted small">Export all resident records</p>
                                    <a href="?export=true&type=residents" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download me-1"></i> Export CSV
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="export-card">
                                    <i class="fas fa-clock"></i>
                                    <h6>Access Logs</h6>
                                    <p class="text-muted small">Export access history (last 1000)</p>
                                    <a href="?export=true&type=access_logs" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download me-1"></i> Export CSV
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="export-card">
                                    <i class="fas fa-user-plus"></i>
                                    <h6>Visitors Data</h6>
                                    <p class="text-muted small">Export all visitor records</p>
                                    <a href="?export=true&type=visitors" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download me-1"></i> Export CSV
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="export-card">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h6>Audit Logs</h6>
                                    <p class="text-muted small">Export all audit trail records</p>
                                    <a href="?export=true&type=audit_logs" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download me-1"></i> Export CSV
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="export-card">
                                    <i class="fas fa-bell"></i>
                                    <h6>Alerts Data</h6>
                                    <p class="text-muted small">Export all alert records</p>
                                    <a href="?export=true&type=alerts" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download me-1"></i> Export CSV
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="export-card">
                                    <i class="fas fa-id-card"></i>
                                    <h6>RFID Cards</h6>
                                    <p class="text-muted small">Export all RFID card records</p>
                                    <a href="?export=true&type=rfid_cards" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download me-1"></i> Export CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3 text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            All exports are in CSV format compatible with Excel and Google Sheets.
                            <span class="mx-1">|</span>
                            <i class="fas fa-check-circle text-success me-1"></i>
                            No BOM - clean UTF-8 encoding.
                        </div>
                        <?php endif; ?>

                        <!-- ========== AUDIT TRAIL ========== -->
                        <?php if ($active_tab == 'audit-trail'): ?>
                        <h4><i class="fas fa-clipboard-list me-2"></i>Audit Trail</h4>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <p class="text-muted mb-0">Complete audit log of all system actions.</p>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small">Total: <?php echo $auditTotal; ?> logs</span>
                                <?php if ($auditTotal > 0): ?>
                                <a href="?tab=audit-trail&clear_audit=true" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to clear all audit logs? This action cannot be undone.')">
                                    <i class="fas fa-trash me-1"></i> Clear All
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date/Time</th>
                                        <th>Admin</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($auditLogs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                                No audit logs available
                                            </td>
                                        </tr>
                                    <?php else: 
                                        $counter = $auditOffset + 1;
                                        foreach ($auditLogs as $log): 
                                    ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['full_name'] ?? 'Unknown'); ?></td>
                                            <td><span class="badge bg-info"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                            <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Audit Trail Pagination -->
                        <?php if ($auditTotal > $auditPerPage): ?>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3 pt-3 border-top">
                            <div class="text-muted small">
                                <i class="fas fa-database me-1"></i>
                                Showing <?php echo $auditOffset + 1; ?> to <?php echo min($auditOffset + $auditPerPage, $auditTotal); ?> of <?php echo $auditTotal; ?> entries
                            </div>
                            
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <!-- Per Page Selector -->
                                <div class="d-flex align-items-center gap-2">
                                    <label class="text-muted small mb-0">Show:</label>
                                    <select class="form-select form-select-sm" style="width: auto;" 
                                            onchange="changeAuditPerPage(this.value)">
                                        <?php foreach ($perPageOptions as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $option == $auditPerPage ? 'selected' : ''; ?>>
                                                <?php echo $option; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Pagination -->
                                <nav aria-label="Audit trail pagination">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?php echo $auditPage <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?tab=audit-trail&audit_page=1&audit_per_page=<?php echo $auditPerPage; ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo $auditPage <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?tab=audit-trail&audit_page=<?php echo $auditPage - 1; ?>&audit_per_page=<?php echo $auditPerPage; ?>">
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $startAuditPage = max(1, $auditPage - 2);
                                        $endAuditPage = min($auditTotalPages, $auditPage + 2);
                                        
                                        if ($startAuditPage > 1) {
                                            echo '<li class="page-item"><a class="page-link" href="?tab=audit-trail&audit_page=1&audit_per_page=' . $auditPerPage . '">1</a></li>';
                                            if ($startAuditPage > 2) {
                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                        }
                                        
                                        for ($i = $startAuditPage; $i <= $endAuditPage; $i++) {
                                            $active = $i == $auditPage ? 'active' : '';
                                            echo '<li class="page-item ' . $active . '">
                                                    <a class="page-link" href="?tab=audit-trail&audit_page=' . $i . '&audit_per_page=' . $auditPerPage . '">' . $i . '</a>
                                                  </li>';
                                        }
                                        
                                        if ($endAuditPage < $auditTotalPages) {
                                            if ($endAuditPage < $auditTotalPages - 1) {
                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                            echo '<li class="page-item"><a class="page-link" href="?tab=audit-trail&audit_page=' . $auditTotalPages . '&audit_per_page=' . $auditPerPage . '">' . $auditTotalPages . '</a></li>';
                                        }
                                        ?>
                                        
                                        <li class="page-item <?php echo $auditPage >= $auditTotalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?tab=audit-trail&audit_page=<?php echo $auditPage + 1; ?>&audit_per_page=<?php echo $auditPerPage; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo $auditPage >= $auditTotalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?tab=audit-trail&audit_page=<?php echo $auditTotalPages; ?>&audit_per_page=<?php echo $auditPerPage; ?>">
                                                <i class="fas fa-angle-double-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <?php endif; ?>

                        <script>
                            function changeAuditPerPage(value) {
                                const urlParams = new URLSearchParams(window.location.search);
                                urlParams.set('tab', 'audit-trail');
                                urlParams.set('audit_per_page', value);
                                urlParams.set('audit_page', 1);
                                window.location.href = '?' + urlParams.toString();
                            }
                        </script>
                        <?php endif; ?>

                        <!-- ========== BACKUP & RESTORE ========== -->
                        <?php if ($active_tab == 'backup'): ?>
                        <h4><i class="fas fa-database me-2"></i>Backup & Restore</h4>
                        <p class="text-muted">Manage your data backups and restoration.</p>
                        
                        <div class="backup-card">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 style="color:#e0e0e0;"><i class="fas fa-download text-primary me-2"></i>Create Backup</h6>
                                    <p class="text-muted small mb-0">Download a complete backup of your database in SQL format.</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <a href="?tab=backup&create_backup=1" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download me-1"></i> Download Backup
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="backup-card">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 style="color:#e0e0e0;"><i class="fas fa-upload text-success me-2"></i>Restore Backup</h6>
                                    <p class="text-muted small mb-0">Upload a .sql backup file to restore your database.</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <form method="POST" action="" enctype="multipart/form-data">
                                        <div class="d-flex gap-2 flex-wrap justify-content-end">
                                            <input type="file" class="form-control form-control-sm" name="backup_file" accept=".sql" style="width:auto; display:inline-block; background:#1a1a2e; border-color:#2a2a4a; color:#e0e0e0; font-size:12px; padding:4px 8px;">
                                            <button type="submit" name="restore_backup" class="btn btn-success btn-sm" onclick="return confirm('Restoring backup will overwrite current data. Are you sure?')">
                                                <i class="fas fa-upload me-1"></i> Restore
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h6><i class="fas fa-clock text-warning me-2"></i>Auto Backup</h6>
                                <p>Automatically backup data daily at midnight</p>
                            </div>
                            <div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="autoBackupToggle" <?php echo ($userSettings['auto_backup'] == 'true') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <?php if (!empty($backupFiles)): ?>
                        <div class="mt-4">
                            <h6 style="color:#e0e0e0;"><i class="fas fa-file-archive me-2"></i>Existing Backups</h6>
                            <p class="text-muted small"><?php echo count($backupFiles); ?> backup file(s) found.</p>
                            
                            <?php foreach ($backupFiles as $file): ?>
                                <div class="backup-file-item">
                                    <div>
                                        <div class="backup-name">
                                            <i class="fas fa-file-code me-2" style="color:#93c5fd;"></i>
                                            <?php echo htmlspecialchars($file['name']); ?>
                                        </div>
                                        <div class="backup-size">
                                            <i class="fas fa-weight-hanging me-1"></i>
                                            <?php echo number_format($file['size'] / 1024, 2); ?> KB
                                            <span class="mx-1">|</span>
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            <?php echo $file['modified']; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <a href="<?php echo $backup_dir . $file['name']; ?>" class="btn btn-download-backup btn-sm" download>
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                        <a href="?tab=backup&delete_backup=<?php echo urlencode($file['name']); ?>" 
                                           class="btn btn-remove-backup btn-sm"
                                           onclick="return confirm('Delete this backup file?')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3 text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            Backups are stored in <code>../../backups/</code> folder.
                            <span class="mx-1">|</span>
                            <i class="fas fa-database me-1"></i>
                            <?php echo count($backupFiles); ?> backup(s) available.
                        </div>
                        <?php endif; ?>

                        <!-- ========== SYSTEM INFO ========== -->
                        <?php if ($active_tab == 'system'): ?>
                        <h4><i class="fas fa-server me-2"></i>System Information</h4>
                        <p class="text-muted">View system status and information.</p>
                        <div class="row g-3">
                            <div class="col-md-6"><div class="p-3 border rounded"><h6><i class="fas fa-server text-primary me-2"></i>Server</h6><table class="table table-sm table-borderless"><tr><td class="text-muted">PHP Version</td><td><strong><?php echo phpversion(); ?></strong></td></tr><tr><td class="text-muted">MySQL Version</td><td><strong><?php echo $conn->server_info; ?></strong></td></tr><tr><td class="text-muted">Server Software</td><td><strong><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?></strong></td></tr><tr><td class="text-muted">OS</td><td><strong><?php echo php_uname('s') . ' ' . php_uname('r'); ?></strong></td></tr></table></div></div>
                            <div class="col-md-6"><div class="p-3 border rounded"><h6><i class="fas fa-database text-success me-2"></i>Database</h6><table class="table table-sm table-borderless"><tr><td class="text-muted">Database Name</td><td><strong><?php echo DB_NAME; ?></strong></td></tr><tr><td class="text-muted">Total Tables</td><td><strong><?php echo $conn->query("SHOW TABLES")->num_rows; ?></strong></td></tr><tr><td class="text-muted">Database Size</td><td><strong><?php $result = $conn->query("SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'"); $row = $result->fetch_assoc(); echo $row ? number_format($row['size'] / 1024, 2) . ' KB' : 'N/A'; ?></strong></td></tr></table></div></div>
                            <div class="col-md-12"><div class="p-3 border rounded"><h6><i class="fas fa-cog text-warning me-2"></i>Application</h6><table class="table table-sm table-borderless"><tr><td class="text-muted">Application Name</td><td><strong><?php echo SITE_NAME; ?></strong></td></tr><tr><td class="text-muted">Version</td><td><strong>v1.0</strong></td></tr><tr><td class="text-muted">Environment</td><td><strong><?php echo APP_ENV; ?></strong></td></tr><tr><td class="text-muted">Session Timeout</td><td><strong><?php echo SESSION_TIMEOUT / 60; ?> minutes</strong></td></tr></table></div></div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
                
                <footer class="pt-4 pb-2 text-muted text-center small border-top mt-3">
                    &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System. All rights reserved.
                    <span class="mx-2">|</span>
                    <span id="serverTime">Server Time: <?php echo date('F d, Y h:i A'); ?></span>
                </footer>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const darkToggle = document.getElementById('darkModeToggle');
            if (darkToggle) {
                darkToggle.addEventListener('change', function() {
                    const isDark = this.checked;
                    document.body.classList.toggle('dark-mode', isDark);
                    document.documentElement.classList.toggle('dark-mode', isDark);
                    localStorage.setItem('darkMode', isDark ? 'true' : 'false');
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'update_settings=1&settings[dark_mode]=' + (isDark ? 'true' : 'false')
                    });
                });
            }
            
            function updateServerTime() {
                const now = new Date();
                const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
                const timeStr = now.toLocaleDateString('en-US', options);
                const el = document.getElementById('serverTime');
                if (el) {
                    el.textContent = 'Server Time: ' + timeStr;
                }
            }
            updateServerTime();
            setInterval(updateServerTime, 60000);
        });
        
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
