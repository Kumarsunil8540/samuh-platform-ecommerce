<?php
session_start();
require_once 'token_config.php'; // Maan lijiye ki $pdo connection object yahan se aa raha hai

// Initialize variables
$error = '';
$success = '';
$username_prefill = '';
// Determine the currently active type based on POST data or default to 'member'
$active_type = $_POST['user_type'] ?? 'member'; 

// Redirect if already logged in
if (isset($_SESSION['user_type'])) {
    redirectToDashboard();
}

// Handle login process
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'member'; // Hidden field se aayega
    
    $username_prefill = htmlspecialchars($username);
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password!";
    } else {
        $login_result = processLogin($username, $password, $user_type);
        if ($login_result['success']) {
            setUserSession($login_result['user_data'], $user_type);
            redirectToDashboard();
        } else {
            $error = $login_result['error'];
        }
    }
    // Agar login fail ho jaye, to active type ko POST se aaye hue value par set rakhen
    $active_type = $user_type; 
}

/**
 * Process login for member or admin
 */
function processLogin($username, $password, $user_type) {
    global $pdo;
    
    try {
        if ($user_type === 'member') {
            // Member login: NO CHANGE (Still uses password_verify for security)
            return loginMember($username, $password); 
        } elseif ($user_type === 'admin') {
            // Admin login: FIXED for Plain Text/No Hashing
            return loginAdmin($username, $password);
        }
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage()); 
        return ['success' => false, 'error' => 'System error. Please try again.'];
    }
    
    return ['success' => false, 'error' => 'Invalid user type selected.'];
}

/**
 * Member login function (Using 'users' table - Secure password_verify assumed)
 * ⚠️ NO CHANGES APPLIED HERE.
 */
function loginMember($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT user_id, username, full_name, password, status FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'error' => 'Invalid username or password!'];
    }
    
    // Check account status
    if ($user['status'] !== 'active') {
        return ['success' => false, 'error' => 'Your account is inactive. Please contact support.'];
    }
    
    // Verify password (Uses password_verify for 'users' table)
    if (password_verify($password, $user['password'])) { 
        return ['success' => true, 'user_data' => $user]; 
    }
    
    return ['success' => false, 'error' => 'Invalid username or password!'];
}

/**
 * Admin login function (Using 'admins' table - FIXED FOR PLAIN TEXT)
 */
function loginAdmin($username, $password) {
    global $pdo;
    
    // Select admin_id, username, password, and name from the 'admins' table
    $stmt = $pdo->prepare("SELECT admin_id, username, password, name FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'error' => 'Invalid username or password!'];
    }
    
    // *** FIX: Admin Password Verification (Plain Text/No Hashing) ***
    // ⚠️ WARNING: This is INSECURE. Use only if passwords are not hashed in the database.
    if ($password === $user['password']) { 
        return ['success' => true, 'user_data' => $user];
    } 
    
    return ['success' => false, 'error' => 'Invalid username or password!'];
}

/**
 * Set user session after successful login
 */
function setUserSession($user_data, $user_type) {
    $_SESSION['user_type'] = $user_type;
    $_SESSION['username'] = $user_data['username'];
    
    if ($user_type === 'member') {
        $_SESSION['user_id'] = $user_data['user_id']; 
        $_SESSION['full_name'] = $user_data['full_name']; // full_name from users table
    } elseif ($user_type === 'admin') {
        $_SESSION['admin_id'] = $user_data['admin_id'];
        $_SESSION['full_name'] = $user_data['name']; // 'name' from admins table
    }
    
    // Set login timestamp
    $_SESSION['login_time'] = time();
}

/**
 * Redirect to appropriate dashboard
 */
function redirectToDashboard() {
    if ($_SESSION['user_type'] === 'member') {
        header("Location: token_member_dashboard.php");
    } elseif ($_SESSION['user_type'] === 'admin') {
        header("Location: token_admin_dashboard.php");
    }
    exit();
}

// Get success messages from URL
if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $success = "✅ Registration successful! Please login.";
}

if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success = "✅ Logout successful!";
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Samuh Token System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS styles here */
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --error-color: #dc3545;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            font-size: 3rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .login-header h2 {
            color: var(--dark-color);
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .login-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #ffe6e6;
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }
        
        .alert-success {
            background: #e6ffe6;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .user-type-selector {
            display: flex;
            background: var(--light-color);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 25px;
            border: 2px solid #e9ecef;
        }
        
        .user-type-btn {
            flex: 1;
            padding: 12px;
            text-align: center;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .user-type-btn.active {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .login-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .login-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .login-link:hover {
            color: var(--secondary-color);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .login-links {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-coins"></i>
            </div>
            <h2>Welcome Back</h2>
            <p>Login to your Samuh Token Account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="user-type-selector">
            <button type="button" class="user-type-btn <?php echo ($active_type === 'member') ? 'active' : ''; ?>" 
                    onclick="setUserType('member', this)">
                <i class="fas fa-user"></i> Member
            </button>
            <button type="button" class="user-type-btn <?php echo ($active_type === 'admin') ? 'active' : ''; ?>" 
                    onclick="setUserType('admin', this)">
                <i class="fas fa-user-shield"></i> Admin
            </button>
        </div>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username" class="form-label">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" id="username" name="username" class="form-input" required 
                       placeholder="Enter your username" 
                       value="<?php echo $username_prefill; ?>">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-input" required 
                           placeholder="Enter your password">
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <input type="hidden" id="user_type_hidden" name="user_type" value="<?php echo $active_type; ?>">
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="login-links">
            <a href="token_system.php" class="login-link">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        <div class="register-links">
            <a href="token_reset_password.php" class="register-link">
                <i class="fas fa-sign-in-alt"></i> forgot password
            </a>
        </div>
            <a href="token_register.php" class="login-link">
                <i class="fas fa-user-plus"></i> Create Account
            </a>
        </div>
    </div>

    <script>
        function setUserType(type, button) {
            document.getElementById('user_type_hidden').value = type;
            
            // Update button styles
            document.querySelectorAll('.user-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            button.classList.add('active');
        }
        
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all required fields!');
                return false;
            }
        });
        
        // Auto-focus on username field
        document.getElementById('username').focus();
    </script>
</body>
</html>