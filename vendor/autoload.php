<?php
// vendor/autoload.php - Manual Autoloader

// PHPMailer classes
require_once __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/phpmailer/src/Exception.php';

// Other vendors if needed
// require_once __DIR__ . '/doctrine/...';
// require_once __DIR__ . '/nikic/...';

// Register the autoloader
spl_autoload_register(function ($class) {
    // PHPMailer namespace
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

echo "Autoloader loaded successfully!\n";