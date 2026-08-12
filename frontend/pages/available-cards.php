<?php
/**
 * Tap-and-Go Doorlock - Available RFID Cards Inventory
 * Manage available cards for assignment
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
// CREATE TABLE IF NOT EXISTS
// ============================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS available_rfid_cards (
        card_id INT AUTO_INCREMENT PRIMARY KEY,
        card_uid VARCHAR(50) UNIQUE NOT NULL,
        card_type ENUM('resident', 'staff', 'visitor') DEFAULT 'resident',
        status ENUM('available', 'assigned') DEFAULT 'available',
        notes VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_card_uid (card_uid),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ============================================================
// HANDLE ADD CARD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_card'])) {
    $card_uid = strtoupper(trim($_POST['card_uid'] ?? ''));
    $card_type = $_POST['card_type'] ?? 'resident';
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($card_uid)) {
        $error = 'Please enter a Card UID.';
    } elseif (strlen($card_uid) < 4) {
        $error = 'Card UID must be at least 4 characters.';
    } else {
        // Check if already exists
        $check = $conn->prepare("SELECT card_id FROM available_rfid_cards WHERE card_uid = ?");
        $check->bind_param("s", $card_uid);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Card UID already exists in inventory.';
        } else {
            // Check if already assigned to a resident
            $check2 = $conn->prepare("SELECT card_uid FROM rfid_cards WHERE card_uid = ?");
            $check2->bind_param("s", $card_uid);
            $check2->execute();
            if ($check2->get_result()->num_rows > 0) {
                $error = 'Card UID is already assigned to a resident.';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO available_rfid_cards (card_uid, card_type, notes, status)
                    VALUES (?, ?, ?, 'available')
                ");
                $stmt->bind_param("sss", $card_uid, $card_type, $notes);
                if ($stmt->execute()) {
                    $success = "✅ Card added to available inventory!";
                    logAudit($_SESSION['admin_id'], 'Add Available Card', "Added card $card_uid to inventory");
                    header('Location: available-cards.php?added=1');
                    exit();
                } else {
                    $error = "Failed to add card: " . $stmt->error;
                }
                $stmt->close();
            }
            $check2->close();
        }
        $check->close();
    }
}

// ============================================================
// HANDLE BULK ADD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add'])) {
    $card_uids = trim($_POST['card_uids'] ?? '');
    $card_type = $_POST['card_type'] ?? 'resident';
    
    if (empty($card_uids)) {
        $error = 'Please enter at least one card UID.';
    } else {
        $uids = array_map('trim', explode("\n", $card_uids));
        $uids = array_filter($uids);
        $added = 0;
        $failed = 0;
        
        foreach ($uids as $uid) {
            $uid = strtoupper($uid);
            if (empty($uid) || strlen($uid) < 4) continue;
            
            // Check if exists
            $check = $conn->prepare("SELECT card_id FROM available_rfid_cards WHERE card_uid = ?");
            $check->bind_param("s", $uid);
            $check->execute();
            if ($check->get_result()->num_rows == 0) {
                $stmt = $conn->prepare("
                    INSERT INTO available_rfid_cards (card_uid, card_type, status)
                    VALUES (?, ?, 'available')
                ");
                $stmt->bind_param("ss", $uid, $card_type);
                if ($stmt->execute()) {
                    $added++;
                } else {
                    $failed++;
                }
                $stmt->close();
            } else {
                $failed++;
            }
            $check->close();
        }
        
        if ($added > 0) {
            $success = "✅ Added $added card(s) to inventory.";
            if ($failed > 0) {
                $success .= " $failed card(s) already exist.";
            }
            logAudit($_SESSION['admin_id'], 'Bulk Add Available Cards', "Added $added cards to inventory");
            header('Location: available-cards.php?bulk=1');
            exit();
        } else {
            $error = "No new cards were added. They may already exist.";
        }
    }
}

// ============================================================
// HANDLE REMOVE CARD
// ============================================================
if (isset($_GET['remove']) && !empty($_GET['remove'])) {
    $card_id = (int)$_GET['remove'];
    
    $stmt = $conn->prepare("DELETE FROM available_rfid_cards WHERE card_id = ? AND status = 'available'");
    $stmt->bind_param("i", $card_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = "✅ Card removed from inventory.";
        logAudit($_SESSION['admin_id'], 'Remove Available Card', "Removed card ID: $card_id");
        header('Location: available-cards.php?removed=1');
        exit();
    } else {
        $error = "Card not found or already assigned.";
    }
    $stmt->close();
}

// ============================================================
// GET AVAILABLE CARDS (with pagination)
// ============================================================
$availableCards = [];

// Count total available
$countResult = $conn->query("SELECT COUNT(*) as total FROM available_rfid_cards WHERE status = 'available'");
$totalAvailable = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalAvailable = (int)$row['total'];
}

$totalPages = ceil($totalAvailable / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$result = $conn->query("
    SELECT * FROM available_rfid_cards 
    WHERE status = 'available'
    ORDER BY card_uid
    LIMIT $perPage OFFSET $offset
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $availableCards[] = $row;
    }
}

// ============================================================
// GET ASSIGNED CARDS
// ============================================================
$assignedCards = [];
$result = $conn->query("
    SELECT ac.*, u.full_name as user_name, u.room_number
    FROM available_rfid_cards ac
    LEFT JOIN rfid_cards c ON ac.card_uid = c.card_uid
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE ac.status = 'assigned'
    ORDER BY ac.card_uid
    LIMIT 50
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $assignedCards[] = $row;
    }
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
    <title>Available Cards - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           RESET & BASE
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
            padding: 14px 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
        .stat-icon {
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; flex-shrink: 0;
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
        .btn-warning {
            background: #4a3a1a !important;
            border: none !important;
            color: #fbbf24 !important;
            font-size: 13px;
            padding: 8px 20px;
            border-radius: 10px;
        }
        .btn-warning:hover {
            background: #5a4a2a !important;
            color: #fcd34d !important;
        }
        .btn-danger-custom {
            background: #7a2a2a !important;
            border: none !important;
            color: #f87171 !important;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 11px;
            transition: all 0.3s ease;
        }
        .btn-danger-custom:hover { background: #8a3a3a !important; color: #fca5a5 !important; }
        
        /* ============================================================
           DARK CARD ITEMS
           ============================================================ */
        .card-item {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 10px !important;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-left: 3px solid #f59e0b;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
            transition: all 0.3s ease;
        }
        .card-item:hover { transform: translateX(4px); box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important; }
        .card-item .uid {
            font-family: monospace;
            font-weight: 700;
            color: #93c5fd !important;
            font-size: 16px;
            background: #1a2a4a !important;
            padding: 3px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .card-item .detail {
            font-size: 11px;
            color: #808090 !important;
        }
        .card-item.assigned {
            border-left-color: #10b981 !important;
            opacity: 0.8;
        }
        .badge-available { background: #065f46 !important; color: #34d399 !important; font-size: 9px; }
        .badge-assigned { background: #1a2a4a !important; color: #93c5fd !important; font-size: 9px; }
        .badge-light { background: #2a2a4a !important; color: #b0b0c0 !important; font-size: 9px; }
        
        /* ============================================================
           DARK TEXTAREA
           ============================================================ */
        .card-uid-list {
            font-family: monospace;
            font-size: 13px;
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 8px;
            padding: 8px 12px;
        }
        .card-uid-list:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .card-uid-list::placeholder { color: #606070 !important; }
        
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
        a { color: #93c5fd !important; }
        a:hover { color: #bfdbfe !important; }
        
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
            .card-item { background: #f8f9fa !important; border: 1px solid #ddd !important; }
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
                    <h1><i class="fas fa-boxes me-2"></i>Available Cards Inventory</h1>
                    <span class="badge bg-warning"><i class="fas fa-box me-1"></i> <?php echo count($availableCards); ?> available</span>
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
                STATS
                ============================================================ -->
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-box"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count($availableCards); ?></div>
                                <div class="stat-label">Available</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count($assignedCards); ?></div>
                                <div class="stat-label">Assigned</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count($availableCards) + count($assignedCards); ?></div>
                                <div class="stat-label">Total Inventory</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #8b5cf6;"><i class="fas fa-arrow-right"></i></div>
                            <div>
                                <div class="stat-number">
                                    <?php 
                                        $total = count($availableCards) + count($assignedCards);
                                        $assigned = count($assignedCards);
                                        echo $total > 0 ? round(($assigned / $total) * 100) : 0;
                                    ?>%
                                </div>
                                <div class="stat-label">Assigned Rate</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                ADD SINGLE CARD
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-plus-circle me-2"></i>Add Card to Inventory</h5>
                    
                    <form method="POST" action="">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Card UID <span class="required">*</span></label>
                                <input type="text" class="form-control" name="card_uid" placeholder="e.g., A1B2C3D4" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Card Type</label>
                                <select class="form-select" name="card_type">
                                    <option value="resident">Resident</option>
                                    <option value="staff">Staff</option>
                                    <option value="visitor">Visitor</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Notes</label>
                                <input type="text" class="form-control" name="notes" placeholder="Optional notes">
                            </div>
                            <div class="col-md-12">
                                <button type="submit" name="add_card" class="btn btn-submit">
                                    <i class="fas fa-plus me-1"></i> Add Card
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ============================================================
                BULK ADD
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-layer-group me-2"></i>Bulk Add Cards</h5>
                    
                    <form method="POST" action="">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label">Card UIDs (one per line)</label>
                                <textarea class="form-control card-uid-list" name="card_uids" rows="4" placeholder="A1B2C3D4&#10;E5F6G7H8&#10;I9J0K1L2" style="resize:vertical; min-height:80px;"></textarea>
                                <small class="text-muted">Enter one UID per line</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Card Type</label>
                                <select class="form-select" name="card_type">
                                    <option value="resident">Resident</option>
                                    <option value="staff">Staff</option>
                                    <option value="visitor">Visitor</option>
                                </select>
                                <div class="mt-2">
                                    <button type="submit" name="bulk_add" class="btn btn-warning w-100">
                                        <i class="fas fa-layer-group me-1"></i> Add All Cards
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ============================================================
                AVAILABLE CARDS LIST WITH PAGINATION
                ============================================================ -->
                <div class="form-section">
                    <h5><i class="fas fa-list me-2"></i>Available Cards</h5>
                    
                    <?php if (empty($availableCards)): ?>
                        <p class="text-muted text-center py-2">
                            <i class="fas fa-box fa-2x d-block mb-2"></i>
                            No available cards in inventory.
                            <br>Add cards using the form above.
                        </p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($availableCards as $card): ?>
                                <div class="col-md-4 col-lg-3">
                                    <div class="card-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="uid"><?php echo htmlspecialchars($card['card_uid']); ?></div>
                                                <div class="detail">
                                                    <span class="badge badge-available">Available</span>
                                                    <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($card['card_type']); ?></span>
                                                </div>
                                                <?php if (!empty($card['notes'])): ?>
                                                    <div class="detail mt-1">
                                                        <i class="fas fa-sticky-note me-1"></i>
                                                        <?php echo htmlspecialchars($card['notes']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="detail">
                                                    <i class="far fa-calendar-alt me-1"></i>
                                                    Added: <?php echo date('M d, Y', strtotime($card['created_at'])); ?>
                                                </div>
                                            </div>
                                            <a href="?remove=<?php echo $card['card_id']; ?>" 
                                               class="btn btn-danger-custom"
                                               onclick="return confirm('Remove this card from inventory?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalAvailable); ?> of <?php echo $totalAvailable; ?> cards
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

                <!-- ============================================================
                ASSIGNED CARDS LIST
                ============================================================ -->
                <?php if (!empty($assignedCards)): ?>
                <div class="form-section">
                    <h5><i class="fas fa-check-circle me-2"></i>Assigned Cards</h5>
                    
                    <div class="row g-2">
                        <?php foreach ($assignedCards as $card): ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="card-item assigned">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="uid"><?php echo htmlspecialchars($card['card_uid']); ?></div>
                                            <div class="detail">
                                                <span class="badge badge-assigned">Assigned</span>
                                                <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($card['card_type']); ?></span>
                                            </div>
                                            <div class="detail">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($card['user_name'] ?? 'Unknown'); ?>
                                                <?php if (!empty($card['room_number'])): ?>
                                                    <span class="mx-1">•</span>
                                                    Room <?php echo htmlspecialchars($card['room_number']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="detail">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                Assigned: <?php echo date('M d, Y', strtotime($card['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="text-center text-muted small mt-2">
                    <i class="fas fa-info-circle me-1"></i>
                    Available cards can be assigned to residents from the 
                    <a href="register-rfid.php">Register RFID</a> page.
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

    <?php include '../includes/footer.php'; ?>
    
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
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>