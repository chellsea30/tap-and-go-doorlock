<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="student_dashboard.php">
            
            <!-- ===== LOGO (Student folder, kaya umakyat ng isang level: ../) ===== -->
            <img src="../assets/images/isu-logo.png" alt="ISU Logo" style="width: 55px; height: 55px; border-radius: 50%; object-fit: contain; border: 2px solid #ffd700; background: white; padding: 2px; margin-right: 15px;">
            
            <!-- ===== ISU-E LADIES DORMITORY ===== -->
            <span style="font-size: 24px; font-weight: 900; color: #ffffff; letter-spacing: 1px; line-height: 1;">
                ISU-E <span style="color: #ffd700;">LADIES DORMITORY</span>
            </span>
            
            <!-- ===== WELCOME BACK STUDENT (MAS MALAKI AT MAY KULAY) ===== -->
            <span class="ms-4 fw-bold" style="font-size: 20px; color: #34d399;">Welcome back, STUDENT! 👋</span>
            
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- User Dropdown -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1" style="font-size: 20px;"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="request-reset.php">
                                <i class="fas fa-key me-2"></i> Reset Password
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="concerns.php">
                                <i class="fas fa-exclamation-circle me-2"></i> Concerns
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="announcements.php">
                                <i class="fas fa-bullhorn me-2"></i> Announcements
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="../../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
