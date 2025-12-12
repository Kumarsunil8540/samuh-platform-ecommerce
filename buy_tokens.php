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

$error = '';
$success = '';
$membership_plans = [];
$qr_codes = [];
$member_wallet = 0;
$referral_code = '';

// Get member wallet balance and referral code
try {
    $stmt = $pdo->prepare("SELECT wallet_balance, referral_code FROM token_members WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $member_data = $stmt->fetch();
    
    if ($member_data) {
        $member_wallet = $member_data['wallet_balance'];
        $referral_code = $member_data['referral_code'];
    }
} catch (PDOException $e) {
    error_log("Member Data Error: " . $e->getMessage());
}

// Get available membership plans
try {
    $stmt = $pdo->prepare("SELECT * FROM token_types WHERE status = 'active' ORDER BY token_price ASC");
    $stmt->execute();
    $membership_plans = $stmt->fetchAll();
    
    if (empty($membership_plans)) {
        $error = "No membership plans available at the moment.";
    }
} catch (PDOException $e) {
    error_log("Membership Plans Error: " . $e->getMessage());
    $error = "Error loading membership plans. Please try again.";
}

// Get UPI QR codes
try {
    $stmt = $pdo->prepare("SELECT * FROM token_qr_codes WHERE status = 'active' ORDER BY qr_id ASC");
    $stmt->execute();
    $qr_codes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("QR Codes Error: " . $e->getMessage());
}

// Handle membership purchase request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_membership'])) {
    $membership_type_id = intval($_POST['membership_type']);
    $quantity = intval($_POST['quantity']);
    $upi_reference = trim($_POST['upi_reference'] ?? '');
    
    // Validate purchase
    if ($quantity < 1) {
        $error = "Please select at least 1 token.";
    } elseif (empty($upi_reference)) {
        $error = "Please enter UPI transaction reference ID.";
    } else {
        $purchase_result = processMembershipPurchaseRequest($member_id, $membership_type_id, $quantity, $upi_reference);
        
        if ($purchase_result['success']) {
            $success = $purchase_result['message'];
        } else {
            $error = $purchase_result['error'];
        }
    }
}

/**
 * Process membership purchase request
 */
function processMembershipPurchaseRequest($member_id, $membership_type_id, $quantity, $upi_reference) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get membership details
        $stmt = $pdo->prepare("SELECT * FROM token_types WHERE token_id = ? AND status = 'active'");
        $stmt->execute([$membership_type_id]);
        $membership = $stmt->fetch();
        
        if (!$membership) {
            return ['success' => false, 'error' => 'Invalid token selected.'];
        }
        
        $total_amount = $membership['token_price'] * $quantity;
        
        // Calculate lock period (2 months from now) and buyback price (20% profit)
        $lock_until = date('Y-m-d H:i:s', strtotime('+2 months'));
        $buyback_price = ceil($membership['token_price'] * 1.20); // 20% profit
        
        // Insert token purchase record
        $stmt = $pdo->prepare("INSERT INTO member_tokens (member_id, token_type, quantity, purchase_price, lock_until, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$member_id, $membership_type_id, $quantity, $membership['token_price'], $lock_until]);
        
        $purchase_id = $pdo->lastInsertId();
        
        // Add transaction record
        $stmt = $pdo->prepare("INSERT INTO token_transactions (member_id, transaction_type, amount, description, status, reference_id) VALUES (?, 'purchase', ?, ?, 'completed', ?)");
        $description = "Purchased {$quantity} {$membership['token_name']} token(s) at ₹{$membership['token_price']} each. Buyback Price: ₹{$buyback_price} after 2 months";
        $stmt->execute([$member_id, $total_amount, $description, $upi_reference]);
        
        // Update member's total tokens
        $stmt = $pdo->prepare("UPDATE token_members SET total_tokens = total_tokens + ? WHERE member_id = ?");
        $stmt->execute([$quantity, $member_id]);
        
        // Add notification
        $stmt = $pdo->prepare("INSERT INTO token_notifications (member_id, title, message, notification_type) VALUES (?, 'Token Purchase Successful', ?, 'purchase')");
        $notification_msg = "You successfully purchased {$quantity} {$membership['token_name']} token(s). Total: ₹{$total_amount}. You can sell back after 2 months for ₹{$buyback_price} each.";
        $stmt->execute([$member_id, $notification_msg]);
        
        // Check for referral bonus
        checkAndGiveReferralBonus($member_id);
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => "Token purchase successful! You bought {$quantity} token(s) for ₹{$total_amount}. After 2 months, you can sell back for ₹{$buyback_price} each.",
            'purchase_id' => $purchase_id
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Token Purchase Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Token purchase failed. Please try again.'];
    }
}

/**
 * Check and give referral bonus
 */
function checkAndGiveReferralBonus($member_id) {
    global $pdo;
    
    try {
        // Check if this member was referred by someone
        $stmt = $pdo->prepare("SELECT referred_by FROM token_members WHERE member_id = ? AND referred_by IS NOT NULL");
        $stmt->execute([$member_id]);
        $referral_data = $stmt->fetch();
        
        if ($referral_data) {
            $referrer_id = $referral_data['referred_by'];
            
            // Add referral credit
            $stmt = $pdo->prepare("INSERT INTO referral_credits (referrer_id, referred_id, credit_amount) VALUES (?, ?, 1)");
            $stmt->execute([$referrer_id, $member_id]);
            
            // Check if referrer has 5 credits
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_credits FROM referral_credits WHERE referrer_id = ? AND status = 'active'");
            $stmt->execute([$referrer_id]);
            $credits_data = $stmt->fetch();
            
            if ($credits_data['total_credits'] >= 5) {
                // Give free token bonus
                $stmt = $pdo->prepare("INSERT INTO referral_bonus (referrer_id, bonus_tokens) VALUES (?, 1)");
                $stmt->execute([$referrer_id]);
                
                // Mark credits as used
                $stmt = $pdo->prepare("UPDATE referral_credits SET status = 'used' WHERE referrer_id = ? AND status = 'active' LIMIT 5");
                $stmt->execute([$referrer_id]);
                
                // Add notification
                $stmt = $pdo->prepare("INSERT INTO token_notifications (member_id, title, message, notification_type) VALUES (?, 'Referral Bonus Earned', 'You earned 1 free token for referring 5 members!', 'bonus')");
                $stmt->execute([$referrer_id]);
            }
        }
    } catch (PDOException $e) {
        error_log("Referral Bonus Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>टोकन खरीदें - Buy Tokens</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Previous CSS remains same, just updating colors and messages */
        
        .profit-section {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .profit-badge {
            background: #ffd700;
            color: #856404;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 1.2rem;
            margin: 15px 0;
            display: inline-block;
        }
        
        .benefit-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: 15px 0;
            border-left: 5px solid #28a745;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>समूह टोकन खरीदें 🎫</h1>
            <p>टोकन खरीदें, समूह का सदस्य बनें, और बाद में लाभ के साथ बेचें</p>
        </div>

        <!-- Profit Section -->
        <div class="profit-section">
            <h2><i class="fas fa-chart-line"></i> निश्चित लाभ का अवसर!</h2>
            <div class="profit-badge">
                20% गारंटीड प्रॉफिट
            </div>
            <p>आज 10₹ में खरीदें → 2 महीने बाद 12₹ में बेचें</p>
        </div>

        <!-- Benefits Section -->
        <div class="benefit-card">
            <h3><i class="fas fa-check-circle" style="color: #28a745;"></i> आपको क्या मिलेगा?</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                <div style="text-align: center;">
                    <i class="fas fa-rupee-sign" style="font-size: 2rem; color: #28a745;"></i>
                    <h4>20% प्रॉफिट</h4>
                    <p>हर टोकन पर गारंटीड लाभ</p>
                </div>
                <div style="text-align: center;">
                    <i class="fas fa-users" style="font-size: 2rem; color: #667eea;"></i>
                    <h4>समूह सदस्यता</h4>
                    <p>समूह के विशेष सदस्य बनें</p>
                </div>
                <div style="text-align: center;">
                    <i class="fas fa-gift" style="font-size: 2rem; color: #ff6b6b;"></i>
                    <h4>फ्री टोकन</h4>
                    <p>5 दोस्तों को जोड़ें, 1 फ्री टोकन पाएं</p>
                </div>
            </div>
        </div>

        <!-- Referral Section -->
        <div class="referral-section">
            <h3><i class="fas fa-share-alt"></i> दोस्तों को आमंत्रित करें</h3>
            <p>अपने 5 दोस्तों को जोड़ें और 1 फ्री टोकन पाएं!</p>
            <div class="referral-code" id="referralCode">
                <?php echo $referral_code; ?>
            </div>
            <button class="copy-btn" onclick="copyReferralCode()">
                <i class="fas fa-copy"></i> कोड कॉपी करें
            </button>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Available Token Plans -->
        <div class="plans-grid">
            <?php foreach($membership_plans as $plan): 
                $buyback_price = ceil($plan['token_price'] * 1.20);
                $profit_per_token = $buyback_price - $plan['token_price'];
                $profit_percentage = round(($profit_per_token / $plan['token_price']) * 100);
            ?>
                <div class="plan-card">
                    <div class="plan-badge" style="background: #28a745;"><?php echo $profit_percentage; ?>% प्रॉफिट</div>
                    <div class="plan-icon">
                        💰
                    </div>
                    <div class="plan-name"><?php echo htmlspecialchars($plan['token_name']); ?> टोकन</div>
                    <div class="plan-price">₹<?php echo htmlspecialchars($plan['token_price']); ?></div>
                    
                    <div style="background: #e8f5e8; padding: 15px; border-radius: 10px; margin: 15px 0;">
                        <div style="font-size: 0.9rem; color: #666;">बायबैक प्राइस:</div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #28a745;">₹<?php echo $buyback_price; ?></div>
                        <div style="font-size: 0.9rem; color: #28a745;">
                            <i class="fas fa-arrow-up"></i> ₹<?php echo $profit_per_token; ?> प्रति टोकन
                        </div>
                    </div>
                    
                    <ul class="plan-features">
                        <li>खरीद मूल्य: ₹<?php echo $plan['token_price']; ?></li>
                        <li>बेचने का मूल्य: ₹<?php echo $buyback_price; ?></li>
                        <li>लाभ: ₹<?php echo $profit_per_token; ?> (<?php echo $profit_percentage; ?>%)</li>
                        <li>लॉक अवधि: 2 महीने</li>
                        <li>कंपनी द्वारा बायबैक गारंटी</li>
                    </ul>
                    
                    <button class="btn-membership" onclick="selectToken(<?php echo $plan['token_id']; ?>, '<?php echo $plan['token_name']; ?>', <?php echo $plan['token_price']; ?>, <?php echo $buyback_price; ?>)">
                        <i class="fas fa-shopping-cart"></i> टोकन खरीदें
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- How It Works -->
        <div class="purchase-form">
            <h3 style="margin-bottom: 20px; color: var(--dark-color); text-align: center;">
                <i class="fas fa-play-circle"></i> यह कैसे काम करता है?
            </h3>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h4>टोकन खरीदें</h4>
                    <p>10₹ में टोकन खरीदें</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h4>2 महीने प्रतीक्षा</h4>
                    <p>टोकन 2 महीने लॉक रहेगा</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h4>लाभ के साथ बेचें</h4>
                    <p>12₹ में कंपनी को वापस बेचें</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h4>भुगतान प्राप्त करें</h4>
                    <p>अपने UPI में पैसा प्राप्त करें</p>
                </div>
            </div>
        </div>

        <!-- Purchase Form -->
        <div class="purchase-form" id="purchaseForm" style="display: none;">
            <h3 style="margin-bottom: 20px; color: var(--dark-color); text-align: center;">
                <i class="fas fa-shopping-cart"></i> टोकन खरीदें
            </h3>
            
            <form method="POST" action="">
                <input type="hidden" name="membership_type" id="selectedTokenType">
                
                <div class="form-group">
                    <label class="form-label">चुना गया टोकन</label>
                    <div id="selectedTokenInfo" style="background: #f8f9fa; padding: 20px; border-radius: 15px; font-weight: 600; text-align: center; font-size: 1.2rem;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">टोकन संख्या</label>
                    <div class="quantity-controls" style="justify-content: center;">
                        <button type="button" class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                        <div class="quantity-display" id="quantityDisplay">1</div>
                        <button type="button" class="quantity-btn" onclick="changeQuantity(1)">+</button>
                    </div>
                    <input type="hidden" name="quantity" id="quantityInput" value="1">
                </div>

                <div class="amount-display">
                    <div style="font-size: 1.2rem; margin-bottom: 10px;">कुल भुगतान राशि</div>
                    <div class="total-amount" id="totalAmount">₹0</div>
                    <div id="profitInfo" style="font-size: 1rem; color: #28a745; margin-top: 10px; font-weight: 600;"></div>
                </div>

                <!-- UPI QR Code Section -->
                <div class="qr-section">
                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">
                        <i class="fas fa-qrcode"></i> भुगतान करने के लिए QR कोड स्कैन करें
                    </h4>
                    
                    <?php if (!empty($qr_codes)): ?>
                        <div class="qr-code">
                            <img src="<?php echo htmlspecialchars($qr_codes[0]['image_path']); ?>" 
                                 alt="UPI QR Code">
                        </div>
                        <div class="upi-details">
                            <div class="upi-id">samuh@upi</div>
                            <div>किसी भी UPI ऐप से भुगतान करें</div>
                        </div>
                    <?php else: ?>
                        <div class="qr-code">
                            <img src="https://via.placeholder.com/250x250/28a745/ffffff?text=UPI+QR" 
                                 alt="QR Code Placeholder">
                        </div>
                        <div class="upi-details">
                            <div class="upi-id">samuh@upi</div>
                            <div>भुगतान के लिए इस UPI आईडी का उपयोग करें</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="upi_reference" class="form-label">
                        <i class="fas fa-receipt"></i> UPI लेनदेन आईडी *
                    </label>
                    <input type="text" id="upi_reference" name="upi_reference" class="form-input" 
                           placeholder="अपना UPI लेनदेन आईडी दर्ज करें" 
                           required>
                    <small style="color: #666; font-size: 0.9rem; display: block; margin-top: 8px;">
                        यह आईडी आपके UPI ऐप के लेनदेन इतिहास में मिलेगी
                    </small>
                </div>

                <button type="submit" name="purchase_membership" class="btn-membership" style="margin-top: 20px;">
                    <i class="fas fa-paper-plane"></i> टोकन खरीदें
                </button>
            </form>
        </div>

        <!-- Guarantee Section -->
        <div class="disclaimer" style="border-left-color: #28a745;">
            <h3 style="color: #28a745;">हमारी गारंटी</h3>
            <p>✅ हर टोकन के लिए 20% लाभ गारंटीड</p>
            <p>✅ 2 महीने बाद कंपनी टोकन वापस खरीदेगी</p>
            <p>✅ सीधे आपके UPI में भुगतान</p>
            <p>✅ कोई जोखिम नहीं, पूरी तरह सुरक्षित</p>
        </div>
    </div>

    <script>
        let selectedTokenPrice = 0;
        let selectedTokenName = '';
        let selectedBuybackPrice = 0;
        let currentQuantity = 1;

        function selectToken(tokenId, tokenName, tokenPrice, buybackPrice) {
            selectedTokenPrice = tokenPrice;
            selectedTokenName = tokenName;
            selectedBuybackPrice = buybackPrice;
            
            document.getElementById('selectedTokenType').value = tokenId;
            document.getElementById('selectedTokenInfo').innerHTML = 
                `${tokenName} टोकन - खरीद: ₹${tokenPrice}, बेचें: ₹${buybackPrice}`;
            
            updateTotalAmount();
            
            // Show purchase form
            document.getElementById('purchaseForm').style.display = 'block';
            
            // Scroll to form
            document.getElementById('purchaseForm').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        function changeQuantity(change) {
            currentQuantity += change;
            if (currentQuantity < 1) currentQuantity = 1;
            if (currentQuantity > 100) currentQuantity = 100;
            
            document.getElementById('quantityDisplay').textContent = currentQuantity;
            document.getElementById('quantityInput').value = currentQuantity;
            updateTotalAmount();
        }

        function updateTotalAmount() {
            const totalCost = selectedTokenPrice * currentQuantity;
            const totalBuyback = selectedBuybackPrice * currentQuantity;
            const totalProfit = totalBuyback - totalCost;
            const profitPercentage = Math.round((totalProfit / totalCost) * 100);
            
            document.getElementById('totalAmount').textContent = `₹${totalCost}`;
            
            // Update profit info
            const profitInfo = document.getElementById('profitInfo');
            if (profitInfo) {
                profitInfo.innerHTML = 
                    `बेचने पर मिलेगा: ₹${totalBuyback} | लाभ: ₹${totalProfit} (${profitPercentage}%)`;
            }
        }

        function copyReferralCode() {
            const referralCode = document.getElementById('referralCode').textContent;
            navigator.clipboard.writeText(referralCode).then(function() {
                alert('रेफरल कोड कॉपी हो गया: ' + referralCode);
            });
        }

        // Auto-select first token if only one available
        document.addEventListener('DOMContentLoaded', function() {
            const tokenCards = document.querySelectorAll('.plan-card');
            if (tokenCards.length === 1) {
                const token = tokenCards[0];
                const tokenId = token.querySelector('.btn-membership').getAttribute('onclick').match(/\d+/)[0];
                const tokenName = token.querySelector('.plan-name').textContent.split(' ')[0];
                const tokenPrice = parseInt(token.querySelector('.plan-price').textContent.replace('₹', ''));
                const buybackPrice = parseInt(token.querySelector('.plan-features li:nth-child(2)').textContent.split('₹')[1]);
                selectToken(tokenId, tokenName, tokenPrice, buybackPrice);
            }
        });
    </script>
</body>
</html>