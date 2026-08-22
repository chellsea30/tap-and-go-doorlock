<?php
/**
 * Tap-and-Go Doorlock - Main Login with Math Puzzle
 * SIMPLE ADDITION ONLY
 * WITH 10-MINUTE BAN AFTER 3 INCORRECT ATTEMPTS
 */

session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check if already logged in
if (isset($_SESSION['admin_id']) && isSessionValid()) {
    header('Location: dashboard.php');
    exit();
}
if (isset($_SESSION['staff_id']) && isStaffSessionValid()) {
    header('Location: staff/dashboard.php');
    exit();
}
if (isset($_SESSION['student_id']) && isStudentSessionValid()) {
    header('Location: student/dashboard.php');
    exit();
}

$error = '';
$email = '';
$show_puzzle = false;
$puzzle_user_id = 0;
$puzzle_user_type = '';
$puzzle_email = '';
$puzzle_name = '';
$puzzle_data = null;
$is_blocked = false;
$reset_success = '';
$remaining_attempts = 3; // CHANGED: 3 attempts max
$block_minutes = 10;
$block_until = '';
$max_attempts = 3; // CHANGED: Maximum attempts set to 3

// ============================================================
// HANDLE PASSWORD RESET REQUEST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $reset_email = trim($_POST['reset_email'] ?? '');
    $reset_reason = trim($_POST['reset_reason'] ?? '');
    
    if (empty($reset_email)) {
        $error = 'Please enter your email address.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT student_id, student_id_number, full_name, username, email FROM student_users WHERE email = ? AND is_active = 1");
        $stmt->bind_param("s", $reset_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $check = $conn->prepare("SELECT request_id FROM password_reset_requests WHERE student_id = ? AND status = 'pending'");
            $check->bind_param("i", $row['student_id']);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'You already have a pending reset request. Please wait for admin approval.';
            } else {
                $stmt2 = $conn->prepare("
                    INSERT INTO password_reset_requests (
                        student_id, 
                        student_name, 
                        student_id_number, 
                        username, 
                        email, 
                        reason, 
                        status, 
                        requested_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt2->bind_param("isssss", 
                    $row['student_id'], 
                    $row['full_name'], 
                    $row['student_id_number'], 
                    $row['username'], 
                    $row['email'], 
                    $reset_reason
                );
                
                if ($stmt2->execute()) {
                    $reset_success = "✅ Password reset request submitted! Please wait for admin approval.";
                    logStudentAudit($row['student_id'], 'Request Password Reset', "Requested password reset");
                } else {
                    $error = "Failed to submit request. Please try again.";
                }
                $stmt2->close();
            }
            $check->close();
        } else {
            $error = 'Email not found. Please check your email address or contact admin.';
        }
        $stmt->close();
    }
}

// ============================================================
// HANDLE "BACK TO LOGIN" - Reset puzzle session
// ============================================================
if (isset($_GET['reset'])) {
    unset($_SESSION['puzzle_user_id']);
    unset($_SESSION['puzzle_user_type']);
    unset($_SESSION['puzzle_email']);
    unset($_SESSION['puzzle_name']);
    unset($_SESSION['puzzle_answer']);
    unset($_SESSION['puzzle_question']);
    unset($_SESSION['puzzle_display']);
    header('Location: login.php');
    exit();
}

// ============================================================
// CHECK IF USER IS BLOCKED
// ============================================================
function checkLoginBlock($role, $user_id) {
    $conn = getDBConnection();
    $table = $role . '_users';
    $id_field = $role . '_id';
    
    $stmt = $conn->prepare("SELECT login_attempts, login_blocked_until FROM $table WHERE $id_field = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) {
        return false;
    }
    
    // Check if blocked and block time hasn't expired
    if (!empty($row['login_blocked_until'])) {
        $blocked_until = strtotime($row['login_blocked_until']);
        if ($blocked_until > time()) {
            return [
                'blocked' => true,
                'blocked_until' => $row['login_blocked_until'],
                'remaining_seconds' => $blocked_until - time()
            ];
        } else {
            // Block expired - reset attempts
            $stmt = $conn->prepare("UPDATE $table SET login_attempts = 0, login_blocked_until = NULL WHERE $id_field = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            return false;
        }
    }
    
    return [
        'blocked' => false,
        'attempts' => (int)($row['login_attempts'] ?? 0)
    ];
}

// ============================================================
// HANDLE LOGIN (First Step - Show Math Puzzle)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $role = $_POST['role'] ?? 'admin';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $result = null;
        $user_id = 0;
        $full_name = '';
        
        switch ($role) {
            case 'admin':
                $result = authenticateAdminByEmail($email, $password);
                if ($result['success']) {
                    $user_id = $result['admin_id'];
                    $full_name = $result['full_name'];
                    $email = $result['email'];
                }
                break;
            case 'staff':
                $result = authenticateStaffByEmail($email, $password);
                if ($result['success']) {
                    $user_id = $result['staff_id'];
                    $full_name = $result['full_name'];
                    $email = $result['email'];
                }
                break;
            case 'student':
                $result = authenticateStudent($email, $password);
                if ($result['success']) {
                    $user_id = $result['student_id'];
                    $full_name = $result['full_name'];
                    $email = $result['email'];
                }
                break;
        }
        
        if ($result && $result['success']) {
            // Reset login attempts on successful login
            resetLoginAttempts($role, $user_id);
            
            // Check if user is blocked for math puzzle
            if (isMathBlocked($role, $user_id)) {
                $blocked_until = '';
                $conn = getDBConnection();
                $table = $role . '_users';
                $id_field = $role . '_id';
                $stmt = $conn->prepare("SELECT math_blocked_until FROM $table WHERE $id_field = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $blocked_until = date('h:i A', strtotime($row['math_blocked_until']));
                }
                $stmt->close();
                
                $error = "⛔ Too many failed puzzle attempts. You are blocked until $blocked_until. Please try again later.";
                $is_blocked = true;
            } else {
                // Generate simple addition puzzle
                $num1 = rand(1, 20);
                $num2 = rand(1, 20);
                $answer = $num1 + $num2;
                $question = "$num1 + $num2";
                $display = "$num1 + $num2 = ?";
                
                $puzzle_data = [
                    'answer' => $answer,
                    'question' => $question,
                    'display' => $display
                ];
                
                // Store in session
                $_SESSION['puzzle_user_id'] = $user_id;
                $_SESSION['puzzle_user_type'] = $role;
                $_SESSION['puzzle_email'] = $email;
                $_SESSION['puzzle_name'] = $full_name;
                $_SESSION['puzzle_answer'] = $puzzle_data['answer'];
                $_SESSION['puzzle_question'] = $puzzle_data['question'];
                $_SESSION['puzzle_display'] = $puzzle_data['display'];
                
                $show_puzzle = true;
                $puzzle_user_id = $user_id;
                $puzzle_user_type = $role;
                $puzzle_email = $email;
                $puzzle_name = $full_name;
            }
        } else {
            // INCORRECT PASSWORD - Increment login attempts
            $conn = getDBConnection();
            
            // Find user by email first
            $user_found = false;
            $found_user_id = 0;
            $found_role = '';
            
            // Check admin
            $stmt = $conn->prepare("SELECT admin_id FROM admin_users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $found_user_id = $row['admin_id'];
                $found_role = 'admin';
                $user_found = true;
            }
            $stmt->close();
            
            // Check staff if not found
            if (!$user_found) {
                $stmt = $conn->prepare("SELECT staff_id FROM staff_users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $found_user_id = $row['staff_id'];
                    $found_role = 'staff';
                    $user_found = true;
                }
                $stmt->close();
            }
            
            // Check student if not found
            if (!$user_found) {
                $stmt = $conn->prepare("SELECT student_id FROM student_users WHERE email = ? AND is_active = 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $found_user_id = $row['student_id'];
                    $found_role = 'student';
                    $user_found = true;
                }
                $stmt->close();
            }
            
            if ($user_found) {
                $table = $found_role . '_users';
                $id_field = $found_role . '_id';
                
                // Get current attempts
                $stmt = $conn->prepare("SELECT login_attempts, login_blocked_until FROM $table WHERE $id_field = ?");
                $stmt->bind_param("i", $found_user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                
                $current_attempts = (int)($row['login_attempts'] ?? 0);
                $new_attempts = $current_attempts + 1;
                
                // Check if block exists but expired
                if (!empty($row['login_blocked_until'])) {
                    $blocked_until_time = strtotime($row['login_blocked_until']);
                    if ($blocked_until_time < time()) {
                        // Block expired, reset attempts
                        $new_attempts = 1;
                        $stmt = $conn->prepare("UPDATE $table SET login_attempts = 1, login_blocked_until = NULL WHERE $id_field = ?");
                        $stmt->bind_param("i", $found_user_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        // Still blocked
                        $remaining_minutes = ceil(($blocked_until_time - time()) / 60);
                        $error = "⛔ Account is temporarily locked. Please wait $remaining_minutes minute(s) before trying again.";
                        $is_blocked = true;
                        $block_until = date('h:i A', $blocked_until_time);
                        $remaining_attempts = 0;
                    }
                } else {
                    // Update attempts - CHANGED: max attempts is now 3
                    if ($new_attempts >= 3) { // CHANGED: 3 attempts max
                        // Block for 10 minutes
                        $block_until_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        $stmt = $conn->prepare("UPDATE $table SET login_attempts = ?, login_blocked_until = ? WHERE $id_field = ?");
                        $stmt->bind_param("isi", $new_attempts, $block_until_time, $found_user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        $error = "⛔ Too many failed login attempts (3). Your account is locked for 10 minutes. Please try again at " . date('h:i A', strtotime($block_until_time));
                        $is_blocked = true;
                        $block_until = date('h:i A', strtotime($block_until_time));
                        $remaining_attempts = 0;
                    } else {
                        $stmt = $conn->prepare("UPDATE $table SET login_attempts = ? WHERE $id_field = ?");
                        $stmt->bind_param("ii", $new_attempts, $found_user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        $remaining_attempts = 3 - $new_attempts; // CHANGED: 3 - attempts
                        $error = "Invalid credentials. You have $remaining_attempts attempt(s) remaining before account lock.";
                    }
                }
            } else {
                $error = 'Invalid credentials. Please try again.';
            }
        }
    }
}

// ============================================================
// HANDLE MATH PUZZLE VERIFICATION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_math'])) {
    $user_answer = isset($_POST['math_answer']) ? (int)trim($_POST['math_answer']) : -1;
    $user_id = (int)($_POST['user_id'] ?? 0);
    $user_type = $_POST['user_type'] ?? '';
    $email = $_POST['email'] ?? '';
    $question = $_POST['question'] ?? '';
    $correct_answer = (int)($_POST['correct_answer'] ?? 0);
    
    if ($user_answer === -1 || $user_answer === '') {
        $error = 'Please enter your answer.';
    } else {
        if (isMathBlocked($user_type, $user_id)) {
            $error = '⛔ You are temporarily blocked. Please try again later.';
        } else {
            $checkResult = checkMathPuzzle($user_type, $user_id, $email, $question, $user_answer, $correct_answer);
            
            if ($checkResult['success']) {
                resetMathAttempts($user_type, $user_id);
                
                $conn = getDBConnection();
                $table = $user_type . '_users';
                $id_field = $user_type . '_id';
                $stmt = $conn->prepare("SELECT * FROM $table WHERE $id_field = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $userData = $result->fetch_assoc();
                $stmt->close();
                
                switch ($user_type) {
                    case 'admin':
                        $_SESSION['admin_id'] = $user_id;
                        $_SESSION['username'] = $userData['username'] ?? 'admin';
                        $_SESSION['full_name'] = $userData['full_name'];
                        $_SESSION['role'] = 'administrator';
                        $_SESSION['email'] = $email;
                        $_SESSION['login_time'] = time();
                        session_regenerate_id(true);
                        updateLastLogin($user_id);
                        unset($_SESSION['puzzle_user_id'], $_SESSION['puzzle_user_type'], $_SESSION['puzzle_email'], $_SESSION['puzzle_name'], $_SESSION['puzzle_answer'], $_SESSION['puzzle_question']);
                        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title>';
                        echo '<style>body{background:#0a1628;display:flex;align-items:center;justify-content:center;height:100vh;font-family:Arial,sans-serif;color:#fff;flex-direction:column;}';
                        echo '.granted{color:#10b981;font-size:80px;animation:bounceIn 0.6s ease;}';
                        echo '@keyframes bounceIn{0%{transform:scale(0)}50%{transform:scale(1.2)}100%{transform:scale(1)}}';
                        echo '.text{font-size:28px;font-weight:700;color:#10b981;margin-top:15px;}';
                        echo '.sub{color:rgba(255,255,255,0.5);font-size:14px;margin-top:8px;}';
                        echo '</style></head><body>';
                        echo '<div class="granted">✅</div>';
                        echo '<div class="text">GRANTED</div>';
                        echo '<div class="sub">Access Granted! Redirecting...</div>';
                        echo '<script>setTimeout(function(){ window.location.href = "dashboard.php"; }, 1500);</script>';
                        echo '</body></html>';
                        exit();
                    case 'staff':
                        $_SESSION['staff_id'] = $user_id;
                        $_SESSION['staff_id_number'] = $userData['staff_id_number'] ?? 'STAFF-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                        $_SESSION['full_name'] = $userData['full_name'];
                        $_SESSION['department'] = $userData['department'] ?? 'Dormitory Staff';
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = 'staff';
                        $_SESSION['login_time'] = time();
                        session_regenerate_id(true);
                        logStaffAudit($user_id, 'Login', 'Staff logged in');
                        unset($_SESSION['puzzle_user_id'], $_SESSION['puzzle_user_type'], $_SESSION['puzzle_email'], $_SESSION['puzzle_name'], $_SESSION['puzzle_answer'], $_SESSION['puzzle_question']);
                        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title>';
                        echo '<style>body{background:#0a1628;display:flex;align-items:center;justify-content:center;height:100vh;font-family:Arial,sans-serif;color:#fff;flex-direction:column;}';
                        echo '.granted{color:#10b981;font-size:80px;animation:bounceIn 0.6s ease;}';
                        echo '@keyframes bounceIn{0%{transform:scale(0)}50%{transform:scale(1.2)}100%{transform:scale(1)}}';
                        echo '.text{font-size:28px;font-weight:700;color:#10b981;margin-top:15px;}';
                        echo '.sub{color:rgba(255,255,255,0.5);font-size:14px;margin-top:8px;}';
                        echo '</style></head><body>';
                        echo '<div class="granted">✅</div>';
                        echo '<div class="text">GRANTED</div>';
                        echo '<div class="sub">Access Granted! Redirecting to Staff Dashboard...</div>';
                        echo '<script>setTimeout(function(){ window.location.href = "staff/dashboard.php"; }, 1500);</script>';
                        echo '</body></html>';
                        exit();
                    case 'student':
                        $_SESSION['student_id'] = $user_id;
                        $_SESSION['student_id_number'] = $userData['student_id_number'] ?? 'STU-' . date('Y') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                        $_SESSION['full_name'] = $userData['full_name'];
                        $_SESSION['course'] = $userData['course'] ?? 'N/A';
                        $_SESSION['year_level'] = $userData['year_level'] ?? 'N/A';
                        $_SESSION['role'] = 'student';
                        $_SESSION['login_time'] = time();
                        session_regenerate_id(true);
                        logStudentAudit($user_id, 'Login', 'Student logged in');
                        unset($_SESSION['puzzle_user_id'], $_SESSION['puzzle_user_type'], $_SESSION['puzzle_email'], $_SESSION['puzzle_name'], $_SESSION['puzzle_answer'], $_SESSION['puzzle_question']);
                        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title>';
                        echo '<style>body{background:#0a1628;display:flex;align-items:center;justify-content:center;height:100vh;font-family:Arial,sans-serif;color:#fff;flex-direction:column;}';
                        echo '.granted{color:#10b981;font-size:80px;animation:bounceIn 0.6s ease;}';
                        echo '@keyframes bounceIn{0%{transform:scale(0)}50%{transform:scale(1.2)}100%{transform:scale(1)}}';
                        echo '.text{font-size:28px;font-weight:700;color:#10b981;margin-top:15px;}';
                        echo '.sub{color:rgba(255,255,255,0.5);font-size:14px;margin-top:8px;}';
                        echo '</style></head><body>';
                        echo '<div class="granted">✅</div>';
                        echo '<div class="text">GRANTED</div>';
                        echo '<div class="sub">Access Granted! Redirecting to Student Dashboard...</div>';
                        echo '<script>setTimeout(function(){ window.location.href = "student/dashboard.php"; }, 1500);</script>';
                        echo '</body></html>';
                        exit();
                }
            } else {
                $error = $checkResult['message'];
                if (isset($checkResult['blocked']) && $checkResult['blocked']) {
                    $error = '⛔ Too many failed attempts. You are blocked for 5 minutes.';
                }
                $show_puzzle = true;
                $puzzle_user_id = (int)$_POST['user_id'];
                $puzzle_user_type = $_POST['user_type'];
                $puzzle_email = $_POST['email'];
                $puzzle_name = $_SESSION['puzzle_name'] ?? '';
            }
        }
    }
}

// ============================================================
// RESET MATH PUZZLE (Get new question)
// ============================================================
if (isset($_POST['reset_math']) && isset($_SESSION['puzzle_user_id'])) {
    // Generate simple addition puzzle
    $num1 = rand(1, 20);
    $num2 = rand(1, 20);
    $answer = $num1 + $num2;
    $question = "$num1 + $num2";
    $display = "$num1 + $num2 = ?";
    
    $puzzle_data = [
        'answer' => $answer,
        'question' => $question,
        'display' => $display
    ];
    
    $_SESSION['puzzle_answer'] = $puzzle_data['answer'];
    $_SESSION['puzzle_question'] = $puzzle_data['question'];
    $_SESSION['puzzle_display'] = $puzzle_data['display'];
    $show_puzzle = true;
    $puzzle_user_id = $_SESSION['puzzle_user_id'] ?? 0;
    $puzzle_user_type = $_SESSION['puzzle_user_type'] ?? '';
    $puzzle_email = $_SESSION['puzzle_email'] ?? '';
    $puzzle_name = $_SESSION['puzzle_name'] ?? '';
}

// If puzzle data is in session but not set, retrieve it
if (!$puzzle_data && isset($_SESSION['puzzle_user_id'])) {
    $puzzle_data = [
        'answer' => $_SESSION['puzzle_answer'] ?? 0,
        'question' => $_SESSION['puzzle_question'] ?? '5 + 3',
        'display' => $_SESSION['puzzle_display'] ?? '5 + 3 = ?'
    ];
    $show_puzzle = true;
    $puzzle_user_id = $_SESSION['puzzle_user_id'] ?? 0;
    $puzzle_user_type = $_SESSION['puzzle_user_type'] ?? '';
    // FIX: Explicitly set email and name from session to prevent "undefined array key"
    $puzzle_email = $_SESSION['puzzle_email'] ?? '';
    $puzzle_name = $_SESSION['puzzle_name'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tap-and-Go Doorlock - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #0a1628 0%, #1a2a4a 50%, #0d1f3c 100%);
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
        .login-container {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        .login-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 32px;
            padding: 50px 40px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.6);
            transition: all 0.3s ease;
            position: relative;
        }
        .brand-logo { text-align: center; margin-bottom: 35px; }
        .logo-circle {
            width: 100px; height: 100px; border-radius: 50%;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 15px; box-shadow: 0 15px 40px rgba(26,86,168,0.4);
            font-size: 40px; color: #ffd700; position: relative;
        }
        .logo-circle::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            padding: 2px;
            background: conic-gradient(from 0deg, transparent, rgba(255,215,0,0.3), transparent, rgba(255,215,0,0.3), transparent);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude;
            animation: spinGlow 8s linear infinite;
        }
        @keyframes spinGlow { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .brand-logo h2 { color: #ffffff; font-weight: 700; font-size: 24px; }
        .brand-logo h2 span { background: linear-gradient(135deg, #ffd700, #f0e6b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .brand-logo .subtitle { color: rgba(255,255,255,0.5); font-size: 13px; margin-top: 5px; letter-spacing: 1px; }
        
        .role-selector {
            display: flex; gap: 10px; margin-bottom: 30px;
            background: rgba(255,255,255,0.05); border-radius: 16px;
            padding: 6px; border: 1px solid rgba(255,255,255,0.06);
        }
        .role-btn {
            flex: 1; padding: 12px; border: none; border-radius: 12px;
            background: transparent; color: rgba(255,255,255,0.5);
            font-weight: 600; font-size: 14px; transition: all 0.3s ease; cursor: pointer;
        }
        .role-btn:hover { color: rgba(255,255,255,0.8); }
        .role-btn.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            color: white; box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        .role-btn i { margin-right: 8px; }
        
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
        }
        .form-control::placeholder { color: rgba(255,255,255,0.3); }
        .form-control.is-invalid { border-color: #ef4444; }
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .input-group-text {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-right: none;
            border-radius: 14px 0 0 14px;
            color: rgba(255,255,255,0.4);
            padding: 0 18px;
        }
        .input-group .form-control {
            border-radius: 0 14px 14px 0;
            border-left: none;
        }
        .form-label {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }
        
        .btn-login {
            width: 100%; padding: 16px; font-size: 16px; font-weight: 600;
            border-radius: 14px; background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            border: none; color: white; height: 54px; margin-top: 10px;
            transition: all 0.3s ease;
        }
        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(26,58,106,0.4);
        }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; background: #4a4a5a; }
        .btn-login i { margin-right: 10px; }
        
        /* ===== LOCKED INDICATOR ===== */
        .lock-indicator {
            display: <?php echo $is_blocked ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 15px;
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 12px;
            margin-bottom: 15px;
            color: #f87171;
        }
        .lock-indicator i {
            font-size: 20px;
        }
        .lock-indicator .lock-timer {
            font-weight: 700;
            color: #ffd700;
        }
        
        .attempts-warning {
            color: #fbbf24;
            font-size: 13px;
            text-align: center;
            margin-top: 8px;
        }
        
        .alert-danger {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.15);
            border-radius: 14px;
            color: #fca5a5;
            font-size: 14px;
            padding: 14px 18px;
        }
        .alert-success {
            background: rgba(16,185,129,0.12);
            border: 1px solid rgba(16,185,129,0.15);
            border-radius: 14px;
            color: #6ee7b7;
            font-size: 14px;
            padding: 14px 18px;
        }
        .email-hint { color: rgba(255,255,255,0.25); font-size: 11px; margin-top: 4px; }
        .footer-text {
            color: rgba(255,255,255,0.15);
            font-size: 11px; text-align: center; margin-top: 25px; letter-spacing: 1px;
        }
        .footer-text span { color: rgba(255,215,0,0.15); }
        
        /* ===== LOADING SPINNER ===== */
        .spinner-container {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 10px 0;
        }
        .spinner-container.active {
            display: flex;
        }
        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top: 3px solid #ffd700;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .spinner-text {
            color: rgba(255,255,255,0.7);
            font-size: 14px;
            font-weight: 500;
        }
        
        /* ===== MATH PUZZLE ===== */
        .puzzle-section {
            display: <?php echo $show_puzzle ? 'block' : 'none'; ?>;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .puzzle-header {
            text-align: center; margin-bottom: 20px;
        }
        .puzzle-header h4 {
            color: #ffffff; font-weight: 600;
        }
        .puzzle-header p {
            color: rgba(255,255,255,0.5); font-size: 13px;
        }
        .puzzle-header .user-email {
            color: #ffd700; font-weight: 500;
        }
        .puzzle-box {
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.08);
            margin: 20px 0;
        }
        .puzzle-question {
            font-size: 32px;
            font-weight: 700;
            color: #ffd700;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }
        .puzzle-hint {
            color: rgba(255,255,255,0.3);
            font-size: 12px;
        }
        .puzzle-input {
            width: 120px;
            height: 60px;
            background: rgba(255,255,255,0.06);
            border: 2px solid rgba(255,215,0,0.2);
            border-radius: 12px;
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            transition: all 0.3s ease;
            margin: 15px auto;
            display: block;
        }
        .puzzle-input:focus {
            border-color: #ffd700;
            box-shadow: 0 0 0 3px rgba(255,215,0,0.1);
            outline: none;
        }
        .puzzle-attempts {
            text-align: center; color: rgba(255,255,255,0.4); font-size: 13px; margin: 10px 0;
        }
        .reset-puzzle-btn {
            background: transparent; border: none;
            color: rgba(255,215,0,0.4); font-size: 13px;
            cursor: pointer; transition: all 0.3s ease;
            text-decoration: underline;
        }
        .reset-puzzle-btn:hover {
            color: #ffd700;
        }
        .back-login-btn {
            color: rgba(255,255,255,0.3);
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-login-btn:hover {
            color: rgba(255,255,255,0.6);
        }
        
        /* ============================================================
           PASSWORD RESET SECTION
           ============================================================ */
        .reset-section {
            display: <?php echo isset($_POST['show_reset']) ? 'block' : 'none'; ?>;
            animation: fadeIn 0.5s ease;
        }
        .reset-section .reset-link {
            color: rgba(255,215,0,0.6);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .reset-section .reset-link:hover {
            color: #ffd700;
        }
        .back-to-login-link {
            color: rgba(255,255,255,0.3);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .back-to-login-link:hover {
            color: rgba(255,255,255,0.6);
        }
        .reset-section .form-control {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 14px 18px;
            color: #ffffff;
            font-size: 14px;
            height: 54px;
            transition: all 0.3s ease;
        }
        .reset-section .form-control:focus {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,215,0,0.3);
            box-shadow: 0 0 0 4px rgba(255,215,0,0.06);
            color: #ffffff;
        }
        .reset-section .form-control::placeholder {
            color: rgba(255,255,255,0.3);
        }
        .reset-section .btn-reset {
            width: 100%; padding: 16px; font-size: 16px; font-weight: 600;
            border-radius: 14px; background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none; color: white; height: 54px; margin-top: 10px;
            transition: all 0.3s ease;
        }
        .reset-section .btn-reset:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(245,158,11,0.4);
        }
        .reset-section .btn-reset:disabled { opacity: 0.7; cursor: not-allowed; }
        
        /* ===== COUNTDOWN TIMER ANIMATION ===== */
        @keyframes pulseWarning {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse-warning {
            animation: pulseWarning 1.5s ease-in-out infinite;
        }
        
        @media (max-width: 480px) {
            .login-card { padding: 30px 20px; border-radius: 24px; }
            .logo-circle { width: 80px; height: 80px; font-size: 32px; }
            .role-btn { font-size: 12px; padding: 10px; }
            .puzzle-question { font-size: 24px; }
            .puzzle-input { width: 100px; height: 50px; font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card" id="loginCard">
            
            <div class="brand-logo">
                <div class="logo-circle"><i class="fas fa-door-open"></i></div>
                <h2><span>Tap &amp; Go</span> Doorlock</h2>
                <p class="subtitle">ISU-Echague Ladies Dormitory</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-<?php echo strpos($error, '✅') !== false || strpos($error, 'solved') !== false ? 'success' : 'danger'; ?>">
                    <i class="fas <?php echo strpos($error, '✅') !== false || strpos($error, 'solved') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($reset_success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $reset_success; ?>
                </div>
            <?php endif; ?>

            <!-- ===== LOCK INDICATOR ===== -->
            <?php if ($is_blocked): ?>
            <div class="lock-indicator" id="lockIndicator">
                <i class="fas fa-lock"></i>
                <span>
                    Account Locked 
                    <?php if (!empty($block_until)): ?>
                        until <span class="lock-timer"><?php echo $block_until; ?></span>
                    <?php endif; ?>
                </span>
                <i class="fas fa-clock"></i>
            </div>
            <?php endif; ?>

            <!-- ===== MATH PUZZLE SECTION ===== -->
            <div class="puzzle-section" id="puzzleSection">
                <div class="puzzle-header">
                    <h4><i class="fas fa-calculator me-2" style="color: #ffd700;"></i>Solve the Math Puzzle</h4>
                    <p>Please solve this simple addition problem to continue</p>
                    <p class="user-email"><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($puzzle_name ?? ''); ?> (<?php echo htmlspecialchars($puzzle_email ?? ''); ?>)</p>
                </div>

                <form method="POST" action="" id="puzzleForm">
                    <input type="hidden" name="user_id" value="<?php echo $puzzle_user_id; ?>">
                    <input type="hidden" name="user_type" value="<?php echo $puzzle_user_type; ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($puzzle_email ?? ''); ?>">
                    <input type="hidden" name="question" value="<?php echo htmlspecialchars($puzzle_data['question'] ?? $_SESSION['puzzle_question'] ?? ''); ?>">
                    <input type="hidden" name="correct_answer" value="<?php echo $puzzle_data['answer'] ?? $_SESSION['puzzle_answer'] ?? 0; ?>">
                    
                    <div class="puzzle-box">
                        <div class="puzzle-question">
                            <?php echo htmlspecialchars($puzzle_data['display'] ?? $_SESSION['puzzle_display'] ?? '5 + 3 = ?'); ?>
                        </div>
                        <p class="puzzle-hint">Add the two numbers together</p>
                        <input type="number" class="puzzle-input" name="math_answer" id="mathAnswer" placeholder="?" required autofocus>
                    </div>
                    
                    <div class="puzzle-attempts">
                        <span>Attempts left: </span>
                        <span class="attempts-left" id="attemptsLeft">3</span>
                    </div>
                    
                    <button type="submit" name="verify_math" class="btn-login" id="verifyMathBtn">
                        <i class="fas fa-check-circle"></i> Verify Answer
                    </button>
                </form>

                <div class="text-center mt-3">
                    <form method="POST" action="" style="display:inline;">
                        <button type="submit" name="reset_math" class="reset-puzzle-btn">
                            <i class="fas fa-redo me-1"></i> New Question
                        </button>
                    </form>
                    <span class="mx-2 text-muted" style="opacity:0.2;">|</span>
                    <a href="?reset=1" class="back-login-btn">
                        <i class="fas fa-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            </div>

            <!-- ===== LOGIN FORM ===== -->
            <div id="loginFormSection" style="<?php echo $show_puzzle ? 'display:none;' : 'display:block;'; ?>">
                <div class="role-selector" id="roleSelector">
                    <button type="button" class="role-btn active" data-role="admin">
                        <i class="fas fa-user-shield"></i> Admin
                    </button>
                    <button type="button" class="role-btn" data-role="staff">
                        <i class="fas fa-user-tie"></i> Staff
                    </button>
                    <button type="button" class="role-btn" data-role="student">
                        <i class="fas fa-user-graduate"></i> Student
                    </button>
                </div>

                <form method="POST" action="" id="adminForm" class="login-form">
                    <input type="hidden" name="role" value="admin">
                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-envelope me-1"></i> Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" id="loginEmail" placeholder="Enter your email" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock me-1"></i> Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" id="loginPassword" placeholder="Enter password" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    
                    <?php if ($remaining_attempts < 3 && !$is_blocked): ?>
                    <div class="attempts-warning pulse-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <?php echo $remaining_attempts; ?> attempt(s) remaining before account lock
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="login" class="btn-login" id="loginBtn" <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt"></i> Sign In as Admin
                    </button>
                </form>

                <form method="POST" action="" id="staffForm" class="login-form" style="display:none;">
                    <input type="hidden" name="role" value="staff">
                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-envelope me-1"></i> Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" placeholder="Enter your registered email" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock me-1"></i> Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" placeholder="Enter password" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    
                    <?php if ($remaining_attempts < 3 && !$is_blocked): ?>
                    <div class="attempts-warning pulse-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <?php echo $remaining_attempts; ?> attempt(s) remaining before account lock
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="login" class="btn-login" <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt"></i> Sign In as Staff
                    </button>
                </form>

                <form method="POST" action="" id="studentForm" class="login-form" style="display:none;">
                    <input type="hidden" name="role" value="student">
                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-envelope me-1"></i> Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" placeholder="Enter your student email" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock me-1"></i> Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" placeholder="Enter password" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    
                    <?php if ($remaining_attempts < 3 && !$is_blocked): ?>
                    <div class="attempts-warning pulse-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <?php echo $remaining_attempts; ?> attempt(s) remaining before account lock
                    </div>
                    <?php endif; ?>
                    
                    <!-- Forgot Password Link -->
                    <div class="text-center mt-2 mb-3">
                        <a href="#" class="reset-link" onclick="showResetForm()">
                            <i class="fas fa-key me-1"></i> Forgot Password? Request Reset
                        </a>
                    </div>
                    
                    <button type="submit" name="login" class="btn-login" <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt"></i> Sign In as Student
                    </button>
                </form>

                <!-- ============================================================
                PASSWORD RESET REQUEST FORM
                ============================================================ -->
                <div class="reset-section" id="resetSection">
                    <div class="text-center mb-4">
                        <h4 style="color: #ffffff; font-weight: 600;">
                            <i class="fas fa-key me-2" style="color: #ffd700;"></i>Request Password Reset
                        </h4>
                        <p style="color: rgba(255,255,255,0.5); font-size: 13px;">
                            Enter your email address to request a password reset
                        </p>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-envelope me-1"></i> Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="reset_email" placeholder="Enter your registered email" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-info-circle me-1"></i> Reason for Reset</label>
                            <textarea class="form-control" name="reset_reason" rows="2" placeholder="e.g., I forgot my password" style="height:60px; resize:none;"></textarea>
                        </div>
                        <button type="submit" name="request_reset" class="btn-reset">
                            <i class="fas fa-paper-plane me-1"></i> Request Reset
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="#" class="back-to-login-link" onclick="hideResetForm()">
                            <i class="fas fa-arrow-left me-1"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>

            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> <span>ISU-Echague Dormitory</span> — All Rights Reserved
            </div>
        </div>
    </div>

    <script>
        // ============================================================
        // ROLE SELECTOR
        // ============================================================
        document.querySelectorAll('.role-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const role = this.dataset.role;
                document.querySelectorAll('.login-form').forEach(form => {
                    form.style.display = 'none';
                });
                document.getElementById(role + 'Form').style.display = 'block';
                
                document.getElementById('resetSection').style.display = 'none';
            });
        });

        // ============================================================
        // AUTO-FOCUS
        // ============================================================
        <?php if ($show_puzzle): ?>
        document.getElementById('mathAnswer').focus();
        <?php else: ?>
        document.getElementById('loginEmail')?.focus();
        <?php endif; ?>

        // ============================================================
        // ENTER KEY SUPPORT
        // ============================================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeForm = document.querySelector('.login-form:not([style*="display: none"])');
                if (activeForm) {
                    e.preventDefault();
                    activeForm.submit();
                }
            }
        });

        // ============================================================
        // MATH PUZZLE - Auto-submit on Enter
        // ============================================================
        document.getElementById('mathAnswer')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('verifyMathBtn').click();
            }
        });

        // ============================================================
        // SHOW/HIDE RESET FORM
        // ============================================================
        function showResetForm() {
            document.getElementById('resetSection').style.display = 'block';
            document.getElementById('studentForm').style.display = 'none';
            document.getElementById('resetSection').scrollIntoView({ behavior: 'smooth' });
        }

        function hideResetForm() {
            document.getElementById('resetSection').style.display = 'none';
            document.getElementById('studentForm').style.display = 'block';
        }
        
        // ============================================================
        // COUNTDOWN TIMER FOR LOCKED ACCOUNT
        // ============================================================
        <?php if ($is_blocked && !empty($block_until)): ?>
        function updateLockTimer() {
            const now = new Date();
            const blockTime = new Date('<?php echo date('Y-m-d H:i:s', strtotime($block_until)); ?>');
            const diff = blockTime - now;
            
            if (diff > 0) {
                const minutes = Math.floor(diff / 60000);
                const seconds = Math.floor((diff % 60000) / 1000);
                const timerElement = document.querySelector('.lock-timer');
                if (timerElement) {
                    timerElement.textContent = minutes + 'm ' + seconds + 's';
                }
            } else {
                // Reload page to unlock
                location.reload();
            }
        }
        
        setInterval(updateLockTimer, 1000);
        updateLockTimer();
        <?php endif; ?>
    </script>
</body>
</html>
