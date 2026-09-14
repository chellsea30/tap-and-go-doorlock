<?php
/**
 * Tap-and-Go Doorlock - Student Self-Registration Form
 * Public - No login required
 * Location: frontend/pages/student-self-registration.php
 */

session_start();
require_once '../../backend/config/config.php';
require_once '../../backend/helpers/functions.php';

$success = '';
$error = '';

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_registration'])) {
    // Get form data - UPPERCASE
    $full_name = strtoupper(trim($_POST['full_name'] ?? ''));
    $course = strtoupper(trim($_POST['course'] ?? ''));
    $year_level = trim($_POST['year_level'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $gender_other = strtoupper(trim($_POST['gender_other'] ?? ''));
    $birth_date = $_POST['birth_date'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $birth_no = strtoupper(trim($_POST['birth_no'] ?? ''));
    $no_siblings = strtoupper(trim($_POST['no_siblings'] ?? ''));
    $scholarship = strtoupper(trim($_POST['scholarship'] ?? ''));
    $allowance_source = strtoupper(trim($_POST['allowance_source'] ?? ''));
    $school_last = strtoupper(trim($_POST['school_last'] ?? ''));
    $school_address = strtoupper(trim($_POST['school_address'] ?? ''));
    $cultural_origin = strtoupper(trim($_POST['cultural_origin'] ?? ''));
    $religion = strtoupper(trim($_POST['religion'] ?? ''));
    $dialect = strtoupper(trim($_POST['dialect'] ?? ''));
    $cp_no = strtoupper(trim($_POST['cp_no'] ?? ''));
    $home_address = strtoupper(trim($_POST['home_address'] ?? ''));
    $civil_status = $_POST['civil_status'] ?? '';
    $father_education = strtoupper(trim($_POST['father_education'] ?? ''));
    $mother_education = strtoupper(trim($_POST['mother_education'] ?? ''));
    $father_occupation = strtoupper(trim($_POST['father_occupation'] ?? ''));
    $mother_occupation = strtoupper(trim($_POST['mother_occupation'] ?? ''));
    $emergency_name = strtoupper(trim($_POST['emergency_name'] ?? ''));
    $emergency_relationship = strtoupper(trim($_POST['emergency_relationship'] ?? ''));
    $emergency_address = strtoupper(trim($_POST['emergency_address'] ?? ''));
    $emergency_contact = strtoupper(trim($_POST['emergency_contact'] ?? ''));
    $parents_marital_status = $_POST['parents_marital_status'] ?? '';
    $former_boarding_years = strtoupper(trim($_POST['former_boarding_years'] ?? ''));
    $plan_transfer = $_POST['plan_transfer'] ?? '';
    $plan_transfer_yes = strtoupper(trim($_POST['plan_transfer_yes'] ?? ''));
    $plan_transfer_no = strtoupper(trim($_POST['plan_transfer_no'] ?? ''));
    $date_registered = date('Y-m-d');
    
    // Validate required
    $required = [
        'Full Name' => $full_name,
        'Course' => $course,
        'Year Level' => $year_level,
        'Gender' => $gender,
        'Birth Date' => $birth_date,
        'Contact Number' => $cp_no,
        'Home Address' => $home_address,
        'Emergency Name' => $emergency_name,
        'Emergency Relationship' => $emergency_relationship,
        'Emergency Address' => $emergency_address,
        'Emergency Contact' => $emergency_contact
    ];
    
    $missing = [];
    foreach ($required as $label => $value) {
        if (empty($value)) $missing[] = $label;
    }
    
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $missing[] = 'Profile Photo';
    }
    
    if (!empty($missing)) {
        $error = 'Please fill in: ' . implode(', ', $missing);
    } else {
        try {
            $conn = getDBConnection();
            
            // Generate unique student ID
            $student_id = 'STU-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $check = $conn->prepare("SELECT user_id FROM users WHERE student_id = ?");
            $check->bind_param("s", $student_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $student_id = 'STU-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            }
            $check->close();
            
            // Handle photo upload
            $photo_path = '';
            $upload_dir = '../../uploads/resident_photos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed)) {
                $file_name = time() . '_' . $student_id . '.' . $file_extension;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $file_name)) {
                    $photo_path = 'uploads/resident_photos/' . $file_name;
                }
            }
            
            // Insert into users (PENDING approval)
            $email_placeholder = strtolower(str_replace(' ', '.', $full_name)) . '@student.isu.edu.ph';
            
            $stmt = $conn->prepare("
                INSERT INTO users (
                    full_name, student_id, contact_number, email, status,
                    approval_status, profile_photo, created_at
                ) VALUES (?, ?, ?, ?, 'pending', 'pending', ?, NOW())
            ");
            $stmt->bind_param("sssss", $full_name, $student_id, $cp_no, $email_placeholder, $photo_path);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                $stmt->close();
                
                // ============================================================
                // INSERT INTO RESIDENT_PROFILES - FIXED BIND_PARAM
                // ============================================================
                $profileStmt = $conn->prepare("
                    INSERT INTO resident_profiles (
                        user_id, date_registered, gender, gender_other, birth_date, age,
                        birth_no, course, year_level, no_siblings, scholarship, allowance_source,
                        school_last, school_address, cultural_origin, religion, dialect,
                        home_address, civil_status, father_education, mother_education,
                        father_occupation, mother_occupation, emergency_name, emergency_relationship,
                        emergency_address, emergency_contact, parents_marital_status,
                        former_boarding_years, plan_transfer, plan_transfer_yes, plan_transfer_no
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Type string: 32 chars (i + s + s + s + s + i + 26 s)
                // user_id = i, age = i, all others = s
                $types = "i" . str_repeat("s", 4) . "i" . str_repeat("s", 26);
                // Result: "i" + "ssss" + "i" + "ssssssssssssssssssssssssss" = 32 chars
                
                $profileStmt->bind_param($types,
                    $user_id,
                    $date_registered,
                    $gender,
                    $gender_other,
                    $birth_date,
                    $age,
                    $birth_no,
                    $course,
                    $year_level,
                    $no_siblings,
                    $scholarship,
                    $allowance_source,
                    $school_last,
                    $school_address,
                    $cultural_origin,
                    $religion,
                    $dialect,
                    $home_address,
                    $civil_status,
                    $father_education,
                    $mother_education,
                    $father_occupation,
                    $mother_occupation,
                    $emergency_name,
                    $emergency_relationship,
                    $emergency_address,
                    $emergency_contact,
                    $parents_marital_status,
                    $former_boarding_years,
                    $plan_transfer,
                    $plan_transfer_yes,
                    $plan_transfer_no
                );
                $profileStmt->execute();
                $profileStmt->close();
                
                // Log registration
                $logStmt = $conn->prepare("
                    INSERT INTO student_registration_logs (user_id, action, details, performed_by, ip_address)
                    VALUES (?, 'self_registration', ?, 'student', ?)
                ");
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $details = "Student self-registered with ID: $student_id";
                $logStmt->bind_param("iss", $user_id, $details, $ip);
                $logStmt->execute();
                $logStmt->close();
                
                $success = $student_id;
            } else {
                $error = 'Failed to save: ' . $stmt->error;
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
    <title>Student Registration - ISU Ladies Dormitory</title>
    <link rel="icon" type="image/png" href="../assets/images/isu-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a1628 0%, #0d1f3c 50%, #1a2a4a 100%);
            color: #e5e7eb;
            min-height: 100vh;
            padding: 20px;
        }
        .container-main { max-width: 1000px; margin: 0 auto; }
        .header-section {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            text-align: center;
        }
        .header-section .logo {
            width: 80px; height: 80px; border-radius: 50%;
            border: 3px solid #ffd700; margin-bottom: 15px;
        }
        .header-section h1 { color: #ffd700; font-size: 24px; font-weight: 800; margin-bottom: 5px; }
        .header-section p { color: rgba(255,255,255,0.6); font-size: 13px; margin: 0; }
        .form-section {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 18px;
        }
        .form-section h5 {
            color: #ffd700; font-weight: 700;
            border-bottom: 2px solid #ffd700;
            padding-bottom: 8px; margin-bottom: 18px; font-size: 15px;
        }
        .form-label { font-weight: 500; font-size: 12px; color: #d1d5db; }
        .form-control, .form-select {
            background: rgba(255,255,255,0.06) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            color: #e5e7eb !important;
            border-radius: 10px; padding: 10px 14px; font-size: 13px; height: 42px;
        }
        .form-control.auto-upper { text-transform: uppercase; }
        .form-control:focus, .form-select:focus {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 3px rgba(255,215,0,0.15) !important;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.3) !important; text-transform: none !important; }
        .form-select option { background: #131926; color: #e5e7eb; }
        .form-check-label { color: #d1d5db; font-size: 13px; }
        .form-check-input {
            background-color: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
        }
        .form-check-input:checked {
            background-color: #ffd700;
            border-color: #ffd700;
        }
        .required { color: #ef4444; }
        .photo-upload {
            text-align: center; padding: 25px;
            border: 2px dashed rgba(255,215,0,0.3);
            border-radius: 16px; background: rgba(255,215,0,0.03);
            transition: all 0.3s ease; cursor: pointer;
        }
        .photo-upload:hover { border-color: #ffd700; background: rgba(255,215,0,0.08); }
        .photo-upload .preview {
            width: 120px; height: 120px; border-radius: 50%;
            object-fit: cover; border: 3px solid #ffd700;
            margin-bottom: 15px; display: none;
        }
        .photo-upload .icon { font-size: 48px; color: rgba(255,215,0,0.5); margin-bottom: 10px; }
        .btn-submit {
            background: linear-gradient(135deg, #ffd700, #f59e0b);
            border: none; padding: 15px 40px; border-radius: 12px;
            font-weight: 700; font-size: 15px; color: #0a1628;
            transition: all 0.3s ease; width: 100%; max-width: 300px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255,215,0,0.3); color: #0a1628;
        }
        .alert-custom { padding: 18px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
        .alert-success { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }
        .alert-danger { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
        .footer-section { text-align: center; padding: 20px; color: rgba(255,255,255,0.3); font-size: 12px; }
        .footer-section a { color: #ffd700; text-decoration: none; }
        .success-box {
            background: rgba(16,185,129,0.1);
            border: 2px solid #10b981;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
        }
        .success-box .student-id {
            font-size: 32px;
            font-weight: 800;
            color: #ffd700;
            letter-spacing: 3px;
            margin: 15px 0;
            font-family: monospace;
        }
        @media (max-width: 768px) {
            .form-section { padding: 18px; }
            .header-section { padding: 20px; }
            .header-section h1 { font-size: 18px; }
            .success-box .student-id { font-size: 22px; letter-spacing: 1px; }
        }
    </style>
</head>
<body>
    <div class="container-main">
        
        <div class="header-section">
            <img src="../assets/images/isu-logo.png" alt="ISU Logo" class="logo">
            <h1>ISU-E LADIES DORMITORY</h1>
            <p>Isabela State University · Echague, Isabela</p>
            <p style="margin-top: 8px; color: #ffd700; font-weight: 600;">Student Self-Registration</p>
            <p style="font-size: 11px; margin-top: 5px;">Fill up all required fields. Wait for admin approval.</p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="success-box">
                <i class="fas fa-check-circle fa-3x" style="color: #10b981;"></i>
                <h3 style="color: #10b981; margin-top: 15px;">Registration Submitted!</h3>
                <p style="color: rgba(255,255,255,0.7);">Your Student ID:</p>
                <div class="student-id"><?php echo htmlspecialchars($success); ?></div>
                <p style="color: #fbbf24; font-weight: 600;">
                    <i class="fas fa-clock me-1"></i> Status: PENDING APPROVAL
                </p>
                <p style="color: rgba(255,255,255,0.5); font-size: 13px; margin-top: 15px;">
                    ⚠️ SAVE YOUR STUDENT ID! You will need this to create your portal account after admin approval.
                </p>
                <div class="mt-3">
                    <a href="student-portal-register.php" class="btn btn-submit" style="display: inline-block; text-decoration: none;">
                        <i class="fas fa-user-plus me-2"></i> Create Portal Account
                    </a>
                    <a href="student-self-registration.php" class="btn" style="display: inline-block; padding: 15px 30px; border-radius: 12px; background: #2a2a4a; color: #e0e0e0; text-decoration: none; margin-left: 10px;">
                        <i class="fas fa-plus me-1"></i> Register Another
                    </a>
                </div>
            </div>
        <?php else: ?>

            <?php if (!empty($error)): ?>
                <div class="alert-custom alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" id="registrationForm">
                
                <!-- PHOTO UPLOAD -->
                <div class="form-section">
                    <h5><i class="fas fa-camera me-2"></i>Profile Photo <span class="required">*</span></h5>
                    <div class="photo-upload" onclick="document.getElementById('photoInput').click()">
                        <img src="" alt="Preview" class="preview" id="photoPreview">
                        <div class="icon" id="photoIcon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <p style="color: #d1d5db; margin: 0; font-weight: 600;">Click to Upload Photo</p>
                        <p style="color: rgba(255,255,255,0.4); font-size: 11px; margin: 5px 0 0 0;">
                            JPG, PNG, GIF, WEBP · Max 2MB
                        </p>
                        <input type="file" id="photoInput" name="profile_photo" accept="image/*" 
                               style="display: none;" required onchange="previewPhoto(this)">
                    </div>
                </div>

                <!-- PERSONAL INFORMATION -->
                <div class="form-section">
                    <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="full_name" 
                                   placeholder="Enter your full name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender <span class="required">*</span></label>
                            <div class="d-flex flex-wrap gap-3 pt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gender" value="Female" id="genderFemale" required>
                                    <label class="form-check-label" for="genderFemale">Female</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gender" value="Male" id="genderMale">
                                    <label class="form-check-label" for="genderMale">Male</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gender" value="LGBT" id="genderLGBT">
                                    <label class="form-check-label" for="genderLGBT">LGBTQ+</label>
                                </div>
                            </div>
                            <input type="text" class="form-control auto-upper mt-2" name="gender_other" 
                                   placeholder="If LGBTQ+, please specify" style="height: 34px; font-size: 12px;">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Birth Date <span class="required">*</span></label>
                            <input type="date" class="form-control" name="birth_date" id="birthDate" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control" name="age" id="ageInput" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Birth No.</label>
                            <input type="text" class="form-control auto-upper" name="birth_no" placeholder="Birth Cert. No.">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No. of Siblings</label>
                            <input type="text" class="form-control auto-upper" name="no_siblings" placeholder="e.g., 3 siblings">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="cp_no" placeholder="09XXXXXXXXX" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Civil Status</label>
                            <select class="form-select" name="civil_status">
                                <option value="">Select</option>
                                <option value="Married">Married</option>
                                <option value="Single">Single</option>
                                <option value="Separated">Separated</option>
                                <option value="Abandoned">Abandoned</option>
                                <option value="Live-in">Live-in</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Complete Home Address <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="home_address" 
                                   placeholder="House No., Street, Barangay, Municipality, Province" required>
                        </div>
                    </div>
                </div>

                <!-- ACADEMIC INFORMATION -->
                <div class="form-section">
                    <h5><i class="fas fa-graduation-cap me-2"></i>Academic Information</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Course <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="course" 
                                   placeholder="e.g., BSIT, BSED" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Year Level <span class="required">*</span></label>
                            <select class="form-select" name="year_level" required>
                                <option value="">Select</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                                <option value="5th Year">5th Year</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Scholarship Grant</label>
                            <input type="text" class="form-control auto-upper" name="scholarship" 
                                   placeholder="If none, type 'None'">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">School Last Attended</label>
                            <input type="text" class="form-control auto-upper" name="school_last" placeholder="School name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">School Address</label>
                            <input type="text" class="form-control auto-upper" name="school_address" placeholder="School address">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Other Sources of Allowance for School</label>
                            <input type="text" class="form-control auto-upper" name="allowance_source" 
                                   placeholder="e.g., Parents, Part-time job">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cultural Origin</label>
                            <input type="text" class="form-control auto-upper" name="cultural_origin" placeholder="e.g., Ilocano">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Religion</label>
                            <input type="text" class="form-control auto-upper" name="religion" placeholder="Religion">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dialect Spoken</label>
                            <input type="text" class="form-control auto-upper" name="dialect" placeholder="e.g., Ilocano">
                        </div>
                    </div>
                </div>

                <!-- PARENT / GUARDIAN INFORMATION -->
                <div class="form-section">
                    <h5><i class="fas fa-users me-2"></i>Parent / Guardian Information</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Father's Highest Educational Attainment</label>
                            <input type="text" class="form-control auto-upper" name="father_education" placeholder="e.g., College Graduate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mother's Highest Educational Attainment</label>
                            <input type="text" class="form-control auto-upper" name="mother_education" placeholder="e.g., High School Graduate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father's Occupation</label>
                            <input type="text" class="form-control auto-upper" name="father_occupation" placeholder="Occupation">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mother's Occupation</label>
                            <input type="text" class="form-control auto-upper" name="mother_occupation" placeholder="Occupation">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Parent's Marital Status</label>
                            <div class="d-flex flex-wrap gap-3 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="parents_marital_status" value="Living Together" id="livingTogether">
                                    <label class="form-check-label" for="livingTogether">Living Together</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="parents_marital_status" value="Separated" id="separated">
                                    <label class="form-check-label" for="separated">Separated</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="parents_marital_status" value="Abandoned" id="abandoned">
                                    <label class="form-check-label" for="abandoned">Abandoned</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="parents_marital_status" value="Mother with Other Family" id="motherOther">
                                    <label class="form-check-label" for="motherOther">Mother with Other</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="parents_marital_status" value="Father with Other Family" id="fatherOther">
                                    <label class="form-check-label" for="fatherOther">Father with Other</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EMERGENCY CONTACT -->
                <div class="form-section">
                    <h5><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="emergency_name" 
                                   placeholder="Emergency contact name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Relationship <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="emergency_relationship" 
                                   placeholder="e.g., Mother, Father" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Address <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="emergency_address" 
                                   placeholder="Complete address" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact No. <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="emergency_contact" 
                                   placeholder="09XXXXXXXXX" required>
                        </div>
                    </div>
                </div>

                <!-- BOARDING HISTORY -->
                <div class="form-section">
                    <h5><i class="fas fa-home me-2"></i>Boarding History</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Length of Stay in Former Boarding House</label>
                            <input type="text" class="form-control auto-upper" name="former_boarding_years" 
                                   placeholder="e.g., 2 years, 6 months">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Do you plan to transfer to another boarding house after this semester?</label>
                            <div class="d-flex gap-3 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="plan_transfer" value="Yes" id="planYes">
                                    <label class="form-check-label" for="planYes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="plan_transfer" value="No" id="planNo">
                                    <label class="form-check-label" for="planNo">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" id="planYesDiv" style="display:none;">
                            <label class="form-label">Why, If Yes?</label>
                            <input type="text" class="form-control auto-upper" name="plan_transfer_yes" 
                                   placeholder="Reason for transferring">
                        </div>
                        <div class="col-md-6" id="planNoDiv" style="display:none;">
                            <label class="form-label">Why, If No?</label>
                            <input type="text" class="form-control auto-upper" name="plan_transfer_no" 
                                   placeholder="Reason for staying">
                        </div>
                    </div>
                </div>

                <!-- SUBMIT -->
                <div class="text-center mb-4">
                    <button type="submit" name="submit_registration" class="btn-submit">
                        <i class="fas fa-paper-plane me-2"></i> Submit Registration
                    </button>
                    <p style="color: rgba(255,255,255,0.4); font-size: 12px; margin-top: 12px;">
                        <i class="fas fa-info-circle me-1"></i>
                        After submitting, wait for admin approval.
                    </p>
                </div>

            </form>
        <?php endif; ?>

        <div class="footer-section">
            &copy; <?php echo date('Y'); ?> <span>Isabela State University</span> · Ladies Dormitory
            <br>
            <a href="login.php"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
        </div>
    </div>

    <script>
        // AUTO UPPERCASE
        document.querySelectorAll('.auto-upper').forEach(function(input) {
            input.addEventListener('input', function() {
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(start, end);
            });
        });

        // PHOTO PREVIEW
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('photoPreview');
                    const icon = document.getElementById('photoIcon');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    icon.style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // AUTO AGE
        document.getElementById('birthDate')?.addEventListener('change', function() {
            if (this.value) {
                const birth = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                const m = today.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                if (age > 0) document.getElementById('ageInput').value = age;
            }
        });

        // TOGGLE PLAN TRANSFER
        document.querySelectorAll('input[name="plan_transfer"]').forEach(function(el) {
            el.addEventListener('change', function() {
                if (this.value === 'Yes') {
                    document.getElementById('planYesDiv').style.display = 'block';
                    document.getElementById('planNoDiv').style.display = 'none';
                } else if (this.value === 'No') {
                    document.getElementById('planYesDiv').style.display = 'none';
                    document.getElementById('planNoDiv').style.display = 'block';
                }
            });
        });
    </script>
</body>
</html>
