<?php
/**
 * Tap-and-Go Doorlock - View Resident Profile
 * FULL DARK MODE - With Fixed Action Bar Below Navbar
 */

// Start session
session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: residents.php');
    exit();
}

// Include header
include '../includes/header.php'; 

$resident = null;
$profile = null;
$admission = null;
$card = null;
$error = '';

try {
    $conn = getDBConnection();
    
    // ============================================================
    // GET USER DATA
    // ============================================================
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND status != 'deleted'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $resident = $result->fetch_assoc();
    $stmt->close();
    
    if (!$resident) {
        header('Location: residents.php');
        exit();
    }
    
    // ============================================================
    // GET PROFILE DATA
    // ============================================================
    $stmt = $conn->prepare("SELECT * FROM resident_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    
    // ============================================================
    // GET ADMISSION DATA
    // ============================================================
    $stmt = $conn->prepare("SELECT * FROM admission_records WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admission = $result->fetch_assoc();
    $stmt->close();
    
    // ============================================================
    // GET RFID CARD DATA
    // ============================================================
    $stmt = $conn->prepare("SELECT * FROM rfid_cards WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $card = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $error = 'Error loading data: ' . $e->getMessage();
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function getVal($array, $key, $default = 'N/A') {
    if ($array && isset($array[$key]) && $array[$key] !== null && $array[$key] !== '') {
        return htmlspecialchars(trim($array[$key]));
    }
    return $default;
}

function displayVal($value, $default = 'N/A') {
    if ($value !== null && $value !== '' && $value !== '0') {
        return htmlspecialchars(trim($value));
    }
    return $default;
}

function getStatusBadge($status) {
    $status = strtolower($status ?? 'pending');
    $colors = [
        'active' => 'active',
        'approved' => 'active',
        'pending' => 'pending',
        'inactive' => 'inactive',
        'denied' => 'denied',
        'completed' => 'completed'
    ];
    return $colors[$status] ?? 'inactive';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Resident - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           ROOT VARIABLES - FULL DARK MODE
           ============================================================ */
        :root {
            --bg-primary: #0a0e17;
            --bg-card: #111927;
            --bg-card-hover: #1a2335;
            --bg-header: linear-gradient(135deg, #0a1628, #0d1f3c);
            --text-primary: #e8edf5;
            --text-secondary: #8899bb;
            --text-muted: #4a5a7a;
            --border-color: #1a2a44;
            --gold: #ffd700;
            --gold-dark: #b8960f;
            --shadow: 0 4px 24px rgba(0,0,0,0.4);
            --shadow-hover: 0 6px 32px rgba(0,0,0,0.6);
            --success-bg: #0d3b2e;
            --success-text: #6ee7b7;
            --warning-bg: #3d2e0a;
            --warning-text: #fcd34d;
            --danger-bg: #3d0a0a;
            --danger-text: #fca5a5;
            --info-bg: #0a2a3d;
            --info-text: #7dd3fc;
            --secondary-bg: #1a2335;
            --secondary-text: #8899bb;
            --navbar-height: 60px;
        }

        /* ============================================================
           GENERAL STYLES
           ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        .container-fluid {
            background: var(--bg-primary) !important;
        }

        .row {
            background: var(--bg-primary) !important;
        }

        main {
            background: var(--bg-primary) !important;
            padding-top: 0 !important;
        }

        /* ============================================================
           FIXED ACTION BAR - BELOW NAVBAR
           ============================================================ */
        .action-bar {
            position: sticky;
            top: var(--navbar-height, 60px);
            z-index: 1040;
            background: var(--bg-card) !important;
            padding: 10px 24px;
            margin: 0 -12px 20px -12px;
            border-bottom: 2px solid var(--gold-dark);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-radius: 0 0 12px 12px;
            min-height: 60px;
        }

        .action-bar .title-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .action-bar .title-section .h2 {
            color: var(--text-primary) !important;
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }

        .action-bar .title-section .h2 i {
            color: var(--gold) !important;
        }

        .action-bar .btn-group-custom {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        /* ============================================================
           BUTTONS - DARK MODE
           ============================================================ */
        .btn-outline-secondary {
            color: var(--text-secondary) !important;
            border-color: var(--border-color) !important;
            background: transparent !important;
        }

        .btn-outline-secondary:hover {
            background: var(--bg-card-hover) !important;
            color: var(--text-primary) !important;
            border-color: var(--gold-dark) !important;
        }

        .btn-primary {
            background: var(--gold-dark) !important;
            border-color: var(--gold-dark) !important;
            color: #0a0e17 !important;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: var(--gold) !important;
            border-color: var(--gold) !important;
            color: #0a0e17 !important;
        }

        .btn-outline-primary {
            color: var(--gold) !important;
            border-color: var(--gold-dark) !important;
            background: transparent !important;
        }

        .btn-outline-primary:hover {
            background: var(--gold-dark) !important;
            color: #0a0e17 !important;
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        /* ============================================================
           THEME TOGGLE
           ============================================================ */
        .theme-toggle {
            background: var(--bg-card-hover) !important;
            border: 1px solid var(--border-color);
            color: var(--gold) !important;
            font-size: 15px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 6px;
            transition: all 0.3s ease;
            line-height: 1.5;
        }

        .theme-toggle:hover {
            background: var(--gold-dark) !important;
            color: #0a0e17 !important;
            border-color: var(--gold-dark);
        }

        /* ============================================================
           PRINT SECTION - DARK MODE
           ============================================================ */
        .print-section {
            background: var(--bg-card) !important;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .print-section:hover {
            box-shadow: var(--shadow-hover);
        }

        .print-section h6 {
            color: var(--gold) !important;
            font-weight: 700;
            border-bottom: 2px solid var(--gold-dark);
            padding-bottom: 8px;
            margin-bottom: 15px;
        }

        .print-header-logo {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .print-header-logo .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0a1628, #0d1f3c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-weight: 800;
            font-size: 20px;
            flex-shrink: 0;
            border: 2px solid var(--gold-dark);
        }

        .logo-text {
            font-size: 20px;
            font-weight: 800;
            color: var(--gold) !important;
            letter-spacing: 2px;
        }

        .logo-sub {
            font-size: 12px;
            color: var(--text-secondary) !important;
            letter-spacing: 1px;
        }

        .logo-line {
            width: 80px;
            height: 3px;
            background: var(--gold);
            margin: 5px auto;
        }

        /* ============================================================
           DETAIL ROWS - DARK STYLE
           ============================================================ */
        .detail-row {
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row:hover {
            background: var(--bg-card-hover);
            padding-left: 8px;
            border-radius: 4px;
        }

        .info-label {
            color: var(--text-secondary) !important;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: block;
            margin-bottom: 1px;
        }

        .info-value {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary) !important;
            display: block;
            padding: 1px 0;
        }

        /* ============================================================
           PROFILE AVATAR - DARK MODE
           ============================================================ */
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0a1628, #0d1f3c);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: var(--gold);
            overflow: hidden;
            border: 4px solid var(--gold-dark);
            margin: 0 auto;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ============================================================
           BADGES - DARK MODE
           ============================================================ */
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.3px;
        }

        .badge-active {
            background: var(--success-bg) !important;
            color: var(--success-text) !important;
            border: 1px solid rgba(110, 231, 183, 0.2);
        }

        .badge-pending {
            background: var(--warning-bg) !important;
            color: var(--warning-text) !important;
            border: 1px solid rgba(252, 211, 77, 0.2);
        }

        .badge-inactive {
            background: var(--secondary-bg) !important;
            color: var(--secondary-text) !important;
            border: 1px solid rgba(136, 153, 187, 0.2);
        }

        .badge-denied {
            background: var(--danger-bg) !important;
            color: var(--danger-text) !important;
            border: 1px solid rgba(252, 165, 165, 0.2);
        }

        .badge-completed {
            background: var(--info-bg) !important;
            color: var(--info-text) !important;
            border: 1px solid rgba(125, 211, 252, 0.2);
        }

        .badge-no-card {
            background: var(--secondary-bg) !important;
            color: var(--text-muted) !important;
            border: 1px solid rgba(74, 90, 122, 0.2);
        }

        /* ============================================================
           ALERT - DARK MODE
           ============================================================ */
        .alert-danger {
            background: var(--danger-bg) !important;
            color: var(--danger-text) !important;
            border-color: rgba(252, 165, 165, 0.2) !important;
        }

        .alert-danger .btn-close {
            filter: brightness(0.5) invert(1);
        }

        /* ============================================================
           PRINT FOOTER - DARK MODE
           ============================================================ */
        .print-footer {
            text-align: center !important;
            margin-top: 20px !important;
            padding-top: 10px !important;
            border-top: 1px solid var(--border-color) !important;
            font-size: 11px !important;
            color: var(--text-muted) !important;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        .text-muted i {
            color: var(--gold-dark) !important;
        }

        /* ============================================================
           PRINT STYLES
           ============================================================ */
        @media print {
            .no-print { display: none !important; }
            
            .action-bar {
                display: none !important;
            }
            
            body * {
                visibility: hidden !important;
            }
            
            #printContainer, 
            #printContainer * {
                visibility: visible !important;
            }
            
            #printContainer {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                padding: 30px !important;
                background: #0a0e17 !important;
                margin: 0 !important;
            }
            
            .print-section {
                box-shadow: none !important;
                border: 1px solid #1a2a44 !important;
                border-radius: 8px !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                margin-bottom: 15px !important;
                padding: 20px !important;
                background: #0a0e17 !important;
            }
            
            .print-header {
                text-align: center !important;
                margin-bottom: 20px !important;
                border-bottom: 2px solid var(--gold-dark) !important;
                padding-bottom: 15px !important;
            }
            
            .print-header .logo-text {
                font-size: 22px !important;
                font-weight: 800 !important;
                color: var(--gold) !important;
                letter-spacing: 3px !important;
            }
            
            .print-header .logo-sub {
                font-size: 12px !important;
                color: var(--text-secondary) !important;
                letter-spacing: 2px !important;
            }
            
            .print-header .logo-line {
                width: 100px !important;
                height: 3px !important;
                background: var(--gold) !important;
                margin: 8px auto !important;
            }
            
            .print-label {
                font-weight: 600 !important;
                color: var(--gold) !important;
                font-size: 12px !important;
            }
            
            .print-value {
                font-weight: 500 !important;
                color: var(--text-primary) !important;
                font-size: 14px !important;
            }
            
            .print-footer {
                text-align: center !important;
                margin-top: 20px !important;
                padding-top: 10px !important;
                border-top: 1px solid var(--border-color) !important;
                font-size: 11px !important;
                color: var(--text-muted) !important;
            }
            
            .badge-status {
                border: 1px solid var(--gold-dark) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .profile-avatar {
                border: 2px solid var(--gold-dark) !important;
            }
            
            .detail-row {
                border-bottom: 1px solid var(--border-color) !important;
            }
            
            .info-label {
                color: var(--text-secondary) !important;
            }
            
            .info-value {
                color: var(--text-primary) !important;
            }
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 992px) {
            .action-bar {
                top: var(--navbar-height, 56px);
                padding: 8px 16px;
                min-height: 50px;
            }
        }

        @media (max-width: 768px) {
            .action-bar {
                top: var(--navbar-height, 56px);
                padding: 8px 14px;
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                margin: 0 -8px 15px -8px;
                min-height: auto;
            }
            
            .action-bar .title-section {
                justify-content: center;
            }
            
            .action-bar .title-section .h2 {
                font-size: 16px;
            }
            
            .action-bar .btn-group-custom {
                justify-content: center;
            }
            
            .action-bar .btn-group-custom .btn {
                font-size: 11px;
                padding: 4px 8px;
            }
            
            .theme-toggle {
                font-size: 13px;
                padding: 4px 8px;
            }
            
            .print-section {
                padding: 15px;
            }
            
            .print-header-logo {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            
            .logo-text {
                font-size: 16px;
            }
        }

        @media (max-width: 576px) {
            .action-bar {
                top: var(--navbar-height, 56px);
                padding: 6px 10px;
                margin: 0 -4px 12px -4px;
            }
            
            .action-bar .title-section .h2 {
                font-size: 14px;
            }
            
            .action-bar .btn-group-custom {
                gap: 4px;
            }
            
            .action-bar .btn-group-custom .btn {
                font-size: 10px;
                padding: 3px 6px;
            }
            
            .theme-toggle {
                font-size: 12px;
                padding: 3px 6px;
            }
            
            .print-section {
                padding: 12px;
            }
            
            .info-label {
                font-size: 9px;
            }
            
            .info-value {
                font-size: 11px;
            }
        }

        /* ============================================================
           SCROLLBAR - DARK STYLE
           ============================================================ */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gold-dark);
        }

        /* ============================================================
           NAVBAR OVERRIDES
           ============================================================ */
        .navbar {
            background: var(--bg-card) !important;
            border-bottom: 1px solid var(--border-color) !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1060 !important;
        }

        .navbar .navbar-brand,
        .navbar .nav-link {
            color: var(--text-primary) !important;
        }

        .navbar .nav-link:hover {
            color: var(--gold) !important;
        }

        .sidebar {
            background: var(--bg-card) !important;
            border-right: 1px solid var(--border-color) !important;
        }

        .sidebar .nav-link {
            color: var(--text-secondary) !important;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--gold) !important;
            background: var(--bg-card-hover) !important;
        }

        /* ============================================================
           ADJUST FOR SIDEBAR
           ============================================================ */
        @media (min-width: 768px) {
            .col-md-9 {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- ============================================================
                FIXED ACTION BAR - BELOW NAVBAR
                ============================================================ -->
                <div class="action-bar no-print">
                    <div class="title-section">
                        <h1 class="h2">
                            <i class="fas fa-user-circle me-2"></i>
                            Resident Profile
                        </h1>
                    </div>
                    <div class="btn-group-custom">
                        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                            <i class="fas fa-moon" id="themeIcon"></i>
                        </button>
                        <a href="residents.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <a href="edit-resident.php?id=<?php echo $user_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                        <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i> 
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($resident): ?>
                <!-- ============================================================
                PRINT CONTAINER
                ============================================================ -->
                <div id="printContainer">
                    
                    <!-- ===== HEADER WITH ISU LOGO ===== -->
                    <div class="print-section">
                        <div class="print-header-logo">
                            <div class="logo-icon no-print">ISU</div>
                            <div class="flex-grow-1 text-center">
                                <div class="print-header">
                                    <div class="logo-text">
                                        <i class="fas fa-university me-2" style="color: var(--gold);"></i>
                                        ISABELA STATE UNIVERSITY
                                    </div>
                                    <div class="logo-sub">Echague, Isabela</div>
                                    <div class="logo-line"></div>
                                    <div style="font-size: 13px; color: var(--gold); font-weight: 600; letter-spacing: 2px;">
                                        STUDENT HOUSING SERVICES
                                    </div>
                                    <div style="font-size: 14px; font-weight: 700; color: var(--gold); margin-top: 5px;">
                                        ISU-ECHAGUE CAMPUS DORMITORY
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">
                                        Student Boarder's Data Profile
                                    </div>
                                </div>
                            </div>
                            <!-- Profile Photo -->
                            <div class="no-print" style="text-align:center;">
                                <div class="profile-avatar" style="width:100px; height:100px; font-size:40px;">
                                    <?php 
                                        $photoPath = $resident['profile_photo'] ?? '';
                                        $fullPath = '../../' . $photoPath;
                                        if (!empty($photoPath) && file_exists($fullPath)):
                                    ?>
                                        <img src="<?php echo $fullPath; ?>" alt="Profile Photo" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else:
                                        $nameParts = explode(' ', $resident['full_name'] ?? '');
                                        $initials = '';
                                        foreach ($nameParts as $part) {
                                            if (!empty($part)) {
                                                $initials .= strtoupper($part[0]);
                                            }
                                        }
                                        echo substr($initials, 0, 2) ?: '?';
                                    endif; 
                                    ?>
                                </div>
                                <div style="font-size:10px; color:var(--text-muted); margin-top:5px;">ID Photo</div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== BASIC INFORMATION ===== -->
                    <div class="print-section">
                        <h6><i class="fas fa-id-card me-2"></i>Basic Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Full Name</span>
                                    <span class="info-value"><?php echo displayVal($resident['full_name']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Student ID</span>
                                    <span class="info-value"><?php echo displayVal($resident['student_id']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Room Number</span>
                                    <span class="info-value"><?php echo displayVal($resident['room_number'], 'Not Assigned'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Contact Number</span>
                                    <span class="info-value"><?php echo displayVal($resident['contact_number']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Email</span>
                                    <span class="info-value"><?php echo displayVal($resident['email']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Status</span>
                                    <?php $statusClass = getStatusBadge($resident['status']); ?>
                                    <span class="badge-status badge-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst(displayVal($resident['status'])); ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Date Registered</span>
                                    <span class="info-value"><?php echo !empty($resident['created_at']) ? date('F d, Y', strtotime($resident['created_at'])) : 'N/A'; ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">RFID Card</span>
                                    <?php if (!empty($card['card_uid'])): ?>
                                        <span class="badge-status badge-active">
                                            <i class="fas fa-check-circle me-1"></i> Active
                                        </span>
                                        <span class="text-muted small">(<?php echo htmlspecialchars($card['card_uid']); ?>)</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-no-card">
                                            <i class="fas fa-times-circle me-1"></i> No Card Assigned
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== PERSONAL INFORMATION ===== -->
                    <div class="print-section">
                        <h6><i class="fas fa-user me-2"></i>Personal Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Gender</span>
                                    <span class="info-value"><?php echo getVal($profile, 'gender'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Birth Date</span>
                                    <span class="info-value"><?php echo getVal($profile, 'birth_date'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Age</span>
                                    <span class="info-value"><?php echo getVal($profile, 'age'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Religion</span>
                                    <span class="info-value"><?php echo getVal($profile, 'religion'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Course</span>
                                    <span class="info-value"><?php echo getVal($profile, 'course'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Year Level</span>
                                    <span class="info-value"><?php echo getVal($profile, 'year_level'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Dialect Spoken</span>
                                    <span class="info-value"><?php echo getVal($profile, 'dialect'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Civil Status</span>
                                    <span class="info-value"><?php echo getVal($profile, 'civil_status'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== EDUCATIONAL BACKGROUND ===== -->
                    <div class="print-section">
                        <h6><i class="fas fa-graduation-cap me-2"></i>Educational Background</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">School Last Attended</span>
                                    <span class="info-value"><?php echo getVal($profile, 'school_last'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">School Address</span>
                                    <span class="info-value"><?php echo getVal($profile, 'school_address'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Strand/Track (SHS)</span>
                                    <span class="info-value"><?php echo getVal($admission, 'strand_track'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Course Taken</span>
                                    <span class="info-value"><?php echo getVal($admission, 'course_taken'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== HOME ADDRESS ===== -->
                    <div class="print-section">
                        <h6><i class="fas fa-home me-2"></i>Home Address</h6>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="detail-row">
                                    <span class="info-label">Complete Home Address</span>
                                    <span class="info-value"><?php echo nl2br(getVal($profile, 'home_address')); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== PARENT/GUARDIAN INFORMATION ===== -->
                    <div class="print-section">
                        <h6><i class="fas fa-users me-2"></i>Parent / Guardian Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Father's Education</span>
                                    <span class="info-value"><?php echo getVal($profile, 'father_education'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Father's Occupation</span>
                                    <span class="info-value"><?php echo getVal($profile, 'father_occupation'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Mother's Education</span>
                                    <span class="info-value"><?php echo getVal($profile, 'mother_education'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Mother's Occupation</span>
                                    <span class="info-value"><?php echo getVal($profile, 'mother_occupation'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="detail-row">
                                    <span class="info-label">Parent's Marital Status</span>
                                    <span class="info-value"><?php echo getVal($profile, 'parents_marital_status'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== EMERGENCY CONTACT ===== -->
                    <div class="print-section">
                        <h6><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Name</span>
                                    <span class="info-value"><?php echo getVal($profile, 'emergency_name'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Relationship</span>
                                    <span class="info-value"><?php echo getVal($profile, 'emergency_relationship'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Address</span>
                                    <span class="info-value"><?php echo nl2br(getVal($profile, 'emergency_address')); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Contact No.</span>
                                    <span class="info-value"><?php echo getVal($profile, 'emergency_contact'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== ADMISSION INFORMATION ===== -->
                    <?php if ($admission): ?>
                    <div class="print-section">
                        <h6><i class="fas fa-clipboard-list me-2"></i>Admission Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Semester, SY</span>
                                    <span class="info-value"><?php echo getVal($admission, 'semester_sy'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Guardian Name</span>
                                    <span class="info-value"><?php echo getVal($admission, 'guardian_name'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Guardian Contact</span>
                                    <span class="info-value"><?php echo getVal($admission, 'guardian_contact'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Room Assignment</span>
                                    <span class="info-value"><?php echo getVal($admission, 'room_assignment', 'Not Assigned'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Admission Status</span>
                                    <?php $admStatus = getStatusBadge($admission['status'] ?? 'pending'); ?>
                                    <span class="badge-status badge-<?php echo $admStatus; ?>">
                                        <?php echo ucfirst(getVal($admission, 'status', 'Pending')); ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Student Signature</span>
                                    <span class="info-value"><?php echo getVal($admission, 'student_signature'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ===== FORMER BOARDING ===== -->
                    <div class="print-section">
                        <h6><i class="fas fa-building me-2"></i>Boarding Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Former Boarding House</span>
                                    <span class="info-value"><?php echo getVal($admission, 'former_bh', 'None'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-row">
                                    <span class="info-label">Former Address</span>
                                    <span class="info-value"><?php echo getVal($admission, 'former_address', 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOOTER ===== -->
                    <div class="print-footer">
                        <i class="fas fa-print me-1"></i> 
                        Printed on <?php echo date('F d, Y h:i A'); ?> 
                        <span class="mx-2">|</span>
                        ISUE-OSS-SDP-025 | Effectivity: 01/09/2013 | Revision: 0
                        <br>
                        <span style="font-size: 10px; color: var(--text-muted);">This is a system-generated document.</span>
                    </div>
                </div>
                <!-- ============================================================
                END PRINT CONTAINER
                ============================================================ -->
                
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // THEME TOGGLE
        // ============================================================
        function toggleTheme() {
            const html = document.documentElement;
            const icon = document.getElementById('themeIcon');
            
            if (html.getAttribute('data-theme') === 'light') {
                html.removeAttribute('data-theme');
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'dark');
            } else {
                html.setAttribute('data-theme', 'light');
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'light');
            }
        }

        // Load saved theme (default to dark)
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            const icon = document.getElementById('themeIcon');
            
            if (savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
                if (icon) icon.className = 'fas fa-sun';
            } else {
                document.documentElement.removeAttribute('data-theme');
                if (icon) icon.className = 'fas fa-moon';
            }
        });

        // Adjust action bar top position based on navbar height
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.querySelector('.navbar');
            const actionBar = document.querySelector('.action-bar');
            
            if (navbar && actionBar) {
                const navbarHeight = navbar.offsetHeight;
                document.documentElement.style.setProperty('--navbar-height', navbarHeight + 'px');
            }
        });

        // Update on resize
        window.addEventListener('resize', function() {
            const navbar = document.querySelector('.navbar');
            const actionBar = document.querySelector('.action-bar');
            
            if (navbar && actionBar) {
                const navbarHeight = navbar.offsetHeight;
                document.documentElement.style.setProperty('--navbar-height', navbarHeight + 'px');
            }
        });
    </script>
</body>
</html>