<?php
echo "<h1>PHPMailer Test</h1>";

// Check if PHPMailer exists
$paths = [
    __DIR__ . '/backend/PHPMailer-master/src/PHPMailer.php',
    __DIR__ . '/PHPMailer-master/src/PHPMailer.php',
    __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        echo "✅ Found: " . $path . "<br>";
    } else {
        echo "❌ Not found: " . $path . "<br>";
    }
}

echo "<br>";

// Where to download PHPMailer
echo "<h3>How to fix:</h3>";
echo "1. Go to: <a href='https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip' target='_blank'>Download PHPMailer</a><br>";
echo "2. Extract the ZIP file<br>";
echo "3. Copy the 'src' folder to: <strong>backend/PHPMailer-master/</strong><br>";
echo "4. So the path should be: <strong>backend/PHPMailer-master/src/PHPMailer.php</strong><br>";
echo "5. Refresh this page<br>";