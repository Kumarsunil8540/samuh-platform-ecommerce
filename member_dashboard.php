<?php
session_start();
include("config.php");

// Check if member is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: core_member_login.php?role=member");
    exit;
}

// Get member details
$member_id = $_SESSION['user_id'];
$group_id = $_SESSION['group_id'];

// Initialize variables with default values
$member = [];
$expected_amount = 0;
$due_date = null;
$next_payment_date = null;
$days_remaining = 0;
$current_payment = null;
$payment_history = [];
$notifications = [];
$error = '';
$unpaid_late_fees = [];
$total_unpaid_fine = 0;
$payment_cycle = 'monthly';
$payment_start_date = null;

try {
    // Fetch member details with group information
    $member_sql = "SELECT m.*, g.group_name, g.expected_amount, g.payment_cycle, 
                          g.payment_start_date, g.late_fee_type, g.late_fee_value
                   FROM members m 
                   JOIN groups g ON m.group_id = g.id 
                   WHERE m.id = ?";
    $member_stmt = $conn->prepare($member_sql);
    $member_stmt->execute([$member_id]);
    $member = $member_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        session_destroy();
        header("Location: core_member_login.php?role=member");
        exit;
    }

    // Get group payment details
    $expected_amount = $member['expected_amount'] ?? 0;
    $payment_cycle = $member['payment_cycle'] ?? 'monthly';
    $payment_start_date = $member['payment_start_date'] ?? null;

    // Calculate next payment date based on payment cycle
    $today = new DateTime();
    $next_payment_date = null;
    $days_remaining = 0;
    
    if ($payment_start_date) {
        $start_date = new DateTime($payment_start_date);
        $next_payment_date = clone $start_date;
        
        // Find the next payment date after today
        while ($next_payment_date <= $today) {
            if ($payment_cycle === 'weekly') {
                $next_payment_date->modify('+7 days');
            } else {
                $next_payment_date->modify('+1 month');
            }
        }
        
        // Calculate days remaining
        $interval = $today->diff($next_payment_date);
        $days_remaining = $interval->days;
        
        // Format dates for display
        $due_date = $next_payment_date->format('Y-m-d');
    }

    // Fetch unpaid late fees from late_fees table
    $late_fee_sql = "SELECT * FROM late_fees 
                     WHERE member_id = ? AND is_paid = 0
                     ORDER BY cycle_no DESC, created_at DESC";
    $late_fee_stmt = $conn->prepare($late_fee_sql);
    $late_fee_stmt->execute([$member_id]);
    $unpaid_late_fees = $late_fee_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_unpaid_fine = array_sum(array_column($unpaid_late_fees, 'fine_amount'));

    // Check if payment already made for current cycle
    if ($payment_start_date) {
        $cycle_start = clone $next_payment_date;
        if ($payment_cycle === 'weekly') {
            $cycle_start->modify('-7 days');
        } else {
            $cycle_start->modify('-1 month');
        }
        
        $cycle_start_date = $cycle_start->format('Y-m-d');
        $cycle_end_date = $next_payment_date->format('Y-m-d');
        
        $payment_sql = "SELECT * FROM payment_request 
                       WHERE member_id = ? 
                       AND payment_date BETWEEN ? AND ?
                       AND payment_status = 'verified'";
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_stmt->execute([$member_id, $cycle_start_date, $cycle_end_date]);
        $current_payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch payment history
    $history_sql = "SELECT * FROM payment_request 
                   WHERE member_id = ? 
                   ORDER BY payment_date DESC 
                   LIMIT 10";
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->execute([$member_id]);
    $payment_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch notifications
    $notif_sql = "SELECT * FROM notifications 
                 WHERE user_id = ? AND user_type = 'member' 
                 ORDER BY created_at DESC 
                 LIMIT 5";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->execute([$member_id]);
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Database error occurred: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - Samuh</title>
    <link rel="stylesheet" href="member_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="dashboard-header">
        <div class="header-left">
            <div class="logo">
                <i class="fas fa-users"></i>
                <span>Samuh</span>
            </div>
        </div>
        
        <div class="header-right">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo isset($member['full_name']) ? strtoupper(substr($member['full_name'], 0, 1)) : 'U'; ?>
                </div>
                <span class="user-name"><?php echo isset($member['full_name']) ? htmlspecialchars($member['full_name']) : 'User'; ?></span>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <!-- Main Dashboard -->
    <main class="dashboard-main">
        <!-- Error Message -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Unpaid Late Fees Alert -->
        <?php if (!empty($unpaid_late_fees)): ?>
        <div class="late-fees-alert">
            <h4>
                <i class="fas fa-exclamation-triangle"></i>
                <span class="hindi">⚠️ बकाया जुर्माना</span>
                <span class="english">⚠️ Unpaid Late Fees</span>
            </h4>
            <div class="alert-content">
                <div>
                    <p class="hindi" style="margin: 0;">आपके <?php echo count($unpaid_late_fees); ?> जुर्माने बकाया हैं</p>
                    <p class="english" style="margin: 0;">You have <?php echo count($unpaid_late_fees); ?> unpaid late fee(s)</p>
                    <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">
                        <span class="hindi">कुल राशि: <strong>₹<?php echo number_format($total_unpaid_fine, 2); ?></strong></span>
                        <span class="english">Total Amount: <strong>₹<?php echo number_format($total_unpaid_fine, 2); ?></strong></span>
                    </p>
                </div>
                <a href="make_payment.php" class="pay-late-fee-btn">
                    <i class="fas fa-credit-card"></i> 
                    <span class="hindi">अभी भरें</span>
                    <span class="english">Pay Now</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <section class="welcome-section">
            <h1>
                <span class="hindi">नमस्ते, <?php echo isset($member['full_name']) ? htmlspecialchars($member['full_name']) : 'User'; ?>!</span>
                <span class="english">Welcome, <?php echo isset($member['full_name']) ? htmlspecialchars($member['full_name']) : 'User'; ?>!</span>
            </h1>
            <p>
                <span class="hindi">ग्रुप: <?php echo isset($member['group_name']) ? htmlspecialchars($member['group_name']) : 'N/A'; ?></span>
                <span class="english">Group: <?php echo isset($member['group_name']) ? htmlspecialchars($member['group_name']) : 'N/A'; ?></span>
            </p>
            
            <!-- Navigation Links -->
            <div class="nav-links">
                <a href="qr_history.php" class="nav-item">
                    <span class="hindi">📱 क्यूआर इतिहास</span>
                    <span class="english">📱 QR History</span>
                </a>

                <a href="set_payment_start.php" class="nav-item">
                    <span class="hindi">📅 भुगतान आरंभ तिथि</span>
                    <span class="english">📅 Payment Start Date</span>
                </a>
                
                <a href="group_payment_history.php" class="nav-item">
                    <span class="hindi">📊 भुगतान इतिहास</span>
                    <span class="english">📊 Payment History</span>
                </a>
            </div>

            <!-- Current Payment Status -->
            <div class="payment-status-card">
                <div class="status-item">
                    <span class="status-label hindi">मासिक योगदान:</span>
                    <span class="status-label english">Monthly Contribution:</span>
                    <span class="status-value">₹<?php echo number_format($expected_amount, 2); ?></span>
                </div>
                
                <?php if ($due_date): ?>
                <div class="status-item">
                    <span class="status-label hindi">अगला भुगतान तिथि:</span>
                    <span class="status-label english">Next Payment Date:</span>
                    <span class="status-value"><?php echo date('d M, Y', strtotime($due_date)); ?></span>
                </div>
                
                <div class="status-item">
                    <span class="status-label hindi">दिन शेष:</span>
                    <span class="status-label english">Days Remaining:</span>
                    <span class="status-value <?php echo $days_remaining <= 3 ? 'text-warning' : ''; ?>">
                        <?php echo $days_remaining; ?> दिन / days
                    </span>
                </div>
                <?php else: ?>
                <div class="status-item">
                    <span class="status-label hindi">भुगतान तिथि:</span>
                    <span class="status-label english">Payment Date:</span>
                    <span class="status-value text-muted">
                        <span class="hindi">सेट नहीं है</span>
                        <span class="english">Not Set</span>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="status-item">
                    <span class="status-label hindi">भुगतान चक्र:</span>
                    <span class="status-label english">Payment Cycle:</span>
                    <span class="status-value">
                        <?php 
                        $cycle_text = [
                            'weekly' => ['hindi' => 'साप्ताहिक', 'english' => 'Weekly'],
                            'monthly' => ['hindi' => 'मासिक', 'english' => 'Monthly']
                        ];
                        $current_cycle = $cycle_text[$payment_cycle] ?? $cycle_text['monthly'];
                        ?>
                        <span class="hindi"><?php echo $current_cycle['hindi']; ?></span>
                        <span class="english"><?php echo $current_cycle['english']; ?></span>
                    </span>
                </div>
                
                <div class="status-item">
                    <span class="status-label hindi">वर्तमान स्थिति:</span>
                    <span class="status-label english">Current Status:</span>
                    <span class="status-value status-<?php echo $current_payment ? $current_payment['payment_status'] : 'pending'; ?>">
                        <?php 
                        if ($current_payment) {
                            $status_text = [
                                'verified' => ['hindi' => 'सत्यापित ✅', 'english' => 'Verified ✅'],
                                'pending' => ['hindi' => 'लंबित ⏳', 'english' => 'Pending ⏳'],
                                'rejected' => ['hindi' => 'अस्वीकृत ❌', 'english' => 'Rejected ❌']
                            ];
                            $current_status = $status_text[$current_payment['payment_status']] ?? $status_text['pending'];
                            echo $current_status['hindi'] . ' / ' . $current_status['english'];
                        } else {
                            echo '<span class="hindi">लंबित ⏳</span><span class="english">Pending ⏳</span>';
                        }
                        ?>
                    </span>
                </div>
                
                <!-- Total Unpaid Late Fees -->
                <?php if (!empty($unpaid_late_fees)): ?>
                <div class="status-item">
                    <span class="status-label hindi">कुल बकाया जुर्माना:</span>
                    <span class="status-label english">Total Unpaid Late Fees:</span>
                    <span class="status-value late-fee">₹<?php echo number_format($total_unpaid_fine, 2); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Payment Cycle Info -->
            <?php if ($payment_start_date): ?>
            <div class="cycle-info">
                <p style="text-align: center; color: #666; font-size: 0.9rem; margin-top: 10px;">
                    <span class="hindi">
                        💡 भुगतान चक्र: 
                        <?php echo $payment_cycle === 'weekly' ? 'हर सप्ताह' : 'हर महीने'; ?> 
                        <?php echo date('d M', strtotime($payment_start_date)); ?> को
                    </span>
                    <span class="english">
                        💡 Payment Cycle: 
                        <?php echo $payment_cycle === 'weekly' ? 'Every week' : 'Every month'; ?> 
                        on <?php echo date('d M', strtotime($payment_start_date)); ?>
                    </span>
                </p>
            </div>
            <?php endif; ?>
        </section>

        <!-- Quick Actions Grid -->
        <section class="actions-grid">
            <a href="make_payment.php" class="action-card payment-card">
                <div class="card-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <h3>
                    <span class="hindi">भुगतान करें</span>
                    <span class="english">Make Payment</span>
                </h3>
                <p>
                    <span class="hindi">मासिक भुगतान जमा करें</span>
                    <span class="english">Submit your monthly payment</span>
                </p>
                <?php if (!$current_payment && $due_date && $days_remaining <= 7): ?>
                    <div class="card-badge">
                        <span class="hindi">जल्दी करें</span>
                        <span class="english">Due Soon</span>
                    </div>
                <?php endif; ?>
            </a>

            <a href="late_fee_record.php" class="action-card fine-card">
                <div class="card-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>
                    <span class="hindi">जुर्माना</span>
                    <span class="english">Late Fees</span>
                </h3>
                <p>
                    <span class="hindi">बकाया जुर्माना भरें</span>
                    <span class="english">Pay pending late fees</span>
                </p>
                <?php if (!empty($unpaid_late_fees)): ?>
                    <div class="card-badge"><?php echo count($unpaid_late_fees); ?></div>
                <?php endif; ?>
            </a>

            <a href="group_payment_history.php" class="action-card history-card">
                <div class="card-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h3>
                    <span class="hindi">भुगतान इतिहास</span>
                    <span class="english">Payment History</span>
                </h3>
                <p>
                    <span class="hindi">अपने भुगतान रिकॉर्ड देखें</span>
                    <span class="english">View your payment records</span>
                </p>
            </a>

            <a href="loan_request_form.php" class="action-card loan-card">
                <div class="card-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <h3>
                    <span class="hindi">लोन आवेदन</span>
                    <span class="english">Loan Request</span>
                </h3>
                <p>
                    <span class="hindi">नया लोन के लिए आवेदन करें</span>
                    <span class="english">Apply for new loan</span>
                </p>
            </a>

            <a href="loan_repayment.php" class="action-card repayment-card">
                <div class="card-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h3>
                    <span class="hindi">लोन भुगतान</span>
                    <span class="english">Loan Repayment</span>
                </h3>
                <p>
                    <span class="hindi">लोन की किश्त भरें</span>
                    <span class="english">Pay loan installment</span>
                </p>
            </a>

            <a href="loan_summary.php" class="action-card summary-card">
                <div class="card-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h3>
                    <span class="hindi">लोन सारांश</span>
                    <span class="english">Loan Summary</span>
                </h3>
                <p>
                    <span class="hindi">लोन विवरण देखें</span>
                    <span class="english">View loan details</span>
                </p>
            </a>

            <a href="forgot_password.php" class="action-card password-card">
                <div class="card-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h3>
                    <span class="hindi">पासवर्ड बदलें</span>
                    <span class="english">Change Password</span>
                </h3>
                <p>
                    <span class="hindi">लॉगिन पासवर्ड अपडेट करें</span>
                    <span class="english">Update your login password</span>
                </p>
            </a>
        </section>

        <!-- Payment History -->
        <?php if (!empty($payment_history)): ?>
        <section class="history-section">
            <h3>
                <span class="hindi">🕒 हाल के भुगतान</span>
                <span class="english">🕒 Recent Payments</span>
            </h3>
            <div class="history-list">
                <?php foreach (array_slice($payment_history, 0, 3) as $payment): ?>
                <div class="history-item">
                    <div class="history-icon">
                        <i class="fas fa-<?php echo $payment['payment_status'] === 'verified' ? 'check-circle success' : 'clock warning'; ?>"></i>
                    </div>
                    <div class="history-content">
                        <h4>₹<?php echo number_format($payment['payment_amount'], 2); ?></h4>
                        <p><?php echo date('d M, Y', strtotime($payment['payment_date'])); ?></p>
                    </div>
                    <div class="history-status">
                        <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                            <?php 
                            $status_text = [
                                'verified' => ['hindi' => 'सत्यापित', 'english' => 'Verified'],
                                'pending' => ['hindi' => 'लंबित', 'english' => 'Pending'],
                                'rejected' => ['hindi' => 'अस्वीकृत', 'english' => 'Rejected']
                            ];
                            $current_status = $status_text[$payment['payment_status']] ?? $status_text['pending'];
                            ?>
                            <span class="hindi"><?php echo $current_status['hindi']; ?></span>
                            <span class="english"><?php echo $current_status['english']; ?></span>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="group_payment_history.php" class="view-all-link">
                <span class="hindi">सभी भुगतान देखें →</span>
                <span class="english">View All Payments →</span>
            </a>
        </section>
        <?php else: ?>
        <section class="history-section">
            <h3>
                <span class="hindi">🕒 भुगतान इतिहास</span>
                <span class="english">🕒 Payment History</span>
            </h3>
            <div class="no-data">
                <i class="fas fa-file-invoice"></i>
                <p>
                    <span class="hindi">अभी तक कोई भुगतान नहीं</span>
                    <span class="english">No payments yet</span>
                </p>
            </div>
        </section>
        <?php endif; ?>

        <!-- Notifications -->
        <?php if (!empty($notifications)): ?>
        <section class="notifications-section">
            <h3>
                <span class="hindi">🔔 सूचनाएं</span>
                <span class="english">🔔 Notifications</span>
            </h3>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item">
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="notification-content">
                        <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <span class="notification-time">
                            <?php echo date('d M, g:i A', strtotime($notification['created_at'])); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php else: ?>
        <section class="notifications-section">
            <h3>
                <span class="hindi">🔔 सूचनाएं</span>
                <span class="english">🔔 Notifications</span>
            </h3>
            <div class="no-data">
                <i class="fas fa-bell-slash"></i>
                <p>
                    <span class="hindi">कोई नई सूचना नहीं</span>
                    <span class="english">No new notifications</span>
                </p>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="dashboard-footer">
        <p>
            <span class="hindi">© 2024 समूह प्लेटफॉर्म। सर्वाधिकार सुरक्षित।</span>
            <span class="english">© 2024 Samuh Platform. All rights reserved.</span>
        </p>
    </footer>

    <script src="member_dashboard.js"></script>
</body>
</html>