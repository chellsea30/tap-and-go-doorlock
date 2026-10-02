<?php
/**
 * Approve / Reject handler
 * Location: frontend/pages/approve-resident.php
 */
session_start();

require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';
require_once '../../backend/helpers/audit.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn    = getDBConnection();
$adminId = (int)$_SESSION['admin_id'];

// ============================================================
// APPROVE (GET)
// ============================================================
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $id = (int)$_GET['approve'];

    $stmt = $conn->prepare("SELECT user_id, full_name, student_id, approval_status FROM users WHERE user_id = ? AND status != 'deleted'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header('Location: residents.php?msg=error');
        exit();
    }

    $oldStatus = $user['approval_status'] ?? 'pending';

    $stmt = $conn->prepare("
        UPDATE users
        SET approval_status = 'approved',
            status = 'active',
            approved_at = NOW(),
            approved_by = ?,
            rejection_reason = NULL,
            rejected_at = NULL,
            rejected_by = NULL
        WHERE user_id = ?
    ");
    $stmt->bind_param("ii", $adminId, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        auditLog(
            $conn,
            'Approve Student',
            sprintf('Approved student: %s (%s)', $user['full_name'], $user['student_id'] ?? 'N/A')
        );
        header('Location: residents.php?msg=approved');
    } else {
        header('Location: residents.php?msg=error');
    }
    exit();
}

// ============================================================
// REJECT (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject']) && is_numeric($_POST['reject'])) {
    $id     = (int)$_POST['reject'];
    $reason = trim($_POST['reason'] ?? '');

    if ($reason === '') $reason = 'No reason provided.';
    if (mb_strlen($reason) > 1000) $reason = mb_substr($reason, 0, 1000);

    $stmt = $conn->prepare("SELECT user_id, full_name, student_id, approval_status FROM users WHERE user_id = ? AND status != 'deleted'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header('Location: residents.php?msg=error');
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE users
        SET approval_status = 'rejected',
            rejection_reason = ?,
            rejected_at = NOW(),
            rejected_by = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("sii", $reason, $adminId, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        auditLog(
            $conn,
            'Reject Student',
            sprintf('Rejected student: %s (%s) - Reason: %s',
                $user['full_name'],
                $user['student_id'] ?? 'N/A',
                $reason
            )
        );
        header('Location: residents.php?msg=rejected');
    } else {
        header('Location: residents.php?msg=error');
    }
    exit();
}

header('Location: residents.php');
exit();
