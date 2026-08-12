<?php
/**
 * Student Footer
 * Separate footer file for student section
 */
?>
    <!-- ===== FOOTER ===== -->
    <footer class="student-footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">
                        <i class="fas fa-door-open me-1" style="color: #ffd700;"></i>
                        <strong>Tap-and-Go Doorlock System</strong>
                        <span class="mx-2">|</span>
                        <span class="text-muted">ISU - Echague Campus Dormitory</span>
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 text-muted">
                        <i class="far fa-calendar-alt me-1"></i>
                        <?php echo date('Y'); ?> &copy; All Rights Reserved
                        <span class="mx-2">|</span>
                        <i class="fas fa-user-graduate me-1"></i>
                        Student Portal v2.0
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <style>
        .student-footer {
            background: white;
            border-top: 1px solid #e5e7eb;
            padding: 12px 0;
            margin-top: 30px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.04);
            font-size: 13px;
        }
        .student-footer p {
            margin: 0;
            color: #4b5563;
        }
        .student-footer .text-muted {
            color: #6b7280 !important;
        }
        .student-footer strong {
            color: #1a3a6a;
        }
        
        @media (max-width: 768px) {
            .student-footer {
                padding: 10px 0;
                font-size: 12px;
            }
            .student-footer .col-md-6 {
                text-align: center !important;
            }
            .student-footer .col-md-6:first-child {
                margin-bottom: 5px;
            }
        }
    </style>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Toggle sidebar function (for mobile)
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) {
                sidebar.classList.toggle('show');
            }
            if (overlay) {
                overlay.classList.toggle('show');
            }
        }

        // Close sidebar when clicking outside (on mobile)
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.addEventListener('click', function() {
                    toggleSidebar();
                });
            }

            // Auto-close alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>