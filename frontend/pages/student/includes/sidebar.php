<?php
/**
 * Student Sidebar - Dark Mode
 */
?>
<nav class="sidebar" id="studentSidebar">
    <ul class="nav flex-column">
        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
        </li>
        
        <!-- My Profile -->
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                <i class="fas fa-user me-2"></i> My Profile
            </a>
        </li>
        
        <!-- Access History -->
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'access-history.php' ? 'active' : ''; ?>" href="access-history.php">
                <i class="fas fa-clock me-2"></i> Access History
            </a>
        </li>
        
        <!-- Announcements -->
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">
                <i class="fas fa-bullhorn me-2"></i> Announcements
            </a>
        </li>
        
        <!-- Concerns -->
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'concerns.php' ? 'active' : ''; ?>" href="concerns.php">
                <i class="fas fa-exclamation-circle me-2"></i> Concerns
            </a>
        </li>
        
        <!-- Reset Password -->
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'request-reset.php' ? 'active' : ''; ?>" href="request-reset.php">
                <i class="fas fa-key me-2"></i> Reset Password
            </a>
        </li>
    </ul>
    
    <hr style="border-color: #1e2a3a; margin: 10px 20px;">
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="small text-muted px-3">
            <i class="fas fa-user me-1"></i>
            <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
        </div>
        <div class="small text-muted px-3 mt-1">
            <i class="fas fa-graduation-cap me-1"></i>
            <?php echo htmlspecialchars($_SESSION['course'] ?? 'N/A'); ?>
            - <?php echo htmlspecialchars($_SESSION['year_level'] ?? 'N/A'); ?>
        </div>
        <div class="small text-muted px-3 mt-2 pt-2 border-top">
            <i class="fas fa-door-open me-1"></i>
            ISU-Echague Dormitory
        </div>
    </div>
</nav>

<style>
    /* ================================================================
       SIDEBAR - DARK MODE
       ================================================================ */
    .sidebar {
        position: fixed;
        top: 56px;
        bottom: 0;
        left: 0;
        z-index: 100;
        padding: 15px 0 0;
        background: #131926 !important;
        width: 260px;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        border-right: 1px solid #1e2a3a;
    }
    
    .sidebar .nav {
        flex: 1;
    }
    
    .sidebar .nav-link {
        color: #9ca3af !important;
        padding: 10px 16px;
        border-radius: 10px;
        margin: 2px 10px;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        text-decoration: none;
        cursor: pointer;
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
        width: 24px;
        text-align: center;
        margin-right: 10px;
        font-size: 16px;
    }
    
    .sidebar-footer {
        padding: 15px 15px 20px;
        border-top: 1px solid #1e2a3a;
        margin-top: auto;
        flex-shrink: 0;
    }
    
    .sidebar-footer .small {
        font-size: 12px;
        color: #6b7280 !important;
    }
    
    .sidebar-footer .border-top {
        border-color: #1e2a3a !important;
    }
    
    .sidebar-footer .text-muted {
        color: #6b7280 !important;
    }
    
    /* ================================================================
       SCROLLBAR
       ================================================================ */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    .sidebar::-webkit-scrollbar-track {
        background: #0a0e1a;
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: #1e2a3a;
        border-radius: 5px;
    }
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: #ffd700;
    }
    
    /* ================================================================
       MOBILE RESPONSIVE
       ================================================================ */
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
            background: rgba(0,0,0,0.5);
            z-index: 998;
        }
        .sidebar-overlay.show { display: block; }
    }
</style>