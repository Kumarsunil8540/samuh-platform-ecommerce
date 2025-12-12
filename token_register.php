<?php
// PHP error reporting ko bandh rakhen production environment mein
// ini_set('display_errors', 0);
// error_reporting(E_ALL);

session_start();
require_once 'token_config.php'; // Maan lijiye ki $pdo connection object yahan se aa raha hai

// Redirect if already logged in (Login check optional hai, par acchi practice hai)
if (isset($_SESSION['user_id'])) { 
    header("Location: token_system.php");
    exit();
}

$error = '';
$form_data = [
    'username' => '',
    'mobile' => '',
    'email' => '',
    'full_name' => '',
];

// Handle registration process
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and collect input data
    $username = trim($_POST['username'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? ''); 
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Store form data for repopulation
    $form_data = [
        'username' => htmlspecialchars($username),
        'mobile' => htmlspecialchars($mobile),
        'email' => htmlspecialchars($email),
        'full_name' => htmlspecialchars($full_name),
    ];
    
    // Validate inputs
    $validation_result = validateRegistration($username, $mobile, $email, $full_name, $password, $confirm_password);
    
    if ($validation_result['valid']) {
        $registration_result = registerMember($username, $mobile, $email, $full_name, $password);
        
        if ($registration_result['success']) {
            // Success: Redirect to login page
            header("Location: token_login.php?registered=success");
            exit();
        } else {
            $error = $registration_result['error'];
        }
    } else {
        $error = $validation_result['error'];
    }
}

/**
 * Validate registration form data (Required fields as per table)
 */
function validateRegistration($username, $mobile, $email, $full_name, $password, $confirm_password) {
    
    // Check required fields (Username, Mobile, Full Name, Password are NOT NULL)
    if (empty($username) || empty($mobile) || empty($full_name) || empty($password)) {
        return ['valid' => false, 'error' => 'All fields marked with * are required.'];
    }
    
    // Check username
    if (strlen($username) < 4 || strlen($username) > 100) {
        return ['valid' => false, 'error' => 'Username must be between 4 and 100 characters long.'];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['valid' => false, 'error' => 'Username can only contain letters, numbers and underscore.'];
    }
    
    // Check password
    if (strlen($password) < 8) { 
        return ['valid' => false, 'error' => 'Password must be at least 8 characters long for security.'];
    }
    if ($password !== $confirm_password) {
        return ['valid' => false, 'error' => 'Passwords do not match.'];
    }
    
    // Validate mobile number
    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        return ['valid' => false, 'error' => 'Please enter a valid 10-digit mobile number.'];
    }
    
    // Validate email if provided (Email is optional/NULLable in your schema)
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Please enter a valid email address.'];
        }
        if (strlen($email) > 100) {
            return ['valid' => false, 'error' => 'Email address is too long.'];
        }
    }
    
    return ['valid' => true, 'error' => ''];
}

/**
 * Register new member - Strict adherence to your 'users' table schema
 */
function registerMember($username, $mobile, $email, $full_name, $password) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Check uniqueness (Username and Mobile are UNIQUE NOT NULL)
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR mobile = ?");
        $stmt->execute([$username, $mobile]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Username or Mobile number is already registered.'];
        }
        
        // 2. Check email uniqueness (Email is UNIQUE) - only if provided
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Email address is already registered.'];
            }
        }
        
        // 3. Generate unique user_code (UNIQUE NOT NULL)
        $user_code = generateUniqueUserCode(); 
        
        // 4. Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // 5. Insert new user into 'users' table
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, mobile, user_code) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        
        // Set email to NULL if it's empty, as per your optional schema design
        $email_to_insert = !empty($email) ? $email : null; 

        $stmt->execute([$username, $hashed_password, $full_name, $email_to_insert, $mobile, $user_code]);
        
        $user_id = $pdo->lastInsertId();
        
        // 6. Commit transaction
        $pdo->commit();
        
        return ['success' => true, 'user_id' => $user_id];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Registration Error: " . $e->getMessage()); 
        return ['success' => false, 'error' => 'Registration failed due to a server error. Please try again.'];
    }
}

/**
 * Generate unique user_code (UNIQUE NOT NULL)
 */
function generateUniqueUserCode() {
    global $pdo;
    
    $max_attempts = 10;
    $attempt = 0;
    
    do {
        // Generate a random code, e.g., 'SAMUH' followed by 6 random hex characters
        $code = 'SAMUH' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); 
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_code = ?");
        $stmt->execute([$code]);
        $attempt++;
        
        if ($attempt > $max_attempts) {
             // Fallback in case random generation fails repeatedly
            $code = 'SAMUH' . uniqid();
            break;
        }
    } while ($stmt->fetch());
    
    return $code;
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Samuh Token System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS styles are kept the same for a consistent look and feel */
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
        
        .register-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            position: relative;
            overflow: hidden;
        }
        
        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .register-header {
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
        
        .register-header h2 {
            color: var(--dark-color);
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .register-header p {
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
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 10px; /* Reduced margin */
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px; /* Reduced margin */
            color: var(--dark-color);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-label .required {
            color: var(--error-color);
        }
        
        .form-input {
            width: 100%;
            padding: 12px 14px; /* Reduced padding */
            border: 2px solid #e9ecef;
            border-radius: 8px; /* Reduced border radius */
            font-size: 15px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .input-wrapper {
            position: relative;
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
        
        .btn-register {
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
            margin-top: 15px; /* Increased margin */
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .register-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .register-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .register-link:hover {
            color: var(--secondary-color);
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .register-container {
                padding: 30px 20px;
            }
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        
        .strength-weak { color: var(--error-color); }
        .strength-medium { color: var(--warning-color); }
        .strength-strong { color: var(--success-color); }

        .new-feature-badge {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2>Create Account</h2>
            <p>Join Samuh Token System today & start earning</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <div class="form-grid">
                
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i> Username <span class="required">*</span>
                    </label>
                    <input type="text" id="username" name="username" class="form-input" required 
                               placeholder="Choose username (4+ chars)" 
                               value="<?php echo $form_data['username']; ?>"
                               minlength="4" pattern="[a-zA-Z0-9_]+" title="Only letters, numbers and underscore allowed">
                    <div class="password-strength" id="usernameHelp">Letters, numbers, underscore only</div>
                </div>
                
                <div class="form-group">
                    <label for="mobile" class="form-label">
                        <i class="fas fa-mobile-alt"></i> Mobile <span class="required">*</span>
                    </label>
                    <input type="tel" id="mobile" name="mobile" class="form-input" required 
                               placeholder="10-digit mobile number" 
                               value="<?php echo $form_data['mobile']; ?>"
                               pattern="[0-9]{10}" maxlength="10">
                </div>
                
                <div class="form-group full-width">
                    <label for="full_name" class="form-label">
                        <i class="fas fa-id-card"></i> Full Name <span class="required">*</span>
                    </label>
                    <input type="text" id="full_name" name="full_name" class="form-input" required 
                               placeholder="Enter your full name" 
                               value="<?php echo $form_data['full_name']; ?>">
                </div>
                
                <div class="form-group full-width">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> Email (Optional)
                    </label>
                    <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Enter your email" 
                               value="<?php echo $form_data['email']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" class="form-input" required 
                                       placeholder="Enter password (Min 8 chars)" minlength="8">
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required 
                                       placeholder="Confirm password" minlength="8">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordMatch"></div>
                </div>

                </div>
            
            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="register-links">
            <a href="token_login.php" class="register-link">
                <i class="fas fa-sign-in-alt"></i> Already have an account? Login
            </a>
        </div>
        <div class="register-links">
            <a href="token_reset_password.php" class="register-link">
                <i class="fas fa-sign-in-alt"></i> forgot password
            </a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = passwordInput.parentNode.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthText = document.getElementById('passwordStrength');
            let strength = '';
            let color = '';
            
            if (password.length === 0) {
                strength = '';
            } else if (password.length < 8) { 
                strength = 'Weak - Minimum 8 characters';
                color = 'strength-weak';
            } else if (password.length < 12) {
                strength = 'Medium';
                color = 'strength-medium';
            } else {
                strength = 'Strong';
                color = 'strength-strong';
            }
            
            strengthText.textContent = strength;
            strengthText.className = 'password-strength ' + color;
        });
        
        // Password match indicator
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchText.textContent = '';
            } else if (password === confirmPassword) {
                matchText.textContent = 'Passwords match ✓';
                matchText.className = 'password-strength strength-strong';
            } else {
                matchText.textContent = 'Passwords do not match ✗';
                matchText.className = 'password-strength strength-weak';
            }
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const mobile = document.getElementById('mobile').value;
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            
            // Check password match
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Error: Passwords do not match!');
                return false;
            }
            
            // Check password length
            if (password.length < 8) {
                e.preventDefault();
                alert('Error: Password must be at least 8 characters long for security!');
                return false;
            }

            // Check mobile number
            if (!/^[0-9]{10}$/.test(mobile)) {
                e.preventDefault();
                alert('Error: Please enter a valid 10-digit mobile number!');
                return false;
            }
            
            // Check username format
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                e.preventDefault();
                alert('Error: Username can only contain letters, numbers and underscore!');
                return false;
            }

            // Client-side email validation 
            if (email && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email)) {
                e.preventDefault();
                alert('Error: Please enter a valid email address if provided.');
                return false;
            }
            
            return true;
        });
        
        // Auto-focus on username field
        document.getElementById('username').focus();
    </script>
</body>
</html>