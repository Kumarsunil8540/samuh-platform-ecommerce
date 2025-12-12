<?php
session_start();
require_once 'config.php'; // Assuming this file contains your PDO database connection setup

// ------------------------------------------
// 1. Authentication and Authorization Check
// ------------------------------------------
// Check if user is logged in as leader
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'leader') {
    header("Location: login.php");
    exit();
}

$leader_group_id = $_SESSION['group_id'];
$message = '';
$reminder_results = [];
$group_timeline = [];

// ------------------------------------------
// 2. PHPMailer Setup and Function
// ------------------------------------------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

// Function to send payment reminder email using PHPMailer
function sendPaymentReminderEmail($to_email, $member_name, $group_name, $payment_cycle, $next_due_date, $days_left) {
    $mail = new PHPMailer(true);
    try {
        // Server settings (Use your actual Zoho details)
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
        $mail->addAddress($to_email, $member_name);

        $mail->isHTML(true);
        
        $cycle_text = ($payment_cycle == 'weekly') ? 'weekly' : 'monthly';
        $subject = "Upcoming Payment Reminder - $group_name";
        
        $mail->Subject = $subject;
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e9ecef; border-radius: 10px; overflow: hidden;'>
                <div style='background: #007bff; color: white; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0;'>💰 Payment Reminder</h2>
                </div>
                
                <div style='padding: 20px;'>
                    <p>Hello <strong>" . htmlspecialchars($member_name) . "</strong>,</p>
                    
                    <p>This is a friendly reminder about your upcoming payment for group: <strong>" . htmlspecialchars($group_name) . "</strong></p>
                    
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 5px solid #dc3545;'>
                        <h3 style='color: #dc3545; margin-top: 0;'>Important Details:</h3>
                        <table style='width: 100%;'>
                            <tr>
                                <td style='padding: 5px 0;'><strong>Group:</strong></td>
                                <td style='padding: 5px 0;'>" . htmlspecialchars($group_name) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 5px 0;'><strong>Payment Cycle:</strong></td>
                                <td style='padding: 5px 0;'>" . ucfirst($cycle_text) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 5px 0;'><strong>Next Due Date:</strong></td>
                                <td style='padding: 5px 0; font-weight: bold; color: #007bff;'>" . $next_due_date . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 5px 0;'><strong>Days Left:</strong></td>
                                <td style='padding: 5px 0; font-weight: bold; color: #28a745;'><strong>" . $days_left . " days</strong></td>
                            </tr>
                        </table>
                    </div>
                    
                    <p>Please make your payment before the due date to avoid any late fees and ensure smooth group operations.</p>
                    
                    <p>Thank you for your timely cooperation!</p>
                </div>
                
                <div style='text-align: center; color: #6c757d; font-size: 0.8em; padding: 10px; background: #e9ecef;'>
                    <p style='margin: 0;'>Samuh Platform - Managing Groups Efficiently</p>
                </div>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Return detailed error for logging
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// ------------------------------------------
// 3. Get Group Timeline and Payment History
// ------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT g.group_name, g.payment_cycle, g.payment_start_date,
               COUNT(pr.id) as total_payments,
               MAX(pr.payment_date) as last_payment_date,
               COALESCE(SUM(pr.payment_amount), 0) as total_collected
        FROM groups g
        LEFT JOIN payment_request pr ON g.id = pr.group_id AND pr.payment_status = 'verified'
        WHERE g.id = ?
        GROUP BY g.id, g.group_name, g.payment_cycle, g.payment_start_date
    ");
    $stmt->execute([$leader_group_id]);
    $group_timeline = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group_timeline) {
        $message = "Group not found!";
    }
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
}

// ------------------------------------------
// 4. Handle Manual Reminder Trigger
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminders'])) {
    
    // Check for valid start date before attempting calculation and sending
    if (!$group_timeline || empty($group_timeline['payment_start_date'])) {
        $message = "Payment start date is **not set** for this group. Cannot calculate due date.";
    } else {
        try {
            $group_name = $group_timeline['group_name'];
            $payment_cycle = $group_timeline['payment_cycle'];
            $payment_start_date = $group_timeline['payment_start_date'];
            
            // Calculate timeline
            $today = new DateTime();
            $start_date = new DateTime($payment_start_date);
            
            // Check if start date is in the future
            if ($start_date > $today) {
                $message = "Payment cycle has not started yet. Start date is: " . $start_date->format('d M Y');
                // Set days_left calculation for display purpose
                $days_left = $today->diff($start_date)->days;
                $next_due_date = $start_date;
                $cycles_completed = 0;
            } else {
                $cycle_days = ($payment_cycle == 'weekly') ? 7 : 30;
                $days_since_start = $start_date->diff($today)->days;
                
                // Calculate which cycle we are currently in or approaching
                $cycles_completed = floor($days_since_start / $cycle_days);
                
                // The next due date is after the cycles completed
                $next_due_date = clone $start_date;
                $next_due_date->modify("+ " . ($cycles_completed * $cycle_days) . " days");
                
                // If the next due date is today or in the past, calculate the one after that
                if ($next_due_date <= $today) {
                    $cycles_completed++; // Increment cycle count to ensure next payment is calculated
                    $next_due_date = clone $start_date;
                    $next_due_date->modify("+ " . ($cycles_completed * $cycle_days) . " days");
                }
                
                $next_cycle_number = $cycles_completed + 1; // Display purposes: next cycle number is completed + 1
                $days_left = $today->diff($next_due_date)->days;
            }


            // Get all active members
            // *** FIX APPLIED: Changed is_active = 1 to member_status = 'active' ***
            $stmt = $conn->prepare("
                SELECT id, full_name, email 
                FROM members 
                WHERE group_id = ? AND member_status = 'active' AND email IS NOT NULL
            ");
            $stmt->execute([$leader_group_id]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $reminders_sent = 0;
            $failed = 0;
            
            foreach ($members as $member) {
                $member_name = $member['full_name'];
                $member_email = trim($member['email']); // Trim whitespace
                
                if (filter_var($member_email, FILTER_VALIDATE_EMAIL)) {
                    $email_result = sendPaymentReminderEmail(
                        $member_email, 
                        $member_name, 
                        $group_name, 
                        $payment_cycle, 
                        $next_due_date->format('d M Y'),
                        $days_left
                    );
                    
                    if ($email_result === true) {
                        $reminders_sent++;
                        $reminder_results[] = [
                            'type' => 'success',
                            'message' => "✅ Sent to: $member_name ($member_email)"
                        ];
                    } else {
                        $failed++;
                        $reminder_results[] = [
                            'type' => 'error', 
                            'message' => "❌ Failed to send to: $member_name - " . $email_result
                        ];
                    }
                } else {
                    $reminder_results[] = [
                        'type' => 'warning',
                        'message' => "⚠️ Invalid/Missing email: $member_name ($member_email)"
                    ];
                }
            }
            
            $message = "Reminders process complete. **Sent**: $reminders_sent, **Failed**: $failed";
            
        } catch (PDOException $e) {
            $message = "Database error during member fetch: " . $e->getMessage();
        } catch (Exception $e) {
             $message = "Error in Date Calculation: " . $e->getMessage();
        }
    }
}


// Recalculate timeline details for display on the page load/after post
$next_cycle_number = null;
$days_left = null;
$next_due_date = null;
$cycles_completed = null;
$should_send_reminder = false;

if ($group_timeline && !empty($group_timeline['payment_start_date'])) {
    $today = new DateTime();
    $start_date = new DateTime($group_timeline['payment_start_date']);
    
    if ($start_date <= $today) {
        $cycle_days = ($group_timeline['payment_cycle'] == 'weekly') ? 7 : 30;
        $days_since_start = $start_date->diff($today)->days;
        
        $cycles_completed = floor($days_since_start / $cycle_days);
        
        $next_due_date_obj = clone $start_date;
        $next_due_date_obj->modify("+ " . ($cycles_completed * $cycle_days) . " days");
        
        if ($next_due_date_obj <= $today) {
             $cycles_completed++;
             $next_due_date_obj = clone $start_date;
             $next_due_date_obj->modify("+ " . ($cycles_completed * $cycle_days) . " days");
        }
        
        $next_cycle_number = $cycles_completed + 1;
        $days_left = $today->diff($next_due_date_obj)->days;
        $next_due_date = $next_due_date_obj->format('d M Y');
        
        $should_send_reminder = ($days_left >= 2 && $days_left <= 3);
        
    } else {
        // Start date is in the future
        $days_left = $today->diff($start_date)->days;
        $next_due_date = $start_date->format('d M Y');
        $cycles_completed = 0;
        $next_cycle_number = 1;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reminder - Leader Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: 1px solid #e3e6f0;
            margin-bottom: 20px;
        }
        .timeline-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
        }
        .stats-card {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        .due-soon {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        .email-log {
            max-height: 300px;
            overflow-y: auto;
        }
        .log-success { color: #28a745; }
        .log-error { color: #dc3545; }
        .log-warning { color: #ffc107; }
        .no-start-date-card {
            background-color: #ffe5e5;
            border-color: #dc3545;
            color: #dc3545;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="mb-3">
            <a href="leader_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="fas fa-bell me-2"></i>
                Payment Reminder System
            </h1>
            <span class="badge bg-primary fs-6">Leader Dashboard</span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($group_timeline): ?>
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="timeline-card">
                    <h3><?php echo htmlspecialchars($group_timeline['group_name']); ?></h3>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <h5>Payment Cycle</h5>
                            <p class="mb-0"><?php echo ucfirst($group_timeline['payment_cycle']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <h5>Start Date</h5>
                            <p class="mb-0">
                                <?php echo !empty($group_timeline['payment_start_date']) ? date('d M Y', strtotime($group_timeline['payment_start_date'])) : '<span class="text-warning">Not Set</span>'; ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h5>Last Payment</h5>
                            <p class="mb-0">
                                <?php echo $group_timeline['last_payment_date'] ? date('d M Y', strtotime($group_timeline['last_payment_date'])) : 'No payments yet'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                    <h2>₹<?php echo number_format($group_timeline['total_collected'], 2); ?></h2>
                    <p class="mb-0">Total Collected</p>
                    <small><?php echo $group_timeline['total_payments']; ?> Verified Payments</small>
                </div>
            </div>
        </div>

        <?php 
        // Display this section ONLY if payment_start_date is set
        if (!empty($group_timeline['payment_start_date'])): 
        ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Next Payment Schedule
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="border-end">
                            <h3 class="text-primary"><?php echo $cycles_completed; ?></h3>
                            <small class="text-muted">Cycles Completed</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h3 class="text-success"><?php echo $next_cycle_number; ?></h3>
                            <small class="text-muted">Next Cycle</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h3 class="<?php echo $days_left <= 3 ? 'text-danger' : 'text-warning'; ?>">
                                <?php echo $days_left; ?>
                            </h3>
                            <small class="text-muted">Days Left</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-info"><?php echo $next_due_date; ?></h3>
                        <small class="text-muted">Next Due Date</small>
                    </div>
                </div>
                
                <?php if ($should_send_reminder): ?>
                <div class="due-soon mt-4">
                    <h4><i class="fas fa-bell me-2"></i>Reminder Time!</h4>
                    <p class="mb-0">Next payment is due in **<?php echo $days_left; ?>** days. It's the perfect time to send reminders.</p>
                </div>
                <?php else: ?>
                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Next reminder will be sent automatically when payment is due in 2-3 days. (Currently **<?php echo $days_left; ?>** days left)
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <form method="POST" action="">
                        <button type="submit" name="send_reminders" class="btn btn-warning btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>
                            Send Payment Reminders to All Members
                        </button>
                        <small class="d-block text-muted mt-2">
                            This will send reminders to all active members about the payment due on **<?php echo $next_due_date; ?>**.
                        </small>
                    </form>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="no-start-date-card mb-4">
            <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
            <h4>Payment Start Date Not Set</h4>
            <p>Please set the **`Payment Start Date`** in your group settings to enable payment tracking and reminders.</p>
            <p class="mb-0">Reminder system cannot calculate the next due date without a starting point.</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($reminder_results)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-envelope me-2"></i>
                    Reminder Results
                </h5>
            </div>
            <div class="card-body">
                <div class="email-log">
                    <?php foreach ($reminder_results as $result): ?>
                        <div class="log-<?php echo $result['type']; ?> mb-2">
                            <?php echo $result['message']; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h4>Group Not Found</h4>
                <p>Your group information could not be loaded. Please contact the administrator.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>