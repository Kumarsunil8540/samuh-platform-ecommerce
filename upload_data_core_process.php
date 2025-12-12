<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("config.php");

// Security check
if (!isset($_SESSION['user_verified']) || !$_SESSION['user_verified'] || !isset($_SESSION['role']) || !isset($_SESSION['group_id'])) {
    showError("❌ Unauthorized Access! Please verify your identity first.");
}

$role = strtolower(trim($_SESSION['role']));
$group_id = intval($_SESSION['group_id']);

// Allow only valid roles
$allowed_roles = ['leader', 'admin', 'accountant'];
if (!in_array($role, $allowed_roles)) {
    showError("❌ Invalid Role Access!");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Input data
    $username   = trim($_POST['username']);
    $password   = trim($_POST['password']);
    $full_name  = trim($_POST['full_name']);
    $mobile     = trim($_POST['mobile']);
    $email      = trim($_POST['email']);
    $dob        = trim($_POST['dob']);
    $address    = trim($_POST['address']);
    $pan_number = trim($_POST['pan_number']);

    // Validate inputs
    if (empty($username) || empty($password) || empty($full_name) || empty($mobile) || 
        empty($email) || empty($dob) || empty($address) || empty($pan_number)) {
        showError("❌ सभी फ़ील्ड भरें / Please fill all fields");
    }

    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        showError("❌ मोबाइल नंबर 10 अंकों का होना चाहिए / Mobile number must be 10 digits");
    }

    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_number)) {
        showError("❌ अमान्य पैन नंबर / Invalid PAN number");
    }

    if (strlen($password) < 6) {
        showError("❌ पासवर्ड कम से कम 6 अक्षरों का होना चाहिए / Password must be at least 6 characters");
    }

    // Create upload directory
    $upload_dir = "uploads/kyc/";
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            showError("❌ Upload directory creation failed!");
        }
    }

    // File upload function with validation
    function upload_file($file_input_name, $upload_dir, $max_size_mb = 5) {
        if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES[$file_input_name];
        $max_size = $max_size_mb * 1024 * 1024;
        
        // Validate file size
        if ($file['size'] > $max_size) {
            showError("❌ File too large: " . $file_input_name . " (Max: " . $max_size_mb . "MB)");
        }

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        if (!in_array($file['type'], $allowed_types)) {
            showError("❌ Invalid file type for " . $file_input_name . ". Only JPG, PNG, PDF allowed.");
        }

        // Generate safe filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_filename = time() . "_" . uniqid() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_input_name) . "." . $file_extension;
        $target_path = $upload_dir . $safe_filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            return $target_path;
        }
        
        return null;
    }

    // Upload all files
    $pan_proof_path       = upload_file("pan_proof", $upload_dir, 5);
    $aadhaar_proof_path   = upload_file("aadhaar_proof", $upload_dir, 5);
    $bank_proof_path      = upload_file("bank_proof", $upload_dir, 5);
    $signature_proof_path = upload_file("signature_proof", $upload_dir, 2);
    $profile_photo_path   = upload_file("profile_photo", $upload_dir, 2);

    // Check if all required files were uploaded
    if (!$pan_proof_path || !$aadhaar_proof_path || !$bank_proof_path || 
        !$signature_proof_path || !$profile_photo_path) {
        showError("❌ सभी दस्तावेज़ अपलोड करें / Please upload all required documents");
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Dynamic table name
    $table_name = $role . "s";

    try {
        // Check if user already exists
        $check_sql = "SELECT id FROM $table_name WHERE group_id = ? AND (username = ? OR mobile = ? OR email = ?)";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$group_id, $username, $mobile, $email]);
        
        if ($check_stmt->rowCount() > 0) {
            showError("❌ यूज़रनेम, मोबाइल या ईमेल पहले से मौजूद है / Username, mobile or email already exists");
        }

        // Insert data
        $sql = "INSERT INTO $table_name 
                (group_id, full_name, mobile, email, username, password_hash, dob, address,
                 pan_number, pan_proof_path, aadhaar_proof_path, bank_proof_path, 
                 signature_proof_path, profile_photo_path, is_active)
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

        $stmt = $conn->prepare($sql);
        $success = $stmt->execute([
            $group_id, $full_name, $mobile, $email, $username, $password_hash, $dob, $address,
            $pan_number, $pan_proof_path, $aadhaar_proof_path, $bank_proof_path,
            $signature_proof_path, $profile_photo_path
        ]);

        if ($success) {
            // Update session
            $_SESSION['user_id'] = $conn->lastInsertId();
            $_SESSION['user_name'] = $full_name;
            $_SESSION['kyc_completed'] = true;
            
            showSuccess($role);
        } else {
            showError("❌ डेटा सेव करने में त्रुटि हुई! कृपया पुनः प्रयास करें। / Error saving data! Please try again.");
        }
        
    } catch (PDOException $e) {
        // Cleanup uploaded files on error
        $uploaded_files = [$pan_proof_path, $aadhaar_proof_path, $bank_proof_path, $signature_proof_path, $profile_photo_path];
        foreach ($uploaded_files as $file) {
            if ($file && file_exists($file)) {
                unlink($file);
            }
        }
        showError("❌ डेटाबेस त्रुटि: " . $e->getMessage());
    }
} else {
    header("Location: upload_data_core.php");
    exit();
}

function showError($message) {
    echo "
    <!DOCTYPE html>
    <html lang='hi'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error - KYC Submission</title>
        <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
        <style>
            body { 
                font-family: 'Poppins', sans-serif; 
                background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
                margin: 0; padding: 20px; min-height: 100vh;
                display: flex; align-items: center; justify-content: center;
            }
            .error-card {
                background: white; padding: 40px; border-radius: 20px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.1); max-width: 500px;
                text-align: center; border: 2px solid #dc3545;
            }
            .error-icon { 
                font-size: 4rem; color: #dc3545; margin-bottom: 20px; 
            }
            .btn-retry {
                display: inline-block; padding: 12px 30px;
                background: #dc3545; color: white; text-decoration: none;
                border-radius: 25px; margin-top: 20px; font-weight: 600;
                transition: all 0.3s ease;
            }
            .btn-retry:hover {
                background: #c82333; transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(220,53,69,0.3);
            }
            .hindi { font-weight: 500; margin-bottom: 5px; }
            .english { color: #6c757d; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class='error-card'>
            <div class='error-icon'>❌</div>
            
            <h1 class='hindi'>KYC जमा विफल!</h1>
            <h1 class='english'>KYC Submission Failed!</h1>
            
            <p class='hindi'>" . htmlspecialchars($message) . "</p>
            <p class='english'>" . htmlspecialchars($message) . "</p>
            
            <a href='upload_data_core.php' class='btn-retry'>
                <span class='hindi'>फिर से प्रयास करें</span>
                <span class='english'>Try Again</span>
            </a>
        </div>
    </body>
    </html>
    ";
    exit();
}

function showSuccess($role) {
    $dashboard_page = $role . '_dashboard.php';
    
    echo "
    <!DOCTYPE html>
    <html lang='hi'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Success - KYC Submitted</title>
        <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
        <style>
            body { 
                font-family: 'Poppins', sans-serif; 
                background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
                margin: 0; padding: 20px; min-height: 100vh;
                display: flex; align-items: center; justify-content: center;
            }
            .success-card {
                background: white; padding: 40px; border-radius: 20px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.1); max-width: 500px;
                text-align: center; border: 2px solid #28a745;
            }
            .success-icon { 
                font-size: 4rem; color: #28a745; margin-bottom: 20px; 
            }
            .btn-dashboard {
                display: inline-block; padding: 14px 35px;
                background: #007bff; color: white; text-decoration: none;
                border-radius: 25px; margin: 15px 10px; font-weight: 600;
                transition: all 0.3s ease;
            }
            .btn-dashboard:hover {
                background: #0056b3; transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,123,255,0.3);
            }
            .hindi { font-weight: 500; margin-bottom: 3px; }
            .english { color: #6c757d; margin-bottom: 10px; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='success-card'>
            <div class='success-icon'>✅</div>
            
            <h1 class='hindi'>KYC सफलतापूर्वक जमा!</h1>
            <h1 class='english'>KYC Successfully Submitted!</h1>
            
            <p class='hindi'>✅ आपका डेटा और KYC दस्तावेज़ सफलतापूर्वक जमा हो गए हैं</p>
            <p class='english'>✅ Your data and KYC documents have been successfully submitted</p>
            
            <p class='hindi'>✅ आपका अकाउंट सक्रिय हो गया है</p>
            <p class='english'>✅ Your account has been activated</p>
            
            <a href='$dashboard_page' class='btn-dashboard'>
                <span class='hindi'>डैशबोर्ड पर जाएं</span>
                <span class='english'>Go to Dashboard</span>
            </a>
        </div>
    </body>
    </html>
    ";
    exit();
}
?>