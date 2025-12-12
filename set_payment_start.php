<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_group_id = $_SESSION['group_id'];
$message = '';
$message_type = '';

// PHPMailer setup
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

// Function to generate 6-digit OTP
function generateOTP() {
    return sprintf("%06d", mt_rand(1, 999999));
}

// Function to send OTP email
function sendOTPEmail($toemail, $toname, $otp, $group_name) {
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
        
        $mail->Subject = 'OTP for Payment Date Reset - Samuh Platform';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #007bff; text-align: center;'>Payment Date Reset OTP</h2>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                    <p>Hello <strong>" . htmlspecialchars($toname) . "</strong>,</p>
                    
                    <p>An OTP has been requested to reset the payment start date for group: <strong>" . htmlspecialchars($group_name) . "</strong></p>
                    
                    <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: center;'>
                        <h3 style='color: #dc3545;'>Your OTP Code:</h3>
                        <div style='font-size: 2em; font-weight: bold; color: #dc3545; letter-spacing: 5px;'>
                            " . htmlspecialchars($otp) . "
                        </div>
                        <p style='color: #6c757d; font-size: 0.9em;'>
                            This OTP is valid for 10 minutes only.
                        </p>
                    </div>
                    
                    <p><strong>Note:</strong> This OTP is required to reset the payment start date. Do not share it with anyone.</p>
                </div>
                
                <div style='text-align: center; color: #6c757d; font-size: 0.9em;'>
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

// Fetch current group data
$current_group_data = null;
$is_date_set = false;
try {
    $stmt = $conn->prepare("SELECT id, group_name, payment_start_date FROM groups WHERE id = ?");
    $stmt->execute([$user_group_id]);
    $current_group_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_group_data) {
        $message = "Group not found!";
        $message_type = 'error';
    } else {
        // Check if payment date is already set
        $is_date_set = !is_null($current_group_data['payment_start_date']);
    }
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// Handle OTP generation and sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {
    try {
        // Get group details
        $group_name = $current_group_data['group_name'];
        
        // Get all three core members' emails
        $stmt = $conn->prepare("SELECT leader_email, admin_email, accountant_email FROM groups WHERE id = ?");
        $stmt->execute([$user_group_id]);
        $emails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get member names for personalized emails
        $stmt = $conn->prepare("SELECT leader_name, admin_name, accountant_name FROM groups WHERE id = ?");
        $stmt->execute([$user_group_id]);
        $names = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Generate OTPs for each member
        $otps = [
            'leader' => generateOTP(),
            'admin' => generateOTP(),
            'accountant' => generateOTP()
        ];
        
        // Store OTPs in session for verification
        $_SESSION['reset_otps'] = [
            'group_id' => $user_group_id,
            'otps' => $otps,
            'generated_at' => time(),
            'verified' => [
                'leader' => false,
                'admin' => false,
                'accountant' => false
            ]
        ];
        
        // Send OTP emails
        $email_results = [];
        
        // Send to Leader
        $result = sendOTPEmail($emails['leader_email'], $names['leader_name'], $otps['leader'], $group_name);
        if ($result === true) {
            $email_results[] = "✅ OTP sent to Leader: " . $emails['leader_email'];
        } else {
            $email_results[] = "❌ Failed to send OTP to Leader: " . $result;
        }
        
        // Send to Admin
        $result = sendOTPEmail($emails['admin_email'], $names['admin_name'], $otps['admin'], $group_name);
        if ($result === true) {
            $email_results[] = "✅ OTP sent to Admin: " . $emails['admin_email'];
        } else {
            $email_results[] = "❌ Failed to send OTP to Admin: " . $result;
        }
        
        // Send to Accountant
        $result = sendOTPEmail($emails['accountant_email'], $names['accountant_name'], $otps['accountant'], $group_name);
        if ($result === true) {
            $email_results[] = "✅ OTP sent to Accountant: " . $emails['accountant_email'];
        } else {
            $email_results[] = "❌ Failed to send OTP to Accountant: " . $result;
        }
        
        $message = "OTPs have been sent to all core members.<br>" . implode("<br>", $email_results);
        $message_type = 'success';
        
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = $_POST['otp'] ?? '';
    $member_type = $_POST['member_type'] ?? '';
    
    if (empty($entered_otp) || empty($member_type)) {
        $message = "Please enter OTP and select member type.";
        $message_type = 'error';
    } elseif (!isset($_SESSION['reset_otps'])) {
        $message = "No OTP request found. Please request OTP first.";
        $message_type = 'error';
    } else {
        $otp_data = $_SESSION['reset_otps'];
        
        // Check if OTP is expired (10 minutes)
        if (time() - $otp_data['generated_at'] > 600) {
            unset($_SESSION['reset_otps']);
            $message = "OTP has expired. Please request new OTPs.";
            $message_type = 'error';
        } elseif ($entered_otp === $otp_data['otps'][$member_type]) {
            // Mark this member as verified
            $_SESSION['reset_otps']['verified'][$member_type] = true;
            $message = "OTP verified successfully for " . ucfirst($member_type) . "!";
            $message_type = 'success';
            
            // Check if all three members are verified
            $all_verified = true;
            foreach ($_SESSION['reset_otps']['verified'] as $verified) {
                if (!$verified) {
                    $all_verified = false;
                    break;
                }
            }
            
            if ($all_verified) {
                $_SESSION['all_otps_verified'] = true;
                $message .= " All OTPs verified! You can now reset the payment date.";
            }
        } else {
            $message = "Invalid OTP for " . ucfirst($member_type);
            $message_type = 'error';
        }
    }
}

// Handle payment date setting (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment_date'])) {
    if ($user_role !== 'admin') {
        $message = "Only admin can set payment start date.";
        $message_type = 'error';
    } else {
        $payment_start_date = $_POST['payment_start_date'] ?? '';
        $action_type = $_POST['action_type'] ?? 'set'; // 'set' or 'change'
        
        if (empty($payment_start_date)) {
            $message = "Please select payment start date.";
            $message_type = 'error';
        } else {
            try {
                // Check if we're setting for the first time or changing existing date
                if ($action_type === 'set' && !$is_date_set) {
                    // First time setting - no OTP required
                    $update_stmt = $conn->prepare("UPDATE groups SET payment_start_date = ? WHERE id = ?");
                    $update_stmt->execute([$payment_start_date, $user_group_id]);
                    
                    if ($update_stmt->rowCount() > 0) {
                        $message = "Payment start date saved successfully!";
                        $message_type = 'success';
                        $is_date_set = true;
                    } else {
                        $message = "Failed to save payment start date.";
                        $message_type = 'error';
                    }
                } elseif ($action_type === 'change' && $is_date_set) {
                    // Changing existing date - OTP verification required
                    if (!isset($_SESSION['all_otps_verified']) || $_SESSION['all_otps_verified'] !== true) {
                        $message = "OTP verification required to change existing payment date.";
                        $message_type = 'error';
                    } else {
                        $update_stmt = $conn->prepare("UPDATE groups SET payment_start_date = ? WHERE id = ?");
                        $update_stmt->execute([$payment_start_date, $user_group_id]);
                        
                        if ($update_stmt->rowCount() > 0) {
                            $message = "Payment start date updated successfully!";
                            $message_type = 'success';
                            
                            // Clear OTP session after successful change
                            unset($_SESSION['reset_otps']);
                            unset($_SESSION['all_otps_verified']);
                        } else {
                            $message = "Failed to update payment start date.";
                            $message_type = 'error';
                        }
                    }
                } else {
                    $message = "Invalid operation.";
                    $message_type = 'error';
                }
                
                // Refresh group data
                $stmt = $conn->prepare("SELECT id, group_name, payment_start_date FROM groups WHERE id = ?");
                $stmt->execute([$user_group_id]);
                $current_group_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $is_date_set = !is_null($current_group_data['payment_start_date']);
                
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Handle payment date reset (after OTP verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_payment_date'])) {
    if (!isset($_SESSION['all_otps_verified']) || $_SESSION['all_otps_verified'] !== true) {
        $message = "All OTPs must be verified before resetting payment date.";
        $message_type = 'error';
    } else {
        try {
            $reset_stmt = $conn->prepare("UPDATE groups SET payment_start_date = NULL WHERE id = ?");
            $reset_stmt->execute([$user_group_id]);
            
            if ($reset_stmt->rowCount() > 0) {
                $message = "Payment start date reset successfully!";
                $message_type = 'success';
                
                // Clear OTP session
                unset($_SESSION['reset_otps']);
                unset($_SESSION['all_otps_verified']);
                
                // Refresh group data
                $stmt = $conn->prepare("SELECT id, group_name, payment_start_date FROM groups WHERE id = ?");
                $stmt->execute([$user_group_id]);
                $current_group_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $is_date_set = false;
            } else {
                $message = "Failed to reset payment start date.";
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Start Date - Samuh Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: 1px solid #e3e6f0;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .otp-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .verified-badge {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
        }
        .pending-badge {
            background: #ffc107;
            color: black;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
        }
        .current-date {
            font-size: 1.2em;
            font-weight: bold;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .date-set {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .date-not-set {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .first-time-set {
            border: 2px solid #28a745;
        }
        .change-existing {
            border: 2px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <main class="col-md-12">
                <!-- Back Button -->
                <div class="mt-3">
                    <a href="<?php echo $user_role . '_dashboard.php'; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Payment Start Date</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-primary me-2">Group: <?php echo htmlspecialchars($current_group_data['group_name'] ?? 'N/A'); ?></span>
                        <span class="badge bg-secondary">Role: <?php echo ucfirst($user_role); ?></span>
                    </div>
                </div>

                <!-- Message Alert -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Current Payment Date Display -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Current Payment Start Date</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($current_group_data): ?>
                                    <div class="current-date <?php echo $is_date_set ? 'date-set' : 'date-not-set'; ?>">
                                        <?php if ($is_date_set): ?>
                                            <i class="fas fa-calendar-check me-2"></i>
                                            <?php echo htmlspecialchars($current_group_data['payment_start_date']); ?>
                                            <br>
                                            <small class="text-muted">Payment date is already set</small>
                                        <?php else: ?>
                                            <i class="fas fa-calendar-times me-2"></i>
                                            Not Set
                                            <br>
                                            <small class="text-muted">Payment date has not been set yet</small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">Group information not available.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Only: Set/Change Payment Date -->
                <?php if ($user_role === 'admin'): ?>
                <div class="row mt-4">
                    <!-- Set/Change Payment Date -->
                    <div class="col-md-6">
                        <div class="card <?php echo $is_date_set ? 'change-existing' : 'first-time-set'; ?>">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <?php echo $is_date_set ? 'Change Payment Start Date' : 'Set Payment Start Date'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="payment_start_date" class="form-label">
                                            Select Payment Start Date
                                            <?php if ($is_date_set): ?>
                                                <span class="badge bg-warning">OTP Required</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">First Time Set</span>
                                            <?php endif; ?>
                                        </label>
                                        <input type="date" class="form-control" id="payment_start_date" name="payment_start_date" 
                                               value="<?php echo $is_date_set ? $current_group_data['payment_start_date'] : ''; ?>" required>
                                        <div class="form-text">
                                            <?php if ($is_date_set): ?>
                                                <span class="text-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    Changing existing date requires OTP verification from all core members.
                                                </span>
                                            <?php else: ?>
                                                <span class="text-success">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    First time setting payment date - no OTP required.
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="action_type" value="<?php echo $is_date_set ? 'change' : 'set'; ?>">
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" name="save_payment_date" class="btn <?php echo $is_date_set ? 'btn-warning' : 'btn-primary'; ?>">
                                            <i class="fas fa-save me-2"></i>
                                            <?php echo $is_date_set ? 'Change Payment Date' : 'Save Payment Date'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Reset Payment Date Section -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Reset Payment Date</h5>
                            </div>
                            <div class="card-body">
                                <div class="otp-section">
                                    <?php if ($is_date_set): ?>
                                        <p class="text-muted">
                                            To reset the payment date, OTP verification is required from all three core members.
                                        </p>
                                        
                                        <!-- OTP Request -->
                                        <?php if (!isset($_SESSION['reset_otps'])): ?>
                                        <div id="otp_request_section">
                                            <form method="POST" action="">
                                                <button type="submit" name="request_otp" class="btn btn-warning">
                                                    <i class="fas fa-envelope me-2"></i>Request OTPs for Reset
                                                </button>
                                            </form>
                                        </div>
                                        <?php endif; ?>

                                        <!-- OTP Verification Status -->
                                        <?php if (isset($_SESSION['reset_otps'])): ?>
                                        <div class="mt-3">
                                            <h6>OTP Verification Status:</h6>
                                            <?php
                                            $otp_data = $_SESSION['reset_otps'];
                                            $members = ['leader', 'admin', 'accountant'];
                                            foreach ($members as $member): 
                                                $is_verified = $otp_data['verified'][$member];
                                            ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                                    <span>
                                                        <i class="fas fa-user me-2"></i>
                                                        <?php echo ucfirst($member); ?>
                                                    </span>
                                                    <?php if ($is_verified): ?>
                                                        <span class="verified-badge">
                                                            <i class="fas fa-check me-1"></i>Verified
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="pending-badge me-2">Pending</span>
                                                        <form method="POST" action="" class="d-flex gap-2">
                                                            <input type="hidden" name="member_type" value="<?php echo $member; ?>">
                                                            <input type="text" name="otp" placeholder="Enter OTP" class="form-control form-control-sm" style="width: 120px;" required maxlength="6">
                                                            <button type="submit" name="verify_otp" class="btn btn-primary btn-sm">Verify</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>

                                            <!-- Reset Button (when all verified) -->
                                            <?php if (isset($_SESSION['all_otps_verified']) && $_SESSION['all_otps_verified'] === true): ?>
                                            <form method="POST" action="" class="mt-3">
                                                <button type="submit" name="reset_payment_date" class="btn btn-danger w-100" 
                                                        onclick="return confirm('Are you sure you want to reset the payment start date? This will clear the current date and require setting it again.')">
                                                    <i class="fas fa-redo me-2"></i>Reset Payment Date
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Payment date is not set yet. No reset required.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- For Non-Admin Users: View Only -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Payment Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    You can view the payment start date here. Only admin can set or reset the payment date.
                                </div>
                                
                                <?php if ($current_group_data && $is_date_set): ?>
                                    <p>The payment cycle for your group starts on: <strong><?php echo htmlspecialchars($current_group_data['payment_start_date']); ?></strong></p>
                                <?php else: ?>
                                    <p class="text-warning">Payment start date has not been set yet. Please contact your group admin.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to today for date input
            const dateInput = document.getElementById('payment_start_date');
            if (dateInput) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.min = today;
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // OTP input formatting (only numbers)
            const otpInputs = document.querySelectorAll('input[name="otp"]');
            otpInputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }
                });
            });
        });
    </script>
</body>
</html>