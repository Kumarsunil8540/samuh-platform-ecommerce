<?php
session_start();
require_once 'token_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Simple validation
    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $error = "सभी फील्ड भरें";
    } elseif ($new_password !== $confirm_password) {
        $error = "पासवर्ड मेल नहीं खा रहे";
    } elseif (strlen($new_password) < 6) {
        $error = "पासवर्ड कम से कम 6 अक्षर का होना चाहिए";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Update password directly
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update_stmt->execute([$hashed_password, $email]);
            
            // Set cookies
            setcookie('remember_username', $user['username'], time() + (30 * 24 * 60 * 60), "/");
            setcookie('remember_password', $new_password, time() + (30 * 24 * 60 * 60), "/");
            
            $success = "पासवर्ड बदल गया! लॉगिन पेज पर जा रहे हैं...";
            header("refresh:2;url=token_login.php");
        } else {
            $error = "यह ईमेल रजिस्टर्ड नहीं है";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>पासवर्ड रीसेट - Samuh Token</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px;
            font-family: Arial;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            color: #333;
            margin-bottom: 5px;
        }
        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .success { background: #e8f5e8; color: #2e7d32; border: 1px solid #c8e6c9; }
        .form-group { margin-bottom: 15px; }
        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .btn:hover { background: #5a6fd8; }
        .links { text-align: center; margin-top: 15px; }
        .links a { color: #667eea; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🔐 पासवर्ड बदलें</h2>
            <p>अपना नया पासवर्ड सेट करें</p>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <input type="email" name="email" class="form-input" required 
                       placeholder="आपका ईमेल" value="<?php echo $_POST['email'] ?? ''; ?>">
            </div>
            
            <div class="form-group">
                <input type="password" name="new_password" class="form-input" required 
                       placeholder="नया पासवर्ड (कम से कम 6 अक्षर)">
            </div>
            
            <div class="form-group">
                <input type="password" name="confirm_password" class="form-input" required 
                       placeholder="पासवर्ड दोबारा लिखें">
            </div>

            <button type="submit" class="btn">पासवर्ड बदलें</button>
        </form>

        <div class="links">
            <a href="token_login.php">← लॉगिन पर वापस जाएं</a>
        </div>
    </div>
</body>
</html>