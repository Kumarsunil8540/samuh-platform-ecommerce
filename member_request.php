<?php
// ===============================================
// ⚠️ IMPORTANT: Config File Check
// ===============================================
// सुनिश्चित करें कि config.php में $conn PDO कनेक्शन ऑब्जेक्ट सही ढंग से शामिल है।
// Include config.php (assuming it contains the $conn PDO connection object)
if (!file_exists("config.php")) {
    die("❌ Error: config.php not found. Database connection required.");
}
include("config.php");

// ===============================================
// 🎯 AJAX Request Handler (Group Details Fetch)
// ===============================================
if (isset($_GET['action']) && $_GET['action'] === 'fetch_group_details' && isset($_GET['group_id'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message_hi' => 'समूह नहीं मिला।', 'message_en' => 'Group not found.', 'rules' => '', 'stamp_path' => ''];

    $group_id = trim($_GET['group_id']);

    if (!is_numeric($group_id) || $group_id <= 0) {
        $response['message_hi'] = 'अवैध ग्रुप आईडी।';
        $response['message_en'] = 'Invalid Group ID.';
        echo json_encode($response);
        exit;
    }

    try {
        // Prepare and execute the statement
        $sql = "SELECT group_conditions, stamp_upload_path FROM groups WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$group_id]);
        $group_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($group_data) {
            $response['success'] = true;
            $response['message_hi'] = 'समूह की जानकारी सफलतापूर्वक लोड हो गई है।';
            $response['message_en'] = 'Group details loaded successfully.';
            // Sanitize and format rules
            $response['rules'] = nl2br(htmlspecialchars($group_data['group_conditions']));
            $response['stamp_path'] = htmlspecialchars($group_data['stamp_upload_path']);
        }

    } catch (PDOException $e) {
        $response['message_hi'] = 'डेटाबेस त्रुटि।';
        $response['message_en'] = 'Database Error: ' . $e->getMessage(); // Show error only for debugging
        error_log("DB Error in fetch_group_details: " . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}


// ===============================================
// 📥 Form Submission Handler (POST)
// ===============================================
$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {

    // --- 1. Sanitize and Validate Inputs ---
    $group_id            = filter_var(trim($_POST['group_id']), FILTER_VALIDATE_INT);
    $full_name           = filter_var(trim($_POST['full_name']), FILTER_SANITIZE_STRING);
    $dob                 = trim($_POST['dob']);
    $gender              = trim($_POST['gender']);
    $mobile              = filter_var(trim($_POST['mobile']), FILTER_SANITIZE_STRING);
    $email               = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $bank_account        = filter_var(trim($_POST['bank_account']), FILTER_SANITIZE_STRING);
    $ifsc_code           = filter_var(trim($_POST['ifsc_code']), FILTER_SANITIZE_STRING);
    $bank_name_branch    = filter_var(trim($_POST['bank_name_branch']), FILTER_SANITIZE_STRING);
    $address             = filter_var(trim($_POST['address']), FILTER_SANITIZE_STRING);
    $nominee             = filter_var(trim($_POST['nominee']), FILTER_SANITIZE_STRING);

    // Checkboxes and Rules Verification
    $agree_terms = isset($_POST['agree_terms']);
    $trust_documents = isset($_POST['trust_documents']);
    $rules_verified = isset($_POST['rules_verified']) && $_POST['rules_verified'] === 'true';

    // Basic Validation Check
    if (!$group_id || !$full_name || !$dob || !$gender || !$mobile || !$email || !$bank_account || !$ifsc_code || !$bank_name_branch || !$address || !$nominee) {
         $error_message = "❌ कृपया सभी अनिवार्य फ़ील्ड भरें और मान्य जानकारी दर्ज करें!<br>❌ Please fill all mandatory fields and enter valid information!";
    } elseif (!$agree_terms || !$trust_documents || !$rules_verified) {
         $error_message = "❌ कृपया सभी अनिवार्य बॉक्स चेक करें और समूह नियम सत्यापित करें!<br>❌ Please check all mandatory boxes and verify group rules!";
    } else {
        // --- 2. File Upload Logic ---
        $upload_dir = "uploads/member_kyc/";
        if (!is_dir($upload_dir)) {
            // Attempt to create the directory
            if (!mkdir($upload_dir, 0777, true)) {
                $error_message = "❌ फ़ाइल अपलोड फ़ोल्डर बनाने में त्रुटि! सर्वर अनुमति जांचें।";
                goto end_submission;
            }
        }

        function upload_file($file_input_name, $upload_dir) {
            $file = $_FILES[$file_input_name] ?? null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                // Basic File Validation
                $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
                $max_size = 2 * 1024 * 1024; // 2MB

                if ($file['size'] > $max_size) {
                    return ['error' => 'फाइल ' . $file_input_name . ' 2MB से बड़ी है।'];
                }
                
                // For photo/signature only allow images, for Aadhaar/PAN allow PDF too
                if ($file_input_name == 'photo' || $file_input_name == 'signature') {
                     if (!in_array($file['type'], ['image/jpeg', 'image/png'])) {
                         return ['error' => $file_input_name . ' केवल JPG/PNG होनी चाहिए।'];
                     }
                } elseif (!in_array($file['type'], $allowed_types)) {
                     return ['error' => $file_input_name . ' JPG/PNG/PDF होनी चाहिए।'];
                }

                // Create unique file name
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $file_name = time() . "_" . uniqid() . "." . $file_extension;
                $target_path = $upload_dir . $file_name;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    return ['path' => $target_path];
                } else {
                    return ['error' => 'फाइल को ' . $upload_dir . ' में ले जाने में विफल।'];
                }
            } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                return ['error' => 'अपलोड त्रुटि: कोड ' . $file['error']];
            }
            return ['error' => 'फाइल ' . $file_input_name . ' अनिवार्य है।'];
        }

        $aadhaar_result   = upload_file("aadhaar", $upload_dir);
        $pan_result       = upload_file("pan", $upload_dir);
        $photo_result     = upload_file("photo", $upload_dir);
        $signature_result = upload_file("signature", $upload_dir);

        // Check for upload errors
        if (isset($aadhaar_result['error'])) { $error_message = "❌ आधार: " . $aadhaar_result['error']; }
        elseif (isset($pan_result['error'])) { $error_message = "❌ पैन: " . $pan_result['error']; }
        elseif (isset($photo_result['error'])) { $error_message = "❌ फोटो: " . $photo_result['error']; }
        elseif (isset($signature_result['error'])) { $error_message = "❌ हस्ताक्षर: " . $signature_result['error']; }

        // If no file errors, proceed to DB insert
        if (empty($error_message)) {
            $aadhaar_path   = $aadhaar_result['path'];
            $pan_path       = $pan_result['path'];
            $photo_path     = $photo_result['path'];
            $signature_path = $signature_result['path'];

             // --- 3. Database Insert ---
            try {
                 $sql = "INSERT INTO members
                         (group_id, full_name, dob, gender, mobile, email,
                          bank_account_masked, bank_ifsc, bank_name_branch, address, nominee_name,
                          aadhaar_proof_path, pan_proof_path, photo_path, signature_path,
                          member_status, kyc_status, submitted_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'submitted', NOW())";

                 $stmt = $conn->prepare($sql);
                 $success = $stmt->execute([
                     $group_id, $full_name, $dob, $gender, $mobile, $email, $bank_account, $ifsc_code,
                     $bank_name_branch, $address, $nominee, $aadhaar_path, $pan_path, $photo_path, $signature_path
                 ]);

                 if ($success) {
                     $success_message = "✅ आपका डेटा और KYC सफलतापूर्वक जमा हो गया है! प्रशासक की स्वीकृति की प्रतीक्षा करें।<br>✅ Your data and KYC has been submitted successfully! Waiting for admin approval.";
                 } else {
                     $error_message = "❌ डेटा सेव करने में त्रुटि हुई! कृपया पुनः प्रयास करें।<br>❌ Error saving data! Please try again.";
                 }
            } catch (PDOException $e) {
                $error_message = "❌ डेटाबेस इंसर्ट त्रुटि: " . $e->getMessage();
                error_log("DB Insert Error: " . $e->getMessage());
            }
        }
    }
}
end_submission:
// HTML code continues below
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Membership Request / समूह सदस्यता अनुरोध</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="member_request.css">
</head>
<body>

<div class="form-container">
    <?php if(isset($success_message) && $success_message): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
            <div class="action-buttons">
                <a href="index.php" class="btn btn-primary">Go Back / वापस जाएं</a>
                <a href="member_request.php" class="btn btn-secondary">New Request / नया अनुरोध</a>
            </div>
        </div>
    <?php elseif(isset($error_message) && $error_message): ?>
        <div class="alert alert-error">
            <?php echo $error_message; ?>
            <div class="action-buttons">
                <a href="member_request.php" class="btn btn-primary">Try Again / पुनः प्रयास करें</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="header-section">
        <h1 class="lang-hi">समूह सदस्यता अनुरोध</h1>
        <h1 class="lang-en" style="display:none;">Group Membership Request</h1>
        <p class="lang-hi subheading">नया सदस्य बनने के लिए नीचे दिए गए फॉर्म को भरें</p>
        <p class="lang-en subheading" style="display:none;">Fill the form below to become a new member</p>
    </div>
    
    <form action="" method="POST" enctype="multipart/form-data" id="membershipForm">
        <input type="hidden" name="rules_verified" id="rules_verified" value="<?php echo isset($_POST['rules_verified']) ? htmlspecialchars($_POST['rules_verified']) : 'false'; ?>">

        <section class="form-section">
            <h2 class="lang-hi">समूह की जानकारी</h2>
            <h2 class="lang-en" style="display:none;">Group Information</h2>
            
            <div class="input-group">
                <label class="lang-hi">समूह आईडी *</label>
                <label class="lang-en" style="display:none;">Group ID *</label>
                <input type="text" name="group_id" id="group_id" placeholder="Group ID" required class="modern-input" 
                        value="<?php echo isset($_POST['group_id']) ? htmlspecialchars($_POST['group_id']) : ''; ?>">
                <small class="lang-hi">आपका समूह आईडी दर्ज करें</small>
                <small class="lang-en" style="display:none;">Enter your group ID</small>
            </div>

            <div id="groupRulesContainer" class="group-rules-container" style="display:none;">
                <div class="rules-header">
                    <h4 class="lang-hi">📜 समूह के नियम और शर्तें:</h4>
                    <h4 class="lang-en" style="display:none;">📜 Group Rules & Conditions:</h4>
                </div>
                <div id="rulesStatusMessage" class="rules-status-message" style="display:none;"></div>

                <div id="groupRulesContent" class="rules-content">
                    </div>

                <div id="stampViewContainer" class="stamp-view" style="display:none;">
                    <h4 class="lang-hi">📝 स्टैंप पेपर/कानूनी दस्तावेज़:</h4>
                    <h4 class="lang-en" style="display:none;">📝 Stamp Paper/Legal Document:</h4>
                    <a id="stampLink" href="#" target="_blank" class="btn-stamp">
                        <span class="lang-hi">स्टैंप पेपर देखें</span>
                        <span class="lang-en" style="display:none;">View Stamp Paper</span>
                    </a>
                </div>
                
                <div class="checkbox-group" style="margin-top: 15px;">
                    <input type="checkbox" id="rules_read_confirm" class="agreement-checkbox">
                    <label for="rules_read_confirm" class="agreement-label">
                        <span class="lang-hi">
                            ✅ <strong>मैंने उपरोक्त समूह के नियम और स्टैंप पेपर पढ़ लिया है और स्वीकार करता हूँ।</strong>
                        </span>
                        <span class="lang-en" style="display:none;">
                            ✅ <strong>I have read and accept the above group rules and stamp paper.</strong>
                        </span>
                    </label>
                </div>
            </div>
        </section>

        <section class="form-section">
            <h2 class="lang-hi">व्यक्तिगत विवरण</h2>
            <h2 class="lang-en" style="display:none;">Personal Details</h2>
            
            <div class="form-row">
                <div class="input-group">
                    <label class="lang-hi">पूरा नाम *</label>
                    <label class="lang-en" style="display:none;">Full Name *</label>
                    <input type="text" name="full_name" required class="modern-input"
                                value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                </div>
                
                <div class="input-group">
                    <label class="lang-hi">जन्म तिथि *</label>
                    <label class="lang-en" style="display:none;">Date of Birth *</label>
                    <input type="date" name="dob" required class="modern-input"
                                value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="lang-hi">लिंग *</label>
                    <label class="lang-en" style="display:none;">Gender *</label>
                    <select name="gender" required class="modern-input">
                        <option value="" class="lang-hi">लिंग चुनें</option>
                        <option value="" class="lang-en" style="display:none;">Select Gender</option>
                        <option value="male" class="lang-hi" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>पुरुष</option>
                        <option value="male" class="lang-en" style="display:none;" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" class="lang-hi" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>महिला</option>
                        <option value="female" class="lang-en" style="display:none;" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" class="lang-hi" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'selected' : ''; ?>>अन्य</option>
                        <option value="other" class="lang-en" style="display:none;" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="input-group">
                    <label class="lang-hi">मोबाइल नंबर *</label>
                    <label class="lang-en" style="display:none;">Mobile Number *</label>
                    <input type="text" name="mobile" required class="modern-input" pattern="[0-9]{10}"
                                value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>">
                    <small class="lang-hi">10 अंकों का मोबाइल नंबर</small>
                    <small class="lang-en" style="display:none;">10 digit mobile number</small>
                </div>
            </div>

            <div class="input-group">
                <label class="lang-hi">ईमेल आईडी *</label>
                <label class="lang-en" style="display:none;">Email ID *</label>
                <input type="email" name="email" required class="modern-input"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
        </section>

        <section class="form-section">
            <h2 class="lang-hi">वित्तीय विवरण</h2>
            <h2 class="lang-en" style="display:none;">Financial Details</h2>
            
            <div class="input-group">
                <label class="lang-hi">आधार कार्ड (प्रूफ) *</label>
                <label class="lang-en" style="display:none;">Aadhaar Card (Proof) *</label>
                <input type="file" name="aadhaar" accept=".jpg,.png,.pdf" required class="file-input">
                <small class="lang-hi">JPG, PNG या PDF (अधिकतम 2MB)</small>
                <small class="lang-en" style="display:none;">JPG, PNG or PDF (Max 2MB)</small>
            </div>

            <div class="input-group">
                <label class="lang-hi">पैन कार्ड (प्रूफ) *</label>
                <label class="lang-en" style="display:none;">PAN Card (Proof) *</label>
                <input type="file" name="pan" accept=".jpg,.png,.pdf" required class="file-input">
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="lang-hi">बैंक खाता संख्या *</label>
                    <label class="lang-en" style="display:none;">Bank Account Number *</label>
                    <input type="text" name="bank_account" required class="modern-input"
                                value="<?php echo isset($_POST['bank_account']) ? htmlspecialchars($_POST['bank_account']) : ''; ?>">
                </div>
                
                <div class="input-group">
                    <label class="lang-hi">IFSC कोड *</label>
                    <label class="lang-en" style="display:none;">IFSC Code *</label>
                    <input type="text" name="ifsc_code" required class="modern-input"
                                value="<?php echo isset($_POST['ifsc_code']) ? htmlspecialchars($_POST['ifsc_code']) : ''; ?>">
                </div>
            </div>

            <div class="input-group">
                <label class="lang-hi">बैंक का नाम और शाखा *</label>
                <label class="lang-en" style="display:none;">Bank Name & Branch *</label>
                <input type="text" name="bank_name_branch" required class="modern-input"
                            value="<?php echo isset($_POST['bank_name_branch']) ? htmlspecialchars($_POST['bank_name_branch']) : ''; ?>">
            </div>
        </section>

        <section class="form-section">
            <h2 class="lang-hi">अतिरिक्त विवरण</h2>
            <h2 class="lang-en" style="display:none;">Additional Details</h2>
            
            <div class="input-group">
                <label class="lang-hi">स्थायी पता *</label>
                <label class="lang-en" style="display:none;">Permanent Address *</label>
                <textarea name="address" required class="modern-input" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>

            <div class="input-group">
                <label class="lang-hi">नामांकित व्यक्ति का नाम *</label>
                <label class="lang-en" style="display:none;">Nominee Name *</label>
                <input type="text" name="nominee" required class="modern-input"
                            value="<?php echo isset($_POST['nominee']) ? htmlspecialchars($_POST['nominee']) : ''; ?>">
                <small class="lang-hi">धनराशि प्राप्त करने वाले व्यक्ति का नाम</small>
                <small class="lang-en" style="display:none;">Name of person to receive funds</small>
            </div>
        </section>

        <section class="form-section">
            <h2 class="lang-hi">दस्तावेज़ अपलोड</h2>
            <h2 class="lang-en" style="display:none;">Document Upload</h2>
            
            <div class="form-row">
                <div class="input-group">
                    <label class="lang-hi">फोटोग्राफ *</label>
                    <label class="lang-en" style="display:none;">Photograph *</label>
                    <input type="file" name="photo" accept=".jpg,.png" required class="file-input">
                    <small class="lang-hi">पासपोर्ट साइज फोटो</small>
                    <small class="lang-en" style="display:none;">Passport size photo</small>
                </div>
                
                <div class="input-group">
                    <label class="lang-hi">हस्ताक्षर *</label>
                    <label class="lang-en" style="display:none;">Signature *</label>
                    <input type="file" name="signature" accept=".jpg,.png" required class="file-input">
                </div>
            </div>
        </section>

        <section class="form-section agreement-section">
            <h2 class="lang-hi">सहमति</h2>
            <h2 class="lang-en" style="display:none;">Agreement</h2>
            
            <div class="checkbox-group">
                <input type="checkbox" id="trust_documents" name="trust_documents" required class="agreement-checkbox" 
                       <?php echo (isset($_POST['trust_documents']) ? 'checked' : ''); ?>>
                <label for="trust_documents" class="agreement-label">
                    <span class="lang-hi">
                        ✅ <strong>मैं सहमत हूं कि इस वेबसाइट द्वारा मेरे डेटा का दुरुपयोग नहीं किया जाएगा।</strong>
                    </span>
                    <span class="lang-en" style="display:none;">
                        ✅ <strong>I agree that this website will not misuse my data.</strong>
                    </span>
                </label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="agree_terms" name="agree_terms" required class="agreement-checkbox"
                       <?php echo (isset($_POST['agree_terms']) ? 'checked' : ''); ?>>
                <label for="agree_terms" class="agreement-label">
                    <span class="lang-hi">
                        ✅ <strong>मैंने समूह के नियम और शर्तें पढ़ ली हैं और उनसे सहमत हूं। (Rules and conditions are checked above)</strong>
                    </span>
                    <span class="lang-en" style="display:none;">
                        ✅ <strong>I have read and agree to the group's rules and conditions. (Rules and conditions are checked above)</strong>
                    </span>
                </label>
            </div>

            <div class="submit-btn-container">
                <button type="submit" name="submit_request" class="submit-btn" id="submitBtn" disabled>
                    <span class="lang-hi">अनुरोध सबमिट करें</span>
                    <span class="lang-en" style="display:none;">Submit Request</span>
                </button>
                <small class="lang-hi submit-note">* अनुरोध सबमिट करने के बाद प्रशासक की स्वीकृति की प्रतीक्षा करें</small>
                <small class="lang-en submit-note" style="display:none;">* After submission, wait for admin approval</small>
            </div>
        </section>
    </form>
</div>

<script src="member_request.js"></script>
</body>
</html>