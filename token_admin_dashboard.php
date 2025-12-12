<?php
session_start();
require_once 'token_config.php'; // Database connection file

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Get admin details from database
$admin_query = $pdo->prepare("SELECT name, username, role FROM admins WHERE admin_id = ?");
$admin_query->execute([$admin_id]);
$admin_data = $admin_query->fetch();

if (!$admin_data) {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

$admin_name = $admin_data['name'];
$admin_username = $admin_data['username'];
$admin_role = $admin_data['role'];

// Initialize variables
$message = "";
$error = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Upload QR Code
    if (isset($_POST['upload_qr'])) {
        if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] == 0) {
            $target_dir = "uploads/qr_codes/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES["qr_image"]["name"], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $qr_image_path = $target_dir . "qr_" . time() . "." . $file_extension;
                
                if (move_uploaded_file($_FILES["qr_image"]["tmp_name"], $qr_image_path)) {
                    // Deactivate all existing QR codes
                    $deactivate_qr = $pdo->prepare("UPDATE qr_codes SET status = 'inactive', deactivated_by = ?, deactivated_at = NOW() WHERE status = 'active'");
                    $deactivate_qr->execute([$admin_id]);
                    
                    // Insert new QR code
                    $insert_qr = $pdo->prepare("INSERT INTO qr_codes (qr_image_path, created_by, status, activated_by, activated_at) VALUES (?, ?, 'active', ?, NOW())");
                    if ($insert_qr->execute([$qr_image_path, $admin_id, $admin_id])) {
                        $message = "QR Code uploaded and activated successfully!";
                    } else {
                        $error = "Error uploading QR code.";
                    }
                } else {
                    $error = "Error uploading file.";
                }
            } else {
                $error = "Only JPG, JPEG, PNG, and GIF files are allowed.";
            }
        } else {
            $error = "Please select a valid QR code image.";
        }
    }
    
    // Approve Token Purchase (Bulk Action)
    if (isset($_POST['bulk_approve_tokens'])) {
        $token_ids = $_POST['token_ids'] ?? [];
        
        if (empty($token_ids)) {
            $error = "Please select at least one token to approve.";
        } else {
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($token_ids as $token_id) {
                // Get token details for notification
                $token_query = $pdo->prepare("SELECT member_id FROM member_tokens WHERE id = ?");
                $token_query->execute([$token_id]);
                $token_data = $token_query->fetch();
                
                if ($token_data) {
                    $update_token = $pdo->prepare("UPDATE member_tokens SET status = 'active' WHERE id = ?");
                    if ($update_token->execute([$token_id])) {
                        // Create notification
                        $notification_title = "Token Approved";
                        $notification_message = "Your token purchase has been approved and is now active.";
                        $notification_type = "token_approved";
                        
                        $notify_query = $pdo->prepare("INSERT INTO notifications (member_id, title, message, notification_type) VALUES (?, ?, ?, ?)");
                        $notify_query->execute([$token_data['member_id'], $notification_title, $notification_message, $notification_type]);
                        
                        $success_count++;
                    } else {
                        $failed_count++;
                    }
                } else {
                    $failed_count++;
                }
            }
            
            if ($success_count > 0) {
                $message = "Successfully approved $success_count tokens!" . ($failed_count > 0 ? " $failed_count tokens failed." : "");
            } else {
                $error = "Failed to approve any tokens.";
            }
        }
    }
    
    // Approve Single Token Purchase
    if (isset($_POST['approve_token'])) {
        $token_id = $_POST['token_id'];
        $member_id = $_POST['member_id'];
        
        $update_token = $pdo->prepare("UPDATE member_tokens SET status = 'active' WHERE id = ?");
        if ($update_token->execute([$token_id])) {
            // Create notification
            $notification_title = "Token Approved";
            $notification_message = "Your token purchase has been approved and is now active.";
            $notification_type = "token_approved";
            
            $notify_query = $pdo->prepare("INSERT INTO notifications (member_id, title, message, notification_type) VALUES (?, ?, ?, ?)");
            $notify_query->execute([$member_id, $notification_title, $notification_message, $notification_type]);
            
            $message = "Token approved successfully!";
        } else {
            $error = "Error approving token.";
        }
    }
    
    // Reject Token Purchase
    if (isset($_POST['reject_token'])) {
        $token_id = $_POST['token_id'];
        $member_id = $_POST['member_id'];
        
        $update_token = $pdo->prepare("UPDATE member_tokens SET status = 'sold' WHERE id = ?");
        if ($update_token->execute([$token_id])) {
            // Create notification
            $notification_title = "Token Rejected";
            $notification_message = "Your token purchase request has been rejected. Please contact support.";
            $notification_type = "token_purchase";
            
            $notify_query = $pdo->prepare("INSERT INTO notifications (member_id, title, message, notification_type) VALUES (?, ?, ?, ?)");
            $notify_query->execute([$member_id, $notification_title, $notification_message, $notification_type]);
            
            $message = "Token rejected successfully!";
        } else {
            $error = "Error rejecting token.";
        }
    }
    
    // Bulk Reject Tokens
    if (isset($_POST['bulk_reject_tokens'])) {
        $token_ids = $_POST['token_ids'] ?? [];
        
        if (empty($token_ids)) {
            $error = "Please select at least one token to reject.";
        } else {
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($token_ids as $token_id) {
                // Get token details for notification
                $token_query = $pdo->prepare("SELECT member_id FROM member_tokens WHERE id = ?");
                $token_query->execute([$token_id]);
                $token_data = $token_query->fetch();
                
                if ($token_data) {
                    $update_token = $pdo->prepare("UPDATE member_tokens SET status = 'sold' WHERE id = ?");
                    if ($update_token->execute([$token_id])) {
                        // Create notification
                        $notification_title = "Token Rejected";
                        $notification_message = "Your token purchase request has been rejected. Please contact support.";
                        $notification_type = "token_purchase";
                        
                        $notify_query = $pdo->prepare("INSERT INTO notifications (member_id, title, message, notification_type) VALUES (?, ?, ?, ?)");
                        $notify_query->execute([$token_data['member_id'], $notification_title, $notification_message, $notification_type]);
                        
                        $success_count++;
                    } else {
                        $failed_count++;
                    }
                } else {
                    $failed_count++;
                }
            }
            
            if ($success_count > 0) {
                $message = "Successfully rejected $success_count tokens!" . ($failed_count > 0 ? " $failed_count tokens failed." : "");
            } else {
                $error = "Failed to reject any tokens.";
            }
        }
    }
    
    // Approve Sell Request
    if (isset($_POST['approve_sell'])) {
        $request_id = $_POST['request_id'];
        $token_id = $_POST['token_id'];
        $member_id = $_POST['member_id'];
        $final_sell_price = $_POST['final_sell_price'];
        $admin_transaction_id = $_POST['admin_transaction_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Update sell request
            $update_request = $pdo->prepare("UPDATE token_sell_requests SET status = 'paid', admin_set_price = ?, admin_transaction_id = ?, approved_by = ?, approved_at = NOW(), payment_date = NOW() WHERE request_id = ?");
            $update_request->execute([$final_sell_price, $admin_transaction_id, $admin_id, $request_id]);
            
            // Update member token
            $update_token = $pdo->prepare("UPDATE member_tokens SET status = 'sold', sell_price = ?, sell_payment_id = ?, sell_payment_date = NOW() WHERE id = ?");
            $update_token->execute([$final_sell_price, $admin_transaction_id, $token_id]);
            
            // Create notification
            $notification_title = "Sell Payment Completed";
            $notification_message = "Your token sell request has been processed. Payment of ₹" . number_format($final_sell_price, 2) . " has been sent to your account.";
            $notification_type = "sell_paid";
            
            $notify_query = $pdo->prepare("INSERT INTO notifications (member_id, title, message, notification_type) VALUES (?, ?, ?, ?)");
            $notify_query->execute([$member_id, $notification_title, $notification_message, $notification_type]);
            
            $pdo->commit();
            $message = "Sell request approved and payment processed successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error processing sell request: " . $e->getMessage();
        }
    }
    
    // Bulk Approve Sell Requests
    if (isset($_POST['bulk_approve_sells'])) {
        $request_ids = $_POST['request_ids'] ?? [];
        
        if (empty($request_ids)) {
            $error = "Please select at least one sell request to approve.";
        } else {
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($request_ids as $request_id) {
                try {
                    $pdo->beginTransaction();
                    
                    // Get sell request details
                    $request_query = $pdo->prepare("
                        SELECT tsr.token_id, tsr.member_id, tsr.quantity, tt.sell_price 
                        FROM token_sell_requests tsr
                        JOIN member_tokens mt ON tsr.token_id = mt.id
                        JOIN token_types tt ON mt.token_type = tt.type_id
                        WHERE tsr.request_id = ?
                    ");
                    $request_query->execute([$request_id]);
                    $request_data = $request_query->fetch();
                    
                    if ($request_data) {
                        $final_sell_price = $request_data['sell_price'] * $request_data['quantity'];
                        $admin_transaction_id = "BULK_" . time() . "_" . $request_id;
                        
                        // Update sell request
                        $update_request = $pdo->prepare("UPDATE token_sell_requests SET status = 'paid', admin_set_price = ?, admin_transaction_id = ?, approved_by = ?, approved_at = NOW(), payment_date = NOW() WHERE request_id = ?");
                        $update_request->execute([$final_sell_price, $admin_transaction_id, $admin_id, $request_id]);
                        
                        // Update member token
                        $update_token = $pdo->prepare("UPDATE member_tokens SET status = 'sold', sell_price = ?, sell_payment_id = ?, sell_payment_date = NOW() WHERE id = ?");
                        $update_token->execute([$final_sell_price, $admin_transaction_id, $request_data['token_id']]);
                        
                        // Create notification
                        $notification_title = "Sell Payment Completed";
                        $notification_message = "Your token sell request has been processed. Payment of ₹" . number_format($final_sell_price, 2) . " has been sent to your account.";
                        $notification_type = "sell_paid";
                        
                        $notify_query = $pdo->prepare("INSERT INTO notifications (member_id, title, message, notification_type) VALUES (?, ?, ?, ?)");
                        $notify_query->execute([$request_data['member_id'], $notification_title, $notification_message, $notification_type]);
                        
                        $pdo->commit();
                        $success_count++;
                    } else {
                        $pdo->rollBack();
                        $failed_count++;
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $failed_count++;
                }
            }
            
            if ($success_count > 0) {
                $message = "Successfully processed $success_count sell requests!" . ($failed_count > 0 ? " $failed_count requests failed." : "");
            } else {
                $error = "Failed to process any sell requests.";
            }
        }
    }
    
    // Add/Edit Sell Plan
    if (isset($_POST['save_sell_plan'])) {
        $plan_id = $_POST['plan_id'] ?? null;
        $token_type = $_POST['token_type'];
        $minimum_days = $_POST['minimum_days'];
        $bonus_percentage = $_POST['bonus_percentage'];
        $fixed_bonus = $_POST['fixed_bonus'] ?: 0;
        
        if ($plan_id) {
            // Update existing plan
            $update_plan = $pdo->prepare("UPDATE token_sell_plans SET token_type = ?, minimum_days = ?, bonus_percentage = ?, fixed_bonus = ? WHERE plan_id = ?");
            if ($update_plan->execute([$token_type, $minimum_days, $bonus_percentage, $fixed_bonus, $plan_id])) {
                $message = "Sell plan updated successfully!";
            } else {
                $error = "Error updating sell plan.";
            }
        } else {
            // Insert new plan
            $insert_plan = $pdo->prepare("INSERT INTO token_sell_plans (token_type, minimum_days, bonus_percentage, fixed_bonus) VALUES (?, ?, ?, ?)");
            if ($insert_plan->execute([$token_type, $minimum_days, $bonus_percentage, $fixed_bonus])) {
                $message = "Sell plan added successfully!";
            } else {
                $error = "Error adding sell plan.";
            }
        }
    }
}

// Get dashboard statistics
$stats = [];

// Total Users
$users_query = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$stats['total_users'] = $users_query->fetch()['total'];

// Active Tokens
$active_tokens_query = $pdo->query("SELECT COUNT(*) as total FROM member_tokens WHERE status = 'active'");
$stats['active_tokens'] = $active_tokens_query->fetch()['total'];

// Pending Token Purchases
$pending_tokens_query = $pdo->query("SELECT COUNT(*) as total FROM member_tokens WHERE status = 'pending'");
$stats['pending_purchases'] = $pending_tokens_query->fetch()['total'];

// Pending Sell Requests
$pending_sells_query = $pdo->query("SELECT COUNT(*) as total FROM token_sell_requests WHERE status = 'pending'");
$stats['pending_sells'] = $pending_sells_query->fetch()['total'];

// Total Approved Sells
$approved_sells_query = $pdo->query("SELECT COUNT(*) as total FROM token_sell_requests WHERE status = 'paid'");
$stats['approved_sells'] = $approved_sells_query->fetch()['total'];

// Active QR Count
$active_qr_query = $pdo->query("SELECT COUNT(*) as total FROM qr_codes WHERE status = 'active'");
$stats['active_qr'] = $active_qr_query->fetch()['total'];

// Total Token Types
$token_types_query = $pdo->query("SELECT COUNT(*) as total FROM token_types");
$stats['token_types'] = $token_types_query->fetch()['total'];

// Total Revenue (Sum of all buy prices)
$revenue_query = $pdo->query("SELECT SUM(buy_price) as total FROM member_tokens WHERE status IN ('active', 'sold')");
$stats['total_revenue'] = $revenue_query->fetch()['total'] ?: 0;

// Today's Statistics
$today = date('Y-m-d');
$today_users_query = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = ?");
$today_users_query->execute([$today]);
$stats['today_users'] = $today_users_query->fetch()['total'];

$today_tokens_query = $pdo->prepare("SELECT COUNT(*) as total FROM member_tokens WHERE DATE(purchase_date) = ?");
$today_tokens_query->execute([$today]);
$stats['today_tokens'] = $today_tokens_query->fetch()['total'];

// Get active QR code
$qr_query = $pdo->query("SELECT qr_image_path FROM qr_codes WHERE status = 'active' LIMIT 1");
$active_qr = $qr_query->fetch();

// Get pending token purchases
$pending_tokens_query = $pdo->prepare("
    SELECT mt.id, mt.member_id, u.full_name, u.user_code, tt.token_name, mt.quantity, mt.buy_price, mt.transaction_id, mt.payment_screenshot, mt.purchase_date
    FROM member_tokens mt
    JOIN users u ON mt.member_id = u.user_id
    JOIN token_types tt ON mt.token_type = tt.type_id
    WHERE mt.status = 'pending'
    ORDER BY mt.purchase_date DESC
");
$pending_tokens_query->execute();
$pending_tokens = $pending_tokens_query->fetchAll();

// Get active tokens
$active_tokens_query = $pdo->query("
    SELECT mt.id, u.full_name, u.user_code, tt.token_name, mt.quantity, mt.buy_price, mt.purchase_date
    FROM member_tokens mt
    JOIN users u ON mt.member_id = u.user_id
    JOIN token_types tt ON mt.token_type = tt.type_id
    WHERE mt.status = 'active'
    ORDER BY mt.purchase_date DESC
    LIMIT 50
");
$active_tokens = $active_tokens_query->fetchAll();

// Get pending sell requests with holding days calculation
$pending_sells_query = $pdo->prepare("
    SELECT 
        tsr.request_id,
        tsr.member_id,
        tsr.token_id,
        tsr.quantity,
        tsr.bank_name,
        tsr.account_number,
        tsr.ifsc_code,
        tsr.phone_number,
        tsr.request_date,
        u.full_name,
        u.user_code,
        tt.token_name,
        mt.buy_price,
        mt.purchase_date,
        DATEDIFF(CURDATE(), mt.purchase_date) as holding_days,
        tt.sell_price as base_sell_price
    FROM token_sell_requests tsr
    JOIN users u ON tsr.member_id = u.user_id
    JOIN member_tokens mt ON tsr.token_id = mt.id
    JOIN token_types tt ON mt.token_type = tt.type_id
    WHERE tsr.status = 'pending'
    ORDER BY tsr.request_date DESC
");
$pending_sells_query->execute();
$pending_sells = $pending_sells_query->fetchAll();

// Calculate recommended sell price for each sell request
foreach ($pending_sells as &$sell) {
    $sell_plans_query = $pdo->prepare("
        SELECT bonus_percentage, fixed_bonus 
        FROM token_sell_plans 
        WHERE token_type = (SELECT token_type FROM member_tokens WHERE id = ?) 
        AND minimum_days <= ? 
        ORDER BY minimum_days DESC 
        LIMIT 1
    ");
    $sell_plans_query->execute([$sell['token_id'], $sell['holding_days']]);
    $sell_plan = $sell_plans_query->fetch();
    
    $base_price = $sell['base_sell_price'] * $sell['quantity'];
    $bonus_percentage = $sell_plan['bonus_percentage'] ?? 0;
    $fixed_bonus = $sell_plan['fixed_bonus'] ?? 0;
    
    $sell['recommended_price'] = $base_price + ($base_price * $bonus_percentage / 100) + $fixed_bonus;
}

// Get token sell plans
$sell_plans_query = $pdo->query("
    SELECT tsp.*, tt.token_name 
    FROM token_sell_plans tsp 
    JOIN token_types tt ON tsp.token_type = tt.type_id 
    ORDER BY tt.token_name, tsp.minimum_days
");
$sell_plans = $sell_plans_query->fetchAll();

// Get token types for forms
$token_types_query = $pdo->query("SELECT type_id, token_name, buy_price, sell_price FROM token_types");
$all_token_types = $token_types_query->fetchAll();

// Get latest notifications
$notifications_query = $pdo->query("
    SELECT n.*, u.full_name 
    FROM notifications n 
    JOIN users u ON n.member_id = u.user_id 
    ORDER BY n.created_at DESC 
    LIMIT 10
");
$latest_notifications = $notifications_query->fetchAll();

// Get recent activities - FIXED QUERY (Line 474)
$recent_activities_query = $pdo->query("
    (SELECT 'token_purchase' as type, u.full_name, mt.purchase_date as activity_date, tt.token_name, mt.quantity 
     FROM member_tokens mt 
     JOIN users u ON mt.member_id = u.user_id 
     JOIN token_types tt ON mt.token_type = tt.type_id 
     ORDER BY mt.purchase_date DESC LIMIT 5)
    UNION ALL
    (SELECT 'sell_request' as type, u.full_name, tsr.request_date as activity_date, tt.token_name, tsr.quantity 
     FROM token_sell_requests tsr 
     JOIN users u ON tsr.member_id = u.user_id 
     JOIN member_tokens mt ON tsr.token_id = mt.id 
     JOIN token_types tt ON mt.token_type = tt.type_id 
     ORDER BY tsr.request_date DESC LIMIT 5)
    ORDER BY activity_date DESC 
    LIMIT 8
");
$recent_activities = $recent_activities_query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Samuh Token System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #17a2b8;
            --purple: #9b59b6;
            --teal: #1abc9c;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: var(--secondary);
            color: white;
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: #bdc3c7;
            padding: 12px 20px;
            margin: 2px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 0;
            transition: margin-left 0.3s;
        }
        
        @media (min-width: 768px) {
            .main-content {
                margin-left: 250px;
            }
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .card-primary { border-left-color: var(--primary); }
        .card-success { border-left-color: var(--success); }
        .card-warning { border-left-color: var(--warning); }
        .card-danger { border-left-color: var(--danger); }
        .card-info { border-left-color: var(--info); }
        .card-purple { border-left-color: var(--purple); }
        .card-teal { border-left-color: var(--teal); }
        
        .table-responsive {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .table th {
            background-color: var(--secondary);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-active { background: #d1ecf1; color: #0c5460; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-paid { background: #d4edda; color: #155724; }
        
        .qr-container {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .qr-image {
            max-width: 200px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            background: white;
        }
        
        .notification-item {
            border-left: 3px solid var(--primary);
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .notification-item:hover {
            transform: translateX(5px);
        }
        
        .notification-item.unseen {
            border-left-color: var(--warning);
            background-color: #fff9e6;
        }
        
        .action-buttons .btn {
            margin: 0 2px;
        }
        
        .bank-details {
            font-size: 0.85rem;
        }
        
        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            margin-bottom: 0.5rem;
            background: white;
            border-radius: 5px;
        }
        
        .activity-item.sell {
            border-left-color: var(--success);
        }
        
        .activity-item.buy {
            border-left-color: var(--info);
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .bulk-actions {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--warning);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white">
                            <i class="fas fa-coins me-2"></i>Samuh Token
                        </h4>
                        <small class="text-white-50">Admin Panel</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#pending-purchases">
                                <i class="fas fa-shopping-cart"></i> Pending Purchases
                                <?php if ($stats['pending_purchases'] > 0): ?>
                                <span class="badge bg-danger float-end"><?php echo $stats['pending_purchases']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#sell-requests">
                                <i class="fas fa-exchange-alt"></i> Sell Requests
                                <?php if ($stats['pending_sells'] > 0): ?>
                                <span class="badge bg-warning float-end"><?php echo $stats['pending_sells']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#token-plans">
                                <i class="fas fa-chart-line"></i> Token Plans
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#qr-management">
                                <i class="fas fa-qrcode"></i> QR Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#users">
                                <i class="fas fa-users"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#reports">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Navigation -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                    <div class="container-fluid">
                        <button class="btn btn-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target=".sidebar">
                            <i class="fas fa-bars"></i>
                        </button>
                        
                        <div class="navbar-nav ms-auto">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                                </div>
                                <div>
                                    <small class="text-muted">Welcome,</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($admin_name); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Dashboard Header -->
                <div class="dashboard-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="display-5 fw-bold">Admin Dashboard</h1>
                                <p class="lead mb-0">Manage tokens, users, and system operations</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="text-white-50">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    <?php echo date('F j, Y'); ?>
                                </div>
                                <div class="text-white-50">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('g:i A'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container-fluid mb-5">
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row g-4 mb-5">
                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <div class="card stat-card card-primary h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h5 class="card-title text-muted mb-2">Total Users</h5>
                                            <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                                            <small class="text-success">
                                                <i class="fas fa-arrow-up"></i> +<?php echo $stats['today_users']; ?> today
                                            </small>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-users stat-icon text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <div class="card stat-card card-success h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h5 class="card-title text-muted mb-2">Active Tokens</h5>
                                            <h2 class="mb-0"><?php echo $stats['active_tokens']; ?></h2>
                                            <small class="text-success">
                                                <i class="fas fa-arrow-up"></i> +<?php echo $stats['today_tokens']; ?> today
                                            </small>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-coins stat-icon text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <div class="card stat-card card-warning h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h5 class="card-title text-muted mb-2">Pending Purchases</h5>
                                            <h2 class="mb-0"><?php echo $stats['pending_purchases']; ?></h2>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-shopping-cart stat-icon text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <div class="card stat-card card-danger h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h5 class="card-title text-muted mb-2">Pending Sells</h5>
                                            <h2 class="mb-0"><?php echo $stats['pending_sells']; ?></h2>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-exchange-alt stat-icon text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <div class="card stat-card card-info h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h5 class="card-title text-muted mb-2">Approved Sells</h5>
                                            <h2 class="mb-0"><?php echo $stats['approved_sells']; ?></h2>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-check-circle stat-icon text-info"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <div class="card stat-card card-purple h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h5 class="card-title text-muted mb-2">Total Revenue</h5>
                                            <h4 class="mb-0">₹<?php echo number_format($stats['total_revenue'], 2); ?></h4>
                                        </div>
                                        <div class="col-4 text-end">
                                            <i class="fas fa-rupee-sign stat-icon text-purple"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions & Recent Activities -->
                    <div class="row g-4 mb-5">
                        <!-- Quick Actions -->
                        <div class="col-lg-4">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="#pending-purchases" class="btn btn-warning btn-lg">
                                            <i class="fas fa-shopping-cart me-2"></i>
                                            Pending Purchases
                                            <span class="badge bg-dark ms-2"><?php echo $stats['pending_purchases']; ?></span>
                                        </a>
                                        <a href="#sell-requests" class="btn btn-danger btn-lg">
                                            <i class="fas fa-exchange-alt me-2"></i>
                                            Sell Requests
                                            <span class="badge bg-dark ms-2"><?php echo $stats['pending_sells']; ?></span>
                                        </a>
                                        <a href="#qr-management" class="btn btn-info btn-lg">
                                            <i class="fas fa-qrcode me-2"></i>
                                            Manage QR Code
                                        </a>
                                        <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                                            <i class="fas fa-plus me-2"></i>
                                            Add Sell Plan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activities -->
                        <div class="col-lg-8">
                            <div class="card h-100">
                                <div class="card-header bg-success text-white">
                                    <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (count($recent_activities) > 0): ?>
                                        <?php foreach($recent_activities as $activity): ?>
                                        <div class="activity-item <?php echo $activity['type'] == 'sell_request' ? 'sell' : 'buy'; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                                    <?php echo $activity['type'] == 'sell_request' ? ' requested to sell ' : ' purchased '; ?>
                                                    <span class="badge bg-info"><?php echo $activity['quantity']; ?> <?php echo htmlspecialchars($activity['token_name']); ?></span>
                                                    tokens
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M j, g:i A', strtotime($activity['activity_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle fa-2x mb-3"></i>
                                        <p>No recent activities</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Token Purchases -->
                    <div class="row mt-4" id="pending-purchases">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-clock me-2"></i>Pending Token Purchases
                                        <span class="badge bg-dark ms-2"><?php echo count($pending_tokens); ?></span>
                                    </h5>
                                    <?php if (count($pending_tokens) > 0): ?>
                                    <div class="bulk-actions">
                                        <form method="POST" class="d-inline" id="bulkApproveForm">
                                            <input type="hidden" name="token_ids[]" id="bulkApproveTokens">
                                            <button type="submit" name="bulk_approve_tokens" class="btn btn-success btn-sm me-2">
                                                <i class="fas fa-check-double me-1"></i>Approve Selected
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" id="bulkRejectForm">
                                            <input type="hidden" name="token_ids[]" id="bulkRejectTokens">
                                            <button type="submit" name="bulk_reject_tokens" class="btn btn-danger btn-sm">
                                                <i class="fas fa-times-circle me-1"></i>Reject Selected
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if (count($pending_tokens) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr>
                                                    <th width="50">
                                                        <input type="checkbox" id="selectAllTokens">
                                                    </th>
                                                    <th>Member</th>
                                                    <th>User Code</th>
                                                    <th>Token</th>
                                                    <th>Price</th>
                                                    <th>Txn ID</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($pending_tokens as $token): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="token-checkbox" name="token_ids[]" value="<?php echo $token['id']; ?>">
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                                <?php echo strtoupper(substr($token['full_name'], 0, 1)); ?>
                                                            </div>
                                                            <?php echo htmlspecialchars($token['full_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($token['user_code']); ?></code>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($token['token_name']); ?></span>
                                                    </td>
                                                    <td>₹<?php echo number_format($token['buy_price'], 2); ?></td>
                                                    <td>
                                                        <?php if ($token['transaction_id']): ?>
                                                            <code class="small"><?php echo htmlspecialchars($token['transaction_id']); ?></code>
                                                        <?php else: ?>
                                                            <span class="text-muted small">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($token['purchase_date'])); ?></td>
                                                    <td class="action-buttons">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="token_id" value="<?php echo $token['id']; ?>">
                                                            <input type="hidden" name="member_id" value="<?php echo $token['member_id']; ?>">
                                                            <button type="submit" name="approve_token" class="btn btn-success btn-sm" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="token_id" value="<?php echo $token['id']; ?>">
                                                            <input type="hidden" name="member_id" value="<?php echo $token['member_id']; ?>">
                                                            <button type="submit" name="reject_token" class="btn btn-danger btn-sm" title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                        <?php if ($token['payment_screenshot']): ?>
                                                        <a href="<?php echo htmlspecialchars($token['payment_screenshot']); ?>" target="_blank" class="btn btn-info btn-sm" title="View Screenshot">
                                                            <i class="fas fa-image"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                                        <p class="mb-0">No pending token purchases</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Sell Requests -->
                    <div class="row mt-4" id="sell-requests">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-exchange-alt me-2"></i>Pending Sell Requests
                                        <span class="badge bg-light text-dark ms-2"><?php echo count($pending_sells); ?></span>
                                    </h5>
                                    <?php if (count($pending_sells) > 0): ?>
                                    <form method="POST" class="d-inline" id="bulkApproveSellsForm">
                                        <input type="hidden" name="request_ids[]" id="bulkApproveRequests">
                                        <button type="submit" name="bulk_approve_sells" class="btn btn-success btn-sm">
                                            <i class="fas fa-check-double me-1"></i>Approve All with Base Price
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if (count($pending_sells) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Member</th>
                                                    <th>User Code</th>
                                                    <th>Token</th>
                                                    <th>Holding Days</th>
                                                    <th>Base Price</th>
                                                    <th>Recommended</th>
                                                    <th>Bank Details</th>
                                                    <th>Final Price</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($pending_sells as $sell): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                                <?php echo strtoupper(substr($sell['full_name'], 0, 1)); ?>
                                                            </div>
                                                            <?php echo htmlspecialchars($sell['full_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($sell['user_code']); ?></code>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($sell['token_name']); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $sell['holding_days'] > 30 ? 'success' : 'warning'; ?>">
                                                            <?php echo $sell['holding_days']; ?> days
                                                        </span>
                                                    </td>
                                                    <td>₹<?php echo number_format($sell['base_sell_price'], 2); ?></td>
                                                    <td>₹<?php echo number_format($sell['recommended_price'], 2); ?></td>
                                                    <td class="bank-details">
                                                        <strong><?php echo htmlspecialchars($sell['bank_name']); ?></strong><br>
                                                        A/C: <?php echo htmlspecialchars($sell['account_number']); ?><br>
                                                        IFSC: <?php echo htmlspecialchars($sell['ifsc_code']); ?>
                                                        <?php if ($sell['phone_number']): ?>
                                                            <br>Phone: <?php echo htmlspecialchars($sell['phone_number']); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="row g-2">
                                                            <input type="hidden" name="request_id" value="<?php echo $sell['request_id']; ?>">
                                                            <input type="hidden" name="token_id" value="<?php echo $sell['token_id']; ?>">
                                                            <input type="hidden" name="member_id" value="<?php echo $sell['member_id']; ?>">
                                                            <div class="col-12">
                                                                <input type="number" name="final_sell_price" class="form-control form-control-sm" 
                                                                       value="<?php echo number_format($sell['recommended_price'], 2); ?>" step="0.01" min="0" required>
                                                            </div>
                                                            <div class="col-12">
                                                                <input type="text" name="admin_transaction_id" class="form-control form-control-sm" 
                                                                       placeholder="Payment Txn ID" required>
                                                            </div>
                                                    </td>
                                                    <td>
                                                            <button type="submit" name="approve_sell" class="btn btn-success btn-sm">
                                                                <i class="fas fa-check me-1"></i>Pay
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                                        <p class="mb-0">No pending sell requests</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Management -->
                    <div class="row mt-4" id="qr-management">
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0"><i class="fas fa-qrcode me-2"></i>Active QR Code</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($active_qr): ?>
                                    <div class="qr-container mb-3">
                                        <img src="<?php echo htmlspecialchars($active_qr['qr_image_path']); ?>" alt="Active QR Code" class="qr-image img-fluid">
                                        <p class="mt-3 text-success">
                                            <i class="fas fa-check-circle me-1"></i>QR Code is active and visible to users
                                        </p>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-warning text-center">
                                        <i class="fas fa-exclamation-triangle me-2"></i>No active QR code found
                                        <p class="mt-2 mb-0">Users cannot make payments without an active QR code.</p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="qr_image" class="form-label">Upload New QR Code</label>
                                            <input type="file" class="form-control" id="qr_image" name="qr_image" accept="image/*" required>
                                            <div class="form-text">Supported formats: JPG, JPEG, PNG, GIF (Max 2MB)</div>
                                        </div>
                                        <button type="submit" name="upload_qr" class="btn btn-primary w-100">
                                            <i class="fas fa-upload me-2"></i>Upload & Activate QR
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Token Sell Plans -->
                        <div class="col-lg-6" id="token-plans">
                            <div class="card h-100">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-chart-line me-2"></i>Token Sell Plans
                                        <span class="badge bg-light text-dark ms-2"><?php echo count($sell_plans); ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (count($sell_plans) > 0): ?>
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Token Type</th>
                                                    <th>Min Days</th>
                                                    <th>Bonus %</th>
                                                    <th>Fixed Bonus</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($sell_plans as $plan): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($plan['token_name']); ?></td>
                                                    <td><?php echo $plan['minimum_days']; ?> days</td>
                                                    <td><?php echo $plan['bonus_percentage']; ?>%</td>
                                                    <td>₹<?php echo number_format($plan['fixed_bonus'], 2); ?></td>
                                                    <td>
                                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPlanModal" 
                                                                data-plan-id="<?php echo $plan['plan_id']; ?>"
                                                                data-token-type="<?php echo $plan['token_type']; ?>"
                                                                data-min-days="<?php echo $plan['minimum_days']; ?>"
                                                                data-bonus-percent="<?php echo $plan['bonus_percentage']; ?>"
                                                                data-fixed-bonus="<?php echo $plan['fixed_bonus']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle fa-2x mb-3 text-info"></i>
                                        <p class="mb-0">No sell plans found</p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-primary w-100 mt-3" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                                        <i class="fas fa-plus me-2"></i>Add New Plan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-bell me-2"></i>Recent Notifications
                                        <span class="badge bg-light text-dark ms-2"><?php echo count($latest_notifications); ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (count($latest_notifications) > 0): ?>
                                        <?php foreach($latest_notifications as $notification): ?>
                                        <div class="notification-item <?php echo $notification['seen'] == 'no' ? 'unseen' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>To: <?php echo htmlspecialchars($notification['full_name']); ?> | 
                                                        <i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-light text-dark ms-2"><?php echo $notification['notification_type']; ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-bell-slash fa-2x mb-3 text-muted"></i>
                                        <p class="mb-0">No notifications found</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Plan Modal -->
    <div class="modal fade" id="addPlanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Sell Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Token Type</label>
                            <select name="token_type" class="form-select" required>
                                <option value="">Select Token Type</option>
                                <?php foreach($all_token_types as $type): ?>
                                <option value="<?php echo $type['type_id']; ?>"><?php echo htmlspecialchars($type['token_name']); ?> (₹<?php echo $type['buy_price']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Minimum Days</label>
                            <input type="number" name="minimum_days" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bonus Percentage</label>
                            <input type="number" name="bonus_percentage" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fixed Bonus (₹)</label>
                            <input type="number" name="fixed_bonus" class="form-control" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_sell_plan" class="btn btn-primary">Save Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Plan Modal -->
    <div class="modal fade" id="editPlanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Sell Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="plan_id" id="edit_plan_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Token Type</label>
                            <select name="token_type" id="edit_token_type" class="form-select" required>
                                <option value="">Select Token Type</option>
                                <?php foreach($all_token_types as $type): ?>
                                <option value="<?php echo $type['type_id']; ?>"><?php echo htmlspecialchars($type['token_name']); ?> (₹<?php echo $type['buy_price']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Minimum Days</label>
                            <input type="number" name="minimum_days" id="edit_min_days" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bonus Percentage</label>
                            <input type="number" name="bonus_percentage" id="edit_bonus_percent" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fixed Bonus (₹)</label>
                            <input type="number" name="fixed_bonus" id="edit_fixed_bonus" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_sell_plan" class="btn btn-primary">Update Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Plan Modal Handler
        document.addEventListener('DOMContentLoaded', function() {
            const editPlanModal = document.getElementById('editPlanModal');
            if (editPlanModal) {
                editPlanModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    document.getElementById('edit_plan_id').value = button.getAttribute('data-plan-id');
                    document.getElementById('edit_token_type').value = button.getAttribute('data-token-type');
                    document.getElementById('edit_min_days').value = button.getAttribute('data-min-days');
                    document.getElementById('edit_bonus_percent').value = button.getAttribute('data-bonus-percent');
                    document.getElementById('edit_fixed_bonus').value = button.getAttribute('data-fixed-bonus');
                });
            }

            // Bulk Actions for Tokens
            const selectAllTokens = document.getElementById('selectAllTokens');
            const tokenCheckboxes = document.querySelectorAll('.token-checkbox');
            const bulkApproveForm = document.getElementById('bulkApproveForm');
            const bulkRejectForm = document.getElementById('bulkRejectForm');
            const bulkApproveSellsForm = document.getElementById('bulkApproveSellsForm');

            if (selectAllTokens) {
                selectAllTokens.addEventListener('change', function() {
                    tokenCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            // Bulk Approve Tokens
            if (bulkApproveForm) {
                bulkApproveForm.addEventListener('submit', function(e) {
                    const selectedTokens = Array.from(tokenCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
                    if (selectedTokens.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one token to approve.');
                        return;
                    }
                    document.getElementById('bulkApproveTokens').value = selectedTokens.join(',');
                });
            }

            // Bulk Reject Tokens
            if (bulkRejectForm) {
                bulkRejectForm.addEventListener('submit', function(e) {
                    const selectedTokens = Array.from(tokenCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
                    if (selectedTokens.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one token to reject.');
                        return;
                    }
                    document.getElementById('bulkRejectTokens').value = selectedTokens.join(',');
                });
            }

            // Bulk Approve Sell Requests
            if (bulkApproveSellsForm) {
                bulkApproveSellsForm.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to approve all sell requests with base prices? This action cannot be undone.')) {
                        e.preventDefault();
                        return;
                    }
                    // You can add request IDs here if you implement checkboxes for sell requests
                });
            }

            // Smooth scrolling for sidebar links
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.getAttribute('href').startsWith('#')) {
                        e.preventDefault();
                        const target = document.querySelector(this.getAttribute('href'));
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth' });
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>