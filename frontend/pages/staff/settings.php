<?php
/**
 * Tap-and-Go Doorlock - Staff Settings
 * VIEW-ONLY WITH FULL SETTINGS OPTIONS
 * USING SHARED SIDEBAR
 */

session_start();

// Load config and functions
require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

// Check if logged in as staff
if (!isset($_SESSION['staff_id']) || !isStaffSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$staffInfo = getStaffById($_SESSION['staff_id']);

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
$allowed_tabs = ['profile', 'dashboard', 'alerts', 'monitoring', 'logs', 'security', 'support'];
if (!in_array($active_tab, $allowed_tabs)) {
    $active_tab = 'profile';
}

// Get staff settings from database
$settings = [];
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM user_settings WHERE admin_id = ?");
$stmt->bind_param("i", $_SESSION['staff_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$stmt->close();

// Default settings
$defaultSettings = [
    'dark_mode' => 'false',
    'auto_refresh' => '30',
    'language' => 'english',
    'sms_recipients' => '',
    'alert_threshold' => 'medium',
    'silent_mode_start' => '22:00',
    'silent_mode_end' => '06:00',
    'ups_threshold' => '20'
];

foreach ($defaultSettings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $updatedSettings = $_POST['settings'] ?? [];
    $success = '';
    $error = '';
    
    foreach ($updatedSettings as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO user_settings (admin_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("isss", $_SESSION['staff_id'], $key, $value, $value);
        if ($stmt->execute()) {
            $settings[$key] = $value;
            $success = 'Settings updated successfully!';
        } else {
            $error = 'Failed to update settings.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Settings - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/dark-mode.css">
    <style>
        /* ============================================================
           FIX SPACING
           ============================================================ */
        body {
            padding-top: 0 !important;
        }
        
        .navbar {
            height: 56px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: calc(100vh - 56px);
            margin-top: 56px;
        }
        
        /* ============================================================
           SETTINGS CONTAINER
           ============================================================ */
        .settings-container {
            display: flex;
            gap: 25px;
            align-items: flex-start;
        }
        .settings-sidebar {
            width: 220px;
            flex-shrink: 0;
            background: white;
            border-radius: 16px;
            padding: 10px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .settings-sidebar .nav-link {
            padding: 10px 18px;
            color: #555;
            border-radius: 0;
            border-left: 3px solid transparent;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .settings-sidebar .nav-link:hover {
            background: #f8f9fa;
            color: #1a3a6a;
        }
        .settings-sidebar .nav-link.active {
            background: rgba(26,58,106,0.08);
            color: #1a3a6a;
            border-left-color: #1a3a6a;
            font-weight: 600;
        }
        .settings-sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        
        .settings-content {
            flex: 1;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            min-height: 500px;
        }
        .settings-content h4 {
            color: #1a3a6a;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }
        
        /* ============================================================
           SETTING ITEMS
           ============================================================ */
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .setting-item:last-child {
            border-bottom: none;
        }
        .setting-item .setting-info h6 {
            margin: 0;
            font-weight: 600;
            color: #1a1a2e;
        }
        .setting-item .setting-info p {
            margin: 0;
            font-size: 13px;
            color: #6b7280;
        }
        
        /* ============================================================
           FORM ELEMENTS
           ============================================================ */
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1a3a6a;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.1);
        }
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #333;
        }
        
        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            border: none;
            padding: 10px 35px;
            border-radius: 12px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26,58,106,0.3);
            color: white;
        }
        
        /* ============================================================
           TOGGLE SWITCH
           ============================================================ */
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 28px;
            display: inline-block;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
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
            background: white;
            transition: .4s;
            border-radius: 50%;
        }
        .toggle-switch input:checked + .toggle-slider {
            background: #1a3a6a;
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }
        
        /* ============================================================
           BADGES
           ============================================================ */
        .view-only-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
        }
        .staff-badge {
            background: rgba(16,185,129,0.15);
            color: #10b981;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        /* ============================================================
           ALERTS
           ============================================================ */
        .alert-success {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        .alert-danger {
            background: #fecaca;
            border-color: #ef4444;
            color: #991b1b;
        }
        .alert-info {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1a3a6a;
        }
        
        /* ============================================================
           DARK MODE
           ============================================================ */
        body.dark-mode .settings-sidebar {
            background: #1a1a2e !important;
        }
        body.dark-mode .settings-sidebar .nav-link {
            color: #9090a0;
        }
        body.dark-mode .settings-sidebar .nav-link:hover {
            background: #2a2a4a;
            color: #e0e0e0;
        }
        body.dark-mode .settings-sidebar .nav-link.active {
            background: rgba(102,126,234,0.15);
            color: #93c5fd;
        }
        body.dark-mode .settings-content {
            background: #1a1a2e !important;
        }
        body.dark-mode .settings-content h4 {
            color: #e0e0e0 !important;
        }
        body.dark-mode .setting-item {
            border-bottom-color: #2a2a4a !important;
        }
        body.dark-mode .setting-item .setting-info h6 {
            color: #e0e0e0 !important;
        }
        body.dark-mode .setting-item .setting-info p {
            color: #9090a0 !important;
        }
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background: #22223a !important;
            border-color: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        body.dark-mode .form-label {
            color: #b0b0c0 !important;
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 56px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .settings-container {
                flex-direction: column;
            }
            .settings-sidebar {
                width: 100%;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 56px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.4);
                z-index: 998;
            }
            .sidebar-overlay.show {
                display: block;
            }
            .navbar-toggler {
                border-color: rgba(255,255,255,0.1) !important;
            }
            .navbar-toggler-icon {
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.8)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
            }
        }
    </style>
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            
            <!-- ===== SIDEBAR ===== -->
            <?php include 'includes/sidebar_staff.php'; ?>
            
            <!-- ===== MAIN CONTENT ===== -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-2 mb-3 border-bottom">
                    <h1 class="h2" style="font-size:24px; font-weight:700; color:#1a1a2e; margin:0;">
                        <i class="fas fa-cog me-2"></i>Settings
                    </h1>
                    <span class="view-only-badge"><i class="fas fa-eye me-1"></i> View Only</span>
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

                <div class="settings-container">
                    
                    <!-- Settings Sidebar -->
                    <div class="settings-sidebar">
                        <ul class="nav flex-column">
                            <li><a class="nav-link <?php echo $active_tab == 'profile' ? 'active' : ''; ?>" href="?tab=profile"><i class="fas fa-user"></i> Profile</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" href="?tab=dashboard"><i class="fas fa-desktop"></i> Dashboard</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'alerts' ? 'active' : ''; ?>" href="?tab=alerts"><i class="fas fa-bell"></i> Alerts</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'monitoring' ? 'active' : ''; ?>" href="?tab=monitoring"><i class="fas fa-server"></i> Monitoring</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'logs' ? 'active' : ''; ?>" href="?tab=logs"><i class="fas fa-history"></i> Logs</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'security' ? 'active' : ''; ?>" href="?tab=security"><i class="fas fa-shield-alt"></i> Security</a></li>
                            <li><a class="nav-link <?php echo $active_tab == 'support' ? 'active' : ''; ?>" href="?tab=support"><i class="fas fa-question-circle"></i> Support</a></li>
                        </ul>
                    </div>

                    <!-- Settings Content -->
                    <div class="settings-content">
                        
                        <!-- ===== PROFILE ===== -->
                        <?php if ($active_tab == 'profile'): ?>
                        <h4><i class="fas fa-user me-2"></i>Profile Settings</h4>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($staffInfo['full_name'] ?? ''); ?>" disabled>
                                    <small class="text-muted">Contact admin to change name</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($staffInfo['email'] ?? ''); ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($staffInfo['department'] ?? ''); ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Staff ID</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($staffInfo['staff_id_number'] ?? ''); ?>" disabled>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" name="settings[phone]" value="<?php echo htmlspecialchars($staffInfo['phone'] ?? ''); ?>" placeholder="Enter phone number">
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="update_settings" class="btn btn-submit">
                                    <i class="fas fa-save me-1"></i> Update Profile
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <!-- ===== DASHBOARD ===== -->
                        <?php if ($active_tab == 'dashboard'): ?>
                        <h4><i class="fas fa-desktop me-2"></i>Dashboard Preferences</h4>
                        <form method="POST">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h6>Dark Mode</h6>
                                    <p>Switch to dark theme for better viewing in low light</p>
                                </div>
                                <div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="settings[dark_mode]" <?php echo ($settings['dark_mode'] == 'true') ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h6>Auto-Refresh Interval</h6>
                                    <p>How often the dashboard updates automatically</p>
                                </div>
                                <div>
                                    <select class="form-select form-select-sm" name="settings[auto_refresh]" style="width:150px;">
                                        <option value="15" <?php echo ($settings['auto_refresh'] == '15') ? 'selected' : ''; ?>>15 seconds</option>
                                        <option value="30" <?php echo ($settings['auto_refresh'] == '30') ? 'selected' : ''; ?>>30 seconds</option>
                                        <option value="60" <?php echo ($settings['auto_refresh'] == '60') ? 'selected' : ''; ?>>1 minute</option>
                                        <option value="120" <?php echo ($settings['auto_refresh'] == '120') ? 'selected' : ''; ?>>2 minutes</option>
                                    </select>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h6>Language</h6>
                                    <p>Choose your preferred language</p>
                                </div>
                                <div>
                                    <select class="form-select form-select-sm" name="settings[language]" style="width:150px;">
                                        <option value="english" <?php echo ($settings['language'] == 'english') ? 'selected' : ''; ?>>English</option>
                                        <option value="filipino" <?php echo ($settings['language'] == 'filipino') ? 'selected' : ''; ?>>Filipino</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="update_settings" class="btn btn-submit">
                                    <i class="fas fa-save me-1"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <!-- ===== ALERTS ===== -->
                        <?php if ($active_tab == 'alerts'): ?>
                        <h4><i class="fas fa-bell me-2"></i>Alert Settings</h4>
                        <form method="POST">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h6>SMS Recipients</h6>
                                    <p>Phone numbers to receive SMS alerts (comma separated)</p>
                                </div>
                                <div>
                                    <input type="text" class="form-control form-control-sm" name="settings[sms_recipients]" value="<?php echo htmlspecialchars($settings['sms_recipients'] ?? ''); ?>" placeholder="09XXXXXXXXX, 09XXXXXXXXX" style="width:250px;">
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h6>Alert Threshold</h6>
                                    <p>Minimum priority level to trigger alerts</p>
                                </div>
                                <div>
                                    <select class="form-select form-select-sm" name="settings[alert_threshold]" style="width:150px;">
                                        <option value="low" <?php echo ($settings['alert_threshold'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo ($settings['alert_threshold'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo ($settings['alert_threshold'] == 'high') ? 'selected' : ''; ?>>High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h6>Silent Mode</h6>
                                    <p>Disable alerts during specific hours</p>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="time" class="form-control form-control-sm" name="settings[silent_mode_start]" value="<?php echo htmlspecialchars($settings['silent_mode_start'] ?? '22:00'); ?>" style="width:120px;">
                                    <span>to</span>
                                    <input type="time" class="form-control form-control-sm" name="settings[silent_mode_end]" value="<?php echo htmlspecialchars($settings['silent_mode_end'] ?? '06:00'); ?>" style="width:120px;">
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="update_settings" class="btn btn-submit">
                                    <i class="fas fa-save me-1"></i> Save Alert Settings
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <!-- ===== MONITORING ===== -->
                        <?php if ($active_tab == 'monitoring'): ?>
                        <h4><i class="fas fa-server me-2"></i>Monitoring Settings</h4>
                        <form method="POST">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h6>UPS Battery Threshold</h6>
                                    <p>Alert when battery drops below this percentage</p>
                                </div>
                                <div>
                                    <select class="form-select form-select-sm" name="settings[ups_threshold]" style="width:150px;">
                                        <option value="10" <?php echo ($settings['ups_threshold'] == '10') ? 'selected' : ''; ?>>10%</option>
                                        <option value="20" <?php echo ($settings['ups_threshold'] == '20') ? 'selected' : ''; ?>>20%</option>
                                        <option value="30" <?php echo ($settings['ups_threshold'] == '30') ? 'selected' : ''; ?>>30%</option>
                                        <option value="50" <?php echo ($settings['ups_threshold'] == '50') ? 'selected' : ''; ?>>50%</option>
                                    </select>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h6>System Health Check</h6>
                                    <p>Automatically check system health every</p>
                                </div>
                                <div>
                                    <select class="form-select form-select-sm" name="settings[health_check]" style="width:150px;">
                                        <option value="5">5 minutes</option>
                                        <option value="15" selected>15 minutes</option>
                                        <option value="30">30 minutes</option>
                                        <option value="60">1 hour</option>
                                    </select>
                                </div>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h6>Connectivity Status</h6>
                                    <p>Current system connectivity</p>
                                </div>
                                <div>
                                    <span class="badge bg-success"><i class="fas fa-wifi me-1"></i> Connected</span>
                                    <span class="badge bg-secondary ms-2">Last checked: <?php echo date('h:i A'); ?></span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="update_settings" class="btn btn-submit">
                                    <i class="fas fa-save me-1"></i> Save Monitoring Settings
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <!-- ===== LOGS ===== -->
                        <?php if ($active_tab == 'logs'): ?>
                        <h4><i class="fas fa-history me-2"></i>Logs & Reports</h4>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Filter by Date</label>
                                <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Filter by User</label>
                                <select class="form-select">
                                    <option value="">All Users</option>
                                    <option value="1">Admin</option>
                                    <option value="2">Staff</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A'); ?></td>
                                        <td><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?></td>
                                        <td><span class="badge bg-info">Login</span></td>
                                        <td>Staff logged in</td>
                                    </tr>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime('-1 hour')); ?></td>
                                        <td>System</td>
                                        <td><span class="badge bg-success">Access Granted</span></td>
                                        <td>Room 101 - Juan Dela Cruz</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">
                                            <i class="fas fa-inbox me-2"></i> More logs will appear here
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm"><i class="fas fa-file-export me-1"></i> Export Report</button>
                            <button class="btn btn-outline-secondary btn-sm ms-2"><i class="fas fa-print me-1"></i> Print</button>
                        </div>
                        <?php endif; ?>

                        <!-- ===== SECURITY ===== -->
                        <?php if ($active_tab == 'security'): ?>
                        <h4><i class="fas fa-shield-alt me-2"></i>Security Settings</h4>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h6>Active Sessions</h6>
                                <p>Currently logged in devices</p>
                            </div>
                            <div>
                                <span class="badge bg-success"><i class="fas fa-circle me-1"></i> 1 Active</span>
                            </div>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Device</th>
                                        <th>IP Address</th>
                                        <th>Last Activity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo $_SERVER['HTTP_USER_AGENT'] ? substr($_SERVER['HTTP_USER_AGENT'], 0, 50) . '...' : 'Unknown'; ?></td>
                                        <td><?php echo $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'; ?></td>
                                        <td><?php echo date('h:i A'); ?></td>
                                        <td><span class="badge bg-success">Active</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="setting-item">
                            <div class="setting-info">
                                <h6>Login History</h6>
                                <p>Recent login activity</p>
                            </div>
                            <div>
                                <span class="badge bg-info">Last login: <?php echo date('M d, Y h:i A'); ?></span>
                            </div>
                        </div>

                        <div class="setting-item">
                            <div class="setting-info">
                                <h6>Account Activity Log</h6>
                                <p>All actions performed on your account</p>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i> View Log</button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ===== SUPPORT ===== -->
                        <?php if ($active_tab == 'support'): ?>
                        <h4><i class="fas fa-question-circle me-2"></i>Support & Resources</h4>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                        <h5>User Manual</h5>
                                        <p class="text-muted small">Complete guide on how to use the system</p>
                                        <button class="btn btn-outline-primary btn-sm"><i class="fas fa-download me-1"></i> Download</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-question-circle fa-3x text-warning mb-3"></i>
                                        <h5>FAQs</h5>
                                        <p class="text-muted small">Frequently asked questions and answers</p>
                                        <button class="btn btn-outline-warning btn-sm"><i class="fas fa-eye me-1"></i> View FAQs</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-envelope fa-3x text-success mb-3"></i>
                                        <h5>Contact Admin</h5>
                                        <p class="text-muted small">Reach out to system administrator</p>
                                        <button class="btn btn-outline-success btn-sm"><i class="fas fa-envelope me-1"></i> Send Message</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
                                        <h5>System Version</h5>
                                        <p class="text-muted small">Current system information</p>
                                        <p class="mb-0"><strong>Version:</strong> v1.0</p>
                                        <p class="text-muted small">Last updated: <?php echo date('M d, Y'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
    </script>
</body>
</html>