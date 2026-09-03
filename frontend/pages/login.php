<?php
/**
 * Tap-and-Go Doorlock - Main Login with Math Puzzle
 * ISABELA STATE UNIVERSITY - LADIES DORMITORY
 * SIMPLE ADDITION ONLY
 * WITH 10-MINUTE BAN AFTER 3 INCORRECT ATTEMPTS
 * DESIGN: Modern Login Page
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
$remaining_attempts = 3;
$block_minutes = 10;
$block_until = '';
$max_attempts = 3;

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
        $conn->close();
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
    $conn->close();
    
    if (!$row) {
        return false;
    }
    
    if (!empty($row['login_blocked_until'])) {
        $blocked_until = strtotime($row['login_blocked_until']);
        if ($blocked_until > time()) {
            return [
                'blocked' => true,
                'blocked_until' => $row['login_blocked_until'],
                'remaining_seconds' => $blocked_until - time()
            ];
        } else {
            $stmt = $conn->prepare("UPDATE $table SET login_attempts = 0, login_blocked_until = NULL WHERE $id_field = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            $conn->close();
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
                    $email = $result['email'] ?? $email;
                }
                break;
        }
        
        if ($result && $result['success']) {
            resetLoginAttempts($role, $user_id);
            
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
                $conn->close();
                
                $error = "⛔ Too many failed puzzle attempts. You are blocked until $blocked_until. Please try again later.";
                $is_blocked = true;
            } else {
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
            $conn = getDBConnection();
            
            $user_found = false;
            $found_user_id = 0;
            $found_role = '';
            
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
                
                $stmt = $conn->prepare("SELECT login_attempts, login_blocked_until FROM $table WHERE $id_field = ?");
                $stmt->bind_param("i", $found_user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                
                $current_attempts = (int)($row['login_attempts'] ?? 0);
                $new_attempts = $current_attempts + 1;
                
                if (!empty($row['login_blocked_until'])) {
                    $blocked_until_time = strtotime($row['login_blocked_until']);
                    if ($blocked_until_time < time()) {
                        $new_attempts = 1;
                        $stmt = $conn->prepare("UPDATE $table SET login_attempts = 1, login_blocked_until = NULL WHERE $id_field = ?");
                        $stmt->bind_param("i", $found_user_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $remaining_minutes = ceil(($blocked_until_time - time()) / 60);
                        $error = "⛔ Account is temporarily locked. Please wait $remaining_minutes minute(s) before trying again.";
                        $is_blocked = true;
                        $block_until = date('h:i A', $blocked_until_time);
                        $remaining_attempts = 0;
                    }
                } else {
                    if ($new_attempts >= 3) {
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
                        
                        $remaining_attempts = 3 - $new_attempts;
                        $error = "Invalid credentials. You have $remaining_attempts attempt(s) remaining before account lock.";
                    }
                }
            } else {
                $error = 'Invalid credentials. Please try again.';
            }
            $conn->close();
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
                        $_SESSION['email'] = $email;
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
    $puzzle_email = $_SESSION['puzzle_email'] ?? '';
    $puzzle_name = $_SESSION['puzzle_name'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISU Ladies Dormitory - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           GLOBAL RESET & BASE
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #0a1628 0%, #0d1f3c 50%, #1a2a4a 100%);
            position: relative;
            overflow: hidden;
        }
        
        /* Background Animation */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(ellipse at 20% 50%, rgba(26, 86, 168, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 50%, rgba(26, 86, 168, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 100%, rgba(26, 58, 106, 0.04) 0%, transparent 40%);
            animation: rotateBg 60s linear infinite;
            z-index: 0;
        }
        
        @keyframes rotateBg {
            0% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.1); }
            100% { transform: rotate(360deg) scale(1); }
        }
        
        /* Floating Particles */
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.03);
            pointer-events: none;
            z-index: 0;
        }
        .particle:nth-child(1) { width: 300px; height: 300px; top: -100px; right: -100px; animation: float 20s ease-in-out infinite; }
        .particle:nth-child(2) { width: 200px; height: 200px; bottom: -50px; left: -50px; animation: float 25s ease-in-out infinite reverse; }
        .particle:nth-child(3) { width: 150px; height: 150px; top: 50%; left: 50%; transform: translate(-50%, -50%); animation: float 30s ease-in-out infinite; }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }
        
        /* ============================================================
           LOGIN CONTAINER
           ============================================================ */
        .login-wrapper {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 60px;
        }
        
        /* ============================================================
           LEFT SIDE - BRANDING
           ============================================================ */
        .brand-section {
            flex: 1;
            color: white;
            padding: 20px 0;
        }
        
        .brand-section .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .brand-section .logo-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #0a1628;
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.2);
        }
        
        .brand-section .logo-text h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin: 0;
            line-height: 1.1;
        }
        
        .brand-section .logo-text h1 span {
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .brand-section .logo-text p {
            font-size: 14px;
            color: rgba(255,255,255,0.5);
            margin: 0;
            font-weight: 400;
        }
        
        .brand-section .hero-text {
            margin: 40px 0;
        }
        
        .brand-section .hero-text h2 {
            font-size: 42px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 15px;
        }
        
        .brand-section .hero-text h2 span {
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .brand-section .hero-text p {
            font-size: 16px;
            color: rgba(255,255,255,0.6);
            max-width: 450px;
            line-height: 1.7;
        }
        
        .brand-section .features {
            display: flex;
            gap: 20px;
            margin-top: 30px;
        }
        
        .brand-section .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,255,255,0.7);
            font-size: 14px;
            font-weight: 500;
        }
        
        .brand-section .feature-item i {
            color: #ffd700;
            font-size: 16px;
        }
        
        .brand-section .footer-text {
            margin-top: 50px;
            font-size: 13px;
            color: rgba(255,255,255,0.2);
        }
        
        .brand-section .footer-text span {
            color: rgba(255, 215, 0, 0.3);
        }
        
        /* ============================================================
           RIGHT SIDE - LOGIN CARD
           ============================================================ */
        .login-card {
            width: 100%;
            max-width: 440px;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 32px;
            padding: 45px 40px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.4);
            position: relative;
            flex-shrink: 0;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: 32px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(255,215,0,0.1), transparent, rgba(255,215,0,0.05));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }
        
        .login-card .card-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .login-card .card-header h3 {
            color: white;
            font-weight: 700;
            font-size: 22px;
            margin-bottom: 6px;
        }
        
        .login-card .card-header p {
            color: rgba(255,255,255,0.4);
            font-size: 14px;
            margin: 0;
        }
        
        /* ============================================================
           ROLE SELECTOR
           ============================================================ */
        .role-selector {
            display: flex;
            gap: 8px;
            margin-bottom: 30px;
            background: rgba(255,255,255,0.05);
            border-radius: 14px;
            padding: 5px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        
        .role-btn {
            flex: 1;
            padding: 10px 12px;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .role-btn:hover {
            color: rgba(255,255,255,0.8);
        }
        
        .role-btn.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            color: white;
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
        
        .role-btn i {
            margin-right: 6px;
            font-size: 13px;
        }
        
        /* ============================================================
           FORM ELEMENTS
           ============================================================ */
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }
        
        .form-group .input-group {
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .form-group .input-group:focus-within {
            border-color: rgba(255,215,0,0.3);
            box-shadow: 0 0 0 4px rgba(255,215,0,0.05);
        }
        
        .form-group .input-group .input-icon {
            padding: 0 16px;
            color: rgba(255,255,255,0.3);
            font-size: 15px;
            flex-shrink: 0;
        }
        
        .form-group .input-group input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 14px 16px 14px 0;
            color: white;
            font-size: 14px;
            outline: none;
            width: 100%;
        }
        
        .form-group .input-group input::placeholder {
            color: rgba(255,255,255,0.25);
        }
        
        .form-group .input-group input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .form-group .input-group .toggle-password {
            padding: 0 16px;
            color: rgba(255,255,255,0.3);
            cursor: pointer;
            transition: color 0.3s ease;
            background: none;
            border: none;
        }
        
        .form-group .input-group .toggle-password:hover {
            color: rgba(255,255,255,0.6);
        }
        
        /* ============================================================
           LOCK INDICATOR
           ============================================================ */
        .lock-indicator {
            display: <?php echo $is_blocked ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 16px;
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.15);
            border-radius: 12px;
            margin-bottom: 18px;
            color: #f87171;
            font-size: 13px;
        }
        
        .lock-indicator i {
            font-size: 18px;
        }
        
        .lock-indicator .lock-timer {
            color: #ffd700;
            font-weight: 700;
        }
        
        /* ============================================================
           ATTEMPTS WARNING
           ============================================================ */
        .attempts-warning {
            color: #fbbf24;
            font-size: 13px;
            text-align: center;
            padding: 8px;
            border-radius: 10px;
            background: rgba(251, 191, 36, 0.05);
            border: 1px solid rgba(251, 191, 36, 0.1);
            margin-bottom: 10px;
        }
        
        .pulse-warning {
            animation: pulseWarning 1.5s ease-in-out infinite;
        }
        
        @keyframes pulseWarning {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* ============================================================
           ALERTS
           ============================================================ */
        .alert-custom {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-custom i {
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .alert-custom.alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }
        
        .alert-custom.alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
        }
        
        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn-login {
            width: 100%;
            padding: 16px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 14px;
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            border: none;
            color: #0a1628;
            transition: all 0.3s ease;
            cursor: pointer;
            height: 54px;
            margin-top: 8px;
        }
        
        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.25);
        }
        
        .btn-login:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-login i {
            margin-right: 8px;
        }
        
        .btn-login-puzzle {
            width: 100%;
            padding: 16px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 14px;
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            border: none;
            color: #0a1628;
            transition: all 0.3s ease;
            cursor: pointer;
            height: 54px;
            margin-top: 8px;
        }
        
        .btn-login-puzzle:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.25);
        }
        
        .btn-login-puzzle:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* ============================================================
           MATH PUZZLE
           ============================================================ */
        .puzzle-section {
            display: <?php echo $show_puzzle ? 'block' : 'none'; ?>;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .puzzle-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .puzzle-header h4 {
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        
        .puzzle-header p {
            color: rgba(255,255,255,0.4);
            font-size: 13px;
            margin: 0;
        }
        
        .puzzle-header .user-email {
            color: #ffd700;
            font-weight: 500;
            font-size: 14px;
            margin-top: 4px;
        }
        
        .puzzle-box {
            background: rgba(255,255,255,0.04);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.06);
            margin: 20px 0;
        }
        
        .puzzle-question {
            font-size: 36px;
            font-weight: 700;
            color: #ffd700;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        
        .puzzle-hint {
            color: rgba(255,255,255,0.3);
            font-size: 12px;
        }
        
        .puzzle-input {
            width: 120px;
            height: 60px;
            background: rgba(255,255,255,0.06);
            border: 2px solid rgba(255,215,0,0.15);
            border-radius: 12px;
            color: white;
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            transition: all 0.3s ease;
            margin: 15px auto;
            display: block;
            outline: none;
        }
        
        .puzzle-input:focus {
            border-color: #ffd700;
            box-shadow: 0 0 0 4px rgba(255,215,0,0.05);
        }
        
        .puzzle-attempts {
            text-align: center;
            color: rgba(255,255,255,0.3);
            font-size: 13px;
            margin: 10px 0;
        }
        
        .puzzle-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }
        
        .puzzle-actions .reset-puzzle-btn {
            background: transparent;
            border: none;
            color: rgba(255,215,0,0.4);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: underline;
        }
        
        .puzzle-actions .reset-puzzle-btn:hover {
            color: #ffd700;
        }
        
        .puzzle-actions .back-login-btn {
            color: rgba(255,255,255,0.3);
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
            background: none;
            border: none;
        }
        
        .puzzle-actions .back-login-btn:hover {
            color: rgba(255,255,255,0.6);
        }
        
        .puzzle-actions .divider {
            color: rgba(255,255,255,0.1);
        }
        
        /* ============================================================
           PASSWORD RESET SECTION
           ============================================================ */
        .reset-section {
            display: <?php echo isset($_POST['show_reset']) ? 'block' : 'none'; ?>;
            animation: fadeIn 0.5s ease;
        }
        
        .reset-section .reset-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .reset-section .reset-header h4 {
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        
        .reset-section .reset-header p {
            color: rgba(255,255,255,0.4);
            font-size: 13px;
            margin: 0;
        }
        
        .reset-section .reset-link {
            color: rgba(255,215,0,0.6);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            background: none;
            border: none;
        }
        
        .reset-section .reset-link:hover {
            color: #ffd700;
        }
        
        .reset-section .back-to-login-link {
            color: rgba(255,255,255,0.3);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            background: none;
            border: none;
        }
        
        .reset-section .back-to-login-link:hover {
            color: rgba(255,255,255,0.6);
        }
        
        .btn-reset {
            width: 100%;
            padding: 16px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 14px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
            color: white;
            transition: all 0.3s ease;
            cursor: pointer;
            height: 54px;
            margin-top: 8px;
        }
        
        .btn-reset:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.25);
        }
        
        .btn-reset:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-reset i {
            margin-right: 8px;
        }
        
        textarea.form-control-custom {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            color: white;
            font-size: 14px;
            transition: all 0.3s ease;
            outline: none;
            resize: none;
            font-family: 'Inter', sans-serif;
        }
        
        textarea.form-control-custom:focus {
            border-color: rgba(255,215,0,0.3);
            box-shadow: 0 0 0 4px rgba(255,215,0,0.05);
        }
        
        textarea.form-control-custom::placeholder {
            color: rgba(255,255,255,0.25);
        }
        
        /* ============================================================
           FORGOT PASSWORD LINK
           ============================================================ */
        .forgot-password-link {
            color: rgba(255,255,255,0.3);
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 4px;
        }
        
        .forgot-password-link:hover {
            color: rgba(255,215,0,0.6);
        }
        
        /* ============================================================
           FOOTER TEXT (Login Card)
           ============================================================ */
        .login-footer-text {
            text-align: center;
            margin-top: 25px;
            color: rgba(255,255,255,0.12);
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        
        .login-footer-text span {
            color: rgba(255,215,0,0.15);
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 992px) {
            .login-wrapper {
                flex-direction: column;
                gap: 30px;
            }
            
            .brand-section {
                text-align: center;
                padding: 0;
            }
            
            .brand-section .logo {
                justify-content: center;
            }
            
            .brand-section .hero-text p {
                max-width: 100%;
            }
            
            .brand-section .features {
                justify-content: center;
            }
            
            .brand-section .footer-text {
                margin-top: 20px;
            }
            
            .login-card {
                max-width: 100%;
                padding: 35px 30px;
            }
            
            .brand-section .hero-text h2 {
                font-size: 32px;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .login-card {
                padding: 25px 20px;
                border-radius: 24px;
            }
            
            .brand-section .hero-text h2 {
                font-size: 26px;
            }
            
            .brand-section .features {
                flex-wrap: wrap;
                gap: 12px;
            }
            
            .brand-section .feature-item {
                font-size: 12px;
            }
            
            .role-btn {
                font-size: 11px;
                padding: 8px 6px;
            }
            
            .role-btn i {
                margin-right: 3px;
            }
            
            .puzzle-question {
                font-size: 28px;
            }
            
            .puzzle-input {
                width: 90px;
                height: 50px;
                font-size: 22px;
            }
            
            .login-card .card-header h3 {
                font-size: 18px;
            }
        }
        
        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #0a1628;
        }
        ::-webkit-scrollbar-thumb {
            background: #1a2a4a;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #ffd700;
        }
    </style>
</head>
<body>
    
    <!-- Floating Particles -->
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    
    <div class="login-wrapper">
        
        <!-- ===== LEFT SIDE - BRANDING ===== -->
        <div class="brand-section">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-door-open"></i>
                </div>
                <div class="logo-text">
                    <h1>ISU <span>Ladies</span></h1>
                    <p>Isabela State University · Echague</p>
                </div>
            </div>
            
            <div class="hero-text">
                <h2>Welcome to <span>Dormitory</span> Portal</h2>
                <p>
                    Secure access to the Isabela State University Ladies Dormitory 
                    management system. Track attendance, manage residents, and 
                    monitor access in real-time.
                </p>
            </div>
            
            <div class="features">
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    Secure Access
                </div>
                <div class="feature-item">
                    <i class="fas fa-sync-alt"></i>
                    Real-time Sync
                </div>
                <div class="feature-item">
                    <i class="fas fa-lock"></i>
                    Tamper-proof
                </div>
            </div>
            
            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> <span>Isabela State University</span> · Ladies Dormitory
            </div>
        </div>
        
        <!-- ===== RIGHT SIDE - LOGIN CARD ===== -->
        <div class="login-card" id="loginCard">
            
            <div class="card-header">
                <h3>Sign in to your account</h3>
                <p>Access your dashboard and manage attendance records securely.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-custom alert-<?php echo strpos($error, '✅') !== false || strpos($error, 'solved') !== false ? 'success' : 'danger'; ?>">
                    <i class="fas <?php echo strpos($error, '✅') !== false || strpos($error, 'solved') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($reset_success)): ?>
                <div class="alert-custom alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $reset_success; ?>
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
                    <h4><i class="fas fa-calculator" style="color: #ffd700; margin-right: 8px;"></i>Solve the Math Puzzle</h4>
                    <p>Please solve this simple addition problem to continue</p>
                    <div class="user-email">
                        <i class="fas fa-user me-1"></i> 
                        <?php echo htmlspecialchars($puzzle_name ?? ''); ?> 
                        <span style="color: rgba(255,255,255,0.4); font-weight: 400;">
                            (<?php echo htmlspecialchars($puzzle_email ?? ''); ?>)
                        </span>
                    </div>
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
                        <span id="attemptsLeft">3</span>
                    </div>
                    
                    <button type="submit" name="verify_math" class="btn-login-puzzle" id="verifyMathBtn">
                        <i class="fas fa-check-circle"></i> Verify Answer
                    </button>
                </form>

                <div class="puzzle-actions">
                    <form method="POST" action="" style="display:inline;">
                        <button type="submit" name="reset_math" class="reset-puzzle-btn">
                            <i class="fas fa-redo me-1"></i> New Question
                        </button>
                    </form>
                    <span class="divider">|</span>
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
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope me-1"></i> Email Address</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" id="loginEmail" placeholder="Enter your email" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock me-1"></i> Password</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="loginPassword" placeholder="Enter password" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($remaining_attempts < 3 && !$is_blocked): ?>
                    <div class="attempts-warning pulse-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <?php echo $remaining_attempts; ?> attempt(s) remaining before account lock
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="login" class="btn-login" id="loginBtn" <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>

                <form method="POST" action="" id="staffForm" class="login-form" style="display:none;">
                    <input type="hidden" name="role" value="staff">
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope me-1"></i> Email Address</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" placeholder="Enter your registered email" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock me-1"></i> Password</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" placeholder="Enter password" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($remaining_attempts < 3 && !$is_blocked): ?>
                    <div class="attempts-warning pulse-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <?php echo $remaining_attempts; ?> attempt(s) remaining before account lock
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="login" class="btn-login" <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>

                <form method="POST" action="" id="studentForm" class="login-form" style="display:none;">
                    <input type="hidden" name="role" value="student">
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope me-1"></i> Email Address</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" placeholder="Enter your student email" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock me-1"></i> Password</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" placeholder="Enter password" required <?php echo $is_blocked ? 'disabled' : ''; ?>>
                            <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                            </button>
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
                        <a href="#" class="forgot-password-link" onclick="showResetForm()">
                            <i class="fas fa-key me-1"></i> Forgot Password? Request Reset
                        </a>
                    </div>
                    
                    <button type="submit" name="login" class="btn-login" <?php echo $is_blocked ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>

                <!-- ============================================================
                PASSWORD RESET REQUEST FORM
                ============================================================ -->
                <div class="reset-section" id="resetSection">
                    <div class="reset-header">
                        <h4><i class="fas fa-key" style="color: #ffd700; margin-right: 8px;"></i>Request Password Reset</h4>
                        <p>Enter your email address to request a password reset</p>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label><i class="fas fa-envelope me-1"></i> Email Address</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control-custom" name="reset_email" placeholder="Enter your registered email" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-info-circle me-1"></i> Reason for Reset</label>
                            <textarea class="form-control-custom" name="reset_reason" rows="2" placeholder="e.g., I forgot my password" style="height:60px;"></textarea>
                        </div>
                        
                        <button type="submit" name="request_reset" class="btn-reset">
                            <i class="fas fa-paper-plane me-1"></i> Request Reset
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <button type="button" class="back-to-login-link" onclick="hideResetForm()">
                            <i class="fas fa-arrow-left me-1"></i> Back to Login
                        </button>
                    </div>
                </div>
                
                <!-- ===== LOGIN FOOTER ===== -->
                <div class="login-footer-text">
                    &copy; <?php echo date('Y'); ?> <span>Isabela State University</span> · Ladies Dormitory
                </div>
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
        // TOGGLE PASSWORD VISIBILITY
        // ============================================================
        function togglePasswordVisibility() {
            const passwordInputs = document.querySelectorAll('input[type="password"]');
            const icon = document.getElementById('passwordToggleIcon');
            
            passwordInputs.forEach(input => {
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) icon.className = 'fas fa-eye-slash';
                } else {
                    input.type = 'password';
                    if (icon) icon.className = 'fas fa-eye';
                }
            });
        }

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
                location.reload();
            }
        }
        
        setInterval(updateLockTimer, 1000);
        updateLockTimer();
        <?php endif; ?>
    </script>
</body>
</html>
