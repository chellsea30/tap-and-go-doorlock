<?php
/**
 * Tap-and-Go Doorlock - Student Reset Password
 * Student sets new password using token from admin
 */

session_start();

// Adjust path - from frontend/pages/student/ to backend/
require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

$conn = getDBConnection();
$error = '';
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$valid_token = false;
$student_id = null;
$student_name = '';
$request_id = null;

// Check if token is valid
if (!empty($token)) {
    $stmt = $conn->prepare("
        SELECT r.*, s.full_name, s.student_id
        FROM password_reset_requests r
        JOIN student_users s ON r.student_id = s.student_id
        WHERE r.reset_token = ? 
        AND r.status = 'approved'
        AND r.token_expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $valid_token = true;
        $student_id = $row['student_id'];
        $student_name = $row['full_name'];
        $request_id = $row['request_id'];
    }
    $stmt->close();
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    
    if (empty($new_password) || strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Verify token again
        $stmt = $conn->prepare("
            SELECT r.*, s.student_id
            FROM password_reset_requests r
            JOIN student_users s ON r.student_id = s.student_id
            WHERE r.reset_token = ? 
            AND r.status = 'approved'
            AND r.token_expires_at > NOW()
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("UPDATE student_users SET password_hash = ? WHERE student_id = ?");
            $stmt2->bind_param("si", $new_hash, $row['student_id']);
            
            if ($stmt2->execute()) {
                // Update request status to completed
                $stmt3 = $conn->prepare("
                    UPDATE password_reset_requests 
                    SET status = 'completed' 
                    WHERE request_id = ?
                ");
                $stmt3->bind_param("i", $row['request_id']);
                $stmt3->execute();
                $stmt3->close();
                
                $success = "✅ Password reset successfully! You can now login with your new password.";
                $valid_token = false;
            } else {
                $error = "Failed to update password. Please try again.";
            }
            $stmt2->close();
        } else {
            $error = "Invalid or expired token. Please request a new reset link.";
        }
        $stmt->close();
    }
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
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #0a1628, #1a2a4a);
            margin: 0;
        }
        .reset-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 32px;
            padding: 50px 40px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 40px 80px rgba(0,0,0,0.6);
        }
        .logo-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            color: #ffd700;
            box-shadow: 0 15px 40px rgba(26,86,168,0.4);
        }
        h2 { 
            color: #ffffff; 
            font-weight: 700; 
            text-align: center; 
            margin-bottom: 5px;
        }
        .subtitle { 
            color: rgba(255,255,255,0.5); 
            text-align: center; 
            font-size: 14px;
            margin-bottom: 25px;
        }
        .form-control {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 14px 18px;
            color: #ffffff;
            font-size: 14px;
            height: 54px;
        }
        .form-control:focus {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,215,0,0.3);
            box-shadow: 0 0 0 4px rgba(255,215,0,0.06);
            color: #ffffff;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.3); }
        .form-label { 
            color: rgba(255,255,255,0.6); 
            font-size: 13px; 
            font-weight: 500;
        }
        .btn-reset {
            width: 100%; 
            padding: 16px; 
            font-size: 16px; 
            font-weight: 600;
            border-radius: 14px; 
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            border: none; 
            color: #0a1628; 
            height: 54px;
            transition: all 0.3s ease;
        }
        .btn-reset:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 15px 35px rgba(255,215,0,0.3); 
        }
        .btn-back {
            color: rgba(255,255,255,0.3); 
            text-decoration: none; 
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .btn-back:hover { color: rgba(255,255,255,0.6); }
        .alert-success {
            background: rgba(16,185,129,0.12);
            border: 1px solid rgba(16,185,129,0.15);
            color: #6ee7b7;
            border-radius: 14px;
            padding: 14px 18px;
        }
        .alert-danger {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.15);
            color: #fca5a5;
            border-radius: 14px;
            padding: 14px 18px;
        }
        .student-name {
            color: #ffd700;
            font-weight: 600;
            text-align: center;
            margin: 10px 0 20px 0;
            font-size: 16px;
        }
        .student-name i {
            margin-right: 8px;
        }
        .text-center {
            text-align: center;
        }
        .mt-3 { margin-top: 15px; }
        .mt-2 { margin-top: 10px; }
        .mb-3 { margin-bottom: 15px; }
        .mb-4 { margin-bottom: 20px; }
        @media (max-width: 480px) {
            .reset-card { padding: 30px 20px; border-radius: 24px; }
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="logo-circle"><i class="fas fa-key"></i></div>
        <h2>Reset Password</h2>
        <p class="subtitle">Set a new password for your account</p>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            </div>
            <div class="text-center mt-3">
                <!-- FIXED: ../login.php (from student/ folder, go up one level) -->
                <a href="../login.php" class="btn-back">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
            <?php if (strpos($error, 'expired') !== false || strpos($error, 'Invalid') !== false): ?>
                <div class="text-center mt-3">
                    <!-- FIXED: ../login.php -->
                    <a href="../login.php" class="btn-back">
                        <i class="fas fa-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            <?php endif; ?>
        <?php elseif (!$valid_token): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> Invalid or expired reset link. Please request a new one from admin.
            </div>
            <div class="text-center mt-3">
                <!-- FIXED: ../login.php -->
                <a href="../login.php" class="btn-back">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        <?php else: ?>
            <div class="student-name">
                <i class="fas fa-user me-2"></i> <?php echo htmlspecialchars($student_name); ?>
            </div>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-lock me-1"></i> New Password</label>
                    <input type="password" class="form-control" name="new_password" placeholder="Enter new password (min 8 chars)" required minlength="8">
                </div>
                
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-check-circle me-1"></i> Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm new password" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn-reset">
                    <i class="fas fa-save me-1"></i> Reset Password
                </button>
            </form>
            
            <div class="text-center mt-3">
                <!-- FIXED: ../login.php -->
                <a href="../login.php" class="btn-back">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>