<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Security check
if(!isset($_SESSION['login']) || $_SESSION['role'] !== 'leader') {
    header("location: core_member_login.php?role=leader");
    exit;
}

include("config.php");

// Get leader and group data
$leader_id = $_SESSION['user_id'];
$group_id = $_SESSION['group_id'];
$leader_name = $_SESSION['user_name'];

try {
    // Get group details
    $group_sql = "SELECT group_name, expected_amount, tenure_months, payment_cycle, payment_start_date FROM groups WHERE id = ?";
    $group_stmt = $conn->prepare($group_sql);
    $group_stmt->execute([$group_id]);
    $group_data = $group_stmt->fetch(PDO::FETCH_ASSOC);
    
    $group_name = $group_data['group_name'] ?? 'Unknown Group';
    $expected_amount = $group_data['expected_amount'] ?? 0;
    $payment_cycle = $group_data['payment_cycle'] ?? 'monthly';
    $payment_start_date = $group_data['payment_start_date'] ?? null;
    
    // Get total members count
    $members_sql = "SELECT COUNT(*) as total_members FROM members WHERE group_id = ? AND is_active = 1";
    $members_stmt = $conn->prepare($members_sql);
    $members_stmt->execute([$group_id]);
    $members_data = $members_stmt->fetch(PDO::FETCH_ASSOC);
    $total_members = $members_data['total_members'] ?? 0;
    
    // Get payment statistics - FIXED QUERY
    $payment_sql = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(payment_amount) as total_collected,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments
                    FROM payment_request 
                    WHERE group_id = ?";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->execute([$group_id]);
    $payment_data = $payment_stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_collected = $payment_data['total_collected'] ?? 0;
    $pending_payments = $payment_data['pending_payments'] ?? 0;
    
    // Get late fees statistics - REAL DATA
    $late_fees_sql = "SELECT SUM(fine_amount) as total_fines FROM late_fees WHERE group_id = ? AND is_paid = 1";
    $late_fees_stmt = $conn->prepare($late_fees_sql);
    $late_fees_stmt->execute([$group_id]);
    $late_fees_data = $late_fees_stmt->fetch(PDO::FETCH_ASSOC);
    $total_fines = $late_fees_data['total_fines'] ?? 0;
    
    // Get recent payment records - FIXED QUERY with correct columns
    $recent_payments_sql = "SELECT 
                            m.full_name as member_name,
                            pr.payment_amount,
                            pr.payment_date,
                            pr.payment_status
                            FROM payment_request pr
                            JOIN members m ON pr.member_id = m.id
                            WHERE pr.group_id = ?
                            ORDER BY pr.payment_date DESC, pr.created_at DESC
                            LIMIT 8";
    $recent_payments_stmt = $conn->prepare($recent_payments_sql);
    $recent_payments_stmt->execute([$group_id]);
    $recent_payments = $recent_payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get notifications - REAL DATA only
    $notifications_sql = "SELECT title, message, created_at 
                         FROM notifications 
                         WHERE (user_id = ? AND user_type = 'leader') OR (group_id = ? AND user_type = 'all')
                         ORDER BY created_at DESC 
                         LIMIT 4";
    $notifications_stmt = $conn->prepare($notifications_sql);
    $notifications_stmt->execute([$leader_id, $group_id]);
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    // Set default values on error
    $group_name = 'Unknown Group';
    $total_members = 0;
    $total_collected = 0;
    $total_fines = 0;
    $pending_payments = 0;
    $recent_payments = [];
    $notifications = [];
}

// Calculate next collection date based on payment cycle
$next_collection = null;
if ($payment_start_date) {
    if ($payment_cycle === 'weekly') {
        $next_collection = date('Y-m-d', strtotime($payment_start_date . ' +7 days'));
    } else {
        $next_collection = date('Y-m-01', strtotime('+1 month'));
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>लीडर डैशबोर्ड - Leader Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="leader_dashboard.css">
</head>
<body>
  <div class="app-shell">
    <!-- TOP NAVBAR -->
    <header class="topbar">
      <div class="topbar-left">
        <button id="sidebarToggle" class="btn-ghost" aria-label="Toggle menu">☰</button>
        <div class="brand">
          <div class="logo-circle hindi">लि</div>
          <div class="logo-circle english">L</div>
          <div class="brand-text">
            <span class="brand-title hindi">लीडर डैशबोर्ड</span>
            <span class="brand-title english">Leader Dashboard</span>
            <span class="brand-sub hindi">ग्रुप: <?= htmlspecialchars($group_name) ?></span>
            <span class="brand-sub english">Group: <?= htmlspecialchars($group_name) ?></span>
          </div>
        </div>
      </div>

      <div class="topbar-right">
        <div class="leader-info">
          <div class="leader-name hindi">नमस्ते, <?= htmlspecialchars($leader_name) ?></div>
          <div class="leader-name english">Hello, <?= htmlspecialchars($leader_name) ?></div>
          <div class="profile-pic" title="<?= htmlspecialchars($leader_name) ?>">👤</div>
        </div>
        <a class="btn-logout" href="logout.php">
          <span class="hindi">लॉगआउट</span>
          <span class="english">Logout</span>
        </a>
      </div>
    </header>

    <!-- SIDEBAR + MAIN -->
    <div class="layout">
      <aside id="sidebar" class="sidebar">
        <nav class="nav">
          <ul>
            <li class="nav-item">
              <a href="qr_change_page.php" class="nav-link">
                <span class="hindi">क्यूआर बदलें</span>
                <span class="english">Change QR</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="set_payment_start.php" class="nav-link">
                <span class="hindi">भुगतान तिथि सेट करें</span>
                <span class="english">Set Payment Date</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="qr_history.php" class="nav-link">
                <span class="hindi">क्यूआर इतिहास</span>
                <span class="english">QR History</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="group_payment_history.php" class="nav-link">
                <span class="hindi">भुगतान इतिहास</span>
                <span class="english">Payment History</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="leader_payment_dashboard.php?group_id=<?= $group_id ?>" class="nav-link">
                <span class="hindi">भुगतान रिमाइंडर</span>
                <span class="english">Payment Reminders</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="set_late_fee.php" class="nav-link">
                <span class="hindi">जुर्माना सेटिंग</span>
                <span class="english">Fine Settings</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="late_fee_manager.php" class="nav-link">
                <span class="hindi">जुर्माना </span>
                <span class="english">Fine Calculation</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="loan_request_form.php" class="nav-link">
                <span class="hindi">लोन आवेदन</span>
                <span class="english">Loan Application</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="loan_repayment.php" class="nav-link">
                <span class="hindi">लोन भुगतान</span>
                <span class="english">Loan Repayment</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="loan_summary.php" class="nav-link">
                <span class="hindi">लोन सारांश</span>
                <span class="english">Loan Summary</span>
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
        <!-- SUMMARY CARDS -->
        <section class="cards-row">
          <div class="card summary">
            <div class="card-title hindi">🧍‍♂️ कुल सदस्य</div>
            <div class="card-title english">🧍‍♂️ Total Members</div>
            <div class="card-value"><?= number_format($total_members) ?></div>
            <div class="card-sub hindi">सक्रिय सदस्य</div>
            <div class="card-sub english">Active Members</div>
          </div>

          <div class="card summary">
            <div class="card-title hindi">💸 कुल जमा</div>
            <div class="card-title english">💸 Total Collected</div>
            <div class="card-value">₹<?= number_format($total_collected) ?></div>
            <div class="card-sub hindi">अब तक कुल राशि</div>
            <div class="card-sub english">Total Amount Collected</div>
          </div>

          <div class="card summary">
            <div class="card-title hindi">⚠️ लंबित भुगतान</div>
            <div class="card-title english">⚠️ Pending Payments</div>
            <div class="card-value"><?= number_format($pending_payments) ?></div>
            <div class="card-sub hindi">सत्यापन के लिए</div>
            <div class="card-sub english">Awaiting Verification</div>
          </div>

          <div class="card summary">
            <div class="card-title hindi">💰 जुर्माना जमा</div>
            <div class="card-title english">💰 Fine Collected</div>
            <div class="card-value">₹<?= number_format($total_fines) ?></div>
            <div class="card-sub hindi">जुर्माने से प्राप्त</div>
            <div class="card-sub english">From Late Fees</div>
          </div>
        </section>

        <!-- MAIN CONTENT: Table + Right Panels -->
        <section class="main-grid">
          <!-- left: table -->
          <div class="panel panel-table">
            <div class="panel-header">
              <h3 class="hindi">📊 हाल के भुगतान</h3>
              <h3 class="english">📊 Recent Payments</h3>
              <div class="panel-actions">
                <input id="searchInput" type="search" placeholder="सदस्य खोजें... / Search member...">
              </div>
            </div>

            <div class="table-wrap">
              <table id="memberTable" class="member-table">
                <thead>
                  <tr>
                    <th class="hindi">सदस्य नाम</th>
                    <th class="english">Member Name</th>
                    <th class="hindi">राशि</th>
                    <th class="english">Amount</th>
                    <th class="hindi">तारीख</th>
                    <th class="english">Date</th>
                    <th class="hindi">स्थिति</th>
                    <th class="english">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($recent_payments)): ?>
                    <?php foreach ($recent_payments as $payment): ?>
                      <tr>
                        <td><?= htmlspecialchars($payment['member_name']) ?></td>
                        <td>₹<?= number_format($payment['payment_amount']) ?></td>
                        <td><?= $payment['payment_date'] ? date('d/m/Y', strtotime($payment['payment_date'])) : '—' ?></td>
                        <td>
                          <span class="status <?= htmlspecialchars($payment['payment_status']) ?>">
                            <?php 
                              $status_text = [
                                'verified' => ['hindi' => 'सत्यापित ✅', 'english' => 'Verified ✅'],
                                'pending' => ['hindi' => 'लंबित ⏳', 'english' => 'Pending ⏳'],
                                'rejected' => ['hindi' => 'अस्वीकृत ❌', 'english' => 'Rejected ❌']
                              ];
                              $current_status = $status_text[$payment['payment_status']] ?? $status_text['pending'];
                            ?>
                            <span class="hindi"><?= $current_status['hindi'] ?></span>
                            <span class="english"><?= $current_status['english'] ?></span>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="8" class="no-data">
                        <span class="hindi">📊 अभी तक कोई भुगतान रिकॉर्ड नहीं</span>
                        <span class="english">📊 No payment records yet</span>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- right: notifications + next collection -->
          <aside class="panel-sidebar">
            <div class="panel-block notifications">
              <h4 class="hindi">🔔 सूचनाएं</h4>
              <h4 class="english">🔔 Notifications</h4>
              <ul id="notifList">
                <?php if (!empty($notifications)): ?>
                  <?php foreach ($notifications as $notification): ?>
                    <li>
                      <strong><?= htmlspecialchars($notification['title']) ?></strong><br>
                      <?= htmlspecialchars($notification['message']) ?><br>
                      <small><?= date('d M, Y', strtotime($notification['created_at'])) ?></small>
                    </li>
                  <?php endforeach; ?>
                <?php else: ?>
                  <li>
                    <span class="hindi">📝 कोई नई सूचना नहीं</span>
                    <span class="english">📝 No new notifications</span>
                  </li>
                <?php endif; ?>
              </ul>
            </div>

            <div class="panel-block next-collect">
              <h4 class="hindi">🗓️ अगला संग्रह</h4>
              <h4 class="english">🗓️ Next Collection</h4>
              <?php if ($next_collection): ?>
                <p class="hindi"><strong><?= date('d M, Y', strtotime($next_collection)) ?></strong></p>
                <p class="english"><strong><?= date('d M, Y', strtotime($next_collection)) ?></strong></p>
                <p class="hindi">योगदान: <strong>₹<?= number_format($expected_amount) ?></strong></p>
                <p class="english">Contribution: <strong>₹<?= number_format($expected_amount) ?></strong></p>
                <p class="hindi">चक्र: <strong><?= $payment_cycle === 'weekly' ? 'साप्ताहिक' : 'मासिक' ?></strong></p>
                <p class="english">Cycle: <strong><?= ucfirst($payment_cycle) ?></strong></p>
              <?php else: ?>
                <p class="hindi">भुगतान तिथि सेट नहीं</p>
                <p class="english">Payment date not set</p>
              <?php endif; ?>
              <a href="set_payment_start.php" class="btn-primary">
                <span class="hindi">समय बदलें</span>
                <span class="english">Edit Schedule</span>
              </a>
            </div>
          </aside>
        </section>

        <!-- FOOTER -->
        <div class="small-footer">
          <span class="hindi">© <?= date('Y') ?> समूह प्लेटफॉर्म | सुरक्षित और विश्वसनीय</span>
          <span class="english">© <?= date('Y') ?> Samuh Platform | Secure & Reliable</span>
        </div>
      </main>
    </div>
  </div>

  <script src="leader_dashboard.js"></script>
</body>
</html>