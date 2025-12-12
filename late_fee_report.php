<?php
session_start();
require_once 'config.php';

// Check if user is logged in as admin or accountant
if (!isset($_SESSION['login']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'accountant')) {
    header("Location: login.php");
    exit();
}

// Fetch all late fees
$late_fees = [];
$total_unpaid = 0;
$total_paid = 0;

try {
    $sql = "
        SELECT lf.*, m.full_name as member_name, g.group_name,
               a.full_name as accountant_name
        FROM late_fees lf
        JOIN members m ON lf.member_id = m.id
        JOIN groups g ON lf.group_id = g.id
        LEFT JOIN accountants a ON lf.group_id = a.group_id
        ORDER BY lf.is_paid, lf.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $late_fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($late_fees as $fee) {
        if ($fee['is_paid']) {
            $total_paid += $fee['fine_amount'];
        } else {
            $total_unpaid += $fee['fine_amount'];
        }
    }
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Late Fee Report - Samuh Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid py-4">
        <h2>Late Fee Report</h2>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3><?php echo count($late_fees); ?></h3>
                        <p class="mb-0">Total Late Fees</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h3>₹<?php echo number_format($total_unpaid, 2); ?></h3>
                        <p class="mb-0">Total Unpaid</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3>₹<?php echo number_format($total_paid, 2); ?></h3>
                        <p class="mb-0">Total Collected</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Late Fees Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">All Late Fees</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Group</th>
                                <th>Cycle</th>
                                <th>Due Date</th>
                                <th>Paid On</th>
                                <th>Days Late</th>
                                <th>Fine Amount</th>
                                <th>Status</th>
                                <th>Accountant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($late_fees as $fee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fee['member_name']); ?></td>
                                <td><?php echo htmlspecialchars($fee['group_name']); ?></td>
                                <td>Cycle <?php echo $fee['cycle_no']; ?></td>
                                <td><?php echo date('d M Y', strtotime($fee['due_date'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($fee['payment_date'])); ?></td>
                                <td>
                                    <span class="badge bg-danger"><?php echo $fee['days_late']; ?> days</span>
                                </td>
                                <td>
                                    <strong>₹<?php echo number_format($fee['fine_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php if ($fee['is_paid']): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $fee['accountant_name'] ? htmlspecialchars($fee['accountant_name']) : 'N/A'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>