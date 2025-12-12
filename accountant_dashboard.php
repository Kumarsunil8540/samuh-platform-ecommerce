<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Security check
if(!isset($_SESSION['login']) || $_SESSION['role'] !== 'accountant') {
    header("location: core_member_login.php?role=accountant");
    exit;
}

include("config.php");

// Get accountant and group data
$accountant_id = $_SESSION['user_id'];
$group_id = $_SESSION['group_id'];
$accountant_name = $_SESSION['user_name'];

try {
    // Get accountant details
    $accountant_sql = "SELECT * FROM accountants WHERE id = ?";
    $accountant_stmt = $conn->prepare($accountant_sql);
    $accountant_stmt->execute([$accountant_id]);
    $accountant = $accountant_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get group details
    $group_sql = "SELECT group_name FROM groups WHERE id = ?";
    $group_stmt = $conn->prepare($group_sql);
    $group_stmt->execute([$group_id]);
    $group = $group_stmt->fetch(PDO::FETCH_ASSOC);
    $group_name = $group['group_name'] ?? 'Unknown Group';
    
    // Get total members count
    $members_sql = "SELECT COUNT(*) as total_members FROM members WHERE group_id = ? AND is_active = 1";
    $members_stmt = $conn->prepare($members_sql);
    $members_stmt->execute([$group_id]);
    $members_data = $members_stmt->fetch(PDO::FETCH_ASSOC);
    $total_members = $members_data['total_members'] ?? 0;
    
    // Get payment statistics
    $payment_stats_sql = "SELECT 
                        COUNT(*) as total_payments,
                        SUM(amount_paid) as total_collected,
                        SUM(fine_amount) as total_fines,
                        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                        SUM(CASE WHEN payment_status = 'verified' THEN 1 ELSE 0 END) as verified_payments
                        FROM payment_request 
                        WHERE group_id = ?";
    $payment_stats_stmt = $conn->prepare($payment_stats_sql);
    $payment_stats_stmt->execute([$group_id]);
    $payment_stats = $payment_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_collected = $payment_stats['total_collected'] ?? 0;
    $total_fines = $payment_stats['total_fines'] ?? 0;
    $pending_payments = $payment_stats['pending_payments'] ?? 0;
    $verified_payments = $payment_stats['verified_payments'] ?? 0;
    
    // Get pending payments for verification
    $pending_payments_sql = "SELECT 
                            pr.id as payment_id,
                            m.full_name as member_name,
                            pr.amount_due,
                            pr.amount_paid,
                            pr.fine_amount,
                            pr.due_date,
                            pr.payment_date,
                            pr.payment_method,
                            pr.payment_status,
                            pr.marked_paid_at
                            FROM payment_request pr
                            JOIN members m ON pr.member_id = m.id
                            WHERE pr.group_id = ? AND pr.payment_status = 'pending'
                            ORDER BY pr.due_date ASC
                            LIMIT 20";
    $pending_payments_stmt = $conn->prepare($pending_payments_sql);
    $pending_payments_stmt->execute([$group_id]);
    $pending_payments_list = $pending_payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent verified payments
    $recent_payments_sql = "SELECT 
                            m.full_name as member_name,
                            pr.amount_paid,
                            pr.fine_amount,
                            pr.payment_method,
                            pr.payment_date,
                            pr.verified_at
                            FROM payment_request pr
                            JOIN members m ON pr.member_id = m.id
                            WHERE pr.group_id = ? AND pr.payment_status = 'verified'
                            ORDER BY pr.verified_at DESC
                            LIMIT 10";
    $recent_payments_stmt = $conn->prepare($recent_payments_sql);
    $recent_payments_stmt->execute([$group_id]);
    $recent_payments = $recent_payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Accountant Dashboard Error: " . $e->getMessage());
    // Set default values on error
    $group_name = 'Unknown Group';
    $total_members = 0;
    $total_collected = 0;
    $total_fines = 0;
    $pending_payments = 0;
    $verified_payments = 0;
    $pending_payments_list = [];
    $recent_payments = [];
}

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['payment_id'])) {
    $action = $_POST['action'];
    $payment_id = intval($_POST['payment_id']);
    
    if ($action === 'verify') {
        try {
            $verify_sql = "UPDATE payment_request 
                          SET payment_status = 'verified', 
                              verified_by_accountant = ?, 
                              verified_at = NOW()
                          WHERE id = ? AND group_id = ?";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->execute([$accountant_id, $payment_id, $group_id]);
            
            $_SESSION['flash_msg'] = "Payment verified successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash_msg'] = "Error verifying payment: " . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        try {
            $reject_sql = "UPDATE payment_request 
                          SET payment_status = 'rejected', 
                              verified_by_accountant = ?, 
                              verified_at = NOW(),
                              verification_notes = ?
                          WHERE id = ? AND group_id = ?";
            $reject_stmt = $conn->prepare($reject_sql);
            $reject_stmt->execute([$accountant_id, $_POST['reject_reason'] ?? 'Rejected by accountant', $payment_id, $group_id]);
            
            $_SESSION['flash_msg'] = "Payment rejected successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash_msg'] = "Error rejecting payment: " . $e->getMessage();
        }
    }
}

$flash_msg = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>अकाउंटेंट डैशबोर्ड - Accountant Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="accountant_dashboard.css">
</head>
<body>
<div class="app-shell">
    <!-- TOP NAVBAR -->
    <header class="topbar">
        <div class="topbar-left">
            <button id="sidebarToggle" class="btn-ghost">☰</button>
            <div class="brand">
                <div class="logo-circle hindi">अ</div>
                <div class="logo-circle english">A</div>
                <div class="brand-text">
                    <span class="brand-title hindi">अकाउंटेंट डैशबोर्ड</span>
                    <span class="brand-title english">Accountant Dashboard</span>
                    <span class="brand-sub hindi">ग्रुप: <?= htmlspecialchars($group_name) ?></span>
                    <span class="brand-sub english">Group: <?= htmlspecialchars($group_name) ?></span>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="accountant-info">
                <div class="accountant-name hindi">नमस्ते, <?= htmlspecialchars($accountant_name) ?></div>
                <div class="accountant-name english">Hello, <?= htmlspecialchars($accountant_name) ?></div>
                <div class="profile-pic">👤</div>
            </div>
            <a href="logout.php" class="btn-logout">
                <span class="hindi">लॉगआउट</span>
                <span class="english">Logout</span>
            </a>
        </div>
    </header>

    <!-- SIDEBAR + MAIN -->
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <nav class="nav">
                <ul>
                    <li class="nav-item">
                        <a href="qr_change_page.php" class="nav-link">
                            <span class="hindi">क्यूआर बदलें</span>
                            <span class="english">Change QR</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="make_payment.php" class="nav-link">
                            <span class="hindi">भुगतान सत्यापन</span>
                            <span class="english">Payment Verification</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="payment_reminder.php?group_id=<?= $group_id ?>" class="nav-link">
                            <span class="hindi">भुगतान रिमाइंडर</span>
                            <span class="english">Payment Reminders</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="qr_history.php" class="nav-link">
                            <span class="hindi">क्यूआर इतिहास</span>
                            <span class="english">QR History</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="set_payment_start.php" class="nav-link">
                            <span class="hindi">भुगतान तिथि सेट करें</span>
                            <span class="english">Set Payment Date</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="group_payment_history.php" class="nav-link">
                            <span class="hindi">भुगतान इतिहास</span>
                            <span class="english">Payment History</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="loan_approval.php" class="nav-link">
                            <span class="hindi">loan_approval </span>
                            <span class="english">Fine Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="verify_payment.php" class="nav-link">
                            <span class="hindi">loan verify payment</span>
                            <span class="english">loan verify payment</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="loan_summary.php" class="nav-link">
                            <span class="hindi">loan summary</span>
                            <span class="english">loan summary</span>
                        </a>
                    </li>
                    
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="hindi"><strong>ग्रुप आईडी:</strong> <?= htmlspecialchars($group_id) ?></div>
                <div class="english"><strong>Group ID:</strong> <?= htmlspecialchars($group_id) ?></div>
                <div class="hindi"><strong>कुल सदस्य:</strong> <?= $total_members ?></div>
                <div class="english"><strong>Total Members:</strong> <?= $total_members ?></div>
            </div>
        </aside>

        <main class="main">
            <?php if ($flash_msg): ?>
                <div class="flash-msg">
                    <span class="hindi"><?= htmlspecialchars($flash_msg) ?></span>
                    <span class="english"><?= htmlspecialchars($flash_msg) ?></span>
                </div>
            <?php endif; ?>

            <!-- SUMMARY CARDS -->
            <section class="cards-row">
                <div class="card summary">
                    <div class="card-title hindi">👥 कुल सदस्य</div>
                    <div class="card-title english">👥 Total Members</div>
                    <div class="card-value"><?= number_format($total_members) ?></div>
                    <div class="card-sub hindi">सक्रिय सदस्य</div>
                    <div class="card-sub english">Active Members</div>
                </div>

                <div class="card summary">
                    <div class="card-title hindi">💸 प्राप्त भुगतान</div>
                    <div class="card-title english">💸 Payments Received</div>
                    <div class="card-value">₹<?= number_format($total_collected) ?></div>
                    <div class="card-sub hindi">कुल जमा राशि</div>
                    <div class="card-sub english">Total Collected</div>
                </div>

                <div class="card summary">
                    <div class="card-title hindi">⏳ लंबित भुगतान</div>
                    <div class="card-title english">⏳ Pending Payments</div>
                    <div class="card-value"><?= number_format($pending_payments) ?></div>
                    <div class="card-sub hindi">सत्यापन के लिए</div>
                    <div class="card-sub english">Awaiting Verification</div>
                </div>

                <div class="card summary">
                    <div class="card-title hindi">💰 जुर्माना जमा</div>
                    <div class="card-title english">💰 Fines Collected</div>
                    <div class="card-value">₹<?= number_format($total_fines) ?></div>
                    <div class="card-sub hindi">कुल जुर्माना</div>
                    <div class="card-sub english">Total Fines</div>
                </div>
            </section>

            <!-- MAIN CONTENT GRID -->
            <section class="main-grid">
                <!-- Pending Payments Table -->
                <div class="panel panel-table">
                    <div class="panel-header">
                        <h3 class="hindi">⏳ लंबित भुगतान सत्यापन</h3>
                        <h3 class="english">⏳ Pending Payment Verification</h3>
                        <div class="panel-actions">
                            <input id="searchInput" type="search" placeholder="सदस्य खोजें... / Search member...">
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table id="paymentsTable" class="member-table">
                            <thead>
                                <tr>
                                    <th class="hindi">सदस्य नाम</th>
                                    <th class="english">Member Name</th>
                                    <th class="hindi">राशि</th>
                                    <th class="english">Amount</th>
                                    <th class="hindi">जुर्माना</th>
                                    <th class="english">Fine</th>
                                    <th class="hindi">भुगतान विधि</th>
                                    <th class="english">Payment Method</th>
                                    <th class="hindi">तारीख</th>
                                    <th class="english">Date</th>
                                    <th class="hindi">कार्य</th>
                                    <th class="english">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pending_payments_list)): ?>
                                    <?php foreach($pending_payments_list as $payment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($payment['member_name']) ?></td>
                                        <td>₹<?= number_format($payment['amount_paid']) ?></td>
                                        <td>₹<?= number_format($payment['fine_amount']) ?></td>
                                        <td>
                                            <?php 
                                                $method_text = [
                                                    'cash' => ['hindi' => 'नकद', 'english' => 'Cash'],
                                                    'online' => ['hindi' => 'ऑनलाइन', 'english' => 'Online'],
                                                    'qr' => ['hindi' => 'QR', 'english' => 'QR'],
                                                    'manual' => ['hindi' => 'मैनुअल', 'english' => 'Manual']
                                                ];
                                                $current_method = $method_text[$payment['payment_method']] ?? $method_text['manual'];
                                            ?>
                                            <span class="hindi"><?= $current_method['hindi'] ?></span>
                                            <span class="english"><?= $current_method['english'] ?></span>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                                        <td>
                                            <form method="post" class="action-form">
                                                <input type="hidden" name="payment_id" value="<?= $payment['payment_id'] ?>">
                                                <input type="hidden" name="action" value="verify">
                                                <button type="submit" class="btn-verify" 
                                                        onclick="return confirm('क्या आप इस भुगतान को सत्यापित करना चाहते हैं? / Do you want to verify this payment?')">
                                                    <span class="hindi">सत्यापित</span>
                                                    <span class="english">Verify</span>
                                                </button>
                                            </form>
                                            <button class="btn-reject" data-payment-id="<?= $payment['payment_id'] ?>">
                                                <span class="hindi">अस्वीकार</span>
                                                <span class="english">Reject</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="no-data">
                                            <span class="hindi">🎉 कोई लंबित भुगतान नहीं</span>
                                            <span class="english">🎉 No pending payments</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Side Panels -->
                <aside class="panel-sidebar">
                    <div class="panel-block notifications">
                        <h4 class="hindi">🔔 सूचनाएं</h4>
                        <h4 class="english">🔔 Notifications</h4>
                        <ul id="notifList">
                            <?php if ($pending_payments > 0): ?>
                                <li>
                                    <span class="hindi">⏳ <?= $pending_payments ?> भुगतान सत्यापन के लिए</span>
                                    <span class="english">⏳ <?= $pending_payments ?> payments awaiting verification</span>
                                </li>
                            <?php endif; ?>
                            <?php if ($verified_payments > 0): ?>
                                <li>
                                    <span class="hindi">✅ <?= $verified_payments ?> भुगतान सत्यापित</span>
                                    <span class="english">✅ <?= $verified_payments ?> payments verified</span>
                                </li>
                            <?php endif; ?>
                            <?php if ($total_fines > 0): ?>
                                <li>
                                    <span class="hindi">💰 ₹<?= number_format($total_fines) ?> जुर्माना जमा</span>
                                    <span class="english">💰 ₹<?= number_format($total_fines) ?> fines collected</span>
                                </li>
                            <?php endif; ?>
                            <?php if ($pending_payments === 0 && $verified_payments === 0): ?>
                                <li>
                                    <span class="hindi">📊 सभी भुगतान संसाधित</span>
                                    <span class="english">📊 All payments processed</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="panel-block recent-payments">
                        <h4 class="hindi">🆕 हाल के भुगतान</h4>
                        <h4 class="english">🆕 Recent Payments</h4>
                        <ul>
                            <?php foreach ($recent_payments as $payment): ?>
                                <li>
                                    <strong><?= htmlspecialchars($payment['member_name']) ?></strong><br>
                                    <small>₹<?= number_format($payment['amount_paid']) ?> 
                                    (₹<?= number_format($payment['fine_amount']) ?> जुर्माना / fine)</small><br>
                                    <small><?= date('d M', strtotime($payment['verified_at'])) ?></small>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($recent_payments)): ?>
                                <li>
                                    <span class="hindi">📝 अभी तक कोई भुगतान नहीं</span>
                                    <span class="english">📝 No payments yet</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </aside>
            </section>

            <!-- FOOTER -->
            <div class="small-footer">
                <span class="hindi">© <?= date('Y') ?> समूह प्लेटफॉर्म | वित्तीय प्रबंधन</span>
                <span class="english">© <?= date('Y') ?> Samuh Platform | Financial Management</span>
            </div>
        </main>
    </div>
</div>

<!-- Reject Payment Modal -->
<div id="rejectModal" class="modal" aria-hidden="true" style="display:none;">
  <div class="modal-content">
    <button id="modalClose" class="modal-close">✖</button>
    <h3 id="modalTitle">
        <span class="hindi">भुगतान अस्वीकार करें</span>
        <span class="english">Reject Payment</span>
    </h3>
    <form id="rejectForm" method="post">
      <input type="hidden" name="payment_id" id="reject_payment_id">
      <input type="hidden" name="action" value="reject">
      <label class="hindi">अस्वीकरण कारण</label>
      <label class="english">Rejection Reason</label>
      <textarea name="reject_reason" id="reject_reason" rows="3" placeholder="कारण दर्ज करें... / Enter reason..." required style="width:100%"></textarea>
      <div style="margin-top:12px; display:flex; gap:8px;">
        <button type="button" id="cancelReject" class="btn-cancel">
            <span class="hindi">रद्द करें</span>
            <span class="english">Cancel</span>
        </button>
        <button type="submit" class="btn-decline">
            <span class="hindi">अस्वीकार करें</span>
            <span class="english">Confirm Reject</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script src="accountant_dashboard.js"></script>
</body>
</html>