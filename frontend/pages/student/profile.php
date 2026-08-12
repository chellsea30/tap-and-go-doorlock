<?php
/**
 * Tap-and-Go Doorlock - Student Profile
 * DARK MODE - WITH PROFILE PHOTO
 */

session_start();

require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

if (!isset($_SESSION['student_id']) || !isStudentSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$error = '';
$success = '';

// ============================================================
// GET STUDENT INFO
// ============================================================
$studentInfo = null;
$stmt = $conn->prepare("SELECT * FROM student_users WHERE student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$studentInfo = $result->fetch_assoc();
$stmt->close();

// ============================================================
// GET PROFILE PHOTO FROM users TABLE
// ============================================================
$profilePhoto = '';
$user_id = null;
$studentIdNumber = $_SESSION['student_id_number'] ?? '';

// Try to find user by student_id
$stmt = $conn->prepare("SELECT user_id, profile_photo, full_name FROM users WHERE student_id = ?");
$stmt->bind_param("s", $studentIdNumber);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $profilePhoto = $row['profile_photo'];
    $user_id = $row['user_id'];
}
$stmt->close();

// If not found, try by email
if (empty($profilePhoto) && !empty($studentInfo['email'])) {
    $stmt = $conn->prepare("SELECT user_id, profile_photo, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $studentInfo['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $profilePhoto = $row['profile_photo'];
        $user_id = $row['user_id'];
    }
    $stmt->close();
}

// If still not found, try by full_name
if (empty($profilePhoto) && !empty($_SESSION['full_name'])) {
    $stmt = $conn->prepare("SELECT user_id, profile_photo, full_name FROM users WHERE full_name LIKE ?");
    $name = '%' . $_SESSION['full_name'] . '%';
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $profilePhoto = $row['profile_photo'];
        $user_id = $row['user_id'];
    }
    $stmt->close();
}

$fullPhotoPath = '../../../' . $profilePhoto;
$hasPhoto = !empty($profilePhoto) && file_exists($fullPhotoPath);

// ============================================================
// GET RFID CARD
// ============================================================
$rfidCard = null;
if ($user_id) {
    $stmt = $conn->prepare("SELECT * FROM rfid_cards WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rfidCard = $result->fetch_assoc();
    $stmt->close();
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = trim($_POST['phone'] ?? '');
    $stmt = $conn->prepare("UPDATE student_users SET phone = ? WHERE student_id = ?");
    $stmt->bind_param("si", $phone, $_SESSION['student_id']);
    if ($stmt->execute()) {
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile.";
    }
    $stmt->close();
}

// ============================================================
// GET INITIALS
// ============================================================
$name = $_SESSION['full_name'] ?? 'Student';
$initials = '';
$parts = explode(' ', $name);
foreach ($parts as $p) {
    if (!empty($p)) $initials .= strtoupper($p[0]);
}
$initials = substr($initials, 0, 2) ?: 'S';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        /* ================================================================
           DARK MODE STYLES - NO WHITE BACKGROUNDS
           ================================================================ */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e5e7eb !important;
            padding-top: 56px;
            min-height: 100vh;
        }
        
        /* ===== NAVBAR ===== */
        .navbar {
            background: linear-gradient(135deg, #0a1628, #1a2a4a) !important;
            border-bottom: 1px solid #1e2a3a;
            height: 56px;
            padding: 0 20px;
            z-index: 1050;
        }
        .navbar-brand { color: #ffd700 !important; font-weight: 700; font-size: 18px; }
        .navbar-brand i { color: #ffd700; }
        .navbar .nav-link {
            color: rgba(255,255,255,0.7) !important;
            font-size: 14px;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .navbar .nav-link:hover { color: white !important; background: rgba(255,255,255,0.08); }
        .navbar .nav-link.active { color: white !important; background: rgba(255,215,0,0.15); }
        .navbar .nav-link i { margin-right: 6px; }
        .logout-btn {
            color: rgba(255,255,255,0.7) !important;
            padding: 6px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .logout-btn:hover { background: rgba(255,255,255,0.1); color: white !important; }
        .student-badge {
            background: rgba(255, 215, 0, 0.15);
            color: #ffd700;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        .navbar-text { color: rgba(255,255,255,0.8) !important; font-size: 14px; }
        
        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            bottom: 0;
            width: 260px;
            background: #131926 !important;
            border-right: 1px solid #1e2a3a;
            overflow-y: auto;
            z-index: 999;
            transition: transform 0.3s ease;
        }
        .sidebar .nav-link {
            color: #9ca3af !important;
            padding: 10px 18px;
            border-radius: 8px;
            margin: 2px 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
        }
        .sidebar .nav-link:hover {
            background: rgba(255, 215, 0, 0.08);
            color: #ffd700 !important;
        }
        .sidebar .nav-link.active {
            background: rgba(255, 215, 0, 0.12);
            color: #ffd700 !important;
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        .sidebar-footer {
            padding: 12px 18px;
            border-top: 1px solid #1e2a3a;
            margin-top: 5px;
        }
        .sidebar-footer .text-muted {
            color: #6b7280 !important;
            font-size: 11px;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: calc(100vh - 56px);
            background: #0a0e1a;
        }
        
        /* ===== PROFILE CARD ===== */
        .profile-card {
            background: #131926 !important;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.4);
            border: 1px solid #1e2a3a;
        }
        .profile-card hr {
            border-color: #1e2a3a;
        }
        .profile-card h4 {
            color: #ffd700 !important;
        }
        .profile-card h5 {
            color: #ffd700 !important;
        }
        .profile-card h6 {
            color: #d1d5db !important;
        }
        .profile-card .text-muted {
            color: #6b7280 !important;
        }
        .profile-card .badge.bg-success {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
        }
        .profile-card .badge.bg-info {
            background: rgba(6, 182, 212, 0.2) !important;
            color: #67e8f9 !important;
        }
        
        /* ===== PROFILE AVATAR ===== */
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            margin: 0 auto 15px;
            font-weight: 700;
            overflow: hidden;
            border: 4px solid #1e2a3a;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-avatar .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 48px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .photo-badge {
            position: relative;
            display: inline-block;
        }
        .photo-badge .badge-icon {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            border: 3px solid #131926;
        }
        
        /* ===== FORM ===== */
        .form-label {
            color: #d1d5db !important;
            font-weight: 500;
            font-size: 13px;
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
        .form-control[disabled] {
            background: #0a0e1a !important;
            color: #6b7280 !important;
            cursor: not-allowed;
        }
        .form-control::placeholder {
            color: #6b7280 !important;
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
        .btn-outline-primary {
            color: #ffd700 !important;
            border-color: rgba(255, 215, 0, 0.3) !important;
        }
        .btn-outline-primary:hover {
            background: rgba(255, 215, 0, 0.15) !important;
            color: #ffd700 !important;
        }
        
        /* ===== RFID CARD BOX ===== */
        .rfid-card-box {
            background: #0d1220 !important;
            border-radius: 12px;
            padding: 15px 20px;
            border: 1px solid #1e2a3a;
            text-align: center;
        }
        .rfid-card-box .card-uid {
            font-family: monospace;
            font-size: 18px;
            font-weight: 700;
            color: #ffd700 !important;
            letter-spacing: 2px;
        }
        .rfid-card-box .text-muted {
            color: #6b7280 !important;
        }
        .rfid-card-box .badge.bg-success {
            background: rgba(16, 185, 129, 0.2) !important;
            color: #6ee7b7 !important;
        }
        .rfid-card-box .badge.bg-info {
            background: rgba(6, 182, 212, 0.2) !important;
            color: #67e8f9 !important;
        }
        .rfid-card-box .text-primary {
            color: #93c5fd !important;
        }
        
        /* ===== ALERTS ===== */
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
        
        /* ===== HEADINGS ===== */
        .h1, .h2, .h3, .h4, .h5, h1, h2, h3, h4, h5 {
            color: #e5e7eb !important;
        }
        .border-bottom {
            border-color: #1e2a3a !important;
        }
        .text-muted {
            color: #6b7280 !important;
        }
        
        /* ===== SCROLLBAR ===== */
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
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 56px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 998;
            }
            .sidebar-overlay.show { display: block; }
            .navbar-toggler {
                border-color: rgba(255,255,255,0.1) !important;
            }
            .navbar-toggler-icon {
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.8)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
            }
            .profile-card {
                padding: 20px;
            }
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
        }
    </style>
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <button class="navbar-toggler me-2" type="button" onclick="toggleSidebar()">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-door-open me-2"></i> Tap-and-Go
            </a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="access-history.php"><i class="fas fa-clock"></i> Access History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-rfid.php"><i class="fas fa-id-card"></i> My RFID Card</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="concerns.php"><i class="fas fa-exclamation-circle"></i> Concerns</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="request-reset.php"><i class="fas fa-key"></i> Reset Password</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="navbar-text me-2">
                        <i class="fas fa-user-graduate me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
                        <span class="student-badge ms-1">Student</span>
                    </span>
                    <a href="../../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ===== SIDEBAR OVERLAY (mobile) ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- ===== SIDEBAR ===== -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2" style="font-size:24px; font-weight:700;"><i class="fas fa-user me-2" style="color: #ffd700;"></i>My Profile</h1>
            <span class="badge bg-success"><i class="fas fa-circle me-1"></i> Active</span>
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

        <div class="row g-3">
            <!-- Left Column - Profile Info -->
            <div class="col-lg-8">
                <div class="profile-card">
                    <div class="text-center">
                        <div class="photo-badge">
                            <div class="profile-avatar">
                                <?php if ($hasPhoto): ?>
                                    <img src="<?php echo $fullPhotoPath; ?>" alt="Profile Photo" onerror="this.style.display='none'; this.parentElement.querySelector('.no-photo').style.display='flex';">
                                    <div class="no-photo" style="display:none;">
                                        <?php echo $initials; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-photo"><?php echo $initials; ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($hasPhoto): ?>
                                <span class="badge-icon"><i class="fas fa-check-circle"></i></span>
                            <?php endif; ?>
                        </div>
                        <h4 class="mt-2"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($_SESSION['student_id_number'] ?? 'N/A'); ?></p>
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Active</span>
                    </div>
                    <hr>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Student ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['student_id_number'] ?? ''); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($studentInfo['email'] ?? ''); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($studentInfo['phone'] ?? ''); ?>" placeholder="Enter phone number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Course</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['course'] ?? ''); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Year Level</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['year_level'] ?? ''); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Room Number</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($studentInfo['room_number'] ?? ''); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Created</label>
                                <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($studentInfo['created_at'] ?? 'now')); ?>" disabled>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="update_profile" class="btn btn-submit">
                                <i class="fas fa-save me-1"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column - RFID Card -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <h5><i class="fas fa-id-card me-2" style="color: #ffd700;"></i>My RFID Card</h5>
                    <hr>
                    <?php if ($rfidCard): ?>
                        <div class="rfid-card-box">
                            <i class="fas fa-id-card fa-3x text-primary mb-2"></i>
                            <p class="mb-1 text-muted small">Card UID</p>
                            <p class="card-uid"><?php echo htmlspecialchars($rfidCard['card_uid']); ?></p>
                            <p class="mb-0 text-muted small">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Issued: <?php echo date('M d, Y', strtotime($rfidCard['issued_date'])); ?>
                            </p>
                            <?php if (!empty($rfidCard['expiry_date'])): ?>
                                <p class="mb-0 text-muted small">
                                    <i class="fas fa-hourglass-end me-1"></i>
                                    Expires: <?php echo date('M d, Y', strtotime($rfidCard['expiry_date'])); ?>
                                </p>
                            <?php endif; ?>
                            <p class="mt-2 mb-0">
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Active</span>
                            </p>
                            <?php if (!empty($rfidCard['card_type'])): ?>
                                <p class="mt-2 mb-0">
                                    <span class="badge bg-info"><?php echo ucfirst($rfidCard['card_type']); ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="rfid-card-box">
                            <i class="fas fa-id-card fa-3x text-muted mb-2"></i>
                            <p class="text-muted">No RFID card assigned yet</p>
                            <p class="small text-muted">Please contact the dormitory admin</p>
                            <a href="../contact.php" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="fas fa-envelope me-1"></i> Contact Admin
                            </a>
                        </div>
                    <?php endif; ?>
                    <hr>
                    <h6 class="mt-3"><i class="fas fa-info-circle me-2" style="color: #ffd700;"></i>Card Usage</h6>
                    <ul class="small text-muted">
                        <li>Tap your card on the reader to enter/exit</li>
                        <li>Keep your card safe and secure</li>
                        <li>Report lost cards immediately to admin</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('studentSidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>