<?php
session_start();
include('config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Input sanitization
    $group_id  = trim($_POST['group_id']);
    $username  = trim($_POST['username']);
    $password  = trim($_POST['password']);
    $role_name = trim($_POST['role']);

    // Security - allow only specific roles
    $allowed_roles = ['leader', 'admin', 'accountant'];
    if (!in_array($role_name, $allowed_roles)) {
        showError("❌ Invalid role access!");
    }

    // Validate inputs
    if (empty($group_id) || empty($username) || empty($password)) {
        showError("❌ सभी फ़ील्ड भरें / Please fill all fields");
    }

    if (!preg_match('/^[0-9]{10}$/', $password)) {
        showError("❌ मोबाइल नंबर 10 अंकों का होना चाहिए / Mobile number must be 10 digits");
    }

    // Dynamic columns
    $name_col   = $role_name . '_name';
    $mobile_col = $role_name . '_mobile';

    // SQL Query with prepared statement
    try {
        $sql = "SELECT id, group_name, $name_col as member_name, $mobile_col as member_mobile 
                FROM groups 
                WHERE id = ? AND $name_col = ? AND $mobile_col = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$group_id, $username, $password]);

        if ($stmt->rowCount() > 0) {
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set session variables
            $_SESSION['user_verified'] = true;
            $_SESSION['role'] = $role_name;
            $_SESSION['table_name'] = $role_name . 's';
            $_SESSION['group_id'] = $group_id;
            $_SESSION['user_name'] = $username;
            $_SESSION['group_name'] = $user_data['group_name'];
            $_SESSION['member_data'] = $user_data;

            // Show success message with upload button
            showSuccess($role_name, $user_data);
            
        } else {
            showError("❌ गलत विवरण! कृपया ग्रुप आईडी, नाम और मोबाइल नंबर जांचें / Wrong details! Please check Group ID, Name and Mobile Number");
        }
        
    } catch (PDOException $e) {
        showError("❌ डेटाबेस त्रुटि! कृपया बाद में प्रयास करें / Database error! Please try again later");
    }
} else {
    header("Location: check_group_core_member.php");
    exit();
}

// Function to show error message
function showError($message) {
    echo "
    <!DOCTYPE html>
    <html lang='hi'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Verification Failed</title>
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
            
            <h1 class='hindi'>सत्यापन विफल!</h1>
            <h1 class='english'>Verification Failed!</h1>
            
            <p class='hindi'>" . htmlspecialchars($message) . "</p>
            <p class='english'>" . htmlspecialchars($message) . "</p>
            
            <a href='check_group_core_member.php?role=" . htmlspecialchars($_POST['role'] ?? 'leader') . "' class='btn-retry'>
                <span class='hindi'>फिर से प्रयास करें</span>
                <span class='english'>Try Again</span>
            </a>
        </div>
    </body>
    </html>
    ";
    exit();
}

// Function to show success message
function showSuccess($role_name, $user_data) {
    $role_display = [
        'leader' => ['hindi' => 'लीडर', 'english' => 'Leader'],
        'admin' => ['hindi' => 'एडमिन', 'english' => 'Admin'],
        'accountant' => ['hindi' => 'अकाउंटेंट', 'english' => 'Accountant']
    ];
    
    $current_role = $role_display[$role_name];
    $upload_page = 'upload_data_core.php';
    
    echo "
    <!DOCTYPE html>
    <html lang='hi'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Verification Successful</title>
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
            .user-info {
                background: #e9f5ff; padding: 20px; border-radius: 10px;
                margin: 20px 0; border-left: 4px solid #007bff;
                text-align: left;
            }
            .info-item { margin: 10px 0; }
            .btn-upload {
                display: inline-block; padding: 14px 35px;
                background: #007bff; color: white; text-decoration: none;
                border-radius: 25px; margin: 15px 10px; font-weight: 600;
                transition: all 0.3s ease; border: none; cursor: pointer;
            }
            .btn-upload:hover {
                background: #0056b3; transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,123,255,0.3);
            }
            .btn-home {
                display: inline-block; padding: 12px 25px;
                background: #6c757d; color: white; text-decoration: none;
                border-radius: 25px; margin: 10px; font-weight: 600;
                transition: all 0.3s ease;
            }
            .btn-home:hover {
                background: #5a6268; transform: translateY(-2px);
            }
            .hindi { font-weight: 500; margin-bottom: 3px; }
            .english { color: #6c757d; margin-bottom: 10px; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='success-card'>
            <div class='success-icon'>✅</div>
            
            <h1 class='hindi'>सत्यापन सफल!</h1>
            <h1 class='english'>Verification Successful!</h1>
            
            <div class='user-info'>
                <div class='info-item'>
                    <span class='hindi'><strong>ग्रुप नाम:</strong> " . htmlspecialchars($user_data['group_name']) . "</span>
                    <span class='english'><strong>Group Name:</strong> " . htmlspecialchars($user_data['group_name']) . "</span>
                </div>
                <div class='info-item'>
                    <span class='hindi'><strong>सदस्य नाम:</strong> " . htmlspecialchars($user_data['member_name']) . "</span>
                    <span class='english'><strong>Member Name:</strong> " . htmlspecialchars($user_data['member_name']) . "</span>
                </div>
                <div class='info-item'>
                    <span class='hindi'><strong>पद:</strong> " . $current_role['hindi'] . "</span>
                    <span class='english'><strong>Role:</strong> " . $current_role['english'] . "</span>
                </div>
            </div>
            
            <p class='hindi'>✅ आपका सत्यापन सफल रहा! अब आप अपने दस्तावेज़ अपलोड कर सकते हैं</p>
            <p class='english'>✅ Your verification is successful! You can now upload your documents</p>
            
            <a href='$upload_page' class='btn-upload'>
                <span class='hindi'>दस्तावेज़ अपलोड करें</span>
                <span class='english'>Upload Documents</span>
            </a>
            
            <br>
            
            <a href='index.php' class='btn-home'>
                <span class='hindi'>होम पेज</span>
                <span class='english'>Home Page</span>
            </a>
        </div>
    </body>
    </html>
    ";
    exit();
}
?>