<?php
/**
 * Admin Sidebar - Tap-and-Go Theme
 * DARK MODE - Desktop and Mobile
 * WITH RESIDENTS REPORT - PERMANENT STORAGE (NO DELETE)
 * WITH CHART REPORT - PIE CHART FOR COURSE & YEAR LEVEL
 */
?>

<style>
    /* ============================================================
       SIDEBAR - DARK THEME (Desktop & Mobile)
       ============================================================ */
    .sidebar {
        background: #0d1528 !important;
        border-right: 1px solid #1a2a4a !important;
        min-height: 100vh;
        box-shadow: none !important;
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        width: 240px;
        z-index: 100;
        padding: 20px 0 0;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        transition: left 0.3s ease;
    }

    /* Logo / Brand - Dark */
    .sidebar-brand {
        padding: 0 20px 20px 20px;
        border-bottom: 1px solid #1a2a4a !important;
        margin-bottom: 10px;
    }
    .sidebar-brand h5 {
        font-weight: 700;
        color: #e0e0e0 !important;
        font-size: 18px;
        letter-spacing: -0.5px;
        margin: 0;
    }
    .sidebar-brand h5 small {
        font-weight: 400;
        color: #808090 !important;
        font-size: 12px;
        display: block;
        margin-top: 2px;
    }

    /* Navigation - Dark */
    .sidebar .nav {
        padding: 0 10px;
        flex: 1;
    }
    .sidebar .nav-item {
        margin-bottom: 2px;
    }
    .sidebar .nav-link {
        color: #9090a0 !important;
        padding: 10px 14px;
        border-radius: 10px;
        margin: 0;
        transition: all 0.2s ease;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        text-decoration: none;
        position: relative;
        cursor: pointer;
    }
    .sidebar .nav-link:hover {
        background: rgba(255,255,255,0.05) !important;
        color: #e0e0e0 !important;
    }
    .sidebar .nav-link.active {
        background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
        color: white !important;
        font-weight: 600;
    }
    .sidebar .nav-link i {
        width: 22px;
        text-align: center;
        color: #606070 !important;
        margin-right: 12px;
        font-size: 16px;
    }
    .sidebar .nav-link.active i {
        color: white !important;
    }
    .sidebar .nav-link .badge {
        font-size: 11px;
        padding: 2px 10px;
        border-radius: 20px;
        margin-left: auto;
        font-weight: 500;
    }
    .sidebar .nav-link .fa-chevron-down {
        font-size: 10px;
        opacity: 0.5;
        transition: transform 0.25s ease;
        color: #606070 !important;
        margin-left: auto;
    }
    .sidebar .nav-link.active .fa-chevron-down {
        color: white !important;
    }
    .sidebar .nav-link[aria-expanded="true"] .fa-chevron-down {
        transform: rotate(180deg);
    }

    /* Submenu - Dark */
    .sidebar ul ul {
        padding-left: 0 !important;
        margin-left: 0 !important;
        list-style: none;
    }
    .sidebar ul ul .nav-link {
        font-size: 13px;
        padding: 7px 14px 7px 46px;
        margin: 0;
        color: #808090 !important;
        font-weight: 400;
        border-radius: 8px;
    }
    .sidebar ul ul .nav-link:hover {
        background: rgba(255,255,255,0.03) !important;
        color: #e0e0e0 !important;
    }
    .sidebar ul ul .nav-link.active {
        background: rgba(26, 58, 106, 0.3) !important;
        color: #93c5fd !important;
        font-weight: 500;
    }
    .sidebar ul ul .nav-link i {
        width: 18px;
        font-size: 13px;
        color: #606070 !important;
        margin-right: 10px;
    }
    .sidebar ul ul .nav-link.active i {
        color: #93c5fd !important;
    }

    /* Sub-submenu - Dark */
    .sidebar ul ul ul .nav-link {
        font-size: 12px;
        padding: 5px 14px 5px 62px;
        color: #606070 !important;
    }
    .sidebar ul ul ul .nav-link:hover {
        background: rgba(255,255,255,0.03) !important;
        color: #e0e0e0 !important;
    }
    .sidebar ul ul ul .nav-link.active {
        background: rgba(26, 58, 106, 0.3) !important;
        color: #93c5fd !important;
    }

    /* Separator - Dark */
    .sidebar-divider {
        border-top: 1px solid #1a2a4a !important;
        margin: 10px 20px;
    }

    /* Sidebar Footer - Dark */
    .sidebar-footer {
        padding: 15px 20px 20px;
        border-top: 1px solid #1a2a4a !important;
        margin-top: auto;
    }
    .sidebar-footer .text-muted {
        color: #606070 !important;
        font-size: 12px;
    }
    .sidebar-footer i {
        color: #606070 !important;
        width: 16px;
        text-align: center;
        margin-right: 6px;
    }
    .sidebar-footer .user-name {
        font-weight: 600;
        color: #e0e0e0 !important;
        font-size: 13px;
    }
    .sidebar-footer .dorm-name {
        color: #606070 !important;
        font-size: 11px;
        margin-top: 4px;
    }

    /* Badge Colors - Dark */
    .badge.bg-info { background: #1a3a6a !important; color: #93c5fd !important; }
    .badge.bg-success { background: #065f46 !important; color: #34d399 !important; }
    .badge.bg-primary { background: #1a3a6a !important; color: #93c5fd !important; }
    .badge.bg-danger { background: #7a2a2a !important; color: #f87171 !important; }
    .badge.bg-warning { background: #4a3a1a !important; color: #fbbf24 !important; }
    .badge.bg-secondary { background: #1a2a4a !important; color: #808090 !important; }

    /* Status indicators */
    .status-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }
    .status-dot.online { background: #34d399; }
    .status-dot.offline { background: #f87171; }

    /* Pulse for alerts */
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.7); }
    }
    .pulse-dot {
        animation: pulse-dot 1.5s infinite;
    }

    /* ============================================================
       MOBILE FIX - SIDEBAR TOGGLE
       ============================================================ */
    
    /* Sidebar Overlay - for mobile */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 99;
        backdrop-filter: blur(4px);
        transition: opacity 0.3s ease;
    }
    .sidebar-overlay.show {
        display: block;
    }

    /* Mobile Toggle Button */
    .sidebar-toggle-btn {
        display: none;
        background: transparent;
        border: none;
        color: #e0e0e0;
        font-size: 24px;
        padding: 8px 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        border-radius: 8px;
    }
    .sidebar-toggle-btn:hover {
        background: rgba(255,255,255,0.05);
        color: #ffd700;
    }
    .sidebar-toggle-btn:focus {
        outline: none;
    }

    /* ============================================================
       RESPONSIVE - MOBILE
       ============================================================ */
    @media (max-width: 768px) {
        /* Show toggle button */
        .sidebar-toggle-btn {
            display: inline-block;
        }

        /* Sidebar - hidden by default on mobile */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: -280px;
            width: 280px;
            padding-top: 60px;
            box-shadow: 4px 0 30px rgba(0,0,0,0.5) !important;
            z-index: 100;
            transition: left 0.3s ease;
        }
        .sidebar.show {
            left: 0;
        }

        /* Overlay show */
        .sidebar-overlay.show {
            display: block;
        }

        /* Main content full width */
        .main-content {
            margin-left: 0 !important;
            padding: 10px !important;
        }

        /* Smaller text */
        .sidebar .nav-link {
            font-size: 13px;
            padding: 10px 14px;
        }
        .sidebar .nav-link i {
            font-size: 15px;
            margin-right: 10px;
        }
        .sidebar-brand h5 {
            font-size: 16px;
        }
        .sidebar-brand h5 small {
            font-size: 11px;
        }

        /* Submenu */
        .sidebar ul ul .nav-link {
            font-size: 12px;
            padding: 7px 14px 7px 42px;
        }
        .sidebar ul ul ul .nav-link {
            font-size: 11px;
            padding: 5px 14px 5px 58px;
        }

        /* Footer */
        .sidebar-footer .user-name {
            font-size: 12px;
        }
        .sidebar-footer .dorm-name {
            font-size: 10px;
        }
    }

    @media (max-width: 480px) {
        .sidebar {
            width: 260px;
            left: -260px;
        }
        .sidebar.show {
            left: 0;
        }
        .sidebar .nav-link {
            font-size: 12px;
            padding: 8px 12px;
        }
        .sidebar-brand h5 {
            font-size: 14px;
        }
        .sidebar-brand h5 small {
            font-size: 10px;
        }
        .sidebar ul ul .nav-link {
            font-size: 11px;
            padding: 6px 14px 6px 38px;
        }
        .sidebar ul ul ul .nav-link {
            font-size: 10px;
            padding: 4px 14px 4px 52px;
        }
    }

    /* ============================================================
       SCROLLBAR - DARK THEME
       ============================================================ */
    .sidebar::-webkit-scrollbar {
        width: 4px;
    }
    .sidebar::-webkit-scrollbar-track {
        background: #0d1528;
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: #1a2a4a;
        border-radius: 4px;
    }
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: #2a5a9a;
    }
</style>

<!-- ============================================================
SIDEBAR OVERLAY - for mobile close
============================================================ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<nav class="sidebar" id="mainSidebar">
    <div class="d-flex flex-column h-100">
        
        <!-- ===== BRAND / LOGO ===== -->
        <div class="sidebar-brand">
            <h5>
                <i class="fas fa-door-open" style="color: #ffd700; margin-right: 8px;"></i>
                Tap-and-Go
                <small>ISU-Echague Dormitory</small>
            </h5>
        </div>
        
        <!-- ===== NAVIGATION ===== -->
        <ul class="nav flex-column">
            
            <!-- ===== DASHBOARD ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(DISTINCT room_number) as count FROM users WHERE room_number IS NOT NULL AND room_number != '' AND status = 'active'");
                        $row = $result->fetch_assoc();
                        $roomsUsed = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-info rounded-pill"><?php echo $roomsUsed; ?>/5</span>
                </a>
            </li>
            
            <!-- ===== RESIDENTS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['residents.php', 'new-resident.php', 'admission-form.php', 'student-registration.php', 'room-assign.php']) ? 'active' : ''; ?>" 
                   href="#residentsMenu" 
                   data-bs-toggle="collapse" 
                   role="button" 
                   aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['residents.php', 'new-resident.php', 'admission-form.php', 'student-registration.php', 'room-assign.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-users"></i> Residents
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="nav flex-column collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['residents.php', 'new-resident.php', 'admission-form.php', 'student-registration.php', 'room-assign.php']) ? 'show' : ''; ?>" id="residentsMenu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'residents.php' ? 'active' : ''; ?>" href="residents.php">
                            <i class="fas fa-list"></i> Resident List
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'new-resident.php' ? 'active' : ''; ?>" href="new-resident.php">
                            <i class="fas fa-user-plus"></i> New Resident
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admission-form.php' ? 'active' : ''; ?>" href="admission-form.php">
                            <i class="fas fa-clipboard-list"></i> Admission Form
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'student-registration.php' ? 'active' : ''; ?>" href="student-registration.php">
                            <i class="fas fa-user-graduate"></i> Student Portal
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'room-assign.php' ? 'active' : ''; ?>" href="room-assign.php">
                            <i class="fas fa-bed"></i> Room Assign
                            <span class="badge bg-warning rounded-pill" id="roomCount">0</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- ===== RFID CARDS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['cards.php', 'register-rfid.php', 'available-cards.php']) ? 'active' : ''; ?>" 
                   href="#rfidMenu" 
                   data-bs-toggle="collapse" 
                   role="button" 
                   aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['cards.php', 'register-rfid.php', 'available-cards.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-id-card"></i> RFID Cards
                    <i class="fas fa-chevron-down"></i>
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'active'");
                        $row = $result->fetch_assoc();
                        $activeCards = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-success rounded-pill"><?php echo $activeCards; ?></span>
                </a>
                <ul class="nav flex-column collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['cards.php', 'register-rfid.php', 'available-cards.php']) ? 'show' : ''; ?>" id="rfidMenu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cards.php' ? 'active' : ''; ?>" href="cards.php">
                            <i class="fas fa-list"></i> Card List
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'register-rfid.php' ? 'active' : ''; ?>" href="register-rfid.php">
                            <i class="fas fa-plus-circle"></i> Register RFID
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'available-cards.php' ? 'active' : ''; ?>" href="available-cards.php">
                            <i class="fas fa-boxes"></i> Available Cards
                            <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM available_rfid_cards WHERE status = 'available'");
                                $row = $result->fetch_assoc();
                                $availCount = $row['count'] ?? 0;
                            ?>
                            <span class="badge bg-warning rounded-pill"><?php echo $availCount; ?></span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- ===== ACCESS LOGS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>" href="logs.php">
                    <i class="fas fa-history"></i> Access Logs
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM access_logs WHERE DATE(timestamp) = CURDATE()");
                        $row = $result->fetch_assoc();
                        $todayAccess = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-primary rounded-pill"><?php echo $todayAccess; ?></span>
                </a>
            </li>
            
            <!-- ===== ALERTS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'alerts.php' ? 'active' : ''; ?>" href="alerts.php">
                    <i class="fas fa-bell"></i> Alerts
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM alert_logs WHERE delivery_status = 'pending'");
                        $row = $result->fetch_assoc();
                        $pendingAlerts = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-danger rounded-pill" id="alertCount">
                        <?php echo $pendingAlerts; ?>
                    </span>
                </a>
            </li>
            
            <!-- ===== VISITORS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['visitors.php', 'register-visitor.php', 'visitor-logs.php']) ? 'active' : ''; ?>" 
                   href="#visitorsMenu" 
                   data-bs-toggle="collapse" 
                   role="button" 
                   aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['visitors.php', 'register-visitor.php', 'visitor-logs.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-user-plus"></i> Visitors
                    <i class="fas fa-chevron-down"></i>
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM visitor_logs WHERE DATE(entry_timestamp) = CURDATE()");
                        $row = $result->fetch_assoc();
                        $todayVisitors = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-warning rounded-pill"><?php echo $todayVisitors; ?></span>
                </a>
                <ul class="nav flex-column collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['visitors.php', 'register-visitor.php', 'visitor-logs.php']) ? 'show' : ''; ?>" id="visitorsMenu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'visitors.php' ? 'active' : ''; ?>" href="visitors.php">
                            <i class="fas fa-list"></i> All Visitors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'register-visitor.php' ? 'active' : ''; ?>" href="register-visitor.php">
                            <i class="fas fa-user-plus"></i> New Visitor
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'visitor-logs.php' ? 'active' : ''; ?>" href="visitor-logs.php">
                            <i class="fas fa-clock"></i> Visitor Logs
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- ===== REPORTS with SUBMENU - WITH CHART REPORT ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['reports.php', 'resident-reports.php', 'residents-report.php', 'access-reports.php', 'visitor-reports.php', 'chart-report.php']) ? 'active' : ''; ?>" 
                   href="#reportsMenu" 
                   data-bs-toggle="collapse" 
                   role="button" 
                   aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['reports.php', 'resident-reports.php', 'residents-report.php', 'access-reports.php', 'visitor-reports.php', 'chart-report.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-chart-bar"></i> Reports
                    <i class="fas fa-chevron-down"></i>
                    <?php
                        // Get total residents count for badge
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' OR status = 'inactive'");
                        $row = $result->fetch_assoc();
                        $totalResidents = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-info rounded-pill"><?php echo $totalResidents; ?></span>
                </a>
                <ul class="nav flex-column collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['reports.php', 'resident-reports.php', 'residents-report.php', 'access-reports.php', 'visitor-reports.php', 'chart-report.php']) ? 'show' : ''; ?>" id="reportsMenu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                            <i class="fas fa-chart-pie"></i> Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'resident-reports.php' || basename($_SERVER['PHP_SELF']) == 'residents-report.php' ? 'active' : ''; ?>" href="residents-report.php">
                            <i class="fas fa-users"></i> Residents Report
                            <?php if ($totalResidents > 0): ?>
                                <span class="badge bg-success rounded-pill"><?php echo $totalResidents; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'access-reports.php' ? 'active' : ''; ?>" href="access-reports.php">
                            <i class="fas fa-door-open"></i> Access Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'visitor-reports.php' ? 'active' : ''; ?>" href="visitor-reports.php">
                            <i class="fas fa-user-friends"></i> Visitor Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'chart-report.php' ? 'active' : ''; ?>" href="chart-report.php">
                            <i class="fas fa-chart-pie"></i> Chart Report
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- ===== ANNOUNCEMENTS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">
                    <i class="fas fa-bullhorn"></i> Announcements
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
                        $row = $result->fetch_assoc();
                        $announcementCount = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-warning rounded-pill"><?php echo $announcementCount; ?></span>
                </a>
            </li>
            
            <!-- ===== STAFF ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['staff-info.php', 'staff-card.php', 'staff-logs.php']) ? 'active' : ''; ?>" 
                   href="#staffMenu" 
                   data-bs-toggle="collapse" 
                   role="button" 
                   aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['staff-info.php', 'staff-card.php', 'staff-logs.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-user-tie"></i> Staff
                    <i class="fas fa-chevron-down"></i>
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM staff_users WHERE is_active = 1");
                        $row = $result->fetch_assoc();
                        $staffCount = $row['count'] ?? 0;
                    ?>
                    <span class="badge bg-info rounded-pill"><?php echo $staffCount; ?></span>
                </a>
                <ul class="nav flex-column collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['staff-info.php', 'staff-card.php', 'staff-logs.php']) ? 'show' : ''; ?>" id="staffMenu">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'staff-info.php' ? 'active' : ''; ?>" href="staff-info.php">
                            <i class="fas fa-address-card"></i> Staff Info
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'staff-card.php' ? 'active' : ''; ?>" href="staff-card.php">
                            <i class="fas fa-id-card"></i> Staff Card
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'staff-logs.php' ? 'active' : ''; ?>" href="staff-logs.php">
                            <i class="fas fa-clock"></i> Access Logs
                            <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM staff_logs WHERE DATE(timestamp) = CURDATE()");
                                $row = $result->fetch_assoc();
                                $todayStaffLogs = $row['count'] ?? 0;
                            ?>
                            <span class="badge bg-primary rounded-pill"><?php echo $todayStaffLogs; ?></span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- ===== OTHERS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['concerns-management.php', 'emails.php', 'email-staff.php', 'email-residents.php', 'request-reset-pass.php', 'settings.php']) ? 'active' : ''; ?>" 
                   href="#othersMenu" 
                   data-bs-toggle="collapse" 
                   role="button" 
                   aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['concerns-management.php', 'emails.php', 'email-staff.php', 'email-residents.php', 'request-reset-pass.php', 'settings.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-ellipsis-h"></i> Others
                    <i class="fas fa-chevron-down"></i>
                    <?php
                        $conn = getDBConnection();
                        $result = $conn->query("SELECT COUNT(*) as count FROM student_concerns WHERE status = 'pending'");
                        $row = $result->fetch_assoc();
                        $pendingConcerns = $row['count'] ?? 0;
                        
                        $result = $conn->query("SELECT COUNT(*) as count FROM password_reset_requests WHERE status = 'pending'");
                        $row = $result->fetch_assoc();
                        $pendingResets = $row['count'] ?? 0;
                        
                        $totalOthersBadge = $pendingConcerns + $pendingResets;
                    ?>
                    <?php if ($totalOthersBadge > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $totalOthersBadge; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="nav flex-column collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['concerns-management.php', 'emails.php', 'email-staff.php', 'email-residents.php', 'request-reset-pass.php', 'settings.php']) ? 'show' : ''; ?>" id="othersMenu">
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'concerns-management.php' ? 'active' : ''; ?>" href="concerns-management.php">
                            <i class="fas fa-exclamation-circle"></i> Concerns
                            <?php if ($pendingConcerns > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?php echo $pendingConcerns; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['emails.php', 'email-staff.php', 'email-residents.php']) ? 'active' : ''; ?>" 
                           href="#emailsSubMenu" 
                           data-bs-toggle="collapse" 
                           role="button" 
                           aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['emails.php', 'email-staff.php', 'email-residents.php']) ? 'true' : 'false'; ?>">
                            <i class="fas fa-envelope"></i> Emails
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="nav flex-column collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['emails.php', 'email-staff.php', 'email-residents.php']) ? 'show' : ''; ?>" id="emailsSubMenu">
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'emails.php' ? 'active' : ''; ?>" href="emails.php">
                                    <i class="fas fa-inbox"></i> All Emails
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'email-staff.php' ? 'active' : ''; ?>" href="email-staff.php">
                                    <i class="fas fa-user-tie"></i> Staff
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'email-residents.php' ? 'active' : ''; ?>" href="email-residents.php">
                                    <i class="fas fa-users"></i> Residents
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'request-reset-pass.php' ? 'active' : ''; ?>" href="request-reset-pass.php">
                            <i class="fas fa-key"></i> Reset Requests
                            <?php if ($pendingResets > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?php echo $pendingResets; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                </ul>
            </li>
            
        </ul>
        
        <!-- ===== SIDEBAR FOOTER ===== -->
        <div class="sidebar-footer mt-auto">
            <div class="text-muted">
                <i class="fas fa-server"></i> 
                <span class="status-dot online"></span>
                <span id="serverStatus">Connected</span>
            </div>
            <div class="text-muted mt-1">
                <i class="fas fa-bolt"></i> 
                <span id="powerStatus">Main Power</span>
            </div>
            <div class="user-name mt-2">
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
            </div>
            <div class="dorm-name">
                <i class="fas fa-door-open"></i>
                ISU-Echague Dormitory
            </div>
        </div>
        
    </div>
</nav>

<!-- ============================================================
JAVASCRIPT - SIDEBAR TOGGLE
============================================================ -->
<script>
    // ============================================================
    // SIDEBAR TOGGLE FUNCTIONS
    // ============================================================
    function toggleSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
        if (overlay) {
            overlay.classList.toggle('show');
        }
        
        // Prevent body scroll when sidebar is open
        if (sidebar && sidebar.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    function closeSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar) {
            sidebar.classList.remove('show');
        }
        if (overlay) {
            overlay.classList.remove('show');
        }
        document.body.style.overflow = '';
    }

    // Close sidebar when window resizes to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    // Close sidebar when ESC key is pressed
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    // Close sidebar when clicking a link (auto-close on mobile)
    document.addEventListener('DOMContentLoaded', function() {
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                // Only close if it's not a dropdown toggle
                if (!this.hasAttribute('data-bs-toggle')) {
                    // Check if sidebar is open on mobile
                    const sidebar = document.getElementById('mainSidebar');
                    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
                        // Delay close to allow navigation
                        setTimeout(function() {
                            closeSidebar();
                        }, 300);
                    }
                }
            });
        });
    });

    console.log('✅ Dark Sidebar loaded with mobile toggle support');
</script>
