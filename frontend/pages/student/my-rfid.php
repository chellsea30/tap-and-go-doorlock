<?php
/**
 * Tap-and-Go Doorlock - Student My RFID Card
 */

session_start();

require_once '../../../backend/config/config.php';
require_once '../../../backend/helpers/functions.php';

if (!isset($_SESSION['student_id']) || !isStudentSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

$studentInfo = null;
$stmt = $conn->prepare("SELECT * FROM student_users WHERE student_id = ?");
$stmt->bind_param("i", $_SESSION['student_id']);
$stmt->execute();
$result = $stmt->get_result();
$studentInfo = $result->fetch_assoc();
$stmt->close();

$rfidCard = null;
$stmt = $conn->prepare("SELECT * FROM rfid_cards WHERE user_id = (SELECT user_id FROM users WHERE email = ? LIMIT 1) AND status = 'active'");
$stmt->bind_param("s", $studentInfo['email']);
$stmt->execute();
$result = $stmt->get_result();
$rfidCard = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My RFID Card - Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            padding-top: 56px;
            min-height: 100vh;
        }
        .navbar {
            background: linear-gradient(135deg, #0a1628, #1a3a6a) !important;
            height: 56px;
            padding: 0 20px;
        }
        .navbar-brand { color: white !important; font-weight: 700; font-size: 18px; }
        .navbar-brand i { color: #ffd700; }
        .navbar .nav-link { color: rgba(255,255,255,0.7) !important; padding: 8px 15px; border-radius: 8px; }
        .navbar .nav-link:hover { color: white !important; background: rgba(255,255,255,0.08); }
        .navbar .nav-link.active { color: white !important; background: rgba(255,255,255,0.12); }
        .logout-btn {
            color: rgba(255,255,255,0.7) !important;
            padding: 6px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            text-decoration: none;
        }
        .logout-btn:hover { background: rgba(255,255,255,0.1); color: white !important; }
        .student-badge {
            background: rgba(59,130,246,0.15);
            color: #3b82f6;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: calc(100vh - 56px);
        }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .card-header { background: white; border-bottom: 1px solid #f0f2f5; border-radius: 16px 16px 0 0; padding: 15px 20px; }
        .card-header h5 { margin: 0; font-weight: 600; color: #1a1a2e; }
        .card-body { padding: 20px; }
        .rfid-card-display {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            color: white;
            max-width: 400px;
            margin: 0 auto;
        }
        .rfid-card-display .card-uid {
            font-family: monospace;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 3px;
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 10px;
            margin: 15px 0;
        }
        .rfid-card-display .label { font-size: 12px; opacity: 0.7; }
        .rfid-card-display .icon { font-size: 48px; margin-bottom: 10px; }
        .card-status {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fecaca; color: #991b1b; }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .navbar-toggler {
                border-color: rgba(255,255,255,0.1) !important;
            }
            .navbar-toggler-icon {
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.8)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 56px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.4);
                z-index: 998;
            }
            .sidebar-overlay.show { display: block; }
        }
    </style>
</head>
<body>

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
                        <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="access-history.php"><i class="fas fa-clock"></i> Access History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my-rfid.php"><i class="fas fa-id-card"></i> My RFID Card</a>
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
                    <span class="navbar-text text-white me-2">
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

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2" style="font-size:24px; font-weight:700; color:#1a1a2e;"><i class="fas fa-id-card me-2"></i>My RFID Card</h1>
            <?php if ($rfidCard): ?>
                <span class="card-status status-active"><i class="fas fa-check-circle me-1"></i> Active</span>
            <?php else: ?>
                <span class="card-status status-inactive"><i class="fas fa-times-circle me-1"></i> No Card</span>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if ($rfidCard): ?>
                    <div class="rfid-card-display">
                        <div class="icon"><i class="fas fa-id-card"></i></div>
                        <div class="label">CARD UID</div>
                        <div class="card-uid"><?php echo htmlspecialchars($rfidCard['card_uid']); ?></div>
                        <div class="row mt-3">
                            <div class="col-6">
                                <div class="label">Issued Date</div>
                                <div class="fw-bold"><?php echo date('M d, Y', strtotime($rfidCard['issued_date'])); ?></div>
                            </div>
                            <div class="col-6">
                                <div class="label">Expiry Date</div>
                                <div class="fw-bold"><?php echo date('M d, Y', strtotime($rfidCard['expiry_date'])); ?></div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Active</span>
                            <span class="badge bg-info ms-1"><?php echo ucfirst($rfidCard['card_type']); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6><i class="fas fa-info-circle me-2" style="color: #1a3a6a;"></i>Card Information</h6>
                        <ul class="text-muted small">
                            <li><strong>Card Type:</strong> <?php echo ucfirst($rfidCard['card_type']); ?></li>
                            <li><strong>Status:</strong> <span class="badge bg-success">Active</span></li>
                            <li><strong>Assigned To:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?></li>
                            <li><strong>Room:</strong> <?php echo htmlspecialchars($studentInfo['room_number'] ?? 'N/A'); ?></li>
                        </ul>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>How to use your RFID card:</strong>
                        <ol class="mb-0 small mt-1">
                            <li>Tap your card on the reader at the entrance</li>
                            <li>Wait for the green LED and buzzer sound</li>
                            <li>Door will unlock for 3 seconds</li>
                            <li>Tap again on the exit reader when leaving</li>
                        </ol>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-id-card fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No RFID Card Assigned</h5>
                        <p class="text-muted">You don't have an active RFID card yet.</p>
                        <p class="text-muted small">Please contact the dormitory administrator to get your RFID card.</p>
                        <div class="mt-3">
                            <span class="badge bg-secondary">Pending</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
    </script>
</body>
</html>