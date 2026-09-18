<?php
/**
 * Tap-and-Go Doorlock - New Resident Registration
 * DARK MODE - NO PHOTO - NO ROOM ASSIGNMENT
 * WITH FIXED NAVBAR, SIDEBAR, AND FOOTER
 * AUTO UPPERCASE FOR ALL TEXT FIELDS
 * ✅ PRINT LAYOUT MATCHES OFFICIAL FORM (PORTRAIT)
 * ✅ COLORED PRINT - Blue values, Green dorm name, Light blue title
 * ✅ WITH ISU LOGO
 */

session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

include '../includes/header.php'; 
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
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
    
    if (empty($full_name) || empty($course) || empty($year_level)) {
        $error = 'Please fill in all required fields (Name, Course, Year Level).';
    }
    
    if (empty($error)) {
        try {
            $conn = getDBConnection();
            
            $student_id = 'STU-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
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
            
            $stmt = $conn->prepare("
                INSERT INTO users (full_name, student_id, contact_number, email, status, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param("sssss", $full_name, $student_id, $contact, $email, $status);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e5e7eb !important;
        }
        
        /* NAVBAR */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important; left: 0 !important; right: 0 !important;
            z-index: 1050 !important;
            height: 56px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* SIDEBAR */
        .sidebar {
            position: fixed !important;
            top: 56px !important; left: 0 !important; bottom: 0 !important;
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
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.05) !important; color: #e0e0e0 !important; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important; color: white !important; }
        .sidebar .nav-link i { width: 18px; text-align: center; }
        .sidebar-footer { border-top-color: #1a2a4a !important; padding: 12px 16px !important; margin-top: 10px !important; }
        .sidebar-footer .text-muted { color: #606070 !important; font-size: 11px !important; }
        
        /* PAGE WRAPPER */
        .page-wrapper { display: flex; flex-direction: column; min-height: 100vh; overflow: hidden; }
        .content-wrapper { display: flex; flex: 1; overflow: hidden; }
        
        /* MAIN CONTENT */
        .main-content {
            margin-left: 220px !important;
            margin-top: 56px !important;
            padding: 15px 25px !important;
            flex: 1;
            height: calc(100vh - 56px - 50px) !important;
            overflow-y: auto !important;
            background: #0a0e1a !important;
        }
        
        /* FOOTER */
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
        
        /* FORM SECTION */
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
        
        .form-label { font-weight: 500; font-size: 12px; color: #d1d5db !important; }
        
        .form-control, .form-select {
            background: #0d1220 !important;
            border: 1px solid #1e2a3a !important;
            color: #e5e7eb !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            height: 38px;
        }
        
        .form-control.auto-upper { text-transform: uppercase; }
        
        .form-control:focus, .form-select:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        
        .form-control::placeholder { color: #6b7280 !important; text-transform: none !important; }
        .form-control:disabled, .form-control[readonly] { background: #0a0e1a !important; color: #6b7280 !important; }
        .form-check-label { color: #d1d5db !important; font-size: 13px; }
        .form-check-input { background-color: #0d1220 !important; border-color: #1e2a3a !important; }
        .form-check-input:checked { background-color: #ffd700 !important; border-color: #ffd700 !important; }
        .form-check-input:focus { border-color: #ffd700 !important; box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important; }
        .form-select option { background: #131926 !important; color: #e5e7eb !important; }
        
        /* HEADER TITLE */
        .header-title {
            background: linear-gradient(135deg, #0a1628, #1a2a4a) !important;
            padding: 15px 25px;
            border-radius: 12px 12px 0 0;
            margin: -20px -25px 16px -25px;
            border-bottom: none !important;
            text-align: center;
        }
        .header-title h4 { font-weight: 700; margin: 0; color: #ffd700 !important; font-size: 18px; }
        .header-title p { margin: 0; opacity: 0.8; font-size: 12px; color: #9ca3af !important; }
        .header-title hr { border-color: rgba(255, 215, 0, 0.2); margin: 6px 0; }
        .header-title h5 { color: #ffd700 !important; margin-top: 6px; font-size: 16px; }
        
        /* BUTTONS */
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
        .btn-outline-secondary:hover { background: #1a1f2e !important; color: #e5e7eb !important; }
        .btn-outline-primary {
            color: #ffd700 !important;
            border-color: #ffd700 !important;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 10px;
        }
        .btn-outline-primary:hover { background: #ffd700 !important; color: #0a0e1a !important; }
        
        /* ALERTS */
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
        .btn-close { filter: invert(1) !important; }
        
        /* PAGE HEADER */
        .page-header {
            padding-bottom: 10px;
            margin-bottom: 15px;
            border-bottom: 1px solid #1e2a3a;
        }
        .page-header h1 { font-size: 20px; font-weight: 600; color: #e5e7eb; }
        .page-header h1 i { color: #ffd700; }
        
        /* MISC */
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 { color: #e5e7eb !important; }
        .border-bottom { border-color: #1e2a3a !important; }
        .text-muted { color: #6b7280 !important; }
        .required { color: #ef4444 !important; margin-left: 2px; }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed !important;
                top: 56px !important; bottom: 0 !important;
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
            .footer { margin-left: 0 !important; padding: 8px 15px !important; width: 100% !important; height: 40px !important; }
            .form-section { padding: 15px; }
            .header-title { padding: 12px 15px; margin: -15px -15px 15px -15px; }
            .header-title h4 { font-size: 15px; }
            .header-title h5 { font-size: 14px; }
        }
        
        /* SCROLLBAR */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1e2a3a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
        
        /* ============================================================
           ✅ PRINT LAYOUT - PORTRAIT (COLORED - MATCHES OFFICIAL FORM)
           ============================================================ */
        @page {
            size: A4 portrait;
            margin: 8mm 10mm;
        }
        
        /* Print form hidden on screen */
        .print-form {
            display: none;
        }
        
        @media print {
            /* Hide screen elements */
            .no-print,
            .navbar,
            .sidebar,
            .footer,
            .page-header,
            .alert,
            .form-section,
            #residentForm,
            main.main-content > *:not(.print-form) {
                display: none !important;
            }
            
            body, html {
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                font-family: 'Times New Roman', Arial, serif !important;
                font-size: 10px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
                background: #ffffff !important;
                width: 100% !important;
            }
            
            /* Show print form */
            .print-form {
                display: block !important;
                width: 100%;
                max-width: 210mm;
                margin: 0 auto;
                padding: 5mm;
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Times New Roman', Arial, serif !important;
                font-size: 10px;
                line-height: 1.4;
            }
            
            /* ============================================================
               PRINT HEADER - 3 COLUMNS (Logo | Info | Photo box)
               ============================================================ */
            .print-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 6px;
                gap: 10px;
            }
            
            .print-header-left {
                width: 90px;
                flex-shrink: 0;
                text-align: center;
            }
            
            .print-logo {
                width: 75px;
                height: 75px;
                object-fit: contain;
            }
            
            .print-header-center {
                flex: 1;
                text-align: center;
                padding: 0 5px;
            }
            
            .print-header-center p {
                margin: 0;
                font-size: 10px;
                color: #000 !important;
                line-height: 1.3;
            }
            
            .print-header-center .uni-name {
                font-size: 12px;
                font-weight: 700;
                color: #000 !important;
            }
            
            /* ✅ GREEN: Dormitory Name */
            .print-header-center .dorm-name {
                font-size: 11px;
                font-weight: 700;
                color: #15803d !important;
                margin-top: 2px;
            }
            
            /* ✅ LIGHT BLUE: Student Boarder's Data Profile */
            .print-header-center .profile-title {
                font-size: 12px;
                font-weight: 800;
                color: #0284c7 !important;
                margin: 3px 0;
                letter-spacing: 0.5px;
            }
            
            .print-header-right {
                width: 100px;
                flex-shrink: 0;
            }
            
            .print-photo-box {
                width: 90px;
                height: 105px;
                border: 1.5px solid #000;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                background: #fff;
            }
            
            .print-photo-box .photo-label {
                font-size: 8px;
                font-weight: 600;
                text-align: center;
                color: #000;
                padding: 2px;
            }
            
            /* ============================================================
               PRINT BODY - Line by line, checkbox style
               ============================================================ */
            .print-body {
                margin-top: 4px;
                font-size: 10px;
                color: #000 !important;
            }
            
            .print-row {
                margin-bottom: 3px;
                display: flex;
                align-items: baseline;
                flex-wrap: wrap;
                gap: 4px;
                line-height: 1.5;
            }
            
            .print-label {
                font-weight: 600;
                color: #000 !important;
                white-space: nowrap;
                font-size: 10px;
            }
            
            /* ✅ BLUE: Filled values */
            .print-value {
                border-bottom: 1px solid #000;
                padding: 0 3px 1px 3px;
                min-width: 80px;
                display: inline-block;
                color: #1e40af !important;
                font-weight: 600;
            }
            
            /* Empty values - italic */
            .print-value:empty,
            .print-value.blank {
                color: transparent !important;
            }
            
            .print-value-empty {
                border-bottom: 1px solid #000;
                padding: 0 3px 1px 3px;
                min-width: 80px;
                display: inline-block;
                color: transparent;
            }
            
            .print-spacer { flex: 1; min-width: 5px; }
            
            /* Checkboxes */
            .print-checkbox {
                display: inline-flex;
                align-items: center;
                gap: 2px;
                margin-right: 8px;
                font-size: 10px;
                white-space: nowrap;
            }
            
            .print-checkbox .box {
                width: 9px;
                height: 9px;
                border: 1px solid #000;
                display: inline-block;
                flex-shrink: 0;
                background: #fff;
            }
            
            /* ✅ BLUE: Checked checkbox */
            .print-checkbox .box.checked {
                background: #1e40af !important;
                border-color: #1e40af !important;
            }
            
            .print-checkbox .box-text {
                font-size: 10px;
                color: #000 !important;
            }
            
            /* Sections */
            .print-section {
                margin-top: 4px;
                margin-bottom: 2px;
            }
            
            .print-section-label {
                font-weight: 700;
                font-size: 10px;
                color: #000 !important;
            }
            
            /* Emergency person row */
            .print-emergency-title {
                font-weight: 700;
                font-size: 10px;
                margin-top: 4px;
                margin-bottom: 2px;
                color: #000 !important;
            }
            
            /* Footer form number */
            .print-footer {
                margin-top: 8px;
                padding-top: 4px;
                text-align: center;
                font-size: 8px;
                color: #000 !important;
                font-weight: 600;
            }
            
            .print-footer .rev {
                font-weight: normal;
                font-size: 7px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-wrapper">
        <div class="content-wrapper">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="main-content">
                <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center no-print">
                    <h1><i class="fas fa-user-plus me-2"></i>New Resident Registration</h1>
                    <div class="btn-toolbar">
                        <a href="residents.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Print Form (Portrait)
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

                <!-- SCREEN VERSION (DARK MODE) -->
                <form method="POST" action="" id="residentForm">
                    
                    <div class="form-section">
                        <div class="header-title">
                            <h4><i class="fas fa-university me-2"></i>ISABELA STATE UNIVERSITY</h4>
                            <p>Echague, Isabela</p>
                            <hr>
                            <p style="font-size: 11px; letter-spacing: 1px;">OFFICE OF STUDENT AFFAIRS &amp; SERVICES</p>
                            <p style="font-size: 11px; letter-spacing: 1px;">STUDENT HOUSING SERVICES</p>
                            <h5>Student Boarder's Data Profile</h5>
                            <p style="font-size: 11px;">ISU-ECHAGUE CAMPUS DORMITORY</p>
                        </div>

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

                    <!-- PERSONAL INFO -->
                    <div class="form-section">
                        <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                        <div class="row g-2">
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
                            <div class="col-md-3">
                                <label class="form-label">Age</label>
                                <input type="number" class="form-control" name="age" min="1" max="99" value="<?php echo htmlspecialchars($formData['age'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">No. of Siblings</label>
                                <input type="text" class="form-control auto-upper" name="no_siblings" placeholder="e.g., 3 siblings" value="<?php echo htmlspecialchars($formData['no_siblings'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Birth No.</label>
                                <input type="text" class="form-control auto-upper" name="birth_no" placeholder="Birth Certificate No." value="<?php echo htmlspecialchars($formData['birth_no'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Course <span class="required">*</span></label>
                                <input type="text" class="form-control auto-upper" name="course" placeholder="e.g., BSIT" value="<?php echo htmlspecialchars($formData['course'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-3">
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
                            <div class="col-md-5">
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
                                <input type="text" class="form-control auto-upper" name="school_last" value="<?php echo htmlspecialchars($formData['school_last'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">School Address</label>
                                <input type="text" class="form-control auto-upper" name="school_address" value="<?php echo htmlspecialchars($formData['school_address'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cultural Origin</label>
                                <input type="text" class="form-control auto-upper" name="cultural_origin" value="<?php echo htmlspecialchars($formData['cultural_origin'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Religion</label>
                                <input type="text" class="form-control auto-upper" name="religion" value="<?php echo htmlspecialchars($formData['religion'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Dialect Spoken</label>
                                <input type="text" class="form-control auto-upper" name="dialect" value="<?php echo htmlspecialchars($formData['dialect'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">CP No.</label>
                                <input type="text" class="form-control auto-upper" name="cp_no" value="<?php echo htmlspecialchars($formData['cp_no'] ?? ''); ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Complete Home Address</label>
                                <input type="text" class="form-control auto-upper" name="home_address" value="<?php echo htmlspecialchars($formData['home_address'] ?? ''); ?>">
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

                    <!-- PARENT/GUARDIAN -->
                    <div class="form-section">
                        <h5><i class="fas fa-users me-2"></i>Parent / Guardian Information</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Father's Education</label>
                                <input type="text" class="form-control auto-upper" name="father_education" value="<?php echo htmlspecialchars($formData['father_education'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mother's Education</label>
                                <input type="text" class="form-control auto-upper" name="mother_education" value="<?php echo htmlspecialchars($formData['mother_education'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Father's Occupation</label>
                                <input type="text" class="form-control auto-upper" name="father_occupation" value="<?php echo htmlspecialchars($formData['father_occupation'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mother's Occupation</label>
                                <input type="text" class="form-control auto-upper" name="mother_occupation" value="<?php echo htmlspecialchars($formData['mother_occupation'] ?? ''); ?>">
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
                                <input type="text" class="form-control auto-upper" name="emergency_name" value="<?php echo htmlspecialchars($formData['emergency_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Relationship <span class="required">*</span></label>
                                <input type="text" class="form-control auto-upper" name="emergency_relationship" value="<?php echo htmlspecialchars($formData['emergency_relationship'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Address <span class="required">*</span></label>
                                <input type="text" class="form-control auto-upper" name="emergency_address" value="<?php echo htmlspecialchars($formData['emergency_address'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contact No. <span class="required">*</span></label>
                                <input type="text" class="form-control auto-upper" name="emergency_contact" value="<?php echo htmlspecialchars($formData['emergency_contact'] ?? ''); ?>" required>
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
                                <input type="text" class="form-control auto-upper" name="plan_transfer_yes" value="<?php echo htmlspecialchars($formData['plan_transfer_yes'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6" id="planNoDiv" style="display:none;">
                                <label class="form-label">Why, If No?</label>
                                <input type="text" class="form-control auto-upper" name="plan_transfer_no" value="<?php echo htmlspecialchars($formData['plan_transfer_no'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="text-center mb-2 no-print">
                        <button type="submit" name="submit" class="btn btn-submit">
                            <i class="fas fa-save me-2"></i> Register Resident
                        </button>
                        <button type="reset" class="btn btn-outline-secondary ms-2" onclick="resetForm()">
                            <i class="fas fa-undo me-1"></i> Reset
                        </button>
                    </div>
                </form>
                
                <!-- ============================================================
                     ✅ COLORED PRINT FORM (PORTRAIT)
                     - Blue values (pangalan, course, etc.)
                     - Green dorm name
                     - Light blue profile title
                     - ISU Logo
                     ============================================================ -->
                <div class="print-form">
                    
                    <!-- HEADER: Logo | University Info | Photo Box -->
                    <div class="print-header">
                        <div class="print-header-left">
                            <img src="../../frontend/assets/images/isu-logo.png" 
                                 alt="ISU Logo" 
                                 class="print-logo"
                                 onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'width:75px;height:75px;border:1px dashed #999;display:flex;align-items:center;justify-content:center;font-size:8px;color:#999;text-align:center;\'>ISU<br>Logo</div>';">
                        </div>
                        <div class="print-header-center">
                            <p class="uni-name">ISABELA STATE UNIVERSITY</p>
                            <p>Echague, Isabela</p>
                            <p>OFFICE OF STUDENT AFFAIRS &amp; SERVICES</p>
                            <p>STUDENT HOUSING UNIT</p>
                            <p class="profile-title">Student Boarder's Data Profile</p>
                            <p class="dorm-name">ISU-ECHAGUE CAMPUS DORMITORY</p>
                        </div>
                        <div class="print-header-right">
                            <div class="print-photo-box">
                                <div class="photo-label">ID Passport<br>Size</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- BODY -->
                    <div class="print-body">
                        
                        <!-- Name + Date -->
                        <div class="print-row">
                            <span class="print-label">Name of Dorm Occupant:</span>
                            <span class="print-value" style="flex:1; min-width:200px;"><?php echo htmlspecialchars($formData['full_name'] ?? ''); ?></span>
                            <span class="print-label" style="margin-left:10px;">Date:</span>
                            <span class="print-value" style="min-width:100px;"><?php echo date('Y-m-d'); ?></span>
                        </div>
                        
                        <!-- Name -->
                        <div class="print-row">
                            <span class="print-label">Name:</span>
                            <span class="print-value" style="flex:1; min-width:300px;"><?php echo htmlspecialchars($formData['full_name'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Gender -->
                        <div class="print-row">
                            <span class="print-label">Gender:</span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['gender']) && $formData['gender'] == 'Female') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Female</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['gender']) && $formData['gender'] == 'Male') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Male</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['gender']) && $formData['gender'] == 'LGBT') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">LGBTQ: (Pls. specify)</span>
                            </span>
                            <span class="print-value" style="min-width:100px;"><?php echo htmlspecialchars($formData['gender_other'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Birthday / Age / No. of Siblings / Birth No. -->
                        <div class="print-row">
                            <span class="print-label">Birthday:</span>
                            <span class="print-value" style="min-width:80px;"><?php echo htmlspecialchars($formData['birth_date'] ?? ''); ?></span>
                            <span class="print-label" style="margin-left:8px;">Age:</span>
                            <span class="print-value" style="min-width:40px;"><?php echo htmlspecialchars($formData['age'] ?? ''); ?></span>
                            <span class="print-label" style="margin-left:8px;">No. of Siblings:</span>
                            <span class="print-value" style="min-width:50px;"><?php echo htmlspecialchars($formData['no_siblings'] ?? ''); ?></span>
                            <span class="print-label" style="margin-left:8px;">Birth No.:</span>
                            <span class="print-value" style="min-width:80px;"><?php echo htmlspecialchars($formData['birth_no'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Course / Year Level / Scholarship -->
                        <div class="print-row">
                            <span class="print-label">Course:</span>
                            <span class="print-value" style="min-width:90px;"><?php echo htmlspecialchars($formData['course'] ?? ''); ?></span>
                            <span class="print-label" style="margin-left:8px;">Year Level:</span>
                            <span class="print-value" style="min-width:70px;"><?php echo htmlspecialchars($formData['year_level'] ?? ''); ?></span>
                            <span class="print-label" style="margin-left:8px;">Scholarship grant:</span>
                            <span class="print-value" style="flex:1; min-width:100px;"><?php echo htmlspecialchars($formData['scholarship'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Other Sources -->
                        <div class="print-row">
                            <span class="print-label">Other sources of allowance for school:</span>
                            <span class="print-value" style="flex:1; min-width:250px;"><?php echo htmlspecialchars($formData['allowance_source'] ?? ''); ?></span>
                        </div>
                        
                        <!-- School Last Attended -->
                        <div class="print-row">
                            <span class="print-label">School last attended:</span>
                            <span class="print-value" style="flex:1; min-width:300px;"><?php echo htmlspecialchars($formData['school_last'] ?? ''); ?></span>
                        </div>
                        
                        <!-- School Address -->
                        <div class="print-row">
                            <span class="print-label">School Address:</span>
                            <span class="print-value" style="flex:1; min-width:300px;"><?php echo htmlspecialchars($formData['school_address'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Cultural Origin & Religion -->
                        <div class="print-row">
                            <span class="print-label">Cultural Origin (Pinanggalinan na Lahi):</span>
                            <span class="print-value" style="min-width:120px;"><?php echo htmlspecialchars($formData['cultural_origin'] ?? ''); ?></span>
                            <span class="print-label" style="margin-left:8px;">Religion:</span>
                            <span class="print-value" style="flex:1; min-width:100px;"><?php echo htmlspecialchars($formData['religion'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Dialect & CP No -->
                        <div class="print-row">
                            <span class="print-label">Dialect spoken:</span>
                            <span class="print-value" style="min-width:100px;"><?php echo htmlspecialchars($formData['dialect'] ?? ''); ?></span>
                            <span class="print-label" style="margin-left:8px;">CP No.:</span>
                            <span class="print-value" style="flex:1; min-width:100px;"><?php echo htmlspecialchars($formData['cp_no'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Complete Home Address -->
                        <div class="print-row">
                            <span class="print-label">Complete Home Address:</span>
                            <span class="print-value" style="flex:1; min-width:300px;"><?php echo htmlspecialchars($formData['home_address'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Civil Status -->
                        <div class="print-row">
                            <span class="print-label">Civil Status:</span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Married') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Married</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Single') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Single</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Separated') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Separated</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Abandoned') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Abandoned</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['civil_status']) && $formData['civil_status'] == 'Live-in') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Live-in</span>
                            </span>
                        </div>
                        
                        <!-- Father's Education -->
                        <div class="print-row">
                            <span class="print-label">Father's highest educational attainment:</span>
                            <span class="print-value" style="flex:1; min-width:250px;"><?php echo htmlspecialchars($formData['father_education'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Mother's Education -->
                        <div class="print-row">
                            <span class="print-label">Mother's highest educational attainment:</span>
                            <span class="print-value" style="flex:1; min-width:250px;"><?php echo htmlspecialchars($formData['mother_education'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Father's Occupation -->
                        <div class="print-row">
                            <span class="print-label">Father's Occupation:</span>
                            <span class="print-value" style="flex:1; min-width:250px;"><?php echo htmlspecialchars($formData['father_occupation'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Mother's Occupation -->
                        <div class="print-row">
                            <span class="print-label">Mother's Occupation:</span>
                            <span class="print-value" style="flex:1; min-width:250px;"><?php echo htmlspecialchars($formData['mother_occupation'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Emergency Person -->
                        <div class="print-emergency-title">
                            Person to be contacted in case of emergency:
                        </div>
                        
                        <div class="print-row">
                            <span class="print-label">Name:</span>
                            <span class="print-value" style="min-width:150px;"><?php echo htmlspecialchars($formData['emergency_name'] ?? ''); ?></span>
                            <span class="print-label" style="margin-left:8px;">Relationship:</span>
                            <span class="print-value" style="min-width:120px;"><?php echo htmlspecialchars($formData['emergency_relationship'] ?? ''); ?></span>
                        </div>
                        
                        <div class="print-row">
                            <span class="print-label">Address:</span>
                            <span class="print-value" style="flex:1; min-width:250px;"><?php echo htmlspecialchars($formData['emergency_address'] ?? ''); ?></span>
                        </div>
                        
                        <div class="print-row">
                            <span class="print-label">Contact No.:</span>
                            <span class="print-value" style="min-width:150px;"><?php echo htmlspecialchars($formData['emergency_contact'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Parent's Marital Status -->
                        <div class="print-row">
                            <span class="print-label">Parent's Marital Status:</span>
                        </div>
                        <div class="print-row" style="margin-left:10px;">
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Living Together') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Living together</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Separated') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Separated</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Abandoned') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Abandoned mother/father</span>
                            </span>
                        </div>
                        <div class="print-row" style="margin-left:10px;">
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Mother with Other Family') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Mother -- with other family</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['parents_marital_status']) && $formData['parents_marital_status'] == 'Father with Other Family') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Father -- with other family</span>
                            </span>
                        </div>
                        
                        <!-- Former Boarding House -->
                        <div class="print-row">
                            <span class="print-label">Length of months/years you stayed in your former boarding house:</span>
                            <span class="print-value" style="flex:1; min-width:120px;"><?php echo htmlspecialchars($formData['former_boarding_years'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Plan to Transfer -->
                        <div class="print-row">
                            <span class="print-label">Do you plan to transfer to the other boarding house after this semester?</span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['plan_transfer']) && $formData['plan_transfer'] == 'Yes') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">Yes</span>
                            </span>
                            <span class="print-checkbox">
                                <span class="box <?php echo (isset($formData['plan_transfer']) && $formData['plan_transfer'] == 'No') ? 'checked' : ''; ?>"></span>
                                <span class="box-text">No</span>
                            </span>
                        </div>
                        
                        <!-- Why Yes -->
                        <div class="print-row">
                            <span class="print-label">Why, if Yes?</span>
                            <span class="print-value" style="flex:1; min-width:250px;"><?php echo htmlspecialchars($formData['plan_transfer_yes'] ?? ''); ?></span>
                        </div>
                        
                        <!-- Why No -->
                        <div class="print-row">
                            <span class="print-label">Why, if No?</span>
                            <span class="print-value" style="flex:1; min-width:250px;"><?php echo htmlspecialchars($formData['plan_transfer_no'] ?? ''); ?></span>
                        </div>
                    </div>
                    
                    <!-- FOOTER -->
                    <div class="print-footer">
                        ISUE-OSS-SDP-025 &bull; Effectivity: 01/09/2013 &bull; Revision: 0
                    </div>
                </div>
                
            </main>
        </div>
        
        <footer class="footer">
            &copy; <?php echo date('Y'); ?> <span>Tap-and-Go Doorlock</span> System &bull; ISU-Echague Dormitory. All rights reserved.
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // AUTO UPPERCASE
        document.querySelectorAll('.auto-upper').forEach(function(input) {
            input.addEventListener('input', function() {
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(start, end);
            });
        });

        // TOGGLE PLAN TRANSFER
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

        // AUTO-CALCULATE AGE
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

        // RESET FORM
        function resetForm() {
            document.getElementById('residentForm').reset();
            document.getElementById('planYesDiv').style.display = 'none';
            document.getElementById('planNoDiv').style.display = 'none';
        }

        // AUTO-FOCUS
        document.querySelector('input[name="full_name"]').focus();
        
        // SIDEBAR TOGGLE
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
