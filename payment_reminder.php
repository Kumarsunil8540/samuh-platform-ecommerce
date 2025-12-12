<?php
// payment_reminder.php - Backend Automatic Script
require_once 'config.php';

// PHPMailer setup
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

// Function to send payment reminder email using PHPMailer (same as above)
function sendPaymentReminderEmail($to_email, $member_name, $group_name, $payment_cycle, $next_due_date, $days_left) {
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
        $mail->addAddress($to_email, $member_name);

        $mail->isHTML(true);
        
        $cycle_text = ($payment_cycle == 'weekly') ? 'weekly' : 'monthly';
        $subject = "Upcoming Payment Reminder - $group_name";
        
        $mail->Subject = $subject;
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #007bff; text-align: center;'>Payment Reminder</h2>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                    <p>Hello <strong>" . htmlspecialchars($member_name) . "</strong>,</p>
                    
                    <p>This is a friendly reminder about your upcoming payment for group: <strong>" . htmlspecialchars($group_name) . "</strong></p>
                    
                    <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                        <h3 style='color: #dc3545;'>Payment Details:</h3>
                        <table style='width: 100%;'>
                            <tr>
                                <td><strong>Group:</strong></td>
                                <td>" . htmlspecialchars($group_name) . "</td>
                            </tr>
                            <tr>
                                <td><strong>Payment Cycle:</strong></td>
                                <td>" . ucfirst($cycle_text) . "</td>
                            </tr>
                            <tr>
                                <td><strong>Next Due Date:</strong></td>
                                <td>" . $next_due_date . "</td>
                            </tr>
                            <tr>
                                <td><strong>Days Left:</strong></td>
                                <td><strong>" . $days_left . " days</strong></td>
                            </tr>
                        </table>
                    </div>
                    
                    <p>Please make your payment before the due date to avoid any late fees.</p>
                    
                    <p>Thank you for your cooperation!</p>
                </div>
                
                <div style='text-align: center; color: #6c757d; font-size: 0.9em;'>
                    <p>Samuh Platform - Managing Groups Efficiently</p>
                    <p><small>This is an automated reminder.</small></p>
                </div>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// Main reminder processing function for ALL groups
function processAllGroupsPaymentReminders($conn) {
    $results = [
        'total_groups_checked' => 0,
        'total_reminders_sent' => 0,
        'total_failed' => 0,
        'group_details' => []
    ];
    
    try {
        // Get all active groups with payment start date
        $sql = "SELECT id, group_name, payment_cycle, payment_start_date 
                FROM groups 
                WHERE status = 'active' AND payment_start_date IS NOT NULL";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results['total_groups_checked'] = count($groups);
        
        foreach ($groups as $group) {
            $group_id = $group['id'];
            $group_name = $group['group_name'];
            $payment_cycle = $group['payment_cycle'];
            $payment_start_date = $group['payment_start_date'];
            
            $group_result = [
                'group_id' => $group_id,
                'group_name' => $group_name,
                'reminders_sent' => 0,
                'failed' => 0
            ];
            
            // Calculate timeline
            $today = new DateTime();
            $start_date = new DateTime($payment_start_date);
            $days_since_start = $start_date->diff($today)->days;
            
            // Determine cycle days
            $cycle_days = ($payment_cycle == 'weekly') ? 7 : 30;
            
            // Calculate next due date and days left
            $cycles_completed = floor($days_since_start / $cycle_days);
            $next_cycle_number = $cycles_completed + 1;
            $next_due_date = clone $start_date;
            $next_due_date->modify("+ " . ($next_cycle_number * $cycle_days) . " days");
            
            $days_left = $today->diff($next_due_date)->days;
            
            // Check if we should send reminder (2-3 days before due date)
            if ($days_left >= 2 && $days_left <= 3) {
                // Get all active members for this group
                $stmt = $conn->prepare("
                    SELECT id, full_name, email 
                    FROM members 
                    WHERE group_id = ? AND is_active = 1 AND email IS NOT NULL
                ");
                $stmt->execute([$group_id]);
                $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($members as $member) {
                    $member_name = $member['full_name'];
                    $member_email = $member['email'];
                    
                    // Validate email
                    if (filter_var($member_email, FILTER_VALIDATE_EMAIL)) {
                        // Send reminder email
                        $email_sent = sendPaymentReminderEmail(
                            $member_email, 
                            $member_name, 
                            $group_name, 
                            $payment_cycle, 
                            $next_due_date->format('d M Y'),
                            $days_left
                        );
                        
                        if ($email_sent === true) {
                            $results['total_reminders_sent']++;
                            $group_result['reminders_sent']++;
                        } else {
                            $results['total_failed']++;
                            $group_result['failed']++;
                        }
                    }
                }
            }
            
            $results['group_details'][] = $group_result;
        }
        
    } catch (PDOException $e) {
        $results['error'] = "Database error: " . $e->getMessage();
    }
    
    return $results;
}

// This script runs automatically - no HTML output
$results = processAllGroupsPaymentReminders($conn);

// Log results to file for cron job tracking
$log_message = date('Y-m-d H:i:s') . " - Reminders Sent: " . $results['total_reminders_sent'] . 
               ", Groups Checked: " . $results['total_groups_checked'] . 
               ", Failed: " . $results['total_failed'] . "\n";
file_put_contents('payment_reminder.log', $log_message, FILE_APPEND);

// If accessed via browser, show simple message
if (php_sapi_name() !== 'cli') {
    echo "<h3>Payment Reminder System - Backend</h3>";
    echo "<p>This script runs automatically. Reminders processed successfully.</p>";
    echo "<p>Groups Checked: " . $results['total_groups_checked'] . "</p>";
    echo "<p>Reminders Sent: " . $results['total_reminders_sent'] . "</p>";
    echo "<p>Failed: " . $results['total_failed'] . "</p>";
    echo "<p>Check payment_reminder.log for details.</p>";
}
?>