<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("location: core_member_login.php");
    exit;
}

include("config.php");

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$group_id = $_SESSION['group_id'];

// Fetch loans based on user role
if ($user_role === 'member') {
    $loans_sql = "SELECT l.* FROM loans l 
                  WHERE l.member_id = ? 
                  ORDER BY l.applied_date DESC";
    $loans_stmt = $conn->prepare($loans_sql);
    $loans_stmt->execute([$user_id]);
} else {
    $loans_sql = "SELECT l.*, COALESCE(m.full_name, e.full_name) as applicant_name
                  FROM loans l
                  LEFT JOIN members m ON l.member_id = m.id
                  LEFT JOIN external_applicants e ON l.external_applicant_id = e.id
                  WHERE l.group_id = ?
                  ORDER BY l.applied_date DESC";
    $loans_stmt = $conn->prepare($loans_sql);
    $loans_stmt->execute([$group_id]);
}
$loans = $loans_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Summary - समूह प्लेटफॉर्म</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .loan-item { border-left: 4px solid; padding: 15px; margin: 10px 0; background: #f8f9fa; }
        .status-pending { border-color: #ffc107; }
        .status-approved { border-color: #28a745; }
        .status-rejected { border-color: #dc3545; }
        .status-active { border-color: #007bff; }
        .status-closed { border-color: #6c757d; }
        .progress { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; margin: 10px 0; }
        .progress-bar { height: 100%; background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h2>
            <span class="hindi">📊 लोन सारांश</span>
            <span class="english">📊 Loan Summary</span>
        </h2>

        <div class="card">
            <?php foreach ($loans as $loan): 
                $progress = (($loan['loan_amount'] - $loan['remaining_balance']) / $loan['loan_amount']) * 100;
            ?>
            <div class="loan-item status-<?= $loan['status'] ?>">
                <div style="display: flex; justify-content: between; align-items: center;">
                    <div style="flex: 1;">
                        <h4>
                            <?= htmlspecialchars($loan['applicant_name'] ?? 'Loan') ?> - 
                            ₹<?= number_format($loan['loan_amount']) ?>
                        </h4>
                        <p>
                            <span class="hindi">उद्देश्य: <?= htmlspecialchars($loan['purpose']) ?></span>
                            <span class="english">Purpose: <?= htmlspecialchars($loan['purpose']) ?></span>
                        </p>
                        <p>
                            <span class="hindi">स्थिति: </span>
                            <span class="english">Status: </span>
                            <strong>
                                <?= ucfirst($loan['status']) ?>
                                <?= $loan['status'] === 'approved' ? '✅' : '' ?>
                                <?= $loan['status'] === 'rejected' ? '❌' : '' ?>
                                <?= $loan['status'] === 'closed' ? '🏁' : '' ?>
                            </strong>
                        </p>
                        
                        <?php if ($loan['status'] === 'active' || $loan['status'] === 'approved'): ?>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                        </div>
                        <p>
                            <span class="hindi">
                                भुगतान: ₹<?= number_format($loan['total_paid']) ?> / ₹<?= number_format($loan['loan_amount']) ?>
                                (<?= round($progress) ?>%)
                            </span>
                            <span class="english">
                                Paid: ₹<?= number_format($loan['total_paid']) ?> / ₹<?= number_format($loan['loan_amount']) ?>
                                (<?= round($progress) ?>%)
                            </span>
                        </p>
                        <p>
                            <span class="hindi">ब्याज दर: <?= $loan['interest_rate'] ?>%</span>
                            <span class="english">Interest Rate: <?= $loan['interest_rate'] ?>%</span>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div style="text-align: right;">
                        <p><small><?= date('d M, Y', strtotime($loan['applied_date'])) ?></small></p>
                        <?php if ($loan['status'] === 'active'): ?>
                        <a href="loan_repayment.php" class="btn" style="background: #2b7be4; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">
                            <span class="hindi">भुगतान करें</span>
                            <span class="english">Pay Now</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>