<?php
/**
 * Tap-and-Go Doorlock - Email Staff
 * Send emails to staff members
 * FIXED LAYOUT SAME AS DASHBOARD
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
// PAGINATION SETTINGS
// ============================================================
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPageOptions = [10, 25, 50, 100];
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 10;
}

// ============================================================
// GET STAFF LIST WITH PAGINATION
// ============================================================
$staffList = [];
$countResult = $conn->query("SELECT COUNT(*) as total FROM staff_users WHERE is_active = 1");
$totalStaff = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
    $totalStaff = (int)$row['total'];
}

$totalPages = ceil($totalStaff / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

$result = $conn->query("
    SELECT staff_id, full_name, email, department 
    FROM staff_users 
    WHERE is_active = 1 
    ORDER BY full_name 
    LIMIT $perPage OFFSET $offset
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffList[] = $row;
    }
}

// ============================================================
// HANDLE SEND EMAIL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $recipient_ids = isset($_POST['recipients']) ? $_POST['recipients'] : [];
    $send_to_all = isset($_POST['send_to_all']) ? true : false;
    
    if (empty($subject) || empty($message)) {
        $error = 'Please fill in subject and message.';
    } elseif (empty($recipient_ids) && !$send_to_all) {
        $error = 'Please select at least one recipient.';
    } else {
        $recipients = [];
        if ($send_to_all) {
            $allStaff = [];
            $result = $conn->query("SELECT staff_id, full_name, email FROM staff_users WHERE is_active = 1");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $allStaff[] = $row;
                }
            }
            $recipients = $allStaff;
        } else {
            foreach ($recipient_ids as $id) {
                $stmt = $conn->prepare("SELECT staff_id, full_name, email FROM staff_users WHERE staff_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $recipients[] = $row;
                }
                $stmt->close();
            }
        }
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($recipients as $recipient) {
            $stmt = $conn->prepare("
                INSERT INTO email_logs (
                    recipient_type, recipient_id, recipient_email, recipient_name, 
                    subject, message, sent_by, sent_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $sent_by = $_SESSION['admin_id'];
            $recipient_type = 'staff';
            $stmt->bind_param("sissssi", 
                $recipient_type, 
                $recipient['staff_id'], 
                $recipient['email'], 
                $recipient['full_name'],
                $subject, 
                $message, 
                $sent_by
            );
            
            if ($stmt->execute()) {
                $sent_count++;
            } else {
                $failed_count++;
            }
            $stmt->close();
        }
        
        $success = "✅ Email sent to $sent_count staff member(s).";
        if ($failed_count > 0) {
            $success .= " Failed: $failed_count";
        }
        
        logAudit($_SESSION['admin_id'], 'Send Staff Email', "Sent email to $sent_count staff members");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Staff - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* Same dark theme as dashboard */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
            padding-top: 70px !important;
        }
        .container-fluid { padding-top: 10px !important; }
        main { padding-top: 10px !important; margin-top: 0 !important; }
        
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1050 !important;
            height: 70px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            padding-top: 80px !important;
            min-height: calc(100vh - 70px) !important;
        }
        .sidebar .nav-link { color: #9090a0 !important; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.05) !important; color: #e0e0e0 !important; }
        .sidebar .nav-link.active { background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important; color: white !important; }
        
        .form-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .form-section h5 {
            color: #ffd700 !important;
            font-weight: 700;
            border-bottom: 2px solid #b8960f;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #b0b0c0 !important;
        }
        .form-control, .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: #e0e0e0 !important;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
            background: #1a1a2e !important;
            color: #e0e0e0 !important;
        }
        .form-control::placeholder { color: #606070 !important; }
        .required { color: #f87171 !important; }
        
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            padding: 10px 35px;
            border-radius: 12px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(26,58,106,0.4); color: white !important; }
        .btn-outline-secondary { border-color: #2a2a4a !important; color: #808090 !important; }
        .btn-outline-secondary:hover { background: #2a2a4a !important; color: #e0e0e0 !important; }
        
        .recipient-grid {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            background: #1a1a2e !important;
        }
        .recipient-grid .form-check { padding: 4px 0; }
        .recipient-grid .form-check:hover { background: rgba(255,255,255,0.03) !important; border-radius: 4px; }
        .recipient-grid .form-check-label { color: #e0e0e0 !important; }
        .recipient-grid .form-check-input {
            background-color: #1a1a2e !important;
            border-color: #2a2a4a !important;
        }
        .recipient-grid .form-check-input:checked {
            background-color: #1a3a6a !important;
            border-color: #1a3a6a !important;
        }
        .recipient-grid .text-muted { color: #808090 !important; }
        
        .staff-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a5a8a, #5a3a7a) !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 12px;
            margin-right: 8px;
        }
        .staff-count {
            background: #1a2a4a !important;
            color: #93c5fd !important;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        /* Pagination - Dark */
        .pagination-container {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
            margin-top: 15px;
        }
        .pagination .page-link {
            border-radius: 10px;
            margin: 0 3px;
            border: none;
            color: #9090a0 !important;
            background: transparent !important;
            font-weight: 500;
            padding: 8px 16px;
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
        .page-info { color: #808090 !important; font-size: 14px; }
        .page-info strong { color: #93c5fd !important; }
        
        .per-page-selector select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            color: #e0e0e0 !important;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 13px;
        }
        .per-page-selector select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
        }
        .per-page-selector label { color: #808090 !important; font-size: 13px; margin: 0; }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: #10b981 !important;
            color: #6ee7b7 !important;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: #ef4444 !important;
            color: #fca5a5 !important;
        }
        .btn-close { filter: invert(1) !important; }
        
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
            margin-right: 4px;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1a2a4a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
        
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .navbar { height: 60px !important; }
            .sidebar {
                padding-top: 70px !important;
                position: fixed;
                top: 60px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
                min-height: calc(100vh - 60px) !important;
            }
            .sidebar.show { left: 0; }
            .form-section { padding: 20px; }
            .pagination-container .row {
                flex-direction: column;
                gap: 10px;
            }
            .pagination-container .col-md-6 {
                width: 100%;
                text-align: center !important;
            }
            .pagination { justify-content: center !important; }
        }
    </style>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- HEADER - SAME AS DASHBOARD -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-tie me-2" style="color: #1a3a6a;"></i>
                        Email Staff
                        <span class="staff-count ms-2"><i class="fas fa-users me-1"></i> <?php echo $totalStaff; ?> Staff</span>
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <a href="emails.php" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="fas fa-arrow-left me-1"></i> Back
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

                <div class="form-section">
                    <h5><i class="fas fa-pen me-2"></i>Compose Email to Staff</h5>
                    
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="send_to_all" id="sendToAll" onchange="toggleAllStaff()">
                                        <label class="form-check-label" for="sendToAll" style="color:#e0e0e0 !important;">
                                            <strong>Send to All Staff</strong>
                                        </label>
                                    </div>
                                </div>
                                <label class="form-label">Select Staff Recipients</label>
                                <div class="recipient-grid" id="staffRecipients">
                                    <?php foreach ($staffList as $staff): ?>
                                        <div class="form-check">
                                            <input class="form-check-input staff-checkbox" type="checkbox" name="recipients[]" value="<?php echo $staff['staff_id']; ?>" id="staff_<?php echo $staff['staff_id']; ?>">
                                            <label class="form-check-label" for="staff_<?php echo $staff['staff_id']; ?>">
                                                <span class="staff-avatar">
                                                    <?php 
                                                        $initials = '';
                                                        $parts = explode(' ', $staff['full_name']);
                                                        foreach ($parts as $p) {
                                                            if (!empty($p)) $initials .= strtoupper($p[0]);
                                                        }
                                                        echo substr($initials, 0, 2) ?: '?';
                                                    ?>
                                                </span>
                                                <?php echo htmlspecialchars($staff['full_name']); ?>
                                                <span class="text-muted small">(<?php echo htmlspecialchars($staff['email']); ?>)</span>
                                                <?php if (!empty($staff['department'])): ?>
                                                    <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($staff['department']); ?></span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($staffList)): ?>
                                        <div class="text-center text-muted py-3">No staff members found</div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Pagination for staff list -->
                                <?php if ($totalPages > 1): ?>
                                <div class="pagination-container mt-2">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <div class="page-info">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalStaff); ?> of <?php echo $totalStaff; ?> staff
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                                                <div class="per-page-selector d-flex align-items-center gap-2">
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
                                                    <ul class="pagination pagination-sm mb-0">
                                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="?page=1<?php echo '&per_page=' . $perPage; ?>">
                                                                <i class="fas fa-angle-double-left"></i>
                                                            </a>
                                                        </li>
                                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo '&per_page=' . $perPage; ?>">
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
                                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo '&per_page=' . $perPage; ?>">
                                                                    <?php echo $i; ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>
                                                        <?php if ($endPage < $totalPages): ?>
                                                            <li class="page-item"><span class="page-link">...</span></li>
                                                        <?php endif; ?>
                                                        
                                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo '&per_page=' . $perPage; ?>">
                                                                <i class="fas fa-angle-right"></i>
                                                            </a>
                                                        </li>
                                                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo '&per_page=' . $perPage; ?>">
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
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Subject <span class="required">*</span></label>
                                <input type="text" class="form-control" name="subject" placeholder="Email subject" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Message <span class="required">*</span></label>
                                <textarea class="form-control" name="message" rows="6" placeholder="Write your message here..." required></textarea>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="send_email" class="btn btn-submit">
                                <i class="fas fa-paper-plane me-1"></i> Send to Staff
                            </button>
                            <a href="emails.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        function toggleAllStaff() {
            const checked = document.getElementById('sendToAll').checked;
            document.querySelectorAll('.staff-checkbox').forEach(cb => cb.checked = checked);
        }
        
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            const updateElement = document.getElementById('lastUpdate');
            if (updateElement) {
                updateElement.textContent = 'Updated: ' + timeString;
            }
        }
        setInterval(updateLastUpdateTime, 10000);
        document.addEventListener('DOMContentLoaded', updateLastUpdateTime);
        
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>