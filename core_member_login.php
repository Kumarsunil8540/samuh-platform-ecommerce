<?php
session_start();
include("config.php");

// ✅ Step 1: URL से role लेना aur safe karna
if (isset($_GET['role'])) {
    $role = strtolower(trim($_GET['role']));
    $allowed_roles = ['admin', 'leader', 'accountant', 'member'];
    if (!in_array($role, $allowed_roles)) {
        die("❌ Invalid role access!");
    }
    
    // 💡 FIX 1: Set the correct table name based on the role using your provided SQL schema
    $table_name = ($role === 'member') ? 'members' : $role . 's'; 
    
} else {
    die("❌ Role URL में अनुपलब्ध है!");
}

// Role display names
$role_display_names = [
    'leader' => ['hindi' => 'लीडर', 'english' => 'Leader'],
    'admin' => ['hindi' => 'एडमिन', 'english' => 'Admin'], 
    'accountant' => ['hindi' => 'अकाउंटेंट', 'english' => 'Accountant'],
    'member' => ['hindi' => 'सदस्य', 'english' => 'Member']
];

$current_role = $role_display_names[$role];

// ✅ Step 2: अगर form submit हुआ है
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "❌ कृपया यूज़रनेम और पासवर्ड दर्ज करें / Please enter username and password";
    } else {
        // ✅ SQL query (Prepared Statement)
        try {
            $status_check = '';
            
            // 💡 FIX 2: Check the correct status field for each table
            if ($role === 'member') {
                // 'members' table uses 'member_status' column
                $status_check = " AND member_status = 'active'";
            } else {
                // 'leaders', 'admins', 'accountants' tables use 'is_active' column
                $status_check = " AND is_active = 1";
            }
            
            // Use the determined table name and status check
            $query = "SELECT * FROM $table_name WHERE username = ? {$status_check}";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // ✅ Password verification logic
                $login_success = false;
                
                // Check if password is hashed or plain text
                if (isset($user['password_hash'])) {
                    // 1. If password is hashed
                    if (password_verify($password, $user['password_hash'])) {
                        $login_success = true;
                    }
                } 
                
                // 2. Check temporary password (Mobile number for initial login for Core Members)
                // Note: We use mobile check for all roles if password_hash fails/is empty.
                else if (isset($user['mobile']) && $user['mobile'] === $password) {
                    $login_success = true;
                }
                
                // 3. (Fallback) Check plain text password if no hash is set
                else if (isset($user['password']) && $user['password'] === $password) {
                    $login_success = true;
                }

                if ($login_success) {
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_id'] = $user['id'];
                    // group_id is present in all four tables in your schema
                    $_SESSION['group_id'] = $user['group_id']; 
                    $_SESSION['role'] = $role;
                    $_SESSION['user_name'] = $user['full_name'] ?? $user['username'];
                    $_SESSION['login'] = true;
                    $_SESSION['last_login'] = time();

                    // Update last login time
                    try {
                        $update_sql = "UPDATE $table_name SET last_login = NOW() WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->execute([$user['id']]);
                    } catch (PDOException $e) {
                        // Log error but don't stop login
                        error_log("Last login update failed in $table_name: " . $e->getMessage());
                    }

                    $page_name = $role . "_dashboard.php";
                    header("Location: $page_name");
                    exit;
                } else {
                    $error = "❌ गलत पासवर्ड! / Incorrect password!";
                }
            } else {
                // 💡 FIX 3: Check if the member exists but is pending/rejected
                 if ($role === 'member') {
                     // Separate query to check for pending/rejected status
                     $check_pending_query = "SELECT member_status FROM members WHERE username = ?";
                     $stmt_check = $conn->prepare($check_pending_query);
                     $stmt_check->execute([$username]);
                     $member_status = $stmt_check->fetchColumn();

                     if ($member_status === 'pending') {
                          $error = "❌ आपकी सदस्यता लंबित है! / Your membership is pending!";
                     } elseif ($member_status === 'rejected') {
                          $error = "❌ आपकी सदस्यता अस्वीकृत हो चुकी है! / Your membership has been rejected!";
                     } else {
                          $error = "❌ यूज़रनेम नहीं मिला या निष्क्रिय! / Username not found or inactive!";
                     }

                 } else {
                     $error = "❌ यूज़रनेम नहीं मिला या निष्क्रिय! / Username not found or inactive!";
                 }
            }
        } catch (PDOException $e) {
            // More detailed error for debugging
            error_log("Login Error: " . $e->getMessage());
            // This is the error message the user was seeing. It indicates a database/query problem.
            $error = "❌ लॉगिन त्रुटि! कृपया बाद में प्रयास करें / Login error! Please try again later";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_role['english']; ?> Login - समूह प्लेटफॉर्म</title>
    <link rel="stylesheet" href="core_member_login.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <h1 class="hindi"><?php echo $current_role['hindi']; ?> लॉगिन</h1>
            <h1 class="english"><?php echo $current_role['english']; ?> Login</h1>
            <div class="role-badge">
                <span class="hindi">समूह प्लेटफॉर्म</span>
                <span class="english">Samuh Platform</span>
            </div>
        </div>

        <form method="post" class="login-form">
            <div class="input-group">
                <label for="username" class="hindi">यूज़रनेम *</label>
                <label for="username" class="english">Username *</label>
                <input type="text" id="username" name="username" placeholder="अपना यूज़रनेम दर्ज करें" 
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>

            <div class="input-group">
                <label for="password" class="hindi">पासवर्ड *</label>
                <label for="password" class="english">Password *</label>
                <input type="password" id="password" name="password" placeholder="अपना पासवर्ड दर्ज करें" required>
                
                <?php if (in_array($role, ['leader', 'accountant'])): ?>
                    <small class="password-hint hindi">
                        💡 पहली बार लॉगिन? मोबाइल नंबर का उपयोग करें
                    </small>
                    <small class="password-hint english">
                        💡 First time login? Use your mobile number
                    </small>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-login">
                <span class="hindi">लॉगिन करें</span>
                <span class="english">Login</span>
            </button>

            <?php if (!empty($error)) : ?>
                <div class="error-message">
                    <span class="hindi"><?php echo $error; ?></span>
                    <span class="english"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
        </form>

        <div class="login-footer">
            <div class="login-help">
                <p class="hindi">🔓 लॉगिन में समस्या?</p>
                <p class="english">🔓 Login issues?</p>
                
                <div class="help-options">
                    <?php if (in_array($role, ['leader', 'accountant'])): ?>
                        <div class="help-item">
                            <span class="hindi">• पहली बार: मोबाइल नंबर डालें</span>
                            <span class="english">• First time: Enter mobile number</span>
                        </div>
                        <div class="help-item">
                            <span class="hindi">• पासवर्ड बदलने के लिए डैशबोर्ड जाएं</span>
                            <span class="english">• Go to dashboard to change password</span>
                        </div>
                    <?php elseif ($role === 'member'): ?>
                        <div class="help-item">
                             <span class="hindi">• पासवर्ड भूल गए? समूह लीडर से संपर्क करें</span>
                            <span class="english">• Forgot password? Contact group leader</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="login-links">
                <?php if ($role === 'member'): ?>
                    <a href="member_registration.php" class="link">
                        <span class="hindi">📝 नए सदस्य के रूप में आवेदन करें</span>
                        <span class="english">📝 Apply as a New Member</span>
                    </a>
                <?php else: ?>
                    <a href="check_group_core_member.php?role=<?php echo $role; ?>" class="link">
                        <span class="hindi">📝 दस्तावेज़ अपलोड करें</span>
                        <span class="english">📝 Upload Documents</span>
                    </a>
                <?php endif; ?>
                <a href="index.php" class="link">
                    <span class="hindi">🏠 होम पेज</span>
                    <span class="english">🏠 Home Page</span>
                </a>
            </div>

            <div class="forgot-password-link">
                <a href="forgot_password.php?role=<?php echo $role; ?>" class="forgot-link">
                    <span class="hindi">🔑 पासवर्ड भूल गए?</span>
                    <span class="english">🔑 Forgot Password?</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Real-time form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.login-form');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            
            // Clear error on input
            usernameInput.addEventListener('input', clearError);
            passwordInput.addEventListener('input', clearError);
            
            function clearError() {
                const errorElement = document.querySelector('.error-message');
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            }
            
            // Form submission enhancement
            form.addEventListener('submit', function(e) {
                const username = usernameInput.value.trim();
                const password = passwordInput.value.trim();
                
                if (!username || !password) {
                    e.preventDefault();
                    if (!username) {
                        usernameInput.focus();
                    } else {
                        passwordInput.focus();
                    }
                }
            });

            // Auto-focus on username
            usernameInput.focus();
        });
    </script>

</body>
</html>