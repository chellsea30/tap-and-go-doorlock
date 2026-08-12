<?php
/**
 * Tap-and-Go Doorlock - View Admission Record
 * FULL DARK MODE - Fixed Action Bar Below Navbar
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

// Include header
include '../includes/header.php'; 

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$admission = null;
$resident = null;
$profile = null;
$error = '';

if ($user_id > 0) {
    try {
        $conn = getDBConnection();
        
        // Get resident info
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND status != 'deleted'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $resident = $result->fetch_assoc();
        $stmt->close();
        
        // Get admission record
        $stmt = $conn->prepare("SELECT * FROM admission_records WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $admission = $result->fetch_assoc();
        $stmt->close();
        
        // Get profile data for additional info
        $stmt = $conn->prepare("SELECT * FROM resident_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $stmt->close();
        
    } catch (Exception $e) {
        $error = 'Error loading data: ' . $e->getMessage();
    }
}

if (!$resident || !$admission) {
    header('Location: residents.php');
    exit();
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
        'active' => 'success',
        'approved' => 'success',
        'pending' => 'warning',
        'inactive' => 'secondary',
        'denied' => 'danger',
        'completed' => 'info'
    ];
    $color = $colors[$status] ?? 'secondary';
    return $color;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Admission - Tap-and-Go Doorlock</title>
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
           GENERAL STYLES - NO WHITE BACKGROUND
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
           ADMISSION SECTION - FULL DARK
           ============================================================ */
        .admission-section {
            background: var(--bg-card) !important;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            margin-top: 5px;
        }

        .admission-section:hover {
            box-shadow: var(--shadow-hover);
        }

        .admission-header {
            background: var(--bg-header) !important;
            color: white;
            padding: 25px 30px;
            border-radius: 16px 16px 0 0;
            border-bottom: 2px solid var(--gold-dark);
            position: relative;
            overflow: hidden;
        }

        .admission-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,215,0,0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .admission-header h4 {
            color: var(--gold) !important;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: 1px;
            position: relative;
            z-index: 1;
        }

        .admission-header h5 {
            color: var(--gold) !important;
            font-weight: 700;
            font-size: 17px;
            position: relative;
            z-index: 1;
        }

        .admission-header h6 {
            color: var(--gold) !important;
            font-weight: 600;
            font-size: 15px;
            position: relative;
            z-index: 1;
        }

        .admission-header p {
            color: rgba(255,255,255,0.7) !important;
            font-size: 13px;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .admission-header hr {
            border-color: rgba(255,215,0,0.15);
            margin: 10px 0;
            position: relative;
            z-index: 1;
        }

        .admission-body {
            padding: 25px 30px;
            background: var(--bg-card) !important;
        }

        .admission-body h6 {
            color: var(--gold) !important;
            font-weight: 700;
            border-bottom: 2px solid var(--gold-dark);
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 15px;
            letter-spacing: 0.5px;
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

        .badge-active,
        .badge-approved {
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
           FOOTER - DARK MODE
           ============================================================ */
        .text-muted {
            color: var(--text-muted) !important;
        }

        .text-muted small {
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
            
            .admission-header { 
                background: #0a1628 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .admission-section {
                box-shadow: none !important;
                border: 1px solid #1a2a44 !important;
                background: #0a0e17 !important;
            }
            
            .admission-body {
                background: #0a0e17 !important;
            }
            
            body, html {
                background: #0a0e17 !important;
            }
            
            .detail-row {
                border-bottom: 1px solid #1a2a44 !important;
            }
            
            .info-label {
                color: #8899bb !important;
            }
            
            .info-value {
                color: #e8edf5 !important;
            }
            
            .badge-status {
                border: 1px solid #4a5a7a !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .badge-active, .badge-approved {
                background: #0d3b2e !important;
                color: #6ee7b7 !important;
            }
            
            .badge-pending {
                background: #3d2e0a !important;
                color: #fcd34d !important;
            }
            
            .badge-inactive {
                background: #1a2335 !important;
                color: #8899bb !important;
            }
            
            .badge-denied {
                background: #3d0a0a !important;
                color: #fca5a5 !important;
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
            
            .admission-header {
                padding: 18px 20px;
            }
            .admission-body {
                padding: 18px 20px;
            }
            .admission-header h4 {
                font-size: 17px;
            }
            .admission-header h5 {
                font-size: 14px;
            }
            .admission-header h6 {
                font-size: 12px;
            }
            .detail-row {
                padding: 5px 0;
            }
            .info-value {
                font-size: 12px;
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
            
            .admission-header {
                padding: 12px 14px;
            }
            .admission-body {
                padding: 12px 14px;
            }
            .admission-header h4 {
                font-size: 15px;
            }
            .admission-header h5 {
                font-size: 13px;
            }
            .admission-header h6 {
                font-size: 11px;
            }
            .admission-header p {
                font-size: 11px;
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

        hr {
            border-color: var(--border-color) !important;
            opacity: 0.5;
        }

        .admission-body .mt-4.pt-3 {
            border-top-color: var(--border-color) !important;
        }

        /* Navbar overrides */
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
                FIXED ACTION BAR - BELOW NAVBAR (top: 60px)
                ============================================================ -->
                <div class="action-bar no-print">
                    <div class="title-section">
                        <h1 class="h2">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Admission Record
                        </h1>
                    </div>
                    <div class="btn-group-custom">
                        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                            <i class="fas fa-moon" id="themeIcon"></i>
                        </button>
                        <a href="residents.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <a href="edit-admission.php?id=<?php echo $user_id; ?>" class="btn btn-primary btn-sm">
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

                <!-- ============================================================
                ADMISSION RECORD - FULL DARK
                ============================================================ -->
                <div class="admission-section" id="printContainer">
                    <!-- Header -->
                    <div class="admission-header">
                        <h4><i class="fas fa-university me-2"></i>ISABELA STATE UNIVERSITY</h4>
                        <p>Echague, Isabela</p>
                        <hr>
                        <p style="font-size: 12px;">Office of Student Affairs &amp; Services</p>
                        <h5>ISU-ECHAGUE CAMPUS DORMITORY</h5>
                        <h6 style="margin-top: 5px;">ADMISSION FORM</h6>
                    </div>

                    <!-- Body -->
                    <div class="admission-body">
                        <!-- Student Info -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-user-graduate me-2"></i>Student Information</h6>
                                <div class="detail-row">
                                    <span class="info-label">Name</span>
                                    <span class="info-value"><?php echo displayVal($resident['full_name']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Student ID</span>
                                    <span class="info-value"><?php echo displayVal($resident['student_id']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Course</span>
                                    <span class="info-value"><?php echo getVal($profile, 'course', getVal($admission, 'course')); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Year Level</span>
                                    <span class="info-value"><?php echo getVal($profile, 'year_level', getVal($admission, 'year_level')); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Age</span>
                                    <span class="info-value"><?php echo getVal($profile, 'age', getVal($admission, 'age')); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Birth Date</span>
                                    <span class="info-value"><?php echo getVal($profile, 'birth_date', getVal($admission, 'birth_date')); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Contact Number</span>
                                    <span class="info-value"><?php echo displayVal($resident['contact_number']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-info-circle me-2"></i>Admission Details</h6>
                                <div class="detail-row">
                                    <span class="info-label">Semester, SY</span>
                                    <span class="info-value"><?php echo getVal($admission, 'semester_sy'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Complete Home Address</span>
                                    <span class="info-value"><?php echo nl2br(getVal($admission, 'home_address')); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">School Last Attended</span>
                                    <span class="info-value"><?php echo getVal($admission, 'school_last'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">School Address</span>
                                    <span class="info-value"><?php echo getVal($admission, 'school_address'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Strand/Track (SHS)</span>
                                    <span class="info-value"><?php echo getVal($admission, 'strand_track'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Course Taken</span>
                                    <span class="info-value"><?php echo getVal($admission, 'course_taken'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Former Boarding House</span>
                                    <span class="info-value"><?php echo getVal($admission, 'former_bh', 'None'); ?></span>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Guardian & Room Info -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-users me-2"></i>Guardian Information</h6>
                                <div class="detail-row">
                                    <span class="info-label">Parent/Guardian's Name</span>
                                    <span class="info-value"><?php echo getVal($admission, 'guardian_name'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Guardian Contact Number</span>
                                    <span class="info-value"><?php echo getVal($admission, 'guardian_contact'); ?></span>
                                </div>
                                <?php if (!empty($profile['emergency_name']) && $profile['emergency_name'] !== 'N/A'): ?>
                                <div class="detail-row">
                                    <span class="info-label">Emergency Contact</span>
                                    <span class="info-value"><?php echo displayVal($profile['emergency_name']); ?> 
                                        (<?php echo displayVal($profile['emergency_relationship']); ?>)
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Emergency Contact No.</span>
                                    <span class="info-value"><?php echo displayVal($profile['emergency_contact']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-door-open me-2"></i>Room & Status</h6>
                                <div class="detail-row">
                                    <span class="info-label">Room Assignment</span>
                                    <span class="info-value"><?php echo getVal($admission, 'room_assignment', 'Not Assigned'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Admission Status</span>
                                    <?php 
                                        $status = $admission['status'] ?? 'pending';
                                        $badgeClass = getStatusBadge($status);
                                    ?>
                                    <span class="badge-status badge-<?php echo $badgeClass; ?>">
                                        <i class="fas fa-<?php echo $status == 'active' ? 'check-circle' : ($status == 'pending' ? 'clock' : 'times-circle'); ?> me-1"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Student's Signature</span>
                                    <span class="info-value"><?php echo getVal($admission, 'student_signature'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="info-label">Date Applied</span>
                                    <span class="info-value"><?php echo !empty($admission['created_at']) ? date('F d, Y', strtotime($admission['created_at'])) : 'N/A'; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="text-muted small mt-4 pt-3" style="border-top: 1px solid var(--border-color);">
                            <i class="fas fa-info-circle me-1"></i>
                            ISUE-OSAS-DAF-III | Effective July 18, 2026
                            <span class="float-end">
                                <i class="fas fa-print me-1"></i> 
                                Printed: <?php echo date('F d, Y h:i A'); ?>
                            </span>
                        </div>
                    </div>
                </div>
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