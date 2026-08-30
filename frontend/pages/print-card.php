<?php
/**
 * Tap-and-Go Doorlock - Print Resident ID Card
 * STYLE: ISU-Echague Ladies Dormitory ID Card
 * WITHOUT CARD UID DISPLAY
 */

session_start();
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$uid = isset($_GET['uid']) ? $_GET['uid'] : '';

if (empty($uid)) {
    die('No card UID provided.');
}

// Get card and user details
$query = "
    SELECT 
        c.*,
        u.full_name as user_name,
        u.student_id,
        u.room_number,
        u.profile_photo,
        rp.course,
        rp.year_level,
        rp.gender
    FROM rfid_cards c
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN resident_profiles rp ON u.user_id = rp.user_id
    WHERE c.card_uid = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $uid);
$stmt->execute();
$result = $stmt->get_result();
$card = $result->fetch_assoc();

if (!$card) {
    die('Card not found.');
}

// Determine display name
if ($card['card_type'] == 'visitor' && !empty($card['visitor_name'])) {
    $display_name = $card['visitor_name'];
    $is_visitor = true;
    $tenant_name = $card['user_name'] ?? 'Unknown Tenant';
} else {
    $display_name = $card['user_name'] ?? 'Unassigned';
    $is_visitor = false;
    $tenant_name = null;
}

// Get profile photo
$profile_photo_path = null;
$has_photo = false;

if (!empty($card['profile_photo'])) {
    if (strpos($card['profile_photo'], 'uploads/') === 0) {
        $full_path = '../../' . $card['profile_photo'];
    } else {
        $full_path = '../../uploads/resident_photos/' . $card['profile_photo'];
    }
    if (file_exists($full_path)) {
        $has_photo = true;
        $profile_photo_path = $full_path;
    }
}

// Get initials
$parts = explode(' ', $display_name);
$initials = '';
foreach ($parts as $p) {
    if (!empty($p)) $initials .= strtoupper($p[0]);
}
$initials = substr($initials, 0, 2) ?: '?';

// Room number display
$room_display = $card['room_number'] ?? 'N/A';
if ($is_visitor) {
    $room_display = 'Visit: ' . ($card['room_number'] ?? 'N/A');
}

// User type label
if ($is_visitor) {
    $type_label = 'VISITOR';
    $type_color = '#3730a3';
} elseif ($card['card_type'] == 'staff') {
    $type_label = 'STAFF';
    $type_color = '#4a3a1a';
} else {
    $type_label = 'RESIDENT';
    $type_color = '#065f46';
}

// For visitor, show the tenant they're visiting
if ($is_visitor && !empty($card['visitor_name'])) {
    $display_name = $card['visitor_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print ID Card</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           ID CARD STYLES
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #0a0e1a !important;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        
        .id-card-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding: 20px;
        }
        
        /* ============================================================
           ID CARD - MAIN STYLE
           ============================================================ */
        .id-card {
            width: 350px;
            background: linear-gradient(135deg, #0d1528, #1a2a4a);
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 20px 60px rgba(0,0,0,0.8);
            border: 1px solid #2a3a5a;
            overflow: hidden;
            position: relative;
        }
        
        /* Header - Blue strip */
        .id-card-header {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            padding: 15px 20px 12px 20px;
            text-align: center;
            border-bottom: 2px solid #ffd700;
        }
        .id-card-header .title {
            color: white;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .id-card-header .subtitle {
            color: rgba(255,255,255,0.7);
            font-size: 10px;
            font-weight: 400;
            letter-spacing: 2px;
            margin-top: 2px;
        }
        
        /* Body */
        .id-card-body {
            padding: 20px 20px 15px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        /* Photo */
        .id-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid #ffd700;
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 4px 20px rgba(255, 215, 0, 0.2);
        }
        .id-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .id-photo .initials {
            color: white;
            font-size: 32px;
            font-weight: 700;
        }
        
        /* Name */
        .id-name {
            color: #e0e0e0;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            letter-spacing: 0.5px;
        }
        
        /* Type Badge */
        .id-type {
            display: inline-block;
            padding: 4px 18px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 6px;
            background: <?php echo $type_color; ?>;
            color: white;
            text-transform: uppercase;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Divider */
        .id-divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #ffd700, transparent);
            margin: 10px 0 12px 0;
        }
        
        /* Details */
        .id-details {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 0 5px;
        }
        .id-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .id-detail-row .label {
            color: #808090;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .id-detail-row .value {
            color: #e0e0e0;
            font-size: 12px;
            font-weight: 600;
            text-align: right;
        }
        .id-detail-row .value.room {
            color: #ffd700;
            font-weight: 700;
        }
        
        /* Footer */
        .id-card-footer {
            background: rgba(0,0,0,0.3);
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #1a2a4a;
        }
        .id-card-footer .footer-text {
            color: #606070;
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 1px;
        }
        .id-card-footer .footer-text span {
            color: #ffd700;
        }
        
        /* QR Code / Extra */
        .id-extra {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .id-extra .extra-item {
            text-align: center;
        }
        .id-extra .extra-item .extra-label {
            color: #606070;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .id-extra .extra-item .extra-value {
            color: #93c5fd;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn-print {
            background: linear-gradient(135deg, #ffd700, #f59e0b) !important;
            border: none !important;
            color: #0a0e1a !important;
            padding: 12px 40px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-print:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3) !important;
            color: #0a0e1a !important;
        }
        .btn-back {
            background: #1a2a4a !important;
            border: none !important;
            color: #e0e0e0 !important;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back:hover {
            background: #2a3a5a !important;
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 480px) {
            .id-card {
                width: 320px;
            }
            .id-photo {
                width: 80px;
                height: 80px;
            }
            .id-name {
                font-size: 17px;
            }
        }
        
        @media print {
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .no-print { display: none !important; }
            .id-card {
                box-shadow: none !important;
                border: 1px solid #333 !important;
                width: 350px !important;
                margin: 0 auto !important;
            }
            .id-card-container {
                padding: 0 !important;
                gap: 0 !important;
            }
            .id-card-header {
                background: #1a3a6a !important;
            }
            .id-photo {
                border-color: #ffd700 !important;
            }
            .id-detail-row {
                border-bottom-color: rgba(0,0,0,0.1) !important;
            }
            .id-detail-row .label { color: #666 !important; }
            .id-detail-row .value { color: #000 !important; }
            .id-detail-row .value.room { color: #1a3a6a !important; }
            .id-card-footer { border-top-color: #ddd !important; }
            .id-card-footer .footer-text { color: #999 !important; }
            .id-card-footer .footer-text span { color: #1a3a6a !important; }
            .id-name { color: #000 !important; }
            .id-divider { background: #1a3a6a !important; }
            .id-extra .extra-item .extra-value { color: #1a3a6a !important; }
        }
    </style>
</head>
<body>
    <div class="id-card-container">
        <!-- ID CARD -->
        <div class="id-card" id="idCard">
            <!-- Header -->
            <div class="id-card-header">
                <div class="title">ISU-E LADIES DORMITORY</div>
                <div class="subtitle">TAP AND GO DOOR ACCESS</div>
            </div>
            
            <!-- Body -->
            <div class="id-card-body">
                <!-- Photo -->
                <div class="id-photo">
                    <?php if ($has_photo && $profile_photo_path): ?>
                        <img src="<?php echo $profile_photo_path; ?>" alt="Photo">
                    <?php else: ?>
                        <span class="initials"><?php echo $initials; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Name -->
                <div class="id-name"><?php echo htmlspecialchars($display_name); ?></div>
                
                <!-- Type Badge -->
                <span class="id-type"><?php echo $type_label; ?></span>
                
                <!-- Divider -->
                <div class="id-divider"></div>
                
                <!-- Details - WITHOUT CARD UID -->
                <div class="id-details">
                    <?php if (!$is_visitor && $card['card_type'] != 'staff'): ?>
                        <div class="id-detail-row">
                            <span class="label">Student ID</span>
                            <span class="value"><?php echo htmlspecialchars($card['student_id'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="id-detail-row">
                            <span class="label">Course</span>
                            <span class="value"><?php echo htmlspecialchars($card['course'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="id-detail-row">
                            <span class="label">Room</span>
                            <span class="value room"><?php echo htmlspecialchars($room_display); ?></span>
                        </div>
                    <?php elseif ($card['card_type'] == 'staff'): ?>
                        <div class="id-detail-row">
                            <span class="label">Staff ID</span>
                            <span class="value"><?php echo htmlspecialchars($card['student_id'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="id-detail-row">
                            <span class="label">Department</span>
                            <span class="value"><?php echo htmlspecialchars($card['course'] ?? 'N/A'); ?></span>
                        </div>
                    <?php elseif ($is_visitor): ?>
                        <div class="id-detail-row">
                            <span class="label">Visiting</span>
                            <span class="value"><?php echo htmlspecialchars($tenant_name); ?></span>
                        </div>
                        <div class="id-detail-row">
                            <span class="label">Purpose</span>
                            <span class="value"><?php echo htmlspecialchars($card['purpose_of_visit'] ?? 'Visit'); ?></span>
                        </div>
                        <div class="id-detail-row">
                            <span class="label">Room</span>
                            <span class="value room"><?php echo htmlspecialchars($room_display); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($card['expiry_date']): ?>
                        <div class="id-detail-row">
                            <span class="label">Expiry Date</span>
                            <span class="value"><?php echo date('M d, Y', strtotime($card['expiry_date'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Extra - WITHOUT CARD UID -->
                <div class="id-extra">
                    <div class="extra-item">
                        <div class="extra-label">Status</div>
                        <div class="extra-value" style="color: <?php echo $card['status'] == 'active' ? '#34d399' : '#f87171'; ?>;">
                            <?php echo strtoupper($card['status']); ?>
                        </div>
                    </div>
                    <div class="extra-item">
                        <div class="extra-label">Type</div>
                        <div class="extra-value" style="color: <?php echo $type_color; ?>;">
                            <?php echo $type_label; ?>
                        </div>
                    </div>
                    <div class="extra-item">
                        <div class="extra-label">Issued</div>
                        <div class="extra-value"><?php echo date('M d, Y', strtotime($card['issued_date'])); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="id-card-footer">
                <div class="footer-text">
                    <span>✦</span> TAP AND GO DOORLOCK SYSTEM <span>✦</span>
                </div>
            </div>
        </div>
        
        <!-- BUTTONS -->
        <div class="d-flex gap-3 no-print">
            <button onclick="window.print()" class="btn-print">
                <i class="fas fa-print"></i> Print ID Card
            </button>
            <a href="cards.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
        
        <div class="text-muted small no-print text-center" style="max-width: 400px; color: #606070 !important;">
            <i class="fas fa-info-circle me-1"></i>
            Press <strong>Ctrl+P</strong> or click the Print button to print this ID card.
            <br>For best results, use <strong>card stock</strong> paper.
        </div>
    </div>
    
    <script>
        // Auto-print if print parameter is set
        <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
        window.onload = function() {
            window.print();
        }
        <?php endif; ?>
    </script>
</body>
</html>
