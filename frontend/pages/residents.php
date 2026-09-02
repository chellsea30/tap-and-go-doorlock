<?php
/**
 * Tap-and-Go Doorlock - Residents List
 * WITH PROFILE PHOTO UPLOAD AND DISPLAY - FIXED PATH
 * PURE DARK MODE - No white backgrounds
 * WITH SHOW ENTRIES PAGINATION
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
// FIX: MOVE ALL REDIRECTS HERE (BEFORE INCLUDING HEADER)
// ============================================================
// Handle Delete Request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE users SET status = 'deleted' WHERE user_id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            header('Location: residents.php?msg=deleted');
            exit();
        } else {
            $error = "Failed to delete resident: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// HANDLE PROFILE PHOTO UPLOAD - FIXED
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/resident_photos/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $file_name = time() . '_' . $user_id . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;
        
        $image_info = getimagesize($_FILES['profile_photo']['tmp_name']);
        if ($image_info !== false) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($image_info['mime'], $allowed_types)) {
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                    // FIX: Store relative path from root
                    $photo_path = 'uploads/resident_photos/' . $file_name;
                    
                    $conn = getDBConnection();
                    $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $photo_path, $user_id);
                    
                    if ($stmt->execute()) {
                        $success = "Profile photo uploaded successfully!";
                    } else {
                        $error = "Failed to update database: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Failed to upload photo. Please check folder permissions.";
                }
            } else {
                $error = "Invalid file type. Please upload JPEG, PNG, GIF, or WEBP.";
            }
        } else {
            $error = "Uploaded file is not a valid image.";
        }
    } else {
        $error = "Please select a photo to upload.";
    }
}

// ============================================================
// HANDLE PHOTO REMOVE - FIXED
// ============================================================
if (isset($_GET['remove_photo']) && is_numeric($_GET['remove_photo'])) {
    $user_id = (int)$_GET['remove_photo'];
    
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!empty($row['profile_photo'])) {
            // FIX: Determine correct file path
            if (strpos($row['profile_photo'], 'uploads/') === 0) {
                $file_path = '../../' . $row['profile_photo'];
            } else {
                $file_path = '../../uploads/resident_photos/' . $row['profile_photo'];
            }
            
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $stmt = $conn->prepare("UPDATE users SET profile_photo = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $success = "Profile photo removed successfully!";
        } else {
            $error = "Failed to remove photo: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// NOW INCLUDE HEADER (AFTER ALL REDIRECTS)
// ============================================================
include '../includes/header.php'; 

// ============================================================
// INITIALIZE VARIABLES
// ============================================================
$residents = [];
$totalResidents = 0;
$totalPages = 1;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$error = '';
$success = '';
$delete_success = false;

// Valid per page options
$perPageOptions = [10, 25, 50, 100];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 10;
}

// Get dark mode
$darkModeClass = '';
$darkModeFromDb = 'false';
if (isset($_SESSION['admin_id'])) {
    try {
        $conn = getDBConnection();
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

// ============================================================
// GET RESIDENTS LIST
// ============================================================
try {
    $conn = getDBConnection();
    
    // Count total residents
    $countQuery = "SELECT COUNT(*) as total FROM users WHERE status != 'deleted'";
    $countParams = [];
    $types = "";
    
    if (!empty($search)) {
        $countQuery .= " AND (full_name LIKE ? OR student_id LIKE ? OR room_number LIKE ?)";
        $searchTerm = "%$search%";
        $countParams = [$searchTerm, $searchTerm, $searchTerm];
        $types = "sss";
    }
    
    $stmt = $conn->prepare($countQuery);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$countParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $totalRow = $result->fetch_assoc();
    $totalResidents = (int)($totalRow['total'] ?? 0);
    $stmt->close();
    
    $totalPages = ceil($totalResidents / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages) $page = $totalPages;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $perPage;
    
    // Get residents
    $query = "
        SELECT 
            u.*,
            u.profile_photo,
            rp.course,
            rp.year_level,
            rp.gender,
            rp.birth_date,
            rp.age,
            rp.religion,
            rp.dialect,
            rp.emergency_name,
            rp.emergency_relationship,
            rp.emergency_address,
            rp.date_registered,
            c.card_uid,
            c.status as card_status,
            ar.status as admission_status,
            ar.room_assignment
        FROM users u
        LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
        LEFT JOIN rfid_cards c ON u.user_id = c.user_id AND c.status = 'active'
        LEFT JOIN admission_records ar ON u.user_id = ar.user_id
        WHERE u.status != 'deleted'
    ";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.room_number LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
        $types = "sss";
    }
    
    $query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $residents = [];
    while ($row = $result->fetch_assoc()) {
        $residents[] = $row;
    }
    $stmt->close();
    
} catch (Exception $e) {
    $error = 'Error loading residents: ' . $e->getMessage();
    $residents = [];
}

$delete_success = isset($_GET['msg']) && $_GET['msg'] === 'deleted';

// ============================================================
// HELPER FUNCTION: GET PROFILE PHOTO PATH - FIXED
// ============================================================
function getProfilePhotoPath($photoPath) {
    if (empty($photoPath)) {
        return null;
    }
    
    // Check if path already has 'uploads/'
    if (strpos($photoPath, 'uploads/') === 0) {
        $fullPath = '../../' . $photoPath;
    } else {
        $fullPath = '../../uploads/resident_photos/' . $photoPath;
    }
    
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    return null;
}

// ============================================================
// HELPER FUNCTION: GET INITIALS
// ============================================================
function getInitials($name) {
    if (empty($name)) return '?';
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper($part[0]);
        }
    }
    return substr($initials, 0, 2) ?: '?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
           WRAPPER - FULL HEIGHT
           ============================================================ */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* ============================================================
           MAIN CONTENT - OFFSET FOR FIXED NAVBAR & SIDEBAR
           ============================================================ */
        .main-content {
            margin-left: 220px !important;
            margin-top: 56px !important;
            padding: 15px 25px !important;
            flex: 1;
            min-height: calc(100vh - 56px) !important;
            background: #0a0e1a !important;
        }
        
        /* ============================================================
           FOOTER
           ============================================================ */
        .footer {
            margin-left: 220px !important;
            margin-top: 0 !important;
            padding: 10px 25px !important;
            background: #0d1528 !important;
            border-top: 1px solid #1a2a4a !important;
            color: #606070 !important;
            font-size: 12px !important;
            text-align: center !important;
        }
        
        /* ============================================================
           DARK CARDS
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .card-body { background: #111827 !important; }
        .card .text-muted { color: #808090 !important; }
        .card h5 { color: #e0e0e0 !important; }
        
        /* ============================================================
           RESIDENT CARD - DARK
           ============================================================ */
        .resident-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 14px 18px;
            margin-bottom: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.3) !important;
            transition: all 0.3s ease;
            border-left: 3px solid #1a3a6a;
        }
        .resident-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.5) !important;
        }
        .resident-card .text-muted { color: #808090 !important; }
        .resident-card strong { color: #e0e0e0 !important; }
        
        /* ============================================================
           RESIDENT AVATAR
           ============================================================ */
        .resident-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid #2a2a4a;
            position: relative;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            cursor: pointer;
        }
        .resident-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .resident-avatar .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 18px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
        }
        .resident-avatar .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.8);
            color: white;
            text-align: center;
            font-size: 9px;
            padding: 2px 0;
            opacity: 0;
            transition: all 0.3s ease;
        }
        .resident-avatar:hover .upload-overlay {
            opacity: 1;
        }
        .resident-avatar .has-photo-overlay {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #111827;
        }
        .resident-info h5 { color: #e0e0e0 !important; margin: 0; font-size: 14px; font-weight: 600; }
        .resident-info .text-muted { color: #808090 !important; font-size: 11px; }
        
        /* ============================================================
           BUTTON STYLES - DARK (SMALLER)
           ============================================================ */
        .btn-action {
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            margin: 1px;
        }
        .btn-action:hover { transform: translateY(-1px); }
        
        .btn-admission { 
            background: #1a2a4a !important; 
            color: #93c5fd !important; 
            border-color: #1a3a6a !important; 
        }
        .btn-admission:hover { background: #1a3a6a !important; color: #bfdbfe !important; }
        
        .btn-profile { 
            background: #1a2a3a !important; 
            color: #6ee7b7 !important; 
            border-color: #065f46 !important; 
        }
        .btn-profile:hover { background: #065f46 !important; color: #a7f3d0 !important; }
        
        .btn-edit { 
            background: #2a1a0a !important; 
            color: #fcd34d !important; 
            border-color: #92400e !important; 
        }
        .btn-edit:hover { background: #92400e !important; color: #fde68a !important; }
        
        .btn-view { 
            background: #1a1a3a !important; 
            color: #a5b4fc !important; 
            border-color: #3730a3 !important; 
        }
        .btn-view:hover { background: #3730a3 !important; color: #c7d2fe !important; }
        
        .btn-delete { 
            background: #2a1a1a !important; 
            color: #f87171 !important; 
            border-color: #991b1b !important; 
        }
        .btn-delete:hover { background: #991b1b !important; color: #fca5a5 !important; }
        
        .btn-upload-photo { 
            background: #5b3a9a !important; 
            color: #e0d0ff !important; 
            border-color: #7c3aed !important; 
        }
        .btn-upload-photo:hover { background: #7c3aed !important; color: white !important; }
        
        .btn-remove-photo { 
            background: #7a3a0a !important; 
            color: #fbbf24 !important; 
            border-color: #d97706 !important; 
        }
        .btn-remove-photo:hover { background: #d97706 !important; color: white !important; }
        
        .btn-primary {
            background: #1a3a6a !important;
            border-color: #1a3a6a !important;
            color: white !important;
            padding: 5px 14px !important;
            font-size: 12px !important;
            border-radius: 8px !important;
        }
        .btn-primary:hover {
            background: #2a5a9a !important;
            border-color: #2a5a9a !important;
        }
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
            font-size: 12px !important;
            padding: 5px 12px !important;
            border-radius: 8px !important;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           BADGES - DARK
           ============================================================ */
        .badge-status { padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 500; }
        .badge-active { background: #065f46 !important; color: #6ee7b7 !important; }
        .badge-pending { background: #92400e !important; color: #fcd34d !important; }
        .badge-inactive { background: #2a2a3a !important; color: #808090 !important; }
        .badge-no-card { background: #2a2a3a !important; color: #808090 !important; }
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-info { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        
        /* ============================================================
           SEARCH BOX - DARK
           ============================================================ */
        .search-box { max-width: 380px; }
        .search-box .form-control {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 10px 0 0 10px;
            padding: 7px 14px;
            font-size: 13px;
            height: 36px;
        }
        .search-box .form-control::placeholder { color: #606070 !important; }
        .search-box .form-control:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .search-box .btn {
            background: #1a3a6a !important;
            border: 1px solid #1a3a6a !important;
            color: white !important;
            border-radius: 0 10px 10px 0;
            padding: 7px 14px;
            height: 36px;
        }
        .search-box .btn:hover { background: #2a5a9a !important; }
        .search-box .btn-outline-secondary {
            background: transparent !important;
            border-color: #2a2a4a !important;
            color: #808090 !important;
            border-radius: 10px !important;
            height: 36px;
            padding: 7px 12px !important;
            font-size: 12px !important;
        }
        .search-box .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
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
            margin-top: 12px;
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
           MODAL - DARK
           ============================================================ */
        .modal-content {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
        }
        .modal-header {
            border-bottom-color: #1a2a4a !important;
            padding: 12px 18px !important;
        }
        .modal-header .modal-title { color: #e0e0e0 !important; font-size: 16px; }
        .modal-body { color: #e0e0e0 !important; padding: 18px !important; }
        .modal-footer { border-top-color: #1a2a4a !important; padding: 12px 18px !important; }
        .modal-body .form-control {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            font-size: 13px;
        }
        .modal-body .form-control:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .modal-body .form-text { color: #808090 !important; font-size: 11px; }
        .modal-body .text-muted { color: #808090 !important; }
        .btn-close { filter: invert(1) !important; }
        .btn-secondary {
            background: #2a2a4a !important;
            border-color: #2a2a4a !important;
            color: #b0b0c0 !important;
            font-size: 12px !important;
            padding: 5px 14px !important;
            border-radius: 8px !important;
        }
        .btn-secondary:hover {
            background: #3a3a5a !important;
            color: white !important;
        }
        .btn-danger {
            background: #7a2a2a !important;
            border-color: #7a2a2a !important;
            color: white !important;
            font-size: 12px !important;
            padding: 5px 14px !important;
            border-radius: 8px !important;
        }
        .btn-danger:hover {
            background: #8a3a3a !important;
        }
        
        /* ============================================================
           ALERT - DARK
           ============================================================ */
        .alert-success {
            background: #065f46 !important;
            border-color: #065f46 !important;
            color: #6ee7b7 !important;
            font-size: 13px !important;
            padding: 10px 16px !important;
            border-radius: 10px !important;
        }
        .alert-danger {
            background: #7a2a2a !important;
            border-color: #7a2a2a !important;
            color: #f87171 !important;
            font-size: 13px !important;
            padding: 10px 16px !important;
            border-radius: 10px !important;
        }
        .alert .btn-close { filter: invert(1) !important; }
        
        /* ============================================================
           PAGE HEADER STYLES
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
            }
            .footer {
                margin-left: 0 !important;
                padding: 8px 15px !important;
            }
            .resident-card { padding: 12px; }
            .resident-actions { margin-top: 8px; }
            .resident-actions .btn-action {
                margin-bottom: 3px;
                font-size: 10px;
                padding: 3px 8px;
            }
            .pagination .page-link { padding: 4px 10px; font-size: 11px; }
            .pagination-container { padding: 8px 12px; }
            .page-info { font-size: 11px; }
        }
        
        /* ============================================================
           MISC DARK
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .small { font-size: 11px !important; }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center">
                <h1><i class="fas fa-users me-2"></i>Residents</h1>
                <div>
                    <a href="new-resident.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> New Resident
                    </a>
                </div>
            </div>

            <?php if ($delete_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Resident deleted successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

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

            <!-- Search Bar -->
            <div class="row mb-3">
                <div class="col-md-8">
                    <form method="GET" action="" class="search-box d-flex">
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               placeholder="Search by name, ID, course, or room..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="residents.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <span class="text-muted small">
                        <i class="fas fa-users me-1"></i>
                        Showing <?php echo count($residents); ?> of <?php echo $totalResidents; ?> residents
                        <?php if (!empty($search)): ?>
                            <br><span class="text-muted">(filtered)</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Residents List -->
            <?php if (empty($residents)): ?>
                <div class="card">
                    <div class="card-body text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-2"></i>
                        <h5 class="text-muted">No residents found</h5>
                        <?php if (!empty($search)): ?>
                            <p class="text-muted small">Try adjusting your search criteria</p>
                            <a href="residents.php" class="btn btn-outline-secondary btn-sm">Clear Search</a>
                        <?php else: ?>
                            <p class="text-muted small">Start by adding your first resident</p>
                            <a href="new-resident.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i> Add Resident
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($residents as $resident): 
                    $photoPath = $resident['profile_photo'] ?? '';
                    $hasPhoto = false;
                    $fullPhotoPath = '';
                    
                    // FIX: Get correct photo path
                    if (!empty($photoPath)) {
                        if (strpos($photoPath, 'uploads/') === 0) {
                            $fullPhotoPath = '../../' . $photoPath;
                        } else {
                            $fullPhotoPath = '../../uploads/resident_photos/' . $photoPath;
                        }
                        
                        if (file_exists($fullPhotoPath)) {
                            $hasPhoto = true;
                        }
                    }
                    
                    $initials = getInitials($resident['full_name'] ?? '');
                ?>
                    <div class="resident-card">
                        <div class="row align-items-center">
                            <!-- Avatar & Name -->
                            <div class="col-md-4 col-lg-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="resident-avatar" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#photoModal<?php echo $resident['user_id']; ?>"
                                         title="Click to upload/change photo">
                                        <?php if ($hasPhoto): ?>
                                            <img src="<?php echo $fullPhotoPath; ?>" 
                                                 alt="Photo of <?php echo htmlspecialchars($resident['full_name'] ?? ''); ?>"
                                                 onerror="this.style.display='none'; this.parentElement.querySelector('.no-photo').style.display='flex';">
                                            <span class="has-photo-overlay">
                                                <i class="fas fa-check-circle"></i>
                                            </span>
                                        <?php else: ?>
                                            <div class="no-photo"><?php echo $initials; ?></div>
                                        <?php endif; ?>
                                        <div class="upload-overlay">
                                            <i class="fas fa-camera me-1"></i> Upload
                                        </div>
                                    </div>
                                    <div class="resident-info">
                                        <h5><?php echo htmlspecialchars($resident['full_name'] ?? 'Unknown'); ?></h5>
                                        <span class="text-muted">
                                            <i class="fas fa-id-card me-1"></i>
                                            <?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?>
                                        </span>
                                        <br>
                                        <span class="text-muted">
                                            <i class="fas fa-graduation-cap me-1"></i>
                                            <?php echo htmlspecialchars($resident['course'] ?? 'N/A'); ?>
                                            - <?php echo htmlspecialchars($resident['year_level'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Room & Status -->
                            <div class="col-md-3 col-lg-3">
                                <div>
                                    <span class="text-muted small">Room</span>
                                    <br>
                                    <strong><?php echo htmlspecialchars($resident['room_number'] ?? 'Not Assigned'); ?></strong>
                                </div>
                                <div class="mt-1">
                                    <span class="text-muted small">Card Status</span>
                                    <br>
                                    <?php if (!empty($resident['card_uid'])): ?>
                                        <span class="badge-status badge-active">
                                            <i class="fas fa-check-circle me-1"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status badge-no-card">
                                            <i class="fas fa-times-circle me-1"></i> No Card
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Admission Status -->
                            <div class="col-md-2 col-lg-2">
                                <div>
                                    <span class="text-muted small">Admission</span>
                                    <br>
                                    <?php 
                                        $admStatus = $resident['admission_status'] ?? 'pending';
                                        if ($admStatus == 'active'): ?>
                                            <span class="badge-status badge-active">
                                                <i class="fas fa-check-circle me-1"></i> Active
                                            </span>
                                        <?php elseif ($admStatus == 'pending'): ?>
                                            <span class="badge-status badge-pending">
                                                <i class="fas fa-clock me-1"></i> Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status badge-inactive">
                                                <i class="fas fa-minus-circle me-1"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    ?>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="col-md-3 col-lg-4">
                                <div class="resident-actions d-flex flex-wrap gap-1">
                                    <button type="button" 
                                            class="btn btn-action btn-upload-photo"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#photoModal<?php echo $resident['user_id']; ?>">
                                        <i class="fas fa-camera me-1"></i> Photo
                                    </button>
                                    
                                    <a href="view-resident.php?id=<?php echo $resident['user_id']; ?>" 
                                       class="btn btn-action btn-view">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    
                                    <?php if (!empty($resident['admission_status'])): ?>
                                        <a href="view-admission.php?id=<?php echo $resident['user_id']; ?>" 
                                           class="btn btn-action btn-admission">
                                            <i class="fas fa-clipboard-list me-1"></i> Admission
                                        </a>
                                    <?php else: ?>
                                        <a href="admission-form.php?id=<?php echo $resident['user_id']; ?>" 
                                           class="btn btn-action btn-admission">
                                            <i class="fas fa-plus-circle me-1"></i> Add Admission
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($resident['course'])): ?>
                                        <a href="view-profile.php?id=<?php echo $resident['user_id']; ?>" 
                                           class="btn btn-action btn-profile">
                                            <i class="fas fa-user me-1"></i> Profile
                                        </a>
                                    <?php else: ?>
                                        <a href="edit-resident.php?id=<?php echo $resident['user_id']; ?>" 
                                           class="btn btn-action btn-profile">
                                            <i class="fas fa-plus-circle me-1"></i> Add Profile
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="edit-resident.php?id=<?php echo $resident['user_id']; ?>" 
                                       class="btn btn-action btn-edit">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </a>
                                    
                                    <button type="button" 
                                            class="btn btn-action btn-delete" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal<?php echo $resident['user_id']; ?>">
                                        <i class="fas fa-trash me-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PHOTO UPLOAD MODAL - FIXED -->
                    <div class="modal fade" id="photoModal<?php echo $resident['user_id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-camera me-2"></i>
                                        Profile Photo - <?php echo htmlspecialchars($resident['full_name']); ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <!-- Current Photo Preview - FIXED -->
                                    <div class="mb-3">
                                        <?php 
                                            $photoPath = $resident['profile_photo'] ?? '';
                                            $hasModalPhoto = false;
                                            $fullModalPhotoPath = '';
                                            
                                            if (!empty($photoPath)) {
                                                if (strpos($photoPath, 'uploads/') === 0) {
                                                    $fullModalPhotoPath = '../../' . $photoPath;
                                                } else {
                                                    $fullModalPhotoPath = '../../uploads/resident_photos/' . $photoPath;
                                                }
                                                
                                                if (file_exists($fullModalPhotoPath)) {
                                                    $hasModalPhoto = true;
                                                }
                                            }
                                        ?>
                                        
                                        <?php if ($hasModalPhoto): ?>
                                            <img src="<?php echo $fullModalPhotoPath; ?>" 
                                                 alt="Current Photo"
                                                 style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #2a2a4a;">
                                            <div class="mt-2">
                                                <a href="?remove_photo=<?php echo $resident['user_id']; ?>" 
                                                   class="btn btn-sm btn-remove-photo"
                                                   onclick="return confirm('Remove this photo?')">
                                                    <i class="fas fa-trash me-1"></i> Remove Photo
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div style="width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#1a3a6a,#2a5a9a);display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:40px;font-weight:700;color:white;">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div class="mt-2 text-muted small">
                                                <i class="fas fa-info-circle me-1"></i>
                                                No photo uploaded yet
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <hr>
                                    
                                    <!-- Upload Form -->
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="user_id" value="<?php echo $resident['user_id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Upload New Photo</label>
                                            <input type="file" 
                                                   class="form-control" 
                                                   name="profile_photo" 
                                                   accept="image/*"
                                                   required>
                                            <div class="form-text text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Max size: 2MB. Allowed: JPG, PNG, GIF, WEBP
                                            </div>
                                        </div>
                                        <button type="submit" name="upload_photo" class="btn btn-primary">
                                            <i class="fas fa-upload me-1"></i> Upload Photo
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DELETE CONFIRMATION MODAL -->
                    <div class="modal fade" id="deleteModal<?php echo $resident['user_id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title text-danger">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="text-center py-2">
                                        <i class="fas fa-user-times fa-3x text-danger mb-2"></i>
                                        <p class="mb-1">
                                            Are you sure you want to delete 
                                            <strong><?php echo htmlspecialchars($resident['full_name']); ?></strong>?
                                        </p>
                                        <p class="text-muted small">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Student ID: <?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?>
                                        </p>
                                        <p class="text-danger small">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            This action cannot be undone.
                                        </p>
                                    </div>
                                </div>
                                <div class="modal-footer justify-content-center">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                    <a href="?delete=<?php echo $resident['user_id']; ?>&page=<?php echo $page; ?>" class="btn btn-danger">
                                        <i class="fas fa-trash me-1"></i> Yes, Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1 || $totalResidents > 0): ?>
                <div class="pagination-container">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="page-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalResidents); ?> of <?php echo $totalResidents; ?> residents
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
                                            <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($endPage < $totalPages): ?>
                                            <li class="page-item"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
        </main>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System - ISU-Echague Dormitory. All rights reserved.
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // CHANGE PER PAGE
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        // SIDEBAR TOGGLE (mobile)
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
