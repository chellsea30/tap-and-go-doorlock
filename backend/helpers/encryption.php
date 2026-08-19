<?php
/**
 * Encryption Helper - AES-256-CBC
 * For data encryption at rest
 * 
 * @package TapAndGo
 * @version 2.0.0
 */

class EncryptionHelper {
    /** @var string|null Encryption key */
    private static ?string $encryptionKey = null;
    
    /** @var string Cipher method */
    private static string $cipherMethod = 'AES-256-CBC';
    
    /** @var bool Initialization flag */
    private static bool $initialized = false;
    
    /**
     * Initialize encryption helper
     */
    private static function initialize(): void {
        if (self::$initialized) {
            return;
        }
        
        // Get encryption key from config or generate
        if (defined('ENCRYPTION_KEY')) {
            self::$encryptionKey = ENCRYPTION_KEY;
        } else {
            // Generate from server fingerprint
            $serverInfo = $_SERVER['SERVER_NAME'] ?? '';
            $serverInfo .= $_SERVER['SERVER_ADDR'] ?? '';
            $serverInfo .= php_uname('n') . php_uname('s');
            self::$encryptionKey = hash('sha256', $serverInfo . 'TAP_AND_GO_SECRET_SALT_2026');
        }
        
        if (defined('ENCRYPTION_METHOD')) {
            self::$cipherMethod = ENCRYPTION_METHOD;
        }
        
        self::$initialized = true;
    }
    
    /**
     * Get encryption key
     */
    private static function getEncryptionKey(): string {
        self::initialize();
        return self::$encryptionKey ?? 'default_fallback_key_32bytes_long!!';
    }
    
    /**
     * Get cipher method
     */
    private static function getCipherMethod(): string {
        self::initialize();
        return self::$cipherMethod;
    }
    
    /**
     * Encrypt data using AES-256-CBC
     * 
     * @param string $data Data to encrypt
     * @return string Base64 encoded encrypted data
     */
    public static function encrypt(string $data): string {
        if (empty($data)) return '';
        
        try {
            $key = self::getEncryptionKey();
            $method = self::getCipherMethod();
            
            $ivLength = openssl_cipher_iv_length($method);
            $iv = openssl_random_pseudo_bytes($ivLength);
            
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
            
            return base64_encode($iv . $encrypted);
            
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return $data;
        }
    }
    
    /**
     * Decrypt data using AES-256-CBC
     * 
     * @param string $encryptedData Base64 encoded encrypted data
     * @return string Decrypted data
     */
    public static function decrypt(string $encryptedData): string {
        if (empty($encryptedData)) return '';
        
        try {
            $key = self::getEncryptionKey();
            $method = self::getCipherMethod();
            
            $decoded = base64_decode($encryptedData);
            if ($decoded === false) {
                return $encryptedData;
            }
            
            $ivLength = openssl_cipher_iv_length($method);
            $iv = substr($decoded, 0, $ivLength);
            $encrypted = substr($decoded, $ivLength);
            
            $decrypted = openssl_decrypt(
                $encrypted,
                $method,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted === false) {
                return $encryptedData;
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return $encryptedData;
        }
    }
    
    /**
     * Check if data is encrypted
     * 
     * @param string $data Data to check
     * @return bool True if data appears encrypted
     */
    public static function isEncrypted(string $data): bool {
        if (empty($data)) return false;
        if (strlen($data) < 24) return false;
        
        $decoded = base64_decode($data, true);
        if ($decoded === false) return false;
        
        $ivLength = openssl_cipher_iv_length(self::getCipherMethod());
        if (strlen($decoded) < $ivLength + 16) return false;
        
        return true;
    }
    
    /**
     * Encrypt visitor data array
     * 
     * @param array $data Visitor data
     * @return array Encrypted visitor data
     */
    public static function encryptVisitorArray(array $data): array {
        $fields = [
            'visitor_name', 'visitor_phone', 'phone', 'relationship',
            'purpose_of_visit', 'purpose', 'resident_visited_name',
            'resident_name', 'contact_number', 'email'
        ];
        
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($value) && in_array($key, $fields) && !empty($value)) {
                $result[$key] = self::encrypt($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    
    /**
     * Decrypt visitor data array
     * 
     * @param array $data Encrypted visitor data
     * @return array Decrypted visitor data
     */
    public static function decryptVisitorArray(array $data): array {
        $fields = [
            'visitor_name', 'visitor_phone', 'phone', 'relationship',
            'purpose_of_visit', 'purpose', 'resident_visited_name',
            'resident_name', 'contact_number', 'email'
        ];
        
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($value) && in_array($key, $fields) && !empty($value)) {
                $result[$key] = self::decrypt($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}

/**
 * Global encryption functions for easy use
 */
function encryptData(string $data): string {
    return EncryptionHelper::encrypt($data);
}

function decryptData(string $data): string {
    return EncryptionHelper::decrypt($data);
}

function encryptVisitorData(array $data): array {
    return EncryptionHelper::encryptVisitorArray($data);
}

function decryptVisitorData(array $data): array {
    return EncryptionHelper::decryptVisitorArray($data);
}

function isDataEncrypted(string $data): bool {
    return EncryptionHelper::isEncrypted($data);
}
