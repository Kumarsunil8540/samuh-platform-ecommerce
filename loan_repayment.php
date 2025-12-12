<?php
session_start();
include 'config.php';

// ✅ ACCESS CONTROL - Member, Admin, Leader sab ko allow
if (!isset($_SESSION['login']) || !in_array($_SESSION['role'], ['member', 'admin', 'leader'])) {
    header("location: core_member_login.php");
    exit;
}

$message = "";
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$groupId = $_SESSION['group_id'];

// ✅ SMART BACK URL
$back_url = match($_SESSION['role']) {
    'admin' => "admin_dashboard.php",
    'leader' => "leader_dashboard.php", 
    'member' => "member_dashboard.php",
    default => "index.php"
};

// ✅ PAYMENT SUBMISSION LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['loan_id'])) {
    $loanId = intval($_POST['loan_id']);
    $installmentNumber = intval($_POST['installment_number']);
    $paidAmount = floatval($_POST['paid_amount']);
    $paymentMethod = $_POST['payment_method'];
    $paidDate = date('Y-m-d');

    try {
        $conn->beginTransaction();

        // ✅ VERIFY LOAN ACCESS PERMISSION
        $verifyStmt = $conn->prepare("
            SELECT l.id, l.member_id, l.applicant_type, 
                   COALESCE(m.full_name, e.full_name) as applicant_name
            FROM loans l
            LEFT JOIN members m ON l.member_id = m.id
            LEFT JOIN external_applicants e ON l.external_applicant_id = e.id
            WHERE l.id = ? AND l.group_id = ? AND l.status = 'approved'
        ");
        $verifyStmt->execute([$loanId, $groupId]);
        $loan = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        if (!$loan) {
            throw new Exception("Loan not found or you don't have permission.");
        }

        // ✅ FOR MEMBER: VERIFY THEY OWN THE LOAN
        if ($userRole == 'member' && $loan['member_id'] != $userId) {
            throw new Exception("You can only make payments for your own loans.");
        }

        // ✅ FETCH INSTALLMENT DETAILS
        $stmt = $conn->prepare("
            SELECT * FROM loan_payments 
            WHERE loan_id = ? AND installment_number = ? 
            AND payment_status IN ('pending', 'partially_paid', 'overdue')
        ");
        $stmt->execute([$loanId, $installmentNumber]);
        $installment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$installment) {
            throw new Exception("Installment not found or already paid.");
        }

        // ✅ IMPROVED PAYMENT AMOUNT VALIDATION
        $remainingDue = $installment['due_amount'] - $installment['paid_amount'];
        
        if ($paidAmount <= 0) {
            throw new Exception("Payment amount must be greater than zero.");
        }
        
        // Handle small remaining amounts intelligently
        if ($paidAmount > $remainingDue) {
            $difference = $paidAmount - $remainingDue;
            
            // If difference is very small (less than ₹5), auto-adjust to exact amount
            if ($difference <= 5.00) {
                $paidAmount = $remainingDue; // Auto-adjust to exact remaining amount
            } else {
                throw new Exception("Payment amount (₹" . number_format($paidAmount, 2) . ") cannot exceed remaining due amount (₹" . number_format($remainingDue, 2) . ").");
            }
        }

        // ✅ UPDATE PAYMENT RECORD
        $newPaidAmount = $installment['paid_amount'] + $paidAmount;
        $paymentStatus = ($newPaidAmount >= $installment['due_amount']) ? 'paid' : 'partially_paid';

        $updateStmt = $conn->prepare("
            UPDATE loan_payments 
            SET paid_amount = ?, paid_date = ?, payment_method = ?, 
                payment_status = ?, verification_status = 'pending'
            WHERE id = ?
        ");
        $updateStmt->execute([$newPaidAmount, $paidDate, $paymentMethod, $paymentStatus, $installment['id']]);

        // ✅ UPDATE LOAN BALANCE
        $principalPaid = $paidAmount * 0.7;
        $interestPaid = $paidAmount * 0.3;

        $balanceStmt = $conn->prepare("
            UPDATE loans 
            SET total_paid = total_paid + ?, 
                remaining_balance = remaining_balance - ?
            WHERE id = ?
        ");
        $balanceStmt->execute([$paidAmount, $paidAmount, $loanId]);

        // ✅ UPDATE PRINCIPAL AND INTEREST IN PAYMENT RECORD
        $principalUpdateStmt = $conn->prepare("
            UPDATE loan_payments 
            SET principal_paid = principal_paid + ?,
                interest_paid = interest_paid + ?
            WHERE id = ?
        ");
        $principalUpdateStmt->execute([$principalPaid, $interestPaid, $installment['id']]);

        // ✅ CHECK IF LOAN IS FULLY PAID
        $checkStmt = $conn->prepare("SELECT remaining_balance FROM loans WHERE id = ?");
        $checkStmt->execute([$loanId]);
        $loanBalance = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($loanBalance['remaining_balance'] <= 0) {
            $closeStmt = $conn->prepare("UPDATE loans SET status = 'closed' WHERE id = ?");
            $closeStmt->execute([$loanId]);
            
            // Also mark all remaining installments as paid
            $markPaidStmt = $conn->prepare("
                UPDATE loan_payments 
                SET payment_status = 'paid', paid_amount = due_amount 
                WHERE loan_id = ? AND payment_status IN ('pending', 'partially_paid')
            ");
            $markPaidStmt->execute([$loanId]);
        }

        $conn->commit();

        $successMessage = "
            <div class='alert alert-success'>
                ✅ <span class='hindi'>भुगतान सफलतापूर्वक जमा हो गया!</span>
                <span class='english'>Payment submitted successfully!</span>
                <br>
                <small class='hindi'>राशि: ₹" . number_format($paidAmount, 2) . " | सत्यापन के लिए लंबित</small>
                <small class='english'>Amount: ₹" . number_format($paidAmount, 2) . " | Pending verification</small>
        ";

        // Add special message for small amounts
        if ($paidAmount == $remainingDue && $remainingDue < 10.00) {
            $successMessage .= "
                <br><small class='hindi' style='color: #059669;'>✅ छोटी बकाया राशि स्वचालित रूप से समायोजित की गई</small>
                <br><small class='english' style='color: #059669;'>✅ Small remaining amount auto-adjusted</small>
            ";
        }

        $successMessage .= "</div>";
        $message = $successMessage;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $message = "
            <div class='alert alert-error'>
                ❌ <span class='hindi'>त्रुटि: " . $e->getMessage() . "</span>
                <span class='english'>Error: " . $e->getMessage() . "</span>
            </div>
        ";
    }
}

// ✅ FETCH ACTIVE LOANS
$activeLoans = [];
try {
    if ($userRole == 'member') {
        $stmt = $conn->prepare("
            SELECT l.id, l.loan_amount, l.remaining_balance, l.original_monthly_installment as monthly_installment,
                   l.applicant_type, m.full_name as applicant_name
            FROM loans l
            JOIN members m ON l.member_id = m.id
            WHERE l.status = 'approved' AND l.member_id = ? AND l.group_id = ?
            ORDER BY l.applied_date DESC
        ");
        $stmt->execute([$userId, $groupId]);
    } else {
        $stmt = $conn->prepare("
            SELECT l.id, l.loan_amount, l.remaining_balance, l.original_monthly_installment as monthly_installment,
                   l.applicant_type, COALESCE(m.full_name, e.full_name) as applicant_name
            FROM loans l
            LEFT JOIN members m ON l.member_id = m.id
            LEFT JOIN external_applicants e ON l.external_applicant_id = e.id
            WHERE l.status = 'approved' AND l.group_id = ?
            ORDER BY l.applied_date DESC
        ");
        $stmt->execute([$groupId]);
    }
    $activeLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "
        <div class='alert alert-error'>
            ❌ <span class='hindi'>डेटाबेस त्रुटि</span>
            <span class='english'>Database Error: " . $e->getMessage() . "</span>
        </div>
    ";
}

// ✅ FETCH INSTALLMENTS FOR SELECTED LOAN
$installments = [];
$selectedLoan = null;

if (!empty($activeLoans) && isset($_GET['loan_id'])) {
    $selectedLoanId = intval($_GET['loan_id']);
    
    $loanExists = false;
    foreach ($activeLoans as $loan) {
        if ($loan['id'] == $selectedLoanId) {
            $loanExists = true;
            $selectedLoan = $loan;
            break;
        }
    }
    
    if ($loanExists) {
        $stmt = $conn->prepare("
            SELECT id, installment_number, due_date, due_amount, paid_amount, 
                   payment_status, verification_status,
                   (due_amount - paid_amount) as remaining_due
            FROM loan_payments 
            WHERE loan_id = ? AND payment_status IN ('pending', 'partially_paid', 'overdue')
            ORDER BY due_date ASC
        ");
        $stmt->execute([$selectedLoanId]);
        $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif (!empty($activeLoans)) {
    $selectedLoan = $activeLoans[0];
    $selectedLoanId = $selectedLoan['id'];
    
    $stmt = $conn->prepare("
        SELECT id, installment_number, due_date, due_amount, paid_amount, 
               payment_status, verification_status,
               (due_amount - paid_amount) as remaining_due
        FROM loan_payments 
        WHERE loan_id = ? AND payment_status IN ('pending', 'partially_paid', 'overdue')
        ORDER BY due_date ASC
    ");
    $stmt->execute([$selectedLoanId]);
    $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>लोन भुगतान - Loan Repayment</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2b7be4;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --gray: #6b7280;
            --light-bg: #f8fbff;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container { 
            max-width: 900px; 
            margin: 20px auto; 
            background: #fff; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #f0f4f8;
            padding-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        h2 { 
            color: var(--primary); 
            font-size: 28px;
            margin: 0;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-back { 
            background: var(--gray); 
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), #1e5bb9);
            color: white;
            width: 100%;
            padding: 15px;
            font-size: 16px;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            background: linear-gradient(135deg, #1e5bb9, #164a9c);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(43, 123, 228, 0.3);
        }
        
        .btn-quick {
            background: #e2e8f0;
            color: #2d3748;
            padding: 8px 15px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            margin: 2px;
            transition: all 0.2s ease;
        }
        
        .btn-quick:hover {
            background: var(--primary);
            color: white;
        }
        
        .form-group { margin-bottom: 20px; }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #2d3748;
            font-size: 14px;
        }
        
        input, select { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e2e8f0; 
            border-radius: 8px; 
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43, 123, 228, 0.1);
            outline: none;
        }
        
        .amount-input-container {
            position: relative;
        }
        
        .quick-amounts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .quick-amounts small {
            color: var(--gray);
            font-weight: 500;
            width: 100%;
            margin-bottom: 5px;
        }
        
        .alert { 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            text-align: center;
            font-weight: 500;
        }
        
        .alert-success { 
            background: #d1fae5; 
            color: #065f46;
            border: 2px solid #a7f3d0;
        }
        
        .alert-error { 
            background: #fee2e2; 
            color: #dc2626;
            border: 2px solid #fca5a5;
        }
        
        .loan-card {
            background: var(--light-bg);
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e6f0ff;
            margin-bottom: 20px;
        }
        
        .loan-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .installment-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
        }
        
        .installment-item {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .installment-item:last-child {
            border-bottom: none;
        }
        
        .payment-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e6f0ff;
            margin-top: 20px;
        }
        
        .amount-display-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .amount-box {
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid;
        }
        
        .amount-due { 
            background: #f0fff4; 
            border-color: #a7f3d0;
            color: #065f46;
        }
        
        .amount-paid { 
            background: #fff7ed; 
            border-color: #fdba74;
            color: #9a3412;
        }
        
        .amount-remaining { 
            background: #fff1f2; 
            border-color: #fca5a5;
            color: #dc2626;
        }
        
        .small-amount-notice {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
            font-size: 14px;
        }
        
        .hindi { font-weight: 500; }
        .english { 
            color: var(--gray); 
            font-size: 0.9em;
            margin-top: 3px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--gray);
            background: #f9fafb;
            border-radius: 10px;
            border: 2px dashed #d1d5db;
        }
        
        @media (max-width: 768px) {
            .header-section { flex-direction: column; text-align: center; }
            .loan-info-grid { grid-template-columns: 1fr; }
            .installment-item { flex-direction: column; align-items: flex-start; gap: 10px; }
            .amount-display-grid { grid-template-columns: 1fr; }
            .quick-amounts { justify-content: center; }
        }
    </style>
    <script>
        function goBack() {
            window.location.href = "<?= $back_url ?>";
        }

        function selectLoan(loanId) {
            window.location.href = `loan_repayment.php?loan_id=${loanId}`;
        }

        let currentRemainingDue = 0;

        function updateInstallmentDetails() {
            const installmentSelect = document.getElementById('installment_number');
            const selectedOption = installmentSelect.options[installmentSelect.selectedIndex];
            const dueAmount = parseFloat(selectedOption.getAttribute('data-due'));
            const paidAmount = parseFloat(selectedOption.getAttribute('data-paid'));
            const remainingDue = parseFloat(selectedOption.getAttribute('data-remaining'));
            
            currentRemainingDue = remainingDue;
            
            // Update display boxes
            document.getElementById('due_amount_display').textContent = '₹' + dueAmount.toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('paid_amount_display').textContent = '₹' + paidAmount.toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('remaining_due_display').textContent = '₹' + remainingDue.toLocaleString('en-IN', {minimumFractionDigits: 2});
            
            // Set max amount for payment input
            const paidAmountInput = document.getElementById('paid_amount');
            paidAmountInput.max = remainingDue;
            paidAmountInput.placeholder = 'Max: ₹' + remainingDue.toLocaleString('en-IN', {minimumFractionDigits: 2});
            paidAmountInput.value = ''; // Clear previous value
            
            // Show notice for small amounts
            showSmallAmountNotice(remainingDue);
            
            // Update quick amount buttons
            updateQuickAmounts(remainingDue);
        }

        function showSmallAmountNotice(remainingDue) {
            const noticeDiv = document.getElementById('small_amount_notice');
            
            if (remainingDue < 10.00) {
                noticeDiv.innerHTML = `
                    <div class="small-amount-notice">
                        <span class="hindi">💡 नोट: बकाया राशि केवल ₹${remainingDue.toLocaleString('en-IN', {minimumFractionDigits: 2})} है। आप पूरी बकाया राशि जमा कर सकते हैं।</span>
                        <span class="english">💡 Note: Remaining due is only ₹${remainingDue.toLocaleString('en-IN', {minimumFractionDigits: 2})}. You can pay the full remaining amount.</span>
                    </div>
                `;
                noticeDiv.style.display = 'block';
            } else {
                noticeDiv.style.display = 'none';
            }
        }

        function updateQuickAmounts(maxAmount) {
            const quickAmountsDiv = document.getElementById('quick_amounts');
            const amounts = [];
            
            // Generate quick amounts based on max amount
            if (maxAmount >= 1000) {
                amounts.push(500, 1000, 2000, 5000);
            } else if (maxAmount >= 500) {
                amounts.push(100, 200, 500, 1000);
            } else if (maxAmount >= 100) {
                amounts.push(50, 100, 200, 500);
            } else {
                // For small amounts, show smaller increments
                amounts.push(10, 25, 50, 100);
            }
            
            // Always include full amount as last option
            if (maxAmount > 0) {
                amounts.push(maxAmount);
            }
            
            let buttonsHtml = '<small class="hindi">त्वरित राशि:</small><small class="english">Quick Amount:</small><br>';
            
            amounts.forEach(amount => {
                if (amount <= maxAmount) {
                    buttonsHtml += `<button type="button" class="btn-quick" onclick="setAmount(${amount})">
                        ₹${amount.toLocaleString('en-IN', {minimumFractionDigits: 2})}
                    </button>`;
                }
            });
            
            quickAmountsDiv.innerHTML = buttonsHtml;
        }

        function setAmount(amount) {
            document.getElementById('paid_amount').value = amount;
            validateAmount();
        }

        function validateAmount() {
            const paidAmountInput = document.getElementById('paid_amount');
            const amount = parseFloat(paidAmountInput.value) || 0;
            const maxAmount = currentRemainingDue;
            
            if (amount > maxAmount) {
                paidAmountInput.style.borderColor = 'var(--danger)';
                paidAmountInput.style.backgroundColor = '#fef2f2';
            } else if (amount > 0) {
                paidAmountInput.style.borderColor = 'var(--success)';
                paidAmountInput.style.backgroundColor = '#f0fdf4';
            } else {
                paidAmountInput.style.borderColor = '#e2e8f0';
                paidAmountInput.style.backgroundColor = '#fff';
            }
        }

        function validatePayment() {
            const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
            const remainingDue = currentRemainingDue;
            
            if (paidAmount <= 0) {
                alert('कृपया वैध भुगतान राशि दर्ज करें।\nPlease enter a valid payment amount.');
                return false;
            }
            
            // Handle small overpayments intelligently
            if (paidAmount > remainingDue) {
                const difference = paidAmount - remainingDue;
                
                // If difference is small (≤ ₹5), suggest exact amount
                if (difference <= 5.00) {
                    return confirm(`बकाया राशि केवल ₹${remainingDue.toLocaleString('en-IN', {minimumFractionDigits: 2})} है। क्या आप पूरी बकाया राशि जमा करना चाहते हैं?\n\nRemaining due is only ₹${remainingDue.toLocaleString('en-IN', {minimumFractionDigits: 2})}. Do you want to pay the full remaining amount?`);
                } else {
                    alert(`भुगतान राशि (₹${paidAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}) बकाया राशि (₹${remainingDue.toLocaleString('en-IN', {minimumFractionDigits: 2})}) से अधिक है।\n\nPayment amount (₹${paidAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}) cannot exceed remaining due amount (₹${remainingDue.toLocaleString('en-IN', {minimumFractionDigits: 2})}).`);
                    return false;
                }
            }
            
            return confirm(`क्या आप ₹${paidAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})} का भुगतान जमा करना चाहते हैं?\nDo you want to submit payment of ₹${paidAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}?`);
        }

        // Auto-format amount input
        function formatAmount(input) {
            // Remove non-numeric characters except decimal point
            let value = input.value.replace(/[^\d.]/g, '');
            
            // Ensure only one decimal point
            const decimalCount = (value.match(/\./g) || []).length;
            if (decimalCount > 1) {
                value = value.substring(0, value.lastIndexOf('.'));
            }
            
            // Limit to 2 decimal places
            if (value.includes('.')) {
                const parts = value.split('.');
                if (parts[1].length > 2) {
                    value = parts[0] + '.' + parts[1].substring(0, 2);
                }
            }
            
            input.value = value;
            validateAmount();
        }

        window.onload = function() {
            updateInstallmentDetails();
        }
    </script>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header-section">
            <h2>
                <span class="hindi">💰 लोन भुगतान</span>
                <span class="english">💰 Loan Repayment</span>
            </h2>
            <button class="btn btn-back" onclick="goBack()">
                ← <span class='hindi'>वापस जाएं</span>
                <span class='english'>Back</span>
            </button>
        </div>
        
        <!-- Message Display -->
        <?= $message ?>

        <?php if (empty($activeLoans)): ?>
            <div class="no-data">
                <h3 style="color: #6b7280; margin-bottom: 10px;">📭 कोई स्वीकृत लोन नहीं</h3>
                <p class="hindi">आपके पास कोई स्वीकृत लोन नहीं है जिसके लिए भुगतान किया जा सके।</p>
                <p class="english">You don't have any approved loans for payment.</p>
            </div>
        <?php else: ?>
            <!-- Loan Selection -->
            <div class="loan-card">
                <h3 style="color: var(--primary); margin-bottom: 15px;">
                    <span class="hindi">लोन चुनें</span>
                    <span class="english">Select Loan</span>
                </h3>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php foreach ($activeLoans as $loan): ?>
                        <button type="button" 
                                onclick="selectLoan(<?= $loan['id'] ?>)" 
                                style="background: <?= $selectedLoan && $selectedLoan['id'] == $loan['id'] ? 'var(--primary)' : '#e2e8f0' ?>; 
                                       color: <?= $selectedLoan && $selectedLoan['id'] == $loan['id'] ? 'white' : '#2d3748' ?>; 
                                       padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer;">
                            #<?= $loan['id'] ?> - <?= htmlspecialchars($loan['applicant_name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($selectedLoan): ?>
            <!-- Selected Loan Details -->
            <div class="loan-card">
                <h3 style="color: var(--primary); margin-bottom: 15px;">
                    <span class="hindi">लोन विवरण</span>
                    <span class="english">Loan Details</span>
                </h3>
                
                <div class="loan-info-grid">
                    <div class="info-item">
                        <strong class="hindi">आवेदक</strong>
                        <strong class="english">Applicant</strong>
                        <div><?= htmlspecialchars($selectedLoan['applicant_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <strong class="hindi">लोन राशि</strong>
                        <strong class="english">Loan Amount</strong>
                        <div>₹<?= number_format($selectedLoan['loan_amount'], 2) ?></div>
                    </div>
                    <div class="info-item">
                        <strong class="hindi">बकाया राशि</strong>
                        <strong class="english">Remaining Balance</strong>
                        <div>₹<?= number_format($selectedLoan['remaining_balance'], 2) ?></div>
                    </div>
                    <div class="info-item">
                        <strong class="hindi">मासिक किस्त</strong>
                        <strong class="english">Monthly Installment</strong>
                        <div>₹<?= number_format($selectedLoan['monthly_installment'], 2) ?></div>
                    </div>
                </div>

                <!-- Installments List -->
                <h4 style="margin-bottom: 15px;">
                    <span class="hindi">📅 लंबित किश्तें</span>
                    <span class="english">📅 Pending Installments</span>
                </h4>
                
                <?php if (empty($installments)): ?>
                    <div class="no-data" style="padding: 20px;">
                        <span class="hindi">✅ सभी किश्तें भुगतित</span>
                        <span class="english">✅ All installments paid</span>
                    </div>
                <?php else: ?>
                    <div class="installment-list">
                        <?php foreach ($installments as $inst): ?>
                            <div class="installment-item">
                                <div>
                                    <strong>किश्त #<?= $inst['installment_number'] ?></strong>
                                    <small style="color: var(--gray); display: block;">
                                        देय तिथि: <?= date('d M, Y', strtotime($inst['due_date'])) ?>
                                    </small>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: bold; color: var(--danger);">
                                        ₹<?= number_format($inst['remaining_due'], 2) ?> बकाया
                                    </div>
                                    <small style="color: var(--gray);">
                                        कुल: ₹<?= number_format($inst['due_amount'], 2) ?> | 
                                        भुगतित: ₹<?= number_format($inst['paid_amount'], 2) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Payment Form -->
                    <div class="payment-form">
                        <h4 style="color: var(--primary); margin-bottom: 20px;">
                            <span class="hindi">💳 भुगतान जमा करें</span>
                            <span class="english">💳 Submit Payment</span>
                        </h4>
                        
                        <form method="POST" onsubmit="return validatePayment()">
                            <input type="hidden" name="loan_id" value="<?= $selectedLoan['id'] ?>">
                            
                            <div class="form-group">
                                <label>
                                    <span class="hindi">किश्त चुनें *</span>
                                    <span class="english">Select Installment *</span>
                                </label>
                                <select id="installment_number" name="installment_number" onchange="updateInstallmentDetails()" required>
                                    <?php foreach ($installments as $inst): ?>
                                        <option value="<?= $inst['installment_number'] ?>" 
                                                data-due="<?= $inst['due_amount'] ?>"
                                                data-paid="<?= $inst['paid_amount'] ?>"
                                                data-remaining="<?= $inst['remaining_due'] ?>">
                                            किश्त #<?= $inst['installment_number'] ?> - 
                                            बकाया: ₹<?= number_format($inst['remaining_due'], 2) ?> - 
                                            देय: <?= date('d M, Y', strtotime($inst['due_date'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Small Amount Notice -->
                            <div id="small_amount_notice" style="display: none;"></div>

                            <div class="amount-display-grid">
                                <div class="amount-box amount-due">
                                    <small class="hindi">कुल देय</small>
                                    <small class="english">Total Due</small>
                                    <div style="font-weight: bold; font-size: 16px;" id="due_amount_display">₹0.00</div>
                                </div>
                                <div class="amount-box amount-paid">
                                    <small class="hindi">भुगतित</small>
                                    <small class="english">Paid</small>
                                    <div style="font-weight: bold; font-size: 16px;" id="paid_amount_display">₹0.00</div>
                                </div>
                                <div class="amount-box amount-remaining">
                                    <small class="hindi">बकाया</small>
                                    <small class="english">Remaining</small>
                                    <div style="font-weight: bold; font-size: 16px;" id="remaining_due_display">₹0.00</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <span class="hindi">भुगतान राशि (₹) *</span>
                                    <span class="english">Payment Amount (₹) *</span>
                                </label>
                                <div class="amount-input-container">
                                    <input type="text" id="paid_amount" name="paid_amount" 
                                           oninput="formatAmount(this)" 
                                           required>
                                </div>
                                <div class="quick-amounts" id="quick_amounts">
                                    <!-- Quick amount buttons will be inserted here by JavaScript -->
                                </div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <span class="hindi">भुगतान विधि *</span>
                                    <span class="english">Payment Method *</span>
                                </label>
                                <select name="payment_method" required>
                                    <option value="cash">नकद / Cash</option>
                                    <option value="online">ऑनलाइन / Online Transfer</option>
                                    <option value="qr">क्यूआर / QR Code</option>
                                    <option value="cheque">चेक / Cheque</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-submit">
                                <span class="hindi">✅ भुगतान जमा करें</span>
                                <span class="english">✅ Submit Payment</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>