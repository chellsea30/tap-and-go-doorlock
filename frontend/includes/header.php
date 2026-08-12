<?php
/**
 * Global Header - Include on ALL pages
 * FIXED VERSION WITH AUTO-EXPIRATION CHECK
 */

// ============================================================
// AUTO-CHECK EXPIRED CARDS ON PAGE LOAD
// ============================================================
if (isset($_SESSION['admin_id'])) {
    try {
        // Check for expired visitor cards
        $expired_count = checkExpiredVisitorCards();
        
        if ($expired_count > 0) {
            // Store notification for display
            $_SESSION['expired_card_notification'] = [
                'count' => $expired_count,
                'message' => "$expired_count expired visitor card(s) have been automatically deactivated."
            ];
        }
        
        // Also check for cards expiring soon (within 3 days)
        $expiring_soon = getCardsExpiringSoon(3, 'visitor');
        if (!empty($expiring_soon) && !isset($_SESSION['expiring_soon_notification'])) {
            $_SESSION['expiring_soon_notification'] = [
                'count' => count($expiring_soon),
                'cards' => $expiring_soon
            ];
        }
    } catch (Exception $e) {
        // Silently fail - don't break the page
    }
}

// Get dark mode from database if session exists
$darkModeClass = '';
$darkModeFromDb = 'false';
if (isset($_SESSION['admin_id'])) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT setting_value FROM user_settings WHERE admin_id = ? AND setting_key = 'dark_mode'");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $darkModeFromDb = $row['setting_value'];
            if ($darkModeFromDb == 'true') {
                $darkModeClass = 'dark-mode';
            }
        }
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Silently fail
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tap-and-Go Doorlock</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <!-- Dark Mode CSS -->
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    
    <!-- ============================================================
    DARK MODE - CRITICAL: Apply before any CSS renders
    ============================================================ -->
    <script>
        // CRITICAL: Apply dark mode BEFORE any CSS renders
        (function() {
            // Check if dark mode is enabled in localStorage
            const isDarkFromStorage = localStorage.getItem('darkMode') === 'true';
            
            // Check from PHP (passed from server)
            const isDarkFromDb = <?php echo ($darkModeFromDb == 'true') ? 'true' : 'false'; ?>;
            
            // Apply dark mode immediately if either is true
            if (isDarkFromStorage || isDarkFromDb) {
                document.documentElement.style.backgroundColor = '#0f0f1a';
                document.documentElement.style.color = '#e0e0e0';
                document.documentElement.classList.add('dark-mode');
                document.body.classList.add('dark-mode');
            }
            
            // Store the value for later use
            window.__darkModeEnabled = isDarkFromStorage || isDarkFromDb;
            window.__dbDarkMode = isDarkFromDb;
        })();
    </script>
    
    <!-- ============================================================
    NOTIFICATION STYLES
    ============================================================ -->
    <style>
        /* Expiring Soon Notification */
        .expiring-soon-notification {
            background: rgba(245, 158, 11, 0.15) !important;
            border: 1px solid rgba(245, 158, 11, 0.3) !important;
            border-radius: 12px !important;
            padding: 15px 20px !important;
            margin-bottom: 15px !important;
            animation: slideDown 0.5s ease !important;
        }
        .expiring-soon-notification .icon {
            color: #fbbf24 !important;
            font-size: 24px !important;
        }
        .expiring-soon-notification .title {
            color: #fbbf24 !important;
            font-weight: 600 !important;
        }
        .expiring-soon-notification .card-list {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
            margin-top: 8px !important;
        }
        .expiring-soon-notification .card-chip {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(245, 158, 11, 0.2) !important;
            border-radius: 20px !important;
            padding: 3px 12px !important;
            font-size: 12px !important;
            color: #e0e0e0 !important;
            font-family: monospace !important;
        }
        .expiring-soon-notification .days-left {
            color: #fbbf24 !important;
            font-weight: 600 !important;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="<?php echo $darkModeClass; ?>">
    
    <!-- ============================================================
    EXPIRING SOON NOTIFICATION (Shows on ALL pages)
    ============================================================ -->
    <?php if (isset($_SESSION['expiring_soon_notification']) && !empty($_SESSION['expiring_soon_notification']['cards'])): ?>
        <div class="container-fluid mt-3">
            <div class="expiring-soon-notification">
                <div class="d-flex align-items-start">
                    <div class="icon me-3">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="title">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <?php echo $_SESSION['expiring_soon_notification']['count']; ?> visitor card(s) expiring soon!
                        </div>
                        <div class="card-list">
                            <?php foreach ($_SESSION['expiring_soon_notification']['cards'] as $card): 
                                $days_left = ceil((strtotime($card['expiry_date']) - time()) / 86400);
                            ?>
                                <span class="card-chip">
                                    <?php echo htmlspecialchars($card['card_uid']); ?>
                                    <span class="days-left">
                                        <?php echo $days_left; ?> day<?php echo $days_left > 1 ? 's' : ''; ?>
                                    </span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted mt-1 d-block">
                            <i class="fas fa-info-circle me-1"></i>
                            These cards will expire soon. Please remind residents to renew their cards.
                        </small>
                    </div>
                    <button type="button" class="btn-close btn-close-white ms-2" onclick="this.parentElement.parentElement.style.display='none';" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php 
        // Don't unset immediately so it shows on all pages
        // It will be cleared when admin dismisses or after 24 hours
        ?>
    <?php endif; ?>
    
    <!-- ============================================================
    EXPIRED CARD NOTIFICATION (Shows on ALL pages)
    ============================================================ -->
    <?php if (isset($_SESSION['expired_card_notification'])): ?>
        <div class="container-fluid mt-3">
            <div class="alert alert-warning alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.15) !important; border: 1px solid rgba(239, 68, 68, 0.3) !important; color: #fca5a5 !important; border-radius: 12px !important;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?php echo $_SESSION['expired_card_notification']['count']; ?> visitor card(s)</strong> 
                have been automatically deactivated due to expiration.
                <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="this.closest('.alert').style.display='none';"></button>
            </div>
        </div>
        <?php 
        // Unset after showing once per session
        unset($_SESSION['expired_card_notification']); 
        ?>
    <?php endif; ?>