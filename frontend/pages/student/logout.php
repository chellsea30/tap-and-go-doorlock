<?php
/**
 * Tap-and-Go Doorlock - Student Logout
 * WITH CONFIRMATION MODAL
 */

// Start session
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id']) || !isStudentSessionValid()) {
    header('Location: login.php');
    exit();
}

// Get student info for the modal
$student_name = $_SESSION['full_name'] ?? 'Student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .logout-container {
            background: #131926;
            border-radius: 24px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            text-align: center;
            border: 1px solid #1e2a3a;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: fadeInUp 0.5s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logout-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: #fca5a5;
        }
        .logout-title {
            color: #ffd700;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .logout-subtitle {
            color: #9ca3af;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .logout-subtitle strong {
            color: #e5e7eb;
        }
        .student-info {
            background: #0d1220;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 30px;
            border: 1px solid #1e2a3a;
        }
        .student-info .name {
            color: #ffd700;
            font-weight: 600;
            font-size: 16px;
        }
        .student-info .detail {
            color: #6b7280;
            font-size: 13px;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn-group .btn {
            flex: 1;
            min-width: 120px;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-logout {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
        }
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
            color: white;
        }
        .btn-cancel {
            background: #1e2a3a;
            color: #e5e7eb;
            border: 1px solid #2d3548;
        }
        .btn-cancel:hover {
            background: #2d3548;
            color: white;
            transform: translateY(-2px);
        }
        .btn-back {
            background: transparent;
            color: #6b7280;
            border: 1px solid #1e2a3a;
            flex: 0.5;
        }
        .btn-back:hover {
            background: #1e2a3a;
            color: #e5e7eb;
        }
        .warning-text {
            color: #fca5a5;
            font-size: 13px;
            margin-bottom: 25px;
        }
        .warning-text i {
            margin-right: 6px;
        }
        .spinner-border {
            width: 20px;
            height: 20px;
            border-width: 2px;
        }
        @media (max-width: 480px) {
            .logout-container { padding: 25px; }
            .btn-group { flex-direction: column; }
            .btn-group .btn { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="logout-container">
        <!-- Icon -->
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>

        <!-- Title -->
        <h1 class="logout-title">Logout Confirmation</h1>
        <p class="logout-subtitle">
            Are you sure you want to logout, <strong><?php echo htmlspecialchars($student_name); ?></strong>?
        </p>

        <!-- Student Info -->
        <div class="student-info">
            <div class="name">
                <i class="fas fa-user-graduate me-2"></i>
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
            </div>
            <div class="detail">
                <i class="fas fa-id-card me-1"></i>
                <?php echo htmlspecialchars($_SESSION['student_id_number'] ?? 'N/A'); ?>
                <span class="mx-1">•</span>
                <i class="fas fa-graduation-cap me-1"></i>
                <?php echo htmlspecialchars($_SESSION['course'] ?? 'N/A'); ?>
                - <?php echo htmlspecialchars($_SESSION['year_level'] ?? 'N/A'); ?>
            </div>
            <?php if (!empty($_SESSION['room_number'])): ?>
            <div class="detail">
                <i class="fas fa-bed me-1"></i>
                Room <?php echo htmlspecialchars($_SESSION['room_number']); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Warning -->
        <div class="warning-text">
            <i class="fas fa-exclamation-triangle"></i>
            You will be redirected to the login page.
        </div>

        <!-- Buttons -->
        <div class="btn-group">
            <!-- Cancel - Go Back -->
            <a href="javascript:history.back()" class="btn btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </a>
            
            <!-- Confirm Logout -->
            <form method="POST" action="logout-process.php" style="flex:1; min-width:120px;">
                <button type="submit" name="confirm_logout" class="btn btn-logout" id="logoutBtn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>

        <!-- Back to Dashboard Link -->
        <div class="mt-3">
            <a href="dashboard.php" class="btn btn-back btn-sm" style="padding: 8px 20px; font-size: 13px;">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <!-- Footer -->
        <div class="mt-4 text-muted" style="font-size: 11px; color: #6b7280;">
            <i class="fas fa-lock me-1"></i>
            Your session will be securely terminated
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent accidental double submission
        document.getElementById('logoutBtn')?.addEventListener('click', function(e) {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Logging out...';
        });

        // Keyboard shortcut: Escape key to cancel
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.history.back();
            }
        });
    </script>
</body>
</html>