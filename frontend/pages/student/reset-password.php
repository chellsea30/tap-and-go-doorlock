<?php
/**
 * Tap-and-Go Doorlock - Student Password Reset
 * NO REDIRECTS - DIRECT DISPLAY
 */

// Start session but don't require login
session_start();

// Load config and functions
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

// Get database connection
$conn = getDBConnection();

$error = '';
$success = '';
$show_form = false;
$token_valid = false;
$student_id = 0;
$student_name = '';
$student_email = '';
$request_id = 0;
$token = '';

// Get token from URL
$token = $_GET['token'] ?? '';

// ============================================================
// VERIFY TOKEN
// ============================================================
if (!empty($token)) {
    // Check if token exists and is valid
    $stmt = $conn->prepare("
        SELECT r.*, s.full_name, s.email, s.student_id
        FROM password_reset_requests r
        JOIN student_users s ON r.student_id = s.student_id
        WHERE r.reset_token = ? 
        AND r.status = 'approved' 
        AND r.token_expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();
    $stmt->close();
    
    if ($request) {
        $token_valid = true;
        $show_form = true;
        $student_id = $request['student_id'];
        $student_name = $request['full_name'];
        $student_email = $request['email'];
        $request_id = $request['request_id'];
    } else {
        // Check if token exists but is expired or used
        $stmt = $conn->prepare("
            SELECT status, token_expires_at 
            FROM password_reset_requests 
            WHERE reset_token = ?
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $check = $result->fetch_assoc();
        $stmt->close();
        
        if ($check) {
            if ($check['status'] == 'completed') {
                $error = "❌ This reset link has already been used.";
            } elseif (strtotime($check['token_expires_at']) < time()) {
                $error = "❌ This reset link has expired (valid for 24 hours only).";
            } else {
                $error = "❌ Invalid reset token.";
            }
        } else {
            $error = "❌ Invalid reset token. Please request a new one.";
        }
    }
} else {
    $error = "❌ No reset token provided.";
}

// ============================================================
// HANDLE PASSWORD RESET
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    $request_id = (int)($_POST['request_id'] ?? 0);
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "❌ Please enter both password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "❌ Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "❌ Password must be at least 6 characters.";
    } else {
        // Re-verify token
        $stmt = $conn->prepare("
            SELECT r.*, s.student_id 
            FROM password_reset_requests r
            JOIN student_users s ON r.student_id = s.student_id
            WHERE r.request_id = ? 
            AND r.reset_token = ? 
            AND r.status = 'approved' 
            AND r.token_expires_at > NOW()
        ");
        $stmt->bind_param("is", $request_id, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();
        
        if ($request) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update student password
            $stmt = $conn->prepare("UPDATE student_users SET password_hash = ? WHERE student_id = ?");
            $stmt->bind_param("si", $hashed_password, $request['student_id']);
            
            if ($stmt->execute()) {
                // Mark request as completed
                $stmt2 = $conn->prepare("UPDATE password_reset_requests SET status = 'completed', updated_at = NOW() WHERE request_id = ?");
                $stmt2->bind_param("i", $request_id);
                $stmt2->execute();
                $stmt2->close();
                
                logStudentAudit($request['student_id'], 'Password Reset', 'Password reset completed via link');
                
                $success = "✅ Password reset successfully! You can now login with your new password.";
                $show_form = false;
                $token_valid = false;
            } else {
                $error = "❌ Failed to reset password. Please try again.";
            }
            $stmt->close();
        } else {
            $error = "❌ Invalid or expired reset token. Please request a new one.";
        }
    }
}

$conn->close();

// ============================================================
// GET BASE URL FOR LINKS
// ============================================================
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Remove port if present
    $host = preg_replace('/:\d+$/', '', $host);
    
    return $protocol . $host;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #1a2a4a 50%, #0d1f3c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 30% 50%, rgba(26,86,168,0.15) 0%, transparent 60%),
                        radial-gradient(ellipse at 70% 50%, rgba(26,86,168,0.10) 0%, transparent 60%);
            animation: rotateBg 60s linear infinite;
            z-index: 0;
        }
        @keyframes rotateBg {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .reset-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 32px;
            padding: 45px 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 40px 80px rgba(0,0,0,0.6);
            position: relative;
            z-index: 1;
        }
        .reset-card .logo {
            text-align: center;
            margin-bottom: 25px;
        }
        .reset-card .logo .icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #ffd700;
            box-shadow: 0 15px 40px rgba(26,86,168,0.4);
        }
        .reset-card h2 {
            color: #ffffff;
            font-weight: 700;
            text-align: center;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .reset-card h2 span {
            background: linear-gradient(135deg, #ffd700, #f0e6b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .reset-card .subtitle {
            color: rgba(255,255,255,0.4);
            text-align: center;
            font-size: 14px;
            margin-bottom: 25px;
        }
        .reset-card .subtitle .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
            margin-right: 5px;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .form-control {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 14px 18px;
            color: #ffffff;
            font-size: 14px;
            height: 54px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,215,0,0.3);
            box-shadow: 0 0 0 4px rgba(255,215,0,0.06);
            color: #ffffff;
            outline: none;
        }
        .form-control::placeholder {
            color: rgba(255,255,255,0.3);
        }
        .form-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }
        
        .btn-reset {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 14px;
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            border: none;
            color: #0a0e1a;
            height: 54px;
            margin-top: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(255,215,0,0.3);
        }
        .btn-reset:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .alert-success {
            background: rgba(16,185,129,0.15);
            border: 1px solid #10b981;
            color: #6ee7b7;
            border-radius: 14px;
            padding: 14px 18px;
            text-align: center;
        }
        .alert-danger {
            background: rgba(239,68,68,0.15);
            border: 1px solid #ef4444;
            color: #fca5a5;
            border-radius: 14px;
            padding: 14px 18px;
            text-align: center;
        }
        
        .text-muted { color: rgba(255,255,255,0.4) !important; }
        
        .btn-back {
            color: rgba(255,255,255,0.3);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
            display: inline-block;
        }
        .btn-back:hover {
            color: rgba(255,255,255,0.6);
        }
        .btn-back i { margin-right: 4px; }
        
        .user-info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 14px 16px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .user-info-box .name {
            color: #ffd700;
            font-weight: 600;
            font-size: 16px;
        }
        .user-info-box .email {
            color: rgba(255,255,255,0.4);
            font-size: 13px;
        }
        .user-info-box .email i { margin-right: 4px; }
        
        .password-requirements {
            color: rgba(255,255,255,0.3);
            font-size: 12px;
            margin-top: 5px;
        }
        .password-requirements i { margin-right: 4px; }
        
        .footer-text {
            text-align: center;
            color: rgba(255,255,255,0.1);
            font-size: 11px;
            margin-top: 25px;
            letter-spacing: 1px;
        }
        .footer-text span { color: rgba(255,215,0,0.1); }
        
        .divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.06);
            margin: 20px 0;
        }
        
        .btn-request {
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.6);
            border: 1px solid rgba(255,255,255,0.08);
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            font-size: 14px;
        }
        .btn-request:hover {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.8);
        }
        
        @media (max-width: 480px) {
            .reset-card { padding: 30px 20px; border-radius: 24px; }
            .reset-card .logo .icon { width: 60px; height: 60px; font-size: 24px; }
            .reset-card h2 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="logo">
            <div class="icon"><i class="fas fa-key"></i></div>
        </div>
        <h2><span>Reset Password</span></h2>
        <p class="subtitle">
            <span class="live-indicator"></span> 
            <?php echo !empty($success) ? 'Password Updated' : (!empty($error) ? 'Something went wrong' : 'Create a new password'); ?>
        </p>

        <!-- SUCCESS MESSAGE -->
        <?php if (!empty($success)): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle me-2"></i> 
                <?php echo $success; ?>
                <hr class="divider">
                <a href="../login.php" class="btn-back">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        <?php endif; ?>

        <!-- ERROR MESSAGE -->
        <?php if (!empty($error) && empty($success)): ?>
            <div class="alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> 
                <?php echo $error; ?>
                <hr class="divider">
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="request-reset.php" class="btn-request">
                        <i class="fas fa-key me-1"></i> Request New Link
                    </a>
                    <a href="../login.php" class="btn-back">
                        <i class="fas fa-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- RESET FORM -->
        <?php if ($show_form && $token_valid && empty($success)): ?>
            <div class="user-info-box">
                <div class="name"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($student_name); ?></div>
                <div class="email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student_email); ?></div>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
                
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-lock me-1"></i> New Password</label>
                    <input type="password" class="form-control" name="new_password" placeholder="Enter new password" required minlength="6">
                    <div class="password-requirements">
                        <i class="fas fa-info-circle me-1"></i> Minimum 6 characters
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-check-circle me-1"></i> Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" placeholder="Re-enter new password" required minlength="6">
                </div>
                
                <button type="submit" name="reset_password" class="btn-reset">
                    <i class="fas fa-key me-2"></i> Reset Password
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="../login.php" class="btn-back">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        <?php endif; ?>

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> <span>ISU-Echague Dormitory</span>
        </div>
    </div>
</body>
</html>
