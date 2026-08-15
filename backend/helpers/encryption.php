<?php
/**
 * Encryption Helper - AES-256-CBC
 * For data encryption at rest
 */

class EncryptionHelper {
    private static $encryptionKey = null;
    private static $cipherMethod = 'AES-256-CBC';
    
    /**
     * Get or generate encryption key
     */
    private static function getEncryptionKey() {
        if (self::$encryptionKey === null) {
            // Try to get from environment or config
            $configKey = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : null;
            
            if ($configKey) {
                self::$encryptionKey = $configKey;
            } else {
                // Generate from server fingerprint
                $serverInfo = $_SERVER['SERVER_NAME'] . $_SERVER['SERVER_ADDR'];
                $serverInfo .= php_uname('n') . php_uname('s');
                self::$encryptionKey = hash('sha256', $serverInfo . 'TAP_AND_GO_SECRET_SALT_2026');
            }
        }
        return self::$encryptionKey;
    }
    
    /**
     * Encrypt data
     */
    public static function encrypt($data) {
        if (empty($data)) return '';
        
        try {
            $key = self::getEncryptionKey();
            $ivLength = openssl_cipher_iv_length(self::$cipherMethod);
            $iv = openssl_random_pseudo_bytes($ivLength);
            
            $encrypted = openssl_encrypt(
                $data,
                self::$cipherMethod,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            // Store IV with encrypted data (base64 encoded)
            return base64_encode($iv . $encrypted);
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return $data; // Fallback to plaintext
        }
    }
    
    /**
     * Decrypt data
     */
    public static function decrypt($encryptedData) {
        if (empty($encryptedData)) return '';
        
        try {
            $key = self::getEncryptionKey();
            $data = base64_decode($encryptedData);
            
            $ivLength = openssl_cipher_iv_length(self::$cipherMethod);
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            $decrypted = openssl_decrypt(
                $encrypted,
                self::$cipherMethod,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            return $decrypted !== false ? $decrypted : $encryptedData;
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return $encryptedData; // Fallback to raw data
        }
    }
    
    /**
     * Encrypt array of data
     */
    public static function encryptArray($dataArray) {
        $encrypted = [];
        foreach ($dataArray as $key => $value) {
            if (is_string($value)) {
                $encrypted[$key] = self::encrypt($value);
            } else {
                $encrypted[$key] = $value;
            }
        }
        return $encrypted;
    }
    
    /**
     * Decrypt array of data
     */
    public static function decryptArray($dataArray) {
        $decrypted = [];
        foreach ($dataArray as $key => $value) {
            if (is_string($value) && !empty($value)) {
                $decrypted[$key] = self::decrypt($value);
            } else {
                $decrypted[$key] = $value;
            }
        }
        return $decrypted;
    }
}

// ============================================================
// SPECIFIC VISITOR ENCRYPTION FUNCTIONS
// ============================================================

function encryptVisitorData($data) {
    return EncryptionHelper::encrypt($data);
}

function decryptVisitorData($data) {
    return EncryptionHelper::decrypt($data);
}

function encryptVisitorArray($data) {
    return EncryptionHelper::encryptArray($data);
}

function decryptVisitorArray($data) {
    return EncryptionHelper::decryptArray($data);
}
