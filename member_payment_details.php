<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit();
}

// Check if member_id and group_id are provided
if (!isset($_GET['member_id']) || !isset($_GET['group_id'])) {
    header("Location: group_payment_history.php");
    exit();
}

$member_id = $_GET['member_id'];
$group_id = $_GET['group_id'];
$user_role = $_SESSION['role'];

// Verify that the member belongs to the user's group (for non-admin users)
if ($user_role !== 'admin' && $_SESSION['group_id'] != $group_id) {
    header("Location: group_payment_history.php");
    exit();
}

// Fetch member details and payment history
$member_data = [];
$payment_history = [];

try {
    // Get member basic info
    $stmt = $conn->prepare("
        SELECT m.*, g.group_name 
        FROM members m 
        JOIN groups g ON m.group_id = g.id 
        WHERE m.id = ? AND m.group_id = ?
    ");
    $stmt->execute([$member_id, $group_id]);
    $member_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member_data) {
        header("Location: group_payment_history.php");
        exit();
    }

    // Get payment history with accountant name
    $stmt = $conn->prepare("
        SELECT 
            pr.*,
            a.full_name as accountant_name,
            g.group_name
        FROM payment_request pr
        LEFT JOIN accountants a ON pr.verified_by_accountant = a.id
        JOIN groups g ON pr.group_id = g.id
        WHERE pr.member_id = ? AND pr.group_id = ?
        ORDER BY pr.payment_date DESC, pr.created_at DESC
    ");
    $stmt->execute([$member_id, $group_id]);
    $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
}

// Calculate total verified amount for this member
$total_verified_amount = 0;
foreach ($payment_history as $payment) {
    if ($payment['payment_status'] === 'verified') {
        $total_verified_amount += $payment['payment_amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Payment Details - Samuh Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: 1px solid #e3e6f0;
            margin-bottom: 20px;
        }
        .member-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
        }
        .member-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
        }
        .status-verified { background-color: #d4edda; }
        .status-pending { background-color: #fff3cd; }
        .status-rejected { background-color: #f8d7da; }
        .table-hover tbody tr:hover { background-color: rgba(0, 123, 255, 0.1); }
        .back-btn {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Back Button -->
        <div class="back-btn">
            <a href="group_payment_history.php?group_id=<?php echo $group_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Group
            </a>
        </div>

        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="fas fa-user me-2"></i>
                Payment Details
            </h1>
        </div>

        <!-- Member Header -->
        <div class="member-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <?php if (!empty($member_data['photo_path']) && file_exists($member_data['photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($member_data['photo_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($member_data['full_name']); ?>" 
                                 class="member-photo me-4">
                        <?php else: ?>
                            <div class="member-photo bg-light bg-opacity-25 d-flex align-items-center justify-content-center me-4">
                                <i class="fas fa-user fa-2x text-white"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h2 class="mb-1"><?php echo htmlspecialchars($member_data['full_name']); ?></h2>
                            <p class="mb-1 opacity-75">
                                <i class="fas fa-users me-1"></i><?php echo htmlspecialchars($member_data['group_name']); ?>
                            </p>
                            <p class="mb-0 opacity-75">
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($member_data['mobile']); ?>
                                | 
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($member_data['email']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <h3 class="mb-0">₹<?php echo number_format($total_verified_amount, 2); ?></h3>
                    <p class="mb-0 opacity-75">Total Verified Amount</p>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-receipt me-2"></i>
                    Payment History
                </h5>
            </div>
            <div class="card-body">
                <?php if (count($payment_history) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Payment Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Verified By</th>
                                    <th>Verified At</th>
                                    <th>Created Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_history as $payment): ?>
                                    <tr class="status-<?php echo $payment['payment_status']; ?>">
                                        <td>
                                            <strong><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></strong>
                                        </td>
                                        <td>
                                            <span class="fw-bold">₹<?php echo number_format($payment['payment_amount'], 2); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_badge = [
                                                'verified' => ['success', '✓ Verified'],
                                                'pending' => ['warning', '⏳ Pending'],
                                                'rejected' => ['danger', '✗ Rejected']
                                            ][$payment['payment_status']] ?? ['secondary', 'Unknown'];
                                            ?>
                                            <span class="badge bg-<?php echo $status_badge[0]; ?>">
                                                <?php echo $status_badge[1]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($payment['accountant_name']): ?>
                                                <span class="text-success">
                                                    <i class="fas fa-user-check me-1"></i>
                                                    <?php echo htmlspecialchars($payment['accountant_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($payment['verified_at']): ?>
                                                <?php echo date('d M Y H:i', strtotime($payment['verified_at'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($payment['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Payment Statistics -->
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="card text-center bg-light">
                                <div class="card-body">
                                    <h3 class="text-success mb-0">
                                        <?php 
                                        $verified_count = array_filter($payment_history, function($p) {
                                            return $p['payment_status'] === 'verified';
                                        });
                                        echo count($verified_count);
                                        ?>
                                    </h3>
                                    <small class="text-muted">Verified Payments</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-light">
                                <div class="card-body">
                                    <h3 class="text-warning mb-0">
                                        <?php 
                                        $pending_count = array_filter($payment_history, function($p) {
                                            return $p['payment_status'] === 'pending';
                                        });
                                        echo count($pending_count);
                                        ?>
                                    </h3>
                                    <small class="text-muted">Pending Payments</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-light">
                                <div class="card-body">
                                    <h3 class="text-danger mb-0">
                                        <?php 
                                        $rejected_count = array_filter($payment_history, function($p) {
                                            return $p['payment_status'] === 'rejected';
                                        });
                                        echo count($rejected_count);
                                        ?>
                                    </h3>
                                    <small class="text-muted">Rejected Payments</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-light">
                                <div class="card-body">
                                    <h3 class="text-primary mb-0"><?php echo count($payment_history); ?></h3>
                                    <small class="text-muted">Total Payments</small>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                        <h4>No Payment History</h4>
                        <p class="text-muted">No payments found for this member.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>