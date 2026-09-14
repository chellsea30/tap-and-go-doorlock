<?php
/**
 * Tap-and-Go Doorlock - Student Portal Account Creation
 * Student creates email + password AFTER admin approval
 * Location: frontend/pages/student-portal-register.php
 */

session_start();
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

$error = '';
$success = '';
$studentData = null;
$showForm = false;

// ============================================================
// STEP 1: VERIFY STUDENT ID
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_student_id'])) {
    $student_id = strtoupper(trim($_POST['student_id'] ?? ''));
    
    if (empty($student_id)) {
        $error = 'Please enter your Student ID.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT u.*, rp.course, rp.year_level
            FROM users u
            LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
            WHERE u.student_id = ? AND u.status != 'deleted'
        ");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $studentData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$studentData) {
            $error = '❌ Student ID not found. Please register first.';
            $studentData = null;
        } elseif (($studentData['approval_status'] ?? 'pending') === 'pending') {
            $error = '⏳ Your registration is still PENDING approval. Please wait for admin.';
            $studentData = null;
        } elseif (($studentData['approval_status'] ?? '') === 'rejected') {
            $error = '❌ Your registration was REJECTED. Please contact the admin.';
            $studentData = null;
        } elseif (!empty($studentData['portal_email'])) {
            $error = '⚠️ You already have a portal account. Please login instead.';
            $studentData = null;
        } else {
            $showForm = true;
        }
    }
}

// ============================================================
// STEP 2: CREATE PORTAL ACCOUNT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
    $user_id = (int)$_POST['user_id'];
    $portal_email = trim($_POST['portal_email'] ?? '');
    $portal_password = $_POST['portal_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    if (empty($portal_email)) $errors[] = 'Email is required';
    if (!filter_var($portal_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (strlen($portal_password) < 8) $errors[] = 'Password must be 8+ characters';
    if (!preg_match('/[A-Z]/', $portal_password)) $errors[] = 'Need uppercase letter';
    if (!preg_match('/[a-z]/', $portal_password)) $errors[] = 'Need lowercase letter';
    if (!preg_match('/[0-9]/', $portal_password)) $errors[] = 'Need number';
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $portal_password)) $errors[] = 'Need special character';
    if ($portal_password !== $confirm_password) $errors[] = 'Passwords do not match';
    
    if (!empty($errors)) {
        $error = implode(', ', $errors);
        // Reload student data
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT u.*, rp.course, rp.year_level FROM users u LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id WHERE u.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $studentData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $showForm = true;
    } else {
        $conn = getDBConnection();
        
        // Check email uniqueness
        $check = $conn->prepare("SELECT student_id FROM student_users WHERE email = ?");
        $check->bind_param("s", $portal_email);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = 'Email already registered. Please use another email.';
            $showForm = true;
            $stmt = $conn->prepare("SELECT u.*, rp.course, rp.year_level FROM users u LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id WHERE u.user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $studentData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            // Get approved student data
            $stmt = $conn->prepare("
                SELECT u.*, rp.course, rp.year_level
                FROM users u
                LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
                WHERE u.user_id = ? AND u.approval_status = 'approved'
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $userData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$userData) {
                $error = 'Invalid student or not approved.';
            } else {
                $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $userData['full_name']));
                $password_hash = password_hash($portal_password, PASSWORD_DEFAULT);
                
                // Insert into student_users
                $insert = $conn->prepare("
                    INSERT INTO student_users (
                        student_id_number, full_name, username, course, year_level,
                        email, password_hash, phone, room_number, resident_id, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                
                $course = $userData['course'] ?? 'N/A';
                $year = $userData['year_level'] ?? 'N/A';
                $room = $userData['room_number'] ?? '';
                $phone = $userData['contact_number'] ?? '';
                
                $insert->bind_param("sssssssssi",
                    $userData['student_id'],
                    $userData['full_name'],
                    $username,
                    $course,
                    $year,
                    $portal_email,
                    $password_hash,
                    $phone,
                    $room,
                    $user_id
                );
                
                if ($insert->execute()) {
                    // Update users table
                    $update = $conn->prepare("
                        UPDATE users 
                        SET portal_email = ?, portal_password = ?, portal_created_at = NOW()
                        WHERE user_id = ?
                    ");
                    $update->bind_param("ssi", $portal_email, $password_hash, $user_id);
                    $update->execute();
                    $update->close();
                    
                    // Log
                    $log = $conn->prepare("
                        INSERT INTO student_registration_logs (user_id, action, details, performed_by)
                        VALUES (?, 'portal_created', 'Student created portal account', 'student')
                    ");
                    $log->bind_param("i", $user_id);
                    $log->execute();
                    $log->close();
                    
                    $success = "✅ Portal account created successfully!";
                    $studentData = null;
                    $showForm = false;
                } else {
                    $error = 'Failed to create account: ' . $insert->error;
                    $showForm = true;
                }
                $insert->close();
            }
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Portal Account - ISU Ladies Dormitory</title>
    <link rel="icon" type="image/png" href="../assets/images/isu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #0d1f3c 50%, #1a2a4a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card-custom {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            color: #e5e7eb;
        }
        .card-custom h2 {
            color: #ffd700;
            font-weight: 800;
            text-align: center;
            margin-bottom: 10px;
            font-size: 22px;
        }
        .card-custom .subtitle {
            text-align: center;
            color: rgba(255,255,255,0.5);
            font-size: 13px;
            margin-bottom: 30px;
        }
        .form-control {
            background: rgba(255,255,255,0.06) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            color: #e5e7eb !important;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            height: 46px;
        }
        .form-control:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255,215,0,0.15) !important;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.3) !important; }
        .form-label { color: #d1d5db; font-size: 13px; font-weight: 500; }
        .btn-gold {
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            border: none;
            color: #0a1628;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 14px;
            display: block;
            text-align: center;
            text-decoration: none;
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255,215,0,0.3);
            color: #0a1628;
        }
        .alert-custom {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }
        .alert-success {
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.3);
            color: #6ee7b7;
        }
        .alert-danger {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
        }
        .student-info {
            background: rgba(255,215,0,0.05);
            border: 1px solid rgba(255,215,0,0.2);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .password-requirements {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
            margin-top: 8px;
        }
        .password-requirements .req-item {
            padding: 2px 0;
        }
        .password-requirements .req-item.valid {
            color: #6ee7b7;
        }
        .password-requirements .req-item.invalid {
            color: #f87171;
        }
        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffd700;
        }
        .student-avatar-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 20px;
        }
        .success-info {
            background: rgba(16,185,129,0.1);
            border: 1px solid #10b981;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="card-custom">
        <h2><i class="fas fa-user-plus me-2"></i>Create Portal Account</h2>
        <p class="subtitle">For approved students only</p>

        <?php if (!empty($error)): ?>
            <div class="alert-custom alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
                <div class="success-info mt-3">
                    <strong>📧 Email:</strong> <?php echo htmlspecialchars($_POST['portal_email'] ?? ''); ?><br>
                    <strong>🔑 Password:</strong> (the one you created)
                </div>
                <a href="student/login.php" class="btn-gold mt-2">
                    <i class="fas fa-sign-in-alt me-2"></i> Go to Student Login
                </a>
            </div>
        <?php elseif ($showForm && $studentData): ?>
            <!-- STEP 2: Create Account Form -->
            <div class="student-info">
                <div class="d-flex align-items-center gap-3">
                    <?php 
                    $photoPath = $studentData['profile_photo'] ?? '';
                    $hasPhoto = false;
                    $fullPath = '';
                    if (!empty($photoPath)) {
                        $fullPath = '../../' . $photoPath;
                        if (file_exists($fullPath)) $hasPhoto = true;
                    }
                    ?>
                    <?php if ($hasPhoto): ?>
                        <img src="<?php echo $fullPath; ?>" class="student-avatar" alt="Photo">
                    <?php else: ?>
                        <div class="student-avatar-placeholder">
                            <?php 
                            $initials = '';
                            $parts = explode(' ', $studentData['full_name']);
                            foreach ($parts as $p) { if (!empty($p)) $initials .= strtoupper($p[0]); }
                            echo substr($initials, 0, 2);
                            ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong style="color: #ffd700;"><?php echo htmlspecialchars($studentData['full_name']); ?></strong><br>
                        <small style="color: rgba(255,255,255,0.5);">
                            <?php echo htmlspecialchars($studentData['student_id']); ?> · 
                            <?php echo htmlspecialchars($studentData['course'] ?? 'N/A'); ?>
                        </small><br>
                        <span style="color: #10b981; font-size: 11px;">
                            <i class="fas fa-check-circle me-1"></i> Approved
                        </span>
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $studentData['user_id']; ?>">
                
                <div class="mb-3">
                    <label class="form-label">Email Address <span style="color: #ef4444;">*</span></label>
                    <input type="email" class="form-control" name="portal_email" 
                           placeholder="your.email@example.com" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password <span style="color: #ef4444;">*</span></label>
                    <input type="password" class="form-control" name="portal_password" 
                           id="portalPassword" placeholder="Create password" required>
                    <div class="password-requirements" id="passwordReqs">
                        <div class="req-item" id="req-length">✗ At least 8 characters</div>
                        <div class="req-item" id="req-upper">✗ At least 1 uppercase letter</div>
                        <div class="req-item" id="req-lower">✗ At least 1 lowercase letter</div>
                        <div class="req-item" id="req-number">✗ At least 1 number</div>
                        <div class="req-item" id="req-special">✗ At least 1 special character</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Confirm Password <span style="color: #ef4444;">*</span></label>
                    <input type="password" class="form-control" name="confirm_password" 
                           id="confirmPassword" placeholder="Confirm password" required>
                    <small id="matchMsg" style="font-size: 11px;"></small>
                </div>
                
                <button type="submit" name="create_account" class="btn-gold">
                    <i class="fas fa-user-check me-2"></i> Create Account
                </button>
            </form>
        <?php else: ?>
            <!-- STEP 1: Enter Student ID -->
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Student ID <span style="color: #ef4444;">*</span></label>
                    <input type="text" class="form-control" name="student_id" 
                           placeholder="e.g., STU-2026-1234" required autofocus
                           style="text-transform: uppercase; font-family: monospace; font-weight: 600; letter-spacing: 2px;">
                    <div class="password-requirements">
                        <i class="fas fa-info-circle me-1"></i>
                        Enter the Student ID you received after registration
                    </div>
                </div>
                <button type="submit" name="check_student_id" class="btn-gold">
                    <i class="fas fa-search me-2"></i> Verify Student ID
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="student-self-registration.php" style="color: #ffd700; font-size: 12px; text-decoration: none;">
                    <i class="fas fa-arrow-left me-1"></i> Not registered yet? Register here
                </a>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-3">
            <a href="login.php" style="color: rgba(255,255,255,0.4); font-size: 12px; text-decoration: none;">
                <i class="fas fa-arrow-left me-1"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        const pwd = document.getElementById('portalPassword');
        const confirm = document.getElementById('confirmPassword');
        
        if (pwd) {
            pwd.addEventListener('input', function() {
                const val = this.value;
                toggleReq('req-length', val.length >= 8, 'At least 8 characters');
                toggleReq('req-upper', /[A-Z]/.test(val), 'At least 1 uppercase letter');
                toggleReq('req-lower', /[a-z]/.test(val), 'At least 1 lowercase letter');
                toggleReq('req-number', /[0-9]/.test(val), 'At least 1 number');
                toggleReq('req-special', /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val), 'At least 1 special character');
            });
        }
        
        if (confirm) {
            confirm.addEventListener('input', function() {
                const match = this.value === pwd.value;
                const msg = document.getElementById('matchMsg');
                if (this.value.length > 0) {
                    if (match) {
                        msg.textContent = '✓ Passwords match';
                        msg.style.color = '#6ee7b7';
                    } else {
                        msg.textContent = '✗ Passwords do not match';
                        msg.style.color = '#f87171';
                    }
                } else {
                    msg.textContent = '';
                }
            });
        }
        
        function toggleReq(id, valid, text) {
            const el = document.getElementById(id);
            if (!el) return;
            if (valid) {
                el.innerHTML = '✓ ' + text;
                el.className = 'req-item valid';
            } else {
                el.innerHTML = '✗ ' + text;
                el.className = 'req-item invalid';
            }
        }
    </script>
</body>
</html>
