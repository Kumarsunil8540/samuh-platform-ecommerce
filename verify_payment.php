<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'accountant') {
    header("location: core_member_login.php?role=accountant");
    exit;
}

include("config.php");

$group_id = $_SESSION['group_id'];
$accountant_id = $_SESSION['user_id'];

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = $_POST['payment_id'];
    $action = $_POST['action'];
    
    try {
        $conn->beginTransaction();
        
        if ($action === 'verify') {
            $update_sql = "UPDATE loan_payments SET 
                          verification_status = 'verified',
                          verified_by = ?,
                          verified_at = NOW()
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([$accountant_id, $payment_id]);
            
        } elseif ($action === 'reject') {
            $reject_reason = $_POST['reject_reason'];
            $update_sql = "UPDATE loan_payments SET 
                          verification_status = 'rejected',
                          verified_by = ?,
                          verified_at = NOW(),
                          verification_notes = ?
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([$accountant_id, $reject_reason, $payment_id]);
        }
        
        $conn->commit();
        $_SESSION['message'] = "Payment " . $action . "ed successfully!";
        header("Location: verify_payment.php");
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch pending payments for verification
$pending_payments_sql = "SELECT lp.*, l.applicant_type, 
                        COALESCE(m.full_name, e.full_name) as applicant_name,
                        l.loan_amount, l.remaining_balance
                        FROM loan_payments lp
                        JOIN loans l ON lp.loan_id = l.id
                        LEFT JOIN members m ON l.member_id = m.id
                        LEFT JOIN external_applicants e ON l.external_applicant_id = e.id
                        WHERE l.group_id = ? AND lp.verification_status = 'pending'
                        ORDER BY lp.due_date ASC";
$pending_payments_stmt = $conn->prepare($pending_payments_sql);
$pending_payments_stmt->execute([$group_id]);
$pending_payments = $pending_payments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - समूह प्लेटफॉर्म</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; margin: 2px; }
        .btn-verify { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h2>
            <span class="hindi">✅ भुगतान सत्यापन</span>
            <span class="english">✅ Payment Verification</span>
        </h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['message'] ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Due Date</th>
                        <th>Due Amount</th>
                        <th>Paid Amount</th>
                        <th>Payment Date</th>
                        <th>Method</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_payments as $payment): ?>
                    <tr>
                        <td><?= htmlspecialchars($payment['applicant_name']) ?></td>
                        <td><?= date('d M, Y', strtotime($payment['due_date'])) ?></td>
                        <td>₹<?= number_format($payment['due_amount']) ?></td>
                        <td>₹<?= number_format($payment['paid_amount']) ?></td>
                        <td><?= date('d M, Y', strtotime($payment['paid_date'])) ?></td>
                        <td><?= ucfirst($payment['payment_method']) ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                <input type="hidden" name="action" value="verify">
                                <button type="submit" class="btn btn-verify">Verify</button>
                            </form>
                            <button class="btn btn-reject" onclick="rejectPayment(<?= $payment['id'] ?>)">Reject</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reject Form (hidden) -->
    <form id="rejectForm" method="POST" style="display: none;">
        <input type="hidden" name="payment_id" id="reject_payment_id">
        <input type="hidden" name="action" value="reject">
        <textarea name="reject_reason" id="reject_reason" required></textarea>
    </form>

    <script>
        function rejectPayment(paymentId) {
            const reason = prompt("Please enter rejection reason:");
            if (reason) {
                document.getElementById('reject_payment_id').value = paymentId;
                document.getElementById('reject_reason').value = reason;
                document.getElementById('rejectForm').submit();
            }
        }
    </script>
</body>
</html>