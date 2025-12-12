<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Check if user is verified
if(!isset($_SESSION['user_verified']) || !$_SESSION['user_verified']){
    header('location: index.php');
    exit;
}

// Role display names
$role_display_names = [
    'leader' => ['hindi' => 'लीडर', 'english' => 'Leader'],
    'admin' => ['hindi' => 'एडमिन', 'english' => 'Admin'], 
    'accountant' => ['hindi' => 'अकाउंटेंट', 'english' => 'Accountant']
];

$current_role = isset($_SESSION['role']) ? $role_display_names[$_SESSION['role']] : ['hindi' => '', 'english' => ''];
$group_name = isset($_SESSION['group_name']) ? $_SESSION['group_name'] : '';
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>कोर मेंबर साइनअप - Core Member Signup</title>
<link rel="stylesheet" href="upload_data_core.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<?php include("header.php"); ?>

<div class="signup-container">
    <div class="signup-card">
        <div class="form-header">
            <h1 class="hindi">कोर मेंबर साइनअप</h1>
            <h1 class="english">Core Member Signup</h1>
            <div class="user-info">
                <div class="info-item">
                    <span class="hindi"><strong>ग्रुप:</strong> <?php echo htmlspecialchars($group_name); ?></span>
                    <span class="english"><strong>Group:</strong> <?php echo htmlspecialchars($group_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="hindi"><strong>नाम:</strong> <?php echo htmlspecialchars($user_name); ?></span>
                    <span class="english"><strong>Name:</strong> <?php echo htmlspecialchars($user_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="hindi"><strong>पद:</strong> <?php echo $current_role['hindi']; ?></span>
                    <span class="english"><strong>Role:</strong> <?php echo $current_role['english']; ?></span>
                </div>
            </div>
        </div>

        <p class="lead hindi">कृपया अपनी पूरी जानकारी और KYC दस्तावेज़ भरें</p>
        <p class="lead english">Please fill your complete information and KYC documents</p>

        <form action="upload_data_core_process.php" method="POST" enctype="multipart/form-data" novalidate id="kycForm">
            
            <!-- Login Information Section -->
            <fieldset class="form-section">
                <legend class="section-legend">
                    <span class="hindi">1. लॉगिन जानकारी</span>
                    <span class="english">1. Login Information</span>
                </legend>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username" class="hindi">यूज़रनेम *</label>
                        <label for="username" class="english">Username *</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="अपना यूनीक यूज़रनेम डालें">
                        <div class="error-message" id="username_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="hindi">पासवर्ड *</label>
                        <label for="password" class="english">Password *</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="मज़बूत पासवर्ड सेट करें" minlength="6">
                        <div class="error-message" id="password_error"></div>
                    </div>
                </div>
            </fieldset>

            <!-- Personal Information Section -->
            <fieldset class="form-section">
                <legend class="section-legend">
                    <span class="hindi">2. व्यक्तिगत जानकारी</span>
                    <span class="english">2. Personal Information</span>
                </legend>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name" class="hindi">पूरा नाम *</label>
                        <label for="full_name" class="english">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo htmlspecialchars($user_name); ?>">
                        <div class="error-message" id="full_name_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="mobile" class="hindi">मोबाइल नंबर *</label>
                        <label for="mobile" class="english">Mobile Number *</label>
                        <input type="tel" id="mobile" name="mobile" required 
                               pattern="[0-9]{10}" maxlength="10" placeholder="10 अंकों का मोबाइल नंबर">
                        <div class="error-message" id="mobile_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="hindi">ईमेल *</label>
                        <label for="email" class="english">Email *</label>
                        <input type="email" id="email" name="email" required 
                               placeholder="your@email.com">
                        <div class="error-message" id="email_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="dob" class="hindi">जन्मतिथि *</label>
                        <label for="dob" class="english">Date of Birth *</label>
                        <input type="date" id="dob" name="dob" required>
                        <div class="error-message" id="dob_error"></div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="address" class="hindi">वर्तमान पता *</label>
                    <label for="address" class="english">Full Address *</label>
                    <textarea id="address" name="address" rows="3" required 
                              placeholder="पूरा पता लिखें"></textarea>
                    <div class="error-message" id="address_error"></div>
                </div>
            </fieldset>

            <!-- KYC Documents Section -->
            <fieldset class="form-section">
                <legend class="section-legend">
                    <span class="hindi">3. KYC दस्तावेज़ अपलोड</span>
                    <span class="english">3. KYC Document Upload</span>
                </legend>
                
                <div class="section-note">
                    <p class="hindi">📝 सभी स्कैन साफ़ और पढ़ने योग्य होने चाहिए</p>
                    <p class="english">📝 All scans should be clear and readable</p>
                </div>

                <div class="form-group">
                    <label for="pan_number" class="hindi">पैन नंबर *</label>
                    <label for="pan_number" class="english">PAN Number *</label>
                    <input type="text" id="pan_number" name="pan_number" required 
                           maxlength="10" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" 
                           placeholder="Example: ABCDE1234F">
                    <div class="error-message" id="pan_number_error"></div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="pan_proof" class="hindi">पैन कार्ड स्कैन *</label>
                        <label for="pan_proof" class="english">PAN Card Scan *</label>
                        <input type="file" id="pan_proof" name="pan_proof" 
                               accept=".pdf,.jpg,.png,.jpeg" required>
                        <small class="file-hint hindi">PDF, JPG, PNG (Max: 5MB)</small>
                        <small class="file-hint english">PDF, JPG, PNG (Max: 5MB)</small>
                        <div class="error-message" id="pan_proof_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="aadhaar_proof" class="hindi">आधार कार्ड स्कैन *</label>
                        <label for="aadhaar_proof" class="english">Aadhaar Card Scan *</label>
                        <input type="file" id="aadhaar_proof" name="aadhaar_proof" 
                               accept=".pdf,.jpg,.png,.jpeg" required>
                        <small class="file-hint hindi">PDF, JPG, PNG (Max: 5MB)</small>
                        <small class="file-hint english">PDF, JPG, PNG (Max: 5MB)</small>
                        <div class="error-message" id="aadhaar_proof_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="bank_proof" class="hindi">बैंक प्रूफ *</label>
                        <label for="bank_proof" class="english">Bank Proof *</label>
                        <input type="file" id="bank_proof" name="bank_proof" 
                               accept=".pdf,.jpg,.png,.jpeg" required>
                        <small class="file-hint hindi">पासबुक/चेकबुक का पहला पेज</small>
                        <small class="file-hint english">First page of passbook/chequebook</small>
                        <div class="error-message" id="bank_proof_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="signature_proof" class="hindi">हस्ताक्षर स्कैन *</label>
                        <label for="signature_proof" class="english">Signature Scan *</label>
                        <input type="file" id="signature_proof" name="signature_proof" 
                               accept=".jpg,.png,.jpeg" required>
                        <small class="file-hint hindi">JPG, PNG (Max: 2MB)</small>
                        <small class="file-hint english">JPG, PNG (Max: 2MB)</small>
                        <div class="error-message" id="signature_proof_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="profile_photo" class="hindi">प्रोफ़ाइल फ़ोटो *</label>
                        <label for="profile_photo" class="english">Profile Photo *</label>
                        <input type="file" id="profile_photo" name="profile_photo" 
                               accept=".jpg,.png,.jpeg" required>
                        <small class="file-hint hindi">लाइव सेल्फी (JPG, PNG - Max: 2MB)</small>
                        <small class="file-hint english">Live Selfie (JPG, PNG - Max: 2MB)</small>
                        <div class="error-message" id="profile_photo_error"></div>
                    </div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn btn-submit">
                    <span class="hindi">साइनअप और दस्तावेज़ जमा करें</span>
                    <span class="english">Signup & Submit Documents</span>
                </button>
                
                <p class="form-note hindi">
                    ✅ सभी जानकारी सुरक्षित रूप से सहेजी जाएगी
                </p>
                <p class="form-note english">
                    ✅ All information will be saved securely
                </p>
            </div>
        </form>
    </div>
</div>

<?php include("footer.php"); ?>

<script src="upload_data_core.js"></script>
</body>
</html>