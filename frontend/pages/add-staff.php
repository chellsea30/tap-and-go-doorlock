<?php
/**
 * Tap-and-Go Doorlock - Add Staff
 * DARK MODE - AUTO STAFF ID - NO WHITE BACKGROUNDS
 */

session_start();

// ============================================================
// FIXED: Correct include paths from frontend/pages/
// ============================================================
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check if logged in as admin
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: ../login.php');
    exit();
}

// Include header
include '../includes/header.php'; 

$conn = getDBConnection();
$error = '';
$success = '';

// ============================================================
// GET NEXT STAFF ID NUMBER
// ============================================================
function getNextStaffId($conn) {
    $result = $conn->query("SELECT staff_id_number FROM staff_users ORDER BY staff_id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $lastId = $row['staff_id_number'];
        // Extract number from STAFF-001 format
        $num = (int)substr($lastId, 6);
        $nextNum = $num + 1;
        return 'STAFF-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }
    return 'STAFF-001';
}

$nextStaffId = getNextStaffId($conn);

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $staff_id_number = trim($_POST['staff_id_number'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // ============================================================
    // HANDLE PHOTO UPLOAD
    // ============================================================
    $photo_path = '';
    
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/staff_photos/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $file_name = 'staff_' . time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;
        
        // Check if image file is valid
        $image_info = getimagesize($_FILES['profile_photo']['tmp_name']);
        if ($image_info !== false) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($image_info['mime'], $allowed_types)) {
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                    $photo_path = 'uploads/staff_photos/' . $file_name;
                } else {
                    $error = 'Failed to upload photo. Please check folder permissions.';
                }
            } else {
                $error = 'Invalid file type. Please upload JPEG, PNG, GIF, or WEBP.';
            }
        } else {
            $error = 'Uploaded file is not a valid image.';
        }
    }
    
    // Validation
    if (empty($error)) {
        if (empty($staff_id_number) || empty($full_name) || empty($email) || empty($department)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if staff ID or email already exists
            $check = $conn->prepare("SELECT staff_id FROM staff_users WHERE staff_id_number = ? OR email = ?");
            $check->bind_param("ss", $staff_id_number, $email);
            $check->execute();
            $checkResult = $check->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = 'Staff ID or Email already exists.';
            } else {
                // Hash password
                $password_hash = password_hash($password ?: 'Staff@123', PASSWORD_DEFAULT);
                
                // Check if avatar column exists
                $tableCheck = $conn->query("SHOW COLUMNS FROM staff_users LIKE 'avatar'");
                $hasAvatar = $tableCheck && $tableCheck->num_rows > 0;
                
                if ($hasAvatar) {
                    $stmt = $conn->prepare("INSERT INTO staff_users (staff_id_number, full_name, email, department, phone, password_hash, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss", $staff_id_number, $full_name, $email, $department, $phone, $password_hash, $photo_path);
                } else {
                    $stmt = $conn->prepare("INSERT INTO staff_users (staff_id_number, full_name, email, department, phone, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $staff_id_number, $full_name, $email, $department, $phone, $password_hash);
                }
                
                if ($stmt->execute()) {
                    $success = "Staff added successfully! They can now login with their Staff ID.";
                    if (!empty($photo_path)) {
                        $success .= " Profile photo uploaded!";
                    }
                    logAudit($_SESSION['admin_id'], 'Add Staff', "Added staff: $full_name ($staff_id_number)");
                    
                    // Redirect after success
                    echo '<script>setTimeout(function(){ window.location.href = "staff-info.php"; }, 2000);</script>';
                } else {
                    $error = "Failed to add staff: " . $stmt->error;
                }
                $stmt->close();
            }
            $check->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ================================================================
           DARK MODE STYLES - NO WHITE BACKGROUNDS
           ================================================================ */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        body {
            background: #0a0e1a !important;
            color: #e5e7eb !important;
            font-family: 'Inter', sans-serif;
        }
        
        .container-fluid {
            background: #0a0e1a !important;
        }
        
        .form-section {
            background: #131926 !important;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            border: 1px solid #1e2a3a;
        }
        
        .form-section h5 {
            color: #ffd700 !important;
            font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #d1d5db !important;
        }
        
        .form-control {
            background: #0d1220 !important;
            border: 1px solid #1e2a3a !important;
            color: #e5e7eb !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15) !important;
            background: #0d1220 !important;
            color: #e5e7eb !important;
        }
        
        .form-control::placeholder {
            color: #6b7280 !important;
        }
        
        .required {
            color: #ef4444 !important;
            margin-left: 2px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            padding: 10px 35px;
            border-radius: 12px;
            font-weight: 600;
            color: #0a0e1a !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3) !important;
            color: #0a0e1a !important;
        }
        
        .btn-outline-secondary {
            color: #9ca3af !important;
            border-color: #1e2a3a !important;
        }
        .btn-outline-secondary:hover {
            background: #1e2a3a !important;
            color: #e5e7eb !important;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: #10b981 !important;
            color: #6ee7b7 !important;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: #ef4444 !important;
            color: #fca5a5 !important;
        }
        .btn-close {
            filter: invert(1) !important;
        }
        
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 {
            color: #e5e7eb !important;
        }
        
        .border-bottom {
            border-color: #1e2a3a !important;
        }
        
        .text-muted {
            color: #6b7280 !important;
        }
        
        .info-box {
            background: #0d1220 !important;
            border-radius: 12px;
            padding: 15px 20px;
            border-left: 4px solid #ffd700;
            border: 1px solid #1e2a3a;
        }
        .info-box .label {
            font-size: 12px;
            color: #6b7280 !important;
            font-weight: 500;
        }
        .info-box .value {
            font-size: 15px;
            font-weight: 600;
            color: #ffd700 !important;
        }
        
        /* Photo Upload */
        .photo-upload {
            width: 150px;
            height: 180px;
            border: 2px dashed #1e2a3a;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #0d1220 !important;
            overflow: hidden;
            position: relative;
        }
        .photo-upload:hover {
            border-color: #ffd700 !important;
            background: #1a1f2e !important;
        }
        .photo-upload i {
            font-size: 40px;
            color: #6b7280 !important;
        }
        .photo-upload span {
            font-size: 12px;
            color: #6b7280 !important;
            margin-top: 8px;
        }
        .photo-upload .preview-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }
        .photo-upload .remove-photo {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-size: 14px;
            cursor: pointer;
            z-index: 10;
            display: none;
        }
        .photo-upload.has-image .remove-photo {
            display: block;
        }
        .photo-upload.has-image .upload-placeholder {
            display: none;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #0a0e1a;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e2a3a;
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #ffd700;
        }
        
        .staff-id-preview {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.2);
            color: #ffd700;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
        }
        
        @media print {
            .no-print { display: none !important; }
            .form-section { 
                box-shadow: none !important; 
                border: 1px solid #ddd !important;
                background: #fff !important;
            }
            .form-section h5 { color: #1a3a6a !important; border-bottom-color: #1a3a6a !important; }
            body { background: #fff !important; color: #000 !important; }
            .form-control { background: #fff !important; color: #000 !important; border-color: #ddd !important; }
            .form-label { color: #333 !important; }
            .info-box { background: #f8f9fa !important; border-color: #1a3a6a !important; }
            .info-box .value { color: #1a3a6a !important; }
        }
        
        @media (max-width: 768px) {
            .form-section {
                padding: 20px;
            }
            .photo-upload {
                width: 120px;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-user-plus me-2" style="color: #ffd700;"></i>Add New Staff</h1>
                    <div class="btn-toolbar">
                        <a href="staff-info.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Staff List
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <div class="mt-2">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                The staff member can now login at: 
                                <a href="../staff/login.php" target="_blank" style="color: #6ee7b7;">Staff Login Page</a>
                            </small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="form-section">
                    <h5><i class="fas fa-user-tie me-2"></i>Staff Information</h5>
                    
                    <!-- Info Box -->
                    <div class="info-box mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="label">Next Staff ID</div>
                                <div class="value"><?php echo $nextStaffId; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="label">Default Password</div>
                                <div class="value">Staff@123</div>
                            </div>
                            <div class="col-md-4">
                                <div class="label">Profile Photo</div>
                                <div class="value">Optional - Upload below</div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="row g-3">
                            <!-- Staff ID -->
                            <div class="col-md-6">
                                <label class="form-label">Staff ID Number <span class="required">*</span></label>
                                <input type="text" class="form-control" name="staff_id_number" placeholder="e.g., STAFF-001" value="<?php echo htmlspecialchars($_POST['staff_id_number'] ?? $nextStaffId); ?>" required>
                                <small class="text-muted">Auto-generated: <strong class="text-warning"><?php echo $nextStaffId; ?></strong></small>
                            </div>
                            
                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="full_name" placeholder="Enter full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                            </div>
                            
                            <!-- Email -->
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="required">*</span></label>
                                <input type="email" class="form-control" name="email" placeholder="staff@isu.edu.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            
                            <!-- Department -->
                            <div class="col-md-6">
                                <label class="form-label">Department <span class="required">*</span></label>
                                <input type="text" class="form-control" name="department" placeholder="e.g., Dormitory Management" value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>" required>
                            </div>
                            
                            <!-- Phone -->
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone" placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                            
                            <!-- Password -->
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" placeholder="Leave blank for default (Staff@123)">
                                <small class="text-muted">Default password: <strong class="text-warning">Staff@123</strong></small>
                            </div>
                            
                            <!-- Profile Photo Upload -->
                            <div class="col-md-12">
                                <label class="form-label">Profile Photo</label>
                                <div class="photo-upload" id="photoUpload" onclick="document.getElementById('photoInput').click()">
                                    <i class="fas fa-camera upload-placeholder"></i>
                                    <span class="upload-placeholder">Click to upload photo</span>
                                    <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;">
                                    <button type="button" class="remove-photo" onclick="removePhoto(event)">✕</button>
                                </div>
                                <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF, WEBP</small>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="submit" class="btn btn-submit">
                                <i class="fas fa-save me-1"></i> Add Staff
                            </button>
                            <a href="staff-info.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================================
        // PHOTO UPLOAD PREVIEW
        // ============================================================
        document.getElementById('photoInput').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const photoDiv = document.getElementById('photoUpload');
                    photoDiv.innerHTML = `
                        <img src="${event.target.result}" class="preview-img" alt="Preview">
                        <button type="button" class="remove-photo" onclick="removePhoto(event)">✕</button>
                        <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;" onchange="handlePhotoChange(event)">
                    `;
                    photoDiv.classList.add('has-image');
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        function handlePhotoChange(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const photoDiv = document.getElementById('photoUpload');
                    photoDiv.innerHTML = `
                        <img src="${event.target.result}" class="preview-img" alt="Preview">
                        <button type="button" class="remove-photo" onclick="removePhoto(event)">✕</button>
                        <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;" onchange="handlePhotoChange(event)">
                    `;
                    photoDiv.classList.add('has-image');
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        }

        function removePhoto(e) {
            e.stopPropagation();
            const photoDiv = document.getElementById('photoUpload');
            photoDiv.innerHTML = `
                <i class="fas fa-camera upload-placeholder"></i>
                <span class="upload-placeholder">Click to upload photo</span>
                <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;" onchange="handlePhotoChange(event)">
                <button type="button" class="remove-photo" onclick="removePhoto(event)">✕</button>
            `;
            photoDiv.classList.remove('has-image');
            document.getElementById('photoInput').value = '';
        }

        // ============================================================
        // AUTO-FILL STAFF ID WITH NEXT ID
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const staffIdInput = document.querySelector('input[name="staff_id_number"]');
            if (staffIdInput && !staffIdInput.value) {
                staffIdInput.value = '<?php echo $nextStaffId; ?>';
            }
        });
    </script>
</body>
</html>