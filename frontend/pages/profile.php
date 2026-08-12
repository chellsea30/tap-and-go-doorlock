<?php
/**
 * Tap-and-Go Doorlock - My Profile
 * COMPLETE PROFILE PAGE WITH WORKING AVATAR UPLOAD
 */

// Start session
session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

// Include header
include '../includes/header.php';

$conn = getDBConnection();
$error = '';
$success = '';

// Get user data
$userData = [];
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// ============================================================
// HANDLE AVATAR UPLOAD - FIXED
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        // Create upload directory if not exists
        $upload_dir = '../../uploads/avatars/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Get file info
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_name = $_FILES['avatar']['name'];
        $file_size = $_FILES['avatar']['size'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Validate file
        if (!in_array($file_extension, $allowed_types)) {
            $error = 'Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP';
        } elseif ($file_size > 2097152) { // 2MB
            $error = 'File too large. Maximum size is 2MB.';
        } else {
            // Generate unique filename
            $new_filename = 'avatar_' . $_SESSION['admin_id'] . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            
            // Delete old avatar if exists
            if (!empty($userData['avatar']) && file_exists('../../' . $userData['avatar'])) {
                unlink('../../' . $userData['avatar']);
            }
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $target_file)) {
                // Save to database
                $avatar_path = 'uploads/avatars/' . $new_filename;
                $stmt = $conn->prepare("UPDATE admin_users SET avatar = ? WHERE admin_id = ?");
                $stmt->bind_param("si", $avatar_path, $_SESSION['admin_id']);
                
                if ($stmt->execute()) {
                    $success = 'Profile picture updated successfully!';
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
                    $stmt->bind_param("i", $_SESSION['admin_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $userData = $result->fetch_assoc();
                    $stmt->close();
                } else {
                    $error = 'Failed to save to database: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = 'Failed to upload image. Please check folder permissions.';
            }
        }
    } else {
        $error = 'Please select an image to upload.';
    }
}

// ============================================================
// HANDLE PROFILE UPDATE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("UPDATE admin_users SET full_name = ?, email = ? WHERE admin_id = ?");
        $stmt->bind_param("ssi", $full_name, $email, $_SESSION['admin_id']);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            logAudit($_SESSION['admin_id'], 'Profile Update', "Updated profile: $full_name");
            $success = 'Profile updated successfully!';
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
            $stmt->bind_param("i", $_SESSION['admin_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error = 'Failed to update profile: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Get activity summary
$activitySummary = [
    'total_logins' => 0,
    'last_login' => $userData['last_login'] ?? 'Never',
    'total_actions' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM audit_logs WHERE admin_id = " . $_SESSION['admin_id']);
if ($result && $row = $result->fetch_assoc()) {
    $activitySummary['total_actions'] = $row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM audit_logs WHERE admin_id = " . $_SESSION['admin_id'] . " AND action = 'Login'");
if ($result && $row = $result->fetch_assoc()) {
    $activitySummary['total_logins'] = $row['count'];
}

// ============================================================
// HANDLE AVATAR REMOVE
// ============================================================
if (isset($_GET['remove_avatar'])) {
    if (!empty($userData['avatar']) && file_exists('../../' . $userData['avatar'])) {
        unlink('../../' . $userData['avatar']);
    }
    
    $stmt = $conn->prepare("UPDATE admin_users SET avatar = NULL WHERE admin_id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    if ($stmt->execute()) {
        $success = 'Profile picture removed successfully!';
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        $stmt->close();
    }
    $stmt->close();
}

// Get dark mode
$darkModeClass = '';
$darkModeFromDb = 'false';
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE admin_id = ? AND setting_key = 'dark_mode'");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $darkModeFromDb = $row['setting_value'];
            if ($darkModeFromDb == 'true') {
                $darkModeClass = 'dark-mode';
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Silently fail
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
        }
        
        /* ============================================================
           DARK NAVBAR
           ============================================================ */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* ============================================================
           DARK SIDEBAR
           ============================================================ */
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar-footer { border-top-color: #1a2a4a !important; }
        .sidebar-footer .text-muted { color: #606070 !important; }
        
        /* ============================================================
           DARK CARDS
           ============================================================ */
        .card {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .card-header {
            background: #111827 !important;
            border-bottom: 1px solid #1a2a4a !important;
        }
        .card-header h5 { color: #e0e0e0 !important; }
        .card-body { background: #111827 !important; }
        
        /* ============================================================
           PROFILE HEADER - DARK
           ============================================================ */
        .profile-header {
            background: linear-gradient(135deg, #0d1528, #1a3a6a) !important;
            border-radius: 16px 16px 0 0;
            padding: 30px;
            color: white;
            margin: -30px -30px 25px -30px;
            border-bottom: 1px solid #1a2a4a;
        }
        .profile-header h4 {
            color: #ffd700;
            font-weight: 700;
        }
        .profile-header .text-muted {
            color: #808090 !important;
        }
        
        /* ============================================================
           PROFILE AVATAR - DARK
           ============================================================ */
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: white;
            border: 4px solid #ffd700;
            margin: 0 auto;
            overflow: hidden;
            position: relative;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-upload {
            position: relative;
            display: inline-block;
        }
        .avatar-upload .upload-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #1a3a6a;
            color: white;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.3s ease;
        }
        .avatar-upload .upload-btn:hover {
            background: #ffd700;
            color: #1a3a6a;
        }
        
        .avatar-remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.3s ease;
            font-size: 12px;
            text-decoration: none;
        }
        .avatar-remove-btn:hover {
            background: #dc2626;
            color: white;
        }
        
        /* ============================================================
           PROFILE INFO - DARK
           ============================================================ */
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #1a2a4a;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #808090;
            font-weight: 500;
        }
        .info-value {
            font-weight: 600;
            color: #e0e0e0;
        }
        
        /* ============================================================
           STAT BOX - DARK
           ============================================================ */
        .stat-box {
            background: #1a1a2e !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .stat-box .number {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700;
        }
        .stat-box .label {
            font-size: 13px;
            color: #808090;
            margin: 0;
        }
        
        /* ============================================================
           FORM ELEMENTS - DARK
           ============================================================ */
        .form-control {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: #e0e0e0 !important;
        }
        .form-control:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
            background: #1a1a2e !important;
            color: #e0e0e0 !important;
        }
        .form-control:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .form-label {
            color: #b0b0c0 !important;
            font-weight: 500;
            font-size: 13px;
        }
        .required {
            color: #f87171 !important;
            margin-left: 2px;
        }
        
        /* ============================================================
           BUTTONS - DARK
           ============================================================ */
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            padding: 10px 35px;
            border-radius: 12px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26,58,106,0.4);
            color: white !important;
        }
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        .btn-outline-primary {
            border-color: #1a3a6a !important;
            color: #93c5fd !important;
        }
        .btn-outline-primary:hover {
            background: #1a3a6a !important;
            color: white !important;
        }
        .btn-outline-danger {
            border-color: #7a2a2a !important;
            color: #f87171 !important;
        }
        .btn-outline-danger:hover {
            background: #7a2a2a !important;
            color: #fca5a5 !important;
        }
        
        /* ============================================================
           ALERTS - DARK
           ============================================================ */
        .alert-success {
            background: #065f46 !important;
            border-color: #065f46 !important;
            color: #6ee7b7 !important;
        }
        .alert-danger {
            background: #7a2a2a !important;
            border-color: #7a2a2a !important;
            color: #f87171 !important;
        }
        .alert .btn-close { filter: invert(1) !important; }
        
        /* ============================================================
           BADGES - DARK
           ============================================================ */
        .badge-primary { background: #1a3a6a !important; color: #93c5fd !important; }
        .badge-success { background: #065f46 !important; color: #34d399 !important; }
        .badge-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
        
        /* ============================================================
           BORDER & MISC
           ============================================================ */
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        .text-warning { color: #fbbf24 !important; }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 56px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
            }
            .sidebar.show { left: 0; }
            .profile-header {
                padding: 20px;
                margin: -20px -20px 20px -20px;
            }
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            .info-item {
                flex-direction: column;
                gap: 5px;
            }
            .avatar-remove-btn {
                width: 25px;
                height: 25px;
                font-size: 10px;
                right: 2px;
                top: 2px;
            }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-user me-2" style="color: #1a3a6a;"></i>My Profile</h1>
                    <div class="btn-toolbar">
                        <a href="settings.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-cog me-1"></i> Settings
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- LEFT COLUMN - Profile Info -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <!-- Avatar -->
                                <div class="avatar-upload">
                                    <div class="profile-avatar">
                                        <?php 
                                            $avatarPath = $userData['avatar'] ?? '';
                                            $fullAvatarPath = '../../' . $avatarPath;
                                            $hasAvatar = !empty($avatarPath) && file_exists($fullAvatarPath);
                                            
                                            if ($hasAvatar): 
                                        ?>
                                            <img src="<?php echo $fullAvatarPath; ?>" alt="Avatar" onerror="this.style.display='none'; this.parentElement.textContent='<?php 
                                                $nameParts = explode(' ', $userData['full_name'] ?? 'User');
                                                $initials = '';
                                                foreach ($nameParts as $part) {
                                                    if (!empty($part)) {
                                                        $initials .= strtoupper($part[0]);
                                                    }
                                                }
                                                echo substr($initials, 0, 2);
                                            ?>';">
                                        <?php else: 
                                            $nameParts = explode(' ', $userData['full_name'] ?? 'User');
                                            $initials = '';
                                            foreach ($nameParts as $part) {
                                                if (!empty($part)) {
                                                    $initials .= strtoupper($part[0]);
                                                }
                                            }
                                            echo substr($initials, 0, 2);
                                        endif; ?>
                                    </div>
                                    
                                    <?php if ($hasAvatar): ?>
                                        <a href="?remove_avatar=1" class="avatar-remove-btn" onclick="return confirm('Remove your profile picture?')" title="Remove Photo">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="" enctype="multipart/form-data" id="avatarForm">
                                        <label class="upload-btn" title="Change Profile Picture">
                                            <i class="fas fa-camera"></i>
                                            <input type="file" name="avatar" accept="image/*" style="display:none;" onchange="document.getElementById('avatarForm').submit();">
                                        </label>
                                        <input type="hidden" name="upload_avatar" value="1">
                                    </form>
                                </div>
                                
                                <h5 class="mt-3 mb-0"><?php echo htmlspecialchars($userData['full_name'] ?? 'User'); ?></h5>
                                <span class="badge badge-primary"><?php echo ucfirst($userData['role'] ?? 'Staff'); ?></span>
                                <p class="text-muted small mt-2">
                                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($userData['email'] ?? 'N/A'); ?>
                                </p>
                                
                                <hr>
                                
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="stat-box">
                                            <div class="number"><?php echo $activitySummary['total_logins']; ?></div>
                                            <p class="label">Logins</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-box">
                                            <div class="number"><?php echo $activitySummary['total_actions']; ?></div>
                                            <p class="label">Actions</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN - Profile Details -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name <span class="required">*</span></label>
                                            <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($userData['full_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>" disabled>
                                            <small class="text-muted">Username cannot be changed</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email Address <span class="required">*</span></label>
                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control" value="<?php echo ucfirst($userData['role'] ?? 'Staff'); ?>" disabled>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Login</label>
                                            <input type="text" class="form-control" value="<?php echo $userData['last_login'] ? date('F d, Y h:i A', strtotime($userData['last_login'])) : 'N/A'; ?>" disabled>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Account Created</label>
                                            <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($userData['created_at'] ?? 'now')); ?>" disabled>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" name="update_profile" class="btn btn-submit">
                                            <i class="fas fa-save"></i> Update Profile
                                        </button>
                                        <a href="settings.php?tab=change-password" class="btn btn-outline-secondary ms-2">
                                            <i class="fas fa-key me-1"></i> Change Password
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <a href="settings.php?tab=twofa" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-shield-alt me-1"></i> Two-Factor Auth
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="settings.php?tab=dark-mode" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-moon me-1"></i> Dark Mode
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="settings.php?tab=security" class="btn btn-outline-danger w-100">
                                            <i class="fas fa-lock me-1"></i> Security
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>