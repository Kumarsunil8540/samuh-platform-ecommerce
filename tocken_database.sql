CREATE DATABASE samuh_token_system;
USE samuh_token_system;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(100),
    mobile VARCHAR(15) NOT NULL,
    user_code VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','inactive') DEFAULT 'active'
);


CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(150),
    role ENUM('admin', 'staff') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO admins (username, password, name, role)
VALUES ('admin', MD5('admin123'), 'Main Admin', 'admin');


CREATE TABLE token_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    token_name VARCHAR(50) UNIQUE NOT NULL,     -- Example: Gold, Silver, Bronze
    buy_price DECIMAL(10,2) NOT NULL,           -- Token buy price
    sell_price DECIMAL(10,2) ,          -- Token sell price
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO token_types (token_name, buy_price, sell_price)
VALUES
('Gold', 100, 120),
('Silver', 50, 60),
('Bronze', 20, 25);

CREATE TABLE token_sell_plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,

    token_type INT NOT NULL,                  -- Gold/Silver/Bronze
    minimum_days INT NOT NULL,                -- Kitne din hold kiya

    bonus_percentage DECIMAL(5,2) DEFAULT 0,  -- +10%, +20%, etc
    fixed_bonus DECIMAL(10,2) DEFAULT 0,      -- +50 Rs extra optional

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (token_type) REFERENCES token_types(type_id)
);
INSERT INTO token_sell_plans (token_type, minimum_days, bonus_percentage)
VALUES
(1, 15, 2),    -- Gold token 15 days holding → +2%
(1, 30, 5),    -- 1 month → +5%
(1, 90, 10),   -- 3 months → +10%
(1, 180, 20),  -- 6 months → +20%
(1, 365, 30);  -- 1 year → +30%


CREATE TABLE qr_codes (
    qr_id INT AUTO_INCREMENT PRIMARY KEY,

    qr_image_path VARCHAR(255) NOT NULL,             -- QR image file ka path

    created_by INT NOT NULL,                         -- jis admin ne QR upload kiya
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  -- kab upload kiya

    status ENUM('active', 'inactive') DEFAULT 'inactive',  -- current status

    activated_by INT DEFAULT NULL,                   -- kis admin ne activate kiya
    activated_at TIMESTAMP NULL DEFAULT NULL,

    deactivated_by INT DEFAULT NULL,                 -- kis admin ne deactivate kiya
    deactivated_at TIMESTAMP NULL DEFAULT NULL,

    FOREIGN KEY (created_by) REFERENCES admins(admin_id),
    FOREIGN KEY (activated_by) REFERENCES admins(admin_id),
    FOREIGN KEY (deactivated_by) REFERENCES admins(admin_id)
);


CREATE TABLE member_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,

    member_id INT NOT NULL,                     -- Kis user ne token kharida
    token_type INT NOT NULL,                    -- Gold / Silver / Bronze

    quantity INT DEFAULT 1,                     -- Kitne token kharide
    buy_price DECIMAL(10,2) NOT NULL,           -- Rate jisme kharida

    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Payment Proof (Optional)
    transaction_id VARCHAR(100) DEFAULT NULL,   -- User ne likha hua Txn ID
    payment_screenshot VARCHAR(255) DEFAULT NULL, -- Upload file (optional)
    
    -- Status Flow:
    -- pending -> active -> sell_requested -> sold
    status ENUM('pending','active','sell_requested','sold') DEFAULT 'pending',

    -- Sell request details (when user sells)
    sell_request_date TIMESTAMP NULL,
    sell_price DECIMAL(10,2) DEFAULT NULL,      -- Admin decide karega
    sell_payment_id VARCHAR(100) DEFAULT NULL,  -- Admin ne paise bhejne ke baad
    sell_payment_date TIMESTAMP NULL,           -- Kab bheja
    
    FOREIGN KEY (member_id) REFERENCES users(user_id),
    FOREIGN KEY (token_type) REFERENCES token_types(type_id)
);
CREATE TABLE token_sell_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,

    member_id INT NOT NULL,             -- Kis member ne request ki
    token_id INT NOT NULL,              -- member_tokens ka id

    quantity INT NOT NULL DEFAULT 1,    -- Kitna token bech raha hai

    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- User bank details (required)
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    ifsc_code VARCHAR(20) DEFAULT NULL,
    phone_number VARCHAR(15) DEFAULT NULL,   -- Optional UPI/mobile

    -- Admin decision
    admin_set_price DECIMAL(10,2) DEFAULT NULL, -- Admin kitne me kharidega
    admin_transaction_id VARCHAR(100) DEFAULT NULL, -- Admin ne pay kiya to ID

    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP NULL,

    payment_date TIMESTAMP NULL,

    status ENUM('pending','approved','rejected','paid')
        DEFAULT 'pending',

    FOREIGN KEY (member_id) REFERENCES users(user_id),
    FOREIGN KEY (token_id) REFERENCES member_tokens(id),
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id)
);







CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,

    member_id INT NOT NULL,                     -- Kis member ke liye notification

    title VARCHAR(200) NOT NULL,                -- Short title
    message TEXT NOT NULL,                      -- Notification description

    notification_type ENUM(
        'token_purchase',
        'token_approved',
        'sell_request',
        'sell_paid'
    ) NOT NULL,

    seen ENUM('yes','no') DEFAULT 'no',         -- Dekha ya nahi

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (member_id) REFERENCES users(user_id)
);


