<?php
/**
 * Tap-and-Go Doorlock - Residents Admission Form
 * WITH AUTO-FILL FROM RESIDENT DATA - DARK MODE - NO ROOM ASSIGNMENT
 * WITH FIXED NAVBAR, SIDEBAR, AND FOOTER
 * ✅ AUTO-FILL SEMESTER, SY BASED ON CURRENT DATE
 * ✅ PRINT LAYOUT MATCHES OFFICIAL ISU DORMITORY FORM (LANDSCAPE)
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
$room_assignment = 'Not Assigned';

// ============================================================
// ✅ AUTO-GENERATE SEMESTER, SY BASED ON CURRENT DATE
// ============================================================
function getCurrentSemesterSY() {
    $month = (int)date('n');
    $year = (int)date('Y');
    
    if ($month >= 8 && $month <= 12) {
        $semester = '1st Semester';
        $sy_start = $year;
        $sy_end = $year + 1;
    } elseif ($month >= 1 && $month <= 5) {
        $semester = '2nd Semester';
        $sy_start = $year - 1;
        $sy_end = $year;
    } else {
        $semester = 'Summer';
        $sy_start = $year - 1;
        $sy_end = $year;
    }
    
    return $semester . ', SY ' . $sy_start . '-' . $sy_end;
}

$auto_semester_sy = getCurrentSemesterSY();

// ============================================================
// GET RESIDENT DATA FOR AUTO-FILL
// ============================================================
if ($user_id > 0) {
    try {
        $conn = getDBConnection();
        
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
// CHECK IF ADMISSION ALREADY EXISTS
// ============================================================
$existing_admission = null;
if ($user_id > 0) {
    try {
        if (!isset($conn)) $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT * FROM admission_records 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing_admission = $result->fetch_assoc();
        $stmt->close();
        
        // Kung may existing admission, gamitin ang naka-save na data para ma-fill ang form
        if ($existing_admission) {
            $formData['semester_sy'] = $existing_admission['semester_sy'] ?? $auto_semester_sy;
            $formData['school_last'] = $existing_admission['school_last'] ?? '';
            $formData['school_address'] = $existing_admission['school_address'] ?? '';
            $formData['strand_track'] = $existing_admission['strand_track'] ?? '';
            $formData['course_taken'] = $existing_admission['course_taken'] ?? '';
            $formData['year_level_old'] = $existing_admission['year_level_old'] ?? '';
            $formData['former_bh'] = $existing_admission['former_bh'] ?? '';
            $formData['former_address'] = $existing_admission['former_address'] ?? '';
            $formData['student_signature'] = $existing_admission['student_signature'] ?? '';
            $formData['status'] = $existing_admission['status'] ?? 'pending';
        }
    } catch (Exception $e) {
        // Silently fail
    }
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
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
    
    if (empty($data['name']) || empty($data['course']) || empty($data['year_level'])) {
        $error = 'Please fill in all required fields (Name, Course, Year Level).';
    } else {
        try {
            $conn = getDBConnection();
            
            if ($user_id > 0) {
                $check = $conn->prepare("SELECT admission_id FROM admission_records WHERE user_id = ?");
                $check->bind_param("i", $user_id);
                $check->execute();
                $checkResult = $check->get_result();
                
                if ($checkResult->num_rows > 0) {
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
            } else {
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

// ============================================================
// PREPARE DISPLAY VALUES (with auto-fill fallback)
// ============================================================
$display = [
    'semester_sy' => $formData['semester_sy'] ?? $auto_semester_sy,
    'name' => $formData['name'] ?? '',
    'contact_number' => $formData['contact_number'] ?? '',
    'course' => $formData['course'] ?? '',
    'year_level' => $formData['year_level'] ?? '',
    'age' => $formData['age'] ?? '',
    'birth_date' => $formData['birth_date'] ?? '',
    'home_address' => $formData['home_address'] ?? '',
    'school_last' => $formData['school_last'] ?? '',
    'school_address' => $formData['school_address'] ?? '',
    'strand_track' => $formData['strand_track'] ?? '',
    'course_taken' => $formData['course_taken'] ?? '',
    'year_level_old' => $formData['year_level_old'] ?? '',
    'former_bh' => $formData['former_bh'] ?? '',
    'former_address' => $formData['former_address'] ?? '',
    'guardian_name' => $formData['guardian_name'] ?? '',
    'guardian_contact' => $formData['guardian_contact'] ?? '',
    'student_signature' => $formData['student_signature'] ?? '',
    'status' => $formData['status'] ?? 'pending',
];

// ============================================================
// HELPER: Get display value or blank line for print
// ============================================================
function printValue($value, $width = 30) {
    if (!empty($value)) {
        return '<span class="print-value">' . htmlspecialchars($value) . '</span>';
    }
    return '<span class="print-blank" style="min-width: ' . $width . 'px;">&nbsp;</span>';
}

// ============================================================
// GET ROOM ASSIGNMENT (for display)
// ============================================================
$room_display = 'Not Assigned';
if ($resident && !empty($resident['room_number'])) {
    $room_display = 'Room ' . $resident['room_number'];
} elseif ($existing_admission && !empty($existing_admission['room_assignment'])) {
    $room_display = $existing_admission['room_assignment'];
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
           SIDEBAR
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
           PAGE WRAPPER
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
           FOOTER
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
           FORM SECTION (Screen view)
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
        
        /* Auto-filled highlight */
        .form-control.auto-filled {
            border-color: #ffd700 !important;
            background: rgba(255, 215, 0, 0.05) !important;
        }
        
        .semester-sy-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            color: #0a0e1a;
            font-size: 9px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            animation: pulse-badge 2s infinite;
        }
        
        @keyframes pulse-badge {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .header-title {
            background: linear-gradient(135deg, #0a1628, #1a2a4a) !important;
            padding: 18px 25px;
            border-radius: 12px 12px 0 0;
            margin: -20px -25px 18px -25px;
            border-bottom: 1px solid #1e2a3a;
            text-align: center;
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
        
        .alert-info {
            background: rgba(59, 130, 246, 0.15) !important;
            border-color: #3b82f6 !important;
            color: #93c5fd !important;
            font-size: 13px;
            padding: 10px 16px;
            border-radius: 10px;
        }
        
        .btn-close { filter: invert(1) !important; }
        
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 { color: #e5e7eb !important; }
        .border-bottom { border-color: #1e2a3a !important; }
        .text-muted { color: #6b7280 !important; }
        .required { color: #ef4444 !important; margin-left: 2px; }
        
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
        .page-header h1 i { color: #ffd700; }
        
        /* ============================================================
           ✅ PRINT LAYOUT (LANDSCAPE - OFFICIAL FORM STYLE)
           ============================================================ */
        .print-form {
            display: none; /* Hidden on screen */
        }
        
        @page {
            size: A4 landscape;
            margin: 10mm;
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
            #admissionForm,
            main.main-content > *:not(.print-form) {
                display: none !important;
            }
            
            body, html {
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                font-family: 'Inter', Arial, sans-serif !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                min-height: auto !important;
                background: #ffffff !important;
            }
            
            /* Show the print form */
            .print-form {
                display: block !important;
                width: 100%;
                max-width: 1100px;
                margin: 0 auto;
                padding: 10px 15px;
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 11px;
                line-height: 1.5;
            }
            
            /* Print Header */
            .print-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 8px;
            }
            
            .print-header-left {
                width: 100px;
                text-align: center;
            }
            
            .print-logo {
                width: 85px;
                height: 85px;
                object-fit: contain;
            }
            
            .print-header-center {
                flex: 1;
                text-align: center;
                padding: 0 20px;
            }
            
            .print-header-center p {
                margin: 1px 0;
                font-size: 12px;
                color: #000 !important;
            }
            
            .print-header-center .uni-name {
                font-size: 14px;
                font-weight: 700;
                color: #000 !important;
            }
            
            .print-header-center .dorm-name {
                font-size: 13px;
                font-weight: 700;
                color: #000 !important;
                margin-top: 2px;
            }
            
            .print-header-center .form-title {
                font-size: 16px;
                font-weight: 800;
                letter-spacing: 1px;
                margin-top: 4px;
                color: #000 !important;
            }
            
            .print-header-center .semester-line {
                font-size: 12px;
                margin-top: 4px;
                color: #000 !important;
            }
            
            .print-header-right {
                width: 110px;
                height: 120px;
                border: 1.5px solid #333;
                background: #fafafa;
            }
            
            /* Print body */
            .print-body {
                margin-top: 8px;
                font-size: 12px;
                color: #000 !important;
            }
            
            .print-row {
                margin-bottom: 6px;
                display: flex;
                align-items: baseline;
                flex-wrap: wrap;
                gap: 6px;
            }
            
            .print-label {
                font-weight: 700;
                color: #000 !important;
                white-space: nowrap;
            }
            
            .print-value {
                font-weight: 500;
                border-bottom: 1px solid #333;
                padding: 0 4px 1px 4px;
                min-width: 80px;
                display: inline-block;
                color: #000 !important;
            }
            
            .print-blank {
                display: inline-block;
                border-bottom: 1px solid #333;
                padding: 0 4px 1px 4px;
                min-width: 100px;
                color: #000 !important;
            }
            
            .print-row .print-spacer {
                flex: 1;
            }
            
            .print-signature {
                margin-top: 25px;
                text-align: right;
                padding-right: 20px;
            }
            
            .print-signature .sig-line {
                display: inline-block;
                border-top: 1px solid #333;
                padding-top: 3px;
                min-width: 280px;
                text-align: center;
                font-size: 11px;
                color: #000 !important;
            }
            
            .print-footer {
                position: absolute;
                bottom: 8mm;
                left: 15px;
                font-size: 9px;
                color: #333 !important;
                font-style: italic;
            }
            
            .print-room-row {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                margin-top: 10px;
            }
            
            .print-room-row .room-line {
                display: inline-block;
                border-bottom: 1px solid #333;
                min-width: 220px;
                padding: 0 4px 1px 4px;
                color: #000 !important;
                font-weight: 500;
            }
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
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1e2a3a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-wrapper">
        <div class="content-wrapper">
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- MAIN CONTENT -->
            <main class="main-content">
                <!-- Page Header (screen only) -->
                <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center no-print">
                    <h1><i class="fas fa-clipboard-list me-2"></i>Admission Form</h1>
                    <div class="btn-toolbar">
                        <a href="residents.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Print (Landscape)
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
                                        <label class="form-label">
                                            Semester, SY <span class="required">*</span>
                                            <span class="semester-sy-badge">
                                                <i class="fas fa-magic me-1"></i>Auto
                                            </span>
                                        </label>
                                        <input type="text" 
                                               class="form-control auto-filled" 
                                               name="semester_sy" 
                                               id="semester_sy"
                                               placeholder="e.g., 1st Semester, SY 2025-2026" 
                                               value="<?php echo htmlspecialchars($display['semester_sy']); ?>" 
                                               required
                                               readonly>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Auto-generated base sa kasalukuyang petsa. 
                                            <a href="#" onclick="unlockSemesterField(event)" style="color: #ffd700;">I-edit manually</a>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>">
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
                                <input type="text" class="form-control" name="name" placeholder="Last Name, First Name, Middle Initial" value="<?php echo htmlspecialchars($display['name']); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                                <?php if ($resident): ?>
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Auto-filled from resident data</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contact Number <span class="required">*</span></label>
                                <input type="text" class="form-control" name="contact_number" placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($display['contact_number']); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Course <span class="required">*</span></label>
                                <input type="text" class="form-control" name="course" placeholder="e.g., BSIT" value="<?php echo htmlspecialchars($display['course']); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year Level <span class="required">*</span></label>
                                <select class="form-select" name="year_level" required <?php echo $resident ? 'disabled' : ''; ?>>
                                    <option value="">Select</option>
                                    <option value="1st Year" <?php echo ($display['year_level'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo ($display['year_level'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo ($display['year_level'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo ($display['year_level'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                    <option value="5th Year" <?php echo ($display['year_level'] == '5th Year') ? 'selected' : ''; ?>>5th Year</option>
                                </select>
                                <?php if ($resident): ?>
                                    <input type="hidden" name="year_level" value="<?php echo htmlspecialchars($display['year_level']); ?>">
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Auto-filled: <?php echo htmlspecialchars($display['year_level'] ?: 'N/A'); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Age</label>
                                <input type="number" class="form-control" name="age" min="1" max="99" value="<?php echo htmlspecialchars($display['age']); ?>" <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Birth Date</label>
                                <input type="date" class="form-control" name="birth_date" value="<?php echo htmlspecialchars($display['birth_date']); ?>" <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Complete Home Address <span class="required">*</span></label>
                                <input type="text" class="form-control" name="home_address" placeholder="House number, Street, Barangay, Municipality, Province" value="<?php echo htmlspecialchars($display['home_address']); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <!-- ===== EDUCATIONAL BACKGROUND ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-graduation-cap me-2"></i>Educational Background</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">School Last Attended</label>
                                <input type="text" class="form-control" name="school_last" placeholder="School name" value="<?php echo htmlspecialchars($display['school_last']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">School Address</label>
                                <input type="text" class="form-control" name="school_address" placeholder="School address" value="<?php echo htmlspecialchars($display['school_address']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOR FIRST-YEAR STUDENTS ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-user-graduate me-2"></i>For First-Year Students</h5>
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Strand/Track Taken</label>
                                <input type="text" class="form-control" name="strand_track" placeholder="e.g., STEM, ABM, HUMSS, TVL" value="<?php echo htmlspecialchars($display['strand_track']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ===== FOR HIGHER YEAR ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-user-graduate me-2"></i>For Higher Year</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Course Taken</label>
                                <input type="text" class="form-control" name="course_taken" placeholder="Course name" value="<?php echo htmlspecialchars($display['course_taken']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Year Level</label>
                                <select class="form-select" name="year_level_old">
                                    <option value="">Select</option>
                                    <option value="1st Year" <?php echo ($display['year_level_old'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo ($display['year_level_old'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo ($display['year_level_old'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo ($display['year_level_old'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
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
                                <input type="text" class="form-control" name="former_bh" placeholder="Boarding house or dorm name" value="<?php echo htmlspecialchars($display['former_bh']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address Area (if any)</label>
                                <input type="text" class="form-control" name="former_address" placeholder="Address of former boarding house" value="<?php echo htmlspecialchars($display['former_address']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ===== PARENT/GUARDIAN ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-users me-2"></i>Parent or Guardian</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Parent or Guardian's Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="guardian_name" placeholder="Full name of parent/guardian" value="<?php echo htmlspecialchars($display['guardian_name']); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number <span class="required">*</span></label>
                                <input type="text" class="form-control" name="guardian_contact" placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($display['guardian_contact']); ?>" required <?php echo $resident ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <!-- ===== STATUS ===== -->
                    <div class="form-section">
                        <h5><i class="fas fa-info-circle me-2"></i>Application Status</h5>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="pending" <?php echo ($display['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="active" <?php echo ($display['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($display['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
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
                                <input type="text" class="form-control" name="student_signature" placeholder="Print your full name (signature)" value="<?php echo htmlspecialchars($display['student_signature'] ?: $display['name']); ?>" required>
                            </div>
                        </div>
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
                
                <!-- ============================================================
                     ✅ PRINT FORM (LANDSCAPE - OFFICIAL ISU FORMAT)
                     Hidden on screen, only shows when printing
                     ============================================================ -->
                <div class="print-form">
                    <!-- HEADER with Logo, University info, and Photo box -->
                    <div class="print-header">
                        <div class="print-header-left">
                            <!-- ISU Logo - adjust path if needed -->
                            <img src="../../frontend/assets/img/isu-logo.png" 
                                 alt="ISU Logo" 
                                 class="print-logo"
                                 onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'width:85px;height:85px;border:1px dashed #999;display:flex;align-items:center;justify-content:center;font-size:9px;color:#999;\'>ISU Logo</div>';">
                        </div>
                        <div class="print-header-center">
                            <p class="uni-name">Isabela State University,</p>
                            <p>Echague, Isabela</p>
                            <p>Office of Student Affairs &amp; Services</p>
                            <p class="dorm-name">ISU -ECHAGUE CAMPUS DORMITORY</p>
                            <p class="form-title">ADMISSION FORM</p>
                            <p class="semester-line">
                                <span style="border-bottom: 1px solid #333; padding: 0 20px;"><?php echo htmlspecialchars($display['semester_sy']); ?></span>
                            </p>
                        </div>
                        <div class="print-header-right"></div>
                    </div>
                    
                    <!-- BODY -->
                    <div class="print-body">
                        <!-- Row 1: NAME + Contact -->
                        <div class="print-row">
                            <span class="print-label">NAME:</span>
                            <span class="print-value" style="min-width: 320px;"><?php echo htmlspecialchars($display['name']); ?></span>
                            <span class="print-spacer"></span>
                            <span class="print-label">Contact number:</span>
                            <span class="print-value" style="min-width: 180px;"><?php echo htmlspecialchars($display['contact_number']); ?></span>
                        </div>
                        
                        <!-- Row 2: Course, Yr level, Age, Birth Day -->
                        <div class="print-row">
                            <span class="print-label">Course:</span>
                            <span class="print-value" style="min-width: 150px;"><?php echo htmlspecialchars($display['course']); ?></span>
                            <span class="print-label">Yr. level:</span>
                            <span class="print-value" style="min-width: 80px;"><?php echo htmlspecialchars($display['year_level']); ?></span>
                            <span class="print-label">Age:</span>
                            <span class="print-value" style="min-width: 50px;"><?php echo htmlspecialchars($display['age']); ?></span>
                            <span class="print-label">Birth Day:</span>
                            <span class="print-value" style="min-width: 130px;"><?php echo htmlspecialchars($display['birth_date']); ?></span>
                        </div>
                        
                        <!-- Row 3: Complete Home Address -->
                        <div class="print-row">
                            <span class="print-label">Complete Home Address:</span>
                            <span class="print-value" style="flex: 1; min-width: 400px;"><?php echo htmlspecialchars($display['home_address']); ?></span>
                        </div>
                        
                        <!-- Row 4: School Last Attended + Sch. Address -->
                        <div class="print-row">
                            <span class="print-label">School Last Attended:</span>
                            <span class="print-value" style="min-width: 280px;"><?php echo htmlspecialchars($display['school_last']); ?></span>
                            <span class="print-label">, Sch. Address:</span>
                            <span class="print-value" style="flex: 1; min-width: 250px;"><?php echo htmlspecialchars($display['school_address']); ?></span>
                        </div>
                        
                        <!-- Row 5: First-year strand -->
                        <div class="print-row">
                            <span class="print-label">(For first-year students) Strand/tract taken:</span>
                            <span class="print-value" style="flex: 1; min-width: 300px;"><?php echo htmlspecialchars($display['strand_track']); ?></span>
                        </div>
                        
                        <!-- Row 6: Higher year course + Yr level -->
                        <div class="print-row">
                            <span class="print-label">(For Higher year) Course taken:</span>
                            <span class="print-value" style="min-width: 200px;"><?php echo htmlspecialchars($display['course_taken']); ?></span>
                            <span class="print-label">Yr. level:</span>
                            <span class="print-value" style="flex: 1; min-width: 100px;"><?php echo htmlspecialchars($display['year_level_old']); ?></span>
                        </div>
                        
                        <!-- Row 7: Old students -->
                        <div class="print-row">
                            <span class="print-label">For Old Students: Name of BH/Dorm. you came from if any:</span>
                            <span class="print-value" style="flex: 1; min-width: 250px;"><?php echo htmlspecialchars($display['former_bh']); ?></span>
                        </div>
                        
                        <!-- Row 8: Address Area -->
                        <div class="print-row">
                            <span class="print-label" style="margin-left: 180px;">Address Area If any:</span>
                            <span class="print-value" style="flex: 1; min-width: 300px;"><?php echo htmlspecialchars($display['former_address']); ?></span>
                        </div>
                        
                        <!-- Row 9: Parent/Guardian -->
                        <div class="print-row">
                            <span class="print-label">Parent or Guardian's Name:</span>
                            <span class="print-value" style="flex: 1; min-width: 350px;"><?php echo htmlspecialchars($display['guardian_name']); ?></span>
                        </div>
                        
                        <!-- Row 10: Guardian Contact -->
                        <div class="print-row">
                            <span class="print-label" style="margin-left: 180px;">Contact Number:</span>
                            <span class="print-value" style="flex: 1; min-width: 350px;"><?php echo htmlspecialchars($display['guardian_contact']); ?></span>
                        </div>
                        
                        <!-- ROOM ASSIGNMENT + SIGNATURE -->
                        <div class="print-room-row">
                            <div>
                                <span class="print-label">ROOM ASSIGNMENT:</span>
                                <span class="room-line"><?php echo htmlspecialchars($room_display); ?></span>
                            </div>
                        </div>
                        
                        <div class="print-signature">
                            <div class="sig-line">
                                <strong>Student's name and signature</strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FOOTER -->
                    <div class="print-footer">
                        ISUE-OSAS-DAF-III<br>
                        Effective July 18, 2024
                    </div>
                </div>
                
            </main>
        </div>
        
        <!-- FOOTER -->
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
        // UNLOCK SEMESTER FIELD (Manual edit)
        // ============================================================
        function unlockSemesterField(event) {
            event.preventDefault();
            const field = document.getElementById('semester_sy');
            field.removeAttribute('readonly');
            field.classList.remove('auto-filled');
            field.style.borderColor = '#ffd700';
            field.style.background = 'rgba(255, 215, 0, 0.1)';
            field.focus();
            
            const badge = document.querySelector('.semester-sy-badge');
            if (badge) {
                badge.innerHTML = '<i class="fas fa-pen me-1"></i>Editing';
                badge.style.background = '#ef4444';
                badge.style.color = 'white';
            }
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
