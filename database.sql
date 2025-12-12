-- ==========================================
-- 1. Create Database
-- ==========================================
CREATE DATABASE IF NOT EXISTS samuh_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE samuh_platform;

-- ==========================================
-- 2. Groups Table
-- ==========================================
CREATE TABLE IF NOT EXISTS groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    tenure_months INT NOT NULL,
    expected_amount DECIMAL(10,2) NOT NULL,
    min_members_count INT NOT NULL,
    group_conditions TEXT NOT NULL,
    stamp_upload_path VARCHAR(255) NOT NULL,

    -- 🔹 Core Members basic info
    leader_name VARCHAR(100) NOT NULL,
    leader_mobile VARCHAR(15) NOT NULL,
    leader_email VARCHAR(150) NOT NULL,

    admin_name VARCHAR(100) NOT NULL,
    admin_mobile VARCHAR(15) NOT NULL,
    admin_email VARCHAR(150) NOT NULL,

    accountant_name VARCHAR(100) NOT NULL,
    accountant_mobile VARCHAR(15) NOT NULL,
    accountant_email VARCHAR(150) NOT NULL,

    payment_cycle ENUM('weekly', 'monthly') NOT NULL DEFAULT 'monthly',
    payment_start_date DATE NULL,
    
    -- 🔹 Late Fee Settings (Updated with progressive option)
    late_fee_type ENUM('fixed','per_day','percent','progressive') 
    NOT NULL DEFAULT 'per_day'
    COMMENT 'fixed = ek hi baar ka fine, per_day = har din ka fine, percent = payment ke percent ke hisab se fine, progressive = badhta hua system',

    late_fee_value DECIMAL(10,2) 
    NOT NULL DEFAULT 0.00 
    COMMENT 'late fee ki value jaise 10 Rs ya 2 Rs/day ya 2%',

    status ENUM('pending_documents','awaiting_verification','active','inactive') DEFAULT 'pending_documents',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 3. Leader (Owner) Table
-- ==========================================
CREATE TABLE IF NOT EXISTS leaders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    dob DATE,
    address TEXT,

    pan_number VARCHAR(10),
    pan_proof_path VARCHAR(255),
    aadhaar_proof_path VARCHAR(255),
    bank_proof_path VARCHAR(255),
    signature_proof_path VARCHAR(255),
    profile_photo_path VARCHAR(255),

    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    UNIQUE KEY uk_leader_username (group_id, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 4. Admin Table
-- ==========================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    dob DATE,
    address TEXT,

    pan_number VARCHAR(10),
    pan_proof_path VARCHAR(255),
    aadhaar_proof_path VARCHAR(255),
    bank_proof_path VARCHAR(255),
    signature_proof_path VARCHAR(255),
    profile_photo_path VARCHAR(255),

    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    UNIQUE KEY uk_admin_username (group_id, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 5. Accountant Table
-- ==========================================
CREATE TABLE IF NOT EXISTS accountants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    dob DATE,
    address TEXT,

    pan_number VARCHAR(10),
    pan_proof_path VARCHAR(255),
    aadhaar_proof_path VARCHAR(255),
    bank_proof_path VARCHAR(255),
    signature_proof_path VARCHAR(255),
    profile_photo_path VARCHAR(255),

    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    UNIQUE KEY uk_accountant_username (group_id, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




-- ==========================================
-- Payment Request Table (SIMPLIFIED - without problematic FK)
-- ==========================================
CREATE TABLE IF NOT EXISTS payment_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    qr_id INT NOT NULL,

    -- NEW COLUMN ADDED: Payment type
    payment_type ENUM('regular', 'late_fee') NOT NULL DEFAULT 'regular',

    -- Verification fields
    verified_by_accountant INT DEFAULT NULL,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    payment_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Only essential foreign keys
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    
    KEY idx_member_status (member_id, payment_status),
    KEY idx_group_qr (group_id, qr_id),
    KEY idx_payment_type (payment_type)  -- NEW INDEX for payment type
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('member', 'leader', 'accountant', 'admin') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    related_entity_type VARCHAR(50),  -- e.g., 'payment', 'member_request'
    related_entity_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    KEY idx_user_notifications (user_id, user_type, is_read)
);


CREATE TABLE qr_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,                       -- Kis group ke liye QR upload hua
    uploaded_by VARCHAR(100) NOT NULL,           -- Kisne upload kiya (accountant username)
    qr_image VARCHAR(255) NOT NULL,              -- QR image ka file path
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Upload ka time
    verified_by VARCHAR(100) DEFAULT NULL,       -- Kis leader ne verify kiya
    verify_date TIMESTAMP NULL,                  -- Kab verify hua
    is_active BOOLEAN DEFAULT FALSE,             -- Active QR hai ya nahi (true/false)
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Late Fees Table
CREATE TABLE IF NOT EXISTS late_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    group_id INT NOT NULL,
    cycle_no INT NOT NULL,
    payment_date DATE NOT NULL,
    due_date DATE NOT NULL,
    days_late INT NOT NULL,
    fine_amount DECIMAL(10,2) NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    is_paid TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    
    -- Ek member ka ek cycle ke liye ek hi late fee record
    UNIQUE KEY uk_member_cycle (member_id, group_id, cycle_no),
    
    KEY idx_member_status (member_id, is_paid),
    KEY idx_group (group_id),
    KEY idx_status (is_paid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Payment Late Policy Table

CREATE TABLE IF NOT EXISTS payment_late_policy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    grace_days INT DEFAULT 3,
    fine_per_day DECIMAL(10,2) DEFAULT 5.00,
    monthly_due_day INT DEFAULT 1,  -- mahine ka konsa din due hai (e.g., 1 = 1st of month)
    monthly_amount DECIMAL(10,2) DEFAULT 1000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    UNIQUE KEY uk_group_policy (group_id)
);
-- ==========================================
-- 1. External Applicants Table
-- ==========================================
CREATE TABLE IF NOT EXISTS external_applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(150),
    aadhaar_no VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    pan_number VARCHAR(10),
    monthly_income DECIMAL(10,2),
    occupation VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_aadhaar (aadhaar_no),
    UNIQUE KEY uk_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------

-- ==========================================
-- 2. Loans Table (Refined)
-- ==========================================
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    applicant_type ENUM('member', 'external') NOT NULL,
    member_id INT NULL, 
    external_applicant_id INT NULL,
    loan_amount DECIMAL(12,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL, -- Annual interest rate (%)
    tenure_months INT NOT NULL, 
    
    original_monthly_installment DECIMAL(10,2) NOT NULL, 
    total_payable DECIMAL(12,2) NOT NULL,
    
    -- Application details
    purpose VARCHAR(255) NOT NULL,
    applied_date DATE NOT NULL,
    approved_date DATE NULL,
    
    -- Status tracking
    status ENUM('pending', 'approved', 'rejected', 'active', 'closed', 'defaulted') DEFAULT 'pending',
    approved_by INT NULL, -- accountant_id
    rejected_reason TEXT,
    
    -- Repayment tracking & Disbursement
    disbursed_amount DECIMAL(12,2) DEFAULT 0.00,
    disbursed_date DATE NULL,
    disbursed_by INT NULL, -- Accountant who disbursed funds
    total_paid DECIMAL(12,2) DEFAULT 0.00,
    remaining_balance DECIMAL(12,2) NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (external_applicant_id) REFERENCES external_applicants(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES accountants(id) ON DELETE SET NULL,
    FOREIGN KEY (disbursed_by) REFERENCES accountants(id) ON DELETE SET NULL, 
    
    KEY idx_group_status (group_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------

-- ==========================================
-- 3. Loan Payments Table (Refined for better tracking)
-- ==========================================
CREATE TABLE IF NOT EXISTS loan_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    installment_number INT NOT NULL,
    due_date DATE NOT NULL,
    
    -- Due amounts
    principal_due DECIMAL(10,2) NOT NULL, 
    interest_due DECIMAL(10,2) NOT NULL,   
    due_amount DECIMAL(10,2) NOT NULL,     
    
    -- Paid amounts
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    principal_paid DECIMAL(10,2) DEFAULT 0.00, 
    interest_paid DECIMAL(10,2) DEFAULT 0.00,  
    
    paid_date DATE NULL,
    payment_method ENUM('cash', 'online', 'qr', 'cheque') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'overdue', 'partially_paid') DEFAULT 'pending',
    
    -- Verification fields (by accountant)
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_notes TEXT,
    
    -- Late fee tracking
    late_fee_amount DECIMAL(10,2) DEFAULT 0.00,
    days_late INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES accountants(id) ON DELETE SET NULL,
    
    UNIQUE KEY uk_loan_installment (loan_id, installment_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------

-- ==========================================
-- 4. Loan Interest Calculation Log
-- ==========================================
CREATE TABLE IF NOT EXISTS loan_interest_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    calculation_date DATE NOT NULL,
    principal_balance DECIMAL(12,2) NOT NULL,
    interest_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    KEY idx_loan_date (loan_id, calculation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ==========================================
-- Single Members Table (Replace both tables)
-- ==========================================
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,

    -- 🔹 Basic Personal Info
    full_name VARCHAR(150) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(150),
    username VARCHAR(80),
    password_hash VARCHAR(255),
    
    dob DATE,
    gender ENUM('male','female','other') DEFAULT 'male',
    address TEXT,
    nominee_name VARCHAR(150),

    -- 🔹 Bank Details
    bank_account_masked VARCHAR(50),
    bank_ifsc VARCHAR(20),
    bank_name_branch VARCHAR(150),

    -- 🔹 Identity Numbers
    aadhaar_no VARCHAR(20),
    pan_number VARCHAR(10),

    -- 🔹 Uploaded Proofs
    aadhaar_proof_path VARCHAR(255),
    pan_proof_path VARCHAR(255),
    address_proof_path VARCHAR(255),
    photo_path VARCHAR(255),
    signature_path VARCHAR(255),

    -- 🔹 MEMBER STATUS (Yeh Naya Column - Most Important)
    member_status ENUM('pending', 'active', 'rejected', 'inactive') DEFAULT 'pending',
    
    -- 🔹 KYC & Verification Status
    kyc_status ENUM('not_submitted','submitted','under_review','verified','rejected') DEFAULT 'not_submitted',
    
    -- 🔹 Application Tracking (For Pending Members)
    submitted_by_user_id INT DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    reviewed_by INT DEFAULT NULL, -- Admin ne verify kiya
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,

    -- 🔹 Financial Tracking (For Active Members)
    joining_date DATE NULL,
    last_payment_date DATE NULL,
    total_contributions DECIMAL(12,2) DEFAULT 0.00,

    -- 🔹 System Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,

    -- 🔹 Unique Constraints
    UNIQUE KEY uk_group_mobile (group_id, mobile),
    UNIQUE KEY uk_aadhaar (aadhaar_no),
    
    -- 🔹 Indexes for Performance
    KEY idx_member_status (member_status),
    KEY idx_group_status (group_id, member_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE late_fees ADD COLUMN payment_request_id INT NULL AFTER is_paid;