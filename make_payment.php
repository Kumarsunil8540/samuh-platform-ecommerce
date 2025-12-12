<?php
session_start();
include("config.php");

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: core_member_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];
$group_id = $_SESSION['group_id'];

$success_message = '';
$error_message = '';
$payment_type = 'regular';

// Get payment type from URL
if (isset($_GET['type'])) {
    $payment_type = $_GET['type'];
}

// Get late fee ID if available
$late_fee_id = isset($_GET['late_fee_id']) ? $_GET['late_fee_id'] : null;

// ==================== MEMBER PAYMENT SUBMISSION ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment']) && $role === 'member') {
    $payment_amount = $_POST['payment_amount'];
    $qr_id = $_POST['qr_id'];
    $payment_type = $_POST['payment_type'];
    $late_fee_id = $_POST['late_fee_id'] ?? null;
    
    if (empty($payment_amount) || $payment_amount <= 0) {
        $error_message = "❌ Please enter a valid payment amount!";
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Insert into payment_request table
            $sql = "INSERT INTO payment_request 
                    (group_id, member_id, payment_amount, payment_date, qr_id, payment_status, payment_type) 
                    VALUES (?, ?, ?, CURDATE(), ?, 'pending', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$group_id, $user_id, $payment_amount, $qr_id, $payment_type]);
            
            $payment_id = $conn->lastInsertId();
            
            // If it's late fee payment, update late_fees table with payment_request_id
            if ($payment_type === 'late_fee' && $late_fee_id) {
                $update_sql = "UPDATE late_fees SET payment_request_id = ? WHERE id = ? AND member_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->execute([$payment_id, $late_fee_id, $user_id]);
            }
            
            // Commit transaction
            $conn->commit();
            
            $payment_type_text = $payment_type === 'late_fee' ? 'Late Fee' : 'Regular';
            $success_message = "✅ {$payment_type_text} payment submitted! Waiting for verification.";
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error_message = "❌ Payment failed: " . $e->getMessage();
        }
    }
}

// ==================== ACCOUNTANT PAYMENT VERIFICATION ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment']) && $role === 'accountant') {
    $payment_id = $_POST['payment_id'];
    $action = $_POST['action'];
    
    try {
        $conn->beginTransaction();
        
        if ($action === 'verify') {
            // Update payment status to verified
            $update_sql = "UPDATE payment_request SET 
                          payment_status = 'verified', 
                          verified_by_accountant = ?, 
                          verified_at = NOW() 
                          WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->execute([$user_id, $payment_id]);
            
            // If it's late fee payment, update late_fees table - mark as paid
            $check_sql = "SELECT payment_type FROM payment_request WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([$payment_id]);
            $payment_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment_data && $payment_data['payment_type'] === 'late_fee') {
                $late_fee_sql = "UPDATE late_fees SET is_paid = 1 WHERE payment_request_id = ?";
                $late_fee_stmt = $conn->prepare($late_fee_sql);
                $late_fee_stmt->execute([$payment_id]);
            }
            
            $success_message = "✅ Payment verified successfully!";
            
        } else {
            // Reject payment
            $update_sql = "UPDATE payment_request SET 
                          payment_status = 'rejected',
                          verified_by_accountant = ?,
                          verified_at = NOW()
                          WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->execute([$user_id, $payment_id]);
            
            $success_message = "✅ Payment rejected!";
        }
        
        $conn->commit();
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "❌ Verification failed: " . $e->getMessage();
    }
}

// ==================== FETCH DATA ====================
try {
    if ($role === 'member') {
        // Get active QR code
        $qr_sql = "SELECT id, qr_image FROM qr_records WHERE is_active = 1 AND group_id = ? LIMIT 1";
        $qr_stmt = $conn->prepare($qr_sql);
        $qr_stmt->execute([$group_id]);
        $active_qr = $qr_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get expected amount for regular payment
        $expected_sql = "SELECT expected_amount FROM groups WHERE id = ?";
        $expected_stmt = $conn->prepare($expected_sql);
        $expected_stmt->execute([$group_id]);
        $group_data = $expected_stmt->fetch(PDO::FETCH_ASSOC);
        $expected_amount = $group_data['expected_amount'] ?? 1000;
        
        // Get member's unpaid late fees
        $late_fees_sql = "SELECT * FROM late_fees WHERE member_id = ? AND is_paid = 0 ORDER BY cycle_no DESC";
        $late_fees_stmt = $conn->prepare($late_fees_sql);
        $late_fees_stmt->execute([$user_id]);
        $unpaid_late_fees = $late_fees_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get selected late fee details
        $selected_late_fee = null;
        if ($late_fee_id) {
            $late_fee_sql = "SELECT * FROM late_fees WHERE id = ? AND member_id = ? AND is_paid = 0";
            $late_fee_stmt = $conn->prepare($late_fee_sql);
            $late_fee_stmt->execute([$late_fee_id, $user_id]);
            $selected_late_fee = $late_fee_stmt->fetch(PDO::FETCH_ASSOC);
        }
        
    } else if ($role === 'accountant') {
        // Get pending payments for verification
        $pending_sql = "SELECT pr.*, m.full_name, m.mobile, g.group_name
                       FROM payment_request pr
                       JOIN members m ON pr.member_id = m.id
                       JOIN groups g ON pr.group_id = g.id
                       WHERE pr.payment_status = 'pending' AND pr.group_id = ?
                       ORDER BY pr.payment_date DESC";
        $pending_stmt = $conn->prepare($pending_sql);
        $pending_stmt->execute([$group_id]);
        $pending_payments = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payment history
        $history_sql = "SELECT pr.*, m.full_name, a.full_name as accountant_name
                       FROM payment_request pr
                       JOIN members m ON pr.member_id = m.id
                       LEFT JOIN accountants a ON pr.verified_by_accountant = a.id
                       WHERE pr.group_id = ? AND pr.payment_status != 'pending'
                       ORDER BY pr.verified_at DESC 
                       LIMIT 20";
        $history_stmt = $conn->prepare($history_sql);
        $history_stmt->execute([$group_id]);
        $payment_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error_message = "❌ Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role === 'member' ? 'Make Payment' : 'Verify Payments'; ?> - Samuh</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        
        .header { 
            background: white; padding: 15px 20px; 
            display: flex; justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .alert { 
            padding: 15px; margin: 10px 20px; border-radius: 5px;
        }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        
        /* Member Styles */
        .payment-types { display: flex; gap: 15px; margin-bottom: 20px; }
        .payment-type { 
            flex: 1; padding: 20px; background: white; 
            border: 2px solid #ddd; border-radius: 8px; cursor: pointer;
            text-align: center;
        }
        .payment-type.active { border-color: #007bff; background: #007bff; color: white; }
        
        .qr-section { 
            display: flex; gap: 30px; background: white; 
            padding: 20px; border-radius: 8px; margin-bottom: 20px;
        }
        .qr-image { max-width: 200px; border: 1px solid #ddd; padding: 10px; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { 
            width: 100%; padding: 10px; border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        .btn { 
            padding: 12px 25px; border: none; border-radius: 4px;
            cursor: pointer; font-size: 16px;
        }
        .btn-primary { background: #007bff; color: white; }
        
        /* Accountant Styles */
        .payment-card {
            background: white; padding: 20px; margin-bottom: 15px;
            border-radius: 8px; border-left: 4px solid #ffc107;
        }
        .payment-actions { margin-top: 15px; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        
        table { width: 100%; background: white; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        
        .late-fee-details {
            background: #fff3cd; padding: 15px; border-radius: 5px; 
            margin-bottom: 15px; border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo $role === 'member' ? 'Make Payment' : 'Payment Verification'; ?></h1>
        <div>
            <strong><?php echo htmlspecialchars($user_name); ?></strong> 
            (<?php echo ucfirst($role); ?>)
            <a href="<?php echo $role . '_dashboard.php'; ?>" style="margin-left: 15px;">← Dashboard</a>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert success">✅ <?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert error">❌ <?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="container">
        <!-- MEMBER VIEW -->
        <?php if ($role === 'member'): ?>
            <div class="payment-types">
                <div class="payment-type <?php echo $payment_type === 'regular' ? 'active' : ''; ?>" 
                     onclick="window.location='make_payment.php?type=regular'">
                    <h3>Regular Payment</h3>
                    <p>Monthly Contribution</p>
                </div>
                <div class="payment-type <?php echo $payment_type === 'late_fee' ? 'active' : ''; ?>" 
                     onclick="window.location='make_payment.php?type=late_fee'">
                    <h3>Late Fee</h3>
                    <p>Pay Pending Fines</p>
                </div>
            </div>

            <?php if ($active_qr): ?>
                <div class="qr-section">
                    <div>
                        <h3>Scan QR Code</h3>
                        <img src="<?php echo htmlspecialchars($active_qr['qr_image']); ?>" 
                             alt="QR Code" class="qr-image">
                        <p>Use any UPI app to scan and pay</p>
                    </div>
                    
                    <div style="flex: 1;">
                        <h3>Payment Details</h3>
                        
                        <!-- Regular Payment -->
                        <?php if ($payment_type === 'regular'): ?>
                            <div class="late-fee-details">
                                <h4>Regular Payment Amount</h4>
                                <p><strong>₹<?php echo number_format($expected_amount, 2); ?></strong></p>
                                <p>This is your fixed monthly contribution amount.</p>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="qr_id" value="<?php echo $active_qr['id']; ?>">
                                <input type="hidden" name="payment_type" value="regular">
                                
                                <div class="form-group">
                                    <label>Amount (₹):</label>
                                    <input type="number" name="payment_amount" 
                                           value="<?php echo $expected_amount; ?>" 
                                           readonly
                                           required>
                                </div>
                                
                                <button type="submit" name="submit_payment" class="btn btn-primary">
                                    Submit Regular Payment
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Late Fee Payment -->
                        <?php if ($payment_type === 'late_fee'): ?>
                            <?php if (empty($unpaid_late_fees)): ?>
                                <div class="late-fee-details">
                                    <h4>No Pending Late Fees</h4>
                                    <p>You don't have any pending late fees to pay.</p>
                                </div>
                            <?php else: ?>
                                <?php if (!$late_fee_id): ?>
                                    <div class="form-group">
                                        <label>Select Late Fee to Pay:</label>
                                        <select onchange="if(this.value) window.location='make_payment.php?type=late_fee&late_fee_id='+this.value">
                                            <option value="">-- Select Late Fee --</option>
                                            <?php foreach ($unpaid_late_fees as $fee): ?>
                                                <option value="<?php echo $fee['id']; ?>">
                                                    Cycle <?php echo $fee['cycle_no']; ?> - 
                                                    ₹<?php echo number_format($fee['fine_amount'], 2); ?> 
                                                    (<?php echo $fee['days_late']; ?> days late)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($selected_late_fee): ?>
                                    <div class="late-fee-details">
                                        <h4>Selected Late Fee Details</h4>
                                        <p><strong>Cycle:</strong> <?php echo $selected_late_fee['cycle_no']; ?></p>
                                        <p><strong>Due Date:</strong> <?php echo date('d M, Y', strtotime($selected_late_fee['due_date'])); ?></p>
                                        <p><strong>Days Late:</strong> <?php echo $selected_late_fee['days_late']; ?> days</p>
                                        <p><strong>Fine Amount:</strong> ₹<?php echo number_format($selected_late_fee['fine_amount'], 2); ?></p>
                                    </div>
                                    
                                    <form method="POST">
                                        <input type="hidden" name="qr_id" value="<?php echo $active_qr['id']; ?>">
                                        <input type="hidden" name="payment_type" value="late_fee">
                                        <input type="hidden" name="late_fee_id" value="<?php echo $selected_late_fee['id']; ?>">
                                        
                                        <div class="form-group">
                                            <label>Late Fee Amount (₹):</label>
                                            <input type="number" name="payment_amount" 
                                                   value="<?php echo $selected_late_fee['fine_amount']; ?>" 
                                                   readonly
                                                   required>
                                        </div>
                                        
                                        <button type="submit" name="submit_payment" class="btn btn-primary">
                                            Pay Late Fee
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert error">
                    ❌ No active QR code found. Please contact your group administrator.
                </div>
            <?php endif; ?>

        <!-- ACCOUNTANT VIEW -->
        <?php elseif ($role === 'accountant'): ?>
            <h2>Pending Payments (<?php echo count($pending_payments); ?>)</h2>
            
            <?php if (empty($pending_payments)): ?>
                <div class="alert success">
                    ✅ No pending payments. All payments have been verified.
                </div>
            <?php else: ?>
                <?php foreach ($pending_payments as $payment): ?>
                    <div class="payment-card">
                        <h3><?php echo htmlspecialchars($payment['full_name']); ?> (<?php echo $payment['mobile']; ?>)</h3>
                        <p><strong>Amount:</strong> ₹<?php echo number_format($payment['payment_amount'], 2); ?></p>
                        <p><strong>Type:</strong> <?php echo ucfirst($payment['payment_type']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('d M, Y', strtotime($payment['payment_date'])); ?></p>
                        <p><strong>Group:</strong> <?php echo htmlspecialchars($payment['group_name']); ?></p>
                        
                        <div class="payment-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                <input type="hidden" name="action" value="verify">
                                <button type="submit" name="verify_payment" class="btn btn-success">
                                    ✓ Verify Payment
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" name="verify_payment" class="btn btn-danger" 
                                        onclick="return confirm('Are you sure you want to reject this payment?')">
                                    ✗ Reject Payment
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <h2 style="margin-top: 30px;">Payment History</h2>
            <?php if (empty($payment_history)): ?>
                <p>No payment history available.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Verified By</th>
                            <th>Verified At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_history as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                <td><?php echo ucfirst($payment['payment_type']); ?></td>
                                <td>₹<?php echo number_format($payment['payment_amount'], 2); ?></td>
                                <td><?php echo date('d M, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 4px; 
                                          background: <?php echo $payment['payment_status'] === 'verified' ? '#d4edda' : '#f8d7da'; ?>;
                                          color: <?php echo $payment['payment_status'] === 'verified' ? '#155724' : '#721c24'; ?>;">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $payment['accountant_name'] ?? 'N/A'; ?></td>
                                <td><?php echo $payment['verified_at'] ? date('d M, Y H:i', strtotime($payment['verified_at'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>