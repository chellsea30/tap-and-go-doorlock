<?php
/**
 * Tap-and-Go Doorlock - Password Reset Requests Management (Admin Only)
 */

session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication (Admin only)
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

// Include header
include '../includes/header.php'; 

$conn = getDBConnection();
$error = '';
$success = '';

// ============================================================
// HANDLE APPROVE/DENY
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $request_id = (int)$_POST['request_id'];
        $new_password = $_POST['new_password'] ?? '';
        $admin_response = trim($_POST['admin_response'] ?? '');
        
        if (empty($new_password) || strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Get student info
            $stmt = $conn->prepare("
                SELECT s.*, r.student_id 
                FROM password_reset_requests r
                JOIN student_users s ON r.student_id = s.student_id
                WHERE r.request_id = ?
            ");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();
            $stmt->close();
            
            if ($student) {
                // Update password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE student_users SET password_hash = ? WHERE student_id = ?");
                $stmt->bind_param("si", $new_hash, $student['student_id']);
                $stmt->execute();
                $stmt->close();
                
                // Update request status
                $stmt = $conn->prepare("
                    UPDATE password_reset_requests 
                    SET status = 'approved', admin_response = ?, responded_at = NOW() 
                    WHERE request_id = ?
                ");
                $stmt->bind_param("si", $admin_response, $request_id);
                $stmt->execute();
                $stmt->close();
                
                logAudit($_SESSION['admin_id'], 'Password Reset Approved', "Approved reset for student: {$student['full_name']}");
                $success = "✅ Password reset approved! Student can now login with the new password.";
            }
        }
    }
    
    if (isset($_POST['deny'])) {
        $request_id = (int)$_POST['request_id'];
        $admin_response = trim($_POST['admin_response'] ?? '');
        
        $stmt = $conn->prepare("
            UPDATE password_reset_requests 
            SET status = 'denied', admin_response = ?, responded_at = NOW() 
            WHERE request_id = ?
        ");
        $stmt->bind_param("si", $admin_response, $request_id);
        $stmt->execute();
        $stmt->close();
        
        logAudit($_SESSION['admin_id'], 'Password Reset Denied', "Denied reset for request ID: $request_id");
        $success = "✅ Request denied successfully.";
    }
}

// ============================================================
// GET REQUESTS
// ============================================================
$pendingRequests = [];
$historyRequests = [];

$result = $conn->query("
    SELECT r.*, s.full_name, s.username, s.email 
    FROM password_reset_requests r
    JOIN student_users s ON r.student_id = s.student_id
    WHERE r.status = 'pending'
    ORDER BY r.requested_at ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pendingRequests[] = $row;
    }
}

$result = $conn->query("
    SELECT r.*, s.full_name, s.username, s.email 
    FROM password_reset_requests r
    JOIN student_users s ON r.student_id = s.student_id
    WHERE r.status != 'pending'
    ORDER BY r.responded_at DESC
    LIMIT 20
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $historyRequests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Requests - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            flex-shrink: 0;
        }
        .stat-number { font-size: 28px; font-weight: 700; color: #1a1a2e; margin: 0; }
        .stat-label { font-size: 13px; color: #6b7280; margin: 0; }
        .request-card {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            border-left: 4px solid #f59e0b;
            transition: all 0.3s ease;
        }
        .request-card:hover { transform: translateX(4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .request-card .student { font-weight: 600; color: #1a1a2e; font-size: 16px; }
        .request-card .detail { font-size: 13px; color: #6b7280; }
        .request-card .reason { color: #4b5563; margin: 8px 0; }
        .request-card .response-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px 15px;
            margin-top: 10px;
        }
        .request-card.history-approved { border-left-color: #10b981; opacity: 0.8; }
        .request-card.history-denied { border-left-color: #ef4444; opacity: 0.8; }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .card-header {
            background: white;
            border-bottom: 1px solid #f0f2f5;
            border-radius: 16px 16px 0 0;
            padding: 15px 20px;
        }
        .card-header h5 { margin: 0; font-weight: 600; color: #1a1a2e; }
        .card-body { padding: 20px; }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 10px 14px;
            font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1a3a6a;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.1);
        }
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            border: none;
            padding: 6px 20px;
            border-radius: 10px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(26,58,106,0.3); color: white; }
        .btn-approve { background: #10b981; border: none; padding: 6px 20px; border-radius: 10px; font-weight: 600; color: white; transition: all 0.3s ease; }
        .btn-approve:hover { background: #059669; }
        .btn-deny { background: #ef4444; border: none; padding: 6px 20px; border-radius: 10px; font-weight: 600; color: white; transition: all 0.3s ease; }
        .btn-deny:hover { background: #dc2626; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-denied { background: #fecaca; color: #991b1b; }
        body.dark-mode .stat-card { background: #1a1a2e !important; }
        body.dark-mode .stat-number { color: #e0e0e0 !important; }
        body.dark-mode .request-card { background: #1a1a2e !important; }
        body.dark-mode .request-card .student { color: #e0e0e0 !important; }
        body.dark-mode .request-card .detail { color: #9090a0 !important; }
        body.dark-mode .request-card .reason { color: #b0b0c0 !important; }
        body.dark-mode .request-card .response-box { background: #15152a !important; }
        body.dark-mode .card { background: #1a1a2e !important; }
        body.dark-mode .card-header { background: #1a1a2e !important; border-bottom-color: #2a2a4a !important; }
        body.dark-mode .card-header h5 { color: #e0e0e0 !important; }
        body.dark-mode .form-control { background: #22223a !important; border-color: #2a2a4a !important; color: #e0e0e0 !important; }
        body.dark-mode .form-control:focus { background: #2a2a4a !important; border-color: #667eea !important; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-key me-2" style="color: #1a3a6a;"></i>Password Reset Requests</h1>
                    <span class="badge bg-success"><i class="fas fa-circle"></i> System Online</span>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f59e0b;"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count($pendingRequests); ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #10b981;"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count(array_filter($historyRequests, function($r) { return $r['status'] == 'approved'; })); ?></div>
                                <div class="stat-label">Approved</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #ef4444;"><i class="fas fa-times-circle"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count(array_filter($historyRequests, function($r) { return $r['status'] == 'denied'; })); ?></div>
                                <div class="stat-label">Denied</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #667eea;"><i class="fas fa-users"></i></div>
                            <div>
                                <div class="stat-number"><?php echo count($pendingRequests) + count($historyRequests); ?></div>
                                <div class="stat-label">Total Requests</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i>Pending Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingRequests)): ?>
                            <p class="text-center text-muted py-3"><i class="fas fa-check-circle me-2 text-success"></i> No pending requests</p>
                        <?php else: ?>
                            <?php foreach ($pendingRequests as $request): ?>
                                <div class="request-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="student">
                                                <i class="fas fa-user me-2" style="color: #667eea;"></i>
                                                <?php echo htmlspecialchars($request['full_name']); ?>
                                                <span class="badge badge-pending ms-2">Pending</span>
                                            </div>
                                            <div class="detail">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($request['student_id_number']); ?>
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-user me-1"></i>
                                                Username: <?php echo htmlspecialchars($request['username']); ?>
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($request['email']); ?>
                                            </div>
                                            <div class="reason">
                                                <strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                                            </div>
                                            <div class="detail">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                Requested: <?php echo date('M d, Y h:i A', strtotime($request['requested_at'])); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Approve/Deny Form -->
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <input type="text" class="form-control" name="new_password" placeholder="New password" required minlength="8">
                                            </div>
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" name="admin_response" placeholder="Response to student (optional)">
                                            </div>
                                            <div class="col-md-3">
                                                <div class="d-flex gap-2">
                                                    <button type="submit" name="approve" class="btn-approve">
                                                        <i class="fas fa-check me-1"></i> Approve
                                                    </button>
                                                    <button type="submit" name="deny" class="btn-deny">
                                                        <i class="fas fa-times me-1"></i> Deny
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="text-muted">Password must be at least 8 characters</small>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- History -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i>Request History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historyRequests)): ?>
                            <p class="text-center text-muted py-3">No history available</p>
                        <?php else: ?>
                            <?php foreach ($historyRequests as $request): ?>
                                <div class="request-card history-<?php echo $request['status']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="student">
                                                <i class="fas fa-user me-2" style="color: #667eea;"></i>
                                                <?php echo htmlspecialchars($request['full_name']); ?>
                                                <span class="badge <?php echo $request['status'] == 'approved' ? 'badge-approved' : 'badge-denied'; ?> ms-2">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </div>
                                            <div class="detail">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($request['student_id_number']); ?>
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($request['username']); ?>
                                            </div>
                                            <div class="reason">
                                                <strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                                            </div>
                                            <?php if (!empty($request['admin_response'])): ?>
                                                <div class="response-box">
                                                    <strong><i class="fas fa-reply me-1"></i> Admin Response:</strong>
                                                    <?php echo nl2br(htmlspecialchars($request['admin_response'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="detail mt-1">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                <?php echo date('M d, Y h:i A', strtotime($request['responded_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>