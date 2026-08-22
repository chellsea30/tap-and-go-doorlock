<?php
/**
 * Admin Sidebar - Tap-and-Go Theme
 * Clean, modern design with icons and minimal styling
 */
?>

<style>
    /* ============================================================
       SIDEBAR - TAP-AND-GO THEME
       ============================================================ */
    .sidebar {
        background: #ffffff !important;
        border-right: 1px solid #e5e7eb !important;
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
    }
    
    /* Logo / Brand */
    .sidebar-brand {
        padding: 0 20px 20px 20px;
        border-bottom: 1px solid #f0f0f0;
        margin-bottom: 10px;
    }
    .sidebar-brand h5 {
        font-weight: 700;
        color: #1a1a2e;
        font-size: 18px;
        letter-spacing: -0.5px;
        margin: 0;
    }
    .sidebar-brand h5 small {
        font-weight: 400;
        color: #888;
        font-size: 12px;
        display: block;
        margin-top: 2px;
    }
    
    /* Navigation */
    .sidebar .nav {
        padding: 0 10px;
    }
    .sidebar .nav-item {
        margin-bottom: 2px;
    }
    .sidebar .nav-link {
        color: #4a4a5a !important;
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
    }
    .sidebar .nav-link:hover {
        background: #f5f7fa !important;
        color: #1a1a2e !important;
    }
    .sidebar .nav-link.active {
        background: #eef2ff !important;
        color: #4f46e5 !important;
        font-weight: 600;
    }
    .sidebar .nav-link i {
        width: 22px;
        text-align: center;
        color: #8a8a9a !important;
        margin-right: 12px;
        font-size: 16px;
    }
    .sidebar .nav-link.active i {
        color: #4f46e5 !important;
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
        color: #8a8a9a !important;
        margin-left: auto;
    }
    .sidebar .nav-link.active .fa-chevron-down {
        color: #4f46e5 !important;
    }
    .sidebar .nav-link[aria-expanded="true"] .fa-chevron-down {
        transform: rotate(180deg);
    }
    
    /* Submenu */
    .sidebar ul ul {
        padding-left: 0 !important;
        margin-left: 0 !important;
    }
    .sidebar ul ul .nav-link {
        font-size: 13px;
        padding: 7px 14px 7px 46px;
        margin: 0;
        color: #666 !important;
        font-weight: 400;
        border-radius: 8px;
    }
    .sidebar ul ul .nav-link:hover {
        background: #f8f9fb !important;
        color: #1a1a2e !important;
    }
    .sidebar ul ul .nav-link.active {
        background: #f0f3ff !important;
        color: #4f46e5 !important;
        font-weight: 500;
    }
    .sidebar ul ul .nav-link i {
        width: 18px;
        font-size: 13px;
        color: #aaa !important;
        margin-right: 10px;
    }
    .sidebar ul ul .nav-link.active i {
        color: #4f46e5 !important;
    }
    
    /* Sub-submenu */
    .sidebar ul ul ul .nav-link {
        font-size: 12px;
        padding: 5px 14px 5px 62px;
        color: #777 !important;
    }
    .sidebar ul ul ul .nav-link:hover {
        background: #f8f9fb !important;
        color: #333 !important;
    }
    .sidebar ul ul ul .nav-link.active {
        background: #f0f3ff !important;
        color: #4f46e5 !important;
    }
    
    /* Separator */
    .sidebar-divider {
        border-top: 1px solid #f0f0f0;
        margin: 10px 20px;
    }
    
    /* Sidebar Footer */
    .sidebar-footer {
        padding: 15px 20px 20px;
        border-top: 1px solid #f0f0f0;
        margin-top: auto;
    }
    .sidebar-footer .text-muted {
        color: #999 !important;
        font-size: 12px;
    }
    .sidebar-footer i {
        color: #bbb !important;
        width: 16px;
        text-align: center;
        margin-right: 6px;
    }
    .sidebar-footer .user-name {
        font-weight: 600;
        color: #1a1a2e !important;
        font-size: 13px;
    }
    .sidebar-footer .dorm-name {
        color: #888 !important;
        font-size: 11px;
        margin-top: 4px;
    }
    
    /* Badge Colors - Soft */
    .badge.bg-info { background: #e0f2fe !important; color: #0284c7 !important; }
    .badge.bg-success { background: #dcfce7 !important; color: #16a34a !important; }
    .badge.bg-primary { background: #dbeafe !important; color: #2563eb !important; }
    .badge.bg-danger { background: #fee2e2 !important; color: #dc2626 !important; }
    .badge.bg-warning { background: #fef3c7 !important; color: #d97706 !important; }
    .badge.bg-secondary { background: #f3f4f6 !important; color: #6b7280 !important; }
    
    /* Status indicators */
    .status-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }
    .status-dot.online { background: #22c55e; }
    .status-dot.offline { background: #ef4444; }
    
    /* Pulse for alerts */
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.7); }
    }
    .pulse-dot {
        animation: pulse-dot 1.5s infinite;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: -280px;
            width: 280px;
            transition: left 0.3s ease;
            z-index: 999;
            box-shadow: 4px 0 30px rgba(0,0,0,0.1) !important;
        }
        .sidebar.show { left: 0; }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 998;
        }
        .sidebar-overlay.show { display: block; }
    }
</style>

<nav class="sidebar">
    <div class="d-flex flex-column h-100">
        
        <!-- ===== BRAND / LOGO ===== -->
        <div class="sidebar-brand">
            <h5>
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
            
            <!-- ===== REPORTS ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
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
            
            <!-- ===== STAFF INFO ===== -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'staff-info.php' ? 'active' : ''; ?>" href="staff-info.php">
                    <i class="fas fa-user-tie"></i> Staff Info
                </a>
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
