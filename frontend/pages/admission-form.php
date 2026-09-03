<?php
/**
 * Tap-and-Go Doorlock - Residents Admission Form
 * WITH AUTO-FILL FROM RESIDENT DATA - DARK MODE - WITH PRINT FORMAT (ISU Paper Form)
 * WITH FIXED NAVBAR, SIDEBAR, AND FOOTER
 * WITH PDF DOWNLOAD FUNCTION
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
$formData = [];
$resident = null;
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$room_assignment = 'Not Assigned'; // Default value

// ============================================================
// GET RESIDENT DATA FOR AUTO-FILL
// ============================================================
if ($user_id > 0) {
    try {
        $conn = getDBConnection();
        
        // Get user data
        $stmt = $conn->prepare("
            SELECT u.*, rp.course, rp.year_level, rp.gender, rp.birth_date, rp.age, 
                   rp.home_address, rp.cp_no, rp.religion, rp.dialect,
                   rp.emergency_name, rp.emergency_relationship, rp.emergency_address, rp.emergency_contact
            FROM users u
            LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
            WHERE u.user_id = ? AND u.status != 'deleted'
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $resident = $result->fetch_assoc();
        $stmt->close();
        
        if ($resident) {
            // Auto-fill form data from resident
            $formData = [
                'name' => $resident['full_name'] ?? '',
                'course' => $resident['course'] ?? '',
                'year_level' => $resident['year_level'] ?? '',
                'age' => $resident['age'] ?? '',
                'birth_date' => $resident['birth_date'] ?? '',
                'contact_number' => $resident['cp_no'] ?? $resident['contact_number'] ?? '',
                'home_address' => $resident['home_address'] ?? '',
                'guardian_name' => $resident['emergency_name'] ?? '',
                'guardian_contact' => $resident['emergency_contact'] ?? ''
            ];
        }
    } catch (Exception $e) {
        $error = 'Error loading resident data: ' . $e->getMessage();
    }
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Get form data
    $data = [
        'semester_sy' => trim($_POST['semester_sy'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'course' => trim($_POST['course'] ?? ''),
        'year_level' => trim($_POST['year_level'] ?? ''),
        'age' => (int)($_POST['age'] ?? 0),
        'birth_date' => trim($_POST['birth_date'] ?? ''),
        'contact_number' => trim($_POST['contact_number'] ?? ''),
        'home_address' => trim($_POST['home_address'] ?? ''),
        'school_last' => trim($_POST['school_last'] ?? ''),
        'school_address' => trim($_POST['school_address'] ?? ''),
        'strand_track' => trim($_POST['strand_track'] ?? ''),
        'course_taken' => trim($_POST['course_taken'] ?? ''),
        'year_level_old' => trim($_POST['year_level_old'] ?? ''),
        'former_bh' => trim($_POST['former_bh'] ?? ''),
        'former_address' => trim($_POST['former_address'] ?? ''),
        'guardian_name' => trim($_POST['guardian_name'] ?? ''),
        'guardian_contact' => trim($_POST['guardian_contact'] ?? ''),
        'student_signature' => trim($_POST['student_signature'] ?? ''),
        'status' => $_POST['status'] ?? 'pending'
    ];
    
    // Validate required fields
    if (empty($data['name']) || empty($data['course']) || empty($data['year_level'])) {
        $error = 'Please fill in all required fields (Name, Course, Year Level).';
    } else {
        try {
            $conn = getDBConnection();
            
            // Check if user already exists or create new
            if ($user_id > 0) {
                // Check if admission record already exists
                $check = $conn->prepare("SELECT admission_id FROM admission_records WHERE user_id = ?");
                $check->bind_param("i", $user_id);
                $check->execute();
                $checkResult = $check->get_result();
                
                if ($checkResult->num_rows > 0) {
                    // Update existing admission
                    $stmt = $conn->prepare("
                        UPDATE admission_records SET
                            semester_sy = ?, age = ?, birth_date = ?, home_address = ?,
                            school_last = ?, school_address = ?, strand_track = ?,
                            course_taken = ?, year_level_old = ?, former_bh = ?,
                            former_address = ?, guardian_name = ?, guardian_contact = ?,
                            student_signature = ?, status = ?
                        WHERE user_id = ?
                    ");
                    $stmt->bind_param(
                        "sisssssssssssssi",
                        $data['semester_sy'],
                        $data['age'],
                        $data['birth_date'],
                        $data['home_address'],
                        $data['school_last'],
                        $data['school_address'],
                        $data['strand_track'],
                        $data['course_taken'],
                        $data['year_level_old'],
                        $data['former_bh'],
                        $data['former_address'],
                        $data['guardian_name'],
                        $data['guardian_contact'],
                        $data['student_signature'],
                        $data['status'],
                        $user_id
                    );
                } else {
                    // Insert new admission
                    $stmt = $conn->prepare("
                        INSERT INTO admission_records (
                            user_id, semester_sy, age, birth_date, home_address,
                            school_last, school_address, strand_track, course_taken,
                            year_level_old, former_bh, former_address,
                            guardian_name, guardian_contact,
                            student_signature, status, room_assignment, created_at
                        ) VALUES (
                            ?, ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, ?,
                            ?, ?,
                            ?, ?, ?, NOW()
                        )
                    ");
                    $stmt->bind_param(
                        "isississsssssssss",
                        $user_id,
                        $data['semester_sy'],
                        $data['age'],
                        $data['birth_date'],
                        $data['home_address'],
                        $data['school_last'],
                        $data['school_address'],
                        $data['strand_track'],
                        $data['course_taken'],
                        $data['year_level_old'],
                        $data['former_bh'],
                        $data['former_address'],
                        $data['guardian_name'],
                        $data['guardian_contact'],
                        $data['student_signature'],
                        $data['status'],
                        $room_assignment
                    );
                }
            } else {
                // Create new user and admission
                $student_id = 'ADM-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $email = strtolower(str_replace(' ', '.', $data['name'])) . '@isu.edu.ph';
                
                $stmt = $conn->prepare("
                    INSERT INTO users (full_name, student_id, contact_number, email, status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->bind_param("ssss", $data['name'], $student_id, $data['contact_number'], $email);
                $stmt->execute();
                $user_id = $conn->insert_id;
                $stmt->close();
                
                // Insert admission
                $room_assignment = 'Not Assigned';
                $stmt = $conn->prepare("
                    INSERT INTO admission_records (
                        user_id, semester_sy, age, birth_date, home_address,
                        school_last, school_address, strand_track, course_taken,
                        year_level_old, former_bh, former_address,
                        guardian_name, guardian_contact,
                        student_signature, status, room_assignment, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, NOW()
                    )
                ");
                $stmt->bind_param(
                    "isississsssssssss",
                    $user_id,
                    $data['semester_sy'],
                    $data['age'],
                    $data['birth_date'],
                    $data['home_address'],
                    $data['school_last'],
                    $data['school_address'],
                    $data['strand_track'],
                    $data['course_taken'],
                    $data['year_level_old'],
                    $data['former_bh'],
                    $data['former_address'],
                    $data['guardian_name'],
                    $data['guardian_contact'],
                    $data['student_signature'],
                    $data['status'],
                    $room_assignment
                );
            }
            
            if ($stmt->execute()) {
                $success = 'Admission form submitted successfully!';
                if ($user_id == 0) {
                    $formData = [];
                }
            } else {
                $error = 'Failed to save admission: ' . $stmt->error;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Form - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <!-- Para sa PDF Download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
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
        
        .btn-submit i {
            margin-right: 6px;
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
        
        .btn-success {
            background: #10b981 !important;
            border: none !important;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 10px;
        }
        
        .btn-success:hover {
            background: #059669 !important;
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
        
        .alert-info {
            background: rgba(59, 130, 246, 0.15) !important;
            border-color: #3b82f6 !important;
            color: #93c5fd !important;
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
        
        .auto-fill-badge {
            background: rgba(255, 215, 0, 0.2) !important;
            color: #ffd700 !important;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
            margin-left: 8px;
            border: 1px solid rgba(255, 215, 0, 0.2);
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
        
        /* ============================================================
           PRINT FORMAT (EXACT COPY OF PHYSICAL FORM)
           ============================================================ */
        @media print {
            /* Hidden Elements */
            .no-print, .navbar, .sidebar, .footer, .page-header, .alert, .btn-submit, .btn-outline-secondary, .btn-outline-primary { 
                display: none !important; 
            }
            
            /* Base */
            body { 
                background: #fff !important; 
                color: #000 !important; 
                font-family: 'Times New Roman', Times, serif !important; 
                margin: 0; 
                padding: 0; 
            }
            .main-content { 
                margin: 0 !important; 
                padding: 0 !important; 
                background: #fff !important; 
            }
            .form-section { 
                background: #fff !important; 
                border: none !important; 
                box-shadow: none !important; 
                padding: 0 !important; 
                margin-bottom: 0 !important; 
                border-radius: 0 !important;
            }
            
            /* Header Layout */
            .header-title {
                background: #fff !important;
                margin: 0 !important;
                padding: 10px 0 20px 0 !important;
                border-bottom: none !important;
                text-align: center !important;
            }
            .header-title h4, .header-title h5, .header-title p {
                color: #000 !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                background: none !important;
                font-weight: bold;
            }
            .header-title h4 { font-size: 14pt; }
            .header-title p { font-size: 12pt; }
            .header-title hr { display: none !important; }
            .header-title h5 { font-size: 14pt; margin-top: 5px; }
            
            /* Paper Logo & Text Columns */
            .print-header-row {
                display: flex !important;
                align-items: center;
                justify-content: center;
                margin-bottom: 10px;
            }
            .print-logo {
                width: 90px;
                height: 90px;
                object-fit: contain;
                margin-right: 20px;
                display: block !important;
            }
            .print-header-text {
                text-align: center;
                line-height: 1.4;
            }
            .print-id-box {
                position: absolute;
                top: 10px;
                right: 10px;
                width: 100px;
                height: 120px;
                border: 1px solid #000;
                display: block !important;
            }

            /* Form Fields (Underscores) */
            .form-label { 
                color: #000 !important; 
                font-weight: normal; 
                font-size: 12pt; 
                margin-bottom: 0 !important;
                display: inline-block;
            }
            .form-control, .form-select {
                background: transparent !important;
                border: none !important;
                border-bottom: 1px solid #000 !important;
                color: #000 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                height: auto !important;
                font-size: 12pt !important;
                padding: 2px 5px !important;
                font-family: 'Times New Roman', Times, serif !important;
                display: inline-block;
                width: 70% !important;
            }
            .form-select {
                -webkit-appearance: none !important;
                appearance: none !important;
                background-image: none !important;
                padding-right: 0 !important;
                border-bottom: 1px solid #000 !important;
            }
            
            /* Layout */
            .row { display: block !important; }
            .col-md-2, .col-md-4, .col-md-6, .col-md-8, .col-md-12 { width: 100% !important; max-width: 100% !important; padding: 0 !important; }
            .row.g-2 > [class*="col-"] { padding: 3px 0 !important; }
            
            /* Special formatting for specific fields based on picture */
            .print-section-title {
                font-weight: bold;
                text-transform: uppercase;
                margin-top: 15px;
                font-size: 12pt;
            }
            .print-note {
                font-size: 9pt;
                margin-top: 30px;
            }
        }
        
        /* Print only elements */
        .print-header-row, .print-logo, .print-id-box { display: none; }
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
                    <h1><i class="fas fa-clipboard-list me-2"></i>Admission Form</h1>
                    <div class="btn-toolbar">
                        <a href="residents.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="downloadPDF()">
                            <i class="fas fa-download me-1"></i> Download Form
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

                <?php if ($resident): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Auto-fill enabled:</strong> Form fields have been pre-filled with resident data.
                        <span class="auto-fill-badge"><i class="fas fa-check me-1"></i>Auto-filled</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="admissionForm">
                    
                    <!-- ===== HEADER (ON SCREEN) ===== -->
                    <div class="form-section no-print">
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
                                        <input type="text" class="form-control" name="semester_sy" placeholder="e.g., 1st Semester, SY 2025-2026" value="<?php echo htmlspecialchars($formData['semester_sy'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== PRINT HEADER (ONLY WHEN PRINTING OR DOWNLOADING) ===== -->
                    <div class="print-header-row">
                        <img src="../assets/images/isu-logo.png" alt="ISU Logo" class="print-logo">
                        <div class="print-header-text">
                            <h4>Isabela State University,</h4>
                            <p>Echague, Isabela</p>
                            <p>Office of Student Affairs &amp; Services</p>
                            <h5>ISU -ECHAGUE CAMPUS DORMITORY</h5>
                            <h5>ADMISSION FORM</h5>
                        </div>
                        <div class="print-id-box"></div> <!-- Picture Box on top right -->
                    </div>

                    <!-- ===== PERSONAL INFORMATION ===== -->
                    <div class="form-section">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label">NAME: </label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                                <div class="col-md-4" style="display:inline-block; width:30%; margin-left: 10px;">
                                    <label class="form-label">Contact number: </label>
                                    <input type="text" class="form-control" name="contact_number" value="<?php echo htmlspecialchars($formData['contact_number'] ?? ''); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <label class="form-label">Course : </label>
                                <input type="text" class="form-control" name="course" style="width:25% !important;" value="<?php echo htmlspecialchars($formData['course'] ?? ''); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                                
                                <label class="form-label">Yr. level: </label>
                                <select class="form-select" name="year_level" style="width:25% !important;" <?php echo $resident ? 'disabled' : ''; ?>>
                                    <option value="">Select</option>
                                    <option value="1st Year" <?php echo (isset($formData['year_level']) && $formData['year_level'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo (isset($formData['year_level']) && $formData['year_level'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo (isset($formData['year_level']) && $formData['year_level'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo (isset($formData['year_level']) && $formData['year_level'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                                <?php if ($resident): ?>
                                    <input type="hidden" name="year_level" value="<?php echo htmlspecialchars($formData['year_level'] ?? ''); ?>">
                                <?php endif; ?>

                                <label class="form-label">Age: </label>
                                <input type="number" class="form-control" name="age" style="width:15% !important;" value="<?php echo htmlspecialchars($formData['age'] ?? ''); ?>" <?php echo $resident ? 'readonly' : ''; ?>>
                                
                                <label class="form-label">Birth Day: </label>
                                <input type="date" class="form-control" name="birth_date" style="width:30% !important;" value="<?php echo htmlspecialchars($formData['birth_date'] ?? ''); ?>" <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Complete Home Address: </label>
                                <input type="text" class="form-control" name="home_address" style="width:75% !important;" value="<?php echo htmlspecialchars($formData['home_address'] ?? ''); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>
                            
                            <div class="col-md-12">
                                <label class="form-label">School Last Attended: </label>
                                <input type="text" class="form-control" name="school_last" style="width:50% !important;" value="<?php echo htmlspecialchars($formData['school_last'] ?? ''); ?>">
                                <label class="form-label">Sch. Address: </label>
                                <input type="text" class="form-control" name="school_address" style="width:40% !important;" value="<?php echo htmlspecialchars($formData['school_address'] ?? ''); ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label print-section-title">(For first-year students) Strand/tract taken: </label>
                                <input type="text" class="form-control" name="strand_track" style="width:60% !important;" value="<?php echo htmlspecialchars($formData['strand_track'] ?? ''); ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label print-section-title">(For Higher year) Course taken: </label>
                                <input type="text" class="form-control" name="course_taken" style="width:50% !important;" value="<?php echo htmlspecialchars($formData['course_taken'] ?? ''); ?>">
                                <label class="form-label">Yr. level: </label>
                                <select class="form-select" name="year_level_old" style="width:25% !important;">
                                    <option value="">Select</option>
                                    <option value="1st Year" <?php echo (isset($formData['year_level_old']) && $formData['year_level_old'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo (isset($formData['year_level_old']) && $formData['year_level_old'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo (isset($formData['year_level_old']) && $formData['year_level_old'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo (isset($formData['year_level_old']) && $formData['year_level_old'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label print-section-title">For Old Students: Name of BH/Dorm. you came from if any: </label>
                                <input type="text" class="form-control" name="former_bh" style="width:50% !important;" value="<?php echo htmlspecialchars($formData['former_bh'] ?? ''); ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Address Area if any: </label>
                                <input type="text" class="form-control" name="former_address" style="width:60% !important;" value="<?php echo htmlspecialchars($formData['former_address'] ?? ''); ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label print-section-title">Parent or Guardian's Name: </label>
                                <input type="text" class="form-control" name="guardian_name" style="width:60% !important;" value="<?php echo htmlspecialchars($formData['guardian_name'] ?? ''); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Contact Number: </label>
                                <input type="text" class="form-control" name="guardian_contact" style="width:60% !important;" value="<?php echo htmlspecialchars($formData['guardian_contact'] ?? ''); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <!-- ===== ROOM ASSIGNMENT & SIGNATURE ===== -->
                    <div class="form-section">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label print-section-title">ROOM ASSIGNMENT: </label>
                                <input type="text" class="form-control" name="room_assignment" style="width:70% !important;" value="<?php echo htmlspecialchars($room_assignment); ?>" readonly>
                            </div>
                            <div class="col-md-6" style="padding-top: 30px;">
                                <label class="form-label">Student's name and signature</label>
                                <input type="text" class="form-control" name="student_signature" style="width:100% !important; border-bottom: 1px solid #000 !important;" value="<?php echo htmlspecialchars($formData['student_signature'] ?? $formData['name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOOTER NOTE ===== -->
                    <div class="print-note">
                        <p><strong>ISUE-OSAS-DAF-III</strong></p>
                        <p>Effective July 18, 2024</p>
                    </div>

                    <!-- ===== SUBMIT ===== -->
                    <div class="text-center mb-2 no-print">
                        <button type="submit" name="submit" class="btn btn-submit">
                            <i class="fas fa-save"></i> Submit Admission Form
                        </button>
                        <button type="reset" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-undo me-1"></i> Reset
                        </button>
                        <?php if ($user_id > 0): ?>
                            <a href="view-admission.php?id=<?php echo $user_id; ?>" class="btn btn-outline-primary ms-2">
                                <i class="fas fa-eye me-1"></i> View Admission
                            </a>
                        <?php endif; ?>
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

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on first field
        document.querySelector('input[name="semester_sy"]').focus();

        // Auto-calculate age from birth date
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
        // DOWNLOAD PDF FUNCTION
        // ============================================================
        function downloadPDF() {
            const element = document.getElementById('admissionForm');
            const opt = {
                margin:       10,
                filename:     'Admission_Form.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // I-print ang form as PDF
            html2pdf().set(opt).from(element).save();
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
