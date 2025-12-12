<?php
// admin_dashboard.php
if (session_status() === PHP_SESSION_NONE) session_start();
include("config.php");

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

// Mail function for approved members
function sendInviteEmail($toemail, $toname, $username, $password, $group_id, $group_name) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.zoho.in';
        $mail->SMTPAuth = true;
        $mail->Username = 'sunilkumar952323@zohomail.in';
        $mail->Password = 'HG1ZU5KnADyu';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('sunilkumar952323@zohomail.in', 'SAMUH PLATFORM');
        $mail->addAddress($toemail, $toname);

        $mail->isHTML(true);
        $mail->Subject = 'Your Login Credentials - Samuh Platform';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #007bff; text-align: center;'>समूह प्लेटफॉर्म में आपका स्वागत है</h2>
                <h2 style='color: #007bff; text-align: center;'>Welcome to Samuh Platform</h2>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                    <p>नमस्ते <strong>" . htmlspecialchars($toname) . "</strong>,</p>
                    <p>Hello <strong>" . htmlspecialchars($toname) . "</strong>,</p>
                    
                    <p style='color: #28a745; font-weight: bold;'>आपकी सदस्यता स्वीकृत की गई है / Your membership has been approved!</p>
                    
                    <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                        <h3 style='color: #28a745;'>आपके लॉगिन क्रेडेंशियल्स:</h3>
                        <h3 style='color: #28a745;'>Your Login Credentials:</h3>
                        
                        <p><strong>समूह (Group):</strong> " . htmlspecialchars($group_name) . "</p>
                        <p><strong>समूह आईडी (Group ID):</strong> " . htmlspecialchars($group_id) . "</p>
                        <p><strong>उपयोगकर्ता नाम (Username):</strong> " . htmlspecialchars($username) . "</p>
                        <p><strong>पासवर्ड (Password):</strong> " . htmlspecialchars($password) . "</p>
                    </div>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='http://yourdomain.com/login.php?role=member' 
                           style='background: #007bff; color: white; padding: 12px 30px; 
                                  text-decoration: none; border-radius: 25px; display: inline-block;'>
                            यहाँ लॉगिन करें / Login Here
                        </a>
                    </div>
                    
                    <div style='background: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107;'>
                        <strong>सुरक्षा सलाह / Security Advice:</strong><br>
                        • अपना पासवर्ड किसी के साथ साझा न करें<br>
                        • पहली बार लॉगिन के बाद पासवर्ड बदलें<br><br>
                        <strong>English:</strong><br>
                        • Do not share your password with anyone<br>
                        • Change password after first login
                    </div>
                </div>
                
                <div style='text-align: center; color: #6c757d; font-size: 0.9em;'>
                    <p>धन्यवाद,<br><strong>समूह प्लेटफॉर्म टीम</strong></p>
                    <p>Thank You,<br><strong>Samuh Platform Team</strong></p>
                </div>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// Mail function for rejected members
function sendRejectionEmail($toemail, $toname, $group_name, $rejection_reason = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.zoho.in';
        $mail->SMTPAuth = true;
        $mail->Username = 'sunilkumar952323@zohomail.in';
        $mail->Password = 'HG1ZU5KnADyu';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('sunilkumar952323@zohomail.in', 'SAMUH PLATFORM');
        $mail->addAddress($toemail, $toname);

        $mail->isHTML(true);
        $mail->Subject = 'Membership Application Status - Samuh Platform';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #dc3545; text-align: center;'>सदस्यता आवेदन स्थिति</h2>
                <h2 style='color: #dc3545; text-align: center;'>Membership Application Status</h2>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                    <p>नमस्ते <strong>" . htmlspecialchars($toname) . "</strong>,</p>
                    <p>Hello <strong>" . htmlspecialchars($toname) . "</strong>,</p>
                    
                    <div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #dc3545;'>
                        <h3 style='color: #dc3545; margin-top: 0;'>❌ सदस्यता आवेदन अस्वीकृत</h3>
                        <h3 style='color: #dc3545;'>❌ Membership Application Rejected</h3>
                        
                        <p><strong>समूह (Group):</strong> " . htmlspecialchars($group_name) . "</p>
                        " . (!empty($rejection_reason) ? "
                        <p><strong>कारण (Reason):</strong> " . htmlspecialchars($rejection_reason) . "</p>
                        " : "") . "
                    </div>
                    
                    <div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #17a2b8;'>
                        <h4 style='color: #17a2b8; margin-top: 0;'>आगे की कार्रवाई / Next Steps:</h4>
                        <ul>
                            <li>आप फिर से नए सदस्यता आवेदन के लिए आवेदन कर सकते हैं</li>
                            <li>कृपया सही और पूरी जानकारी प्रदान करें</li>
                            <li>यदि कोई प्रश्न है तो समूह प्रशासक से संपर्क करें</li>
                        </ul>
                        <br>
                        <strong>English:</strong>
                        <ul>
                            <li>You can apply again with a new membership application</li>
                            <li>Please provide correct and complete information</li>
                            <li>Contact group administrator if you have any questions</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='http://yourdomain.com/member_request.php' 
                           style='background: #6c757d; color: white; padding: 12px 30px; 
                                  text-decoration: none; border-radius: 25px; display: inline-block;'>
                            नया आवेदन करें / Apply Again
                        </a>
                    </div>
                </div>
                
                <div style='text-align: center; color: #6c757d; font-size: 0.9em;'>
                    <p>धन्यवाद,<br><strong>समूह प्लेटफॉर्म टीम</strong></p>
                    <p>Thank You,<br><strong>Samuh Platform Team</strong></p>
                </div>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// -------------------- AUTH CHECK --------------------
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    header('Location: core_member_login.php?role=admin');
    exit;
}
$admin_user_id = intval($_SESSION['user_id']);

// -------------------- HANDLE APPROVE / REJECT --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $action = $_POST['action'];
    $request_id = intval($_POST['request_id']);

    function send_response($ok, $msg = '') {
        $_SESSION['flash_admin_msg'] = ($ok ? "✅ " : "❌ ") . $msg;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Fetch request data from members table (pending status)
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ? AND member_status = 'pending'");
    $stmt->execute([$request_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        send_response(false, "Member request not found or already processed.");
    }

    // Get group name for email
    $group_stmt = $conn->prepare("SELECT group_name FROM groups WHERE id = ?");
    $group_stmt->execute([$member['group_id']]);
    $group = $group_stmt->fetch(PDO::FETCH_ASSOC);
    $group_name = $group['group_name'] ?? 'Unknown Group';

    // APPROVE
    if ($action === 'approve') {
        try {
            $group_id = $member['group_id'];
            $full_name = $member['full_name'];
            $mobile = $member['mobile'];
            $email = $member['email'];
            
            $conn->beginTransaction();

            // Generate username and password
            $username = strtolower(str_replace(' ', '', $full_name)) . rand(100, 999);
            $password = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update member status to active and set credentials
            $updateMemberSql = "UPDATE members 
                SET member_status = 'active', 
                    kyc_status = 'verified',
                    username = ?,
                    password_hash = ?,
                    joining_date = CURDATE(),
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    review_notes = 'Approved by admin'
                WHERE id = ?";
            
            $stmtUpdate = $conn->prepare($updateMemberSql);
            $stmtUpdate->execute([
                $username, 
                $hashed_password, 
                $admin_user_id, 
                $request_id
            ]);

            // Send welcome email with credentials
            if (!empty($email)) {
                $mail_result = sendInviteEmail($email, $full_name, $username, $password, $group_id, $group_name);
                if ($mail_result !== true) {
                    error_log("Email sending failed: " . $mail_result);
                    // Continue even if email fails
                }
            }

            $conn->commit();
            
            $email_status = !empty($email) ? " and email sent" : " (email not sent - no email address)";
            send_response(true, "Member approved successfully." . $email_status);
            
        } catch (Exception $e) {
            $conn->rollBack();
            send_response(false, "Database Error: " . $e->getMessage());
        }
    }

    // REJECT
    if ($action === 'reject') {
        try {
            $full_name = $member['full_name'];
            $email = $member['email'];
            $review_notes = $_POST['review_notes'] ?? 'Rejected by admin';
            
            $conn->beginTransaction();

            $updateMemberSql = "UPDATE members 
                SET member_status = 'rejected',
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    review_notes = ?
                WHERE id = ?";
            
            $stmtUpdate = $conn->prepare($updateMemberSql);
            $stmtUpdate->execute([$admin_user_id, $review_notes, $request_id]);

            // Send rejection email
            if (!empty($email)) {
                $mail_result = sendRejectionEmail($email, $full_name, $group_name, $review_notes);
                if ($mail_result !== true) {
                    error_log("Rejection email sending failed: " . $mail_result);
                    // Continue even if email fails
                }
            }

            $conn->commit();
            
            $email_status = !empty($email) ? " and rejection email sent" : " (email not sent - no email address)";
            send_response(true, "Member request rejected successfully." . $email_status);
            
        } catch (Exception $e) {
            $conn->rollBack();
            send_response(false, "Database Error: " . $e->getMessage());
        }
    }
}

// -------------------- FETCH DASHBOARD DATA --------------------

// Admin info
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_user_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin['full_name'] ?? ($admin['username'] ?? 'Admin');

// Group info
$group_id = $admin['group_id'] ?? null;
$group = null;
if ($group_id) {
    $stmt = $conn->prepare("SELECT * FROM groups WHERE id=?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
}
$group_name = $group['group_name'] ?? '—';

// Counts - UPDATED FOR SINGLE TABLE
$stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE group_id=? AND member_status='active'");
$stmt->execute([$group_id]);
$totalMembers = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE group_id=? AND member_status='pending'");
$stmt->execute([$group_id]);
$pendingRequests = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM payment_request WHERE group_id=? AND payment_status='verified'");
$stmt->execute([$group_id]);
$verifiedPayments = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM payment_request WHERE group_id=? AND payment_status='pending'");
$stmt->execute([$group_id]);
$pendingPayments = (int)$stmt->fetchColumn();

// Pending requests list - UPDATED FOR SINGLE TABLE
$stmt = $conn->prepare("SELECT * FROM members WHERE group_id=? AND member_status='pending' ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$group_id]);
$member_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent approved members - UPDATED FOR SINGLE TABLE
$stmt = $conn->prepare("SELECT full_name, mobile, email, joining_date FROM members WHERE group_id=? AND member_status='active' ORDER BY joining_date DESC LIMIT 5");
$stmt->execute([$group_id]);
$recent_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Flash message
$flash = $_SESSION['flash_admin_msg'] ?? null;
unset($_SESSION['flash_admin_msg']);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>एडमिन डैशबोर्ड - Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin_dashboard.css">
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-left">
            <button id="sidebarToggle" class="btn-ghost">☰</button>
            <div class="brand">
                <div class="logo-circle hindi">ए</div>
                <div class="logo-circle english">A</div>
                <div class="brand-text">
                    <span class="brand-title hindi">एडमिन डैशबोर्ड</span>
                    <span class="brand-title english">Admin Dashboard</span>
                    <span class="brand-sub hindi">ग्रुप: <?= htmlspecialchars($group_name) ?></span>
                    <span class="brand-sub english">Group: <?= htmlspecialchars($group_name) ?></span>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="admin-info">
                <div class="admin-name hindi">नमस्ते, <?= htmlspecialchars($adminName) ?></div>
                <div class="admin-name english">Hello, <?= htmlspecialchars($adminName) ?></div>
                <div class="profile-pic">👤</div>
            </div>
            <a href="logout.php" class="btn-logout">
                <span class="hindi">लॉगआउट</span>
                <span class="english">Logout</span>
            </a>
        </div>
    </header>

    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <nav class="nav">
                <ul>
                    <li class="nav-item">
                        <a href="set_payment_start.php" class="nav-link">
                            <span class="hindi">भुगतान आरंभ तिथि निर्धारित करें</span>
                            <span class="english">Set Payment Start Date</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="member_management.php" class="nav-link">
                            <span class="hindi">सदस्य प्रबंधन</span>
                            <span class="english">Member Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="group_payment_history.php" class="nav-link">
                            <span class="hindi">भुगतान इतिहास</span>
                            <span class="english">Payment History</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="payment_approval.php" class="nav-link">
                            <span class="hindi">भुगतान स्वीकृति</span>
                            <span class="english">Payment Approval</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="loan_request_form.php" class="nav-link">
                            <span class="hindi">Loan Request</span>
                            <span class="english">Loan Request</span>
                        </a>
                    </li>            
                    <li class="nav-item">
                        <a href="loan_repayment.php" class="nav-link">
                            <span class="hindi">loan repayment</span>
                            <span class="english">loan repayment</span>
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
                <div class="hindi"><strong>कुल सदस्य:</strong> <?= $totalMembers ?></div>
                <div class="english"><strong>Total Members:</strong> <?= $totalMembers ?></div>
            </div>
        </aside>

        <main class="main">
            <?php if ($flash): ?>
                <div class="flash-msg">
                    <span class="hindi"><?= htmlspecialchars($flash) ?></span>
                    <span class="english"><?= htmlspecialchars($flash) ?></span>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <section class="cards-row">
                <div class="card summary">
                    <div class="card-title hindi">👥 कुल सदस्य</div>
                    <div class="card-title english">👥 Total Members</div>
                    <div class="card-value"><?= number_format($totalMembers) ?></div>
                    <div class="card-sub hindi">सक्रिय सदस्य</div>
                    <div class="card-sub english">active members</div>
                </div>

                <div class="card summary">
                    <div class="card-title hindi">⏳ लंबित अनुरोध</div>
                    <div class="card-title english">⏳ Pending Requests</div>
                    <div class="card-value"><?= number_format($pendingRequests) ?></div>
                    <div class="card-sub hindi">सदस्यता अनुरोध</div>
                    <div class="card-sub english">membership requests</div>
                </div>

                <div class="card summary">
                    <div class="card-title hindi">✅ सत्यापित भुगतान</div>
                    <div class="card-title english">✅ Verified Payments</div>
                    <div class="card-value"><?= number_format($verifiedPayments) ?></div>
                    <div class="card-sub hindi">कुल भुगतान</div>
                    <div class="card-sub english">total payments</div>
                </div>

                <div class="card summary">
                    <div class="card-title hindi">⚠️ लंबित भुगतान</div>
                    <div class="card-title english">⚠️ Pending Payments</div>
                    <div class="card-value"><?= number_format($pendingPayments) ?></div>
                    <div class="card-sub hindi">जमा होना बाकी</div>
                    <div class="card-sub english">awaiting clearance</div>
                </div>
            </section>

            <section class="main-grid">
                <!-- Member Requests Table -->
                <div class="panel panel-table">
                    <div class="panel-header">
                        <h3 class="hindi">📋 सदस्यता अनुरोध (Pending Members)</h3>
                        <h3 class="english">📋 Membership Requests (Pending Members)</h3>
                        <div class="panel-actions">
                            <input id="searchInput" type="search" placeholder="नाम या मोबाइल खोजें... / Search by name or mobile...">
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table id="requestsTable" class="member-table">
                            <thead>
                                <tr>
                                    <th class="hindi">आईडी</th>
                                    <th class="english">ID</th>
                                    <th class="hindi">नाम</th>
                                    <th class="english">Name</th>
                                    <th class="hindi">मोबाइल</th>
                                    <th class="english">Mobile</th>
                                    <th class="hindi">ईमेल</th>
                                    <th class="english">Email</th>
                                    <th class="hindi">तारीख</th>
                                    <th class="english">Date</th>
                                    <th class="hindi">कार्य</th>
                                    <th class="english">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($member_requests as $r): ?>
                                    <tr>
                                        <td><?= $r['id'] ?></td>
                                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                                        <td><?= htmlspecialchars($r['mobile']) ?></td>
                                        <td><?= htmlspecialchars($r['email'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['created_at']))) ?></td>
                                        <td>
                                            <button class="btn-view" data-req='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'>
                                                <span class="hindi">देखें</span>
                                                <span class="english">View</span>
                                            </button>
                                            <form method="post" style="display:inline-block">
                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-approve" onclick="return confirm('क्या आप इस सदस्य को स्वीकार करना चाहते हैं? / Do you want to approve this member?')">
                                                    <span class="hindi">स्वीकारें</span>
                                                    <span class="english">Approve</span>
                                                </button>
                                            </form>
                                            <button class="btn-reject" data-id="<?= $r['id'] ?>">
                                                <span class="hindi">अस्वीकार</span>
                                                <span class="english">Reject</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($member_requests) === 0): ?>
                                    <tr>
                                        <td colspan="10" class="no-data">
                                            <span class="hindi">🎉 कोई लंबित अनुरोध नहीं</span>
                                            <span class="english">🎉 No pending requests</span>
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
                            <?php if ($pendingRequests > 0): ?>
                                <li>
                                    <span class="hindi">📝 <?= $pendingRequests ?> नए सदस्यता अनुरोध</span>
                                    <span class="english">📝 <?= $pendingRequests ?> new membership requests</span>
                                </li>
                            <?php endif; ?>
                            <?php if ($pendingPayments > 0): ?>
                                <li>
                                    <span class="hindi">💰 <?= $pendingPayments ?> भुगतान लंबित</span>
                                    <span class="english">💰 <?= $pendingPayments ?> payments pending</span>
                                </li>
                            <?php endif; ?>
                            <?php foreach (array_slice($recent_members, 0, 3) as $member): ?>
                                <li>
                                    <span class="hindi">✅ <?= htmlspecialchars($member['full_name']) ?> जुड़े</span>
                                    <span class="english">✅ <?= htmlspecialchars($member['full_name']) ?> joined</span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (count($recent_members) === 0 && $pendingRequests === 0 && $pendingPayments === 0): ?>
                                <li>
                                    <span class="hindi">📊 सभी सिस्टम सामान्य</span>
                                    <span class="english">📊 All systems normal</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="panel-block recent-members">
                        <h4 class="hindi">🆕 नए सदस्य</h4>
                        <h4 class="english">🆕 New Members</h4>
                        <ul>
                            <?php foreach ($recent_members as $member): ?>
                                <li>
                                    <strong><?= htmlspecialchars($member['full_name']) ?></strong><br>
                                    <small><?= htmlspecialchars($member['mobile']) ?></small><br>
                                    <small><?= htmlspecialchars($member['email'] ?? 'No email') ?></small><br>
                                    <small><?= date('d M Y', strtotime($member['joining_date'])) ?></small>
                                </li>
                            <?php endforeach; ?>
                            <?php if (count($recent_members) === 0): ?>
                                <li>
                                    <span class="hindi">📝 अभी तक कोई सदस्य नहीं</span>
                                    <span class="english">📝 No members yet</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </aside>
            </section>

            <div class="small-footer">
                <span class="hindi">© <?= date('Y') ?> समूह प्लेटफॉर्म | सुरक्षित प्रबंधन</span>
                <span class="english">© <?= date('Y') ?> Samuh Platform | Secure Management</span>
            </div>
        </main>
    </div>
</div>

<!-- Modal for viewing request details & rejecting -->
<div id="reqModal" class="modal" aria-hidden="true" style="display:none;">
  <div class="modal-content">
    <button id="modalClose" class="modal-close">✖</button>
    <h3 id="modalName"></h3>
    <div id="modalBody"></div>

    <form id="rejectForm" method="post" style="margin-top:12px;">
      <input type="hidden" name="request_id" id="reject_request_id" value="">
      <input type="hidden" name="action" value="reject">
      <label class="hindi">अस्वीकरण कारण (वैकल्पिक) - यह ईमेल में भेजा जाएगा</label>
      <label class="english">Rejection Reason (Optional) - This will be sent in email</label>
      <textarea name="review_notes" id="reject_notes" rows="3" style="width:100%" placeholder="Rejection reason..."></textarea>
      <div style="margin-top:8px;">
        <button type="submit" class="btn-decline" onclick="return confirm('क्या आप इस अनुरोध को अस्वीकार करना चाहते हैं? ईमेल भेजा जाएगा। / Do you want to reject this request? Email will be sent.')">
            <span class="hindi">अस्वीकार करें और ईमेल भेजें</span>
            <span class="english">Reject and Send Email</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script src="admin_dashboard.js"></script>
</body>
</html>