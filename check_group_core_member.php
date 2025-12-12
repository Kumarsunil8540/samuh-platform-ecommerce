<?php
session_start();

// ✅ Step 1: URL से रोल (Role/Table Name) लेना
if (isset($_GET['role'])) {
    $role_name = strtolower(trim($_GET['role']));
    $allowed_roles = ['leader', 'admin', 'accountant'];
    
    if (!in_array($role_name, $allowed_roles)) {
        die("❌ Invalid role access!");
    }
} else {
    die("❌ रोल/पद का नाम URL में अनुपलब्ध है!");
}

// Role display names
$role_display_names = [
    'leader' => ['hindi' => 'लीडर', 'english' => 'Leader'],
    'admin' => ['hindi' => 'एडमिन', 'english' => 'Admin'], 
    'accountant' => ['hindi' => 'अकाउंटेंट', 'english' => 'Accountant']
];

$current_role = $role_display_names[$role_name];
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>कोर मेंबर सत्यापन - Core Member Verification</title>
    <link rel="stylesheet" href="check_group_core_member.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h1 class="hindi">कोर मेंबर सत्यापन</h1>
            <h1 class="english">Core Member Verification</h1>
            <div class="role-badge">
                <span class="hindi">पद: <?php echo $current_role['hindi']; ?></span>
                <span class="english">Role: <?php echo $current_role['english']; ?></span>
            </div>
        </div>

        <form action="check_group_core_member_process.php" method="post" class="verify-form">
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role_name); ?>">
            
            <div class="input-group">
                <label for="group_id" class="hindi">ग्रुप आईडी *</label>
                <label for="group_id" class="english">Group ID *</label>
                <input type="number" id="group_id" name="group_id" placeholder="Group ID (e.g., 123)" required>
            </div>

            <div class="input-group">
                <label for="username" class="hindi">पूरा नाम *</label>
                <label for="username" class="english">Full Name *</label>
                <input type="text" id="username" name="username" placeholder="जैसा ग्रुप में दर्ज है" required>
                <small class="hint hindi">वही नाम जो ग्रुप रजिस्ट्रेशन में दिया था</small>
                <small class="hint english">Same name as in group registration</small>
            </div>

            <div class="input-group">
                <label for="password" class="hindi">मोबाइल नंबर *</label>
                <label for="password" class="english">Mobile Number *</label>
                <input type="password" id="password" name="password" placeholder="10 अंकों का मोबाइल नंबर" 
                       pattern="[0-9]{10}" maxlength="10" required>
                <small class="hint hindi">ग्रुप रजिस्ट्रेशन में दिया गया मोबाइल नंबर</small>
                <small class="hint english">Mobile number used in group registration</small>
            </div>

            <button type="submit" class="btn-verify">
                <span class="hindi">सत्यापित करें</span>
                <span class="english">Verify Identity</span>
            </button>
        </form>

        <div class="form-footer">
            <p class="hindi">✅ सत्यापन सफल होने पर, आप अपने दस्तावेज़ अपलोड कर सकेंगे</p>
            <p class="english">✅ After verification, you can upload your documents</p>
            
            <a href="index.php" class="btn-home">
                <span class="hindi">होम पेज पर वापस जाएं</span>
                <span class="english">Go back to Home Page</span>
            </a>
        </div>
    </div>

    <script>
        // Real-time validation
        document.addEventListener('DOMContentLoaded', function() {
            const mobileInput = document.getElementById('password');
            
            mobileInput.addEventListener('input', function() {
                // Allow only numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Validate length
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
            });

            // Form submission enhancement
            const form = document.querySelector('.verify-form');
            form.addEventListener('submit', function(e) {
                const mobile = document.getElementById('password').value;
                if (mobile.length !== 10) {
                    e.preventDefault();
                    alert('कृपया 10 अंकों का मोबाइल नंबर दर्ज करें / Please enter exactly 10 digit mobile number');
                    document.getElementById('password').focus();
                }
            });
        });
    </script>
</body>
</html>