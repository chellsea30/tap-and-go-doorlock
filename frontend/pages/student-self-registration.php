<?php
/**
 * Tap-and-Go Doorlock - Student Self-Registration Form
 * Public - No login required
 * Location: frontend/pages/student-self-registration.php
 * ✅ ALL FIELDS REQUIRED - Hindi pwedeng mag-skip
 * ✅ AUTO-SCROLL sa unang missing field
 * ✅ RED HIGHLIGHT sa missing fields
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
    
    // ============================================================
    // ✅ LAHAT NG FIELDS AY REQUIRED
    // ============================================================
    $required_fields = [
        'full_name' => 'Full Name',
        'gender' => 'Gender',
        'birth_date' => 'Birth Date',
        'birth_no' => 'Birth Certificate No.',
        'no_siblings' => 'Number of Siblings',
        'cp_no' => 'Contact Number',
        'civil_status' => 'Civil Status',
        'home_address' => 'Complete Home Address',
        'course' => 'Course',
        'year_level' => 'Year Level',
        'scholarship' => 'Scholarship Grant',
        'school_last' => 'School Last Attended',
        'school_address' => 'School Address',
        'allowance_source' => 'Other Sources of Allowance',
        'cultural_origin' => 'Cultural Origin',
        'religion' => 'Religion',
        'dialect' => 'Dialect Spoken',
        'father_education' => "Father's Education",
        'mother_education' => "Mother's Education",
        'father_occupation' => "Father's Occupation",
        'mother_occupation' => "Mother's Occupation",
        'parents_marital_status' => "Parent's Marital Status",
        'emergency_name' => 'Emergency Contact Name',
        'emergency_relationship' => 'Emergency Relationship',
        'emergency_address' => 'Emergency Address',
        'emergency_contact' => 'Emergency Contact Number',
        'former_boarding_years' => 'Length of Stay in Former Boarding House',
        'plan_transfer' => 'Plan to Transfer'
    ];
    
    $missing_fields = [];
    foreach ($required_fields as $field_key => $field_label) {
        if (empty($$field_key)) {
            $missing_fields[] = $field_key;
        }
    }
    
    // ✅ Conditional validation para sa plan_transfer
    if ($plan_transfer == 'Yes' && empty($plan_transfer_yes)) {
        $missing_fields[] = 'plan_transfer_yes';
    }
    if ($plan_transfer == 'No' && empty($plan_transfer_no)) {
        $missing_fields[] = 'plan_transfer_no';
    }
    
    // ✅ Check profile photo
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $missing_fields[] = 'profile_photo';
    }
    
    if (!empty($missing_fields)) {
        // Build error message
        $error = '⚠️ Please fill in ALL required fields. ' . count($missing_fields) . ' field(s) missing.';
        
        // ✅ I-store ang missing fields para sa JS highlighting
        $missing_fields_json = json_encode($missing_fields);
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
                
                // Insert into resident_profiles
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
                
                $types = "i" . str_repeat("s", 4) . "i" . str_repeat("s", 26);
                
                $profileStmt->bind_param($types,
                    $user_id, $date_registered, $gender, $gender_other, $birth_date, $age,
                    $birth_no, $course, $year_level, $no_siblings, $scholarship, $allowance_source,
                    $school_last, $school_address, $cultural_origin, $religion, $dialect,
                    $home_address, $civil_status, $father_education, $mother_education,
                    $father_occupation, $mother_occupation, $emergency_name, $emergency_relationship,
                    $emergency_address, $emergency_contact, $parents_marital_status,
                    $former_boarding_years, $plan_transfer, $plan_transfer_yes, $plan_transfer_no
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
            transition: all 0.3s ease;
        }
        /* ✅ SECTION WITH MISSING FIELDS */
        .form-section.has-error {
            border-color: rgba(239,68,68,0.5);
            background: rgba(239,68,68,0.05);
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
            transition: all 0.3s ease;
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
        
        /* ✅ BAGO: ERROR HIGHLIGHT */
        .form-control.error-field,
        .form-select.error-field {
            border-color: #ef4444 !important;
            background: rgba(239,68,68,0.08) !important;
            animation: shake 0.5s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .form-check-input.error-field {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239,68,68,0.3);
        }
        .error-message {
            color: #fca5a5;
            font-size: 11px;
            margin-top: 3px;
            display: none;
        }
        .error-message.show { display: block; }
        
        .photo-upload {
            text-align: center; padding: 25px;
            border: 2px dashed rgba(255,215,0,0.3);
            border-radius: 16px; background: rgba(255,215,0,0.03);
            transition: all 0.3s ease; cursor: pointer;
        }
        .photo-upload:hover { border-color: #ffd700; background: rgba(255,215,0,0.08); }
        .photo-upload.error-field {
            border-color: #ef4444 !important;
            background: rgba(239,68,68,0.08) !important;
            animation: shake 0.5s ease;
        }
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
        
        /* ✅ BAGO: Required notice banner */
        .required-notice {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 20px;
            color: #fca5a5;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .required-notice i {
            color: #ef4444;
            font-size: 20px;
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
                    <strong><?php echo htmlspecialchars($error); ?></strong>
                </div>
            <?php endif; ?>

            <!-- ✅ REQUIRED NOTICE -->
            <div class="required-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>ALL FIELDS ARE REQUIRED</strong> — Hindi pwedeng mag-skip ng kahit anong field.
                    Kung may kulang, awtomatikong babalik ka sa field na kailangan mong punan.
                </div>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" id="registrationForm" novalidate>
                
                <!-- PHOTO UPLOAD -->
                <div class="form-section" id="section_photo">
                    <h5><i class="fas fa-camera me-2"></i>Profile Photo <span class="required">*</span></h5>
                    <div class="photo-upload" id="photoUploadBox" onclick="document.getElementById('photoInput').click()">
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
                    <div class="error-message" id="error_profile_photo">Profile photo is required</div>
                </div>

                <!-- PERSONAL INFORMATION -->
                <div class="form-section" id="section_personal">
                    <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="full_name" id="full_name"
                                   placeholder="Enter your full name" required>
                            <div class="error-message" id="error_full_name">Full name is required</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender <span class="required">*</span></label>
                            <div class="d-flex flex-wrap gap-3 pt-2" id="genderGroup">
                                <div class="form-check">
                                    <input class="form-check-input gender-radio" type="radio" name="gender" value="Female" id="genderFemale" required>
                                    <label class="form-check-label" for="genderFemale">Female</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input gender-radio" type="radio" name="gender" value="Male" id="genderMale">
                                    <label class="form-check-label" for="genderMale">Male</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input gender-radio" type="radio" name="gender" value="LGBT" id="genderLGBT">
                                    <label class="form-check-label" for="genderLGBT">LGBTQ+</label>
                                </div>
                            </div>
                            <input type="text" class="form-control auto-upper mt-2" name="gender_other" id="gender_other"
                                   placeholder="If LGBTQ+, please specify" style="height: 34px; font-size: 12px;">
                            <div class="error-message" id="error_gender">Gender is required</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Birth Date <span class="required">*</span></label>
                            <input type="date" class="form-control" name="birth_date" id="birth_date" required>
                            <div class="error-message" id="error_birth_date">Birth date is required</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Age <span class="required">*</span></label>
                            <input type="number" class="form-control" name="age" id="ageInput" readonly required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Birth No. <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="birth_no" id="birth_no" placeholder="Birth Cert. No." required>
                            <div class="error-message" id="error_birth_no">Birth No. is required</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No. of Siblings <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="no_siblings" id="no_siblings" placeholder="e.g., 3 siblings" required>
                            <div class="error-message" id="error_no_siblings">No. of siblings is required</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Number <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="cp_no" id="cp_no" placeholder="09XXXXXXXXX" required>
                            <div class="error-message" id="error_cp_no">Contact number is required</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Civil Status <span class="required">*</span></label>
                            <select class="form-select" name="civil_status" id="civil_status" required>
                                <option value="">Select</option>
                                <option value="Married">Married</option>
                                <option value="Single">Single</option>
                                <option value="Separated">Separated</option>
                                <option value="Abandoned">Abandoned</option>
                                <option value="Live-in">Live-in</option>
                            </select>
                            <div class="error-message" id="error_civil_status">Civil status is required</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Complete Home Address <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="home_address" id="home_address"
                                   placeholder="House No., Street, Barangay, Municipality, Province" required>
                            <div class="error-message" id="error_home_address">Home address is required</div>
                        </div>
                    </div>
                </div>

                <!-- ACADEMIC INFORMATION -->
                <div class="form-section" id="section_academic">
                    <h5><i class="fas fa-graduation-cap me-2"></i>Academic Information</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Course <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="course" id="course"
                                   placeholder="e.g., BSIT, BSED" required>
                            <div class="error-message" id="error_course">Course is required</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Year Level <span class="required">*</span></label>
                            <select class="form-select" name="year_level" id="year_level" required>
                                <option value="">Select</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                                <option value="5th Year">5th Year</option>
                            </select>
                            <div class="error-message" id="error_year_level">Year level is required</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Scholarship Grant <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="scholarship" id="scholarship"
                                   placeholder="If none, type 'None'" required>
                            <div class="error-message" id="error_scholarship">Scholarship is required (type 'None' if none)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">School Last Attended <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="school_last" id="school_last" placeholder="School name" required>
                            <div class="error-message" id="error_school_last">School last attended is required</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">School Address <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="school_address" id="school_address" placeholder="School address" required>
                            <div class="error-message" id="error_school_address">School address is required</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Other Sources of Allowance for School <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="allowance_source" id="allowance_source"
                                   placeholder="e.g., Parents, Part-time job" required>
                            <div class="error-message" id="error_allowance_source">Allowance source is required</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cultural Origin <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="cultural_origin" id="cultural_origin" placeholder="e.g., Ilocano" required>
                            <div class="error-message" id="error_cultural_origin">Cultural origin is required</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Religion <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="religion" id="religion" placeholder="Religion" required>
                            <div class="error-message" id="error_religion">Religion is required</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dialect Spoken <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="dialect" id="dialect" placeholder="e.g., Ilocano" required>
                            <div class="error-message" id="error_dialect">Dialect is required</div>
                        </div>
                    </div>
                </div>

                <!-- PARENT / GUARDIAN INFORMATION -->
                <div class="form-section" id="section_parents">
                    <h5><i class="fas fa-users me-2"></i>Parent / Guardian Information</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Father's Highest Educational Attainment <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="father_education" id="father_education" placeholder="e.g., College Graduate" required>
                            <div class="error-message" id="error_father_education">Father's education is required</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mother's Highest Educational Attainment <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="mother_education" id="mother_education" placeholder="e.g., High School Graduate" required>
                            <div class="error-message" id="error_mother_education">Mother's education is required</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father's Occupation <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="father_occupation" id="father_occupation" placeholder="Occupation" required>
                            <div class="error-message" id="error_father_occupation">Father's occupation is required</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mother's Occupation <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="mother_occupation" id="mother_occupation" placeholder="Occupation" required>
                            <div class="error-message" id="error_mother_occupation">Mother's occupation is required</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Parent's Marital Status <span class="required">*</span></label>
                            <div class="d-flex flex-wrap gap-3 pt-1" id="maritalStatusGroup">
                                <div class="form-check">
                                    <input class="form-check-input marital-radio" type="radio" name="parents_marital_status" value="Living Together" id="livingTogether" required>
                                    <label class="form-check-label" for="livingTogether">Living Together</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input marital-radio" type="radio" name="parents_marital_status" value="Separated" id="separated">
                                    <label class="form-check-label" for="separated">Separated</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input marital-radio" type="radio" name="parents_marital_status" value="Abandoned" id="abandoned">
                                    <label class="form-check-label" for="abandoned">Abandoned</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input marital-radio" type="radio" name="parents_marital_status" value="Mother with Other Family" id="motherOther">
                                    <label class="form-check-label" for="motherOther">Mother with Other</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input marital-radio" type="radio" name="parents_marital_status" value="Father with Other Family" id="fatherOther">
                                    <label class="form-check-label" for="fatherOther">Father with Other</label>
                                </div>
                            </div>
                            <div class="error-message" id="error_parents_marital_status">Parent's marital status is required</div>
                        </div>
                    </div>
                </div>

                <!-- EMERGENCY CONTACT -->
                <div class="form-section" id="section_emergency">
                    <h5><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="emergency_name" id="emergency_name"
                                   placeholder="Emergency contact name" required>
                            <div class="error-message" id="error_emergency_name">Emergency contact name is required</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Relationship <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="emergency_relationship" id="emergency_relationship"
                                   placeholder="e.g., Mother, Father" required>
                            <div class="error-message" id="error_emergency_relationship">Relationship is required</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Address <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="emergency_address" id="emergency_address"
                                   placeholder="Complete address" required>
                            <div class="error-message" id="error_emergency_address">Emergency address is required</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact No. <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="emergency_contact" id="emergency_contact"
                                   placeholder="09XXXXXXXXX" required>
                            <div class="error-message" id="error_emergency_contact">Emergency contact number is required</div>
                        </div>
                    </div>
                </div>

                <!-- BOARDING HISTORY -->
                <div class="form-section" id="section_boarding">
                    <h5><i class="fas fa-home me-2"></i>Boarding History</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Length of Stay in Former Boarding House <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="former_boarding_years" id="former_boarding_years"
                                   placeholder="e.g., 2 years, 6 months" required>
                            <div class="error-message" id="error_former_boarding_years">Length of stay is required</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Do you plan to transfer to another boarding house after this semester? <span class="required">*</span></label>
                            <div class="d-flex gap-3 pt-1" id="planTransferGroup">
                                <div class="form-check">
                                    <input class="form-check-input plan-radio" type="radio" name="plan_transfer" value="Yes" id="planYes" required>
                                    <label class="form-check-label" for="planYes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input plan-radio" type="radio" name="plan_transfer" value="No" id="planNo">
                                    <label class="form-check-label" for="planNo">No</label>
                                </div>
                            </div>
                            <div class="error-message" id="error_plan_transfer">Please select Yes or No</div>
                        </div>
                        <div class="col-md-6" id="planYesDiv" style="display:none;">
                            <label class="form-label">Why, If Yes? <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="plan_transfer_yes" id="plan_transfer_yes"
                                   placeholder="Reason for transferring">
                            <div class="error-message" id="error_plan_transfer_yes">Please provide a reason</div>
                        </div>
                        <div class="col-md-6" id="planNoDiv" style="display:none;">
                            <label class="form-label">Why, If No? <span class="required">*</span></label>
                            <input type="text" class="form-control auto-upper" name="plan_transfer_no" id="plan_transfer_no"
                                   placeholder="Reason for staying">
                            <div class="error-message" id="error_plan_transfer_no">Please provide a reason</div>
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
        // ============================================================
        // AUTO UPPERCASE
        // ============================================================
        document.querySelectorAll('.auto-upper').forEach(function(input) {
            input.addEventListener('input', function() {
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(start, end);
            });
        });

        // ============================================================
        // PHOTO PREVIEW
        // ============================================================
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('photoPreview');
                    const icon = document.getElementById('photoIcon');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    icon.style.display = 'none';
                    
                    // Remove error state
                    document.getElementById('photoUploadBox').classList.remove('error-field');
                    document.getElementById('error_profile_photo').classList.remove('show');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // ============================================================
        // AUTO AGE
        // ============================================================
        document.getElementById('birth_date')?.addEventListener('change', function() {
            if (this.value) {
                const birth = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                const m = today.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                if (age > 0) document.getElementById('ageInput').value = age;
            }
        });

        // ============================================================
        // TOGGLE PLAN TRANSFER
        // ============================================================
        document.querySelectorAll('input[name="plan_transfer"]').forEach(function(el) {
            el.addEventListener('change', function() {
                if (this.value === 'Yes') {
                    document.getElementById('planYesDiv').style.display = 'block';
                    document.getElementById('planNoDiv').style.display = 'none';
                    document.getElementById('plan_transfer_no').value = '';
                } else if (this.value === 'No') {
                    document.getElementById('planYesDiv').style.display = 'none';
                    document.getElementById('planNoDiv').style.display = 'block';
                    document.getElementById('plan_transfer_yes').value = '';
                }
            });
        });

        // ============================================================
        // ✅ REAL-TIME VALIDATION - Remove error kapag may laman na
        // ============================================================
        document.querySelectorAll('.form-control, .form-select').forEach(function(input) {
            input.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('error-field');
                    const errorId = 'error_' + this.name;
                    const errorEl = document.getElementById(errorId);
                    if (errorEl) errorEl.classList.remove('show');
                }
            });
            input.addEventListener('change', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('error-field');
                    const errorId = 'error_' + this.name;
                    const errorEl = document.getElementById(errorId);
                    if (errorEl) errorEl.classList.remove('show');
                }
            });
        });

        // Radio buttons
        document.querySelectorAll('.gender-radio').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.gender-radio').forEach(r => r.classList.remove('error-field'));
                document.getElementById('error_gender').classList.remove('show');
            });
        });
        document.querySelectorAll('.marital-radio').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.marital-radio').forEach(r => r.classList.remove('error-field'));
                document.getElementById('error_parents_marital_status').classList.remove('show');
            });
        });
        document.querySelectorAll('.plan-radio').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.plan-radio').forEach(r => r.classList.remove('error-field'));
                document.getElementById('error_plan_transfer').classList.remove('show');
            });
        });

        // ============================================================
        // ✅ VALIDATION ON SUBMIT - CHECK ALL FIELDS
        // ============================================================
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const missing = [];
            const errorMessages = {};
            
            // 1. Check profile photo
            const photoInput = document.getElementById('photoInput');
            if (!photoInput.files || photoInput.files.length === 0) {
                missing.push('profile_photo');
                document.getElementById('photoUploadBox').classList.add('error-field');
                document.getElementById('error_profile_photo').classList.add('show');
            }
            
            // 2. Check all text/select/date inputs with required attribute
            const requiredInputs = this.querySelectorAll('.form-control[required], .form-select[required]');
            requiredInputs.forEach(function(input) {
                if (!input.value || input.value.trim() === '') {
                    missing.push(input.name);
                    input.classList.add('error-field');
                    const errorId = 'error_' + input.name;
                    const errorEl = document.getElementById(errorId);
                    if (errorEl) errorEl.classList.add('show');
                }
            });
            
            // 3. Check radio groups
            const genderSelected = document.querySelector('input[name="gender"]:checked');
            if (!genderSelected) {
                missing.push('gender');
                document.querySelectorAll('.gender-radio').forEach(r => r.classList.add('error-field'));
                document.getElementById('error_gender').classList.add('show');
            }
            
            const maritalSelected = document.querySelector('input[name="parents_marital_status"]:checked');
            if (!maritalSelected) {
                missing.push('parents_marital_status');
                document.querySelectorAll('.marital-radio').forEach(r => r.classList.add('error-field'));
                document.getElementById('error_parents_marital_status').classList.add('show');
            }
            
            const planSelected = document.querySelector('input[name="plan_transfer"]:checked');
            if (!planSelected) {
                missing.push('plan_transfer');
                document.querySelectorAll('.plan-radio').forEach(r => r.classList.add('error-field'));
                document.getElementById('error_plan_transfer').classList.add('show');
            } else {
                // Check conditional fields
                if (planSelected.value === 'Yes') {
                    const yesVal = document.getElementById('plan_transfer_yes').value.trim();
                    if (!yesVal) {
                        missing.push('plan_transfer_yes');
                        document.getElementById('plan_transfer_yes').classList.add('error-field');
                        document.getElementById('error_plan_transfer_yes').classList.add('show');
                    }
                } else if (planSelected.value === 'No') {
                    const noVal = document.getElementById('plan_transfer_no').value.trim();
                    if (!noVal) {
                        missing.push('plan_transfer_no');
                        document.getElementById('plan_transfer_no').classList.add('error-field');
                        document.getElementById('error_plan_transfer_no').classList.add('show');
                    }
                }
            }
            
            // 4. Kung may kulang, scroll sa unang error
            if (missing.length > 0) {
                e.preventDefault();
                
                // Show alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert-custom alert-danger';
                alertDiv.style.position = 'fixed';
                alertDiv.style.top = '20px';
                alertDiv.style.left = '50%';
                alertDiv.style.transform = 'translateX(-50%)';
                alertDiv.style.zIndex = '9999';
                alertDiv.style.maxWidth = '500px';
                alertDiv.style.boxShadow = '0 10px 40px rgba(0,0,0,0.5)';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i><strong>May ' + missing.length + ' field(s) na hindi pa napunan!</strong> Awtomatikong babalik ka sa unang kulang na field.';
                document.body.appendChild(alertDiv);
                
                setTimeout(() => alertDiv.remove(), 5000);
                
                // Scroll to first error
                const firstError = document.querySelector('.error-field');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(function() {
                        if (firstError.tagName !== 'DIV') firstError.focus();
                    }, 500);
                }
                
                return false;
            }
        });

        // ============================================================
        // ✅ SCROLL TO TOP KAPAG MAY ERROR FROM PHP (page reload)
        // ============================================================
        <?php if (!empty($error)): ?>
        window.addEventListener('DOMContentLoaded', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        <?php endif; ?>
    </script>
</body>
</html>
