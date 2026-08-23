<?php
/**
 * Tap-and-Go Doorlock - Edit Resident
 * FIXED LAYOUT SAME AS DASHBOARD
 * FIXED: Double close() error
 */

// Start session
session_start();

// Load config and functions
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

// Check authentication
if (!isset($_SESSION['admin_id']) || !isSessionValid()) {
    header('Location: login.php');
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: residents.php');
    exit();
}
// Include header
include '../includes/header.php'; 

$resident = null;
$profile = null;
$error = '';
$success = '';

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND status != 'deleted'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $resident = $result->fetch_assoc();
    $stmt->close();
    
    if (!$resident) {
        header('Location: residents.php');
        exit();
    }
    
    $stmt = $conn->prepare("SELECT * FROM resident_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $error = 'Error loading data: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $room_number = trim($_POST['room_number'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $birth_date = $_POST['birth_date'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $religion = trim($_POST['religion'] ?? '');
    $dialect = trim($_POST['dialect'] ?? '');
    $home_address = trim($_POST['home_address'] ?? '');
    $emergency_name = trim($_POST['emergency_name'] ?? '');
    $emergency_relationship = trim($_POST['emergency_relationship'] ?? '');
    $emergency_address = trim($_POST['emergency_address'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    
    if (empty($full_name) || empty($course) || empty($year_level)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $conn = getDBConnection();
            
            // Update users table
            $stmt = $conn->prepare("
                UPDATE users 
                SET full_name = ?, room_number = ?, contact_number = ? 
                WHERE user_id = ?
            ");
            $stmt->bind_param("sssi", $full_name, $room_number, $contact_number, $user_id);
            $stmt->execute();
            $stmt->close(); // CLOSE AFTER EXECUTION
            
            // Update or insert resident_profiles
            if ($profile) {
                $stmt = $conn->prepare("
                    UPDATE resident_profiles 
                    SET course = ?, year_level = ?, gender = ?, birth_date = ?, 
                        age = ?, religion = ?, dialect = ?, home_address = ?,
                        emergency_name = ?, emergency_relationship = ?, 
                        emergency_address = ?, emergency_contact = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param(
                    "ssssisssssssi",
                    $course, $year_level, $gender, $birth_date,
                    $age, $religion, $dialect, $home_address,
                    $emergency_name, $emergency_relationship,
                    $emergency_address, $emergency_contact, $user_id
                );
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO resident_profiles (
                        user_id, course, year_level, gender, birth_date,
                        age, religion, dialect, home_address,
                        emergency_name, emergency_relationship,
                        emergency_address, emergency_contact
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "issssississss",
                    $user_id, $course, $year_level, $gender, $birth_date,
                    $age, $religion, $dialect, $home_address,
                    $emergency_name, $emergency_relationship,
                    $emergency_address, $emergency_contact
                );
            }
            
            if ($stmt->execute()) {
                $success = 'Resident updated successfully!';
                
                // Refresh data - use new statements
                $stmt->close();
                
                // Reload resident data
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $resident = $result->fetch_assoc();
                $stmt->close();
                
                // Reload profile data
                $stmt = $conn->prepare("SELECT * FROM resident_profiles WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $profile = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error = 'Failed to update: ' . $stmt->error;
                $stmt->close();
            }
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Resident - Tap-and-Go Doorlock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ============================================================
           GLOBAL DARK THEME - SAME AS DASHBOARD
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
            padding-top: 70px !important;
        }
        
        .container-fluid {
            padding-top: 10px !important;
        }
        
        main {
            padding-top: 10px !important;
            margin-top: 0 !important;
        }
        
        /* ============================================================
           DARK NAVBAR OVERRIDE
           ============================================================ */
        .navbar {
            background: linear-gradient(135deg, #0d1528, #1a2a4a) !important;
            border-bottom: 1px solid #1a2a4a !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1050 !important;
            height: 70px !important;
        }
        .navbar-brand { color: #e0e0e0 !important; }
        .navbar .nav-link { color: rgba(255,255,255,0.6) !important; }
        .navbar .nav-link:hover { color: #ffffff !important; background: rgba(255,255,255,0.05) !important; }
        .navbar .nav-link.active { color: #ffffff !important; background: rgba(255,255,255,0.08) !important; }
        
        /* ============================================================
           DARK SIDEBAR
           ============================================================ */
        .sidebar {
            background: #0d1528 !important;
            border-right: 1px solid #1a2a4a !important;
            padding-top: 80px !important;
            min-height: calc(100vh - 70px) !important;
        }
        .sidebar .nav-link {
            color: #9090a0 !important;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e0e0e0 !important;
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            color: white !important;
        }
        .sidebar-footer { border-top-color: #1a2a4a !important; }
        .sidebar-footer .text-muted { color: #606070 !important; }
        
        /* ============================================================
           DARK FORM SECTION
           ============================================================ */
        .form-section {
            background: #111827 !important;
            border: 1px solid #1a2a4a !important;
            border-radius: 16px !important;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        .form-section .header-title {
            background: linear-gradient(135deg, #0a1628, #0d1f3c);
            color: white;
            padding: 15px 25px;
            border-radius: 12px 12px 0 0;
            margin: -30px -30px 25px -30px;
            border-bottom: 2px solid #ffd700;
        }
        .form-section .header-title h4 {
            font-weight: 700;
            margin: 0;
            color: #ffd700 !important;
        }
        .form-section .header-title p {
            margin: 0;
            opacity: 0.8;
            font-size: 13px;
            color: rgba(255,255,255,0.7) !important;
        }
        .form-section h5 {
            color: #ffd700 !important;
            font-weight: 700;
            border-bottom: 2px solid #b8960f;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #b0b0c0 !important;
        }
        .form-control, .form-select {
            background: #1a1a2e !important;
            border: 1px solid #2a2a4a !important;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: #e0e0e0 !important;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5a9a !important;
            box-shadow: 0 0 0 3px rgba(26,58,106,0.3);
            background: #1a1a2e !important;
            color: #e0e0e0 !important;
        }
        .required { color: #f87171 !important; }
        
        /* ============================================================
           DARK BUTTONS
           ============================================================ */
        .btn-submit {
            background: linear-gradient(135deg, #1a3a6a, #2a5a9a) !important;
            border: none !important;
            padding: 12px 40px;
            border-radius: 12px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26,58,106,0.4);
            color: white !important;
        }
        .btn-outline-secondary {
            border-color: #2a2a4a !important;
            color: #808090 !important;
        }
        .btn-outline-secondary:hover {
            background: #2a2a4a !important;
            color: #e0e0e0 !important;
        }
        
        /* ============================================================
           DARK ALERTS
           ============================================================ */
        .alert-success {
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: #10b981 !important;
            color: #6ee7b7 !important;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: #ef4444 !important;
            color: #fca5a5 !important;
        }
        .btn-close { filter: invert(1) !important; }
        
        .border-bottom { border-bottom-color: #1a2a4a !important; }
        .h1, .h2, h1, h2 { color: #e0e0e0 !important; }
        .text-muted { color: #808090 !important; }
        
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #34d399;
            animation: pulse 1.5s infinite;
            margin-right: 4px;
        }
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        hr { border-color: #1a2a4a !important; }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0a0e1a; }
        ::-webkit-scrollbar-thumb { background: #1a2a4a; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ffd700; }
        
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .navbar { height: 60px !important; }
            .sidebar {
                padding-top: 70px !important;
                position: fixed;
                top: 60px;
                bottom: 0;
                left: -280px;
                width: 280px;
                transition: left 0.3s ease;
                z-index: 999;
                min-height: calc(100vh - 60px) !important;
            }
            .sidebar.show { left: 0; }
            .form-section { padding: 20px; }
            .form-section .header-title {
                margin: -20px -20px 20px -20px;
                padding: 12px 18px;
            }
            .form-section .header-title h4 { font-size: 18px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                
                <!-- ============================================================
                HEADER - SAME AS DASHBOARD
                ============================================================ -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-edit me-2" style="color: #1a3a6a;"></i>
                        Edit Resident
                    </h1>
                    <div>
                        <span class="badge bg-success me-2">
                            <span class="live-indicator"></span> Live
                        </span>
                        <span class="badge bg-secondary" id="lastUpdate">Updated: <?php echo date('h:i A'); ?></span>
                        <a href="residents.php" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($resident): ?>
                <div class="form-section">
                    <div class="header-title">
                        <h4><i class="fas fa-user-edit me-2"></i>Edit Resident Information</h4>
                        <p>Student ID: <?php echo htmlspecialchars($resident['student_id'] ?? 'N/A'); ?></p>
                    </div>

                    <form method="POST" action="">
                        <!-- Personal Information -->
                        <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($resident['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Room Number</label>
                                <input type="text" class="form-control" name="room_number" value="<?php echo htmlspecialchars($resident['room_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Contact Number</label>
                                <input type="text" class="form-control" name="contact_number" value="<?php echo htmlspecialchars($resident['contact_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Course <span class="required">*</span></label>
                                <input type="text" class="form-control" name="course" value="<?php echo htmlspecialchars($profile['course'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year Level <span class="required">*</span></label>
                                <select class="form-select" name="year_level" required>
                                    <option value="1st Year" <?php echo (isset($profile['year_level']) && $profile['year_level'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo (isset($profile['year_level']) && $profile['year_level'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo (isset($profile['year_level']) && $profile['year_level'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo (isset($profile['year_level']) && $profile['year_level'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                    <option value="5th Year" <?php echo (isset($profile['year_level']) && $profile['year_level'] == '5th Year') ? 'selected' : ''; ?>>5th Year</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select</option>
                                    <option value="Female" <?php echo (isset($profile['gender']) && $profile['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Male" <?php echo (isset($profile['gender']) && $profile['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="LGBT" <?php echo (isset($profile['gender']) && $profile['gender'] == 'LGBT') ? 'selected' : ''; ?>>LGBT</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Birth Date</label>
                                <input type="date" class="form-control" name="birth_date" value="<?php echo htmlspecialchars($profile['birth_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Age</label>
                                <input type="number" class="form-control" name="age" value="<?php echo htmlspecialchars($profile['age'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Religion</label>
                                <input type="text" class="form-control" name="religion" value="<?php echo htmlspecialchars($profile['religion'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dialect Spoken</label>
                                <input type="text" class="form-control" name="dialect" value="<?php echo htmlspecialchars($profile['dialect'] ?? ''); ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Home Address</label>
                                <input type="text" class="form-control" name="home_address" value="<?php echo htmlspecialchars($profile['home_address'] ?? ''); ?>">
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Emergency Contact -->
                        <h5><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Emergency Name</label>
                                <input type="text" class="form-control" name="emergency_name" value="<?php echo htmlspecialchars($profile['emergency_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Relationship</label>
                                <input type="text" class="form-control" name="emergency_relationship" value="<?php echo htmlspecialchars($profile['emergency_relationship'] ?? ''); ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Emergency Address</label>
                                <input type="text" class="form-control" name="emergency_address" value="<?php echo htmlspecialchars($profile['emergency_address'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Emergency Contact</label>
                                <input type="text" class="form-control" name="emergency_contact" value="<?php echo htmlspecialchars($profile['emergency_contact'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="text-center mt-4">
                            <button type="submit" name="update" class="btn btn-submit">
                                <i class="fas fa-save me-2"></i> Update Resident
                            </button>
                            <a href="residents.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate age from birth date
        document.querySelector('input[name="birth_date"]').addEventListener('change', function() {
            if (this.value) {
                const birthDate = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                if (age > 0) {
                    document.querySelector('input[name="age"]').value = age;
                }
            }
        });
        
        // ============================================================
        // UPDATE TIME
        // ============================================================
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            const updateElement = document.getElementById('lastUpdate');
            if (updateElement) {
                updateElement.textContent = 'Updated: ' + timeString;
            }
        }
        setInterval(updateLastUpdateTime, 10000);
        document.addEventListener('DOMContentLoaded', updateLastUpdateTime);
        
        function toggleSidebar() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        }
    </script>
</body>
</html>
