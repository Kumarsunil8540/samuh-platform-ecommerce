<?php
session_start();
require_once 'token_config.php';

// Check if member is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header("Location: token_login.php");
    exit();
}

$member_id = $_SESSION['member_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];

// Get member's referral code and stats - UPDATED FOR CREDIT SYSTEM
try {
    $stmt = $pdo->prepare("SELECT referral_code, total_tokens FROM token_members WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $member_data = $stmt->fetch();
    
    if ($member_data) {
        $referral_code = $member_data['referral_code'];
    } else {
        $referral_code = "ERROR";
    }

    // Get referral statistics - UPDATED FOR NEW SYSTEM
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_referrals,
            SUM(CASE WHEN has_purchased = 'yes' THEN 1 ELSE 0 END) as active_referrals,
            (SELECT COUNT(*) FROM referral_credits WHERE referrer_id = ? AND status = 'active') as available_credits
        FROM referral_system 
        WHERE referrer_id = ?
    ");
    $stmt->execute([$member_id, $member_id]);
    $referral_stats = $stmt->fetch();
    
    $total_referrals = $referral_stats['total_referrals'] ?? 0;
    $active_referrals = $referral_stats['active_referrals'] ?? 0;
    $available_credits = $referral_stats['available_credits'] ?? 0;

    // Get referral settings from admin_settings
    $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_name = 'referrals_for_bonus'");
    $stmt->execute();
    $bonus_setting = $stmt->fetch();
    $credits_required = $bonus_setting ? intval($bonus_setting['setting_value']) : 5;

    // Get bonus token value
    $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_name = 'bonus_token_value'");
    $stmt->execute();
    $token_setting = $stmt->fetch();
    $bonus_token_value = $token_setting ? intval($token_setting['setting_value']) : 10;

    // Get recent referrals with purchase status
    $stmt = $pdo->prepare("
        SELECT 
            tm.username, 
            tm.full_name, 
            tm.join_date,
            rs.has_purchased,
            rs.credit_given,
            CASE 
                WHEN rs.has_purchased = 'yes' THEN 'Purchased Tokens'
                ELSE 'Registered'
            END as status_text
        FROM referral_system rs 
        JOIN token_members tm ON rs.referred_id = tm.member_id 
        WHERE rs.referrer_id = ? 
        ORDER BY rs.referral_date DESC 
        LIMIT 10
    ");
    $stmt->execute([$member_id]);
    $recent_referrals = $stmt->fetchAll();

    // Check if user has any bonus tokens
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as bonus_count 
        FROM referral_bonus 
        WHERE referrer_id = ? AND status = 'active'
    ");
    $stmt->execute([$member_id]);
    $bonus_count = $stmt->fetch()['bonus_count'] ?? 0;

} catch (PDOException $e) {
    error_log("Referral System Error: " . $e->getMessage());
    $referral_code = 'ERROR';
    $total_referrals = 0;
    $active_referrals = 0;
    $available_credits = 0;
    $credits_required = 5;
    $bonus_token_value = 10;
    $recent_referrals = [];
    $bonus_count = 0;
}

// Agar referral code NULL hai to generate karen
if (empty($referral_code) || $referral_code === 'ERROR') {
    try {
        $new_referral_code = generateReferralCode();
        $stmt = $pdo->prepare("UPDATE token_members SET referral_code = ? WHERE member_id = ?");
        $stmt->execute([$new_referral_code, $member_id]);
        $referral_code = $new_referral_code;
    } catch (PDOException $e) {
        error_log("Referral Code Generation Error: " . $e->getMessage());
        $referral_code = "SAMUH" . $member_id;
    }
}

/**
 * Generate unique referral code
 */
function generateReferralCode() {
    global $pdo;
    
    do {
        $code = 'SAMUH' . strtoupper(substr(uniqid(), -6));
        $stmt = $pdo->prepare("SELECT member_id FROM token_members WHERE referral_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    
    return $code;
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Program - Samuh Token System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --purple-color: #6f42c1;
            --teal-color: #20c997;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fb 0%, #e4e8f0 100%);
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles - Same as dashboard */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }

        .logo-section {
            text-align: center;
            padding: 30px 20px;
            background: rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .logo {
            font-size: 2.8rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #fff, #e3f2fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-text {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .user-info {
            text-align: center;
            padding: 20px;
            margin-bottom: 10px;
        }

        .user-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8rem;
            border: 3px solid rgba(255,255,255,0.2);
        }

        .nav-menu {
            list-style: none;
            padding: 0 15px;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255,255,255,0.2);
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .nav-link i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .logout-section {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 20px;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 25px;
        }

        .header {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            padding: 25px 30px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border-left: 5px solid var(--purple-color);
        }

        .welcome-text h1 {
            color: var(--dark-color);
            margin-bottom: 8px;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .welcome-text p {
            color: #6c757d;
            font-size: 1rem;
        }

        .referral-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        .referral-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .referral-code-box {
            background: linear-gradient(135deg, var(--purple-color), #5a32a3);
            color: white;
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .referral-code-box::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .referral-code {
            font-size: 2.8rem;
            font-weight: 800;
            letter-spacing: 4px;
            margin: 20px 0;
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 15px;
            border: 3px dashed rgba(255,255,255,0.4);
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Courier New', monospace;
            backdrop-filter: blur(10px);
        }

        .referral-code:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.02);
        }

        .share-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .share-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .share-btn.whatsapp { 
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
        }
        .share-btn.telegram { 
            background: linear-gradient(135deg, #0088cc, #006699);
            color: white;
        }
        .share-btn.copy { 
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }
        .share-btn:hover { 
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .progress-container {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            border: 2px solid #e9ecef;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .progress-bar {
            height: 12px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--teal-color));
            border-radius: 10px;
            transition: width 0.8s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .referral-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-top: 4px solid var(--purple-color);
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--purple-color);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--dark-color), var(--purple-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .how-to-section {
            background: linear-gradient(135deg, #fff, #f8f9fa);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            border: 2px solid #e9ecef;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            background: linear-gradient(135deg, var(--purple-color), var(--primary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .step {
            text-align: center;
            padding: 25px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .step:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .step-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--purple-color), var(--primary-color));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.8rem;
            font-weight: bold;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .step h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .step p {
            color: #6c757d;
            line-height: 1.5;
        }

        .recent-referrals {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .referral-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
            transition: all 0.3s ease;
        }

        .referral-item:hover {
            background: #f8f9fa;
            border-radius: 12px;
            transform: translateX(5px);
        }

        .referral-item:last-child {
            border-bottom: none;
        }

        .referral-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--purple-color), var(--primary-color));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .referral-details {
            flex: 1;
        }

        .referral-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark-color);
        }

        .referral-date {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .referral-status {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-purchased {
            background: #d4edda;
            color: #155724;
        }

        .status-registered {
            background: #fff3cd;
            color: #856404;
        }

        .no-referrals {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }

        .no-referrals i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .bonus-info {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 2px solid #ffd43b;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
        }

        .bonus-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #856404;
            margin: 10px 0;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 250px;
            }
            .main-content {
                margin-left: 250px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                display: none;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .referral-code {
                font-size: 2rem;
                padding: 15px;
            }

            .share-buttons {
                flex-direction: column;
                align-items: center;
            }

            .share-btn {
                width: 200px;
                justify-content: center;
            }

            .steps {
                grid-template-columns: 1fr;
            }
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark-color);
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }

            .sidebar.active {
                display: block;
            }
        }

        .credit-badge {
            background: linear-gradient(135deg, var(--purple-color), #5a32a3);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="logo-text">SAMUH TOKEN</div>
            </div>

            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="user-id">@<?php echo htmlspecialchars($username); ?></div>
            </div>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="token_member_dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="buy_tokens.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i> Buy Tokens
                    </a>
                </li>
                <li class="nav-item">
                    <a href="my_tokens.php" class="nav-link">
                        <i class="fas fa-wallet"></i> My Tokens
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sell_tokens.php" class="nav-link">
                        <i class="fas fa-money-bill-wave"></i> Sell Tokens
                    </a>
                </li>
                <li class="nav-item">
                    <a href="referral_system.php" class="nav-link active">
                        <i class="fas fa-user-friends"></i> Referral Program
                        <?php if ($available_credits > 0): ?>
                            <span class="credit-badge"><?php echo $available_credits; ?> Credits</span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="transaction_history.php" class="nav-link">
                        <i class="fas fa-history"></i> Transaction History
                    </a>
                </li>
            </ul>

            <div class="logout-section">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="welcome-text">
                    <h1>Referral Program 🎯</h1>
                    <p>Invite friends & earn ₹<?php echo $bonus_token_value; ?> tokens</p>
                </div>
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Referral Code Section -->
            <div class="referral-container">
                <div class="referral-code-box">
                    <h2 style="font-size: 1.8rem; margin-bottom: 10px;"><i class="fas fa-gift"></i> Your Unique Referral Code</h2>
                    <p style="font-size: 1.1rem; opacity: 0.9;">Share this code and earn when friends buy tokens</p>
                    <div class="referral-code" id="referralCode" title="Click to copy">
                        <?php echo htmlspecialchars($referral_code); ?>
                    </div>
                    
                    <div class="share-buttons">
                        <button class="share-btn whatsapp" onclick="shareOnWhatsApp()">
                            <i class="fab fa-whatsapp"></i> Share on WhatsApp
                        </button>
                        <button class="share-btn telegram" onclick="shareOnTelegram()">
                            <i class="fab fa-telegram"></i> Share on Telegram
                        </button>
                        <button class="share-btn copy" onclick="copyReferralCode()">
                            <i class="fas fa-copy"></i> Copy Referral Code
                        </button>
                    </div>
                </div>
            </div>

            <!-- Referral Statistics -->
            <div class="referral-stats">
                <div class="stat-box">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_referrals; ?></div>
                    <div class="stat-label">Total Referrals</div>
                </div>

                <div class="stat-box">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $active_referrals; ?></div>
                    <div class="stat-label">Active Referrals</div>
                </div>

                <div class="stat-box">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-number"><?php echo $available_credits; ?></div>
                    <div class="stat-label">Available Credits</div>
                </div>

                <div class="stat-box">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-number"><?php echo $bonus_count; ?></div>
                    <div class="stat-label">Bonus Tokens</div>
                </div>
            </div>

            <!-- Progress Section -->
            <div class="referral-container">
                <h3 class="section-title"><i class="fas fa-trophy"></i> Your Referral Progress</h3>
                <div class="progress-container">
                    <div class="progress-info">
                        <span>Credits: <?php echo $available_credits; ?> / <?php echo $credits_required; ?></span>
                        <span>Bonus: ₹<?php echo $bonus_token_value; ?> Token</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(100, ($available_credits / $credits_required) * 100); ?>%"></div>
                    </div>
                    <p style="text-align: center; margin-top: 15px; color: #6c757d; font-weight: 500;">
                        <?php 
                        $remaining = $credits_required - $available_credits;
                        if ($remaining > 0) {
                            echo "Need $remaining more credits to get ₹$bonus_token_value bonus token!";
                        } else {
                            echo "Congratulations! You've earned ₹$bonus_token_value bonus token!";
                        }
                        ?>
                    </p>
                </div>

                <!-- Bonus Information -->
                <div class="bonus-info">
                    <h4 style="color: #856404; margin-bottom: 10px;">
                        <i class="fas fa-info-circle"></i> How Credits Work
                    </h4>
                    <p style="color: #856404; margin-bottom: 10px;">
                        <strong>1 Credit = 1 Friend who bought tokens</strong>
                    </p>
                    <div class="bonus-amount">
                        <?php echo $credits_required; ?> Credits = ₹<?php echo $bonus_token_value; ?> Bonus Token
                    </div>
                    <p style="color: #856404; font-size: 0.9rem;">
                        Bonus tokens are locked for 2 months before you can sell them
                    </p>
                </div>
            </div>

            <!-- How It Works -->
            <div class="how-to-section">
                <h3 class="section-title"><i class="fas fa-info-circle"></i> How It Works</h3>
                <div class="steps">
                    <div class="step">
                        <div class="step-icon">1</div>
                        <h4>Share Your Code</h4>
                        <p>Share your referral code with friends using WhatsApp, Telegram, or any other platform</p>
                    </div>
                    <div class="step">
                        <div class="step-icon">2</div>
                        <h4>Friends Register & Buy</h4>
                        <p>Friends register using your code and purchase their first tokens</p>
                    </div>
                    <div class="step">
                        <div class="step-icon">3</div>
                        <h4>You Earn Credits</h4>
                        <p>Get 1 credit for each friend who buys tokens. Credits never expire!</p>
                    </div>
                    <div class="step">
                        <div class="step-icon">4</div>
                        <h4>Get Bonus Tokens</h4>
                        <p>Earn ₹<?php echo $bonus_token_value; ?> token for every <?php echo $credits_required; ?> credits</p>
                    </div>
                </div>
            </div>

            <!-- Recent Referrals -->
            <div class="recent-referrals">
                <h3 class="section-title"><i class="fas fa-users"></i> Your Referrals (<?php echo $total_referrals; ?>)</h3>
                <?php if (count($recent_referrals) > 0): ?>
                    <?php foreach($recent_referrals as $referral): ?>
                        <div class="referral-item">
                            <div class="referral-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="referral-details">
                                <div class="referral-name">
                                    <?php echo htmlspecialchars($referral['full_name']); ?> 
                                    (@<?php echo htmlspecialchars($referral['username']); ?>)
                                </div>
                                <div class="referral-date">
                                    Joined: <?php echo date('d M Y', strtotime($referral['join_date'])); ?>
                                </div>
                            </div>
                            <div class="referral-status <?php echo $referral['has_purchased'] === 'yes' ? 'status-purchased' : 'status-registered'; ?>">
                                <?php echo htmlspecialchars($referral['status_text']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-referrals">
                        <i class="fas fa-user-friends"></i>
                        <h4>No referrals yet</h4>
                        <p>Share your referral code to start earning credits!</p>
                        <p style="font-size: 0.9rem; margin-top: 10px;">You'll see your referrals here once they join</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function copyReferralCode() {
            const referralCode = document.getElementById('referralCode').textContent;
            navigator.clipboard.writeText(referralCode).then(function() {
                // Show custom notification
                showNotification('Referral code copied to clipboard!', 'success');
            }, function(err) {
                console.error('Could not copy text: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = referralCode;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showNotification('Referral code copied to clipboard!', 'success');
            });
        }

        function shareOnWhatsApp() {
            const referralCode = document.getElementById('referralCode').textContent;
            const message = `🚀 Join Samuh Token System using my referral code: ${referralCode}

💰 Earn money with token investments
🔐 Secure & transparent system
📈 Grow your wealth

Register now: ${window.location.origin}/token_register.php?ref=${referralCode}

Use my code for special benefits! 🎁`;
            const url = `https://wa.me/?text=${encodeURIComponent(message)}`;
            window.open(url, '_blank');
        }

        function shareOnTelegram() {
            const referralCode = document.getElementById('referralCode').textContent;
            const message = `🚀 Join Samuh Token System using my referral code: ${referralCode}

💰 Earn money with token investments
🔐 Secure & transparent system  
📈 Grow your wealth

Register now: ${window.location.origin}/token_register.php?ref=${referralCode}

Use my code for special benefits! 🎁`;
            const url = `https://t.me/share/url?url=${encodeURIComponent(window.location.origin + '/token_register.php?ref=' + referralCode)}&text=${encodeURIComponent(message)}`;
            window.open(url, '_blank');
        }

        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                z-index: 10000;
                font-weight: 600;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

            // Animate out after 3 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Auto-copy on click
        document.getElementById('referralCode').addEventListener('click', function() {
            copyReferralCode();
        });

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !mobileMenuBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Auto-hide sidebar on mobile when navigating
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    document.getElementById('sidebar').classList.remove('active');
                }
            });
        });

        // Add animation to stat boxes
        document.addEventListener('DOMContentLoaded', function() {
            const statBoxes = document.querySelectorAll('.stat-box');
            statBoxes.forEach((box, index) => {
                box.style.opacity = '0';
                box.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    box.style.transition = 'all 0.6s ease';
                    box.style.opacity = '1';
                    box.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>