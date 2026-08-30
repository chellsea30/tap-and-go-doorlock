<?php
/**
 * Tap-and-Go Doorlock - Staff Card Management
 * PURE DARK MODE - WITH SHOW ENTRIES
 * WITH FIXED NAVBAR, SIDEBAR, AND FOOTER
 */

session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication (Admin only)
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

// Include header
include '../includes/header.php'; 
$conn = getDBConnection();
$error = '';
$success = '';

// ============================================================
// PAGINATION SETTINGS
// ============================================================
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPageOptions = [10, 25, 50, 100];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 10;
}

// ============================================================
// GET ALL STAFF (with pagination)
// ============================================================
$countQuery = "SELECT COUNT(*) as total FROM staff";
$countResult = $conn->query($countQuery);
$totalStaff = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalStaff = (int)$row['total'];
}

$totalPages = ceil($totalStaff / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$staffList = [];
$result = $conn->query("
    SELECT id, full_name, position, email, phone, status, created_at
    FROM staff
    ORDER BY full_name
    LIMIT $perPage OFFSET $offset
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffList[] = $row;
    }
}

// ============================================================
// GET ACTIVE STAFF COUNT
// ============================================================
$activeStaff = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM staff WHERE status = 'active'");
if ($result && $row = $result->fetch_assoc()) {
    $activeStaff = (int)$row['count'];
}

// ============================================================
// GET INACTIVE STAFF COUNT
// ============================================================
$inactiveStaff = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM staff WHERE status = 'inactive'");
if ($result && $row = $result->fetch_assoc()) {
    $inactiveStaff = (int)$row['count'];
}

// ============================================================
// HANDLE STAFF ADD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($full_name) || empty($position)) {
        $error = 'Please fill in all required fields (Name and Position).';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO staff (full_name, position, email, phone, status, created_at)
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->bind_param("ssss", $full_name, $position, $email, $phone);
        
        if ($stmt->execute()) {
            $success = "✅ Staff member added successfully!";
            logAudit($_SESSION['admin_id'], 'Add Staff', "Added staff: $full_name ($position)");
            header('Location: staff-card.php?success=1');
            exit();
        } else {
            $error = "Failed to add staff: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================================
// HANDLE STAFF UPDATE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $staff_id = (int)$_POST['staff_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($full_name) || empty($position)) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $conn->prepare("
            UPDATE staff SET 
                full_name = ?, 
                position = ?, 
                email = ?, 
                phone = ?, 
                status = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssssi", $full_name, $position, $email, $phone, $status, $staff_id);
        
        if ($stmt->execute()) {
            $success = "✅ Staff updated successfully!";
            logAudit($_SESSION['admin_id'], 'Update Staff', "Updated staff ID: $staff_id");
            header('Location: staff-card.php?updated=1');
            exit();
        } else {
            $error = "Failed to update staff: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================================
// HANDLE STAFF DELETE
// ============================================================
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $staff_id = (int)$_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
    $stmt->bind_param("i", $staff_id);
    if ($stmt->execute()) {
        $success = "✅ Staff deleted successfully!";
        logAudit($_SESSION['admin_id'], 'Delete Staff', "Deleted staff ID: $staff_id");
        header('Location: staff-card.php?deleted=1');
        exit();
    } else {
        $error = "Failed to delete staff: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================================
// HANDLE STAFF ACTIVATION/DEACTIVATION
// ============================================================
if (isset($_GET['toggle_status']) && !empty($_GET['toggle_status'])) {
    $staff_id = (int)$_GET['toggle_status'];
    $new_status = $_GET['status'] ?? 'active';
    
    $stmt = $conn->prepare("UPDATE staff SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $staff_id);
    if ($stmt->execute()) {
        $success = "✅ Staff status updated successfully!";
        logAudit($_SESSION['admin_id'], 'Toggle Staff Status', "Staff ID: $staff_id set to $new_status");
        header('Location: staff-card.php?status_updated=1');
        exit();
    } else {
        $error = "Failed to update status: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================================
// GET DARK MODE
// ============================================================
$darkModeClass = '';
$darkModeFromDb = 'false';
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE admin_id = ? AND setting_key = 'dark_mode'");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $darkModeFromDb = $row['setting_value'];
            if ($darkModeFromDb == 'true') {
                $darkModeClass = 'dark-mode';
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Silently fail
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Card - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           RESET & BASE - SAME AS STAFF CARD PAGE
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           FIXED NAVBAR
           ============================================================ */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1050 !important;
            height: 56px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* ============================================================
           SIDEBAR - FIXED POSITION
           ============================================================ */
        .sidebar {
            position: fixed !important;
            top: 56px !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 220px !important;
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            overflow-y: auto !important;
            z-index: 1040 !important;
            padding-top: 10px !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            margin: 2px 10px !important;
            font-size: 13px !important;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar .nav-link i {
            width: 18px;
            text-align: center;
        }
        .sidebar-footer { 
            border-top-color: #1a2a4a !important;
            padding: 12px 16px !important;
            margin-top: 10px !important;
        }
        .sidebar-footer .text-muted { color: #606070 !important; font-size: 11px !important; }
        
        /* ============================================================
           PAGE WRAPPER - FLEX LAYOUT
           ============================================================ */
        .page-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .content-wrapper {
            display: flex;
            flex: 1;
        }
        
        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: 220px !important;
            margin-top: 56px !important;
            padding: 15px 25px !important;
            flex: 1;
            min-height: calc(100vh - 56px - 50px) !important;
            background: #0a0e1a !important;
        }
        
        /* ============================================================
           FOOTER - STICKY BOTTOM (DARK)
           ============================================================ */
        .footer {
            margin-left: 220px !important;
            padding: 10px 25px !important;
            background: #0d1528 !important;
            border-top: 1px solid #1a2a4a !important;
            color: #606070 !important;
            font-size: 12px !important;
            text-align: center !important;
            flex-shrink: 0;
            width: calc(100% - 220px) !important;
        }
        .footer span { color: #ffd700 !important; }
        
        /* ============================================================
           DARK STAT CARDS
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 14px 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }
        .stat-number { font-size: 20px; font-weight: 700; color: #e0e0e0; margin: 0; }
        .stat-label { font-size: 11px; color: #808090; margin: 0; }
        
        /* ============================================================
           DARK FORM SECTIONS
           ============================================================ */
        .form-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 20px 25px;
            margin-bottom: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .form-section h5 {
            color: #93c5fd !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 8px;
            margin-bottom: 16px;
            font-size: 15px;
        }
        .form-label {
            font-weight: 500;
            font-size: 12px;
            color: #b0b0c0 !important;
        }
        .form-control, .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #e0e0e0 !important;
            height: 38px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
            background: #1a1a2e !important;
            color: #e0e0e0 !important;
        }
        .form-control::placeholder { color: #606070 !important; }
        .required { color: #f87171 !important; }
        
        /* ============================================================
           DARK BUTTONS
           ============================================================ */
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            padding: 8px 25px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            color: white !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26,58,106,0.4);
            color: white !important;
        }
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
            font-size: 12px;
            padding: 5px 12px;
            border-radius: 8px;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .btn-sm-custom {
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 6px;
            border: none !important;
            transition: all 0.3s ease;
        }
        .btn-sm-custom:hover { transform: translateY(-1px); }
        .btn-warning {
            background: #4a3a1a !important;
            color: #fbbf24 !important;
        }
        .btn-warning:hover { background: #5a4a2a !important; color: #fcd34d !important; }
        .btn-success {
            background: #065f46 !important;
            color: #34d399 !important;
        }
        .btn-success:hover { background: #0a7a5a !important; color: #6ee7b7 !important; }
        .btn-danger-sm {
            background: #7a2a2a !important;
            color: #f87171 !important;
            border: none !important;
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 6px;
        }
        .btn-danger-sm:hover { background: #9a3a3a !important; color: #fca5a5 !important; }
        .btn-primary-sm {
            background: #1a3a6a !important;
            border: 1px solid #1a3a6a !important;
            color: white !important;
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 6px;
        }
        .btn-primary-sm:hover {
            background: #2a5a9a !important;
            border-color: #2a5a9a !important;
            color: white !important;
        }
        
        /* ============================================================
           DARK CARD ITEMS
           ============================================================ */
        .card-item {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 10px !important;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-left: 3px solid #10b981;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            transition: all 0.3s ease;
        }
        .card-item:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important;
        }
        .card-item .name {
            font-weight: 600;
            color: #e0e0e0 !important;
            font-size: 13px;
        }
        .card-item .detail {
            font-size: 11px;
            color: #808090 !important;
        }
        .card-item .status-badge {
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .card-item .status-active {
            background: #065f46 !important;
            color: #34d399 !important;
        }
        .card-item .status-inactive {
            background: #2a2a3a !important;
            color: #808090 !important;
        }
        .card-item.inactive {
            border-left-color: #6b7280 !important;
            opacity: 0.7;
        }
        
        /* ============================================================
           DARK TABLE
           ============================================================ */
        .table {
            color: #e0e0e0 !important;
            font-size: 13px;
            background: #111827 !important;
        }
        .table th {
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
            font-size: 12px;
            background: #0d1528 !important;
        }
        .table td {
            border-bottom: 1px solid #1a2a4a !important;
            background: #111827 !important;
            color: #e0e0e0 !important;
        }
        .table-hover tbody tr:hover td {
            background: rgba(255,255,255,0.03) !important;
        }
        .table .text-muted { color: #6b7280 !important; }
        .table-responsive {
            background: #111827 !important;
            border-radius: 8px;
            border: 1px solid #1a2a4a !important;
            overflow: hidden;
        }
        
        /* ============================================================
           DARK ALERTS
           ============================================================ */
        .alert-success {
            background: #065f46 !important;
            border-color: #065f46 !important;
            color: #6ee7b7 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        .alert-danger {
            background: #7a2a2a !important;
            border-color: #7a2a2a !important;
            color: #f87171 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        .alert-warning {
            background: #4a3a1a !important;
            border-color: #4a3a1a !important;
            color: #fbbf24 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        .alert-info {
            background: #1a2a4a !important;
            border-color: #1a3a6a !important;
            color: #93c5fd !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        .alert .btn-close { filter: invert(1) !important; }
        
        /* ============================================================
           PAGE HEADER
           ============================================================ */
        .page-header {
            padding-bottom: 10px;
            margin-bottom: 15px;
            border-bottom: 1px solid #1a2a4a;
        }
        .page-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #e0e0e0;
        }
        .page-header h1 i {
            color: #1a3a6a;
        }
        
        /* ============================================================
           PAGINATION - DARK
           ============================================================ */
        .pagination-container {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 10px 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-top: 15px;
        }
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: none;
            color: #9090a0 !important;
            background: transparent !important;
            font-weight: 500;
            padding: 5px 12px;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        .pagination .page-link:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        .pagination .page-item.disabled .page-link {
            color: #4a4a5a !important;
        }
        .page-info { color: #808090 !important; font-size: 12px; }
        .page-info strong { color: #93c5fd !important; }
        
        /* ============================================================
           PER PAGE SELECTOR - DARK
           ============================================================ */
        .per-page-selector select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 6px;
            padding: 3px 6px;
            font-size: 12px;
        }
        .per-page-selector select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .per-page-selector label { color: #808090 !important; font-size: 12px; margin: 0; }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .text-danger { color: #f87171 !important; }
        
        /* ============================================================
           MODAL - DARK
           ============================================================ */
        .modal-content {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
        }
        .modal-header {
            border-bottom: 1px solid #1a2a4a !important;
        }
        .modal-header .modal-title {
            color: #e0e0e0 !important;
        }
        .modal-header .btn-close {
            filter: invert(1) !important;
        }
        .modal-body {
            color: #e0e0e0 !important;
        }
        .modal-footer {
            border-top: 1px solid #1a2a4a !important;
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed !important;
                top: 56px !important;
                bottom: 0 !important;
                left: -260px !important;
                width: 260px !important;
                transition: left 0.3s ease !important;
                z-index: 1040 !important;
            }
            .sidebar.show { left: 0 !important; }
            .main-content {
                margin-left: 0 !important;
                padding: 12px 15px !important;
                min-height: calc(100vh - 56px - 40px) !important;
            }
            .footer {
                margin-left: 0 !important;
                padding: 8px 15px !important;
                width: 100% !important;
            }
            .form-section { padding: 15px; }
            .stat-card { padding: 12px; }
            .stat-number { font-size: 18px; }
            .stat-icon { width: 36px; height: 36px; font-size: 14px; }
            .pagination-container .row {
                flex-direction: column;
                gap: 8px;
            }
            .pagination-container .col-md-6 {
                width: 100%;
                text-align: center !important;
            }
            .pagination {
                justify-content: center !important;
            }
        }
        
        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0a0e1a;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e2a3a;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #ffd700;
        }
        
        @media print {
            .no-print { display: none !important; }
            .footer { display: none !important; }
            .navbar { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .stat-card { background: #f8f9fa !important; border: 1px solid #ddd !important; }
            body { background: #fff !important; color: #000 !important; }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-wrapper">
        <div class="content-wrapper">
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- MAIN CONTENT -->
            <main class="main-content">
                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center">
                    <h1><i class="fas fa-id-badge me-2"></i>Staff Card</h1>
                    <button class="btn btn-submit btn-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                        <i class="fas fa-user-plus me-1"></i> Add Staff
                    </button>
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

                <!-- Stats -->
                <div class="row g-2 mb-3">
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $totalStaff; ?></div>
                                <div class="stat-label">Total Staff</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-user-check"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $activeStaff; ?></div>
                                <div class="stat-label">Active</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-4 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #6b7280;"><i class="fas fa-user-slash"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $inactiveStaff; ?></div>
                                <div class="stat-label">Inactive</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                STAFF LIST WITH PAGINATION
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-list me-2"></i>Staff Members</h5>
                    
                    <?php if (empty($staffList)): ?>
                        <p class="text-muted text-center py-2">
                            <i class="fas fa-info-circle me-2"></i>
                            No staff members found. Click "Add Staff" to get started.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = $offset + 1; foreach ($staffList as $staff): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($staff['position']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['email'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $staff['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo ucfirst($staff['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary-sm" data-bs-toggle="modal" data-bs-target="#editStaffModal<?php echo $staff['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($staff['status'] == 'active'): ?>
                                                    <a href="?toggle_status=<?php echo $staff['id']; ?>&status=inactive" 
                                                       class="btn btn-warning btn-sm-custom"
                                                       onclick="return confirm('Deactivate this staff?')">
                                                        <i class="fas fa-pause"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?toggle_status=<?php echo $staff['id']; ?>&status=active" 
                                                       class="btn btn-success btn-sm-custom"
                                                       onclick="return confirm('Activate this staff?')">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?delete=<?php echo $staff['id']; ?>" 
                                                   class="btn btn-danger-sm"
                                                   onclick="return confirm('Delete this staff member permanently?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        
                                        <!-- ============================================================
                                        EDIT STAFF MODAL
                                        ============================================================ -->
                                        <div class="modal fade" id="editStaffModal<?php echo $staff['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Staff</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                            <input type="hidden" name="update_staff" value="1">
                                                            
                                                            <div class="mb-2">
                                                                <label class="form-label">Full Name <span class="required">*</span></label>
                                                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label">Position <span class="required">*</span></label>
                                                                <input type="text" class="form-control" name="position" value="<?php echo htmlspecialchars($staff['position']); ?>" required>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label">Email</label>
                                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>">
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label">Phone</label>
                                                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>">
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status">
                                                                    <option value="active" <?php echo $staff['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                                    <option value="inactive" <?php echo $staff['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-submit">Update Staff</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- ============================================================
                        PAGINATION WITH SHOW ENTRIES
                        ============================================================ -->
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="page-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalStaff); ?> of <?php echo $totalStaff; ?> staff
                                        <span class="mx-1 text-muted">|</span>
                                        <span class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                                        <!-- Per Page Selector -->
                                        <div class="per-page-selector d-flex align-items-center gap-1">
                                            <label>Show:</label>
                                            <select onchange="changePerPage(this.value)">
                                                <?php foreach ($perPageOptions as $option): ?>
                                                    <option value="<?php echo $option; ?>" <?php echo $option == $perPage ? 'selected' : ''; ?>>
                                                        <?php echo $option; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-end mb-0">
                                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=1<?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-double-left"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-left"></i>
                                                    </a>
                                                </li>
                                                
                                                <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($totalPages, $page + 2);
                                                if ($startPage > 1) {
                                                    echo '<li class="page-item"><span class="page-link">...</span></li>';
                                                }
                                                for ($i = $startPage; $i <= $endPage; $i++):
                                                ?>
                                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo '&per_page=' . $perPage; ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                <?php if ($endPage < $totalPages): ?>
                                                    <li class="page-item"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-right"></i>
                                                    </a>
                                                </li>
                                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo '&per_page=' . $perPage; ?>">
                                                        <i class="fas fa-angle-double-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </main>
        </div>

    <?php include '../includes/footer.php'; ?>

    <!-- ============================================================
    ADD STAFF MODAL
    ============================================================ -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New Staff</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="add_staff" value="1">
                        
                        <div class="mb-2">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" class="form-control" name="full_name" placeholder="Enter full name" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Position <span class="required">*</span></label>
                            <input type="text" class="form-control" name="position" placeholder="e.g., Security Guard, Admin, Maintenance" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="staff@example.com">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" placeholder="09123456789">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-submit">Add Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // CHANGE PER PAGE
        // ============================================================
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        // ============================================================
        // SIDEBAR TOGGLE
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
