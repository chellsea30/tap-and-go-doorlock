<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <button class="navbar-toggler me-2" type="button" onclick="toggleSidebar()">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="dashboard.php">
            <!-- Logo -->
            <img src="../../assets/images/isu-logo.png" alt="ISU Logo" style="width: 45px; height: 45px; border-radius: 50%; object-fit: contain; border: 2px solid #ffd700; background: white; padding: 2px; margin-right: 10px; vertical-align: middle;">
            
            <!-- Pangalan ng Dormitory -->
            <span style="font-size: 20px; font-weight: 900; color: #ffffff; letter-spacing: 1px; vertical-align: middle;">
                ISU-E <span style="color: #ffd700;">LADIES DORMITORY</span>
            </span>
            
            <!-- Welcome Back Student -->
            <span class="ms-3 fw-bold" style="font-size: 18px; color: #34d399; vertical-align: middle;">Welcome back, STUDENT! 👋</span>
        </a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1" style="font-size: 20px;"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
                    </a>
                    <!-- Dito: Logout Lang -->
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item text-danger" href="../../login.php">
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
    /* ===== NAVBAR STYLES ===== */
    .navbar {
        background: linear-gradient(135deg, #0a1628, #1a2a4a) !important;
        border-bottom: 1px solid #1e2a3a !important;
        height: 56px;
        padding: 0 20px;
        z-index: 1050;
    }
    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .logout-btn {
        color: rgba(255,255,255,0.7) !important;
        padding: 6px 16px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.15);
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    .logout-btn:hover {
        background: rgba(255,255,255,0.1) !important;
        color: white !important;
    }
    .navbar-text {
        color: rgba(255,255,255,0.8) !important;
        font-size: 14px;
    }
    
    /* ===== DARK MODE DROPDOWN MENU ===== */
    .dropdown-menu {
        background: #131926 !important;
        border: 1px solid #1e2a3a !important;
        border-radius: 12px !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
    }
    .dropdown-item {
        color: #e5e7eb !important;
        font-size: 14px;
        padding: 10px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    .dropdown-item.text-danger {
        color: #f87171 !important;
    }
    .dropdown-item.text-danger:hover {
        background: rgba(239, 68, 68, 0.1) !important;
        color: #fca5a5 !important;
    }
    .dropdown-divider {
        border-color: #1e2a3a !important;
    }
    
    /* ===== DARK MODE MODAL ===== */
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
    body.dark-mode .btn-secondary {
        background: #1e2a3a !important;
        border: none !important;
        color: #e5e7eb !important;
    }
    body.dark-mode .btn-secondary:hover {
        background: #2d3548 !important;
        color: #e5e7eb !important;
    }
    body.dark-mode .btn-close {
        filter: invert(1) !important;
    }
    
    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .navbar-brand img {
            width: 35px !important;
            height: 35px !important;
        }
        .navbar-brand span {
            font-size: 14px !important;
        }
        .navbar-text {
            margin-bottom: 5px;
        }
        .logout-btn {
            margin-top: 10px;
            display: inline-block;
        }
    }
</style>
