<?php
// --- 1. CONFIGURATION AND SESSION START ---
session_start();

// Database connection
$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = ''; 
$db_name = 'samuh_token_system'; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Ensure a user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // REMOVE IN PRODUCTION!
}

$member_id = $_SESSION['user_id'];
$message = ''; 

// --- 2. LOGOUT LOGIC ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: token_login.php');
    exit;
}

// --- 3. GET USER DATA ---
$user_stmt = $conn->prepare("SELECT user_id, full_name, user_code, created_at, email, mobile FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $member_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_data) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch active QR code
$qr_stmt = $conn->prepare("SELECT qr_image_path FROM qr_codes WHERE status = 'active' LIMIT 1");
$qr_stmt->execute();
$qr_result = $qr_stmt->get_result();
$active_qr = $qr_result->fetch_assoc();
$qr_stmt->close();

// Fetch token types
$token_types_result = $conn->query("SELECT type_id, token_name, buy_price, sell_price FROM token_types ORDER BY buy_price ASC");
$token_types = [];
while ($row = $token_types_result->fetch_assoc()) {
    $token_types[$row['type_id']] = $row;
}

// --- 4. ENHANCED PRICE CALCULATION FUNCTION ---
function calculate_final_sell_price($conn, $token_type_id, $purchase_date, $base_sell_price) {
    $datetime1 = new DateTime($purchase_date);
    $datetime2 = new DateTime(date('Y-m-d'));
    $interval = $datetime1->diff($datetime2);
    $holding_days = $interval->days;

    // Get bonus plan based on holding days
    $bonus_stmt = $conn->prepare("
        SELECT bonus_percentage, fixed_bonus 
        FROM token_sell_plans 
        WHERE token_type = ? AND minimum_days <= ? 
        ORDER BY minimum_days DESC 
        LIMIT 1
    ");
    $bonus_stmt->bind_param("ii", $token_type_id, $holding_days);
    $bonus_stmt->execute();
    $bonus_result = $bonus_stmt->get_result();
    $bonus_plan = $bonus_result->fetch_assoc();
    $bonus_stmt->close();

    $final_bonus_percentage = $bonus_plan['bonus_percentage'] ?? 0;
    $final_fixed_bonus = $bonus_plan['fixed_bonus'] ?? 0;
    
    $percentage_amount = $base_sell_price * ($final_bonus_percentage / 100);
    $final_sell_price = $base_sell_price + $percentage_amount + $final_fixed_bonus;
    
    return [
        'price' => $final_sell_price,
        'bonus_percent' => $final_bonus_percentage,
        'fixed_bonus' => $final_fixed_bonus,
        'holding_days' => $holding_days,
        'base_price' => $base_sell_price,
        'bonus_amount' => $percentage_amount
    ];
}

// --- 5. PRICE PREDICTION FOR ACTIVE TOKENS ---
function get_price_prediction($conn, $token_type_id, $purchase_date, $base_sell_price) {
    $predictions = [];
    
    // Get all sell plans for this token type
    $plan_stmt = $conn->prepare("
        SELECT minimum_days, bonus_percentage, fixed_bonus 
        FROM token_sell_plans 
        WHERE token_type = ? 
        ORDER BY minimum_days ASC
    ");
    $plan_stmt->bind_param("i", $token_type_id);
    $plan_stmt->execute();
    $plan_result = $plan_stmt->get_result();
    
    $current_date = new DateTime(date('Y-m-d'));
    $purchase_date_obj = new DateTime($purchase_date);
    
    while ($plan = $plan_result->fetch_assoc()) {
        $target_date = clone $purchase_date_obj;
        $target_date->modify("+{$plan['minimum_days']} days");
        
        if ($current_date < $target_date) {
            $days_remaining = $current_date->diff($target_date)->days;
            
            $bonus_amount = $base_sell_price * ($plan['bonus_percentage'] / 100);
            $final_price = $base_sell_price + $bonus_amount + $plan['fixed_bonus'];
            
            $predictions[] = [
                'days_remaining' => $days_remaining,
                'target_days' => $plan['minimum_days'],
                'bonus_percent' => $plan['bonus_percentage'],
                'final_price' => $final_price,
                'bonus_amount' => $bonus_amount + $plan['fixed_bonus']
            ];
        }
    }
    $plan_stmt->close();
    
    return $predictions;
}

// --- 6. BUY TOKEN PROCESSING ---
if (isset($_POST['buy_token'])) {
    $token_type = (int)$_POST['token_type'];
    $quantity = (int)$_POST['quantity'];
    $transaction_id = $conn->real_escape_string($_POST['transaction_id'] ?? '');

    $buy_price_per_token = $token_types[$token_type]['buy_price'] ?? 0;
    
    if ($quantity > 0 && $buy_price_per_token > 0) {
        $upload_path = null;
        $is_valid_proof = !empty($transaction_id);
        
        // Handle file upload
        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] == UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['payment_screenshot']['tmp_name'];
            $file_extension = pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('scr_') . '.' . $file_extension;
            $target_dir = 'uploads/screenshots/';
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($file_tmp, $target_file)) {
                $upload_path = $target_file;
                $is_valid_proof = true;
            } else {
                $message = '<div class="alert alert-error">❌ Error uploading file.</div>';
                goto end_buy_logic;
            }
        }
        
        if ($is_valid_proof) {
            $insert_stmt = $conn->prepare("
                INSERT INTO member_tokens 
                (member_id, token_type, quantity, buy_price, purchase_date, transaction_id, payment_screenshot, status) 
                VALUES (?, ?, ?, ?, NOW(), ?, ?, 'pending')
            ");
            $insert_stmt->bind_param("iiddss", 
                $member_id, 
                $token_type, 
                $quantity, 
                $buy_price_per_token, 
                $transaction_id, 
                $upload_path
            );

            if ($insert_stmt->execute()) {
                $message = '<div class="alert alert-success">✅ Token purchase request submitted successfully! Status: Pending.</div>';
            } else {
                $message = '<div class="alert alert-error">❌ Database error: ' . $insert_stmt->error . '</div>';
            }
            $insert_stmt->close();
        } else {
            $message = '<div class="alert alert-error">⚠️ Please provide either a payment screenshot or a Transaction ID.</div>';
        }
    } else {
        $message = '<div class="alert alert-error">❌ Invalid quantity or token type selected.</div>';
    }
    end_buy_logic:
}

// --- 7. SELL TOKEN PROCESSING ---
if (isset($_POST['initiate_sell'])) {
    $token_id = (int)$_POST['sell_token_id'];
    $sell_quantity = (int)$_POST['sell_quantity'];
    $bank_name = $conn->real_escape_string($_POST['bank_name']);
    $account_number = $conn->real_escape_string($_POST['account_number']);
    $ifsc_code = $conn->real_escape_string($_POST['ifsc_code'] ?? '');
    $phone_number = $conn->real_escape_string($_POST['phone_number'] ?? '');
    $kyc_document = $_FILES['kyc_document'] ?? null;
    
    // KYC Validation
    if (empty($bank_name) || empty($account_number)) {
        $message = '<div class="alert alert-error">❌ Bank details are required.</div>';
        goto end_sell_logic;
    }

    // KYC Document Upload
    $kyc_path = null;
    if ($kyc_document && $kyc_document['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $kyc_document['tmp_name'];
        $file_extension = pathinfo($kyc_document['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('kyc_') . '.' . $file_extension;
        $target_dir = 'uploads/kyc/';
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $target_file = $target_dir . $file_name;

        if (!move_uploaded_file($file_tmp, $target_file)) {
            $message = '<div class="alert alert-error">❌ Error uploading KYC document.</div>';
            goto end_sell_logic;
        }
        $kyc_path = $target_file;
    }

    // Get token details
    $token_detail_stmt = $conn->prepare("
        SELECT mt.id, mt.quantity, mt.buy_price, mt.token_type, tt.sell_price AS base_sell_price, mt.purchase_date
        FROM member_tokens mt
        JOIN token_types tt ON mt.token_type = tt.type_id
        WHERE mt.id = ? AND mt.member_id = ? AND mt.status = 'active'
    ");
    $token_detail_stmt->bind_param("ii", $token_id, $member_id);
    $token_detail_stmt->execute();
    $token_detail_result = $token_detail_stmt->get_result();
    $token_details = $token_detail_result->fetch_assoc();
    $token_detail_stmt->close();

    if ($token_details) {
        $original_quantity = $token_details['quantity'];
        $base_sell_price = $token_details['base_sell_price'];
        $token_type_id = $token_details['token_type'];
        $purchase_date = $token_details['purchase_date'];
        $buy_price = $token_details['buy_price'];
        
        if ($sell_quantity > $original_quantity || $sell_quantity <= 0) {
            $message = '<div class="alert alert-error">❌ Invalid quantity specified.</div>';
            goto end_sell_logic;
        }

        // Calculate final price with bonus
        $final_price_data = calculate_final_sell_price($conn, $token_type_id, $purchase_date, $base_sell_price);
        $final_sell_price = $final_price_data['price'];
        $total_sale_value = $final_sell_price * $sell_quantity;

        $conn->begin_transaction();

        try {
            // Handle partial/full sell
            if ($sell_quantity < $original_quantity) {
                $remaining_quantity = $original_quantity - $sell_quantity;
                
                $update_original_stmt = $conn->prepare("UPDATE member_tokens SET quantity = ?, status = 'sell_requested', sell_request_date = NOW(), sell_price = ? WHERE id = ? AND member_id = ?");
                $update_original_stmt->bind_param("idii", $sell_quantity, $final_sell_price, $token_id, $member_id);
                $update_original_stmt->execute();
                $update_original_stmt->close();
                
                $insert_remaining_stmt = $conn->prepare("
                    INSERT INTO member_tokens 
                    (member_id, token_type, quantity, buy_price, purchase_date, status, sell_price) 
                    VALUES (?, ?, ?, ?, ?, 'active', NULL)
                ");
                $insert_remaining_stmt->bind_param("iiids", $member_id, $token_type_id, $remaining_quantity, $buy_price, $purchase_date);
                $insert_remaining_stmt->execute();
                $insert_remaining_stmt->close();
            } else {
                $update_full_stmt = $conn->prepare("UPDATE member_tokens SET status = 'sell_requested', sell_request_date = NOW(), sell_price = ? WHERE id = ? AND member_id = ?");
                $update_full_stmt->bind_param("dii", $final_sell_price, $token_id, $member_id);
                $update_full_stmt->execute();
                $update_full_stmt->close();
            }

            // Insert sell request with KYC details
            $insert_sell_stmt = $conn->prepare("
                INSERT INTO token_sell_requests 
                (member_id, token_id, quantity, request_date, bank_name, account_number, ifsc_code, phone_number, kyc_document, admin_set_price, status) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $insert_sell_stmt->bind_param("iiisssssd", 
                $member_id, 
                $token_id, 
                $sell_quantity, 
                $bank_name, 
                $account_number, 
                $ifsc_code, 
                $phone_number, 
                $kyc_path,
                $final_sell_price
            );
            $insert_sell_stmt->execute();
            $insert_sell_stmt->close();
            
            $conn->commit();

            $message = '<div class="alert alert-success">💰 Sell request submitted successfully! Holding: ' . $final_price_data['holding_days'] . ' days, Bonus: ' . $final_price_data['bonus_percent'] . '%. Price per token: ₹' . number_format($final_sell_price, 2) . '.</div>';

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-error">❌ Transaction error: Could not process sell request.</div>';
        }
    } else {
        $message = '<div class="alert alert-error">❌ Error: Token slot not found or not active.</div>';
    }
    end_sell_logic:
}

// --- 8. FETCH DASHBOARD DATA ---
$summary_sql = "
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'active' THEN quantity ELSE 0 END), 0) AS total_active,
        COALESCE(SUM(CASE WHEN status = 'pending' OR status = 'sell_requested' THEN quantity ELSE 0 END), 0) AS total_pending,
        COALESCE(SUM(CASE WHEN status = 'sold' THEN quantity ELSE 0 END), 0) AS total_sold
    FROM member_tokens
    WHERE member_id = ?
";
$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("i", $member_id);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$token_summary = $summary_result->fetch_assoc();
$summary_stmt->close();

// User tokens with price predictions
$tokens_list_sql = "
    SELECT 
        mt.id, mt.token_type, tt.token_name, mt.quantity, mt.buy_price, mt.purchase_date, mt.status, tt.sell_price AS base_sell_price,
        mt.sell_price AS recorded_sell_price
    FROM member_tokens mt
    JOIN token_types tt ON mt.token_type = tt.type_id
    WHERE mt.member_id = ?
    ORDER BY mt.id DESC
";
$tokens_list_stmt = $conn->prepare($tokens_list_sql);
$tokens_list_stmt->bind_param("i", $member_id);
$tokens_list_stmt->execute();
$tokens_list_result = $tokens_list_stmt->get_result();

// Notifications
$notifications_sql = "
    SELECT notification_id, title, message, created_at, seen
    FROM notifications
    WHERE member_id = ?
    ORDER BY created_at DESC
    LIMIT 5
";
$notifications_stmt = $conn->prepare($notifications_sql);
$notifications_stmt->bind_param("i", $member_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();
$notifications_stmt->close();

$update_seen_stmt = $conn->prepare("UPDATE notifications SET seen = 'yes' WHERE member_id = ? AND seen = 'no'");
$update_seen_stmt->bind_param("i", $member_id);
$update_seen_stmt->execute();
$update_seen_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Member Dashboard | Samuh Token System</title>
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --success-color: #28a745;
            --error-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --bg-light: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #343a40;
            --border-color: #dee2e6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-light);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 15px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 1.5rem;
            margin: 0;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        @media (min-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr 2fr;
            }
            .main-content {
                grid-column: 2 / 3;
            }
            .sidebar {
                grid-column: 1 / 2;
                grid-row: 1 / 3; 
            }
        }

        .card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .card h2 {
            font-size: 1.3rem;
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .welcome-card h2 {
            color: white;
            border-bottom-color: rgba(255, 255, 255, 0.3);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-item {
            text-align: center;
            padding: 20px 10px;
            background: var(--bg-light);
            border-radius: 8px;
            border: 2px solid var(--border-color);
        }

        .summary-item .value {
            font-size: 2rem;
            font-weight: 700;
        }

        .summary-item .label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .summary-item.active .value { color: var(--success-color); }
        .summary-item.pending .value { color: var(--warning-color); }
        .summary-item.sold .value { color: var(--error-color); }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
            display: inline-block;
        }
        .btn-primary:hover { background: var(--primary-hover); }

        .btn-sell {
            background: var(--error-color);
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: opacity 0.3s;
        }
        .btn-sell:hover { opacity: 0.8; }

        .btn-info {
            background: var(--info-color);
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-left: 5px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background-color: var(--bg-light);
            font-weight: 600;
            color: #6c757d;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.sell_requested { background: #cce5ff; color: #004085; }
        .status-badge.sold { background: #f8d7da; color: #721c24; }

        .price-breakdown {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid var(--info-color);
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }

        .prediction-item {
            background: #e7f3ff;
            border-radius: 5px;
            padding: 8px;
            margin: 5px 0;
            border-left: 3px solid var(--info-color);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .close-btn {
            float: right;
            font-size: 24px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .header h1 { font-size: 1.3rem; }
            .data-table th, .data-table td { padding: 8px; font-size: 0.85rem; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>🚀 Samuh Token Dashboard</h1>
    <a href="?logout=true" class="logout-btn">🚪 Logout</a>
</div>

<div class="container">
    
    <?php echo $message; ?>

    <div class="dashboard-grid">

        <div class="sidebar">
            <div class="card welcome-card">
                <h2>👋 Welcome, <?php echo htmlspecialchars($user_data['full_name']); ?>!</h2>
                <p>Your Member Code: <strong><?php echo htmlspecialchars($user_data['user_code']); ?></strong></p>
                <p>Joined: <?php echo date('d M, Y', strtotime($user_data['created_at'])); ?></p>
            </div>

            <div class="card">
                <h2>📊 Token Summary</h2>
                <div class="summary-grid">
                    <div class="summary-item active">
                        <div class="value"><?php echo $token_summary['total_active']; ?></div>
                        <div class="label">Active Tokens</div>
                    </div>
                    <div class="summary-item pending">
                        <div class="value"><?php echo $token_summary['total_pending']; ?></div>
                        <div class="label">Pending</div>
                    </div>
                    <div class="summary-item sold">
                        <div class="value"><?php echo $token_summary['total_sold']; ?></div>
                        <div class="label">Sold Tokens</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>🔔 Notifications</h2>
                <div class="notification-list">
                    <?php if ($notifications_result->num_rows > 0): ?>
                        <?php while ($n = $notifications_result->fetch_assoc()): ?>
                            <div class="notification-item <?php echo ($n['seen'] === 'no' ? 'unseen' : ''); ?>">
                                <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                                <p><?php echo htmlspecialchars($n['message']); ?></p>
                                <small><?php echo date('d M, h:i A', strtotime($n['created_at'])); ?></small>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No recent notifications.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="main-content">
            
            <div class="card">
                <h2>🛒 Buy Tokens</h2>
                <form action="" method="POST" enctype="multipart/form-data">
                    <?php if ($active_qr): ?>
                        <div style="text-align: center; margin-bottom: 20px;">
                            <p><strong>Scan QR Code to Pay:</strong></p>
                            <img src="<?php echo htmlspecialchars($active_qr['qr_image_path']); ?>" alt="QR Code" style="max-width: 200px; border: 1px solid #ddd;">
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Token Type:</label>
                        <select name="token_type" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($token_types as $type): ?>
                                <option value="<?php echo $type['type_id']; ?>">
                                    <?php echo htmlspecialchars($type['token_name']); ?> 
                                    (₹<?php echo number_format($type['buy_price'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quantity:</label>
                        <input type="number" name="quantity" min="1" value="1" required>
                    </div>

                    <div class="form-group">
                        <label>Payment Proof (Screenshot):</label>
                        <input type="file" name="payment_screenshot" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label>Transaction ID:</label>
                        <input type="text" name="transaction_id" placeholder="Enter transaction ID">
                    </div>

                    <button type="submit" name="buy_token" class="btn-primary">Submit Purchase</button>
                </form>
            </div>

            <div class="card">
                <h2>💎 My Tokens</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Qty</th>
                                <th>Buy Price</th>
                                <th>Current Value</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $tokens_list_result->data_seek(0);
                            if ($tokens_list_result->num_rows > 0): 
                                while ($token = $tokens_list_result->fetch_assoc()): 
                                    $current_price_data = calculate_final_sell_price($conn, $token['token_type'], $token['purchase_date'], $token['base_sell_price']);
                                    $predictions = $token['status'] === 'active' ? 
                                        get_price_prediction($conn, $token['token_type'], $token['purchase_date'], $token['base_sell_price']) : [];
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($token['token_name']); ?></strong>
                                        <br><small>Held: <?php echo $current_price_data['holding_days']; ?> days</small>
                                    </td>
                                    <td><?php echo $token['quantity']; ?></td>
                                    <td>₹<?php echo number_format($token['buy_price'], 2); ?></td>
                                    <td>
                                        <?php if ($token['status'] === 'active'): ?>
                                            <strong>₹<?php echo number_format($current_price_data['price'], 2); ?></strong>
                                            <br>
                                            <small>Bonus: <?php echo $current_price_data['bonus_percent']; ?>%</small>
                                            <button class="btn-info" onclick="showPriceDetails(<?php echo $token['id']; ?>)">ℹ️</button>
                                        <?php else: ?>
                                            ₹<?php echo number_format($token['recorded_sell_price'], 2); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $token['status']; ?>">
                                            <?php echo str_replace('_', ' ', $token['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($token['status'] === 'active'): ?>
                                            <button class="btn-sell" onclick="openSellModal(<?php echo $token['id']; ?>)">Sell</button>
                                            <?php if (!empty($predictions)): ?>
                                                <button class="btn-info" onclick="showPredictions(<?php echo $token['id']; ?>)">📈</button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center;">No tokens found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Price Details Modal -->
<div id="priceModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('priceModal')">&times;</span>
        <h3>💰 Price Breakdown</h3>
        <div id="priceDetails"></div>
    </div>
</div>

<!-- Predictions Modal -->
<div id="predictionModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('predictionModal')">&times;</span>
        <h3>📈 Future Price Predictions</h3>
        <div id="predictionDetails"></div>
    </div>
</div>

<!-- Sell Modal -->
<div id="sellModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('sellModal')">&times;</span>
        <h3>💸 Sell Tokens</h3>
        <form action="" method="POST" enctype="multipart/form-data" id="sellForm">
            <input type="hidden" name="sell_token_id" id="sellTokenId">
            
            <div class="form-group">
                <label>Quantity to Sell:</label>
                <input type="number" name="sell_quantity" id="sellQuantity" min="1" required>
                <small>Max: <span id="maxQuantity">0</span></small>
            </div>

            <div id="sellPriceInfo" class="price-breakdown"></div>

            <div class="form-group">
                <label>Bank Name:</label>
                <input type="text" name="bank_name" required>
            </div>

            <div class="form-group">
                <label>Account Number:</label>
                <input type="text" name="account_number" required>
            </div>

            <div class="form-group">
                <label>IFSC Code:</label>
                <input type="text" name="ifsc_code">
            </div>

            <div class="form-group">
                <label>KYC Document (Aadhar/PAN):</label>
                <input type="file" name="kyc_document" accept="image/*,.pdf" required>
            </div>

            <button type="submit" name="initiate_sell" class="btn-primary">Submit Sell Request</button>
        </form>
    </div>
</div>

<script>
// Token data from PHP
const tokensData = <?php 
    $tokens_list_result->data_seek(0);
    $tokens_js = [];
    while ($token = $tokens_list_result->fetch_assoc()) {
        if ($token['status'] === 'active') {
            $price_data = calculate_final_sell_price($conn, $token['token_type'], $token['purchase_date'], $token['base_sell_price']);
            $predictions = get_price_prediction($conn, $token['token_type'], $token['purchase_date'], $token['base_sell_price']);
            
            $tokens_js[$token['id']] = [
                'name' => $token['token_name'],
                'quantity' => $token['quantity'],
                'buy_price' => $token['buy_price'],
                'current_price' => $price_data,
                'predictions' => $predictions
            ];
        }
    }
    echo json_encode($tokens_js);
?>;

function showPriceDetails(tokenId) {
    const token = tokensData[tokenId];
    if (!token) return;
    
    const price = token.current_price;
    let html = `
        <div class="price-breakdown">
            <div class="price-item">
                <span>Base Price:</span>
                <span>₹${price.base_price.toFixed(2)}</span>
            </div>
            <div class="price-item">
                <span>Bonus (${price.bonus_percent}%):</span>
                <span>+₹${price.bonus_amount.toFixed(2)}</span>
            </div>
            ${price.fixed_bonus > 0 ? `
            <div class="price-item">
                <span>Fixed Bonus:</span>
                <span>+₹${price.fixed_bonus.toFixed(2)}</span>
            </div>` : ''}
            <div class="price-item" style="font-weight: bold; border-top: 1px solid #ccc; padding-top: 5px;">
                <span>Final Price:</span>
                <span>₹${price.price.toFixed(2)}</span>
            </div>
            <div style="margin-top: 10px; color: #666;">
                Holding Period: ${price.holding_days} days
            </div>
        </div>
    `;
    
    document.getElementById('priceDetails').innerHTML = html;
    document.getElementById('priceModal').style.display = 'block';
}

function showPredictions(tokenId) {
    const token = tokensData[tokenId];
    if (!token || !token.predictions.length) return;
    
    let html = '<h4>Future Bonus Opportunities:</h4>';
    token.predictions.forEach(pred => {
        html += `
            <div class="prediction-item">
                <div><strong>${pred.target_days} days total</strong> (${pred.days_remaining} days remaining)</div>
                <div>Bonus: ${pred.bonus_percent}% (+₹${pred.bonus_amount.toFixed(2)})</div>
                <div>Final Price: ₹${pred.final_price.toFixed(2)}</div>
            </div>
        `;
    });
    
    document.getElementById('predictionDetails').innerHTML = html;
    document.getElementById('predictionModal').style.display = 'block';
}

function openSellModal(tokenId) {
    const token = tokensData[tokenId];
    if (!token) return;
    
    document.getElementById('sellTokenId').value = tokenId;
    document.getElementById('maxQuantity').textContent = token.quantity;
    document.getElementById('sellQuantity').max = token.quantity;
    document.getElementById('sellQuantity').value = token.quantity;
    
    const price = token.current_price;
    const totalValue = price.price * token.quantity;
    
    let html = `
        <h4>Sale Calculation:</h4>
        <div class="price-item">
            <span>Per Token:</span>
            <span>₹${price.price.toFixed(2)}</span>
        </div>
        <div class="price-item">
            <span>Quantity:</span>
            <span>${token.quantity}</span>
        </div>
        <div class="price-item" style="font-weight: bold;">
            <span>Total Value:</span>
            <span>₹${totalValue.toFixed(2)}</span>
        </div>
        <div style="color: #666; font-size: 0.9em;">
            Includes ${price.bonus_percent}% bonus for ${price.holding_days} days holding
        </div>
    `;
    
    document.getElementById('sellPriceInfo').innerHTML = html;
    document.getElementById('sellModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['priceModal', 'predictionModal', 'sellModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
}

// Update sell calculation when quantity changes
document.getElementById('sellQuantity')?.addEventListener('input', function() {
    const tokenId = document.getElementById('sellTokenId').value;
    const quantity = parseInt(this.value) || 0;
    const token = tokensData[tokenId];
    
    if (token && quantity > 0) {
        const maxQty = parseInt(document.getElementById('maxQuantity').textContent);
        const validQty = Math.min(quantity, maxQty);
        this.value = validQty;
        
        const price = token.current_price;
        const totalValue = price.price * validQty;
        
        document.querySelector('#sellPriceInfo .price-item:last-child span:last-child').textContent = 
            '₹' + totalValue.toFixed(2);
    }
});
</script>

</body>
</html>

<?php 
$conn->close(); 
?>