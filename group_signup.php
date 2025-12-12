<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>समूह पंजीकरण - Samuh Group Registration</title>
<link rel="stylesheet" href="group_signup.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<?php include("header.php"); ?>

<div class="container">
    <div class="card">
        <div class="form-header">
            <h1 class="hindi">समूह पंजीकरण (चरण 1)</h1>
            <h1 class="english">Group Registration (Step 1)</h1>
            <p class="lead hindi">कृपया ग्रुप की जानकारी और तीन मुख्य ज़िम्मेदारों (लीडर, एडमिन, अकाउंटेंट) के KYC दस्तावेज़ भरें</p>
            <p class="lead english">Please fill group information and KYC documents for three core members (Leader, Admin, Accountant)</p>
        </div>

        <form id="groupCreateForm" action="group_create_action.php" method="POST" enctype="multipart/form-data" novalidate>
            
            <!-- Group Details Section -->
            <fieldset class="form-section">
                <legend class="section-legend">
                    <span class="hindi">1. समूह विवरण</span>
                    <span class="english">1. Group Details</span>
                </legend>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="group_name" class="hindi">समूह का नाम *</label>
                        <label for="group_name" class="english">Group Name *</label>
                        <input type="text" id="group_name" name="group_name" required maxlength="100" 
                               placeholder="Group ka naam (e.g., Shanti Mahila Samuh)">
                        <div class="error-message" id="group_name_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="tenure_months" class="hindi">समूह अवधि (महीने) *</label>
                        <label for="tenure_months" class="english">Group Duration (Months) *</label>
                        <input type="number" id="tenure_months" name="tenure_months" min="6" max="60" value="12" required>
                        <div class="error-message" id="tenure_months_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="expected_amount" class="hindi">मासिक योगदान राशि *</label>
                        <label for="expected_amount" class="english">Monthly Contribution Amount *</label>
                        <input type="number" id="expected_amount" name="expected_amount" min="100" required 
                               placeholder="Rs. 500, Rs. 1000, etc.">
                        <div class="error-message" id="expected_amount_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="min_members_count" class="hindi">न्यूनतम सदस्य संख्या *</label>
                        <label for="min_members_count" class="english">Minimum Members Required *</label>
                        <input type="number" id="min_members_count" name="min_members_count" min="2" max="50" value="7" required>
                        <div class="error-message" id="min_members_count_error"></div>
                    </div>

                    <!-- NEW: Payment Cycle Field -->
                    <div class="form-group">
                        <label for="payment_cycle" class="hindi">भुगतान चक्र *</label>
                        <label for="payment_cycle" class="english">Payment Cycle *</label>
                        <select id="payment_cycle" name="payment_cycle" required>
                            <option value="">चुनें / Select</option>
                            <option value="weekly" class="hindi">साप्ताहिक (7 दिन)</option>
                            <option value="weekly" class="english">Weekly (7 days)</option>
                            <option value="monthly" class="hindi" selected>मासिक (30 दिन)</option>
                            <option value="monthly" class="english" selected>Monthly (30 days)</option>
                        </select>
                        <div class="error-message" id="payment_cycle_error"></div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="group_conditions" class="hindi">समूह नियम और शर्तें *</label>
                    <label for="group_conditions" class="english">Group Conditions / Rules *</label>
                    <textarea id="group_conditions" name="group_conditions" required 
                              placeholder="Late payment penalty, auction rules, early exit terms, etc."></textarea>
                    <div class="error-message" id="group_conditions_error"></div>
                </div>

                <div class="form-group full-width">
                    <label for="stamp_upload" class="hindi">समूह समझौता स्टाम्प पेपर (₹100) *</label>
                    <label for="stamp_upload" class="english">Group Agreement Stamp Paper (₹100) *</label>
                    <input type="file" id="stamp_upload" name="stamp_upload" accept=".pdf,.jpg,.png,.jpeg" required>
                    <small class="file-hint hindi">केवल PDF, JPG, PNG फाइलें। अधिकतम साइज: 5MB</small>
                    <small class="file-hint english">Only PDF, JPG, PNG files. Max size: 5MB</small>
                    <div class="error-message" id="stamp_upload_error"></div>
                </div>
            </fieldset>

            <!-- Core Team Section -->
            <fieldset class="form-section">
                <legend class="section-legend">
                    <span class="hindi">2. मुख्य टीम सदस्यों की जानकारी</span>
                    <span class="english">2. Core Team Members Information</span>
                </legend>
                
                <p class="section-note hindi">तीनों व्यक्तियों के लिए मोबाइल नंबर और ईमेल आईडी अनिवार्य है</p>
                <p class="section-note english">Mobile number and email ID are mandatory for all three persons</p>

                <!-- Group Leader -->
                <div class="member-section">
                    <h3 class="member-role hindi">2A. समूह मालिक (लीडर)</h3>
                    <h3 class="member-role english">2A. Group Owner (Leader)</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="owner_name" class="hindi">पूरा नाम *</label>
                            <label for="owner_name" class="english">Full Name *</label>
                            <input type="text" id="owner_name" name="owner_name" required maxlength="80">
                            <div class="error-message" id="owner_name_error"></div>
                        </div>

                        <div class="form-group">
                            <label for="owner_mobile" class="hindi">मोबाइल नंबर *</label>
                            <label for="owner_mobile" class="english">Mobile Number *</label>
                            <input type="tel" id="owner_mobile" name="owner_mobile" required 
                                   pattern="[0-9]{10}" maxlength="10" placeholder="10 digit mobile number">
                            <div class="error-message" id="owner_mobile_error"></div>
                        </div>

                        <div class="form-group">
                            <label for="owner_email" class="hindi">ईमेल आईडी *</label>
                            <label for="owner_email" class="english">Email ID *</label>
                            <input type="email" id="owner_email" name="owner_email" required>
                            <div class="error-message" id="owner_email_error"></div>
                        </div>
                    </div>
                </div>

                <!-- Group Admin -->
                <div class="member-section">
                    <h3 class="member-role hindi">2B. समूह प्रबंधक (एडमिन)</h3>
                    <h3 class="member-role english">2B. Group Manager (Admin)</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="admin_name" class="hindi">पूरा नाम *</label>
                            <label for="admin_name" class="english">Full Name *</label>
                            <input type="text" id="admin_name" name="admin_name" required maxlength="80">
                            <div class="error-message" id="admin_name_error"></div>
                        </div>

                        <div class="form-group">
                            <label for="admin_mobile" class="hindi">मोबाइल नंबर *</label>
                            <label for="admin_mobile" class="english">Mobile Number *</label>
                            <input type="tel" id="admin_mobile" name="admin_mobile" required 
                                   pattern="[0-9]{10}" maxlength="10" placeholder="10 digit mobile number">
                            <div class="error-message" id="admin_mobile_error"></div>
                        </div>

                        <div class="form-group">
                            <label for="admin_email" class="hindi">ईमेल आईडी *</label>
                            <label for="admin_email" class="english">Email ID *</label>
                            <input type="email" id="admin_email" name="admin_email" required>
                            <div class="error-message" id="admin_email_error"></div>
                        </div>
                    </div>
                </div>

                <!-- Group Accountant -->
                <div class="member-section">
                    <h3 class="member-role hindi">2C. समूह लेखाकार (अकाउंटेंट)</h3>
                    <h3 class="member-role english">2C. Group Accountant</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="accountant_name" class="hindi">पूरा नाम *</label>
                            <label for="accountant_name" class="english">Full Name *</label>
                            <input type="text" id="accountant_name" name="accountant_name" required maxlength="80">
                            <div class="error-message" id="accountant_name_error"></div>
                        </div>

                        <div class="form-group">
                            <label for="accountant_mobile" class="hindi">मोबाइल नंबर *</label>
                            <label for="accountant_mobile" class="english">Mobile Number *</label>
                            <input type="tel" id="accountant_mobile" name="accountant_mobile" required 
                                   pattern="[0-9]{10}" maxlength="10" placeholder="10 digit mobile number">
                            <div class="error-message" id="accountant_mobile_error"></div>
                        </div>

                        <div class="form-group">
                            <label for="accountant_email" class="hindi">ईमेल आईडी *</label>
                            <label for="accountant_email" class="english">Email ID *</label>
                            <input type="email" id="accountant_email" name="accountant_email" required>
                            <div class="error-message" id="accountant_email_error"></div>
                        </div>
                    </div>
                </div>
            </fieldset>
            
            <!-- Submit Button -->
            <div class="form-actions">
                <button type="submit" class="btn btn-submit">
                    <span class="hindi">समूह पंजीकरण जमा करें</span>
                    <span class="english">Submit Group Registration</span>
                </button>
                <p class="form-note hindi">
                    ✅ सभी मुख्य सदस्यों को लॉगिन क्रेडेंशियल्स ईमेल से भेजे जाएंगे
                </p>
                <p class="form-note english">
                    ✅ All core members will receive login credentials via email
                </p>
            </div>
        </form>
    </div>
</div>

<?php include("footer.php"); ?>
<script src="group_signup.js"></script>
</body>
</html>