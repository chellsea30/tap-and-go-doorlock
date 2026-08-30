<?php
/**
 * Tap-and-Go Doorlock - Print Staff ID Card
 */

session_start();
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

$uid = $_GET['uid'] ?? '';

if (empty($uid)) {
    die('No card UID provided.');
}

// Get staff data
$query = "
    SELECT 
        s.*,
        rf.card_uid,
        rf.issued_date,
        rf.expiry_date
    FROM staff_users s
    LEFT JOIN rfid_cards rf ON rf.card_uid = s.card_uid
    WHERE s.card_uid = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $uid);
$stmt->execute();
$result = $stmt->get_result();
$staff = $result->fetch_assoc();
$stmt->close();

if (!$staff) {
    die('Staff not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff ID - <?php echo htmlspecialchars($staff['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body { margin: 0; padding: 0; background: #fff; }
            .no-print { display: none !important; }
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
        
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .id-card {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .id-card .header {
            background: linear-gradient(135deg, #1a2a4a, #2a5a9a);
            padding: 15px 20px;
            text-align: center;
            color: white;
        }
        
        .id-card .header h4 {
            margin: 0;
            font-weight: 700;
            font-size: 18px;
            letter-spacing: 1px;
        }
        
        .id-card .header small {
            opacity: 0.8;
            font-size: 12px;
        }
        
        .id-card .body {
            padding: 20px;
            text-align: center;
        }
        
        .id-card .photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #1a2a4a;
            margin: 0 auto 12px;
            display: block;
            background: #e5e7eb;
        }
        
        .id-card .photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #1a2a4a;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 48px;
            font-weight: 700;
        }
        
        .id-card .name {
            font-size: 22px;
            font-weight: 700;
            color: #1a2a4a;
            margin-bottom: 2px;
        }
        
        .id-card .staff-id {
            font-size: 14px;
            color: #6b7280;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .id-card .dept {
            font-size: 14px;
            color: #4b5563;
            margin-bottom: 10px;
        }
        
        .id-card .uid {
            background: #f3f4f6;
            padding: 8px 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 16px;
            color: #1a2a4a;
            display: inline-block;
            margin: 6px 0;
            border: 1px solid #e5e7eb;
        }
        
        .id-card .expiry {
            font-size: 12px;
            color: #6b7280;
            margin-top: 8px;
        }
        
        .id-card .footer {
            border-top: 1px solid #e5e7eb;
            padding: 12px 20px;
            text-align: center;
            background: #f9fafb;
        }
        
        .id-card .footer small {
            color: #9ca3af;
            font-size: 10px;
        }
        
        .btn-print {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 12px 24px;
            background: linear-gradient(135deg, #1a2a4a, #2a5a9a);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26,58,106,0.3);
        }
    </style>
</head>
<body>

    <div class="id-card">
        <!-- Header -->
        <div class="header">
            <h4>🏢 TAP-AND-GO DOORLOCK</h4>
            <small>Staff Identification Card</small>
        </div>
        
        <!-- Body -->
        <div class="body">
            <!-- Profile Photo -->
            <?php 
                $photoPath = $staff['avatar'] ?? '';
                $hasPhoto = false;
                $fullPhotoPath = '';
                
                if (!empty($photoPath)) {
                    if (strpos($photoPath, 'uploads/') === 0) {
                        $fullPhotoPath = '../../' . $photoPath;
                    } else {
                        $fullPhotoPath = '../../uploads/staff_photos/' . $photoPath;
                    }
                    
                    if (file_exists($fullPhotoPath)) {
                        $hasPhoto = true;
                    }
                }
                
                $name = $staff['full_name'] ?? 'Staff';
                $initials = '';
                $parts = explode(' ', $name);
                foreach ($parts as $p) {
                    if (!empty($p)) $initials .= strtoupper($p[0]);
                }
                $initials = substr($initials, 0, 2) ?: 'ST';
            ?>
            
            <?php if ($hasPhoto): ?>
                <img src="<?php echo $fullPhotoPath; ?>" 
                     alt="<?php echo htmlspecialchars($name); ?>" 
                     class="photo"
                     onerror="this.style.display='none'; this.parentElement.querySelector('.photo-placeholder').style.display='flex';">
            <?php else: ?>
                <div class="photo-placeholder"><?php echo $initials; ?></div>
            <?php endif; ?>
            
            <!-- Name -->
            <div class="name"><?php echo htmlspecialchars($staff['full_name']); ?></div>
            
            <!-- Staff ID -->
            <div class="staff-id"><?php echo htmlspecialchars($staff['staff_id_number']); ?></div>
            
            <!-- Department -->
            <div class="dept"><?php echo htmlspecialchars($staff['department'] ?? 'Staff'); ?></div>
            
            <!-- Card UID -->
            <div class="uid">
                <i class="fas fa-id-card me-2" style="color:#6b7280;"></i>
                <?php echo htmlspecialchars($staff['card_uid']); ?>
            </div>
            
            <!-- Expiry -->
            <div class="expiry">
                <i class="far fa-calendar-alt me-1"></i>
                Issued: <?php echo date('M d, Y', strtotime($staff['issued_date'] ?? date('Y-m-d'))); ?>
                <?php if (!empty($staff['expiry_date'])): ?>
                    <span class="mx-1">|</span>
                    <i class="far fa-clock me-1"></i>
                    Expires: <?php echo date('M d, Y', strtotime($staff['expiry_date'])); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <small>
                <i class="fas fa-shield-alt me-1"></i>
                This card is the property of Tap-and-Go Doorlock System.
                <br>Please return if found.
            </small>
        </div>
    </div>
    
    <!-- Print Button -->
    <div class="no-print text-center">
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print me-2"></i> Print ID Card
        </button>
        <br>
        <a href="staff-card.php" class="text-muted small">
            <i class="fas fa-arrow-left me-1"></i> Back to Staff Cards
        </a>
    </div>

    <script>
        // Auto-print when loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Uncomment below to auto-print
            // window.print();
        });
    </script>
</body>
</html>
