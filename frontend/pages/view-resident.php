<?php
/**
 * Tap-and-Go Doorlock - View Resident
 * FULL DARK MODE - With Fixed Action Bar
 * ✅ FIXED: Safety check para sa undefined $resident
 * ✅ FIXED: Fallback kung walang admission_records table
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
$resident = null;  // ✅ Initialize sa null para safe

try {
    $conn = getDBConnection();
    
    // ============================================================
    // ✅ CHECK IF admission_records TABLE EXISTS
    // ============================================================
    $hasAdmissionTable = false;
    $tableCheck = $conn->query("SHOW TABLES LIKE 'admission_records'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $hasAdmissionTable = true;
    }
    
    // ============================================================
    // BUILD QUERY BASED ON AVAILABLE TABLES
    // ============================================================
    if ($hasAdmissionTable) {
        $query = "
            SELECT 
                u.*,
                u.profile_photo,
                rp.*,
                c.card_uid,
                c.status as card_status,
                c.issued_date as card_issued_date,
                c.expiry_date as card_expiry_date,
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
    } else {
        // ✅ Fallback: Walang admission_records table
        $query = "
            SELECT 
                u.*,
                u.profile_photo,
                rp.*,
                c.card_uid,
                c.status as card_status,
                c.issued_date as card_issued_date,
                c.expiry_date as card_expiry_date,
                NULL as admission_status,
                NULL as semester_sy,
                NULL as guardian_name,
                NULL as guardian_contact,
                NULL as room_assignment,
                NULL as student_signature,
                NULL as strand_track,
                NULL as course_taken,
                NULL as former_bh
            FROM users u
            LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
            LEFT JOIN rfid_cards c ON u.user_id = c.user_id AND c.status = 'active'
            WHERE u.user_id = ? AND u.status != 'deleted'
        ";
    }
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        header('Location: residents.php');
        exit();
    }
    
    $resident = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $error = "Error loading resident: " . $e->getMessage();
}

// ============================================================
// ✅ SAFETY CHECK: Kung walang resident, mag-redirect
// ============================================================
if (!$resident) {
    header('Location: residents.php?error=not_found');
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

function formatDate($date, $format = 'F d, Y') {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'N/A';
    return date($format, $timestamp);
}

function formatDateTime($date, $format = 'F d, Y h:i A') {
    if (empty($date) || $date == '0000-00-00 00:00:00') return 'N/A';
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'N/A';
    return date($format, $timestamp);
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
        :root {
            --bg-primary: #0a0e17;
            --bg-card: #111927;
            --bg-card-hover: #1a2335;
            --text-primary: #e8edf5;
            --text-secondary: #8899bb;
            --text-muted: #4a5a7a;
            --border-color: #1a2a44;
            --gold: #ffd700;
            --gold-dark: #b8960f;
            --shadow: 0 4px 24px rgba(0,0,0,0.4);
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

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        .container-fluid, .row, main { background: var(--bg-primary) !important; }
        main { padding-top: 0 !important; }

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

        .action-bar .title-section { display: flex; align-items: center; gap: 10px; }
        .action-bar .title-section .h2 {
            color: var(--text-primary) !important;
            font-size: 20px; font-weight: 700; margin: 0;
        }
        .action-bar .title-section .h2 i { color: var(--gold) !important; }
        .action-bar .btn-group-custom { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }

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
        .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 6px; }

        .theme-toggle {
            background: var(--bg-card-hover) !important;
            border: 1px solid var(--border-color);
            color: var(--gold) !important;
            font-size: 15px; cursor: pointer; padding: 5px 10px;
            border-radius: 6px; transition: all 0.3s ease; line-height: 1.5;
        }
        .theme-toggle:hover {
            background: var(--gold-dark) !important;
            color: #0a0e17 !important;
            border-color: var(--gold-dark);
        }

        .profile-header {
            background: var(--bg-card) !important;
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
        }

        .profile-avatar {
            width: 120px; height: 120px; border-radius: 50%;
            background: linear-gradient(135deg, #0a1628, #0d1f3c);
            display: flex; align-items: center; justify-content: center;
            font-size: 48px; font-weight: 700; color: var(--gold);
            overflow: hidden; border: 4px solid var(--gold-dark);
            margin: 0 auto;
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .profile-name { text-align: center; margin-top: 15px; }
        .profile-name h3 { margin: 0; color: var(--text-primary) !important; font-weight: 700; }
        .profile-name .text-muted { color: var(--text-secondary) !important; font-size: 14px; }

        .info-card {
            background: var(--bg-card-hover) !important;
            border-radius: 12px;
            padding: 15px 20px;
            box-shadow: none; margin-bottom: 10px;
            border: 1px solid var(--border-color);
        }
        .info-card .label {
            font-size: 11px; color: var(--text-secondary) !important;
            font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .info-card .value {
            font-size: 14px; font-weight: 600;
            color: var(--text-primary) !important;
        }
        .info-card .value i { color: var(--gold); }

        .card {
            background: var(--bg-card) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 16px !important;
            box-shadow: var(--shadow);
        }
        .card-body {
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
        }

        .section-title {
            font-size: 16px; font-weight: 700;
            color: var(--gold) !important;
            border-bottom: 2px solid var(--gold-dark);
            padding-bottom: 10px; margin-bottom: 20px;
        }

        .data-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid rgba(26, 42, 68, 0.5);
            align-items: flex-start;
        }
        .data-row:last-child { border-bottom: none; }
        .data-row .data-label {
            flex: 0 0 40%;
            font-size: 12px;
            color: var(--text-secondary) !important;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding-right: 10px;
        }
        .data-row .data-value {
            flex: 1;
            font-size: 13px;
            color: var(--text-primary) !important;
            font-weight: 500;
            word-break: break-word;
        }
        .data-row .data-value.empty {
            color: var(--text-muted) !important;
            font-style: italic;
            font-weight: 400;
        }

        .badge-status {
            padding: 4px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 600;
            display: inline-block; letter-spacing: 0.3px;
        }
        .badge-active { background: var(--success-bg) !important; color: var(--success-text) !important; border: 1px solid rgba(110, 231, 183, 0.2); }
        .badge-pending { background: var(--warning-bg) !important; color: var(--warning-text) !important; border: 1px solid rgba(252, 211, 77, 0.2); }
        .badge-inactive { background: var(--secondary-bg) !important; color: var(--secondary-text) !important; border: 1px solid rgba(136, 153, 187, 0.2); }
        .badge-denied { background: var(--danger-bg) !important; color: var(--danger-text) !important; border: 1px solid rgba(252, 165, 165, 0.2); }
        .badge-completed { background: var(--info-bg) !important; color: var(--info-text) !important; border: 1px solid rgba(125, 211, 252, 0.2); }

        .alert-danger {
            background: var(--danger-bg) !important;
            color: var(--danger-text) !important;
            border-color: rgba(252, 165, 165, 0.2) !important;
        }
        .alert-danger .btn-close { filter: brightness(0.5) invert(1); }

        @media (max-width: 992px) {
            .action-bar { top: var(--navbar-height, 56px); padding: 8px 16px; min-height: 50px; }
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
            .action-bar .title-section { justify-content: center; }
            .action-bar .title-section .h2 { font-size: 16px; }
            .action-bar .btn-group-custom { justify-content: center; }
            .action-bar .btn-group-custom .btn { font-size: 11px; padding: 4px 8px; }
            .profile-header { padding: 20px; }
            .profile-avatar { width: 80px; height: 80px; font-size: 32px; }
            .profile-name h3 { font-size: 18px; }
            .info-card { padding: 12px 15px; }
            .info-card .value { font-size: 13px; }
            .data-row { flex-direction: column; padding: 6px 0; }
            .data-row .data-label { flex: none; width: 100%; margin-bottom: 3px; }
        }
        @media (max-width: 576px) {
            .action-bar { top: var(--navbar-height, 56px); padding: 6px 10px; margin: 0 -4px 12px -4px; }
            .action-bar .title-section .h2 { font-size: 14px; }
            .action-bar .btn-group-custom .btn { font-size: 10px; padding: 3px 6px; }
            .profile-header { padding: 15px; }
            .profile-avatar { width: 60px; height: 60px; font-size: 24px; }
            .profile-name h3 { font-size: 16px; }
            .info-card .label { font-size: 10px; }
            .info-card .value { font-size: 12px; }
            .section-title { font-size: 14px; }
            .data-row .data-label { font-size: 11px; }
            .data-row .data-value { font-size: 12px; }
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-primary); }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gold-dark); }

        .navbar {
            background: var(--bg-card) !important;
            border-bottom: 1px solid var(--border-color) !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1060 !important;
        }
        .navbar .navbar-brand, .navbar .nav-link { color: var(--text-primary) !important; }
        .navbar .nav-link:hover { color: var(--gold) !important; }

        .sidebar {
            background: var(--bg-card) !important;
            border-right: 1px solid var(--border-color) !important;
        }
        .sidebar .nav-link { color: var(--text-secondary) !important; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: var(--gold) !important;
            background: var(--bg-card-hover) !important;
        }

        .photo-full {
            width: 100%;
            max-width: 300px;
            border-radius: 12px;
            border: 3px solid var(--gold-dark);
        }

        @media print {
            .no-print { display: none !important; }
            .action-bar { display: none !important; }
            body * { visibility: hidden !important; }
            .profile-header, .profile-header *, .card, .card * { visibility: visible !important; }
            .profile-header {
                position: absolute !important;
                left: 0 !important; top: 0 !important;
                width: 100% !important;
                padding: 20px !important;
                background: #0a0e17 !important;
                border: 1px solid #1a2a44 !important;
                margin: 0 !important;
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
                
                <!-- ACTION BAR -->
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

                <!-- PROFILE HEADER -->
                <div class="profile-header">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <div class="profile-avatar">
                                <?php 
                                    $photoPath = $resident['profile_photo'] ?? '';
                                    $fullPath = '../../' . $photoPath;
                                    if (!empty($photoPath) && file_exists($fullPath)):
                                ?>
                                    <img src="<?php echo $fullPath; ?>" alt="Profile Photo" 
                                         style="cursor:pointer;" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#photoModal">
                                <?php else:
                                    $nameParts = explode(' ', $resident['full_name'] ?? '');
                                    $initials = '';
                                    foreach ($nameParts as $part) {
                                        if (!empty($part)) $initials .= strtoupper($part[0]);
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
                                <?php $statusClass = getStatusBadge($resident['status'] ?? 'pending'); ?>
                                <span class="badge-status badge-<?php echo $statusClass; ?> mt-2">
                                    <?php echo ucfirst(displayVal($resident['status'])); ?>
                                </span>
                                <?php if (!empty($resident['approval_status'])): ?>
                                    <br>
                                    <span class="badge-status badge-<?php echo getStatusBadge($resident['approval_status']); ?> mt-1">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        <?php echo ucfirst($resident['approval_status']); ?>
                                    </span>
                                <?php endif; ?>
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
                                        <div class="value"><?php echo formatDate($resident['birth_date'] ?? null); ?></div>
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

                <!-- PERSONAL INFORMATION -->
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="fas fa-user me-2"></i>Personal Information</h5>
                                
                                <div class="data-row">
                                    <div class="data-label">Full Name</div>
                                    <div class="data-value"><?php echo getVal($resident, 'full_name'); ?></div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Student ID</div>
                                    <div class="data-value"><?php echo getVal($resident, 'student_id'); ?></div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Gender</div>
                                    <div class="data-value"><?php echo getVal($resident, 'gender'); ?></div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Gender (Specify)</div>
                                    <div class="data-value <?php echo empty($resident['gender_other']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'gender_other'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Birth Date</div>
                                    <div class="data-value"><?php echo formatDate($resident['birth_date'] ?? null); ?></div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Age</div>
                                    <div class="data-value"><?php echo getVal($resident, 'age'); ?></div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Birth Certificate No.</div>
                                    <div class="data-value <?php echo empty($resident['birth_no']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'birth_no'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">No. of Siblings</div>
                                    <div class="data-value <?php echo empty($resident['no_siblings']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'no_siblings'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Civil Status</div>
                                    <div class="data-value <?php echo empty($resident['civil_status']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'civil_status'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Religion</div>
                                    <div class="data-value <?php echo empty($resident['religion']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'religion'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Dialect</div>
                                    <div class="data-value <?php echo empty($resident['dialect']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'dialect'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Cultural Origin</div>
                                    <div class="data-value <?php echo empty($resident['cultural_origin']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'cultural_origin'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="fas fa-address-book me-2"></i>Contact Information</h5>
                                
                                <div class="data-row">
                                    <div class="data-label">Contact Number (CP No.)</div>
                                    <div class="data-value <?php echo empty($resident['cp_no']) ? 'empty' : ''; ?>">
                                        <?php if (!empty($resident['cp_no'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($resident['cp_no']); ?>" 
                                               style="color: var(--gold); text-decoration: none;">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($resident['cp_no']); ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Email</div>
                                    <div class="data-value <?php echo empty($resident['email']) ? 'empty' : ''; ?>">
                                        <?php if (!empty($resident['email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($resident['email']); ?>" 
                                               style="color: var(--gold); text-decoration: none;">
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($resident['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Home Address</div>
                                    <div class="data-value <?php echo empty($resident['home_address']) ? 'empty' : ''; ?>">
                                        <?php echo nl2br(getVal($resident, 'home_address')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="section-title"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h5>
                                
                                <div class="data-row">
                                    <div class="data-label">Course</div>
                                    <div class="data-value"><?php echo getVal($resident, 'course'); ?></div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Year Level</div>
                                    <div class="data-value"><?php echo getVal($resident, 'year_level'); ?></div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Scholarship Grant</div>
                                    <div class="data-value <?php echo empty($resident['scholarship']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'scholarship'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Other Sources of Allowance</div>
                                    <div class="data-value <?php echo empty($resident['allowance_source']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'allowance_source'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">School Last Attended</div>
                                    <div class="data-value <?php echo empty($resident['school_last']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'school_last'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">School Address</div>
                                    <div class="data-value <?php echo empty($resident['school_address']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'school_address'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAMILY INFORMATION -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="section-title"><i class="fas fa-users me-2"></i>Family Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">Father's Education</div>
                                    <div class="data-value <?php echo empty($resident['father_education']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'father_education'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Father's Occupation</div>
                                    <div class="data-value <?php echo empty($resident['father_occupation']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'father_occupation'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">Mother's Education</div>
                                    <div class="data-value <?php echo empty($resident['mother_education']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'mother_education'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Mother's Occupation</div>
                                    <div class="data-value <?php echo empty($resident['mother_occupation']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'mother_occupation'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="data-row">
                                    <div class="data-label">Parent's Marital Status</div>
                                    <div class="data-value <?php echo empty($resident['parents_marital_status']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'parents_marital_status'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EMERGENCY CONTACT -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="section-title"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">Name</div>
                                    <div class="data-value <?php echo empty($resident['emergency_name']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'emergency_name'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Relationship</div>
                                    <div class="data-value <?php echo empty($resident['emergency_relationship']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'emergency_relationship'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">Address</div>
                                    <div class="data-value <?php echo empty($resident['emergency_address']) ? 'empty' : ''; ?>">
                                        <?php echo nl2br(getVal($resident, 'emergency_address')); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Contact No.</div>
                                    <div class="data-value <?php echo empty($resident['emergency_contact']) ? 'empty' : ''; ?>">
                                        <?php if (!empty($resident['emergency_contact'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($resident['emergency_contact']); ?>" 
                                               style="color: var(--gold); text-decoration: none;">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($resident['emergency_contact']); ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOARDING HISTORY -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="section-title"><i class="fas fa-home me-2"></i>Boarding History</h5>
                        
                        <div class="data-row">
                            <div class="data-label">Length of Stay in Former Boarding House</div>
                            <div class="data-value <?php echo empty($resident['former_boarding_years']) ? 'empty' : ''; ?>">
                                <?php echo getVal($resident, 'former_boarding_years'); ?>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="data-label">Plan to Transfer</div>
                            <div class="data-value <?php echo empty($resident['plan_transfer']) ? 'empty' : ''; ?>">
                                <?php if (!empty($resident['plan_transfer'])): ?>
                                    <span class="badge-status badge-<?php echo strtolower($resident['plan_transfer']) == 'yes' ? 'warning' : 'active'; ?>">
                                        <?php echo htmlspecialchars($resident['plan_transfer']); ?>
                                    </span>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($resident['plan_transfer_yes']) || (isset($resident['plan_transfer']) && $resident['plan_transfer'] == 'Yes')): ?>
                        <div class="data-row">
                            <div class="data-label">Reason (If Yes)</div>
                            <div class="data-value <?php echo empty($resident['plan_transfer_yes']) ? 'empty' : ''; ?>">
                                <?php echo getVal($resident, 'plan_transfer_yes'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($resident['plan_transfer_no']) || (isset($resident['plan_transfer']) && $resident['plan_transfer'] == 'No')): ?>
                        <div class="data-row">
                            <div class="data-label">Reason (If No)</div>
                            <div class="data-value <?php echo empty($resident['plan_transfer_no']) ? 'empty' : ''; ?>">
                                <?php echo getVal($resident, 'plan_transfer_no'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ADMISSION INFORMATION -->
                <?php if (!empty($resident['semester_sy']) || !empty($resident['guardian_name']) || !empty($resident['student_signature'])): ?>
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="section-title"><i class="fas fa-clipboard-list me-2"></i>Admission Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">Semester, SY</div>
                                    <div class="data-value <?php echo empty($resident['semester_sy']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'semester_sy'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Guardian Name</div>
                                    <div class="data-value <?php echo empty($resident['guardian_name']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'guardian_name'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Guardian Contact</div>
                                    <div class="data-value <?php echo empty($resident['guardian_contact']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'guardian_contact'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Room Assignment</div>
                                    <div class="data-value <?php echo empty($resident['room_assignment']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'room_assignment', 'Not Assigned'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">Strand/Track Taken</div>
                                    <div class="data-value <?php echo empty($resident['strand_track']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'strand_track'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Course Taken</div>
                                    <div class="data-value <?php echo empty($resident['course_taken']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'course_taken'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Former BH/Dorm</div>
                                    <div class="data-value <?php echo empty($resident['former_bh']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'former_bh'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Student Signature</div>
                                    <div class="data-value <?php echo empty($resident['student_signature']) ? 'empty' : ''; ?>">
                                        <?php echo getVal($resident, 'student_signature'); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Admission Status</div>
                                    <div class="data-value">
                                        <?php $admStatus = getStatusBadge($resident['admission_status'] ?? 'pending'); ?>
                                        <span class="badge-status badge-<?php echo $admStatus; ?>">
                                            <?php echo ucfirst(getVal($resident, 'admission_status', 'Pending')); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- RFID CARD INFORMATION -->
                <div class="card mt-3 mb-4">
                    <div class="card-body">
                        <h5 class="section-title"><i class="fas fa-id-card me-2"></i>RFID Card Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">Card UID</div>
                                    <div class="data-value">
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
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Card Status</div>
                                    <div class="data-value">
                                        <?php if (!empty($resident['card_status'])): ?>
                                            <span class="badge-status badge-<?php echo $resident['card_status'] == 'active' ? 'active' : 'inactive'; ?>">
                                                <?php echo ucfirst($resident['card_status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">N/A</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">Issued Date</div>
                                    <div class="data-value <?php echo empty($resident['card_issued_date']) ? 'empty' : ''; ?>">
                                        <?php echo formatDate($resident['card_issued_date'] ?? null); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Expiry Date</div>
                                    <div class="data-value <?php echo empty($resident['card_expiry_date']) ? 'empty' : ''; ?>">
                                        <?php echo formatDate($resident['card_expiry_date'] ?? null); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SYSTEM INFORMATION -->
                <div class="card mt-3 mb-4">
                    <div class="card-body">
                        <h5 class="section-title"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">Date Registered</div>
                                    <div class="data-value <?php echo empty($resident['date_registered']) ? 'empty' : ''; ?>">
                                        <?php echo formatDate($resident['date_registered'] ?? null); ?>
                                    </div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Account Created</div>
                                    <div class="data-value <?php echo empty($resident['created_at']) ? 'empty' : ''; ?>">
                                        <?php echo formatDateTime($resident['created_at'] ?? null); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-row">
                                    <div class="data-label">User ID</div>
                                    <div class="data-value">#<?php echo $resident['user_id'] ?? 'N/A'; ?></div>
                                </div>
                                <div class="data-row">
                                    <div class="data-label">Account Status</div>
                                    <div class="data-value">
                                        <span class="badge-status badge-<?php echo getStatusBadge($resident['status'] ?? 'pending'); ?>">
                                            <?php echo ucfirst(displayVal($resident['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- PHOTO MODAL -->
    <?php if (!empty($photoPath) && file_exists($fullPath)): ?>
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-card) !important; border: 2px solid var(--gold-dark);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" style="color: var(--gold);">
                        <i class="fas fa-camera me-2"></i>Profile Photo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" 
                            style="filter: invert(1) brightness(0.7);"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="<?php echo $fullPath; ?>" alt="Full Photo" class="photo-full">
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.querySelector('.navbar');
            const actionBar = document.querySelector('.action-bar');
            
            if (navbar && actionBar) {
                const navbarHeight = navbar.offsetHeight;
                document.documentElement.style.setProperty('--navbar-height', navbarHeight + 'px');
            }
        });

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
