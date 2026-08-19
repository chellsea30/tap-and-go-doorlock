<?php
/**
 * Tap-and-Go Doorlock System - Complete Configuration
 * WITH ENCRYPTION & VISITOR MANAGEMENT
 * 
 * @package TapAndGo
 * @author ISU-Echague Dormitory
 * @version 2.0.0
 */

// ============================================
// DATABASE CONFIGURATION - UPDATED FOR RAILWAY
// ============================================

// Database credentials - NOW USING RAILWAY ENVIRONMENT VARIABLES
// These will be automatically set by Railway
define('DB_HOST', getenv('DB_HOST') ?: 'mysql.railway.internal');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: 'uQcMTaYnsOvpMlVctbUHUtgBkAaryBWa');
define('DB_NAME', getenv('DB_DATABASE') ?: 'railway');

// Database connection settings
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// ============================================
// APPLICATION CONFIGURATION
// ============================================

define('SITE_NAME', 'Tap-and-Go Doorlock System');
// CHANGE THIS TO YOUR RAILWAY URL AFTER DEPLOYMENT
define('SITE_URL', getenv('RAILWAY_PUBLIC_DOMAIN') ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN') . '/' : 'http://10.55.160.156/tap-and-go-doorlock/');
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // Changed to production for Railway

// Application paths
define('BASE_PATH', dirname(dirname(__DIR__)));
define('BACKEND_PATH', BASE_PATH . '/backend');
define('FRONTEND_PATH', BASE_PATH . '/frontend');
define('LOG_PATH', BACKEND_PATH . '/logs');
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// ============================================
// SECURITY CONFIGURATION
// ============================================

define('SESSION_TIMEOUT', 900); // 15 minutes in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('BAN_DURATION', 600); // 10 minutes in seconds
define('PASSWORD_MIN_LENGTH', 8);
define('BCRYPT_COST', 12);

// CSRF Protection
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// ============================================
// ENCRYPTION CONFIGURATION - AES-256-CBC
// ============================================

/**
 * ENCRYPTION KEY - IMPORTANT!
 * 
 * ⚠️ FOR PRODUCTION: Generate a unique key and store it securely
 * Use this command to generate a secure key:
 *   openssl rand -hex 32
 * 
 * DO NOT use the default key in production!
 */
// Use Railway environment variable for encryption key, or fallback to default
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'tapandgo_secret_key_2024_secure_encryption_32bytes!!');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// Encryption for visitor data
define('ENCRYPT_VISITOR_DATA', true);
define('ENCRYPT_STAFF_DATA', true);
define('ENCRYPT_RESIDENT_DATA', true);

// ============================================
// VISITOR MANAGEMENT CONFIGURATION
// ============================================

// Default visitor validity period (days)
define('DEFAULT_VISITOR_DURATION', 7);
define('MAX_VISITOR_DURATION', 30);

// Auto-expiration settings
define('AUTO_EXPIRE_VISITORS', true);
define('EXPIRE_CHECK_INTERVAL_HOURS', 24);

// Visitor card settings
define('MAX_VISITOR_CARDS', 500);
define('VISITOR_CARD_EXPIRY_DAYS', 365);

// ============================================
// API CONFIGURATION
// ============================================

define('API_VERSION', 'v1');
define('API_KEY_HEADER', 'X-API-Key');
define('API_RATE_LIMIT', 100); // Max requests per minute

// API endpoints
define('API_BASE_URL', SITE_URL . 'backend/api/' . API_VERSION . '/');
define('API_RFID_ACCESS', API_BASE_URL . 'rfid_access.php');
define('API_GET_CARDS', API_BASE_URL . 'get_cards.php');
define('API_LOG_ACCESS', API_BASE_URL . 'log_access.php');
define('API_SEND_ALERT', API_BASE_URL . 'send_alert.php');

// ============================================
// SESSION CONFIGURATION
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
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    if (defined('LOG_PATH')) {
        ini_set('error_log', LOG_PATH . '/error.log');
    }
}

// ============================================
// DATABASE CONNECTION FUNCTION
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
        } catch (Exception | Error $e) {
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
    } catch (Exception | Error $e) {
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
    } catch (Exception | Error $e) {
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
        } catch (Exception | Error $e) {
            // Silently fail
        }
    }
}

// ============================================
// ENCRYPTION FUNCTIONS
// ============================================

/**
 * Encrypt data using AES-256-CBC
 * 
 * @param string $data Data to encrypt
 * @param string|null $key Optional custom key
 * @return string Base64 encoded encrypted data
 */
function encryptData(string $data, ?string $key = null): string {
    if (empty($data)) return '';
    
    try {
        $key = $key ?? ENCRYPTION_KEY;
        $method = ENCRYPTION_METHOD;
        
        // Generate random IV
        $ivLength = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        // Encrypt data
        $encrypted = openssl_encrypt(
            $data,
            $method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
        
    } catch (Exception $e) {
        error_log("Encryption error: " . $e->getMessage());
        return $data; // Fallback to plaintext
    }
}

/**
 * Decrypt data using AES-256-CBC
 * 
 * @param string $data Base64 encoded encrypted data
 * @param string|null $key Optional custom key
 * @return string Decrypted data
 */
function decryptData(string $data, ?string $key = null): string {
    if (empty($data)) return '';
    
    try {
        $key = $key ?? ENCRYPTION_KEY;
        $method = ENCRYPTION_METHOD;
        
        // Decode base64
        $decoded = base64_decode($data);
        if ($decoded === false) {
            return $data;
        }
        
        // Extract IV and encrypted data
        $ivLength = openssl_cipher_iv_length($method);
        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);
        
        // Decrypt
        $decrypted = openssl_decrypt(
            $encrypted,
            $method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            return $data;
        }
        
        return $decrypted;
        
    } catch (Exception $e) {
        error_log("Decryption error: " . $e->getMessage());
        return $data; // Fallback to raw data
    }
}

/**
 * Check if data is encrypted
 * 
 * @param string $data Data to check
 * @return bool True if data appears encrypted
 */
function isEncrypted(string $data): bool {
    // Check if data is base64 encoded and likely encrypted
    if (empty($data)) return false;
    if (strlen($data) < 24) return false;
    
    // Try to decode base64
    $decoded = base64_decode($data, true);
    if ($decoded === false) return false;
    
    // Check if it looks like encrypted data (has IV + encrypted content)
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    if (strlen($decoded) < $ivLength + 16) return false;
    
    return true;
}

/**
 * Encrypt array values recursively
 * 
 * @param array $data Array to encrypt
 * @param array $fields Fields to encrypt
 * @return array Encrypted array
 */
function encryptArray(array $data, array $fields = []): array {
    $result = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $result[$key] = encryptArray($value, $fields);
        } elseif (is_string($value) && (empty($fields) || in_array($key, $fields))) {
            $result[$key] = encryptData($value);
        } else {
            $result[$key] = $value;
        }
    }
    return $result;
}

/**
 * Decrypt array values recursively
 * 
 * @param array $data Array to decrypt
 * @param array $fields Fields to decrypt
 * @return array Decrypted array
 */
function decryptArray(array $data, array $fields = []): array {
    $result = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $result[$key] = decryptArray($value, $fields);
        } elseif (is_string($value) && (empty($fields) || in_array($key, $fields))) {
            $result[$key] = decryptData($value);
        } else {
            $result[$key] = $value;
        }
    }
    return $result;
}

// ============================================
// VISITOR ENCRYPTION HELPERS
// ============================================

/**
 * Encrypt visitor data
 * 
 * @param array $visitorData Visitor data array
 * @return array Encrypted visitor data
 */
function encryptVisitor(array $visitorData): array {
    $encryptFields = [
        'visitor_name',
        'visitor_phone',
        'phone',
        'relationship',
        'purpose_of_visit',
        'purpose',
        'resident_visited_name',
        'resident_name',
        'resident_visited'
    ];
    
    return encryptArray($visitorData, $encryptFields);
}

/**
 * Decrypt visitor data
 * 
 * @param array $visitorData Visitor data array
 * @return array Decrypted visitor data
 */
function decryptVisitor(array $visitorData): array {
    $decryptFields = [
        'visitor_name',
        'visitor_phone',
        'phone',
        'relationship',
        'purpose_of_visit',
        'purpose',
        'resident_visited_name',
        'resident_name',
        'resident_visited'
    ];
    
    return decryptArray($visitorData, $decryptFields);
}

// ============================================
// INITIALIZATION
// ============================================

// Create required directories
$requiredDirs = [LOG_PATH, UPLOAD_PATH, UPLOAD_PATH . '/avatars', UPLOAD_PATH . '/visitor_photos'];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
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
            $stmt = $conn->prepare("UPDATE system_configuration SET config_value = ?, updated_at = NOW() WHERE config_key = ?");
            $stmt->bind_param("ss", $value, $key);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO system_configuration (config_key, config_value, updated_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ss", $key, $value);
        }
        
        $success = $stmt->execute();
        $stmt->close();
        return $success;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get visitor encryption status
 * 
 * @return bool True if visitor encryption is enabled
 */
function isVisitorEncryptionEnabled(): bool {
    return defined('ENCRYPT_VISITOR_DATA') && ENCRYPT_VISITOR_DATA === true;
}

// ============================================
// EMAIL CONFIGURATION - SMTP (GMAIL)
// ============================================

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'albanochellsea30@gmail.com');
define('SMTP_PASSWORD', 'djuz tjyz uhvr lfel');
define('SMTP_FROM_EMAIL', 'albanochellsea30@gmail.com');
define('SMTP_FROM_NAME', 'Tap-and-Go Doorlock System');

// Email settings
define('EMAIL_ENCRYPTION', 'tls');
define('EMAIL_DEBUG', false);

// ============================================
// OTP CONFIGURATION
// ============================================

define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_MAX_ATTEMPTS', 3);

// ============================================
// LOGGING CONFIGURATION
// ============================================

define('LOG_LEVEL', 'info');
define('LOG_ACCESS_ATTEMPTS', true);
define('LOG_SYSTEM_EVENTS', true);
define('LOG_ENCRYPTION_EVENTS', true);

// ============================================
// TIMEZONE
// ============================================

date_default_timezone_set('Asia/Manila');

// ============================================
// SESSION START
// ============================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    startSecureSession();
}

// ============================================
// SECURITY HEADERS
// ============================================

// Set security headers if not already sent
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (defined('APP_ENV') && APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ============================================
// ERROR HANDLING - CUSTOM
// ============================================

/**
 * Custom error handler
 * 
 * @param int $errno Error level
 * @param string $errstr Error message
 * @param string $errfile File where error occurred
 * @param int $errline Line number
 * @return bool
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $logMessage = "[$errno] $errstr in $errfile on line $errline";
    
    if (defined('LOG_PATH') && is_dir(LOG_PATH)) {
        error_log($logMessage . PHP_EOL, 3, LOG_PATH . '/error.log');
    }
    
    // Don't display errors in production
    if (defined('APP_ENV') && APP_ENV === 'production') {
        return true;
    }
    
    // Display in development
    echo "<pre>Error: $errstr in $errfile on line $errline</pre>";
    return true;
}

/**
 * Custom exception handler
 * 
 * @param Throwable $exception
 * @return void
 */
function customExceptionHandler($exception) {
    $logMessage = "Uncaught Exception: " . $exception->getMessage() . 
                  " in " . $exception->getFile() . " on line " . $exception->getLine();
    
    if (defined('LOG_PATH') && is_dir(LOG_PATH)) {
        error_log($logMessage . PHP_EOL, 3, LOG_PATH . '/error.log');
        error_log($exception->getTraceAsString() . PHP_EOL, 3, LOG_PATH . '/error.log');
    }
    
    if (defined('APP_ENV') && APP_ENV === 'production') {
        echo "An error occurred. Please try again later.";
    } else {
        echo "<pre>Uncaught Exception: " . $exception->getMessage() . 
             "\nFile: " . $exception->getFile() . 
             "\nLine: " . $exception->getLine() . 
             "\n\nTrace:\n" . $exception->getTraceAsString() . "</pre>";
    }
    exit(1);
}

// Set custom error and exception handlers
if (defined('APP_ENV') && APP_ENV !== 'development') {
    set_error_handler('customErrorHandler');
    set_exception_handler('customExceptionHandler');
}

// ============================================
// COMPRESSION & OPTIMIZATION
// ============================================

// Enable compression if available
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ini_set('zlib.output_compression', 'On');
}

// ============================================
// GENERATE ENCRYPTION KEY HELPER
// ============================================

/**
 * Generate a secure encryption key
 * 
 * @param int $length Key length in bytes (32 = 256-bit)
 * @return string Hex encoded key
 */
function generateEncryptionKey(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

// ============================================
// OUTPUT ENCRYPTION STATUS
// ============================================

if (defined('APP_ENV') && APP_ENV === 'development') {
    // Check encryption status
    $encryptionStatus = isVisitorEncryptionEnabled() ? '✅ ENABLED' : '❌ DISABLED';
    $encryptionMethod = defined('ENCRYPTION_METHOD') ? ENCRYPTION_METHOD : 'Not Set';
    $encryptionKey = defined('ENCRYPTION_KEY') ? substr(ENCRYPTION_KEY, 0, 8) . '...' : 'Not Set';
    
    // Log encryption status
    error_log("=== Encryption Status ===");
    error_log("Status: $encryptionStatus");
    error_log("Method: $encryptionMethod");
    error_log("Key: $encryptionKey");
    error_log("==========================");
}
