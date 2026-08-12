<?php
/**
 * Tap-and-Go Doorlock - Staff Navbar
 * Location: /frontend/staff/includes/navbar_staff.php
 */
?>
<nav class="navbar navbar-expand-lg fixed-top" style="background: linear-gradient(135deg, #0a1628, #1a2a4a); border-bottom: 1px solid #1e2a3a; height: 56px; padding: 0 20px; z-index: 1050;">
    <div class="container-fluid">
        <!-- Sidebar Toggle Button -->
        <button class="navbar-toggler me-2" type="button" onclick="toggleSidebar()" style="border-color: rgba(255,255,255,0.1);">
            <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 30 30\'%3e%3cpath stroke=\'rgba(255,255,255,0.8)\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' stroke-width=\'2\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3c/svg%3e');"></span>
        </button>
        
        <!-- Brand -->
        <a class="navbar-brand" href="dashboard.php" style="color: #ffd700 !important; font-weight: 700; font-size: 18px;">
            <i class="fas fa-door-open me-2"></i> Tap-and-Go
            <span class="badge bg-primary ms-2" style="font-size: 10px; background: rgba(59, 130, 246, 0.2) !important; color: #93c5fd !important;">Staff</span>
        </a>
        
        <!-- Navbar Toggle for Mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#staffNavbarNav" style="border-color: rgba(255,255,255,0.1);">
            <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 30 30\'%3e%3cpath stroke=\'rgba(255,255,255,0.8)\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' stroke-width=\'2\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3c/svg%3e');"></span>
        </button>
        
        <!-- Navbar Links -->
        <div class="collapse navbar-collapse" id="staffNavbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php" style="color: rgba(255,255,255,0.7) !important; font-size: 14px; padding: 8px 15px; border-radius: 8px; transition: all 0.3s ease;">
                        <i class="fas fa-home me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'visitors.php' ? 'active' : ''; ?>" href="visitors.php" style="color: rgba(255,255,255,0.7) !important; font-size: 14px; padding: 8px 15px; border-radius: 8px; transition: all 0.3s ease;">
                        <i class="fas fa-user-plus me-1"></i> Visitors
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>" href="logs.php" style="color: rgba(255,255,255,0.7) !important; font-size: 14px; padding: 8px 15px; border-radius: 8px; transition: all 0.3s ease;">
                        <i class="fas fa-history me-1"></i> Access Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'alerts.php' ? 'active' : ''; ?>" href="alerts.php" style="color: rgba(255,255,255,0.7) !important; font-size: 14px; padding: 8px 15px; border-radius: 8px; transition: all 0.3s ease;">
                        <i class="fas fa-bell me-1"></i> Alerts
                        <?php 
                            // Get pending alerts count
                            $pendingAlerts = 0;
                            try {
                                $conn = getDBConnection();
                                $result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
                                if ($result && $row = $result->fetch_assoc()) {
                                    $pendingAlerts = (int)$row['count'];
                                }
                            } catch (Exception $e) {}
                            if ($pendingAlerts > 0): 
                        ?>
                            <span class="badge bg-danger ms-1" style="font-size: 9px; animation: pulseBadge 1.5s infinite;"><?php echo $pendingAlerts; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>" href="announcements.php" style="color: rgba(255,255,255,0.7) !important; font-size: 14px; padding: 8px 15px; border-radius: 8px; transition: all 0.3s ease;">
                        <i class="fas fa-bullhorn me-1"></i> Announcements
                    </a>
                </li>
            </ul>
            
            <!-- Right Side - Staff Info & Logout -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="staffDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color: rgba(255,255,255,0.8) !important; font-size: 14px; padding: 8px 15px; border-radius: 8px;">
                        <i class="fas fa-user-tie me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?>
                        <span class="badge bg-primary ms-1" style="font-size: 9px; background: rgba(59, 130, 246, 0.2) !important; color: #93c5fd !important;">Staff</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="background: #131926 !important; border: 1px solid #1e2a3a; border-radius: 12px; padding: 8px 0; min-width: 220px;">
                        <li>
                            <div class="dropdown-item-text" style="color: #9ca3af; font-size: 12px; padding: 8px 20px; border-bottom: 1px solid #1e2a3a; margin-bottom: 4px;">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?>
                                <br>
                                <span style="font-size: 10px; color: #6b7280;">
                                    <?php echo htmlspecialchars($_SESSION['staff_id_number'] ?? 'N/A'); ?>
                                </span>
                            </div>
                        </li>
                        <li>
                            <a class="dropdown-item" href="profile.php" style="color: #e5e7eb !important; padding: 8px 20px; transition: all 0.3s ease;">
                                <i class="fas fa-user me-2"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="settings.php" style="color: #e5e7eb !important; padding: 8px 20px; transition: all 0.3s ease;">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider" style="border-color: #1e2a3a; margin: 4px 0;">
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php" style="color: #fca5a5 !important; padding: 8px 20px; transition: all 0.3s ease;">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
    /* ===== NAVBAR ACTIVE STYLES ===== */
    .navbar .nav-link.active {
        background: rgba(255, 215, 0, 0.15) !important;
        color: #ffd700 !important;
    }
    .navbar .nav-link:hover {
        background: rgba(255, 255, 255, 0.05) !important;
        color: white !important;
    }
    
    /* ===== DROPDOWN HOVER ===== */
    .dropdown-item:hover {
        background: rgba(255, 215, 0, 0.08) !important;
        color: #ffd700 !important;
    }
    .dropdown-item.text-danger:hover {
        background: rgba(239, 68, 68, 0.15) !important;
        color: #fca5a5 !important;
    }
    
    /* ===== PULSE ANIMATION ===== */
    @keyframes pulseBadge {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    
    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .navbar .nav-link {
            padding: 8px 12px !important;
            font-size: 13px !important;
        }
        .dropdown-menu {
            min-width: 180px !important;
        }
    }
</style>

<!-- ===== TOGGLE SIDEBAR FUNCTION ===== -->
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('staffSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
        if (overlay) {
            overlay.classList.toggle('show');
        }
    }

    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('staffSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggler = document.querySelector('.navbar-toggler');
        
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
            if (!sidebar.contains(event.target) && !toggler?.contains(event.target)) {
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }
        }
    });

    // Close sidebar on window resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            const sidebar = document.getElementById('staffSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
    });
</script>