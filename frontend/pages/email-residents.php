<?php
/**
 * Tap-and-Go Doorlock - Email Residents
 * MANUAL PHPMailer - No Composer needed
 * FIXED LAYOUT SAME AS DASHBOARD
 */

session_start();

// ============================================================
// MANUAL LOADING NG PHPMailer (walang composer)
// ============================================================
require_once '../../includes/PHPMailer/src/PHPMailer.php';
require_once '../../includes/PHPMailer/src/SMTP.php';
require_once '../../includes/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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
// EMAIL CONFIGURATION - UPDATE THIS!
// ============================================================
$mail_config = [
    'host' => 'smtp.gmail.com',
    'username' => 'albanochellsea30@gmail.com',
    'password' => 'gofmjbdjelbvhrmo',
    'port' => 587,
    'from_email' => 'albanochellsea30@gmail.com',
    'from_name' => 'tap-and-go doorlock'
];

// ============================================================
// GET RESIDENT LIST
// ============================================================
$residentsList = [];
$result = $conn->query("
    SELECT u.user_id, u.full_name, u.email, u.room_number, rp.course, rp.year_level
    FROM users u
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $residentsList[] = $row;
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
            $recipients = $residentsList;
        } else {
            foreach ($recipient_ids as $id) {
                $stmt = $conn->prepare("SELECT user_id, full_name, email FROM users WHERE user_id = ?");
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
        $failed_emails = [];
        
        foreach ($recipients as $recipient) {
            try {
                $mail = new PHPMailer(true);
                
                $mail->isSMTP();
                $mail->Host       = $mail_config['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $mail_config['username'];
                $mail->Password   = $mail_config['password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $mail_config['port'];
                $mail->setLanguage('en');
                
                $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
                $mail->addAddress($recipient['email'], $recipient['full_name']);
                
                $mail->isHTML(true);
                $mail->Subject = $subject;
                
                $htmlMessage = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f5f7fa; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                        .header { text-align: center; border-bottom: 2px solid #ffd700; padding-bottom: 15px; margin-bottom: 20px; }
                        .header h2 { color: #1a3a6a; font-weight: 700; }
                        .content { color: #333; line-height: 1.6; }
                        .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 12px; color: #6b7280; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>🏠 Tap &amp; Go Doorlock</h2>
                            <p style='color:#6b7280;'>ISU-Echague Dormitory</p>
                        </div>
                        <div class='content'>
                            <p><strong>Dear " . htmlspecialchars($recipient['full_name']) . ",</strong></p>
                            <p>" . nl2br(htmlspecialchars($message)) . "</p>
                            <p style='margin-top:20px;'>Sincerely,<br><strong>Administrator</strong></p>
                        </div>
                        <div class='footer'>
                            <p>Tap &amp; Go Doorlock | ISU-Echague Dormitory</p>
                            <p>&copy; " . date('Y') . " All Rights Reserved</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $mail->Body    = $htmlMessage;
                $mail->AltBody = strip_tags($message);
                
                $mail->send();
                
                $stmt = $conn->prepare("
                    INSERT INTO email_logs (
                        recipient_type, recipient_id, recipient_email, recipient_name, 
                        subject, message, sent_by, sent_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $sent_by = $_SESSION['admin_id'];
                $recipient_type = 'resident';
                $stmt->bind_param("sissssi", 
                    $recipient_type, 
                    $recipient['user_id'], 
                    $recipient['email'], 
                    $recipient['full_name'],
                    $subject, 
                    $message, 
                    $sent_by
                );
                $stmt->execute();
                $stmt->close();
                
                $sent_count++;
                
            } catch (Exception $e) {
                $failed_count++;
                $failed_emails[] = $recipient['email'];
            }
        }
        
        $success = "✅ Email sent to $sent_count resident(s).";
        if ($failed_count > 0) {
            $success .= " Failed: $failed_count";
        }
        
        logAudit($_SESSION['admin_id'], 'Send Resident Email', "Sent email to $sent_count residents");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Residents - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* Same dark theme styles as dashboard */
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
            max-height: 250px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            background: #1a1a2e !important;
        }
        .recipient-grid .form-check { padding: 4px 0; }
        .recipient-grid .form-check:hover { background: rgba(255,255,255,0.03) !important; border-radius: 4px; }
        .recipient-grid .form-check-label { color: #b0b0c0 !important; }
        .recipient-grid .form-check-input {
            background-color: #1a1a2e !important;
            border-color: #2a2a4a !important;
        }
        .recipient-grid .form-check-input:checked {
            background-color: #1a3a6a !important;
            border-color: #1a3a6a !important;
        }
        
        .resident-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #065f46, #0a7a5a) !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white !important;
            font-weight: 700;
            font-size: 12px;
            margin-right: 8px;
        }
        .resident-count {
            background: #2a2a4a !important;
            color: #6ee7b7 !important;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
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
                        <i class="fas fa-users me-2" style="color: #1a3a6a;"></i>
                        Email Residents
                        <span class="resident-count ms-2"><i class="fas fa-users me-1"></i> <?php echo count($residentsList); ?> Residents</span>
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
                    <h5><i class="fas fa-pen me-2"></i>Compose Email to Residents</h5>
                    
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="send_to_all" id="sendToAll" onchange="toggleAllResidents()">
                                        <label class="form-check-label" for="sendToAll" style="color:#e0e0e0 !important;">
                                            <strong>Send to All Residents</strong>
                                            <span class="text-muted small">(<?php echo count($residentsList); ?> residents)</span>
                                        </label>
                                    </div>
                                </div>
                                <label class="form-label">Select Resident Recipients</label>
                                <div class="recipient-grid" id="residentRecipients">
                                    <?php foreach ($residentsList as $resident): ?>
                                        <div class="form-check">
                                            <input class="form-check-input resident-checkbox" type="checkbox" name="recipients[]" value="<?php echo $resident['user_id']; ?>" id="resident_<?php echo $resident['user_id']; ?>">
                                            <label class="form-check-label" for="resident_<?php echo $resident['user_id']; ?>">
                                                <span class="resident-avatar">
                                                    <?php 
                                                        $initials = '';
                                                        $parts = explode(' ', $resident['full_name']);
                                                        foreach ($parts as $p) {
                                                            if (!empty($p)) $initials .= strtoupper($p[0]);
                                                        }
                                                        echo substr($initials, 0, 2);
                                                    ?>
                                                </span>
                                                <?php echo htmlspecialchars($resident['full_name']); ?>
                                                <span class="text-muted small">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($resident['email']); ?>
                                                    <span class="mx-1">|</span>
                                                    <i class="fas fa-bed me-1"></i>
                                                    Room <?php echo htmlspecialchars($resident['room_number'] ?? 'N/A'); ?>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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
                                <i class="fas fa-paper-plane me-1"></i> Send to Residents
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
        function toggleAllResidents() {
            const checked = document.getElementById('sendToAll').checked;
            document.querySelectorAll('.resident-checkbox').forEach(cb => cb.checked = checked);
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
