<?php
/**
 * Tap-and-Go Doorlock - Staff Sidebar
 * VIEW ONLY - Same Design as Admin Sidebar
 * PURE DARK MODE - No white backgrounds
 * WITH VISITORS SUBMENU
 */
?>

<style>
    /* ============================================================
       SIDEBAR - DARK THEME (Same as Admin)
       ============================================================ */
    .sidebar {
        background: #0d1528 !important;
        border-right: 1px solid #1a2a4a !important;
        min-height: 100vh;
        box-shadow: 2px 0 15px rgba(0,0,0,0.3) !important;
        position: fixed;
        top: 56px;
        bottom: 0;
        left: 0;
        width: 260px;
        z-index: 100;
        padding: 15px 0 0;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }
    .sidebar .nav-link {
        color: #9090a0 !important;
        padding: 10px 16px;
        border-radius: 8px;
        margin: 2px 8px;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        text-decoration: none;
    }
    .sidebar .nav-link:hover {
        background: rgba(255,255,255,0.05) !important;
        color: #e0e0e0 !important;
    }
    .sidebar .nav-link.active {
        background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
        color: white !important;
        box-shadow: 0 4px 15px rgba(26,58,106,0.3) !important;
    }
    .sidebar .nav-link i {
        width: 20px;
        text-align: center;
        color: #606070 !important;
        margin-right: 10px;
    }
    .sidebar .nav-link.active i {
        color: white !important;
    }
    .sidebar .nav-link .badge {
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 20px;
        margin-left: auto;
    }
    .sidebar .nav-link .fa-chevron-down {
        font-size: 11px;
        opacity: 0.4;
        transition: transform 0.3s ease;
        color: #606070 !important;
        margin-left: auto;
    }
    .sidebar .nav-link.active .fa-chevron-down {
        color: white !important;
    }
    .sidebar .nav-link[aria-expanded="true"] .fa-chevron-down {
        transform: rotate(180deg);
    }
    .sidebar .nav-link.active .badge {
        background: rgba(255,255,255,0.2) !important;
        color: white !important;
    }
    
    /* Submenu */
    .sidebar ul ul .nav-link {
        font-size: 13px;
        padding: 6px 16px 6px 16px;
        margin: 1px 8px;
        color: #707080 !important;
    }
    .sidebar ul ul .nav-link:hover {
        background: rgba(255,255,255,0.03) !important;
        color: #b0b0c0 !important;
    }
    .sidebar ul ul .nav-link.active {
        background: rgba(255,255,255,0.05) !important;
        color: #93c5fd !important;
        box-shadow: none !important;
    }
    .sidebar ul ul .nav-link i {
        color: #505060 !important;
    }
    .sidebar ul ul .nav-link.active i {
        color: #93c5fd !important;
    }
    
    /* Sub-submenu */
    .sidebar ul ul ul .nav-link {
        font-size: 12px;
        padding: 4px 16px 4px 30px;
        color: #606070 !important;
    }
    .sidebar ul ul ul .nav-link:hover {
        background: rgba(255,255,255,0.02) !important;
        color: #9090a0 !important;
    }
    .sidebar ul ul ul .nav-link.active {
        background: rgba(255,255,255,0.03) !important;
        color: #93c5fd !important;
    }
    
    /* Sidebar Footer */
    .sidebar-footer {
        padding: 10px 0 20px 0;
        border-top: 1px solid #1a2a4a !important;
        margin-top: auto;
    }
    .sidebar-footer .border-top {
        border-color: #1a2a4a !important;
    }
    .sidebar-footer .text-muted {
        color: #606070 !important;
    }
    .sidebar-footer i {
        color: #505060 !important;
    }
    
    /* View Only Badge */
    .view-only-badge-sidebar {
        background: #4a3a1a !important;
        color: #fbbf24 !important;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 500;
        margin-left: 8px;
    }
    .nav-link.active .view-only-badge-sidebar {
        background: rgba(255,215,0,0.25) !important;
        color: #ffd700 !important;
    }
    
    /* Badge Colors */
    .badge.bg-info { background: #1a3a6a !important; color: #93c5fd !important; }
    .badge.bg-success { background: #065f46 !important; color: #34d399 !important; }
    .badge.bg-primary { background: #1a3a6a !important; color: #93c5fd !important; }
    .badge.bg-danger { background: #7a2a2a !important; color: #f87171 !important; }
    .badge.bg-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
    .badge.bg-secondary { background: #1a2a4a !important; color: #808090 !important; }
    
    /* Responsive */
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
    
    /* Pulse Animation */
    @keyframes pulse-red {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.3; transform: scale(0.8); }
        100% { opacity: 1; transform: scale(1); }
    }
</style>

<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="staffSidebar">
    <div class="position-sticky pt-3">
        
        <!-- ===== STAFF INFO AT TOP ===== -->
        <div class="px-3 mb-3 pb-2 border-bottom" style="border-color: #1a2a4a !important;">
            <div class="d-flex align-items-center gap-2">
                <div class="user-avatar-sidebar" style="width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#1a3a6a,#2a5a9a); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:14px; flex-shrink:0;">
                    <?php 
                        $name = $_SESSION['full_name'] ?? 'Staff';
                        $initials = '';
                        $parts = explode(' ', $name);
                        foreach ($parts as $p) {
                            if (!empty($p)) $initials .= strtoupper($p[0]);
                        }
                        echo substr($initials, 0, 2) ?: 'S';
                    ?>
                </div>
                <div>
                    <div style="color:#e0e0e0; font-weight:600; font-size:14px;">
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?>
                    </div>
                    <div style="color:#606070; font-size:11px;">
                        <i class="fas fa-eye me-1"></i> View Only
                    </div>
                </div>
            </div>
        </div>
        
        <ul class="nav flex-column">
            
            <!-- ===== DASHBOARD ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-home me-2"></i> Dashboard
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(DISTINCT room_number) as count FROM users WHERE room_number IS NOT NULL AND room_number != '' AND status = 'active'");
                        $row = $result->fetch_assoc();
                        $roomsUsed = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-info rounded-pill ms-2"><?php echo $roomsUsed; ?>/5</span>
                </a>
            </li>
            
            <!-- ===== RESIDENTS (VIEW ONLY) ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'residents.php' ? 'active' : ''; ?>" href="residents.php">
                    <i class="fas fa-users me-2"></i> Residents
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
                        $row = $result->fetch_assoc();
                        $residentCount = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-primary rounded-pill ms-2"><?php echo $residentCount; ?></span>
                </a>
            </li>
            
            <!-- ===== RFID CARDS (VIEW ONLY) ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cards.php' ? 'active' : ''; ?>" href="cards.php">
                    <i class="fas fa-id-card me-2"></i> RFID Cards
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'active'");
                        $row = $result->fetch_assoc();
                        $activeCards = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-success rounded-pill ms-2"><?php echo $activeCards; ?></span>
                </a>
            </li>
            
            <!-- ===== ACCESS LOGS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>" href="logs.php">
                    <i class="fas fa-history me-2"></i> Access Logs
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE DATE(timestamp) = CURDATE()");
                        $row = $result->fetch_assoc();
                        $todayAccess = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-primary rounded-pill ms-2"><?php echo $todayAccess; ?></span>
                </a>
            </li>
            
            <!-- ===== ALERTS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'alerts.php' ? 'active' : ''; ?>" href="alerts.php">
                    <i class="fas fa-bell me-2"></i> Alerts
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
                        $row = $result->fetch_assoc();
                        $pendingAlerts = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-danger rounded-pill ms-2" id="alertCount">
                        <?php echo $pendingAlerts; ?>
                    </span>
                    <?php if ($pendingAlerts > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1" style="animation: pulse-red 1s infinite;">
                            <i class="fas fa-circle"></i>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- ===== VISITORS WITH SUBMENU (VIEW ONLY) ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['visitors.php', 'register-visitor.php', 'visitor-logs.php']) ? 'active' : ''; ?>" 
                   href="#visitorsMenu" 
                   data-bs-toggle="collapse" 
                   role="button" 
                   aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['visitors.php', 'register-visitor.php', 'visitor-logs.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-user-plus me-2"></i> Visitors
                    <i class="fas fa-chevron-down float-end mt-1"></i>
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(entry_timestamp) = CURDATE()");
                        $row = $result->fetch_assoc();
                        $todayVisitors = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-warning rounded-pill ms-2"><?php echo $todayVisitors; ?></span>
                </a>
                <ul class="nav flex-column ms-3 collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['visitors.php', 'register-visitor.php', 'visitor-logs.php']) ? 'show' : ''; ?>" id="visitorsMenu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'visitors.php' ? 'active' : ''; ?>" href="visitors.php">
                            <i class="fas fa-list me-2"></i> All Visitors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'register-visitor.php' ? 'active' : ''; ?>" href="register-visitor.php">
                            <i class="fas fa-user-plus me-2"></i> + New Visitor
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'visitor-logs.php' ? 'active' : ''; ?>" href="visitor-logs.php">
                            <i class="fas fa-clock me-2"></i> Visitor Logs
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- ===== REPORTS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i> Reports
                </a>
            </li>
            
            <!-- ===== ANNOUNCEMENTS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">
                    <i class="fas fa-bullhorn me-2"></i> Announcements
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
                        $row = $result->fetch_assoc();
                        $announcementCount = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-warning rounded-pill ms-2"><?php echo $announcementCount; ?></span>
                </a>
            </li>
            
            <!-- ===== MY PROFILE ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user me-2"></i> My Profile
                </a>
            </li>
            
        </ul>
        
        <hr>
        
        <!-- ===== SIDEBAR FOOTER ===== -->
        <div class="sidebar-footer">
            <div class="small text-muted px-3">
                <i class="fas fa-eye me-1"></i> View Only Access
            </div>
            <div class="small text-muted px-3 mt-1">
                <i class="fas fa-server me-1"></i> 
                <span id="serverStatus">Connected</span>
            </div>
            <div class="small text-muted px-3 mt-1">
                <i class="fas fa-bolt me-1"></i>
                <span id="powerStatus">Main Power</span>
            </div>
            <div class="small text-muted px-3 mt-1">
                <i class="fas fa-user me-1"></i>
                <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?></span>
            </div>
            <div class="small text-muted px-3 mt-2 pt-2 border-top">
                <i class="fas fa-door-open me-1"></i>
                <span>ISU-Echague Dormitory</span>
            </div>
        </div>
    </div>
</nav>
