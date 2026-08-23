<?php
/**
 * Tap-and-Go Doorlock - Edit Admission Record
 * FULL DARK MODE - WITH AUTO-FILL
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

// ============================================================
// FIX: HANDLE REDIRECTS BEFORE INCLUDE HEADER
// ============================================================
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    header('Location: residents.php');
    exit();
}

// ============================================================
// NOW INCLUDE HEADER
// ============================================================
include '../includes/header.php'; 

// ============================================================
// FETCH DATA
// ============================================================
$admission = null;
$resident = null;
$profile = null;
$error = '';
$success = '';

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
    
    // Get profile data
    $stmt = $conn->prepare("SELECT * FROM resident_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $error = 'Error loading data: ' . $e->getMessage();
}

if (!$resident || !$admission) {
    header('Location: residents.php');
    exit();
}

// ============================================================
// HANDLE UPDATE SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $semester_sy = trim($_POST['semester_sy'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $birth_date = trim($_POST['birth_date'] ?? '');
    $home_address = trim($_POST['home_address'] ?? '');
    $school_last = trim($_POST['school_last'] ?? '');
    $school_address = trim($_POST['school_address'] ?? '');
    $strand_track = trim($_POST['strand_track'] ?? '');
    $course_taken = trim($_POST['course_taken'] ?? '');
    $year_level_old = trim($_POST['year_level_old'] ?? '');
    $former_bh = trim($_POST['former_bh'] ?? '');
    $former_address = trim($_POST['former_address'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_contact = trim($_POST['guardian_contact'] ?? '');
    $student_signature = trim($_POST['student_signature'] ?? '');
    $room_assignment = trim($_POST['room_assignment'] ?? 'Not Assigned');
    $status = $_POST['status'] ?? 'pending';

    if (empty($semester_sy) || empty($guardian_name) || empty($guardian_contact)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $conn = getDBConnection();
            
            $stmt = $conn->prepare("
                UPDATE admission_records SET
                    semester_sy = ?, age = ?, birth_date = ?, home_address = ?,
                    school_last = ?, school_address = ?, strand_track = ?,
                    course_taken = ?, year_level_old = ?, former_bh = ?,
                    former_address = ?, guardian_name = ?, guardian_contact = ?,
                    student_signature = ?, room_assignment = ?, status = ?
                WHERE user_id = ?
            ");
            
            $stmt->bind_param(
                "sissssssssssssssi",
                $semester_sy,
                $age,
                $birth_date,
                $home_address,
                $school_last,
                $school_address,
                $strand_track,
                $course_taken,
                $year_level_old,
                $former_bh,
                $former_address,
                $guardian_name,
                $guardian_contact,
                $student_signature,
                $room_assignment,
                $status,
                $user_id
            );
            
            if ($stmt->execute()) {
                $success = "Admission record updated successfully!";
                
                // Refresh data
                $stmt = $conn->prepare("SELECT * FROM admission_records WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $admission = $result->fetch_assoc();
                $stmt->close(); // FIX: Only close once
            } else {
                $error = "Failed to update admission record: " . $stmt->error;
            }
            // FIX: REMOVED REDUNDANT $stmt->close() HERE
            
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function getVal($array, $key, $default = '') {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admission - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            color: #e5e7eb !important;
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
           FORM SECTION
           ============================================================ */
        .form-section {
            background: #131926 !important;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            border: 1px solid #1e2a3a;
        }
        
        .form-section h5 {
            color: #ffd700 !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 8px;
            margin-bottom: 16px;
            font-size: 15px;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 12px;
            color: #d1d5db !important;
        }
        
        .form-control,
        .form-select {
            background: #0d1220 !important;
            border: 1px solid #1e2a3a !important;
            color: #e5e7eb !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            height: 38px;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        
        .form-control::placeholder {
            color: #6b7280 !important;
        }
        
        .form-control[readonly] {
            background: #0a0e1a !important;
            color: #6b7280 !important;
            cursor: not-allowed;
        }
        
        .form-control:disabled,
        .form-select:disabled {
            background: #0a0e1a !important;
            color: #6b7280 !important;
            cursor: not-allowed;
        }
        
        .form-select option {
            background: #131926 !important;
            color: #e5e7eb !important;
        }
        
        .header-title {
            background: linear-gradient(135deg, #0a1628, #1a2a4a) !important;
            padding: 18px 25px;
            border-radius: 12px 12px 0 0;
            margin: -20px -25px 18px -25px;
            border-bottom: 1px solid #1e2a3a;
        }
        
        .header-title h4 {
            font-weight: 800;
            margin: 0;
            color: #ffd700 !important;
            font-size: 18px;
            letter-spacing: 1px;
        }
        
        .header-title p {
            margin: 0;
            opacity: 0.8;
            font-size: 12px;
            color: #9ca3af !important;
        }
        
        .header-title hr {
            border-color: rgba(255, 215, 0, 0.15);
            margin: 6px 0;
        }
        
        .header-title h5 {
            color: #ffd700 !important;
            margin-top: 6px;
            font-weight: 700;
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none;
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            color: #0a0e1a !important;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3) !important;
            color: #0a0e1a !important;
        }
        
        .btn-outline-secondary {
            color: #9ca3af !important;
            border-color: #1e2a3a !important;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 10px;
        }
        
        .btn-outline-secondary:hover {
            background: #1a1f2e !important;
            color: #e5e7eb !important;
        }
        
        .btn-outline-primary {
            color: #ffd700 !important;
            border-color: #ffd700 !important;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 10px;
        }
        
        .btn-outline-primary:hover {
            background: #ffd700 !important;
            color: #0a0e1a !important;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: #10b981 !important;
            color: #6ee7b7 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: #ef4444 !important;
            color: #fca5a5 !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        
        .btn-close {
            filter: invert(1) !important;
        }
        
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 {
            color: #e5e7eb !important;
        }
        
        .border-bottom {
            border-color: #1e2a3a !important;
        }
        
        .text-muted {
            color: #6b7280 !important;
        }
        
        .required {
            color: #ef4444 !important;
            margin-left: 2px;
        }
        
        /* ============================================================
           PAGE HEADER
           ============================================================ */
        .page-header {
            padding-bottom: 10px;
            margin-bottom: 15px;
            border-bottom: 1px solid #1e2a3a;
        }
        .page-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #e5e7eb;
        }
        .page-header h1 i {
            color: #ffd700;
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
            .header-title { padding: 12px 15px; margin: -15px -15px 15px -15px; }
            .header-title h4 { font-size: 15px; }
            .header-title h5 { font-size: 14px; }
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
            .header-title { 
                background: #1a3a6a !important; 
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .header-title h4 { color: #ffd700 !important; }
            body { background: #fff !important; color: #000 !important; }
            .form-control, .form-select { background: #fff !important; color: #000 !important; border-color: #ddd !important; }
            .form-label { color: #333 !important; }
            .form-control[readonly] { background: #f8f9fa !important; }
            .footer { display: none !important; }
            .navbar { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-wrapper">
        <div class="content-wrapper">
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- MAIN CONTENT -->
            <main class="main-content">
                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center no-print">
                    <h1><i class="fas fa-edit me-2"></i>Edit Admission Record</h1>
                    <div class="btn-toolbar">
                        <a href="view-admission.php?id=<?php echo $user_id; ?>" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to View
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
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

                <form method="POST" action="" id="editForm">
                    
                    <!-- ===== HEADER ===== -->
                    <div class="form-section">
                        <div class="header-title">
                            <h4><i class="fas fa-university me-2"></i>ISABELA STATE UNIVERSITY</h4>
                            <p>Echague, Isabela</p>
                            <hr>
                            <p style="font-size: 11px; letter-spacing: 0.5px; opacity: 0.8;">Office of Student Affairs &amp; Services</p>
                            <p style="font-size: 12px; font-weight: 600; color: #ffd700; margin-top: 4px;">ISU-ECHAGUE CAMPUS DORMITORY</p>
                            <h5 style="color: #ffd700; margin-top: 6px; font-weight: 700;">ADMISSION FORM</h5>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Semester, SY <span class="required">*</span></label>
                                        <input type="text" class="form-control" name="semester_sy" placeholder="e.g., 1st Semester, SY 2025-2026" value="<?php echo getVal($admission, 'semester_sy'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date (Auto-filled)</label>
                                        <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== PERSONAL INFORMATION ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label">NAME <span class="required">*</span></label>
                                <input type="text" class="form-control" value="<?php echo displayVal($resident['full_name']); ?>" readonly>
                                <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Read-only from resident data</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contact Number <span class="required">*</span></label>
                                <input type="text" class="form-control" value="<?php echo displayVal($resident['contact_number']); ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Course <span class="required">*</span></label>
                                <input type="text" class="form-control" value="<?php echo getVal($profile, 'course'); ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year Level <span class="required">*</span></label>
                                <input type="text" class="form-control" value="<?php echo getVal($profile, 'year_level'); ?>" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Age</label>
                                <input type="number" class="form-control" name="age" min="1" max="99" value="<?php echo getVal($admission, 'age', getVal($profile, 'age')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Birth Date</label>
                                <input type="date" class="form-control" name="birth_date" value="<?php echo getVal($admission, 'birth_date', getVal($profile, 'birth_date')); ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Complete Home Address <span class="required">*</span></label>
                                <input type="text" class="form-control" name="home_address" placeholder="House number, Street, Barangay, Municipality, Province" value="<?php echo getVal($admission, 'home_address'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- ===== EDUCATIONAL BACKGROUND ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-graduation-cap me-2"></i>Educational Background</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">School Last Attended</label>
                                <input type="text" class="form-control" name="school_last" placeholder="School name" value="<?php echo getVal($admission, 'school_last'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">School Address</label>
                                <input type="text" class="form-control" name="school_address" placeholder="School address" value="<?php echo getVal($admission, 'school_address'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOR FIRST-YEAR STUDENTS ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-user-graduate me-2"></i>For First-Year Students</h5>
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Strand/Track Taken</label>
                                <input type="text" class="form-control" name="strand_track" placeholder="e.g., STEM, ABM, HUMSS, TVL" value="<?php echo getVal($admission, 'strand_track'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOR HIGHER YEAR ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-user-graduate me-2"></i>For Higher Year</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Course Taken</label>
                                <input type="text" class="form-control" name="course_taken" placeholder="Course name" value="<?php echo getVal($admission, 'course_taken'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Year Level</label>
                                <select class="form-select" name="year_level_old">
                                    <option value="">Select</option>
                                    <option value="1st Year" <?php echo (getVal($admission, 'year_level_old') == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo (getVal($admission, 'year_level_old') == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo (getVal($admission, 'year_level_old') == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo (getVal($admission, 'year_level_old') == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOR OLD STUDENTS ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-home me-2"></i>For Old Students</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Name of BH/Dorm You Came From (if any)</label>
                                <input type="text" class="form-control" name="former_bh" placeholder="Boarding house or dorm name" value="<?php echo getVal($admission, 'former_bh'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address Area (if any)</label>
                                <input type="text" class="form-control" name="former_address" placeholder="Address of former boarding house" value="<?php echo getVal($admission, 'former_address'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ===== PARENT/GUARDIAN ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-users me-2"></i>Parent or Guardian</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Parent or Guardian's Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="guardian_name" placeholder="Full name of parent/guardian" value="<?php echo getVal($admission, 'guardian_name'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number <span class="required">*</span></label>
                                <input type="text" class="form-control" name="guardian_contact" placeholder="09XXXXXXXXX" value="<?php echo getVal($admission, 'guardian_contact'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- ===== ROOM & STATUS ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-door-open me-2"></i>Room & Status</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Room Assignment</label>
                                <input type="text" class="form-control" name="room_assignment" placeholder="e.g., Room 1" value="<?php echo getVal($admission, 'room_assignment', 'Not Assigned'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="pending" <?php echo (getVal($admission, 'status') == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="active" <?php echo (getVal($admission, 'status') == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (getVal($admission, 'status') == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SIGNATURE ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-pen me-2"></i>Student's Name and Signature</h5>
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Student's Name and Signature <span class="required">*</span></label>
                                <input type="text" class="form-control" name="student_signature" placeholder="Print your full name (signature)" value="<?php echo getVal($admission, 'student_signature'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SUBMIT ===== -->
                    <div class="text-center mb-2 no-print">
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Admission Record
                        </button>
                        <a href="view-admission.php?id=<?php echo $user_id; ?>" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                    </div>
                </form>
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
        // Auto-focus on first field
        document.querySelector('input[name="semester_sy"]').focus();

        // ============================================================
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
