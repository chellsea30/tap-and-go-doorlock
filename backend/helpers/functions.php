<?php
/**
 * Tap-and-Go Doorlock - Complete Functions
 * WITH MATH PUZZLE, AUTHENTICATION, ENCRYPTION
 * WITH LOGIN ATTEMPTS (5 attempts = 10-minute ban)
 * WITH AUTO-EXPIRATION SYSTEM
 * COMPLETE VERSION
 * NOTE: getDBConnection() is defined in config.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

// ============================================================
// EMAIL ENCRYPTION FUNCTIONS
// ============================================================

/**
 * Encrypt email for storage
 * 
 * @param string $email Plain email
 * @return string Encrypted email
 */
function encryptEmail(string $email): string {
    $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'tapandgo_secret_key_2024_secure_encryption_32bytes!!';
    $method = defined('ENCRYPTION_METHOD') ? ENCRYPTION_METHOD : 'AES-256-CBC';
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    $encrypted = openssl_encrypt($email, $method, $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt email from storage
 * 
 * @param string $encryptedEmail Encrypted email
 * @return string Decrypted email
 */
function decryptEmail(string $encryptedEmail): string {
    $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'tapandgo_secret_key_2024_secure_encryption_32bytes!!';
    $method = defined('ENCRYPTION_METHOD') ? ENCRYPTION_METHOD : 'AES-256-CBC';
    $data = base64_decode($encryptedEmail);
    $iv_length = openssl_cipher_iv_length($method);
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    return openssl_decrypt($encrypted, $method, $key, 0, $iv);
}

/**
 * Hash email for searching (SHA256)
 * 
 * @param string $email Plain email
 * @return string Hashed email for search
 */
function hashEmailForSearch(string $email): string {
    return hash('sha256', strtolower(trim($email)));
}

// ============================================================
// LOGIN ATTEMPTS FUNCTIONS - 10-MINUTE BAN
// ============================================================

/**
 * Reset login attempts for a user
 * 
 * @param string $role The user role (admin, staff, student)
 * @param int $user_id The user ID
 * @return bool True on success, false on failure
 */
function resetLoginAttempts($role, $user_id) {
    try {
        $conn = getDBConnection();
        $table = $role . '_users';
        $id_field = $role . '_id';
        
        $stmt = $conn->prepare("UPDATE $table SET login_attempts = 0, login_blocked_until = NULL WHERE $id_field = ?");
        $stmt->bind_param("i", $user_id);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get login attempt status for a user
 * 
 * @param string $role The user role (admin, staff, student)
 * @param int $user_id The user ID
 * @return array Status array with 'blocked', 'attempts', 'blocked_until' keys
 */
function getLoginAttemptStatus($role, $user_id) {
    try {
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
            $conn->close();
            return [
                'blocked' => false,
                'attempts' => 0,
                'blocked_until' => null
            ];
        }
        
        $blocked_until = $row['login_blocked_until'] ?? null;
        $is_blocked = false;
        $remaining_seconds = 0;
        
        if (!empty($blocked_until)) {
            $blocked_time = strtotime($blocked_until);
            if ($blocked_time > time()) {
                $is_blocked = true;
                $remaining_seconds = $blocked_time - time();
            } else {
                // Block expired - reset attempts
                resetLoginAttempts($role, $user_id);
                $conn->close();
                return [
                    'blocked' => false,
                    'attempts' => 0,
                    'blocked_until' => null
                ];
            }
        }
        $conn->close();
        return [
            'blocked' => $is_blocked,
            'attempts' => (int)($row['login_attempts'] ?? 0),
            'blocked_until' => $blocked_until,
            'remaining_seconds' => $remaining_seconds
        ];
    } catch (Exception $e) {
        return [
            'blocked' => false,
            'attempts' => 0,
            'blocked_until' => null
        ];
    }
}

/**
 * Increment login attempts for a user
 * 
 * @param string $role The user role (admin, staff, student)
 * @param int $user_id The user ID
 * @param int $max_attempts Maximum attempts before lock (default: 5)
 * @param int $lock_minutes Lock duration in minutes (default: 10)
 * @return array Result with 'locked', 'attempts', 'message'
 */
function incrementLoginAttempts($role, $user_id, $max_attempts = 5, $lock_minutes = 10) {
    try {
        $conn = getDBConnection();
        $table = $role . '_users';
        $id_field = $role . '_id';
        
        // Get current attempts
        $stmt = $conn->prepare("SELECT login_attempts, login_blocked_until FROM $table WHERE $id_field = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $current_attempts = (int)($row['login_attempts'] ?? 0);
        $new_attempts = $current_attempts + 1;
        
        // Check if already blocked
        if (!empty($row['login_blocked_until'])) {
            $blocked_time = strtotime($row['login_blocked_until']);
            if ($blocked_time > time()) {
                $remaining = ceil(($blocked_time - time()) / 60);
                $conn->close();
                return [
                    'locked' => true,
                    'attempts' => $current_attempts,
                    'message' => "Account locked. Please wait $remaining minute(s).",
                    'blocked_until' => $row['login_blocked_until']
                ];
            }
        }
        
        if ($new_attempts >= $max_attempts) {
            // Lock account
            $block_until = date('Y-m-d H:i:s', strtotime("+$lock_minutes minutes"));
            $stmt = $conn->prepare("UPDATE $table SET login_attempts = ?, login_blocked_until = ? WHERE $id_field = ?");
            $stmt->bind_param("isi", $new_attempts, $block_until, $user_id);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            
            return [
                'locked' => true,
                'attempts' => $new_attempts,
                'message' => "Account locked for $lock_minutes minutes.",
                'blocked_until' => $block_until
            ];
        } else {
            // Update attempts
            $stmt = $conn->prepare("UPDATE $table SET login_attempts = ? WHERE $id_field = ?");
            $stmt->bind_param("ii", $new_attempts, $user_id);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            
            $remaining = $max_attempts - $new_attempts;
            return [
                'locked' => false,
                'attempts' => $new_attempts,
                'message' => "$remaining attempt(s) remaining before lock.",
                'remaining_attempts' => $remaining
            ];
        }
    } catch (Exception $e) {
        return [
            'locked' => false,
            'attempts' => 0,
            'message' => 'Error updating attempts.',
            'remaining_attempts' => 5
        ];
    }
}

/**
 * Check if user is blocked for login
 * 
 * @param string $role The user role (admin, staff, student)
 * @param int $user_id The user ID
 * @return array|bool Returns false if not blocked, or array with block info
 */
function isLoginBlocked($role, $user_id) {
    try {
        $conn = getDBConnection();
        $table = $role . '_users';
        $id_field = $role . '_id';
        
        $stmt = $conn->prepare("SELECT login_blocked_until FROM $table WHERE $id_field = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        if (!$row || empty($row['login_blocked_until'])) {
            return false;
        }
        
        $blocked_time = strtotime($row['login_blocked_until']);
        if ($blocked_time > time()) {
            return [
                'blocked_until' => $row['login_blocked_until'],
                'remaining_seconds' => $blocked_time - time(),
                'remaining_minutes' => ceil(($blocked_time - time()) / 60)
            ];
        }
        
        // Block expired
        resetLoginAttempts($role, $user_id);
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// ADMIN AUTHENTICATION - FIXED (DIRECT EMAIL CHECK)
// ============================================================

/**
 * Authenticate admin user using email - FIXED
 * 
 * @param string $email Admin email
 * @param string $password Password
 * @return array{success: bool, message: string, admin_id?: int, username?: string, full_name?: string, role?: string, email?: string}
 */
function authenticateAdminByEmail(string $email, string $password): array {
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT admin_id, username, email, password_hash, full_name, role, is_active FROM admin_users WHERE email = ? AND is_active = 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ((int)$row['is_active'] !== 1) {
                return ['success' => false, 'message' => 'Your account is deactivated. Please contact administrator.'];
            }
            
            if (password_verify($password, $row['password_hash'])) {
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'admin_id' => (int)$row['admin_id'],
                    'username' => $row['username'],
                    'full_name' => $row['full_name'],
                    'role' => $row['role'],
                    'email' => $row['email']
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid password. Please try again.'];
            }
        }
        
        return ['success' => false, 'message' => 'Email not found. Please check your email address.'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Authentication error: ' . $e->getMessage()];
    }
}

/**
 * Authenticate user (Legacy - username based)
 * 
 * @param string $username Username to authenticate
 * @param string $password Password to verify
 * @return array{success: bool, message: string, admin_id?: int, username?: string, full_name?: string, role?: string}
 */
function authenticateUser(string $username, string $password): array {
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT admin_id, username, password_hash, full_name, role, is_active FROM admin_users WHERE username = ?");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ((int)$row['is_active'] !== 1) {
                return ['success' => false, 'message' => 'Account is deactivated.'];
            }
            
            if (password_verify($password, $row['password_hash'])) {
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'admin_id' => (int)$row['admin_id'],
                    'username' => $row['username'],
                    'full_name' => $row['full_name'],
                    'role' => $row['role']
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid password.'];
            }
        } else {
            return ['success' => false, 'message' => 'Username not found.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Authentication error: ' . $e->getMessage()];
    }
}

/**
 * Update admin last login
 * 
 * @param int $admin_id Admin user ID
 * @return void
 */
function updateLastLogin(int $admin_id): void {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE admin_id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Silently fail
    }
}

// ============================================================
// STAFF AUTHENTICATION
// ============================================================

/**
 * Authenticate staff user using email
 * 
 * @param string $email Staff email
 * @param string $password Password
 * @return array{success: bool, message: string, staff_id?: int, staff_id_number?: string, full_name?: string, department?: string, email?: string}
 */
function authenticateStaffByEmail(string $email, string $password): array {
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT staff_id, staff_id_number, full_name, email, password_hash, department, is_active FROM staff_users WHERE email = ? AND is_active = 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error'];
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ((int)$row['is_active'] !== 1) {
                return ['success' => false, 'message' => 'Your account is deactivated. Please contact administrator.'];
            }
            
            if (password_verify($password, $row['password_hash'])) {
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'staff_id' => (int)$row['staff_id'],
                    'staff_id_number' => $row['staff_id_number'],
                    'full_name' => $row['full_name'],
                    'department' => $row['department'] ?? 'Dormitory Staff',
                    'email' => $row['email']
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid password. Please try again.'];
            }
        }
        
        return ['success' => false, 'message' => 'Email not found. Please check your email address.'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Authentication error: ' . $e->getMessage()];
    }
}

/**
 * Authenticate staff (Legacy - staff_id or username)
 * 
 * @param string $username Staff ID or username
 * @param string $password Password
 * @return array{success: bool, message: string, staff_id?: int, staff_id_number?: string, full_name?: string, department?: string}
 */
function authenticateStaff(string $username, string $password): array {
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT staff_id, staff_id_number, full_name, email, password_hash, department, is_active FROM staff_users WHERE (staff_id_number = ? OR email = ?) AND is_active = 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ((int)$row['is_active'] !== 1) {
                return ['success' => false, 'message' => 'Account is deactivated.'];
            }
            
            if (password_verify($password, $row['password_hash'])) {
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'staff_id' => (int)$row['staff_id'],
                    'staff_id_number' => $row['staff_id_number'],
                    'full_name' => $row['full_name'],
                    'department' => $row['department']
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid password.'];
            }
        } else {
            return ['success' => false, 'message' => 'Staff account not found.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Authentication error: ' . $e->getMessage()];
    }
}

/**
 * Get staff by ID
 * 
 * @param int $staff_id Staff ID
 * @return array|null Staff data or null
 */
function getStaffById(int $staff_id): ?array {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM staff_users WHERE staff_id = ? AND is_active = 1");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get all staff members
 * 
 * @return array List of staff members
 */
function getAllStaff(): array {
    try {
        $conn = getDBConnection();
        $result = $conn->query("SELECT * FROM staff_users WHERE is_active = 1 ORDER BY full_name");
        $staff = [];
        while ($row = $result->fetch_assoc()) {
            $staff[] = $row;
        }
        $conn->close();
        return $staff;
    } catch (Exception $e) {
        return [];
    }
}

// ============================================================
// STUDENT AUTHENTICATION
// ============================================================

/**
 * Get student by ID
 * 
 * @param int $student_id Student ID
 * @return array|null Student data or null
 */
function getStudentById(int $student_id): ?array {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM student_users WHERE student_id = ? AND is_active = 1");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Authenticate student user
 * 
 * @param string $username Student ID or email
 * @param string $password Password
 * @return array{success: bool, message: string, student_id?: int, student_id_number?: string, full_name?: string, course?: string, year_level?: string}
 */
function authenticateStudent(string $username, string $password): array {
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT student_id, student_id_number, full_name, course, year_level, email, password_hash, is_active FROM student_users WHERE (student_id_number = ? OR email = ?) AND is_active = 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ((int)$row['is_active'] !== 1) {
                return ['success' => false, 'message' => 'Account is deactivated.'];
            }
            
            if (password_verify($password, $row['password_hash'])) {
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'student_id' => (int)$row['student_id'],
                    'student_id_number' => $row['student_id_number'],
                    'full_name' => $row['full_name'],
                    'course' => $row['course'],
                    'year_level' => $row['year_level']
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid password.'];
            }
        } else {
            return ['success' => false, 'message' => 'Student account not found.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Authentication error: ' . $e->getMessage()];
    }
}

// ============================================================
// MATH PUZZLE FUNCTIONS
// ============================================================

/**
 * Generate a random math puzzle - SIMPLE ADDITION ONLY
 * 
 * @return array{question: string, answer: int, display: string, num1: int, num2: int}
 */
function generateMathPuzzle(): array {
    $num1 = rand(1, 20);
    $num2 = rand(1, 20);
    $answer = $num1 + $num2;
    $question = "$num1 + $num2";
    $display = "$num1 + $num2 = ?";
    
    return [
        'question' => $question,
        'answer' => $answer,
        'display' => $display,
        'num1' => $num1,
        'num2' => $num2
    ];
}

/**
 * Check math puzzle answer
 * 
 * @param string $user_type admin/staff/student
 * @param int $user_id User ID
 * @param string $email User email
 * @param string $question The math question
 * @param int $user_answer User's answer
 * @param int $correct_answer Correct answer
 * @return array{success: bool, message: string, attempts_left?: int, blocked?: bool}
 */
function checkMathPuzzle(string $user_type, int $user_id, string $email, string $question, int $user_answer, int $correct_answer): array {
    try {
        $conn = getDBConnection();
        
        logMathAttempt($user_type, $user_id, $email, $question, $user_answer, $correct_answer, $user_answer === $correct_answer);
        
        if ($user_answer === $correct_answer) {
            resetMathAttempts($user_type, $user_id);
            return ['success' => true, 'message' => 'Math puzzle solved correctly!'];
        } else {
            $attempts = incrementMathAttempts($user_type, $user_id);
            $max_attempts = 3;
            $attempts_left = $max_attempts - $attempts;
            
            if ($attempts >= $max_attempts) {
                $block_time = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                blockMathUser($user_type, $user_id, $block_time);
                return ['success' => false, 'message' => 'Too many failed attempts. You are blocked for 5 minutes.', 'blocked' => true];
            }
            
            return ['success' => false, 'message' => "Incorrect answer. You have $attempts_left attempt(s) left.", 'attempts_left' => $attempts_left];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Increment math attempts for user
 * 
 * @param string $user_type admin/staff/student
 * @param int $user_id User ID
 * @return int Number of attempts
 */
function incrementMathAttempts(string $user_type, int $user_id): int {
    try {
        $conn = getDBConnection();
        
        $table = '';
        $id_field = '';
        switch ($user_type) {
            case 'admin':
                $table = 'admin_users';
                $id_field = 'admin_id';
                break;
            case 'staff':
                $table = 'staff_users';
                $id_field = 'staff_id';
                break;
            case 'student':
                $table = 'student_users';
                $id_field = 'student_id';
                break;
            default:
                return 0;
        }
        
        $stmt = $conn->prepare("UPDATE $table SET math_attempts = math_attempts + 1 WHERE $id_field = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("SELECT math_attempts FROM $table WHERE $id_field = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        return (int)($row['math_attempts'] ?? 0);
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Reset math attempts for user
 * 
 * @param string $user_type admin/staff/student
 * @param int $user_id User ID
 * @return void
 */
function resetMathAttempts(string $user_type, int $user_id): void {
    try {
        $conn = getDBConnection();
        
        $table = '';
        $id_field = '';
        switch ($user_type) {
            case 'admin':
                $table = 'admin_users';
                $id_field = 'admin_id';
                break;
            case 'staff':
                $table = 'staff_users';
                $id_field = 'staff_id';
                break;
            case 'student':
                $table = 'student_users';
                $id_field = 'student_id';
                break;
            default:
                return;
        }
        
        $stmt = $conn->prepare("UPDATE $table SET math_attempts = 0, math_blocked_until = NULL WHERE $id_field = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Block user from math puzzle
 * 
 * @param string $user_type admin/staff/student
 * @param int $user_id User ID
 * @param string $block_until Block until datetime
 * @return void
 */
function blockMathUser(string $user_type, int $user_id, string $block_until): void {
    try {
        $conn = getDBConnection();
        
        $table = '';
        $id_field = '';
        switch ($user_type) {
            case 'admin':
                $table = 'admin_users';
                $id_field = 'admin_id';
                break;
            case 'staff':
                $table = 'staff_users';
                $id_field = 'staff_id';
                break;
            case 'student':
                $table = 'student_users';
                $id_field = 'student_id';
                break;
            default:
                return;
        }
        
        $stmt = $conn->prepare("UPDATE $table SET math_blocked_until = ? WHERE $id_field = ?");
        $stmt->bind_param("si", $block_until, $user_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Check if user is blocked from math puzzle
 * 
 * @param string $user_type admin/staff/student
 * @param int $user_id User ID
 * @return bool True if blocked
 */
function isMathBlocked(string $user_type, int $user_id): bool {
    try {
        $conn = getDBConnection();
        
        $table = '';
        $id_field = '';
        switch ($user_type) {
            case 'admin':
                $table = 'admin_users';
                $id_field = 'admin_id';
                break;
            case 'staff':
                $table = 'staff_users';
                $id_field = 'staff_id';
                break;
            case 'student':
                $table = 'student_users';
                $id_field = 'student_id';
                break;
            default:
                return false;
        }
        
        $stmt = $conn->prepare("SELECT math_blocked_until FROM $table WHERE $id_field = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        if ($row && !empty($row['math_blocked_until'])) {
            if (strtotime($row['math_blocked_until']) > time()) {
                return true;
            } else {
                resetMathAttempts($user_type, $user_id);
                return false;
            }
        }
        return false;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Log math attempt
 * 
 * @param string $user_type admin/staff/student
 * @param int $user_id User ID
 * @param string $email User email
 * @param string $question Math question
 * @param int $user_answer User's answer
 * @param int $correct_answer Correct answer
 * @param bool $is_correct Whether answer was correct
 * @return void
 */
function logMathAttempt(string $user_type, int $user_id, string $email, string $question, int $user_answer, int $correct_answer, bool $is_correct): void {
    try {
        $conn = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $conn->prepare("INSERT INTO math_logs (user_type, user_id, email, math_question, user_answer, correct_answer, is_correct, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssiiss", $user_type, $user_id, $email, $question, $user_answer, $correct_answer, $is_correct, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        // Silently fail
    }
}

// ============================================================
// SESSION VALIDATION
// ============================================================

/**
 * Check if admin session is valid
 * 
 * @return bool True if valid
 */
function isSessionValid(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }
    
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 900;
    if (time() - $_SESSION['login_time'] > $timeout) {
        $_SESSION = array();
        session_destroy();
        return false;
    }
    
    return true;
}

/**
 * Check if staff session is valid
 * 
 * @return bool True if valid
 */
function isStaffSessionValid(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    
    if (!isset($_SESSION['staff_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }
    
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 900;
    if (time() - $_SESSION['login_time'] > $timeout) {
        $_SESSION = array();
        session_destroy();
        return false;
    }
    
    return true;
}

/**
 * Check if student session is valid
 * 
 * @return bool True if valid
 */
function isStudentSessionValid(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    
    if (!isset($_SESSION['student_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }
    
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 900;
    if (time() - $_SESSION['login_time'] > $timeout) {
        $_SESSION = array();
        session_destroy();
        return false;
    }
    
    return true;
}

// ============================================================
// AUDIT LOGGING
// ============================================================

/**
 * Log admin audit
 * 
 * @param int $admin_id Admin user ID
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool True on success
 */
function logAudit(int $admin_id, string $action, string $details = ''): bool {
    try {
        $conn = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $admin_id, $action, $details, $ip, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Log staff audit
 * 
 * @param int $staff_id Staff ID
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool True on success
 */
function logStaffAudit(int $staff_id, string $action, string $details = ''): bool {
    try {
        $conn = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $conn->prepare("INSERT INTO staff_audit_logs (staff_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $staff_id, $action, $details, $ip, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Log student audit
 * 
 * @param int $student_id Student ID
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool True on success
 */
function logStudentAudit(int $student_id, string $action, string $details = ''): bool {
    try {
        $conn = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $conn->prepare("INSERT INTO student_audit_logs (student_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $student_id, $action, $details, $ip, $user_agent);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// AUTO-EXPIRATION FUNCTIONS
// ============================================================

/**
 * Check and deactivate expired RFID cards
 * This should be called during login or on page load
 * 
 * @param string $card_type Optional - filter by card type ('visitor', 'resident', 'staff')
 * @return int Number of cards deactivated
 */
function checkExpiredCards(string $card_type = 'visitor'): int {
    try {
        $conn = getDBConnection();
        
        // Build query based on card type
        $type_filter = '';
        if ($card_type === 'visitor') {
            $type_filter = "AND card_type = 'visitor'";
        } elseif ($card_type === 'resident') {
            $type_filter = "AND card_type IN ('resident', 'staff')";
        } else {
            $type_filter = "AND card_type = '$card_type'";
        }
        
        // Deactivate expired cards
        $stmt = $conn->prepare("
            UPDATE rfid_cards 
            SET status = 'expired' 
            WHERE status = 'active'
            AND expiry_date IS NOT NULL
            AND expiry_date < CURDATE()
            $type_filter
        ");
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected > 0) {
            // Log the action
            logSystemEvent('Auto Deactivate', "Deactivated $affected expired $card_type card(s)");
        }
        
        $conn->close();
        return $affected;
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Check and deactivate expired visitor cards only
 * 
 * @return int Number of cards deactivated
 */
function checkExpiredVisitorCards(): int {
    return checkExpiredCards('visitor');
}

/**
 * Check all expired cards (all types)
 * 
 * @return int Total number of cards deactivated
 */
function checkAllExpiredCards(): int {
    $total = 0;
    $total += checkExpiredCards('visitor');
    $total += checkExpiredCards('resident');
    return $total;
}

/**
 * Log system event
 * 
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool True on success
 */
function logSystemEvent(string $action, string $details = ''): bool {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO system_logs (action, details) VALUES (?, ?)");
        $stmt->bind_param("ss", $action, $details);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get system logs
 * 
 * @param int $limit Number of logs to retrieve
 * @return array List of system logs
 */
function getSystemLogs(int $limit = 50): array {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
        $conn->close();
        return $logs;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get expired cards count
 * 
 * @param string $card_type Optional - filter by card type
 * @return int Number of expired cards
 */
function getExpiredCardsCount(string $card_type = ''): int {
    try {
        $conn = getDBConnection();
        $type_filter = '';
        if (!empty($card_type)) {
            $type_filter = "AND card_type = '$card_type'";
        }
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM rfid_cards 
            WHERE status = 'expired'
            AND expiry_date IS NOT NULL
            $type_filter
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)($row['count'] ?? 0);
        $stmt->close();
        $conn->close();
        return $count;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get cards expiring soon (within next 7 days)
 * 
 * @param int $days Number of days to check
 * @param string $card_type Optional - filter by card type
 * @return array List of cards expiring soon
 */
function getCardsExpiringSoon(int $days = 7, string $card_type = ''): array {
    try {
        $conn = getDBConnection();
        $type_filter = '';
        if (!empty($card_type)) {
            $type_filter = "AND card_type = '$card_type'";
        }
        
        $stmt = $conn->prepare("
            SELECT 
                card_uid,
                card_type,
                status,
                expiry_date,
                visitor_name,
                user_id
            FROM rfid_cards 
            WHERE status = 'active'
            AND expiry_date IS NOT NULL
            AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            $type_filter
            ORDER BY expiry_date ASC
        ");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        $cards = [];
        while ($row = $result->fetch_assoc()) {
            $cards[] = $row;
        }
        $stmt->close();
        $conn->close();
        return $cards;
    } catch (Exception $e) {
        return [];
    }
}

// ============================================================
// USER SETTINGS
// ============================================================

/**
 * Get user setting from database
 * 
 * @param int $admin_id Admin user ID
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value or default
 */
function getUserSetting(int $admin_id, string $key, $default = null) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE admin_id = ? AND setting_key = ?");
        $stmt->bind_param("is", $admin_id, $key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            $conn->close();
            return $row['setting_value'];
        }
        $stmt->close();
        $conn->close();
        return $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Set user setting in database
 * 
 * @param int $admin_id Admin user ID
 * @param string $key Setting key
 * @param string $value Setting value
 * @return bool True on success
 */
function setUserSetting(int $admin_id, string $key, string $value): bool {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO user_settings (admin_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("isss", $admin_id, $key, $value, $value);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// DASHBOARD STATISTICS
// ============================================================

/**
 * Get dashboard statistics
 * 
 * @return array<string, int> Dashboard statistics
 */
function getDashboardStats(): array {
    $stats = [
        'total_residents' => 0,
        'active_cards' => 0,
        'today_access' => 0,
        'pending_alerts' => 0,
        'current_occupancy' => 0,
        'total_visitors' => 0,
        'total_announcements' => 0,
        'unauthorized_today' => 0,
        'critical_alerts' => 0,
        'expired_cards' => 0,
        'total_rooms' => 5,
        'max_per_room' => 7
    ];
    
    try {
        $conn = getDBConnection();
        
        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_residents'] = (int)$row['count'];
        }
        
        $result = $conn->query("SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'active'");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['active_cards'] = (int)$row['count'];
        }
        
        $result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE DATE(timestamp) = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['today_access'] = (int)$row['count'];
        }
        
        $result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['pending_alerts'] = (int)$row['count'];
        }
        
        $result = $conn->query("
            SELECT COUNT(*) as count 
            FROM alert_logs 
            WHERE delivery_status = 'pending' 
            AND alert_type = 'unauthorized'
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['critical_alerts'] = (int)$row['count'];
        }
        
        $result = $conn->query("
            SELECT COUNT(*) as count 
            FROM access_logs 
            WHERE DATE(timestamp) = CURDATE() 
            AND access_status = 'denied'
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['unauthorized_today'] = (int)$row['count'];
        }
        
        $result = $conn->query("
            SELECT COUNT(DISTINCT user_id) as count 
            FROM access_logs 
            WHERE user_id IS NOT NULL 
            AND access_type = 'entry' 
            AND timestamp = (
                SELECT MAX(timestamp) 
                FROM access_logs al2 
                WHERE al2.user_id = access_logs.user_id
            )
        ");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['current_occupancy'] = (int)$row['count'];
        }
        
        $result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(entry_timestamp) = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_visitors'] = (int)$row['count'];
        }
        
        $result = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_announcements'] = (int)$row['count'];
        }
        
        $result = $conn->query("SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'expired'");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['expired_cards'] = (int)$row['count'];
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        // Return default stats
    }
    
    return $stats;
}

// ============================================================
// PASSWORD FUNCTIONS
// ============================================================

/**
 * Hash password
 * 
 * @param string $password Password to hash
 * @return string|false Hashed password
 */
function hashPassword(string $password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 * 
 * @param string $password Plain password
 * @param string $hash Hashed password
 * @return bool True if valid
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

// ============================================================
// CSRF FUNCTIONS
// ============================================================

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generateCSRFToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verifyCSRFToken(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// ============================================================
// DATABASE CONNECTION - USING config.php
// ============================================================
// NOTE: getDBConnection() is already defined in config.php
// This alias is for backward compatibility

/**
 * Alias for getDBConnection() - uses the one from config.php
 * 
 * @return mysqli Database connection
 */
function getConnection(): mysqli {
    return getDBConnection();
}

/**
 * Close database connection
 * 
 * @param mysqli|null $conn Database connection
 * @return void
 */
function closeDBConnection(?mysqli $conn): void {
    if ($conn) {
        $conn->close();
    }
}

// ============================================================
// SANITIZATION
// ============================================================

/**
 * Sanitize input
 * 
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitizeInput(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 * 
 * @param string $email Email to validate
 * @return bool True if valid
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Philippine format)
 * 
 * @param string $phone Phone number
 * @return bool True if valid
 */
function isValidPhone(string $phone): bool {
    return preg_match('/^(09|\+639)\d{9}$/', $phone) === 1;
}

// ============================================================
// USER FUNCTIONS
// ============================================================

/**
 * Get user by ID
 * 
 * @param int $user_id User ID
 * @return array|null User data or null
 */
function getUserById(int $user_id): ?array {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $user;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get all users
 * 
 * @return array List of users
 */
function getAllUsers(): array {
    try {
        $conn = getDBConnection();
        $result = $conn->query("SELECT * FROM users WHERE status != 'deleted' ORDER BY full_name");
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $conn->close();
        return $users;
    } catch (Exception $e) {
        return [];
    }
}
?>