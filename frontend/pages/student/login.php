<?php
/**
 * Tap-and-Go Doorlock - Student Login with Math Puzzle
 */

session_start();

require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

if (isset($_SESSION['student_id']) && isStudentSessionValid()) {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();
$error = '';
$is_locked = false;

function generatePuzzle() {
    $num1 = rand(1, 20);
    $num2 = rand(1, 20);
    $operators = ['+', '-', '×'];
    $op = $operators[rand(0, 2)];
    $answer = 0;
    
    switch ($op) {
        case '+': $answer = $num1 + $num2; break;
        case '-': 
            if ($num1 < $num2) { list($num1, $num2) = array($num2, $num1); }
            $answer = $num1 - $num2; 
            break;
        case '×': $answer = $num1 * $num2; break;
    }
    
    return [
        'num1' => $num1,
        'num2' => $num2,
        'operator' => $op,
        'answer' => $answer,
        'display' => "$num1 $op $num2 = ?"
    ];
}

// Check lock
if (isset($_SESSION['student_login_attempts']) && $_SESSION['student_login_attempts'] >= 5) {
    if (isset($_SESSION['student_lock_time']) && time() - $_SESSION['student_lock_time'] < 300) {
        $is_locked = true;
        $error = "Too many failed attempts. Please wait 5 minutes.";
    } else {
        $_SESSION['student_login_attempts'] = 0;
        unset($_SESSION['student_lock_time']);
    }
}

// Generate puzzle
if (!isset($_SESSION['student_puzzle']) || isset($_POST['new_puzzle'])) {
    $_SESSION['student_puzzle'] = generatePuzzle();
}
$puzzle = $_SESSION['student_puzzle'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($is_locked) {
        $error = "Account is temporarily locked. Please wait.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $puzzle_answer = isset($_POST['puzzle_answer']) ? (int)$_POST['puzzle_answer'] : -1;
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter username and password.';
        } elseif ($puzzle_answer !== $puzzle['answer']) {
            $_SESSION['student_login_attempts'] = ($_SESSION['student_login_attempts'] ?? 0) + 1;
            if ($_SESSION['student_login_attempts'] >= 5) {
                $_SESSION['student_lock_time'] = time();
                $is_locked = true;
                $error = "Too many failed attempts. Account locked for 5 minutes.";
            } else {
                $remaining = 5 - $_SESSION['student_login_attempts'];
                $error = "Incorrect puzzle answer. $remaining attempts remaining.";
                $_SESSION['student_puzzle'] = generatePuzzle();
                $puzzle = $_SESSION['student_puzzle'];
            }
        } else {
            $_SESSION['student_login_attempts'] = 0;
            unset($_SESSION['student_lock_time']);
            
            $result = authenticateStudent($username, $password);
            
            if ($result['success']) {
                $_SESSION['student_id'] = $result['student_id'];
                $_SESSION['student_id_number'] = $result['student_id_number'];
                $_SESSION['full_name'] = $result['full_name'];
                $_SESSION['course'] = $result['course'];
                $_SESSION['year_level'] = $result['year_level'];
                $_SESSION['role'] = 'student';
                $_SESSION['login_time'] = time();
                
                logStudentAudit($result['student_id'], 'Login', 'Student logged in');
                
                header('Location: dashboard.php?loading=true');
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - Tap-and-Go Doorlock</title>
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
            background: linear-gradient(135deg, #0a1628, #1a2a4a, #0d1f3c);
        }
        .login-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 40px 35px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
        }
        .logo-area { text-align: center; margin-bottom: 30px; }
        .logo-area .icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #ffd700;
            margin-bottom: 15px;
        }
        .logo-area h3 { color: white; font-weight: 700; }
        .logo-area h3 span { color: #ffd700; }
        .logo-area p { color: rgba(255,255,255,0.5); font-size: 13px; }
        .student-badge {
            display: inline-block;
            background: rgba(59,130,246,0.15);
            color: #93c5fd;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .puzzle-box {
            background: rgba(255,215,0,0.06);
            border: 1px solid rgba(255,215,0,0.15);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }
        .puzzle-box .question {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700;
            letter-spacing: 2px;
        }
        .puzzle-box .hint {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            margin-top: 5px;
        }
        .form-control {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 14px 16px;
            color: white;
            height: 50px;
        }
        .form-control:focus {
            background: rgba(255,255,255,0.1);
            border-color: #ffd700;
            box-shadow: 0 0 0 3px rgba(255,215,0,0.08);
            color: white;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.3); }
        .input-group-text {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: rgba(255,255,255,0.4);
        }
        .input-group .form-control {
            border-radius: 0 12px 12px 0;
            border-left: none;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            font-weight: 600;
            border-radius: 12px;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(26,58,106,0.4); }
        .btn-back {
            color: rgba(255,255,255,0.4);
            text-decoration: none;
            font-size: 13px;
        }
        .btn-back:hover { color: white; }
        .alert-danger {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.15);
            color: #fca5a5;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
        }
        .footer-text {
            color: rgba(255,255,255,0.15);
            font-size: 11px;
            text-align: center;
            margin-top: 20px;
        }
        .attempts-warning {
            text-align: center;
            font-size: 12px;
            color: rgba(255,215,0,0.5);
            margin-top: 5px;
        }
        .puzzle-input {
            font-size: 24px;
            text-align: center;
            font-weight: 600;
        }
        .btn-new-puzzle {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.3);
            font-size: 12px;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .btn-new-puzzle:hover { color: rgba(255,255,255,0.6); }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-area">
            <div class="icon"><i class="fas fa-user-graduate"></i></div>
            <h3><span>Student</span> Login</h3>
            <p>ISU-Echague Dormitory</p>
            <span class="student-badge"><i class="fas fa-puzzle-piece me-1"></i> Puzzle Verified</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-danger" style="padding:12px 16px; border-radius:12px; margin-bottom:20px;">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="form-label text-light">Student ID / Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                    <input type="text" class="form-control" name="username" placeholder="Enter student ID" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label text-light">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Enter password" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                </div>
            </div>

            <!-- Puzzle -->
            <div class="mb-3">
                <label class="form-label text-light">Solve the Puzzle</label>
                <div class="puzzle-box">
                    <div class="question"><?php echo $puzzle['display']; ?></div>
                    <div class="hint">Enter the answer below</div>
                </div>
                <div class="mt-2">
                    <input type="number" class="form-control puzzle-input" name="puzzle_answer" placeholder="?" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                </div>
                <div class="text-center mt-1">
                    <button type="submit" name="new_puzzle" class="btn-new-puzzle" <?php echo $is_locked ? 'disabled' : ''; ?>>
                        <i class="fas fa-redo me-1"></i> New Puzzle
                    </button>
                    <?php if (isset($_SESSION['student_login_attempts']) && $_SESSION['student_login_attempts'] > 0): ?>
                        <span class="attempts-warning">
                            Attempts: <?php echo $_SESSION['student_login_attempts']; ?>/5
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" name="login" class="btn btn-login" <?php echo $is_locked ? 'disabled' : ''; ?>>
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="../login.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i> Back to Main Login</a>
        </div>

        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> ISU-Echague Dormitory
        </div>
    </div>

    <script>
        document.querySelector('input[name="puzzle_answer"]')?.focus();
    </script>
</body>
</html>