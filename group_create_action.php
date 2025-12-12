<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("config.php");

// PHPMailer (composer autoload)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

// Enhanced Mail function
function sendInviteEmail($toemail, $toname, $user_name, $password, $group_ID, $role) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.zoho.in';
        $mail->SMTPAuth = true;
        $mail->Username = 'sunilkumar952323@zohomail.in';
        $mail->Password = 'HG1ZU5KnADyu';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('sunilkumar952323@zohomail.in', 'SAMUH PLATFORM');
        $mail->addAddress($toemail, $toname);

        $mail->isHTML(true);
        
        // Hindi + English Email Content
        $mail->Subject = 'Your Login Credentials - Samuh Platform';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #007bff; text-align: center;'>समूह प्लेटफॉर्म में आपका स्वागत है</h2>
                <h2 style='color: #007bff; text-align: center;'>Welcome to Samuh Platform</h2>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                    <p>नमस्ते <strong>" . htmlspecialchars($toname) . "</strong>,</p>
                    <p>Hello <strong>" . htmlspecialchars($toname) . "</strong>,</p>
                    
                    <p>आपको <strong>" . htmlspecialchars($role) . "</strong> के रूप में समूह में जोड़ा गया है</p>
                    <p>You have been added as <strong>" . htmlspecialchars($role) . "</strong> in the group</p>
                    
                    <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                        <h3 style='color: #28a745;'>आपके लॉगिन क्रेडेंशियल्स:</h3>
                        <h3 style='color: #28a745;'>Your Login Credentials:</h3>
                        
                        <p><strong>समूह आईडी (Group ID):</strong> " . htmlspecialchars($group_ID) . "</p>
                        <p><strong>उपयोगकर्ता नाम (Username):</strong> " . htmlspecialchars($user_name) . "</p>
                        <p><strong>पासवर्ड (Password):</strong> " . htmlspecialchars($password) . "</p>
                    </div>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='http://yourdomain.com/core_member_login.php' 
                           style='background: #007bff; color: white; padding: 12px 30px; 
                                  text-decoration: none; border-radius: 25px; display: inline-block;'>
                            यहाँ लॉगिन करें / Login Here
                        </a>
                    </div>
                    
                    <p><small>लॉगिन के बाद, कृपया अपने दस्तावेज़ अपलोड करें और विवरण सत्यापित करें</small></p>
                    <p><small>After login, please upload your documents and verify your details</small></p>
                </div>
                
                <div style='text-align: center; color: #6c757d; font-size: 0.9em;'>
                    <p>धन्यवाद,<br><strong>समूह प्लेटफॉर्म टीम</strong></p>
                    <p>Thank You,<br><strong>Samuh Platform Team</strong></p>
                </div>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// MAIN: Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_name'])) {
    
    // Sanitize and validate inputs
    $group_name = trim($_POST['group_name']);
    $tenure_months = (int)$_POST['tenure_months'];
    $expected_amount = (float)$_POST['expected_amount'];
    $min_members_count = (int)$_POST['min_members_count'];
    $group_conditions = trim($_POST['group_conditions']);
    $payment_cycle = trim($_POST['payment_cycle']); // NEW: Payment cycle

    // Validate required fields
    if (empty($group_name) || empty($group_conditions) || empty($payment_cycle)) {
        die("Error: Group name, conditions and payment cycle are required.");
    }

    // Validate payment cycle
    if (!in_array($payment_cycle, ['weekly', 'monthly'])) {
        die("Error: Invalid payment cycle selected.");
    }

    // Core members data
    $core_members = [
        'leader' => [
            'name' => trim($_POST['owner_name'] ?? ''),
            'mobile' => trim($_POST['owner_mobile'] ?? ''),
            'email' => trim($_POST['owner_email'] ?? ''),
            'role' => 'Leader'
        ],
        'admin' => [
            'name' => trim($_POST['admin_name'] ?? ''),
            'mobile' => trim($_POST['admin_mobile'] ?? ''),
            'email' => trim($_POST['admin_email'] ?? ''),
            'role' => 'Admin'
        ],
        'accountant' => [
            'name' => trim($_POST['accountant_name'] ?? ''),
            'mobile' => trim($_POST['accountant_mobile'] ?? ''),
            'email' => trim($_POST['accountant_email'] ?? ''),
            'role' => 'Accountant'
        ]
    ];

    // Validate core members
    foreach ($core_members as $role => $member) {
        if (empty($member['name']) || empty($member['mobile']) || empty($member['email'])) {
            die("Error: All core member details are required.");
        }
        
        // Validate mobile number
        if (!preg_match('/^[0-9]{10}$/', $member['mobile'])) {
            die("Error: Invalid mobile number for " . $role);
        }
        
        // Validate email
        if (!filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
            die("Error: Invalid email address for " . $role);
        }
    }

    // Stamp upload handling
    $stamp_upload_folder = "uploads/stamp_100/";
    if (!is_dir($stamp_upload_folder)) {
        if (!mkdir($stamp_upload_folder, 0755, true)) {
            die("Error: Failed to create folder for stamp upload.");
        }
    }

    if (!isset($_FILES['stamp_upload']) || $_FILES['stamp_upload']['error'] !== UPLOAD_ERR_OK) {
        die("Error: Stamp file missing or upload error.");
    }

    $stamp_filename = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['stamp_upload']['name']));
    $stamp_upload_path = $stamp_upload_folder . $stamp_filename;
    
    // Validate file type and size
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($_FILES['stamp_upload']['type'], $allowed_types)) {
        die("Error: Only PDF, JPG, and PNG files are allowed.");
    }
    
    if ($_FILES['stamp_upload']['size'] > $max_size) {
        die("Error: File size must be less than 5MB.");
    }

    if (!move_uploaded_file($_FILES['stamp_upload']['tmp_name'], $stamp_upload_path)) {
        die("Error: Stamp upload failed.");
    }

    // Insert into groups table
    try {
        $sql = "INSERT INTO groups
            (group_name, tenure_months, expected_amount, min_members_count, group_conditions, stamp_upload_path,
             leader_name, leader_mobile, leader_email,
             admin_name, admin_mobile, admin_email,
             accountant_name, accountant_mobile, accountant_email,
             payment_cycle)  -- NEW: payment_cycle column
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";  // 16 parameters now

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $group_name,
            $tenure_months,
            $expected_amount,
            $min_members_count,
            $group_conditions,
            $stamp_upload_path,
            $core_members['leader']['name'],
            $core_members['leader']['mobile'],
            $core_members['leader']['email'],
            $core_members['admin']['name'],
            $core_members['admin']['mobile'],
            $core_members['admin']['email'],
            $core_members['accountant']['name'],
            $core_members['accountant']['mobile'],
            $core_members['accountant']['email'],
            $payment_cycle  // NEW: payment cycle value
        ]);

        $group_id = $conn->lastInsertId();

        // Send invite emails to each core member
        $email_results = [];
        foreach ($core_members as $role => $person) {
            $toemail = $person['email'];
            $toname = $person['name'];
            $username_to_send = $person['name'];
            $password_to_send = $person['mobile'];

            // Send email
            $mail_result = sendInviteEmail($toemail, $toname, $username_to_send, $password_to_send, $group_id, $person['role']);
            
            if ($mail_result === true) {
                $email_results[] = "✅ Email sent successfully to " . htmlspecialchars($toemail);
            } else {
                $email_results[] = "❌ Error sending email to " . htmlspecialchars($toemail) . ": " . htmlspecialchars($mail_result);
            }
        }

        // Success response with better styling
        echo "
        <!DOCTYPE html>
        <html lang='hi'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Registration Successful - Samuh Platform</title>
            <style>
                body { 
                    font-family: 'Poppins', sans-serif; 
                    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                    margin: 0; padding: 20px; min-height: 100vh;
                    display: flex; align-items: center; justify-content: center;
                }
                .success-card {
                    background: white; padding: 40px; border-radius: 16px;
                    box-shadow: 0 8px 25px rgba(0,0,0,0.1); max-width: 600px;
                    text-align: center; border: 2px solid #28a745;
                }
                .success-icon { 
                    font-size: 4rem; color: #28a745; margin-bottom: 20px; 
                }
                .hindi { font-weight: 500; margin-bottom: 5px; }
                .english { color: #6c757d; margin-bottom: 15px; }
                .group-id { 
                    background: #e9f5ff; padding: 10px; border-radius: 8px;
                    font-size: 1.2em; margin: 20px 0; border-left: 4px solid #007bff;
                }
                .email-results { 
                    text-align: left; margin: 20px 0; padding: 15px;
                    background: #f8f9fa; border-radius: 8px;
                }
                .btn-home {
                    display: inline-block; padding: 12px 30px;
                    background: #007bff; color: white; text-decoration: none;
                    border-radius: 25px; margin-top: 20px; font-weight: 600;
                    transition: all 0.3s ease;
                }
                .btn-home:hover {
                    background: #0056b3; transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
                }
            </style>
        </head>
        <body>
            <div class='success-card'>
                <div class='success-icon'>✅</div>
                
                <h1 class='hindi'>समूह पंजीकरण सफल!</h1>
                <h1 class='english'>Group Registration Successful!</h1>
                
                <div class='group-id'>
                    <strong class='hindi'>आपका समूह आईडी:</strong>
                    <strong class='english'>Your Group ID:</strong>
                    <br>
                    <span style='font-size: 1.5em; color: #007bff;'>#$group_id</span>
                </div>
                
                <p class='hindi'>✅ समूह की जानकारी सफलतापूर्वक सहेजी गई</p>
                <p class='english'>✅ Group information saved successfully</p>
                
                <p class='hindi'>✅ भुगतान चक्र: " . ($payment_cycle === 'weekly' ? 'साप्ताहिक' : 'मासिक') . "</p>
                <p class='english'>✅ Payment Cycle: " . ucfirst($payment_cycle) . "</p>
                
                <div class='email-results'>
                    <h3 class='hindi'>ईमेल स्थिति:</h3>
                    <h3 class='english'>Email Status:</h3>
                    " . implode('<br>', array_map('htmlspecialchars', $email_results)) . "
                </div>
                
                <p class='hindi'>मुख्य सदस्यों को लॉगिन क्रेडेंशियल्स ईमेल से भेजे गए हैं</p>
                <p class='english'>Core members have been sent login credentials via email</p>
                
                <a href='index.php' class='btn-home'>
                    <span class='hindi'>होम पेज पर जाएं</span>
                    <span class='english'>Go to Home Page</span>
                </a>
            </div>
        </body>
        </html>
        ";

    } catch (PDOException $e) {
        // Cleanup uploaded file on error
        if (file_exists($stamp_upload_path)) {
            unlink($stamp_upload_path);
        }
        
        // Error response
        echo "
        <!DOCTYPE html>
        <html lang='hi'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Registration Failed - Samuh Platform</title>
            <style>
                body { 
                    font-family: 'Poppins', sans-serif; 
                    background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
                    margin: 0; padding: 20px; min-height: 100vh;
                    display: flex; align-items: center; justify-content: center;
                }
                .error-card {
                    background: white; padding: 40px; border-radius: 16px;
                    box-shadow: 0 8px 25px rgba(0,0,0,0.1); max-width: 600px;
                    text-align: center; border: 2px solid #dc3545;
                }
                .error-icon { 
                    font-size: 4rem; color: #dc3545; margin-bottom: 20px; 
                }
                .btn-retry {
                    display: inline-block; padding: 12px 30px;
                    background: #dc3545; color: white; text-decoration: none;
                    border-radius: 25px; margin-top: 20px; font-weight: 600;
                }
            </style>
        </head>
        <body>
            <div class='error-card'>
                <div class='error-icon'>❌</div>
                
                <h1 class='hindi'>पंजीकरण विफल!</h1>
                <h1 class='english'>Registration Failed!</h1>
                
                <p class='hindi'>त्रुटि: " . htmlspecialchars($e->getMessage()) . "</p>
                <p class='english'>Error: " . htmlspecialchars($e->getMessage()) . "</p>
                
                <a href='group_signup.php' class='btn-retry'>
                    <span class='hindi'>फिर से प्रयास करें</span>
                    <span class='english'>Try Again</span>
                </a>
            </div>
        </body>
        </html>
        ";
    }

} else {
    // Invalid request
    header("Location: group_signup.php");
    exit();
}
?>