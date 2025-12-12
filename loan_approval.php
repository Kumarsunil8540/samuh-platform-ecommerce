<?php
session_start();
include 'config.php'; // ✅ Tumhara config.php use karo

// ✅ Access Control - Only Accountant can access
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'accountant') {
    header("location: core_member_login.php?role=accountant");
    exit;
}

$message = "";
$accountant_id = $_SESSION['user_id'];
$group_id = $_SESSION['group_id'];

// ✅ Determine back URL
$back_url = "accountant_dashboard.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $loanId = $_POST['loan_id'];
    $action = $_POST['action'];

    try {
        // Fetch applicant email and name for notification
        $stmt = $conn->prepare("
            SELECT l.loan_amount, l.applicant_type, l.group_id,
                   m.full_name AS m_name, m.email AS m_email, 
                   e.full_name AS e_name, e.email AS e_email 
            FROM loans l
            LEFT JOIN members m ON l.member_id = m.id
            LEFT JOIN external_applicants e ON l.external_applicant_id = e.id
            WHERE l.id = ? AND l.group_id = ?
        ");
        $stmt->execute([$loanId, $group_id]);
        $loanDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$loanDetails) {
            throw new Exception("Loan not found or you don't have permission.");
        }

        $applicantName = ($loanDetails['applicant_type'] == 'member') ? $loanDetails['m_name'] : $loanDetails['e_name'];
        $applicantEmail = ($loanDetails['applicant_type'] == 'member') ? $loanDetails['m_email'] : $loanDetails['e_email'];

        if ($action == 'approve') {
            $approvedAmount = $_POST['approved_amount'];
            $interestRate = $_POST['interest_rate'];
            $tenure = $_POST['tenure_months_approved'];
            $disbursementDate = date('Y-m-d');

            // ✅ Calculate EMI and total payable
            $monthly_interest = $interestRate / 12 / 100;
            $emi = $approvedAmount * $monthly_interest * pow(1 + $monthly_interest, $tenure) / 
                   (pow(1 + $monthly_interest, $tenure) - 1);
            $total_payable = $emi * $tenure;

            $conn->beginTransaction();

            // 1. Update loan with approval details - REMOVED next_due_date
            $stmt = $conn->prepare("
                UPDATE loans SET 
                    status = 'approved',
                    approved_date = CURDATE(), 
                    approved_by = ?, 
                    interest_rate = ?, 
                    loan_amount = ?, 
                    tenure_months = ?,
                    original_monthly_installment = ?,
                    total_payable = ?,
                    remaining_balance = ?
                WHERE id = ? AND group_id = ?
            ");
            $stmt->execute([
                $accountant_id, 
                $interestRate, 
                $approvedAmount, 
                $tenure,
                round($emi, 2),
                round($total_payable, 2),
                round($total_payable, 2), // Initial remaining balance = total payable
                $loanId, 
                $group_id
            ]);

            // 2. Create payment schedule
            $due_date = date('Y-m-d', strtotime('+1 month'));
            for ($i = 1; $i <= $tenure; $i++) {
                $payment_sql = "INSERT INTO loan_payments (loan_id, installment_number, due_date, due_amount) 
                               VALUES (?, ?, ?, ?)";
                $payment_stmt = $conn->prepare($payment_sql);
                $payment_stmt->execute([$loanId, $i, $due_date, round($emi, 2)]);
                $due_date = date('Y-m-d', strtotime($due_date . ' +1 month'));
            }

            $conn->commit();
            $message = "<div class='alert alert-success'>✅ Loan ID $loanId approved successfully. Installment schedule created.</div>";
            
            // ✅ Email Notification: Approval 
            if (!empty($applicantEmail) && function_exists('sendNotificationEmail')) {
                $emailSubject = "Loan Approved! (₹" . number_format($approvedAmount, 2) . ")";
                $emailBody = "
                    <p>नमस्ते $applicantName,</p>
                    <p>आपका लोन आवेदन ₹" . number_format($approvedAmount, 2) . " पर <strong>स्वीकृत</strong> हो गया है।</p>
                    <p>Your loan application has been <strong>APPROVED</strong> for ₹" . number_format($approvedAmount, 2) . ".</p>
                    <p><strong>ब्याज दर:</strong> $interestRate% | <strong>कार्यकाल:</strong> $tenure महीने</p>
                    <p><strong>Interest Rate:</strong> $interestRate% | <strong>Tenure:</strong> $tenure months</p>
                    <p><strong>मासिक किस्त:</strong> ₹" . number_format(round($emi, 2)) . "</p>
                    <p><strong>Monthly Installment:</strong> ₹" . number_format(round($emi, 2)) . "</p>
                    <p>आपकी पहली किश्त जल्द ही देय होगी। विवरण के लिए कृपया अपने डैशबोर्ड पर देखें।</p>
                ";
                sendNotificationEmail($applicantEmail, $applicantName, $emailSubject, $emailBody);
            }

        } elseif ($action == 'reject') {
            $reason = $_POST['rejected_reason'];
            
            $stmt = $conn->prepare("
                UPDATE loans SET 
                    status = 'rejected', 
                    rejected_reason = ?, 
                    approved_by = ? 
                WHERE id = ? AND group_id = ?
            ");
            $stmt->execute([$reason, $accountant_id, $loanId, $group_id]);
            
            $message = "<div class='alert alert-warning'>⚠️ Loan ID $loanId rejected.</div>";

            // ✅ Email Notification: Rejection
            if (!empty($applicantEmail) && function_exists('sendNotificationEmail')) {
                $emailSubject = "Loan Application Rejected";
                $emailBody = "
                    <p>नमस्ते $applicantName,</p>
                    <p>हमें आपको यह बताते हुए खेद है कि आपका लोन आवेदन <strong>अस्वीकृत</strong> कर दिया गया है।</p>
                    <p>We regret to inform you that your loan application has been <strong>REJECTED</strong>.</p>
                    <p><strong>कारण (Reason):</strong> " . htmlspecialchars($reason) . "</p>
                    <p>किसी भी प्रश्न के लिए कृपया हमसे संपर्क करें।</p>
                ";
                sendNotificationEmail($applicantEmail, $applicantName, $emailSubject, $emailBody);
            }
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $message = "<div class='alert alert-error'>❌ Error: " . $e->getMessage() . "</div>";
    }
}

// ✅ Fetch pending loans with document details
$pendingLoans = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            l.id, 
            l.loan_amount AS requested_amount, 
            l.tenure_months, 
            l.purpose, 
            l.applied_date, 
            l.applicant_type,
            COALESCE(m.full_name, e.full_name) AS applicant_name,
            COALESCE(m.mobile, e.mobile) AS applicant_mobile,
            COALESCE(m.email, e.email) AS applicant_email,
            COALESCE(m.aadhaar_no, e.aadhaar_no) AS aadhaar_no,
            COALESCE(m.pan_number, e.pan_number) AS pan_number,
            COALESCE(m.address, e.address) AS address,
            -- Member documents
            m.aadhaar_proof_path AS m_aadhaar_proof,
            m.pan_proof_path AS m_pan_proof,
            m.address_proof_path AS m_address_proof,
            m.photo_path AS m_photo,
            m.signature_path AS m_signature,
            -- External applicant documents (if any)
            '' AS e_aadhaar_proof,
            '' AS e_pan_proof,
            '' AS e_address_proof,
            '' AS e_photo,
            '' AS e_signature
        FROM loans l
        LEFT JOIN members m ON l.member_id = m.id
        LEFT JOIN external_applicants e ON l.external_applicant_id = e.id
        WHERE l.status = 'pending' AND l.group_id = ?
        ORDER BY l.applied_date ASC
    ");
    $stmt->execute([$group_id]);
    $pendingLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "<div class='alert alert-error'>❌ Database Error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Approval - समूह प्लेटफॉर्म</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 1200px; 
            margin: 20px auto; 
            background: #fff; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #f0f4f8;
            padding-bottom: 15px;
        }
        h2 { 
            color: #2b7be4; 
            font-size: 28px;
            margin: 0;
        }
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        .loan-card { 
            background: #f8fbff; 
            border: 2px solid #e6f0ff; 
            padding: 20px; 
            border-radius: 12px; 
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .loan-card h4 { 
            color: #2b7be4; 
            margin-bottom: 15px;
            border-bottom: 2px solid #e6f0ff;
            padding-bottom: 10px;
        }
        .loan-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #2b7be4;
        }
        .info-item strong {
            color: #2d3748;
            display: block;
            margin-bottom: 5px;
        }
        .documents-section {
            background: #fff9ed;
            border: 2px solid #fed7aa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .documents-section h5 {
            color: #ea580c;
            margin-bottom: 15px;
            border-bottom: 2px solid #fed7aa;
            padding-bottom: 8px;
        }
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .document-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .document-item strong {
            color: #2d3748;
            display: block;
            margin-bottom: 8px;
        }
        .document-preview {
            margin-top: 8px;
        }
        .document-preview a {
            display: inline-block;
            background: #2b7be4;
            color: white;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .document-preview a:hover {
            background: #1e5bb9;
            transform: translateY(-2px);
        }
        .no-document {
            color: #6b7280;
            font-style: italic;
        }
        .action-forms {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .approve-form, .reject-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid;
        }
        .approve-form {
            border-color: #28a745;
            background: linear-gradient(135deg, #f8fff9, #f0fff4);
        }
        .reject-form {
            border-color: #dc3545;
            background: linear-gradient(135deg, #fff8f8, #fff0f0);
        }
        .form-group { 
            margin-bottom: 15px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #2d3748;
            font-size: 14px;
        }
        input[type="number"], textarea, select { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e2e8f0; 
            border-radius: 8px; 
            box-sizing: border-box;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #2b7be4;
            box-shadow: 0 0 0 3px rgba(43, 123, 228, 0.1);
            outline: none;
        }
        .btn-approve { 
            background: linear-gradient(135deg, #28a745, #218838);
            color: white; 
            padding: 12px 25px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-approve:hover { 
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-reject { 
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white; 
            padding: 12px 25px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-reject:hover { 
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
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
        .alert-warning { 
            background: #fff7ed; 
            color: #9a3412;
            border: 2px solid #fdba74;
        }
        .alert-error { 
            background: #fee2e2; 
            color: #dc2626;
            border: 2px solid #fca5a5;
        }
        .no-loans {
            text-align: center;
            padding: 40px;
            color: #6b7280;
            background: #f9fafb;
            border-radius: 10px;
            border: 2px dashed #d1d5db;
        }
        .hindi { font-weight: 500; }
        .english { 
            color: #6b7280; 
            font-size: 0.9em;
            margin-top: 3px;
        }
        @media (max-width: 768px) {
            .action-forms {
                grid-template-columns: 1fr;
            }
            .loan-info {
                grid-template-columns: 1fr;
            }
            .documents-grid {
                grid-template-columns: 1fr;
            }
            .header-section {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
    <script>
        function goBack() {
            window.location.href = "<?= $back_url ?>";
        }

        // Calculate EMI on the fly
        function calculateEMI() {
            const amount = parseFloat(document.getElementById('approved_amount').value) || 0;
            const rate = parseFloat(document.getElementById('interest_rate').value) || 0;
            const tenure = parseInt(document.getElementById('tenure_months_approved').value) || 0;
            
            if (amount > 0 && rate > 0 && tenure > 0) {
                const monthlyRate = rate / 12 / 100;
                const emi = amount * monthlyRate * Math.pow(1 + monthlyRate, tenure) / 
                           (Math.pow(1 + monthlyRate, tenure) - 1);
                
                document.getElementById('emi_preview').textContent = '₹' + emi.toFixed(2);
                document.getElementById('emi_preview_en').textContent = '₹' + emi.toFixed(2);
                document.getElementById('total_payable').textContent = '₹' + (emi * tenure).toFixed(2);
                document.getElementById('total_payable_en').textContent = '₹' + (emi * tenure).toFixed(2);
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <!-- Header with Back Button -->
        <div class="header-section">
            <h2>
                <span class="hindi">✅ लोन स्वीकृति</span><br>
                <span class="english">✅ Loan Approval</span>
            </h2>
            <button class="back-btn" onclick="goBack()">
                ← <span class="hindi">वापस जाएं</span>
                <span class="english">Back to Dashboard</span>
            </button>
        </div>
        
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <?php if (empty($pendingLoans)): ?>
            <div class="no-loans">
                <h3 style="color: #6b7280; margin-bottom: 10px;">🎉 कोई लंबित लोन नहीं</h3>
                <p class="hindi">अभी तक कोई लोन आवेदन लंबित नहीं है।</p>
                <p class="english">No pending loan applications found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingLoans as $loan): ?>
                <div class="loan-card">
                    <h4>
                        <span class="hindi">लोन आईडी: #<?= htmlspecialchars($loan['id']) ?></span>
                        <span class="english">Loan ID: #<?= htmlspecialchars($loan['id']) ?></span>
                    </h4>
                    
                    <div class="loan-info">
                        <div class="info-item">
                            <strong class="hindi">आवेदक</strong>
                            <strong class="english">Applicant</strong>
                            <div><?= htmlspecialchars($loan['applicant_name']) ?></div>
                            <small><?= htmlspecialchars($loan['applicant_mobile']) ?></small>
                            <small><?= htmlspecialchars($loan['applicant_email']) ?></small>
                        </div>
                        <div class="info-item">
                            <strong class="hindi">अनुरोधित राशि</strong>
                            <strong class="english">Requested Amount</strong>
                            <div>₹<?= number_format($loan['requested_amount']) ?></div>
                        </div>
                        <div class="info-item">
                            <strong class="hindi">अवधि</strong>
                            <strong class="english">Tenure</strong>
                            <div><?= htmlspecialchars($loan['tenure_months']) ?> महीने / months</div>
                        </div>
                        <div class="info-item">
                            <strong class="hindi">आवेदन तिथि</strong>
                            <strong class="english">Applied Date</strong>
                            <div><?= date('d M, Y', strtotime($loan['applied_date'])) ?></div>
                        </div>
                    </div>

                    <div class="info-item">
                        <strong class="hindi">लोन का उद्देश्य</strong>
                        <strong class="english">Loan Purpose</strong>
                        <div><?= htmlspecialchars($loan['purpose']) ?></div>
                    </div>

                    <!-- Documents Section -->
                    <div class="documents-section">
                        <h5>
                            <span class="hindi">📄 आवेदक दस्तावेज</span>
                            <span class="english">📄 Applicant Documents</span>
                        </h5>
                        
                        <div class="documents-grid">
                            <div class="document-item">
                                <strong class="hindi">आधार नंबर</strong>
                                <strong class="english">Aadhaar Number</strong>
                                <div><?= htmlspecialchars($loan['aadhaar_no'] ?? 'N/A') ?></div>
                                
                                <strong class="hindi" style="margin-top: 10px;">आधार प्रमाण</strong>
                                <strong class="english">Aadhaar Proof</strong>
                                <div class="document-preview">
                                    <?php if (!empty($loan['m_aadhaar_proof'])): ?>
                                        <a href="<?= htmlspecialchars($loan['m_aadhaar_proof']) ?>" target="_blank" class="hindi">📄 देखें</a>
                                        <a href="<?= htmlspecialchars($loan['m_aadhaar_proof']) ?>" target="_blank" class="english">📄 View</a>
                                    <?php else: ?>
                                        <span class="no-document hindi">उपलब्ध नहीं</span>
                                        <span class="no-document english">Not Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="document-item">
                                <strong class="hindi">पैन नंबर</strong>
                                <strong class="english">PAN Number</strong>
                                <div><?= htmlspecialchars($loan['pan_number'] ?? 'N/A') ?></div>
                                
                                <strong class="hindi" style="margin-top: 10px;">पैन प्रमाण</strong>
                                <strong class="english">PAN Proof</strong>
                                <div class="document-preview">
                                    <?php if (!empty($loan['m_pan_proof'])): ?>
                                        <a href="<?= htmlspecialchars($loan['m_pan_proof']) ?>" target="_blank" class="hindi">📄 देखें</a>
                                        <a href="<?= htmlspecialchars($loan['m_pan_proof']) ?>" target="_blank" class="english">📄 View</a>
                                    <?php else: ?>
                                        <span class="no-document hindi">उपलब्ध नहीं</span>
                                        <span class="no-document english">Not Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="document-item">
                                <strong class="hindi">पता प्रमाण</strong>
                                <strong class="english">Address Proof</strong>
                                <div class="document-preview">
                                    <?php if (!empty($loan['m_address_proof'])): ?>
                                        <a href="<?= htmlspecialchars($loan['m_address_proof']) ?>" target="_blank" class="hindi">📄 देखें</a>
                                        <a href="<?= htmlspecialchars($loan['m_address_proof']) ?>" target="_blank" class="english">📄 View</a>
                                    <?php else: ?>
                                        <span class="no-document hindi">उपलब्ध नहीं</span>
                                        <span class="no-document english">Not Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="document-item">
                                <strong class="hindi">फोटो</strong>
                                <strong class="english">Photo</strong>
                                <div class="document-preview">
                                    <?php if (!empty($loan['m_photo'])): ?>
                                        <a href="<?= htmlspecialchars($loan['m_photo']) ?>" target="_blank" class="hindi">🖼️ देखें</a>
                                        <a href="<?= htmlspecialchars($loan['m_photo']) ?>" target="_blank" class="english">🖼️ View</a>
                                    <?php else: ?>
                                        <span class="no-document hindi">उपलब्ध नहीं</span>
                                        <span class="no-document english">Not Available</span>
                                    <?php endif; ?>
                                </div>
                                
                                <strong class="hindi" style="margin-top: 10px;">हस्ताक्षर</strong>
                                <strong class="english">Signature</strong>
                                <div class="document-preview">
                                    <?php if (!empty($loan['m_signature'])): ?>
                                        <a href="<?= htmlspecialchars($loan['m_signature']) ?>" target="_blank" class="hindi">✍️ देखें</a>
                                        <a href="<?= htmlspecialchars($loan['m_signature']) ?>" target="_blank" class="english">✍️ View</a>
                                    <?php else: ?>
                                        <span class="no-document hindi">उपलब्ध नहीं</span>
                                        <span class="no-document english">Not Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item" style="margin-top: 15px;">
                            <strong class="hindi">पता</strong>
                            <strong class="english">Address</strong>
                            <div><?= nl2br(htmlspecialchars($loan['address'] ?? 'N/A')) ?></div>
                        </div>
                    </div>

                    <div class="action-forms">
                        <!-- Approval Form -->
                        <div class="approve-form">
                            <h4 style="color: #28a745; margin-bottom: 15px;">
                                <span class="hindi">✅ लोन स्वीकारें</span>
                                <span class="english">✅ Approve Loan</span>
                            </h4>
                            <form method="POST">
                                <input type="hidden" name="loan_id" value="<?= htmlspecialchars($loan['id']) ?>">
                                <input type="hidden" name="action" value="approve">
                                
                                <div class="form-group">
                                    <label>
                                        <span class="hindi">स्वीकृत राशि (₹)</span>
                                        <span class="english">Approved Amount (₹)</span>
                                    </label>
                                    <input type="number" name="approved_amount" id="approved_amount" 
                                           value="<?= htmlspecialchars($loan['requested_amount']) ?>" 
                                           step="1000" min="1000" required oninput="calculateEMI()">
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <span class="hindi">ब्याज दर (% वार्षिक)</span>
                                        <span class="english">Interest Rate (% Annual)</span>
                                    </label>
                                    <input type="number" name="interest_rate" id="interest_rate" 
                                           value="12.00" step="0.01" min="1" max="24" required oninput="calculateEMI()">
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <span class="hindi">स्वीकृत अवधि (महीने)</span>
                                        <span class="english">Approved Tenure (Months)</span>
                                    </label>
                                    <input type="number" name="tenure_months_approved" id="tenure_months_approved" 
                                           value="<?= htmlspecialchars($loan['tenure_months']) ?>" 
                                           min="6" max="36" required oninput="calculateEMI()">
                                </div>

                                <div class="form-group" style="background: #e8f5e8; padding: 10px; border-radius: 6px;">
                                    <strong class="hindi">पूर्वावलोकन:</strong>
                                    <strong class="english">Preview:</strong><br>
                                    <small class="hindi">मासिक किस्त: <span id="emi_preview">₹0.00</span></small><br>
                                    <small class="english">Monthly EMI: <span id="emi_preview_en">₹0.00</span></small><br>
                                    <small class="hindi">कुल देय: <span id="total_payable">₹0.00</span></small><br>
                                    <small class="english">Total Payable: <span id="total_payable_en">₹0.00</span></small>
                                </div>
                                
                                <button type="submit" class="btn-approve">
                                    <span class="hindi">✅ लोन स्वीकारें</span>
                                    <span class="english">✅ APPROVE LOAN</span>
                                </button>
                            </form>
                        </div>

                        <!-- Rejection Form -->
                        <div class="reject-form">
                            <h4 style="color: #dc3545; margin-bottom: 15px;">
                                <span class="hindi">❌ लोन अस्वीकारें</span>
                                <span class="english">❌ Reject Loan</span>
                            </h4>
                            <form method="POST">
                                <input type="hidden" name="loan_id" value="<?= htmlspecialchars($loan['id']) ?>">
                                <input type="hidden" name="action" value="reject">
                                
                                <div class="form-group">
                                    <label>
                                        <span class="hindi">अस्वीकरण कारण</span>
                                        <span class="english">Rejection Reason</span>
                                    </label>
                                    <textarea name="rejected_reason" rows="4" 
                                              placeholder="कारण दर्ज करें... / Enter reason..." required></textarea>
                                </div>
                                
                                <button type="submit" class="btn-reject">
                                    <span class="hindi">❌ लोन अस्वीकारें</span>
                                    <span class="english">❌ REJECT LOAN</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Initialize EMI calculation on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateEMI();
        });
    </script>
</body>
</html>