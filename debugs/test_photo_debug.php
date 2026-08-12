<?php
/**
 * COMPLETE PHOTO UPLOAD DEBUGGER
 * I-save sa: tap-and-go-doorlock/test_photo_debug.php
 */

echo "<h1>📸 Photo Upload Debugger</h1>";
echo "<hr>";

// ============================================================
// 1. CHECK PHP CONFIGURATION
// ============================================================
echo "<h2>1. PHP Configuration</h2>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";
echo "Max File Uploads: " . ini_get('max_file_uploads') . "<br>";
echo "Upload Tmp Dir: " . (ini_get('upload_tmp_dir') ?: 'System default') . "<br>";

// ============================================================
// 2. CHECK FOLDER PERMISSIONS
// ============================================================
echo "<h2>2. Folder Permissions</h2>";

$folders = [
    'uploads/' => __DIR__ . '/uploads/',
    'uploads/resident_photos/' => __DIR__ . '/uploads/resident_photos/',
];

foreach ($folders as $name => $path) {
    echo "<strong>$name</strong><br>";
    if (is_dir($path)) {
        echo "✅ Folder exists: $path<br>";
        if (is_writable($path)) {
            echo "✅ Folder is writable<br>";
        } else {
            echo "❌ Folder is NOT writable!<br>";
            echo "Run: chmod 777 " . $path . "<br>";
        }
    } else {
        echo "❌ Folder does NOT exist!<br>";
        echo "Run: mkdir -p " . $path . "<br>";
        echo "Run: chmod 777 " . $path . "<br>";
    }
    echo "<br>";
}

// ============================================================
// 3. CHECK DATABASE
// ============================================================
echo "<h2>3. Database Check</h2>";

// Try to find config
$configPaths = [
    'backend/config/config.php',
    '../backend/config/config.php',
    '../../backend/config/config.php',
];

$configFound = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $configFound = true;
        echo "✅ Config found: $path<br>";
        break;
    }
}

if (!$configFound) {
    echo "❌ Cannot find config.php<br>";
    echo "Current directory: " . __DIR__ . "<br>";
    echo "Files: " . implode(', ', scandir(__DIR__)) . "<br>";
    exit();
}

try {
    $conn = getDBConnection();
    echo "✅ Database connected<br>";
    
    // Check profile_photo column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
    if ($result && $result->num_rows > 0) {
        echo "✅ profile_photo column exists<br>";
    } else {
        echo "❌ profile_photo column does NOT exist!<br>";
        echo "Run: ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER status;<br>";
    }
    
    // Check users with photos
    $result = $conn->query("SELECT user_id, full_name, profile_photo FROM users WHERE profile_photo IS NOT NULL AND profile_photo != ''");
    if ($result && $result->num_rows > 0) {
        echo "<br>📸 Users with photos:<br>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Photo Path</th><th>File Exists?</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $photoPath = $row['profile_photo'];
            $fullPath = __DIR__ . '/' . $photoPath;
            $fileExists = file_exists($fullPath);
            echo "<tr>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['full_name']}</td>";
            echo "<td><code>$photoPath</code></td>";
            echo "<td>" . ($fileExists ? "✅ Yes" : "❌ No") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No users with profile photos found.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// ============================================================
// 4. TEST FILE UPLOAD
// ============================================================
echo "<h2>4. Test File Upload</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_photo'])) {
    $uploadDir = __DIR__ . '/uploads/resident_photos/';
    
    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $file = $_FILES['test_photo'];
    $fileName = time() . '_test_' . basename($file['name']);
    $targetFile = $uploadDir . $fileName;
    
    echo "<strong>Upload Details:</strong><br>";
    echo "File name: " . $file['name'] . "<br>";
    echo "File size: " . $file['size'] . " bytes<br>";
    echo "File type: " . $file['type'] . "<br>";
    echo "Temp file: " . $file['tmp_name'] . "<br>";
    echo "Target: " . $targetFile . "<br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            echo "✅ File uploaded successfully!<br>";
            echo "Path: uploads/resident_photos/" . $fileName . "<br>";
            echo "<img src='/tap-and-go-doorlock/uploads/resident_photos/" . $fileName . "' style='max-width:200px;border:2px solid green;'><br>";
        } else {
            echo "❌ Failed to move uploaded file!<br>";
            echo "Check permissions for: " . $uploadDir . "<br>";
        }
    } else {
        echo "❌ Upload error code: " . $file['error'] . "<br>";
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];
        echo "Error message: " . ($errors[$file['error']] ?? 'Unknown error') . "<br>";
    }
}

// ============================================================
// 5. TEST UPLOAD FORM
// ============================================================
echo "<h2>5. Test Upload Form</h2>";
?>
<form method="POST" enctype="multipart/form-data" style="border:2px solid #ddd;padding:20px;border-radius:10px;max-width:500px;">
    <div class="mb-3">
        <label style="font-weight:bold;">Select a photo to test:</label>
        <input type="file" name="test_photo" accept="image/*" required style="display:block;margin-top:10px;">
    </div>
    <button type="submit" style="background:#1a3a6a;color:white;border:none;padding:10px 30px;border-radius:8px;cursor:pointer;">
        🚀 Test Upload
    </button>
</form>

<?php
// ============================================================
// 6. CHECK EXISTING FILES
// ============================================================
echo "<h2>6. Existing Files in Uploads Folder</h2>";

$uploadDir = __DIR__ . '/uploads/resident_photos/';
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    $images = array_diff($files, ['.', '..']);
    
    if (count($images) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>File</th><th>Size</th><th>Preview</th></tr>";
        foreach ($images as $file) {
            $filePath = $uploadDir . $file;
            $fileSize = round(filesize($filePath) / 1024, 2) . ' KB';
            echo "<tr>";
            echo "<td>$file</td>";
            echo "<td>$fileSize</td>";
            echo "<td><img src='/tap-and-go-doorlock/uploads/resident_photos/$file' style='width:80px;height:80px;object-fit:cover;border-radius:8px;'></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No files in uploads folder.<br>";
    }
}

// ============================================================
// 7. PHP ERROR LOGS
// ============================================================
echo "<h2>7. PHP Error Log</h2>";
$logFile = ini_get('error_log');
if ($logFile && file_exists($logFile)) {
    echo "Error log: $logFile<br>";
    echo "Last 5 lines:<br>";
    echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;max-height:200px;overflow:auto;'>";
    $lines = file($logFile);
    $lastLines = array_slice($lines, -5);
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "No error log found or error_log not set.<br>";
}

// ============================================================
// 8. FIX RECOMMENDATIONS
// ============================================================
echo "<h2>8. Fix Recommendations</h2>";

$issues = [];

// Check uploads folder
$uploadDir = __DIR__ . '/uploads/resident_photos/';
if (!is_dir($uploadDir)) {
    $issues[] = "Create uploads folder: <code>mkdir -p " . $uploadDir . "</code>";
}
if (is_dir($uploadDir) && !is_writable($uploadDir)) {
    $issues[] = "Make uploads folder writable: <code>chmod 777 " . $uploadDir . "</code>";
}

// Check database column
try {
    $conn = getDBConnection();
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
    if (!$result || $result->num_rows == 0) {
        $issues[] = "Add profile_photo column: <code>ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER status;</code>";
    }
} catch (Exception $e) {
    $issues[] = "Check database connection";
}

if (count($issues) > 0) {
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
} else {
    echo "✅ All checks passed! Your system is ready for photo upload.<br>";
}

echo "<hr>";
echo "<p><a href='frontend/pages/residents.php' style='background:#1a3a6a;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;'>Go to Residents</a> ";
echo "<a href='frontend/pages/new-resident.php' style='background:#10b981;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;'>Go to New Resident</a></p>";
?>