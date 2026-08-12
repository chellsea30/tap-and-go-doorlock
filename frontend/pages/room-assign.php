<?php
/**
 * Tap-and-Go Doorlock - Room Assignment
 * Manage rooms 1-5 with 7 residents per room
 * COMPLETE VERSION - PURE DARK MODE
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
// ROOM CONFIGURATION
// ============================================================
$totalRooms = 5;
$maxPerRoom = 7;
$rooms = [];

// Generate room list
for ($i = 1; $i <= $totalRooms; $i++) {
    $rooms[] = [
        'room_number' => $i,
        'capacity' => $maxPerRoom,
        'occupants' => 0,
        'residents' => []
    ];
}

// ============================================================
// GET ROOM DATA
// ============================================================
$roomData = [];
$result = $conn->query("
    SELECT user_id, full_name, room_number, student_id, status
    FROM users 
    WHERE status = 'active' 
    AND room_number IS NOT NULL 
    AND room_number != ''
    ORDER BY room_number, full_name
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roomNum = (int)$row['room_number'];
        if (!isset($roomData[$roomNum])) {
            $roomData[$roomNum] = [];
        }
        $roomData[$roomNum][] = $row;
    }
}

// Get residents without room
$residentsWithoutRoom = [];
$result = $conn->query("
    SELECT u.user_id, u.full_name, u.student_id, rp.course, rp.year_level
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status = 'active' 
    AND (u.room_number IS NULL OR u.room_number = '' OR u.room_number = '0')
    ORDER BY u.full_name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residentsWithoutRoom[] = $row;
    }
}

// ============================================================
// PAGINATION FOR UNASSIGNED RESIDENTS
// ============================================================
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPageOptions = [10, 25, 50, 100];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 10;
}

$totalUnassigned = count($residentsWithoutRoom);
$totalPages = ceil($totalUnassigned / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$paginatedResidents = array_slice($residentsWithoutRoom, $offset, $perPage);

// ============================================================
// HANDLE ASSIGN ROOM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_room'])) {
    $user_id = (int)$_POST['user_id'];
    $room_number = (int)$_POST['room_number'];
    
    if (empty($user_id) || empty($room_number)) {
        $error = 'Please select a resident and room.';
    } else {
        // Check if room is full
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE room_number = ? AND status = 'active'");
        $stmt->bind_param("i", $room_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $currentCount = (int)$row['count'];
        $stmt->close();
        
        if ($currentCount >= $maxPerRoom) {
            $error = "Room $room_number is already full (max $maxPerRoom residents).";
        } else {
            $stmt = $conn->prepare("UPDATE users SET room_number = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $room_number, $user_id);
            if ($stmt->execute()) {
                $success = "✅ Resident assigned to Room $room_number successfully!";
                logAudit($_SESSION['admin_id'], 'Room Assignment', "Assigned user ID $user_id to Room $room_number");
                header('Location: room-assign.php?success=1');
                exit();
            } else {
                $error = "Failed to assign room: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// ============================================================
// HANDLE REMOVE FROM ROOM
// ============================================================
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $user_id = (int)$_GET['remove'];
    
    $stmt = $conn->prepare("UPDATE users SET room_number = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $success = "✅ Resident removed from room successfully!";
        logAudit($_SESSION['admin_id'], 'Room Removal', "Removed user ID $user_id from room");
        header('Location: room-assign.php?removed=1');
        exit();
    } else {
        $error = "Failed to remove from room: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================================
// HANDLE AUTO-ASSIGN
// ============================================================
if (isset($_POST['auto_assign']) && !empty($residentsWithoutRoom)) {
    $assigned = 0;
    $failed = 0;
    
    foreach ($residentsWithoutRoom as $resident) {
        // Find first available room
        $assigned_room = null;
        for ($room = 1; $room <= $totalRooms; $room++) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE room_number = ? AND status = 'active'");
            $stmt->bind_param("i", $room);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];
            $stmt->close();
            
            if ($count < $maxPerRoom) {
                $assigned_room = $room;
                break;
            }
        }
        
        if ($assigned_room) {
            $stmt = $conn->prepare("UPDATE users SET room_number = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $assigned_room, $resident['user_id']);
            if ($stmt->execute()) {
                $assigned++;
            } else {
                $failed++;
            }
            $stmt->close();
        } else {
            $failed++;
        }
    }
    
    if ($assigned > 0) {
        $success = "✅ Auto-assigned $assigned residents to available rooms.";
        if ($failed > 0) {
            $success .= " ($failed could not be assigned - no rooms available)";
        }
        logAudit($_SESSION['admin_id'], 'Auto Room Assignment', "Auto-assigned $assigned residents to rooms");
        header('Location: room-assign.php?auto=1');
        exit();
    } else {
        $error = "No available rooms to assign residents.";
    }
}

// ============================================================
// GET TOTAL RESIDENTS WITH ROOMS
// ============================================================
$totalAssigned = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE room_number IS NOT NULL AND room_number != '' AND status = 'active'");
if ($result && $row = $result->fetch_assoc()) {
    $totalAssigned = (int)$row['count'];
}

// Get dark mode
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
    <title>Room Assignment - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME
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
           FOOTER - STICKY BOTTOM
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
        
        /* ============================================================
           DARK STAT CARDS
           ============================================================ */
        .stat-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 14px 18px;
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
            margin-bottom: 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .form-section h5 {
            color: #93c5fd !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 10px;
            margin-bottom: 16px;
            font-size: 16px;
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
           DARK ROOM CARDS
           ============================================================ */
        .room-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 16px 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            border-left: 3px solid #10b981;
            transition: all 0.3s ease;
            height: 100%;
        }
        .room-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
        .room-card .room-title {
            font-weight: 700;
            color: #93c5fd !important;
            font-size: 18px;
            margin-bottom: 3px;
        }
        .room-card .room-capacity {
            font-size: 12px;
            color: #808090 !important;
        }
        .room-card .room-count {
            font-size: 24px;
            font-weight: 700;
        }
        .room-card .room-count.full { color: #f87171; }
        .room-card .room-count.available { color: #34d399; }
        .room-card .room-count.partial { color: #fbbf24; }
        .room-card .resident-item {
            padding: 4px 8px;
            margin: 2px 0;
            background: #1a2a4a !important;
            border-radius: 6px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #e0e0e0;
        }
        .room-card .resident-item .remove-btn {
            color: #f87171 !important;
            cursor: pointer;
            font-size: 12px;
        }
        .room-card .resident-item .remove-btn:hover { color: #fca5a5 !important; }
        .room-card .text-muted { color: #606070 !important; }
        
        /* ============================================================
           DARK TABLE
           ============================================================ */
        .table {
            color: #e0e0e0 !important;
            font-size: 13px;
        }
        .table th {
            color: #808090 !important;
            border-bottom: 2px solid #1a2a4a !important;
            font-size: 12px;
        }
        .table td {
            border-bottom: 1px solid #1a2a4a !important;
        }
        .table-hover tbody tr:hover {
            background: rgba(255,255,255,0.02) !important;
        }
        
        /* ============================================================
           DARK BADGES
           ============================================================ */
        .badge-success { background: #065f46 !important; color: #34d399 !important; font-size: 10px; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; font-size: 10px; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; font-size: 10px; }
        .badge-info { background: #1a3a6a !important; color: #93c5fd !important; font-size: 10px; }
        .badge-secondary { background: #1a2a4a !important; color: #808090 !important; font-size: 10px; }
        .badge-primary { background: #1a3a6a !important; color: #93c5fd !important; font-size: 10px; }
        .badge-light { background: #2a2a4a !important; color: #b0b0c0 !important; font-size: 10px; }
        
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
        .btn-auto {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            border: none !important;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            color: white !important;
            transition: all 0.3s ease;
        }
        .btn-auto:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(245,158,11,0.4);
            color: white !important;
        }
        .btn-auto:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-primary-sm {
            background: #1a3a6a !important;
            border: 1px solid #1a3a6a !important;
            color: white !important;
            padding: 3px 10px;
            font-size: 11px;
            border-radius: 6px;
        }
        .btn-primary-sm:hover {
            background: #2a5a9a !important;
            border-color: #2a5a9a !important;
            color: white !important;
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
        .alert .btn-close { filter: invert(1) !important; }
        
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
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        
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
            .room-card { padding: 12px; }
            .stat-card { padding: 12px; }
            .stat-number { font-size: 18px; }
            .stat-icon { width: 36px; height: 36px; font-size: 14px; }
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
            .form-section { 
                box-shadow: none !important; 
                border: 1px solid #333 !important;
                background: #fff !important;
            }
            .form-section h5 { color: #1a3a6a !important; border-bottom-color: #1a3a6a !important; }
            body { background: #fff !important; color: #000 !important; }
            .form-control, .form-select { background: #fff !important; color: #000 !important; border-color: #ddd !important; }
            .form-label { color: #333 !important; }
            .room-card { background: #f8f9fa !important; border-color: #ddd !important; }
            .room-card .room-title { color: #1a3a6a !important; }
            .footer { display: none !important; }
            .navbar { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
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
                    <h1><i class="fas fa-bed me-2"></i>Room Assignment</h1>
                    <span class="badge bg-success"><i class="fas fa-circle"></i> <?php echo $totalAssigned; ?> assigned</span>
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

                <!-- ============================================================
                STATS CARDS
                ============================================================ -->
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-bed"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $totalRooms; ?></div>
                                <div class="stat-label">Total Rooms</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-user-check"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $totalAssigned; ?></div>
                                <div class="stat-label">Assigned Residents</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-user-plus"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count($residentsWithoutRoom); ?></div>
                                <div class="stat-label">Unassigned</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo $maxPerRoom; ?></div>
                                <div class="stat-label">Max per Room</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                ASSIGN ROOM FORM
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-user-plus me-2"></i>Assign Resident to Room</h5>
                    
                    <form method="POST" action="">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label">Select Resident <span class="required">*</span></label>
                                <select class="form-select" name="user_id" required>
                                    <option value="">-- Select Resident --</option>
                                    <?php foreach ($residentsWithoutRoom as $resident): ?>
                                        <option value="<?php echo $resident['user_id']; ?>">
                                            <?php echo htmlspecialchars($resident['full_name']); ?> 
                                            (<?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?>)
                                            <?php if (!empty($resident['course'])): ?>
                                                - <?php echo htmlspecialchars($resident['course']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($residentsWithoutRoom)): ?>
                                    <small class="text-success"><i class="fas fa-check-circle me-1"></i> All residents have rooms assigned!</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Room Number <span class="required">*</span></label>
                                <select class="form-select" name="room_number" required>
                                    <option value="">-- Select Room --</option>
                                    <?php for ($i = 1; $i <= $totalRooms; $i++): 
                                        $count = 0;
                                        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE room_number = $i AND status = 'active'");
                                        if ($result && $row = $result->fetch_assoc()) {
                                            $count = (int)$row['count'];
                                        }
                                        $isFull = $count >= $maxPerRoom;
                                    ?>
                                        <option value="<?php echo $i; ?>" <?php echo $isFull ? 'disabled' : ''; ?>>
                                            Room <?php echo $i; ?> 
                                            (<?php echo $count; ?>/<?php echo $maxPerRoom; ?>)
                                            <?php echo $isFull ? ' - FULL' : ''; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" name="assign_room" class="btn btn-submit w-100" <?php echo empty($residentsWithoutRoom) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check me-1"></i> Assign
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <div class="mt-2">
                        <form method="POST" action="">
                            <button type="submit" name="auto_assign" class="btn btn-auto" <?php echo empty($residentsWithoutRoom) ? 'disabled' : ''; ?>>
                                <i class="fas fa-magic me-1"></i> Auto-Assign All
                            </button>
                            <small class="text-muted ms-2">Auto-assigns residents to available rooms</small>
                        </form>
                    </div>
                </div>

                <!-- ============================================================
                ROOM LIST
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-list me-2"></i>Room List</h5>
                    
                    <div class="row g-2">
                        <?php for ($i = 1; $i <= $totalRooms; $i++): 
                            $occupants = isset($roomData[$i]) ? $roomData[$i] : [];
                            $count = count($occupants);
                            $isFull = $count >= $maxPerRoom;
                            $isPartial = $count > 0 && $count < $maxPerRoom;
                            $isEmpty = $count == 0;
                            
                            $statusClass = $isFull ? 'full' : ($isPartial ? 'partial' : 'available');
                            $statusText = $isFull ? 'Full' : ($isPartial ? 'Partial' : 'Available');
                            $statusColor = $isFull ? 'text-danger' : ($isPartial ? 'text-warning' : 'text-success');
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="room-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="room-title">Room <?php echo $i; ?></div>
                                        <div class="room-capacity">
                                            <span class="room-count <?php echo $statusClass; ?>"><?php echo $count; ?></span>
                                            / <?php echo $maxPerRoom; ?> residents
                                            <span class="badge <?php echo $isFull ? 'badge-danger' : ($isPartial ? 'badge-warning' : 'badge-success'); ?> ms-1">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge badge-light"><?php echo $maxPerRoom - $count; ?> slots</span>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="room-residents">
                                    <?php if ($isEmpty): ?>
                                        <p class="text-muted text-center py-1 small">No residents assigned</p>
                                    <?php else: ?>
                                        <?php foreach ($occupants as $resident): ?>
                                            <div class="resident-item">
                                                <span>
                                                    <i class="fas fa-user me-1" style="color: #667eea;"></i>
                                                    <?php echo htmlspecialchars($resident['full_name']); ?>
                                                    <span class="text-muted small ms-1">
                                                        (<?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?>)
                                                    </span>
                                                </span>
                                                <a href="?remove=<?php echo $resident['user_id']; ?>" 
                                                   class="remove-btn" 
                                                   onclick="return confirm('Remove <?php echo htmlspecialchars($resident['full_name']); ?> from Room <?php echo $i; ?>?')">
                                                    <i class="fas fa-times-circle"></i>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- ============================================================
                UNASSIGNED RESIDENTS LIST WITH PAGINATION
                ============================================================ -->
                <?php if (!empty($residentsWithoutRoom)): ?>
                <div class="form-section">
                    <h5><i class="fas fa-user-plus me-2"></i>Unassigned Residents</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Student ID</th>
                                    <th>Course</th>
                                    <th>Year Level</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = $offset + 1; foreach ($paginatedResidents as $resident): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($resident['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($resident['course'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($resident['year_level'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="?select=<?php echo $resident['user_id']; ?>" class="btn btn-primary-sm">
                                                <i class="fas fa-bed me-1"></i> Assign
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($paginatedResidents)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-2">
                                            <i class="fas fa-check-circle me-2"></i> All residents have rooms assigned!
                                        </td>
                                    </tr>
                                <?php endif; ?>
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
                                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalUnassigned); ?> of <?php echo $totalUnassigned; ?> unassigned
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
                </div>
                <?php endif; ?>

                <div class="text-center text-muted small mt-2">
                    <i class="fas fa-info-circle me-1"></i>
                    Rooms <?php echo $totalRooms; ?> total | <?php echo $maxPerRoom; ?> residents max per room
                    <span class="mx-1">|</span>
                    <?php echo $totalAssigned; ?> assigned | <?php echo count($residentsWithoutRoom); ?> unassigned
                </div>
            </main>
        </div>
        
        <!-- ============================================================
        FOOTER - STICKY BOTTOM
        ============================================================ -->
        <footer class="footer">
            &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System - ISU-Echague Dormitory. All rights reserved.
        </footer>
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
        // AUTO-SELECT RESIDENT
        // ============================================================
        <?php if (isset($_GET['select']) && is_numeric($_GET['select'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const select = document.querySelector('select[name="user_id"]');
                if (select) {
                    select.value = '<?php echo (int)$_GET['select']; ?>';
                }
            });
        <?php endif; ?>
        
        // ============================================================
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>