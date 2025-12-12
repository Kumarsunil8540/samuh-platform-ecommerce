<?php
session_start();
include 'config.php';

// ✅ ACCESS CONTROL - Admin, Leader, Member sab ko allow
if (!isset($_SESSION['login']) || !in_array($_SESSION['role'], ['admin', 'leader', 'member'])) {
    header("location: core_member_login.php");
    exit;
}

$message = "";

// ✅ SMART BACK URL DETERMINATION
$back_url = match($_SESSION['role']) {
    'admin' => "admin_dashboard.php",
    'leader' => "leader_dashboard.php", 
    'member' => "member_dashboard.php",
    default => "index.php"
};

// ✅ AUTO-SET USER DATA
$groupId = $_SESSION['group_id'];
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loanAmount = floatval($_POST['loan_amount']);
    $tenure = intval($_POST['tenure_months']);
    $purpose = trim($_POST['purpose']);

    try {
        $conn->beginTransaction();

        // ✅ MEMBER CASE - Automatic data
        if ($userRole == 'member') {
            $memberId = $userId;
            $externalApplicantId = null;
            $applicantType = 'member';
            
            $stmt = $conn->prepare("SELECT full_name, email FROM members WHERE id = ?");
            $stmt->execute([$memberId]);
            $applicant = $stmt->fetch(PDO::FETCH_ASSOC);
            $applicantName = $applicant['full_name'] ?? 'Member';
            
        } else {
            // ✅ ADMIN/LEADER CASE - Manual selection
            $applicantType = $_POST['applicant_type'];
            
            if ($applicantType == 'member') {
                $memberId = intval($_POST['member_id']);
                $externalApplicantId = null;
                
                // Verify member belongs to same group
                $stmt = $conn->prepare("SELECT full_name FROM members WHERE id = ? AND group_id = ?");
                $stmt->execute([$memberId, $groupId]);
                $applicant = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$applicant) {
                    throw new Exception("Selected member not found in your group.");
                }
                $applicantName = $applicant['full_name'];
                
            } else {
                $memberId = null;
                $externalApplicantId = null;
                $applicantName = trim($_POST['full_name']);
                
                // Validate external applicant data
                $mobile = $_POST['mobile'];
                $aadhaar = $_POST['aadhaar_no'];
                $address = trim($_POST['address']);
                
                if (empty($mobile) || empty($aadhaar) || empty($address)) {
                    throw new Exception("Please fill all required fields for external applicant.");
                }
                
                // Insert external applicant
                $stmt = $conn->prepare("
                    INSERT INTO external_applicants (full_name, mobile, email, aadhaar_no, address, pan_number, monthly_income, occupation)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $applicantName,
                    $mobile,
                    $_POST['email'] ?? null,
                    $aadhaar,
                    $address,
                    $_POST['pan_number'] ?? null,
                    $_POST['monthly_income'] ?? null,
                    $_POST['occupation'] ?? null
                ]);
                $externalApplicantId = $conn->lastInsertId();
            }
        }

        // ✅ INSERT LOAN APPLICATION
        $stmt = $conn->prepare("
            INSERT INTO loans (group_id, applicant_type, member_id, external_applicant_id, 
                              loan_amount, purpose, tenure_months, applied_date, remaining_balance, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'pending')
        ");
        
        $stmt->execute([
            $groupId,
            $applicantType,
            $memberId,
            $externalApplicantId,
            $loanAmount,
            $purpose,
            $tenure,
            $loanAmount
        ]);

        $conn->commit();
        
        $message = "
            <div class='alert alert-success'>
                ✅ <span class='hindi'>लोन आवेदन सफलतापूर्वक जमा हो गया!</span>
                <span class='english'>Loan application submitted successfully!</span>
                <br>
                <small class='hindi'>आवेदक: $applicantName | राशि: ₹" . number_format($loanAmount) . "</small>
                <small class='english'>Applicant: $applicantName | Amount: ₹" . number_format($loanAmount) . "</small>
            </div>
        ";

    } catch (Exception $e) {
        $conn->rollBack();
        $message = "
            <div class='alert alert-error'>
                ❌ <span class='hindi'>त्रुटि: " . $e->getMessage() . "</span>
                <span class='english'>Error: " . $e->getMessage() . "</span>
            </div>
        ";
    }
}

// ✅ FETCH DATA FOR FORM
if ($userRole == 'member') {
    // Member ke liye sirf apna data
    $stmt = $conn->prepare("SELECT id, full_name, mobile FROM members WHERE id = ?");
    $stmt->execute([$userId]);
    $currentMember = $stmt->fetch(PDO::FETCH_ASSOC);
    $members = [$currentMember];
    
} else {
    // Admin/Leader ke liye group ke sab members
    $stmt = $conn->prepare("SELECT id, full_name, mobile FROM members WHERE group_id = ? AND is_active = 1");
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>लोन आवेदन - Loan Application</title>
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
            max-width: 800px; 
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
            flex: 2;
        }
        
        .btn-submit:hover {
            background: linear-gradient(135deg, #1e5bb9, #164a9c);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(43, 123, 228, 0.3);
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .form-group { margin-bottom: 20px; }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #2d3748;
            font-size: 14px;
        }
        
        input, textarea, select { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e2e8f0; 
            border-radius: 8px; 
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        input:focus, textarea:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43, 123, 228, 0.1);
            outline: none;
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
        
        .member-notice { 
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 25px; 
            border-left: 5px solid var(--success);
        }
        
        .external-fields { 
            border-left: 4px solid var(--warning); 
            padding: 20px;
            border-radius: 8px;
            background: #fffbeb;
            margin-top: 10px;
        }
        
        .loan-details-section {
            background: var(--light-bg);
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e6f0ff;
        }
        
        .hindi { font-weight: 500; }
        .english { 
            color: var(--gray); 
            font-size: 0.9em;
            margin-top: 3px;
        }
        
        hr {
            border: none;
            height: 2px;
            background: linear-gradient(90deg, #e2e8f0, var(--primary), #e2e8f0);
            margin: 25px 0;
        }
        
        @media (max-width: 768px) {
            .button-group { flex-direction: column; }
            .header-section { flex-direction: column; text-align: center; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
    <script>
        function toggleApplicantFields() {
            const type = document.getElementById('applicant_type').value;
            document.getElementById('member_fields').style.display = type === 'member' ? 'block' : 'none';
            document.getElementById('external_fields').style.display = type === 'external' ? 'block' : 'none';
        }
        
        function goBack() {
            window.location.href = "<?= $back_url ?>";
        }

        function validateForm() {
            const loanAmount = document.getElementById('loan_amount').value;
            const purpose = document.getElementById('purpose').value;
            
            if (loanAmount < 1000) {
                alert('कृपया कम से कम ₹1000 का लोन राशि दर्ज करें।\nPlease enter at least ₹1000 loan amount.');
                return false;
            }
            
            if (purpose.length < 10) {
                alert('कृपया लोन के उद्देश्य का विस्तार से वर्णन करें।\nPlease describe loan purpose in detail.');
                return false;
            }
            
            return true;
        }

        window.onload = function() {
            <?php if ($userRole == 'member'): ?>
            document.getElementById('member_name_display').innerHTML = 
                "<strong><?= htmlspecialchars($currentMember['full_name'] ?? '') ?></strong> - " +
                "<?= htmlspecialchars($currentMember['mobile'] ?? '') ?>";
            <?php else: ?>
            toggleApplicantFields();
            <?php endif; ?>
        }
    </script>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header-section">
            <h2>
                <span class="hindi">📝 लोन आवेदन फॉर्म</span>
                <span class="english">📝 Loan Application Form</span>
            </h2>
            <button class="btn btn-back" onclick="goBack()">
                ← <span class="hindi">वापस जाएं</span>
                <span class="english">Back</span>
            </button>
        </div>
        
        <!-- Member Notice -->
        <?php if ($userRole == 'member'): ?>
        <div class="member-notice">
            <h4 style="color: #065f46; margin-bottom: 10px;">👤 आपके नाम से आवेदन</h4>
            <p id="member_name_display" style="font-size: 16px; margin-bottom: 8px;"></p>
            <small style="color: #047857;">आप केवल अपने लिए ही लोन आवेदन कर सकते हैं।</small>
        </div>
        <?php endif; ?>
        
        <!-- Message Display -->
        <?= $message ?>

        <!-- Main Form -->
        <form method="POST" onsubmit="return validateForm()">
            <!-- Applicant Type Selection (Admin/Leader Only) -->
            <?php if ($userRole != 'member'): ?>
            <div class="form-group">
                <label>
                    <span class="hindi">आवेदक प्रकार</span>
                    <span class="english">Applicant Type</span>
                </label>
                <select id="applicant_type" name="applicant_type" onchange="toggleApplicantFields()" required>
                    <option value="member">Group Member (समूह सदस्य)</option>
                    <option value="external">External Applicant (बाहरी आवेदक)</option>
                </select>
            </div>

            <!-- Member Selection -->
            <div id="member_fields" class="form-group">
                <label>
                    <span class="hindi">सदस्य चुनें</span>
                    <span class="english">Select Member</span>
                </label>
                <select id="member_id" name="member_id">
                    <?php foreach ($members as $member): ?>
                        <option value="<?= $member['id'] ?>">
                            <?= htmlspecialchars($member['full_name']) ?> - <?= htmlspecialchars($member['mobile']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- External Applicant Fields -->
            <div id="external_fields" class="external-fields" style="display: none;">
                <h4 style="color: #d97706; margin-bottom: 15px;">
                    <span class="hindi">बाहरी आवेदक विवरण</span>
                    <span class="english">External Applicant Details</span>
                </h4>
                
                <div class="form-group">
                    <label>पूरा नाम / Full Name *</label>
                    <input type="text" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label>मोबाइल / Mobile *</label>
                    <input type="text" name="mobile" maxlength="10" required>
                </div>
                
                <div class="form-group">
                    <label>ईमेल / Email</label>
                    <input type="email" name="email">
                </div>
                
                <div class="form-group">
                    <label>आधार नंबर / Aadhaar No. *</label>
                    <input type="text" name="aadhaar_no" maxlength="12" required>
                </div>
                
                <div class="form-group">
                    <label>पता / Address *</label>
                    <textarea name="address" rows="3" required></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>पैन नंबर / PAN Number</label>
                        <input type="text" name="pan_number" maxlength="10">
                    </div>
                    
                    <div class="form-group">
                        <label>मासिक आय / Monthly Income (₹)</label>
                        <input type="number" name="monthly_income" step="0.01">
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Hidden Fields for Member -->
            <input type="hidden" name="applicant_type" value="member">
            <input type="hidden" name="member_id" value="<?= $userId ?>">
            <?php endif; ?>

            <hr>
            
            <!-- Loan Details Section -->
            <div class="loan-details-section">
                <h3 style="color: var(--primary); margin-bottom: 20px;">
                    <span class="hindi">लोन विवरण</span>
                    <span class="english">Loan Details</span>
                </h3>
                
                <div class="form-group">
                    <label for="loan_amount">
                        <span class="hindi">लोन राशि (₹) *</span>
                        <span class="english">Loan Amount (₹) *</span>
                    </label>
                    <input type="number" id="loan_amount" name="loan_amount" 
                           min="1000" step="1000" placeholder="50000" required>
                </div>
                
                <div class="form-group">
                    <label for="tenure_months">
                        <span class="hindi">अवधि (महीने) *</span>
                        <span class="english">Tenure (Months) *</span>
                    </label>
                    <select id="tenure_months" name="tenure_months" required>
                        <option value="6">6 Months</option>
                        <option value="12" selected>12 Months</option>
                        <option value="24">24 Months</option>
                        <option value="36">36 Months</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="purpose">
                        <span class="hindi">लोन का उद्देश्य *</span>
                        <span class="english">Loan Purpose *</span>
                    </label>
                    <textarea id="purpose" name="purpose" rows="3" 
                              placeholder="व्यवसाय, शिक्षा, चिकित्सा, गृह नवीनीकरण... / Business, Education, Medical, Home Renovation..." 
                              required></textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="button-group">
                <a href="<?= $back_url ?>" class="btn btn-back" style="flex: 1;">
                    ← <span class="hindi">वापस जाएं</span>
                    <span class="english">Back</span>
                </a>
                <button type="submit" class="btn btn-submit">
                    <span class="hindi">📄 लोन आवेदन जमा करें</span>
                    <span class="english">Submit Loan Application</span>
                </button>
            </div>
        </form>
    </div>
</body>
</html>