<?php
/**
 * Tap-and-Go Doorlock System - Configuration
 * COMPLETE WITH ENCRYPTION
 * 
 * @package TapAndGo
 * @author ISU-Echague Dormitory
 * @version 1.0.0
 */

// ============================================
// DATABASE CONFIGURATION
// ============================================

// Database credentials - UPDATE THESE FOR YOUR ENVIRONMENT
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tap_and_go_db');

// Database connection settings
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// ============================================
// APPLICATION CONFIGURATION
// ============================================

define('SITE_NAME', 'Tap-and-Go Doorlock System');
define('SITE_URL', 'http://localhost/tap-and-go-doorlock/');
define('APP_ENV', 'development'); // development, staging, production

// Application paths
define('BASE_PATH', dirname(dirname(__DIR__)));
define('BACKEND_PATH', BASE_PATH . '/backend');
define('FRONTEND_PATH', BASE_PATH . '/frontend');
define('LOG_PATH', BACKEND_PATH . '/logs');

// ============================================
// SECURITY CONFIGURATION
// ============================================

define('SESSION_TIMEOUT', 900); // 15 minutes in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('BAN_DURATION', 600); // 10 minutes in seconds
define('PASSWORD_MIN_LENGTH', 8);
define('BCRYPT_COST', 12);

// ============================================
// ENCRYPTION CONFIGURATION
// ============================================

// Encryption key for email encryption (CHANGE IN PRODUCTION!)
define('ENCRYPTION_KEY', 'tapandgo_secret_key_2024_secure_encryption_32bytes!!');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// ============================================
// API CONFIGURATION
// ============================================

define('API_VERSION', 'v1');
define('API_KEY_HEADER', 'X-API-Key');
define('API_RATE_LIMIT', 100); // Max requests per minute

// ============================================
// SESSION CONFIGURATION FUNCTION
// ============================================

/**
 * Configure session settings BEFORE session starts
 * Call this function BEFORE session_start()
 * 
 * @return void
 */
function configureSession(): void {
    // Only set session settings if session is not already active
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Secure cookie in production
        if (defined('APP_ENV') && APP_ENV === 'production') {
            ini_set('session.cookie_secure', 1);
        }
        
        // Set session timeout
        if (defined('SESSION_TIMEOUT')) {
            ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
            ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
        }
        
        // Set session name
        session_name('TAPANDGO_SESSION');
    }
}

/**
 * Start secure session with proper configuration
 * 
 * @return void
 */
function startSecureSession(): void {
    // Configure session settings first
    configureSession();
    
    // Start session if not already active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Regenerate session ID for security
 * 
 * @return void
 */
function regenerateSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['login_time'] = time();
    }
}

// ============================================
// ERROR HANDLING
// ============================================

// Error reporting based on environment
if (defined('APP_ENV') && APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    if (defined('LOG_PATH')) {
        ini_set('error_log', LOG_PATH . '/error.log');
    }
}

// ============================================
// DATABASE CONNECTION FUNCTION - FIXED
// ============================================

/**
 * Get database connection
 * 
 * @return mysqli Database connection object
 * @throws Exception If connection fails
 */
function getDBConnection(): mysqli {
    static $connection = null;
    static $connection_attempted = false;
    
    // Return existing connection if available and valid
    if ($connection !== null && !$connection_attempted) {
        try {
            // Check if connection is still valid
            if (@$connection->ping()) {
                return $connection;
            }
            // If ping fails, close the broken connection
            @$connection->close();
            $connection = null;
            $connection_attempted = false;
        } catch (Exception $e) {
            // Connection is broken, create new one
            $connection = null;
            $connection_attempted = false;
        } catch (Error $e) {
            // Connection is broken, create new one
            $connection = null;
            $connection_attempted = false;
        }
    }
    
    // Create new connection
    try {
        // Check if constants are defined
        if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_NAME')) {
            throw new Exception('Database configuration constants are not defined.');
        }
        
        // Create new connection
        $connection = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            defined('DB_PORT') ? DB_PORT : 3306
        );
        
        // Check connection
        if ($connection->connect_error) {
            throw new Exception("Connection failed: " . $connection->connect_error);
        }
        
        // Set charset
        if (defined('DB_CHARSET')) {
            $connection->set_charset(DB_CHARSET);
        } else {
            $connection->set_charset('utf8mb4');
        }
        
        // Set timezone
        $connection->query("SET time_zone = '+08:00'");
        
        // Set SQL mode
        $connection->query("SET sql_mode = 'STRICT_ALL_TABLES'");
        
        $connection_attempted = true;
        return $connection;
        
    } catch (Exception $e) {
        // Log error if possible
        if (defined('LOG_PATH') && is_dir(LOG_PATH)) {
            error_log("Database connection error: " . $e->getMessage());
        }
        
        // Display user-friendly error based on environment
        if (defined('APP_ENV') && APP_ENV === 'development') {
            die("Database Connection Error: " . $e->getMessage());
        } else {
            die("Database connection error. Please try again later or contact administrator.");
        }
    }
}

/**
 * Test database connection
 * 
 * @return bool True if connection is successful
 */
function testDBConnection(): bool {
    try {
        $conn = getDBConnection();
        return @$conn->ping();
    } catch (Exception $e) {
        return false;
    } catch (Error $e) {
        return false;
    }
}

/**
 * Get database connection with error handling
 * 
 * @return mysqli|null Database connection or null on failure
 */
function getDBConnectionSafe(): ?mysqli {
    try {
        return getDBConnection();
    } catch (Exception $e) {
        return null;
    } catch (Error $e) {
        return null;
    }
}

/**
 * Close database connection safely
 * 
 * @param mysqli|null $conn Database connection
 * @return void
 */
function closeDBConnectionSafe(?mysqli $conn): void {
    if ($conn !== null) {
        try {
            @$conn->close();
        } catch (Exception $e) {
            // Silently fail
        } catch (Error $e) {
            // Silently fail
        }
    }
}

// ============================================
// INITIALIZATION
// ============================================

// Create logs directory if it doesn't exist
if (defined('LOG_PATH') && !is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// Create error log file if it doesn't exist
if (defined('LOG_PATH')) {
    $errorLogFile = LOG_PATH . '/error.log';
    if (!file_exists($errorLogFile)) {
        touch($errorLogFile);
        chmod($errorLogFile, 0644);
    }
}

// ============================================
// CONFIGURATION FUNCTIONS
// ============================================

/**
 * Get configuration value from database
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value or default
 */
function getConfig(string $key, $default = null) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT config_value FROM system_configuration WHERE config_key = ?");
        if (!$stmt) {
            return $default;
        }
        
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['config_value'];
        }
        $stmt->close();
        return $default;
        
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Update configuration value in database
 * 
 * @param string $key Configuration key
 * @param string $value New value
 * @return bool True on success
 */
function setConfig(string $key, string $value): bool {
    try {
        $conn = getDBConnection();
        
        // Check if key exists
        $stmt = $conn->prepare("SELECT config_id FROM system_configuration WHERE config_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing
            $stmt = $conn->prepare("UPDATE system_configuration SET config_value = ? WHERE config_key = ?");
            $stmt->bind_param("ss", $value, $key);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO system_configuration (config_key, config_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
        }
        
        $success = $stmt->execute();
        $stmt->close();
        return $success;
        
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// EMAIL CONFIGURATION - SMTP (GMAIL)
// ============================================
// ⚠️ REPLACE WITH YOUR ACTUAL EMAIL AND APP PASSWORD
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'albanochellsea30@gmail.com');     // ← YOUR GMAIL
define('SMTP_PASSWORD', 'djuz tjyz uhvr lfel'); // ← YOUR APP PASSWORD
define('SMTP_FROM_EMAIL', 'albanochellsea30@gmail.com');
define('SMTP_FROM_NAME', 'Tap-and-Go Doorlock');

// ============================================
// OTP CONFIGURATION
// ============================================
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_MAX_ATTEMPTS', 3);