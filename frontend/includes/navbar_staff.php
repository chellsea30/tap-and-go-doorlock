<?php
/**
 * Tap-and-Go Doorlock - Staff Navbar
 * PURE DARK MODE - Same as Admin
 */

// Check if session exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <!-- Sidebar Toggle (mobile) -->
        <button class="navbar-toggler me-2" type="button" onclick="toggleSidebar()">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Brand -->
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-door-open me-2"></i> Tap-and-Go
        </a>
        
        <!-- Navbar Links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'alerts.php' ? 'active' : ''; ?>" href="alerts.php">
                        <i class="fas fa-bell"></i> Alerts
                        <?php
                            $conn = getDBConnection();
                            $result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
                            $row = $result->fetch_assoc();
                            $pendingAlerts = $row['count'] ?? 0;
                        ?>
                        <?php if ($pendingAlerts > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1"><?php echo $pendingAlerts; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>" href="logs.php">
                        <i class="fas fa-history"></i> Access Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
            </ul>
            
            <!-- Right Side -->
            <div class="d-flex align-items-center">
                <span class="navbar-text me-2">
                    <i class="fas fa-eye me-1" style="color: #fbbf24;"></i>
                    <span class="text-warning">View Only</span>
                    <span class="mx-1">|</span>
                    <i class="fas fa-user-tie me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?>
                </span>
                <a href="../logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<style>
    /* ============================================================
       STAFF NAVBAR - DARK THEME
       ============================================================ */
    .navbar {
        background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
        border-bottom: 1px solid #1a2a4a !important;
        height: 56px;
        padding: 0 20px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.3) !important;
        z-index: 1050;
    }
    .navbar-brand {
        color: #e0e0e0 !important;
        font-weight: 700;
        font-size: 18px;
    }
    .navbar-brand i {
        color: #ffd700;
    }
    .navbar .nav-link {
        color: rgba(255,255,255,0.6) !important;
        font-size: 14px;
        padding: 8px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    .navbar .nav-link:hover {
        color: #ffffff !important;
        background: rgba(255,255,255,0.05) !important;
    }
    .navbar .nav-link.active {
        color: #ffffff !important;
        background: rgba(255,255,255,0.08) !important;
    }
    .navbar .nav-link i {
        margin-right: 6px;
    }
    .navbar .nav-link .badge {
        font-size: 10px;
        padding: 2px 6px;
        background: #7a2a2a !important;
        color: #f87171 !important;
    }
    .navbar-text {
        color: rgba(255,255,255,0.8) !important;
        font-size: 14px;
    }
    .navbar-text .text-warning {
        color: #fbbf24 !important;
    }
    .logout-btn {
        color: rgba(255,255,255,0.7) !important;
        padding: 6px 16px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.15);
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s ease;
        background: transparent;
    }
    .logout-btn:hover {
        background: rgba(255,255,255,0.1);
        color: white !important;
    }
    
    /* Hamburger Menu */
    .navbar-toggler {
        border-color: rgba(255,255,255,0.1) !important;
        padding: 4px 8px;
    }
    .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.8)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .navbar-text {
            font-size: 12px;
        }
        .navbar-text .text-warning {
            display: none;
        }
        .logout-btn {
            padding: 4px 12px;
            font-size: 12px;
        }
        .navbar .nav-link {
            font-size: 13px;
            padding: 6px 12px;
        }
    }
</style>

<script>
    // Toggle sidebar function for mobile
    function toggleSidebar() {
        document.querySelector('.sidebar')?.classList.toggle('show');
        document.querySelector('.sidebar-overlay')?.classList.toggle('show');
    }
</script>