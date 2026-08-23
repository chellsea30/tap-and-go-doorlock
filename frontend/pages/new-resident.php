<?php
/**
 * Tap-and-Go Doorlock - New Resident Registration
 * DARK MODE - NO PHOTO - NO ROOM ASSIGNMENT
 * WITH FIXED NAVBAR, SIDEBAR, AND FOOTER
 * AUTO UPPERCASE FOR ALL TEXT FIELDS
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
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Get form data and convert to UPPERCASE
    $full_name = strtoupper(trim($_POST['full_name'] ?? ''));
    $course = strtoupper(trim($_POST['course'] ?? ''));
    $year_level = trim($_POST['year_level'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $gender_other = strtoupper(trim($_POST['gender_other'] ?? ''));
    $birth_date = $_POST['birth_date'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $birth_no = strtoupper(trim($_POST['birth_no'] ?? ''));
    $no_siblings = strtoupper(trim($_POST['no_siblings'] ?? ''));
    $scholarship = strtoupper(trim($_POST['scholarship'] ?? ''));
    $allowance_source = strtoupper(trim($_POST['allowance_source'] ?? ''));
    $school_last = strtoupper(trim($_POST['school_last'] ?? ''));
    $school_address = strtoupper(trim($_POST['school_address'] ?? ''));
    $cultural_origin = strtoupper(trim($_POST['cultural_origin'] ?? ''));
    $religion = strtoupper(trim($_POST['religion'] ?? ''));
    $dialect = strtoupper(trim($_POST['dialect'] ?? ''));
    $cp_no = strtoupper(trim($_POST['cp_no'] ?? ''));
    $home_address = strtoupper(trim($_POST['home_address'] ?? ''));
    $civil_status = $_POST['civil_status'] ?? '';
    $father_education = strtoupper(trim($_POST['father_education'] ?? ''));
    $mother_education = strtoupper(trim($_POST['mother_education'] ?? ''));
    $father_occupation = strtoupper(trim($_POST['father_occupation'] ?? ''));
    $mother_occupation = strtoupper(trim($_POST['mother_occupation'] ?? ''));
    $emergency_name = strtoupper(trim($_POST['emergency_name'] ?? ''));
    $emergency_relationship = strtoupper(trim($_POST['emergency_relationship'] ?? ''));
    $emergency_address = strtoupper(trim($_POST['emergency_address'] ?? ''));
    $emergency_contact = strtoupper(trim($_POST['emergency_contact'] ?? ''));
    $parents_marital_status = $_POST['parents_marital_status'] ?? '';
    $former_boarding_years = strtoupper(trim($_POST['former_boarding_years'] ?? ''));
    $plan_transfer = $_POST['plan_transfer'] ?? '';
    $plan_transfer_yes = strtoupper(trim($_POST['plan_transfer_yes'] ?? ''));
    $plan_transfer_no = strtoupper(trim($_POST['plan_transfer_no'] ?? ''));
    $date_registered = $_POST['date'] ?? date('Y-m-d');
    
    // Validate required fields
    if (empty($full_name) || empty($course) || empty($year_level)) {
        $error = 'Please fill in all required fields (Name, Course, Year Level).';
    }
    
    if (empty($error)) {
        try {
            $conn = getDBConnection();
            
            // Generate student ID
            $student_id = 'STU-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if student ID already exists
            $check = $conn->prepare("SELECT student_id FROM users WHERE student_id = ?");
            $check->bind_param("s", $student_id);
            $check->execute();
            $checkResult = $check->get_result();
            
            if ($checkResult->num_rows > 0) {
                $student_id = 'STU-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            }
            
            $status = 'active';
            $contact = $cp_no;
            $email = strtolower(str_replace(' ', '.', $full_name)) . '@isu.edu.ph';
            
            // ================================================================
            // INSERT INTO USERS TABLE
            // ================================================================
            $stmt = $conn->prepare("
                INSERT INTO users (
                    full_name, 
                    student_id, 
                    contact_number, 
                    email, 
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param("sssss", 
                $full_name, 
                $student_id, 
                $contact, 
                $email, 
                $status
            );
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // ================================================================
                // INSERT INTO RESIDENT_PROFILES TABLE
                // ================================================================
                $columns = [];
                $colResult = $conn->query("SHOW COLUMNS FROM resident_profiles");
                if ($colResult) {
                    while ($col = $colResult->fetch_assoc()) {
                        $columns[] = $col['Field'];
                    }
                }
                
                $fieldMap = [
                    'user_id' => $user_id,
                    'date_registered' => $date_registered,
                    'gender' => $gender,
                    'gender_other' => $gender_other,
                    'birth_date' => $birth_date,
                    'age' => $age,
                    'birth_no' => $birth_no,
                    'course' => $course,
                    'year_level' => $year_level,
                    'no_siblings' => $no_siblings,
                    'scholarship' => $scholarship,
                    'allowance_source' => $allowance_source,
                    'school_last' => $school_last,
                    'school_address' => $school_address,
                    'cultural_origin' => $cultural_origin,
                    'religion' => $religion,
                    'dialect' => $dialect,
                    'home_address' => $home_address,
                    'civil_status' => $civil_status,
                    'father_education' => $father_education,
                    'mother_education' => $mother_education,
                    'father_occupation' => $father_occupation,
                    'mother_occupation' => $mother_occupation,
                    'emergency_name' => $emergency_name,
                    'emergency_relationship' => $emergency_relationship,
                    'emergency_address' => $emergency_address,
                    'emergency_contact' => $emergency_contact,
                    'parents_marital_status' => $parents_marital_status,
                    'former_boarding_years' => $former_boarding_years,
                    'plan_transfer' => $plan_transfer,
                    'plan_transfer_yes' => $plan_transfer_yes,
                    'plan_transfer_no' => $plan_transfer_no
                ];
                
                $insertFields = [];
                $insertValues = [];
                $bindTypes = "";
                $bindParams = [];
                
                foreach ($fieldMap as $col => $val) {
                    if (in_array($col, $columns)) {
                        $insertFields[] = $col;
                        $insertValues[] = "?";
                        if ($col === 'user_id' || $col === 'age') {
                            $bindTypes .= "i";
                        } else {
                            $bindTypes .= "s";
                        }
                        $bindParams[] = $val;
                    }
                }
                
                if (count($insertFields) > 0) {
                    $sql = "INSERT INTO resident_profiles (" . implode(", ", $insertFields) . ") 
                            VALUES (" . implode(", ", $insertValues) . ")";
                    
                    $stmt2 = $conn->prepare($sql);
                    
                    if ($stmt2) {
                        $stmt2->bind_param($bindTypes, ...$bindParams);
                        
                        if ($stmt2->execute()) {
                            $success = 'Resident registered successfully! Student ID: ' . $student_id;
                            $_POST = array();
                            $_FILES = array();
                        } else {
                            $conn->query("DELETE FROM users WHERE user_id = $user_id");
                            $error = 'Failed to save profile data: ' . $stmt2->error;
                        }
                        $stmt2->close();
                    } else {
                        $conn->query("DELETE FROM users WHERE user_id = $user_id");
                        $error = 'Failed to prepare profile insert: ' . $conn->error;
                    }
                } else {
                    $success = 'Resident registered successfully! Student ID: ' . $student_id;
                    $_POST = array();
                }
                
            } else {
                $error = 'Failed to save resident: ' . $stmt->error;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$formData = $_POST ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Resident - Tap-and-Go Doorlock</title>
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
           PAGE WRAPPER - FLEX LAYOUT (FIXED FOR FOOTER)
           ============================================================ */
        .page-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow: hidden;
        }
        
        .content-wrapper {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: 220px !important;
            margin-top: 56px !important;
            padding: 15px 25px !important;
            flex: 1;
            height: calc(100vh - 56px - 50px) !important;
            overflow-y: auto !important;
            background: #0a0e1a !important;
        }
        
        /* ============================================================
           FOOTER - STICKY BOTTOM (FIXED)
           ============================================================ */
        .footer {
            margin-left: 220px !important;
            padding: 12px 25px !important;
            background: #0d1528 !important;
            border-top: 1px solid #1a2a4a !important;
            color: #8a8a9a !important;
            font-size: 13px !important;
            text-align: center !important;
            flex-shrink: 0 !important;
            height: 50px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            letter-spacing: 0.5px !important;
        }
        .footer span { color: #ffd700 !important; }
        
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
        
        /* ===== AUTO UPPERCASE ===== */
        .form-control.auto-upper {
            text-transform: uppercase;
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
            text-transform: none !important;
        }
        
        .form-control:disabled,
        .form-control[readonly] {
            background: #0a0e1a !important;
            color: #6b7280 !important;
        }
        
        .form-check-label {
            color: #d1d5db !important;
            font-size: 13px;
        }
        
        .form-check-input {
            background-color: #0d1220 !important;
            border-color: #1e2a3a !important;
        }
        
        .form-check-input:checked {
            background-color: #ffd700 !important;
            border-color: #ffd700 !important;
        }
        
        .form-check-input:checked[type="radio"] {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='2' fill='%230d1220'/%3e%3c/svg%3e") !important;
        }
        
        .form-check-input:checked[type="checkbox"] {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%230d1220' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e") !important;
        }
        
        .form-check-input:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
        }
        
        .form-select option {
            background: #131926 !important;
            color: #e5e7eb !important;
        }
        
        /* ============================================================
           HEADER TITLE
           ============================================================ */
        .header-title {
            background: linear-gradient(135deg, #0a1628, #1a2a4a) !important;
            padding: 15px 25px;
            border-radius: 12px 12px 0 0;
            margin: -20px -25px 16px -25px;
            border-bottom: none !important;
        }
        
        .header-title h4 {
            font-weight: 700;
            margin: 0;
            color: #ffd700 !important;
            font-size: 18px;
        }
        
        .header-title p {
            margin: 0;
            opacity: 0.8;
            font-size: 12px;
            color: #9ca3af !important;
        }
        
        .header-title hr {
            border-color: rgba(255, 215, 0, 0.2);
            margin: 6px 0;
        }
        
        .header-title h5 {
            color: #ffd700 !important;
            margin-top: 6px;
            font-size: 16px;
        }
        
        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn-submit {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none;
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            color: #0a0e1a !important;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
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
        
        /* ============================================================
           ALERTS
           ============================================================ */
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
           MISC
           ============================================================ */
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
                height: calc(100vh - 56px - 40px) !important;
            }
            .footer {
                margin-left: 0 !important;
                padding: 8px 15px !important;
                width: 100% !important;
                height: 40px !important;
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
        
        /* ============================================================
           PRINT
           ============================================================ */
        @media print {
            .no-print { display: none !important; }
            .form-section { 
                box-shadow: none !important; 
                border: 1px solid #333 !important; 
                break-inside: avoid;
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
            .form-check-label { color: #333 !important; }
            .footer { display: none !important; }
            .navbar { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; height: auto !important; overflow: visible !important;}
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
                    <h1><i class="fas fa-user-plus me-2"></i>New Resident Registration</h1>
                    <div class="btn-toolbar">
                        <a href="residents.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Print Form
                        </button>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div id="printableForm">
                    <form method="POST" action="" id="residentForm">
                        
                        <!-- HEADER -->
                        <div class="form-section">
                            <div class="header-title">
                                <h4><i class="fas fa-university me-2"></i>ISABELA STATE UNIVERSITY</h4>
                                <p>Echague, Isabela</p>
                                <hr>
                                <p style="font-size: 11px; letter-spacing: 1px;">OFFICE OF STUDENT AFFAIRS &amp; SERVICES</p>
                                <p style="font-size: 11px; letter-spacing: 1px;">STUDENT HOUSING SERVICES</p>
                                <h5 style="color: #ffd700; margin-top: 6px;">Student Boarder's Data Profile</h5>
                                <p style="font-size: 11px;">ISU-ECHAGUE CAMPUS DORMITORY</p>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Date <span class="required">*</span></label>
                                            <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Name of Dorm Occupant <span class="required">*</span></label>
                                            <input type="text" class="form-control auto-upper" name="full_name" placeholder="Enter full name" value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PERSONAL INFORMATION -->
                        <div class="form-section">
                            <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Name <span class="required">*</span></label>
                                    <input type="text" class="form-control auto-upper" name="full_name" placeholder="Full Name" value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <div class="d-flex flex-wrap gap-2 pt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="gender" value="Female" id="genderFemale" <?php echo (isset($formData['gender']) && $formData['gender'] == 'Female') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="genderFemale">Female</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="gender" value="Male" id="genderMale" <?php echo (isset($formData['gender']) && $formData['gender'] == 'Male') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="genderMale">Male</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="gender" value="LGBT" id="genderLGBT" <?php echo (isset($formData['gender']) && $formData['gender'] == 'LGBT') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="genderLGBT">LGBT</label>
                                        </div>
                                        <div>
                                            <input type="text" class="form-control form-control-sm auto-upper" name="gender_other" placeholder="Specify" value="<?php echo htmlspecialchars($formData['gender_other'] ?? ''); ?>" style="width:100px; display:inline; height:32px; font-size:12px;">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Birth Date</label>
                                    <input type="date" class="form-control" name="birth_date" value="<?php echo htmlspecialchars($formData['birth_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Age</label>
                                    <input type="number" class="form-control" name="age" min="1" max="99" value="<?php echo htmlspecialchars($formData['age'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Birth No.</label>
                                    <input type="text" class="form-control auto-upper" name="birth_no" placeholder="Birth Certificate No." value="<?php echo htmlspecialchars($formData['birth_no'] ?? ''); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Course <span class="required">*</span></label>
                                    <input type="text" class="form-control auto-upper" name="course" placeholder="e.g., BSIT" value="<?php echo htmlspecialchars($formData['course'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Year Level <span class="required">*</span></label>
                                    <select class="form-select" name="year_level" required>
                                        <option value="">Select</option>
                                        <option value="1st Year" <?php echo (isset($formData['year_level']) && $formData['year_level'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2nd Year" <?php echo (isset($formData['year_level']) && $formData['year_level'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3rd Year" <?php echo (isset($formData['year_level']) && $formData['year_level'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4th Year" <?php echo (isset($formData['year_level']) && $formData['year_level'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                        <option value="5th Year" <?php echo (isset($formData['year_level']) && $formData['year_level'] == '5th Year') ? 'selected' : ''; ?>>5th Year</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">No. of Siblings</label>
                                    <input type="text" class="form-control auto-upper" name="no_siblings" placeholder="e.g., 3 siblings" value="<?php echo htmlspecialchars($formData['no_siblings'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Scholarship Grant</label>
                                    <input type="text" class="form-control auto-upper" name="scholarship" placeholder="If none, type 'None'" value="<?php echo htmlspecialchars($formData['scholarship'] ?? ''); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Other Sources of Allowance for School</label>
                                    <input type="text" class="form-control auto-upper" name="allowance_source" placeholder="e.g., Parents, Part-time job" value="<?php echo htmlspecialchars($formData['allowance_source'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- EDUCATIONAL BACKGROUND -->
                        <div class="form-section">
                            <h5><i class="fas fa-graduation-cap me-2"></i>Educational Background</h5>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">School Last Attended</label>
                                    <input type="text" class="form-control auto-upper" name="school_last" placeholder="School name" value="<?php echo htmlspecialchars($formData['school_last'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">School Address</label>
                                    <input type="text" class="form-control auto-upper" name="school_address" placeholder="School address" value="<?php echo htmlspecialchars($formData['school_address'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Cultural Origin</label>
                                    <input type="text" class="form-control auto-upper" name="cultural_origin" placeholder="e.g., Ilocano" value="<?php echo htmlspecialchars($formData['cultural_origin'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Religion</label>
                                    <input type="text" class="form-control auto-upper" name="religion" placeholder="Religion" value="<?php echo htmlspecialchars($formData['religion'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Dialect Spoken</label>
                                    <input type="text" class="form-control auto-upper" name="dialect" placeholder="Dialect" value="<?php echo htmlspecialchars($formData['dialect'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">CP No.</label>
                                    <input type="text" class="form-control auto-upper" name="cp_no" placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($formData['cp_no'] ?? ''); ?>">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Complete Home Address</label>
                                    <input type="text" class="form-control auto-upper" name="home_address" placeholder="House number, Street, Barangay" value="<?php echo htmlspecialchars($formData['home_address'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Civil Status</label>
                                    <select class="form-select" name="civil_status">
                                        <option value="">Select</option>
                                        <option value="Married" <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                                        <option value="Single" <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                                        <option value="Separated" <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Separated') ? 'selected' : ''; ?>>Separated</option>
                                        <option value="Abandoned" <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Abandoned') ? 'selected' : ''; ?>>Abandoned</option>
                                        <option value="Live-in" <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Live-in') ? 'selected' : ''; ?>>Live-in</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- PARENT/GUARDIAN INFORMATION -->
                        <div class="form-section">
                            <h5><i class="fas fa-users me-2"></i>Parent / Guardian Information</h5>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Father's Education</label>
                                    <input type="text" class="form-control auto-upper" name="father_education" placeholder="e.g., College Graduate" value="<?php echo htmlspecialchars($formData['father_education'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mother's Education</label>
                                    <input type="text" class="form-control auto-upper" name="mother_education" placeholder="e.g., High School Grad" value="<?php echo htmlspecialchars($formData['mother_education'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Father's Occupation</label>
                                    <input type="text" class="form-control auto-upper" name="father_occupation" placeholder="Occupation" value="<?php echo htmlspecialchars($formData['father_occupation'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mother's Occupation</label>
                                    <input type="text" class="form-control auto-upper" name="mother_occupation" placeholder="Occupation" value="<?php echo htmlspecialchars($formData['mother_occupation'] ?? ''); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Parent's Marital Status</label>
                                    <div class="d-flex flex-wrap gap-2 pt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="parents_marital_status" value="Living Together" id="livingTogether" <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Living Together') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="livingTogether">Living Together</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="parents_marital_status" value="Separated" id="separated" <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Separated') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="separated">Separated</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="parents_marital_status" value="Abandoned" id="abandoned" <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Abandoned') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="abandoned">Abandoned</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="parents_marital_status" value="Mother with Other Family" id="motherOther" <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Mother with Other Family') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="motherOther">Mother with Other</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="parents_marital_status" value="Father with Other Family" id="fatherOther" <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Father with Other Family') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="fatherOther">Father with Other</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- EMERGENCY CONTACT -->
                        <div class="form-section">
                            <h5><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Name <span class="required">*</span></label>
                                    <input type="text" class="form-control auto-upper" name="emergency_name" placeholder="Full name" value="<?php echo htmlspecialchars($formData['emergency_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Relationship <span class="required">*</span></label>
                                    <input type="text" class="form-control auto-upper" name="emergency_relationship" placeholder="e.g., Mother, Father" value="<?php echo htmlspecialchars($formData['emergency_relationship'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Address <span class="required">*</span></label>
                                    <input type="text" class="form-control auto-upper" name="emergency_address" placeholder="Complete address" value="<?php echo htmlspecialchars($formData['emergency_address'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contact No. <span class="required">*</span></label>
                                    <input type="text" class="form-control auto-upper" name="emergency_contact" placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($formData['emergency_contact'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- BOARDING HISTORY -->
                        <div class="form-section">
                            <h5><i class="fas fa-home me-2"></i>Boarding History</h5>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Length of Stay in Former Boarding House</label>
                                    <input type="text" class="form-control auto-upper" name="former_boarding_years" placeholder="e.g., 2 years" value="<?php echo htmlspecialchars($formData['former_boarding_years'] ?? ''); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Plan to Transfer After This Semester?</label>
                                    <div class="d-flex gap-3 pt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="plan_transfer" value="Yes" id="planYes" <?php echo (isset($formData['plan_transfer']) && $formData['plan_transfer'] == 'Yes') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="planYes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="plan_transfer" value="No" id="planNo" <?php echo (isset($formData['plan_transfer']) && $formData['plan_transfer'] == 'No') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="planNo">No</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6" id="planYesDiv" style="display:none;">
                                    <label class="form-label">Why, If Yes?</label>
                                    <input type="text" class="form-control auto-upper" name="plan_transfer_yes" placeholder="Reason for transferring" value="<?php echo htmlspecialchars($formData['plan_transfer_yes'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6" id="planNoDiv" style="display:none;">
                                    <label class="form-label">Why, If No?</label>
                                    <input type="text" class="form-control auto-upper" name="plan_transfer_no" placeholder="Reason for staying" value="<?php echo htmlspecialchars($formData['plan_transfer_no'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- SUBMIT BUTTONS -->
                        <div class="text-center mb-2 no-print">
                            <button type="submit" name="submit" class="btn btn-submit">
                                <i class="fas fa-save me-2"></i> Register Resident
                            </button>
                            <button type="reset" class="btn btn-outline-secondary ms-2" onclick="resetForm()">
                                <i class="fas fa-undo me-1"></i> Reset
                            </button>
                        </div>

                    </form>
                </div>
                
            </main>
        </div>
        
        <!-- ============================================================
        FOOTER - STICKY BOTTOM (FIXED)
        ============================================================ -->
        <footer class="footer">
            &copy; <?php echo date('Y'); ?> <span>Tap-and-Go Doorlock</span> System &bull; ISU-Echague Dormitory. All rights reserved.
        </footer>
    </div>

  
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // AUTO UPPERCASE ON INPUT (real-time)
        // ============================================================
        document.querySelectorAll('.auto-upper').forEach(function(input) {
            input.addEventListener('input', function() {
                // Save cursor position
                const start = this.selectionStart;
                const end = this.selectionEnd;
                
                // Convert to uppercase
                this.value = this.value.toUpperCase();
                
                // Restore cursor position
                this.setSelectionRange(start, end);
            });
        });

        // ============================================================
        // TOGGLE PLAN TRANSFER FIELDS
        // ============================================================
        document.querySelectorAll('input[name="plan_transfer"]').forEach(function(el) {
            el.addEventListener('change', function() {
                if (this.value === 'Yes') {
                    document.getElementById('planYesDiv').style.display = 'block';
                    document.getElementById('planNoDiv').style.display = 'none';
                } else if (this.value === 'No') {
                    document.getElementById('planYesDiv').style.display = 'none';
                    document.getElementById('planNoDiv').style.display = 'block';
                }
            });
        });

        // ============================================================
        // AUTO-CALCULATE AGE
        // ============================================================
        document.querySelector('input[name="birth_date"]').addEventListener('change', function() {
            if (this.value) {
                const birthDate = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                if (age > 0) {
                    document.querySelector('input[name="age"]').value = age;
                }
            }
        });

        // ============================================================
        // RESET FORM
        // ============================================================
        function resetForm() {
            document.getElementById('residentForm').reset();
            document.getElementById('planYesDiv').style.display = 'none';
            document.getElementById('planNoDiv').style.display = 'none';
        }

        // ============================================================
        // AUTO-FOCUS ON FIRST FIELD
        // ============================================================
        document.querySelector('input[name="full_name"]').focus();
        
        // ============================================================
        // SIDEBAR TOGGLE (mobile)
        // ============================================================
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
