<!-- ============================================================
   FOOTER - Always at Bottom
   ============================================================ -->
<footer class="footer mt-auto py-3">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <span class="text-muted small">
                    <i class="fas fa-door-open me-1" style="color: #ffd700;"></i>
                    <strong>Tap-and-Go Doorlock</strong> 
                    <span class="text-muted">v1.0</span>
                </span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <span class="text-muted small">
                    &copy; <?php echo date('Y'); ?> 
                    <span style="color: #ffd700; font-weight: 600;">ISU-Echague Dormitory</span>
                </span>
            </div>
        </div>
    </div>
</footer>

<style>
    /* ============================================================
       FOOTER STYLES - Always at bottom
       ============================================================ */
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
    }
    
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
    
    /* Main content wrapper */
    .main-wrapper {
        flex: 1 0 auto;
        display: flex;
        flex-direction: column;
    }
    
    /* Content area - pushes footer down */
    .content-area {
        flex: 1 0 auto;
    }
    
    /* Footer */
    .footer {
        flex-shrink: 0;
        background: #f8f9fa !important;
        border-top: 1px solid #e5e7eb;
        padding: 15px 0;
        margin-top: 40px;
        width: 100%;
    }
    
    .footer .text-muted {
        font-size: 13px;
        color: #6b7280 !important;
    }
    
    .footer .text-muted strong {
        color: #1a3a6a;
    }
    
    /* Dark mode footer */
    body.dark-mode .footer {
        background: #1a1a2e !important;
        border-top: 1px solid #2a2a4a !important;
    }
    
    body.dark-mode .footer .text-muted {
        color: #808090 !important;
    }
    
    body.dark-mode .footer .text-muted strong {
        color: #8b5cf6 !important;
    }
    
    body.dark-mode .footer .text-muted span {
        color: #ffd700 !important;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .footer .text-center.text-md-start,
        .footer .text-center.text-md-end {
            text-align: center !important;
        }
        .footer .col-md-6 {
            margin-bottom: 5px;
        }
    }
</style>

