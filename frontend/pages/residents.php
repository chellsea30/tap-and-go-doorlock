<?php
/**
 * Tap-and-Go Doorlock - Residents List
 * WITH APPROVAL SYSTEM + BULK APPROVE + REJECTION REASONS + AUDIT LOGGING
 * Location: frontend/pages/residents.php
 *
 * ✅ FIXED: All modals working (Photo, Reject, Delete)
 * ✅ FIXED: Single modal per type (dynamic content)
 * ✅ FIXED: Modals outside bulkForm
 * ✅ FIXED: z-index + pointer-events para ma-click ang buttons
 * ✅ FIXED: View button now points to view-profile.php
 */

session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';
require_once '../../backend/helpers/audit.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

// ============================================================
// HANDLE BULK APPROVE (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_approve']) && !empty($_POST['ids'])) {
    $ids = array_filter(array_map('intval', $_POST['ids']));

    if (!empty($ids)) {
        try {
            $conn = getDBConnection();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            $stmt = $conn->prepare("
                SELECT user_id, full_name, student_id
                FROM users
                WHERE user_id IN ($placeholders)
                  AND approval_status = 'pending'
                  AND status != 'deleted'
            ");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $targets = [];
            while ($row = $result->fetch_assoc()) $targets[] = $row;
            $stmt->close();

            if (!empty($targets)) {
                $idsToUpdate = array_column($targets, 'user_id');
                $ph2 = implode(',', array_fill(0, count($idsToUpdate), '?'));
                $t2  = str_repeat('i', count($idsToUpdate));

                $adminId = (int)$_SESSION['admin_id'];
                $upd = $conn->prepare("
                    UPDATE users
                    SET approval_status = 'approved',
                        status = 'active',
                        approved_at = NOW(),
                        approved_by = ?,
                        rejection_reason = NULL,
                        rejected_at = NULL,
                        rejected_by = NULL
                    WHERE user_id IN ($ph2)
                ");
                $upd->bind_param("i" . $t2, $adminId, ...$idsToUpdate);
                $upd->execute();
                $upd->close();

                foreach ($targets as $t) {
                    auditLog(
                        $conn,
                        'Bulk Approve',
                        sprintf('Bulk-approved student: %s (%s)',
                            $t['full_name'],
                            $t['student_id'] ?? 'N/A'
                        )
                    );
                }
            }

            header('Location: residents.php?msg=bulk_approved&count=' . count($targets));
            exit();
        } catch (Exception $e) {
            header('Location: residents.php?msg=error');
            exit();
        }
    }
}

// ============================================================
// HANDLE DELETE
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];

    try {
        $conn = getDBConnection();

        $fetch = $conn->prepare("SELECT full_name, student_id FROM users WHERE user_id = ?");
        $fetch->bind_param("i", $delete_id);
        $fetch->execute();
        $prev = $fetch->get_result()->fetch_assoc();
        $fetch->close();

        $stmt = $conn->prepare("UPDATE users SET status = 'deleted' WHERE user_id = ?");
        $stmt->bind_param("i", $delete_id);

        if ($stmt->execute()) {
            $stmt->close();
            auditLog($conn, 'Delete Student',
                sprintf('Deleted student: %s', $prev['full_name'] ?? ('ID #' . $delete_id)));
            header('Location: residents.php?msg=deleted');
            exit();
        }
        $stmt->close();
    } catch (Exception $e) {}
}

// ============================================================
// HANDLE PHOTO UPLOAD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];

    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/resident_photos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $file_name = time() . '_' . $user_id . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        $image_info = getimagesize($_FILES['profile_photo']['tmp_name']);
        if ($image_info !== false) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($image_info['mime'], $allowed_types)) {
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                    $photo_path = 'uploads/resident_photos/' . $file_name;
                    $conn = getDBConnection();
                    $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $photo_path, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    auditLog($conn, 'Upload Resident Photo', "Uploaded photo for user ID: $user_id");
                    header('Location: residents.php?msg=photo_uploaded');
                    exit();
                }
            }
        }
    }
}

// ============================================================
// HANDLE PHOTO REMOVE
// ============================================================
if (isset($_GET['remove_photo']) && is_numeric($_GET['remove_photo'])) {
    $user_id = (int)$_GET['remove_photo'];

    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['profile_photo'])) {
            $file_path = (strpos($row['profile_photo'], 'uploads/') === 0)
                ? '../../' . $row['profile_photo']
                : '../../uploads/resident_photos/' . $row['profile_photo'];
            if (file_exists($file_path)) unlink($file_path);
        }

        $stmt = $conn->prepare("UPDATE users SET profile_photo = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        auditLog($conn, 'Remove Resident Photo', "Removed photo for user ID: $user_id");

        header('Location: residents.php?msg=photo_removed');
        exit();
    } catch (Exception $e) {}
}

// ============================================================
// NOW INCLUDE HEADER
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
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$error = '';
$success = '';

$perPageOptions = [10, 25, 50, 100];
if (!in_array($perPage, $perPageOptions)) $perPage = 10;

$darkModeClass = '';
if (isset($_SESSION['admin_id'])) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE admin_id = ? AND setting_key = 'dark_mode'");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['setting_value'] == 'true') $darkModeClass = 'dark-mode';
        }
        $stmt->close();
    } catch (Exception $e) {}
}

try {
    $conn = getDBConnection();

    $countQuery = "SELECT COUNT(*) as total FROM users WHERE status != 'deleted'";
    $countParams = [];
    $types = "";

    if ($statusFilter === 'pending') {
        $countQuery .= " AND approval_status = 'pending'";
    } elseif ($statusFilter === 'approved') {
        $countQuery .= " AND approval_status = 'approved'";
    } elseif ($statusFilter === 'rejected') {
        $countQuery .= " AND approval_status = 'rejected'";
    }

    if (!empty($search)) {
        $countQuery .= " AND (full_name LIKE ? OR student_id LIKE ? OR room_number LIKE ?)";
        $searchTerm = "%$search%";
        $countParams = [$searchTerm, $searchTerm, $searchTerm];
        $types = "sss";
    }

    $stmt = $conn->prepare($countQuery);
    if (!empty($types)) $stmt->bind_param($types, ...$countParams);
    $stmt->execute();
    $totalRow = $stmt->get_result()->fetch_assoc();
    $totalResidents = (int)($totalRow['total'] ?? 0);
    $stmt->close();

    $totalPages = max(1, (int)ceil($totalResidents / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $perPage;

    $query = "
        SELECT
            u.*,
            u.approval_status,
            u.approved_at,
            u.rejection_reason,
            u.rejected_at,
            u.portal_email,
            u.profile_photo,
            rp.course,
            rp.year_level,
            rp.gender,
            rp.birth_date,
            rp.age,
            c.card_uid,
            c.status as card_status
        FROM users u
        LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
        LEFT JOIN rfid_cards c ON u.user_id = c.user_id AND c.status = 'active'
        WHERE u.status != 'deleted'
    ";

    $params = [];
    $types = "";

    if ($statusFilter === 'pending') {
        $query .= " AND u.approval_status = 'pending'";
    } elseif ($statusFilter === 'approved') {
        $query .= " AND u.approval_status = 'approved'";
    } elseif ($statusFilter === 'rejected') {
        $query .= " AND u.approval_status = 'rejected'";
    }

    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.room_number LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
        $types = "sss";
    }

    $query .= " ORDER BY
        CASE u.approval_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            ELSE 3
        END,
        u.created_at DESC
        LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($query);
    if (!empty($types)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $residents[] = $row;
    $stmt->close();

    $pendingCount = $approvedCount = $rejectedCount = $allCount = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE approval_status='pending' AND status!='deleted'");
    if ($r && $row = $r->fetch_assoc()) $pendingCount = (int)$row['c'];
    $r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE approval_status='approved' AND status!='deleted'");
    if ($r && $row = $r->fetch_assoc()) $approvedCount = (int)$row['c'];
    $r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE approval_status='rejected' AND status!='deleted'");
    if ($r && $row = $r->fetch_assoc()) $rejectedCount = (int)$row['c'];
    $r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE status!='deleted'");
    if ($r && $row = $r->fetch_assoc()) $allCount = (int)$row['c'];

} catch (Exception $e) {
    $error = 'Error loading residents: ' . $e->getMessage();
}

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted':        $success = 'Resident deleted successfully!'; break;
        case 'approved':       $success = 'Student registration APPROVED successfully!'; break;
        case 'rejected':       $success = 'Student registration REJECTED.'; break;
        case 'photo_uploaded': $success = 'Profile photo uploaded successfully!'; break;
        case 'photo_removed':  $success = 'Profile photo removed successfully!'; break;
        case 'bulk_approved':  $c = (int)($_GET['count'] ?? 0); $success = "$c application(s) approved in bulk!"; break;
        case 'error':          $error = 'An error occurred. Please try again.'; break;
    }
}

function getInitials($name) {
    if (empty($name)) return '?';
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        if (!empty($part)) $initials .= strtoupper($part[0]);
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            overflow-x: hidden;
        }
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important; left: 0 !important; right: 0 !important;
            z-index: 1050 !important; height: 56px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }

        .sidebar {
            position: fixed !important;
            top: 56px !important; left: 0 !important; bottom: 0 !important;
            width: 220px !important;
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            overflow-y: auto !important;
            z-index: 1040 !important;
            padding-top: 10px !important;
            transition: width 0.3s ease, left 0.3s ease !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            margin: 2px 10px !important;
            font-size: 13px !important;
        }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.05) !important; color: #e0e0e0 !important; }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar .nav-link i { width: 18px; text-align: center; }
        .sidebar-footer { border-top-color: #1a2a4a !important; padding: 12px 16px !important; margin-top: 10px !important; }
        .sidebar-footer .text-muted { color: #606070 !important; font-size: 11px !important; }

        .main-content {
            margin-left: 220px !important;
            margin-top: 56px !important;
            padding: 15px 25px !important;
            min-height: calc(100vh - 56px) !important;
            background: #0a0e1a !important;
            position: relative; z-index: 1;
            transition: margin-left 0.3s ease !important;
        }
        .footer {
            margin-left: 220px !important;
            padding: 10px 25px !important;
            background: #0d1528 !important;
            border-top: 1px solid #1a2a4a !important;
            color: #606070 !important;
            font-size: 12px !important;
            text-align: center !important;
            transition: margin-left 0.3s ease !important;
        }

        .approval-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .approval-tab {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; background: #111827;
            border: 1px solid #1a2a4a; border-radius: 12px;
            color: #9090a0; font-size: 13px; font-weight: 600;
            text-decoration: none; transition: all 0.3s ease;
            cursor: pointer; white-space: nowrap;
        }
        .approval-tab:hover { background: #1a2a4a; color: #e0e0e0; transform: translateY(-2px); }
        .approval-tab.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            color: white; border-color: #2a5a9a;
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        .approval-tab.tab-pending.active { background: linear-gradient(135deg, #92400e, #d97706); border-color: #d97706; }
        .approval-tab.tab-approved.active { background: linear-gradient(135deg, #065f46, #10b981); border-color: #10b981; }
        .approval-tab.tab-rejected.active { background: linear-gradient(135deg, #7a2a2a, #ef4444); border-color: #ef4444; }
        .approval-tab .badge { font-size: 11px; padding: 2px 8px; border-radius: 20px; background: rgba(255,255,255,0.15); }
        .approval-tab.tab-pending .badge { background: #dc2626; color: white; animation: pulseBadge 1.5s infinite; }
        @keyframes pulseBadge {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .card-body { background: #111827 !important; }
        .card .text-muted { color: #808090 !important; }
        .card h5 { color: #e0e0e0 !important; }

        .resident-card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px !important;
            padding: 14px 18px; margin-bottom: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.3) !important;
            transition: all 0.3s ease;
            border-left: 3px solid #1a3a6a;
            overflow: hidden;
        }
        .resident-card .row { --bs-gutter-x: 0.5rem; --bs-gutter-y: 0.5rem; }
        .resident-card.pending-status {
            border-left-color: #f59e0b;
            background: linear-gradient(90deg, rgba(251, 191, 36, 0.05) 0%, #111827 15%) !important;
        }
        .resident-card.approved-status { border-left-color: #10b981; }
        .resident-card.rejected-status { border-left-color: #ef4444; opacity: 0.85; }
        .resident-card:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(0,0,0,0.5) !important; }
        .resident-card .text-muted { color: #808090 !important; }
        .resident-card strong { color: #e0e0e0 !important; }

        .resident-avatar {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; overflow: hidden;
            border: 2px solid #2a2a4a; position: relative;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            cursor: pointer;
        }
        .resident-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .resident-avatar .no-photo {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
            font-size: 16px; font-weight: 700; color: white;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
        }
        .resident-avatar .upload-overlay {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: rgba(0,0,0,0.8); color: white;
            text-align: center; font-size: 8px; padding: 2px 0;
            opacity: 0; transition: all 0.3s ease;
        }
        .resident-avatar:hover .upload-overlay { opacity: 1; }
        .resident-avatar .has-photo-overlay {
            position: absolute; top: -5px; right: -5px;
            background: #10b981; color: white; border-radius: 50%;
            width: 16px; height: 16px; font-size: 7px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #111827;
        }
        .resident-info h5 { color: #e0e0e0 !important; margin: 0; font-size: 13px; font-weight: 600; }
        .resident-info .text-muted { color: #808090 !important; font-size: 11px; }

        .btn-action {
            border-radius: 8px; padding: 4px 10px;
            font-size: 11px; font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid transparent; margin: 1px;
            white-space: nowrap;
        }
        .btn-action:hover { transform: translateY(-1px); }
        .btn-approve { background: #065f46 !important; color: #6ee7b7 !important; border-color: #10b981 !important; font-weight: 600; }
        .btn-approve:hover { background: #10b981 !important; color: #0a0e1a !important; }
        .btn-reject { background: #7a2a2a !important; color: #f87171 !important; border-color: #ef4444 !important; font-weight: 600; }
        .btn-reject:hover { background: #ef4444 !important; color: #0a0e1a !important; }
        .btn-admission { background: #1a2a4a !important; color: #93c5fd !important; border-color: #1a3a6a !important; }
        .btn-admission:hover { background: #1a3a6a !important; color: #bfdbfe !important; }
        .btn-edit { background: #2a1a0a !important; color: #fcd34d !important; border-color: #92400e !important; }
        .btn-edit:hover { background: #92400e !important; color: #fde68a !important; }
        .btn-view { background: #1a1a3a !important; color: #a5b4fc !important; border-color: #3730a3 !important; }
        .btn-view:hover { background: #3730a3 !important; color: #c7d2fe !important; }
        .btn-delete { background: #2a1a1a !important; color: #f87171 !important; border-color: #991b1b !important; }
        .btn-delete:hover { background: #991b1b !important; color: #fca5a5 !important; }
        .btn-upload-photo { background: #5b3a9a !important; color: #e0d0ff !important; border-color: #7c3aed !important; }
        .btn-upload-photo:hover { background: #7c3aed !important; color: white !important; }
        .btn-remove-photo { background: #7a3a0a !important; color: #fbbf24 !important; border-color: #d97706 !important; }
        .btn-remove-photo:hover { background: #d97706 !important; color: white !important; }
        .btn-audit { background: #1a2a2a !important; color: #6ee7b7 !important; border-color: #065f46 !important; font-size: 12px !important; padding: 6px 14px !important; border-radius: 8px !important; }
        .btn-audit:hover { background: #065f46 !important; color: white !important; }

        .btn-primary { background: #1a3a6a !important; border-color: #1a3a6a !important; color: white !important; padding: 5px 14px !important; font-size: 12px !important; border-radius: 8px !important; }
        .btn-primary:hover { background: #2a5a9a !important; border-color: #2a5a9a !important; }
        .btn-outline-secondary { border-color: #2a2a4a !important; color: #808090 !important; font-size: 12px !important; padding: 5px 12px !important; border-radius: 8px !important; }
        .btn-outline-secondary:hover { background: #2a2a4a !important; color: #e0e0e0 !important; }

        .badge-status { padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 500; display: inline-block; }
        .badge-active { background: #065f46 !important; color: #6ee7b7 !important; }
        .badge-pending { background: #92400e !important; color: #fcd34d !important; animation: pulseBadge 2s infinite; }
        .badge-rejected { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-inactive { background: #2a2a3a !important; color: #808090 !important; }
        .badge-no-card { background: #2a2a3a !important; color: #808090 !important; }
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-danger { background: #7a2a2a !important; color: #f87171 !important; }
        .badge-info { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }

        .search-box { max-width: 100%; }
        .search-box .form-control {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 10px 0 0 10px;
            padding: 7px 14px; font-size: 13px; height: 36px;
        }
        .search-box .form-control::placeholder { color: #606070 !important; }
        .search-box .form-control:focus { border-color: #2a5a9a !important; box-shadow: 0 0 0 3px rgba(26,58,106,0.3); }
        .search-box .btn { background: #1a3a6a !important; border: 1px solid #1a3a6a !important; color: white !important; border-radius: 0 10px 10px 0; padding: 7px 14px; height: 36px; }
        .search-box .btn:hover { background: #2a5a9a !important; }
        .search-box .btn-outline-secondary { background: transparent !important; border-color: #2a2a4a !important; color: #808090 !important; border-radius: 10px !important; height: 36px; padding: 7px 12px !important; font-size: 12px !important; }
        .search-box .btn-outline-secondary:hover { background: #2a2a4a !important; color: #e0e0e0 !important; }

        .pagination-container { background: #111827 !important; border: 1px solid #1a2a4a !important; border-radius: 12px !important; padding: 10px 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important; margin-top: 12px; }
        .pagination .page-link { border-radius: 8px; margin: 0 2px; border: none; color: #9090a0 !important; background: transparent !important; font-weight: 500; padding: 5px 12px; font-size: 12px; transition: all 0.3s ease; }
        .pagination .page-link:hover { background: #2a2a4a !important; color: #e0e0e0 !important; }
        .pagination .page-item.active .page-link { background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important; color: white !important; }
        .pagination .page-item.disabled .page-link { color: #4a4a5a !important; }
        .page-info { color: #808090 !important; font-size: 12px; }
        .page-info strong { color: #93c5fd !important; }

        .per-page-selector select { background: #1a1a2e !important; border: 1px solid #2a2a4a !important; color: #e0e0e0 !important; border-radius: 6px; padding: 3px 6px; font-size: 12px; }
        .per-page-selector label { color: #808090 !important; font-size: 12px; margin: 0; }

        .modal-content { background: #111827 !important; border: 1px solid #1a2a4a !important; border-radius: 12px !important; }
        .modal-header { border-bottom-color: #1a2a4a !important; padding: 12px 18px !important; }
        .modal-header .modal-title { color: #e0e0e0 !important; font-size: 16px; }
        .modal-body { color: #e0e0e0 !important; padding: 18px !important; }
        .modal-footer { border-top-color: #1a2a4a !important; padding: 12px 18px !important; }
        .modal-body .form-control { background: #1a1a2e !important; border: 1px solid #2a2a4a !important; color: #e0e0e0 !important; font-size: 13px; }
        .modal-body .form-control:focus { border-color: #2a5a9a !important; box-shadow: 0 0 0 3px rgba(26,58,106,0.3); }
        .modal-body .form-text { color: #808090 !important; font-size: 11px; }
        .btn-close { filter: invert(1) !important; }
        .btn-secondary { background: #2a2a4a !important; border-color: #2a2a4a !important; color: #b0b0c0 !important; font-size: 12px !important; padding: 5px 14px !important; border-radius: 8px !important; }
        .btn-secondary:hover { background: #3a3a5a !important; color: white !important; }
        .btn-danger { background: #7a2a2a !important; border-color: #7a2a2a !important; color: white !important; font-size: 12px !important; padding: 5px 14px !important; border-radius: 8px !important; }
        .btn-danger:hover { background: #8a3a3a !important; }

        .alert-success { background: #065f46 !important; border-color: #065f46 !important; color: #6ee7b7 !important; font-size: 13px !important; padding: 10px 16px !important; border-radius: 10px !important; }
        .alert-danger { background: #7a2a2a !important; border-color: #7a2a2a !important; color: #f87171 !important; font-size: 13px !important; padding: 10px 16px !important; border-radius: 10px !important; }
        .alert-warning { background: #4a3a1a !important; border-color: #4a3a1a !important; color: #fbbf24 !important; font-size: 13px !important; padding: 10px 16px !important; border-radius: 10px !important; }
        .alert .btn-close { filter: invert(1) !important; }

        .page-header { padding-bottom: 10px; margin-bottom: 15px; border-bottom: 1px solid #1a2a4a; }
        .page-header h1 { font-size: 20px; font-weight: 600; color: #e0e0e0; }
        .page-header h1 i { color: #1a3a6a; }

        .btn-new-resident { background: linear-gradient(135deg, #10b981, #059669) !important; border-color: #10b981 !important; color: white !important; font-size: 13px !important; padding: 8px 18px !important; border-radius: 10px !important; font-weight: 600 !important; transition: all 0.3s ease; white-space: nowrap; }
        .btn-new-resident:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3); color: white !important; }
        .btn-admission-form { background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important; border-color: #1a3a6a !important; color: white !important; font-size: 13px !important; padding: 8px 18px !important; border-radius: 10px !important; font-weight: 600 !important; transition: all 0.3s ease; white-space: nowrap; }
        .btn-admission-form:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26, 58, 106, 0.3); color: white !important; }

        .bulk-bar {
            background: #111827;
            border: 1px solid #1a2a4a;
            border-radius: 12px;
            padding: 10px 16px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .bulk-bar .form-check-input { background-color: #1a1a2e; border-color: #2a2a4a; cursor: pointer; }
        .bulk-bar .form-check-input:checked { background-color: #10b981; border-color: #10b981; }
        .bulk-bar .form-check-label { color: #9090a0; font-size: 12px; cursor: pointer; }
        .bulk-count { color: #fbbf24; font-weight: 600; font-size: 12px; }

        .form-check-input { background-color: #1a1a2e; border-color: #2a2a4a; cursor: pointer; }
        .form-check-input:checked { background-color: #10b981; border-color: #10b981; }

        .rejection-note {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            background: rgba(239, 68, 68, 0.15);
            border-left: 2px solid #ef4444;
            border-radius: 4px;
            font-size: 10px;
            color: #fca5a5;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ✅ FINAL FIX: Modal z-index + pointer-events */
        .modal { 
            z-index: 999999 !important; 
        }
        .modal-backdrop { 
            z-index: 999998 !important; 
            pointer-events: none !important;
        }
        .modal-backdrop.show {
            pointer-events: auto !important;
        }
        .modal-dialog { 
            pointer-events: auto !important; 
            z-index: 1000000 !important;
            position: relative;
        }
        .modal-content { 
            pointer-events: auto !important; 
            position: relative;
            z-index: 1000001 !important;
        }
        .modal-header, 
        .modal-body, 
        .modal-footer { 
            pointer-events: auto !important; 
            position: relative;
            z-index: 1000002 !important;
        }
        .modal button, 
        .modal a.btn, 
        .modal input, 
        .modal textarea, 
        .modal select,
        .modal .btn-close { 
            pointer-events: auto !important; 
            cursor: pointer !important;
            position: relative;
            z-index: 1000003 !important;
        }

        @media (max-width: 1400px) {
            .sidebar { width: 200px !important; }
            .main-content { margin-left: 200px !important; padding: 14px 20px !important; }
            .footer { margin-left: 200px !important; }
        }
        @media (max-width: 1200px) {
            .sidebar { width: 180px !important; }
            .sidebar .nav-link { font-size: 12px !important; padding: 7px 12px !important; }
            .main-content { margin-left: 180px !important; padding: 12px 15px !important; }
            .footer { margin-left: 180px !important; }
            .resident-actions .btn-action { font-size: 10px; padding: 3px 8px; }
        }
        @media (max-width: 992px) {
            .sidebar { left: -260px !important; width: 240px !important; }
            .sidebar.show { left: 0 !important; }
            .main-content { margin-left: 0 !important; padding: 12px 15px !important; }
            .footer { margin-left: 0 !important; padding: 8px 15px !important; }
            .page-header h1 { font-size: 18px; }
            .approval-tab { font-size: 12px; padding: 7px 14px; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 10px 12px !important; }
            .resident-card { padding: 12px; }
            .resident-actions { justify-content: flex-start !important; margin-top: 8px; }
            .resident-actions .btn-action { margin-bottom: 3px; font-size: 10px; padding: 3px 8px; }
            .pagination .page-link { padding: 4px 10px; font-size: 11px; }
            .pagination-container { padding: 8px 12px; }
            .page-info { font-size: 11px; }
            .btn-new-resident, .btn-admission-form { font-size: 11px !important; padding: 6px 12px !important; }
            .approval-tab { font-size: 11px; padding: 6px 10px; }
            .approval-tab .badge { font-size: 10px; padding: 1px 6px; }
            .resident-info h5 { font-size: 12px; }
            .resident-info .text-muted { font-size: 10px; }
            .resident-avatar { width: 42px; height: 42px; }
            .resident-avatar .no-photo { font-size: 14px; }
            .page-header { flex-direction: column; align-items: flex-start !important; gap: 10px; }
            .page-header .d-flex { width: 100%; }
            .page-header .btn { flex: 1; text-align: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 8px 10px !important; }
            .resident-card { padding: 10px; }
            .resident-avatar { width: 38px; height: 38px; }
            .resident-avatar .no-photo { font-size: 13px; }
            .btn-action { font-size: 9px !important; padding: 3px 6px !important; }
            .approval-tabs { gap: 5px; }
            .approval-tab { font-size: 10px; padding: 5px 8px; border-radius: 8px; }
            .page-header h1 { font-size: 16px; }
        }

        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .border-top { border-top-color: #1a2a4a !important; }
        hr { border-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-danger { color: #f87171 !important; }
        .text-success { color: #34d399 !important; }
        .text-warning { color: #fbbf24 !important; }
        .small { font-size: 11px !important; }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1e2a3a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header d-flex justify-content-between flex-wrap align-items-center gap-2">
            <h1><i class="fas fa-users me-2"></i>Residents</h1>
            <div class="d-flex gap-2 flex-wrap">
                <a href="audit-logs.php" class="btn btn-audit">
                    <i class="fas fa-history me-1"></i> Audit Logs
                </a>
                <a href="new-resident.php" class="btn btn-new-resident">
                    <i class="fas fa-user-plus me-1"></i> New Resident Form
                </a>
                <a href="admission-form.php" class="btn btn-admission-form">
                    <i class="fas fa-clipboard-list me-1"></i> Admission Form
                </a>
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

        <!-- APPROVAL TABS -->
        <div class="approval-tabs">
            <a href="residents.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>"
               class="approval-tab <?php echo empty($statusFilter) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                All Residents
                <span class="badge"><?php echo $allCount; ?></span>
            </a>
            <a href="residents.php?status=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
               class="approval-tab tab-pending <?php echo $statusFilter == 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                Pending Approval
                <span class="badge"><?php echo $pendingCount; ?></span>
            </a>
            <a href="residents.php?status=approved<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
               class="approval-tab tab-approved <?php echo $statusFilter == 'approved' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                Approved
                <span class="badge"><?php echo $approvedCount; ?></span>
            </a>
            <a href="residents.php?status=rejected<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
               class="approval-tab tab-rejected <?php echo $statusFilter == 'rejected' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i>
                Rejected
                <span class="badge"><?php echo $rejectedCount; ?></span>
            </a>
        </div>

        <?php if ($pendingCount > 0 && empty($statusFilter)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?php echo $pendingCount; ?> student registration(s)</strong> are waiting for your approval.
                <a href="residents.php?status=pending" class="ms-2 text-warning">
                    <u>Review now</u> <i class="fas fa-arrow-right ms-1"></i>
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- BULK FORM -->
        <form method="POST" action="" id="bulkForm">
            <input type="hidden" name="bulk_approve" value="1">

            <?php if ($pendingCount > 0): ?>
            <div class="bulk-bar">
                <div class="form-check m-0">
                    <input class="form-check-input" type="checkbox" id="selectAllPending" onclick="toggleAllPending(this)">
                    <label class="form-check-label" for="selectAllPending">
                        Select all pending on this page
                    </label>
                </div>
                <span class="bulk-count">
                    <i class="fas fa-check-square me-1"></i>
                    <span id="selectedCount">0</span> selected
                </span>
                <div class="ms-auto d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-action btn-view" onclick="clearSelection()">
                        <i class="fas fa-times me-1"></i> Clear
                    </button>
                    <button type="submit"
                            class="btn btn-action btn-approve"
                            onclick="return confirmBulkApprove()">
                        <i class="fas fa-check-double me-1"></i> Bulk Approve Selected
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <div class="row mb-3 g-2">
                <div class="col-md-8">
                    <div class="search-box d-flex">
                        <input type="text"
                               class="form-control"
                               id="searchInput"
                               name="search"
                               placeholder="Search by name, ID, or room..."
                               value="<?php echo htmlspecialchars($search); ?>"
                               onkeydown="if(event.key==='Enter'){event.preventDefault();applySearch();}">
                        <button type="button" class="btn" onclick="applySearch()">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="residents.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>"
                               class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end text-start">
                    <span class="text-muted small">
                        <i class="fas fa-users me-1"></i>
                        Showing <?php echo count($residents); ?> of <?php echo $totalResidents; ?> residents
                        <?php if (!empty($search)): ?>
                            <br><span class="text-muted">(filtered)</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- RESIDENTS LIST -->
            <?php if (empty($residents)): ?>
                <div class="card">
                    <div class="card-body text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-2"></i>
                        <h5 class="text-muted">
                            <?php if (!empty($statusFilter)): ?>
                                No <?php echo htmlspecialchars($statusFilter); ?> residents found
                            <?php else: ?>
                                No residents found
                            <?php endif; ?>
                        </h5>
                        <?php if (!empty($search)): ?>
                            <p class="text-muted small">Try adjusting your search criteria</p>
                            <a href="residents.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>"
                               class="btn btn-outline-secondary btn-sm">Clear Search</a>
                        <?php else: ?>
                            <p class="text-muted small">Start by adding your first resident</p>
                            <div class="d-flex gap-2 justify-content-center mt-2 flex-wrap">
                                <a href="new-resident.php" class="btn btn-new-resident btn-sm">
                                    <i class="fas fa-user-plus me-1"></i> New Resident Form
                                </a>
                                <a href="admission-form.php" class="btn btn-admission-form btn-sm">
                                    <i class="fas fa-clipboard-list me-1"></i> Admission Form
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($residents as $resident):
                    $photoPath = $resident['profile_photo'] ?? '';
                    $hasPhoto = false;
                    $fullPhotoPath = '';

                    if (!empty($photoPath)) {
                        if (strpos($photoPath, 'uploads/') === 0) {
                            $fullPhotoPath = '../../' . $photoPath;
                        } else {
                            $fullPhotoPath = '../../uploads/resident_photos/' . $photoPath;
                        }
                        if (file_exists($fullPhotoPath)) $hasPhoto = true;
                    }

                    $initials = getInitials($resident['full_name'] ?? '');
                    $approvalStatus = $resident['approval_status'] ?? 'pending';
                    $residentName = htmlspecialchars(addslashes($resident['full_name'] ?? 'Unknown'));
                    $residentStudentId = htmlspecialchars($resident['student_id'] ?? 'N/A');

                    $cardClass = '';
                    if ($approvalStatus === 'pending') $cardClass = 'pending-status';
                    elseif ($approvalStatus === 'approved') $cardClass = 'approved-status';
                    elseif ($approvalStatus === 'rejected') $cardClass = 'rejected-status';
                ?>
                    <div class="resident-card <?php echo $cardClass; ?>">
                        <div class="row align-items-center g-2">

                            <?php if ($approvalStatus === 'pending'): ?>
                            <div class="col-auto">
                                <input type="checkbox"
                                       class="form-check-input pending-checkbox"
                                       name="ids[]"
                                       value="<?php echo $resident['user_id']; ?>"
                                       onchange="updateSelectedCount()">
                            </div>
                            <?php endif; ?>

                            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="resident-avatar"
                                         onclick="openPhotoModal(<?php echo $resident['user_id']; ?>, '<?php echo $residentName; ?>', '<?php echo $fullPhotoPath; ?>', '<?php echo $initials; ?>')"
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
                                            <?php if (!empty($resident['year_level'])): ?>
                                                - <?php echo htmlspecialchars($resident['year_level']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-sm-3 col-md-3 col-lg-2">
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

                            <div class="col-6 col-sm-3 col-md-2 col-lg-2">
                                <div>
                                    <span class="text-muted small">Approval</span>
                                    <br>
                                    <?php if ($approvalStatus === 'pending'): ?>
                                        <span class="badge-status badge-pending">
                                            <i class="fas fa-clock me-1"></i> Pending
                                        </span>
                                    <?php elseif ($approvalStatus === 'approved'): ?>
                                        <span class="badge-status badge-active">
                                            <i class="fas fa-check-circle me-1"></i> Approved
                                        </span>
                                        <?php if (!empty($resident['portal_email'])): ?>
                                            <br><span class="badge-status badge-info mt-1" style="font-size: 9px;">
                                                <i class="fas fa-user-check me-1"></i> Portal Active
                                            </span>
                                        <?php else: ?>
                                            <br><span class="badge-status badge-warning mt-1" style="font-size: 9px;">
                                                <i class="fas fa-hourglass-half me-1"></i> No Portal
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge-status badge-rejected">
                                            <i class="fas fa-times-circle me-1"></i> Rejected
                                        </span>
                                        <?php if (!empty($resident['rejection_reason'])): ?>
                                            <br>
                                            <span class="rejection-note"
                                                  title="<?php echo htmlspecialchars($resident['rejection_reason']); ?>">
                                                <i class="fas fa-comment-alt me-1"></i>
                                                <?php echo htmlspecialchars(mb_strimwidth($resident['rejection_reason'], 0, 30, '…')); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-12 col-sm-12 col-md-3 col-lg-5">
                                <div class="resident-actions d-flex flex-wrap gap-1 justify-content-md-end justify-content-start">

                                    <?php if ($approvalStatus === 'pending'): ?>
                                        <a href="approve-resident.php?approve=<?php echo $resident['user_id']; ?>"
                                           class="btn btn-action btn-approve"
                                           onclick="return confirm('Approve this student registration?\n\nStudent: <?php echo htmlspecialchars(addslashes($resident['full_name'])); ?>\nID: <?php echo htmlspecialchars($resident['student_id']); ?>')">
                                            <i class="fas fa-check me-1"></i> Approve
                                        </a>
                                        <button type="button"
                                           class="btn btn-action btn-reject"
                                           onclick="openRejectModal(<?php echo $resident['user_id']; ?>, '<?php echo $residentName; ?>', '<?php echo $residentStudentId; ?>')">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                        <a href="view-profile.php?id=<?php echo $resident['user_id']; ?>"
                                           class="btn btn-action btn-view">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>

                                    <?php elseif ($approvalStatus === 'approved'): ?>
                                        <button type="button"
                                                class="btn btn-action btn-upload-photo"
                                                onclick="openPhotoModal(<?php echo $resident['user_id']; ?>, '<?php echo $residentName; ?>', '<?php echo $fullPhotoPath; ?>', '<?php echo $initials; ?>')">
                                            <i class="fas fa-camera me-1"></i> Photo
                                        </button>
                                        <a href="view-profile.php?id=<?php echo $resident['user_id']; ?>"
                                           class="btn btn-action btn-view">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <a href="edit-resident.php?id=<?php echo $resident['user_id']; ?>"
                                           class="btn btn-action btn-edit">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </a>
                                        <a href="admission-form.php?id=<?php echo $resident['user_id']; ?>"
                                           class="btn btn-action btn-admission">
                                            <i class="fas fa-clipboard-list me-1"></i> Admission
                                        </a>
                                        <button type="button"
                                                class="btn btn-action btn-delete"
                                                onclick="openDeleteModal(<?php echo $resident['user_id']; ?>, '<?php echo $residentName; ?>', '<?php echo $residentStudentId; ?>')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </button>

                                    <?php else: ?>
                                        <a href="view-profile.php?id=<?php echo $resident['user_id']; ?>"
                                           class="btn btn-action btn-view">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <a href="approve-resident.php?approve=<?php echo $resident['user_id']; ?>"
                                           class="btn btn-action btn-approve"
                                           onclick="return confirm('Re-approve this student?')">
                                            <i class="fas fa-redo me-1"></i> Re-Approve
                                        </a>
                                        <button type="button"
                                                class="btn btn-action btn-delete"
                                                onclick="openDeleteModal(<?php echo $resident['user_id']; ?>, '<?php echo $residentName; ?>', '<?php echo $residentStudentId; ?>')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </button>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1 || $totalResidents > 0): ?>
                <div class="pagination-container">
                    <div class="row align-items-center g-2">
                        <div class="col-md-6">
                            <div class="page-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalResidents); ?> of <?php echo $totalResidents; ?> residents
                                <span class="mx-1 text-muted">|</span>
                                <span class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-md-end justify-content-start gap-2 flex-wrap">
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

                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-end mb-0">
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        </li>
                                        <?php
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        if ($startPage > 1) echo '<li class="page-item"><span class="page-link">...</span></li>';
                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($endPage < $totalPages) echo '<li class="page-item"><span class="page-link">...</span></li>'; ?>

                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo '&per_page=' . $perPage; ?>">
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
        </form>
        <!-- END BULK FORM -->

        <!-- ============================================================ -->
        <!-- ✅ SINGLE MODALS -->
        <!-- ============================================================ -->

        <!-- PHOTO MODAL -->
        <div class="modal fade" id="photoModal" tabindex="-1" data-bs-backdrop="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-camera me-2"></i>
                            Profile Photo - <span id="photoModalName">—</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <div class="mb-3">
                            <img id="photoModalPreview" src="" alt="Current Photo"
                                 style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #2a2a4a;display:none;">
                            <div id="photoModalInitials" 
                                 style="width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#1a3a6a,#2a5a9a);display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:40px;font-weight:700;color:white;">
                                ?
                            </div>
                            <div class="mt-2" id="photoModalRemoveBtn" style="display:none;">
                                <a href="#" id="photoModalRemoveLink"
                                   class="btn btn-sm btn-remove-photo"
                                   onclick="return confirm('Remove this photo?')">
                                    <i class="fas fa-trash me-1"></i> Remove Photo
                                </a>
                            </div>
                            <div class="mt-2 text-muted small" id="photoModalNoPhoto" style="display:none;">
                                <i class="fas fa-info-circle me-1"></i>
                                No photo uploaded yet
                            </div>
                        </div>

                        <hr>

                        <form method="POST" action="residents.php" enctype="multipart/form-data">
                            <input type="hidden" name="user_id" id="photoModalUserId" value="">
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

        <!-- REJECT MODAL -->
        <div class="modal fade" id="rejectModal" tabindex="-1" data-bs-backdrop="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header" style="border-bottom-color: #7a2a2a !important;">
                        <h5 class="modal-title text-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            Reject Registration
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="approve-resident.php">
                        <div class="modal-body">
                            <input type="hidden" name="reject" id="rejectModalUserId" value="">

                            <div class="text-center mb-3">
                                <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#7a2a2a,#ef4444);display:flex;align-items:center;justify-content:center;margin:0 auto 15px;font-size:24px;color:white;">
                                    <i class="fas fa-user-times"></i>
                                </div>
                                <p style="color: #e0e0e0; margin-bottom: 5px;">
                                    Are you sure you want to <strong class="text-danger">REJECT</strong> this registration?
                                </p>
                                <p style="color: #fbbf24; font-weight: 600; margin-bottom: 15px;" id="rejectModalName">
                                    —
                                </p>
                                <p class="text-muted small">
                                    <i class="fas fa-id-card me-1"></i>
                                    <span id="rejectModalId">—</span>
                                </p>
                            </div>

                            <hr style="border-color: #1a2a4a;">

                            <div class="mb-2">
                                <label class="form-label" style="color: #d1d5db; font-size: 13px;">
                                    Reason for Rejection <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control"
                                          name="reason"
                                          id="rejectModalReason"
                                          rows="3"
                                          required
                                          placeholder="e.g., Incomplete requirements, Invalid information..."
                                          style="background: #1a1a2e; border: 1px solid #2a2a4a; color: #e0e0e0;"></textarea>
                                <div class="form-text text-muted" style="font-size:11px;">
                                    <i class="fas fa-info-circle me-1"></i>
                                    This will be shown to the student on their status page.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times-circle me-1"></i> Yes, Reject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- DELETE MODAL -->
        <div class="modal fade" id="deleteModal" tabindex="-1" data-bs-backdrop="false">
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
                                <strong id="deleteModalName">—</strong>?
                            </p>
                            <p class="text-muted small">
                                <i class="fas fa-info-circle me-1"></i>
                                Student ID: <span id="deleteModalId">—</span>
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
                        <a href="#" id="deleteModalConfirm" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Yes, Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <footer class="footer">
        &copy; <?php echo date('Y'); ?> Tap-and-Go Doorlock System - ISU-Echague Dormitory. All rights reserved.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // BULK SELECT
        // ============================================================
        function toggleAllPending(src) {
            document.querySelectorAll('.pending-checkbox').forEach(cb => {
                cb.checked = src.checked;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const n = document.querySelectorAll('.pending-checkbox:checked').length;
            const counter = document.getElementById('selectedCount');
            if (counter) counter.textContent = n;

            const total = document.querySelectorAll('.pending-checkbox').length;
            const selectAll = document.getElementById('selectAllPending');
            if (selectAll) {
                selectAll.checked = (total > 0 && n === total);
                selectAll.indeterminate = (n > 0 && n < total);
            }
        }

        function clearSelection() {
            document.querySelectorAll('.pending-checkbox').forEach(cb => cb.checked = false);
            const selectAll = document.getElementById('selectAllPending');
            if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
            updateSelectedCount();
        }

        function confirmBulkApprove() {
            const n = document.querySelectorAll('.pending-checkbox:checked').length;
            if (n === 0) {
                alert('Please select at least one pending application.');
                return false;
            }
            return confirm(`Approve ${n} selected application(s)?`);
        }

        // ============================================================
        // PHOTO MODAL
        // ============================================================
        function openPhotoModal(userId, fullName, photoPath, initials) {
            document.getElementById('photoModalName').textContent = fullName;
            document.getElementById('photoModalUserId').value = userId;

            const preview = document.getElementById('photoModalPreview');
            const initialsDiv = document.getElementById('photoModalInitials');
            const removeBtn = document.getElementById('photoModalRemoveBtn');
            const noPhotoMsg = document.getElementById('photoModalNoPhoto');
            const removeLink = document.getElementById('photoModalRemoveLink');

            preview.style.display = 'none';
            preview.src = '';
            initialsDiv.style.display = 'flex';
            removeBtn.style.display = 'none';
            noPhotoMsg.style.display = 'none';

            if (photoPath && photoPath !== '') {
                preview.src = photoPath;
                preview.style.display = 'block';
                initialsDiv.style.display = 'none';
                removeBtn.style.display = 'block';
                removeLink.href = '?remove_photo=' + userId;
            } else {
                initialsDiv.textContent = initials;
                initialsDiv.style.display = 'flex';
                noPhotoMsg.style.display = 'block';
            }

            const fileInput = document.querySelector('#photoModal input[type="file"]');
            if (fileInput) fileInput.value = '';

            const modalEl = document.getElementById('photoModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            // ✅ Force pointer events sa lahat ng buttons
            setTimeout(() => {
                modalEl.querySelectorAll('button, a.btn, input, textarea').forEach(el => {
                    el.style.pointerEvents = 'auto';
                    el.style.cursor = 'pointer';
                });
            }, 100);
        }

        // ============================================================
        // REJECT MODAL
        // ============================================================
        function openRejectModal(userId, fullName, studentId) {
            document.getElementById('rejectModalUserId').value = userId;
            document.getElementById('rejectModalName').textContent = fullName;
            document.getElementById('rejectModalId').textContent = studentId;
            document.getElementById('rejectModalReason').value = '';

            const modalEl = document.getElementById('rejectModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            setTimeout(() => {
                modalEl.querySelectorAll('button, a.btn, input, textarea').forEach(el => {
                    el.style.pointerEvents = 'auto';
                    el.style.cursor = 'pointer';
                });
            }, 100);
        }

        // ============================================================
        // DELETE MODAL
        // ============================================================
        function openDeleteModal(userId, fullName, studentId) {
            document.getElementById('deleteModalName').textContent = fullName;
            document.getElementById('deleteModalId').textContent = studentId;

            const page = <?php echo (int)$page; ?>;
            document.getElementById('deleteModalConfirm').href = '?delete=' + userId + '&page=' + page;

            const modalEl = document.getElementById('deleteModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            setTimeout(() => {
                modalEl.querySelectorAll('button, a.btn').forEach(el => {
                    el.style.pointerEvents = 'auto';
                    el.style.cursor = 'pointer';
                });
            }, 100);
        }

        // ============================================================
        // SEARCH / PAGINATION
        // ============================================================
        function applySearch() {
            const q = document.getElementById('searchInput').value.trim();
            const params = new URLSearchParams(window.location.search);
            if (q) params.set('search', q); else params.delete('search');
            params.set('page', 1);
            window.location.href = '?' + params.toString();
        }

        function changePerPage(value) {
            const params = new URLSearchParams(window.location.search);
            params.set('per_page', value);
            params.set('page', 1);
            window.location.href = '?' + params.toString();
        }

        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }

        setTimeout(function() {
            document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
                try { new bootstrap.Alert(alert).close(); } catch(e) {}
            });
        }, 5000);

        document.addEventListener('DOMContentLoaded', updateSelectedCount);
    </script>
</body>
</html>
