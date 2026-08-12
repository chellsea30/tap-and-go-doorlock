<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] == 'student') ? 'student-dashboard.php' : 'dashboard.php'; ?>">
            <i class="fas fa-door-open me-2"></i> Tap-and-Go
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'student'): ?>
                <span class="badge bg-success ms-2" style="font-size:10px;">Student</span>
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] == 'administrator'): ?>
                <span class="badge bg-danger ms-2" style="font-size:10px;">Admin</span>
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] == 'staff'): ?>
                <span class="badge bg-primary ms-2" style="font-size:10px;">Staff</span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                       href="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] == 'student') ? 'student-dashboard.php' : 'dashboard.php'; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] != 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['residents.php', 'new-resident.php', 'admission-form.php']) ? 'active' : ''; ?>" href="residents.php">
                        <i class="fas fa-users"></i> Residents
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cards.php' ? 'active' : ''; ?>" href="cards.php">
                        <i class="fas fa-id-card"></i> Cards
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'alerts.php' ? 'active' : ''; ?>" href="alerts.php">
                        <i class="fas fa-bell"></i> Alerts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'visitors.php' ? 'active' : ''; ?>" href="visitors.php">
                        <i class="fas fa-user-plus"></i> Visitors
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'administrator'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <!-- User Dropdown -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['student_name'] ?? 'User'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'student'): ?>
                            <li><a class="dropdown-item" href="student-profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="student-logs.php"><i class="fas fa-history me-2"></i> My Logs</a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=change-password"><i class="fas fa-key me-2"></i> Change Password</a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=dark-mode"><i class="fas fa-moon me-2"></i> Dark Mode</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="../../logout.php" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ===== LOGOUT CONFIRMATION MODAL ===== -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sign-out-alt me-2" style="color: #ef4444;"></i>
                    Confirm Logout
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-question-circle fa-4x mb-3" style="color: #f59e0b;"></i>
                <h5 class="mb-2">Are you sure you want to logout?</h5>
                <p class="text-muted mb-0">You will be redirected to the login page.</p>
                <div class="mt-3">
                    <span class="badge bg-secondary">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['student_name'] ?? 'User'); ?>
                    </span>
                    <span class="badge <?php echo isset($_SESSION['role']) && $_SESSION['role'] == 'student' ? 'bg-success' : (isset($_SESSION['role']) && $_SESSION['role'] == 'administrator' ? 'bg-danger' : 'bg-primary'); ?> ms-1">
                        <?php echo ucfirst($_SESSION['role'] ?? 'User'); ?>
                    </span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <a href="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] == 'student') ? '../student-logout.php' : '../logout.php'; ?>" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt me-1"></i> Yes, Logout
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* ===== LOGOUT MODAL DARK MODE COMPATIBILITY ===== */
    .modal-content {
        border-radius: 16px;
        border: none;
    }
    .modal-header {
        border-bottom: 1px solid #e5e7eb;
        padding: 20px 25px;
    }
    .modal-footer {
        border-top: 1px solid #e5e7eb;
        padding: 15px 25px;
    }
    .modal-body h5 {
        color: #1a1a2e;
    }
    .modal-body .text-muted {
        color: #6b7280;
    }
    .btn-secondary {
        background: #e5e7eb;
        border: none;
        color: #4b5563;
        padding: 8px 25px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-secondary:hover {
        background: #d1d5db;
        color: #1a1a2e;
    }
    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border: none;
        padding: 8px 25px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
    }
    
    /* ===== DARK MODE STYLES ===== */
    body.dark-mode .modal-content {
        background: #131926 !important;
        border: 1px solid #1e2a3a;
    }
    body.dark-mode .modal-header {
        border-bottom: 1px solid #1e2a3a;
    }
    body.dark-mode .modal-footer {
        border-top: 1px solid #1e2a3a;
    }
    body.dark-mode .modal-title {
        color: #ffd700 !important;
    }
    body.dark-mode .modal-body h5 {
        color: #e5e7eb !important;
    }
    body.dark-mode .modal-body .text-muted {
        color: #6b7280 !important;
    }
    body.dark-mode .modal-body .badge.bg-secondary {
        background: rgba(107, 114, 128, 0.3) !important;
        color: #9ca3af !important;
    }
    body.dark-mode .modal-body .badge.bg-success {
        background: rgba(16, 185, 129, 0.2) !important;
        color: #6ee7b7 !important;
    }
    body.dark-mode .modal-body .badge.bg-danger {
        background: rgba(239, 68, 68, 0.2) !important;
        color: #fca5a5 !important;
    }
    body.dark-mode .modal-body .badge.bg-primary {
        background: rgba(59, 130, 246, 0.2) !important;
        color: #93c5fd !important;
    }
    body.dark-mode .btn-secondary {
        background: #1e2a3a !important;
        color: #e5e7eb !important;
    }
    body.dark-mode .btn-secondary:hover {
        background: #2d3548 !important;
        color: #ffd700 !important;
    }
    body.dark-mode .btn-close {
        filter: invert(1) !important;
    }
    
    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .modal-dialog {
            margin: 10px;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-body i.fa-4x {
            font-size: 3rem !important;
        }
    }
</style>

<script>
    // ===== KEYBOARD SHORTCUT: Ctrl + Shift + L =====
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && (e.key === 'l' || e.key === 'L')) {
            e.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('logoutModal'));
            modal.show();
        }
    });

    // ===== AUTO-CLOSE DROPDOWN WHEN CLICKING OUTSIDE =====
    document.addEventListener('click', function(event) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(function(dropdown) {
            if (!dropdown.parentElement.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    });
</script>