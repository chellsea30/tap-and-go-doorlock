<?php
/**
 * Tap-and-Go Doorlock - View Resident
 * FULL DARK MODE - With Fixed Action Bar
 */

session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

include '../includes/header.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: residents.php');
    exit();
}

$error = '';

try {
    $conn = getDBConnection();
    
    $query = "
        SELECT 
            u.*,
            u.profile_photo,
            rp.*,
            c.card_uid,
            c.status as card_status,
            ar.status as admission_status,
            ar.semester_sy,
            ar.guardian_name,
            ar.guardian_contact,
            ar.room_assignment,
            ar.student_signature,
            ar.strand_track,
            ar.course_taken,
            ar.former_bh
        FROM users u
        LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
        LEFT JOIN rfid_cards c ON u.user_id = c.user_id AND c.status = 'active'
        LEFT JOIN admission_records ar ON u.user_id = ar.user_id
        WHERE u.user_id = ? AND u.status != 'deleted'
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        header('Location: residents.php');
        exit();
    }
    
    $resident = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $error = "Error loading resident: " . $e->getMessage();
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
        'completed' => 'completed',
        'deleted' => 'inactive'
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
           PROFILE HEADER - DARK MODE
           ============================================================ */
        .profile-header {
            background: var(--bg-card) !important;
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        .profile-header:hover {
            box-shadow: var(--shadow-hover);
        }

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

        .profile-name {
            text-align: center;
            margin-top: 15px;
        }

        .profile-name h3 {
            margin: 0;
            color: var(--text-primary) !important;
            font-weight: 700;
        }

        .profile-name .text-muted {
            color: var(--text-secondary) !important;
            font-size: 14px;
        }

        /* ============================================================
           INFO CARDS - DARK MODE
           ============================================================ */
        .info-card {
            background: var(--bg-card-hover) !important;
            border-radius: 12px;
            padding: 15px 20px;
            box-shadow: none;
            margin-bottom: 10px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            border-color: var(--gold-dark);
        }

        .info-card .label {
            font-size: 11px;
            color: var(--text-secondary) !important;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-card .value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary) !important;
        }

        .info-card .value i {
            color: var(--gold);
        }

        /* ============================================================
           CARDS - DARK MODE
           ============================================================ */
        .card {
            background: var(--bg-card) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 16px !important;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
        }

        .card-body {
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--gold) !important;
            border-bottom: 2px solid var(--gold-dark);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .card .row .col-6 strong {
            color: var(--text-secondary) !important;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card .row .col-6 {
            color: var(--text-primary) !important;
            font-size: 13px;
            padding: 4px 0;
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
            
            .profile-header {
                padding: 20px;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            
            .profile-name h3 {
                font-size: 18px;
            }
            
            .info-card {
                padding: 12px 15px;
            }
            
            .info-card .value {
                font-size: 13px;
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
            
            .profile-header {
                padding: 15px;
            }
            
            .profile-avatar {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }
            
            .profile-name h3 {
                font-size: 16px;
            }
            
            .info-card .label {
                font-size: 10px;
            }
            
            .info-card .value {
                font-size: 12px;
            }
            
            .section-title {
                font-size: 14px;
            }
            
            .card .row .col-6 {
                font-size: 11px;
            }
            
            .card .row .col-6 strong {
                font-size: 10px;
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
            
            .profile-header, 
            .profile-header *,
            .card, 
            .card * {
                visibility: visible !important;
            }
            
            .profile-header {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                padding: 20px !important;
                background: #0a0e17 !important;
                border: 1px solid #1a2a44 !important;
                margin: 0 !important;
            }
            
            .card {
                background: #0a0e17 !important;
                border: 1px solid #1a2a44 !important;
            }
            
            .card-body {
                background: #0a0e17 !important;
            }
            
            .profile-avatar {
                border: 2px solid var(--gold-dark) !important;
            }
            
            .info-card {
                background: #111927 !important;
                border: 1px solid #1a2a44 !important;
            }
            
            .badge-status {
                border: 1px solid var(--gold-dark) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .section-title {
                color: var(--gold) !important;
                border-bottom-color: var(--gold-dark) !important;
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
                        <a href="edit-resident.php?id=<?php echo $resident['user_id']; ?>" class="btn btn-primary btn-sm">
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
                PROFILE HEADER
                ============================================================ -->
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <div class="profile-avatar">
                                <?php 
                                    $photoPath = $resident['profile_photo'] ?? '';
                                    $fullPath = '../../' . $photoPath;
                                    if (!empty($photoPath) && file_exists($fullPath)):
                                ?>
                                    <img src="<?php echo $fullPath; ?>" alt="Profile Photo">
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
                            <div class="profile-name">
                                <h3><?php echo displayVal($resident['full_name']); ?></h3>
                                <span class="text-muted">
                                    <i class="fas fa-id-card me-1"></i>
                                    <?php echo displayVal($resident['student_id']); ?>
                                </span>
                                <br>
                                <?php 
                                    $statusClass = getStatusBadge($resident['status'] ?? 'pending');
                                ?>
                                <span class="badge-status badge-<?php echo $statusClass; ?> mt-2">
                                    <?php echo ucfirst(displayVal($resident['status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <div class="label"><i class="fas fa-graduation-cap me-1"></i> Course</div>
                                        <div class="value"><?php echo getVal($resident, 'course'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <div class="label"><i class="fas fa-layer-group me-1"></i> Year Level</div>
                                        <div class="value"><?php echo getVal($resident, 'year_level'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <div class="label"><i class="fas fa-door-open me-1"></i> Room</div>
                                        <div class="value"><?php echo displayVal($resident['room_number'], 'Not Assigned'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <div class="label"><i class="fas fa-venus-mars me-1"></i> Gender</div>
                                        <div class="value"><?php echo getVal($resident, 'gender'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <div class="label"><i class="fas fa-calendar-alt me-1"></i> Birth Date</div>
                                        <div class="value"><?php echo getVal($resident, 'birth_date'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <div class="label"><i class="fas fa-clock me-1"></i> Age</div>
                                        <div class="value"><?php echo getVal($resident, 'age'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                MORE INFORMATION - DARK CARDS
                ============================================================ -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="fas fa-info-circle me-2"></i>Personal Information</h5>
                                <div class="row g-2">
                                    <div class="col-6"><strong>Religion:</strong></div>
                                    <div class="col-6"><?php echo getVal($resident, 'religion'); ?></div>
                                    <div class="col-6"><strong>Dialect:</strong></div>
                                    <div class="col-6"><?php echo getVal($resident, 'dialect'); ?></div>
                                    <div class="col-6"><strong>Civil Status:</strong></div>
                                    <div class="col-6"><?php echo getVal($resident, 'civil_status'); ?></div>
                                    <div class="col-6"><strong>Contact No.:</strong></div>
                                    <div class="col-6"><?php echo getVal($resident, 'cp_no'); ?></div>
                                    <div class="col-6"><strong>Home Address:</strong></div>
                                    <div class="col-6"><?php echo nl2br(getVal($resident, 'home_address')); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="fas fa-users me-2"></i>Family Information</h5>
                                <div class="row g-2">
                                    <div class="col-6"><strong>Father's Education:</strong></div>
                                    <div class="col-6"><?php echo getVal($resident, 'father_education'); ?></div>
                                    <div class="col-6"><strong>Mother's Education:</strong></div>
                                    <div class="col-6"><?php echo getVal($resident, 'mother_education'); ?></div>
                                    <div class="col-6"><strong>Father's Occupation:</strong></div>
                                    <div class="col-6"><?php echo getVal($resident, 'father_occupation'); ?></div>
                                    <div class="col-6"><strong>Mother's Occupation:</strong></div>
                                    <div class="col-6"><?php echo getVal($resident, 'mother_occupation'); ?></div>
                                    <div class="col-6"><strong>Parents Status:</strong></div>
                                    <div class="col-6"><?php echo getVal($resident, 'parents_marital_status'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                EMERGENCY CONTACT - DARK CARD
                ============================================================ -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="section-title"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                        <div class="row g-2">
                            <div class="col-md-3"><strong>Name:</strong></div>
                            <div class="col-md-3"><?php echo getVal($resident, 'emergency_name'); ?></div>
                            <div class="col-md-3"><strong>Relationship:</strong></div>
                            <div class="col-md-3"><?php echo getVal($resident, 'emergency_relationship'); ?></div>
                            <div class="col-md-3"><strong>Address:</strong></div>
                            <div class="col-md-3"><?php echo nl2br(getVal($resident, 'emergency_address')); ?></div>
                            <div class="col-md-3"><strong>Contact No.:</strong></div>
                            <div class="col-md-3"><?php echo getVal($resident, 'emergency_contact'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                ADMISSION INFO - DARK CARD
                ============================================================ -->
                <?php if (!empty($resident['semester_sy']) || !empty($resident['guardian_name'])): ?>
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="section-title"><i class="fas fa-clipboard-list me-2"></i>Admission Information</h5>
                        <div class="row g-2">
                            <div class="col-md-3"><strong>Semester, SY:</strong></div>
                            <div class="col-md-3"><?php echo getVal($resident, 'semester_sy'); ?></div>
                            <div class="col-md-3"><strong>Guardian:</strong></div>
                            <div class="col-md-3"><?php echo getVal($resident, 'guardian_name'); ?></div>
                            <div class="col-md-3"><strong>Guardian Contact:</strong></div>
                            <div class="col-md-3"><?php echo getVal($resident, 'guardian_contact'); ?></div>
                            <div class="col-md-3"><strong>Room Assignment:</strong></div>
                            <div class="col-md-3"><?php echo getVal($resident, 'room_assignment', 'Not Assigned'); ?></div>
                            <div class="col-md-3"><strong>Student Signature:</strong></div>
                            <div class="col-md-3"><?php echo getVal($resident, 'student_signature'); ?></div>
                            <div class="col-md-3"><strong>Admission Status:</strong></div>
                            <div class="col-md-3">
                                <?php 
                                    $admStatus = getStatusBadge($resident['admission_status'] ?? 'pending');
                                ?>
                                <span class="badge-status badge-<?php echo $admStatus; ?>">
                                    <?php echo ucfirst(getVal($resident, 'admission_status', 'Pending')); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ============================================================
                RFID CARD INFO - DARK CARD
                ============================================================ -->
                <div class="card mt-3 mb-4">
                    <div class="card-body">
                        <h5 class="section-title"><i class="fas fa-id-card me-2"></i>RFID Card Information</h5>
                        <div class="row g-2">
                            <div class="col-md-3"><strong>Card UID:</strong></div>
                            <div class="col-md-3">
                                <?php if (!empty($resident['card_uid'])): ?>
                                    <span class="badge-status badge-active">
                                        <i class="fas fa-check-circle me-1"></i> 
                                        <?php echo htmlspecialchars($resident['card_uid']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status badge-inactive">
                                        <i class="fas fa-times-circle me-1"></i> No Card Assigned
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3"><strong>Card Status:</strong></div>
                            <div class="col-md-3">
                                <?php if (!empty($resident['card_status'])): ?>
                                    <span class="badge-status badge-<?php echo $resident['card_status'] == 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo ucfirst($resident['card_status']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </div>
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