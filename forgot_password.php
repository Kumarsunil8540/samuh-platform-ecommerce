<?php
session_start();
require_once 'config.php';

// Get role from URL
$role = isset($_GET['role']) ? $_GET['role'] : 'member';
$allowed_roles = ['admin', 'leader', 'accountant', 'member'];

if (!in_array($role, $allowed_roles)) {
    $role = 'member'; // Default to member
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $group_name = trim($_POST['group_name']);
    $group_id = trim($_POST['group_id']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($group_name) || empty($group_id) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        try {
            // Verify all details match in database
            $user_data = null;
            $table_name = '';
            
            switch ($role) {
                case 'admin':
                    $table_name = 'admins';
                    $stmt = $conn->prepare("
                        SELECT a.*, g.group_name 
                        FROM admins a 
                        JOIN groups g ON a.group_id = g.id 
                        WHERE a.username = ? AND a.email = ? AND a.group_id = ? AND g.group_name = ?
                    ");
                    break;
                    
                case 'leader':
                    $table_name = 'leaders';
                    $stmt = $conn->prepare("
                        SELECT l.*, g.group_name 
                        FROM leaders l 
                        JOIN groups g ON l.group_id = g.id 
                        WHERE l.username = ? AND l.email = ? AND l.group_id = ? AND g.group_name = ?
                    ");
                    break;
                    
                case 'accountant':
                    $table_name = 'accountants';
                    $stmt = $conn->prepare("
                        SELECT ac.*, g.group_name 
                        FROM accountants ac 
                        JOIN groups g ON ac.group_id = g.id 
                        WHERE ac.username = ? AND ac.email = ? AND ac.group_id = ? AND g.group_name = ?
                    ");
                    break;
                    
                case 'member':
                    $table_name = 'members';
                    $stmt = $conn->prepare("
                        SELECT m.*, g.group_name 
                        FROM members m 
                        JOIN groups g ON m.group_id = g.id 
                        WHERE (m.username = ? OR m.email = ?) AND m.group_id = ? AND g.group_name = ?
                    ");
                    break;
            }
            
            // Execute query based on role
            if ($role === 'member') {
                $stmt->execute([$username, $email, $group_id, $group_name]);
            } else {
                $stmt->execute([$username, $email, $group_id, $group_name]);
            }
            
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in respective table
                $update_stmt = $conn->prepare("UPDATE $table_name SET password_hash = ? WHERE id = ?");
                $update_result = $update_stmt->execute([$hashed_password, $user_data['id']]);
                
                if ($update_result) {
                    $message = "Password reset successfully! You can now login with your new password.";
                    
                    // Auto redirect to login after 3 seconds
                    header("refresh:3;url=login.php");
                } else {
                    $error = "Failed to reset password. Please try again.";
                }
            } else {
                $error = "No account found with the provided details. Please check your information.";
            }
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Samuh Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .password-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .password-header {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .password-body {
            padding: 30px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .role-badge {
            font-size: 0.9em;
            padding: 5px 15px;
            border-radius: 20px;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="password-container">
                    <div class="password-header">
                        <h3><i class="fas fa-lock me-2"></i>Reset Password</h3>
                        <span class="badge bg-light text-dark role-badge mt-2">
                            <?php echo ucfirst($role); ?>
                        </span>
                    </div>
                    
                    <div class="password-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <!-- Hidden field to maintain role -->
                            <input type="hidden" name="role" value="<?php echo $role; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" required 
                                           placeholder="Enter username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required 
                                           placeholder="Enter email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Group Name *</label>
                                    <input type="text" class="form-control" name="group_name" required 
                                           placeholder="Enter group name" value="<?php echo isset($_POST['group_name']) ? htmlspecialchars($_POST['group_name']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Group ID *</label>
                                    <input type="number" class="form-control" name="group_id" required 
                                           placeholder="Enter group ID" value="<?php echo isset($_POST['group_id']) ? htmlspecialchars($_POST['group_id']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password" required 
                                           placeholder="Enter new password" minlength="6">
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" required 
                                           placeholder="Confirm new password" minlength="6">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-key me-2"></i> Reset Password
                            </button>
                            
                            <div class="text-center">
                                <a href="core_member_login.php?role=<?php echo $role; ?>" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>