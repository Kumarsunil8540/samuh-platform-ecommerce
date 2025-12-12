<?php
session_start();
include("config.php");

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: core_member_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];
$group_id = $_SESSION['group_id'];

$success_message = '';
$error_message = '';

// Fetch all late fees data from late_fees table
try {
    if ($role === 'member') {
        // For members, show only their late fees
        $late_fees_sql = "SELECT lf.* 
                          FROM late_fees lf
                          WHERE lf.member_id = ? 
                          ORDER BY lf.created_at DESC, lf.cycle_no DESC";
        $late_fees_stmt = $conn->prepare($late_fees_sql);
        $late_fees_stmt->execute([$user_id]);
        $late_fees = $late_fees_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else if ($role === 'accountant' || $role === 'leader' || $role === 'admin') {
        // For admin/accountant/leader, show all late fees of the group
        $late_fees_sql = "SELECT lf.*, m.full_name AS member_name
                          FROM late_fees lf
                          JOIN members m ON lf.member_id = m.id
                          WHERE lf.group_id = ? 
                          ORDER BY lf.created_at DESC, lf.cycle_no DESC";
        $late_fees_stmt = $conn->prepare($late_fees_sql);
        $late_fees_stmt->execute([$group_id]);
        $late_fees = $late_fees_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Calculate statistics
    $total_late_fees = count($late_fees);
    $unpaid_late_fees = array_filter($late_fees, function($fee) {
        return $fee['is_paid'] == 0;
    });
    $paid_late_fees = array_filter($late_fees, function($fee) {
        return $fee['is_paid'] == 1;
    });
    
    $total_amount = array_sum(array_column($late_fees, 'fine_amount'));
    $unpaid_amount = array_sum(array_column($unpaid_late_fees, 'fine_amount'));
    $paid_amount = array_sum(array_column($paid_late_fees, 'fine_amount'));
    
} catch (PDOException $e) {
    error_log("Late Fee Records Error: " . $e->getMessage());
    $error_message = "❌ Database error occurred!";
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Late Fee Records - Samuh</title>
    <link rel="stylesheet" href="member_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .records-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            border-left: 4px solid #4361ee;
        }
        
        .stat-card.unpaid {
            border-left-color: #e63946;
        }
        
        .stat-card.paid {
            border-left-color: #28a745;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .records-table {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid #dee2e6;
        }
        
        .data-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .amount-cell {
            font-weight: 600;
        }
        
        .amount-unpaid {
            color: #e63946;
        }
        
        .amount-paid {
            color: #28a745;
        }
        
        .action-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .action-btn:hover {
            background: var(--secondary);
            text-decoration: none;
            color: white;
        }
        
        .no-records {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }
        
        .no-records i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                font-size: 0.8rem;
            }
            
            .table-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="dashboard-header">
        <div class="header-left">
            <div class="logo">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Late Fee Records</span>
            </div>
        </div>
        
        <div class="header-right">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role">(<?php echo ucfirst($role); ?>)</span>
            </div>
            <a href="<?php echo $role . '_dashboard.php'; ?>" class="logout-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </header>

    <main class="dashboard-main">
        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="records-header">
            <h1>
                <i class="fas fa-file-invoice-dollar"></i>
                Late Fee Records
            </h1>
            <p>Complete history of all late fees in your <?php echo $role === 'member' ? 'account' : 'group'; ?></p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_late_fees; ?></div>
                <div class="stat-label">Total Late Fees</div>
            </div>
            
            <div class="stat-card unpaid">
                <div class="stat-number"><?php echo count($unpaid_late_fees); ?></div>
                <div class="stat-label">Unpaid Late Fees</div>
            </div>
            
            <div class="stat-card paid">
                <div class="stat-number"><?php echo count($paid_late_fees); ?></div>
                <div class="stat-label">Paid Late Fees</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">₹<?php echo number_format($total_amount, 2); ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
            
            <div class="stat-card unpaid">
                <div class="stat-number">₹<?php echo number_format($unpaid_amount, 2); ?></div>
                <div class="stat-label">Unpaid Amount</div>
            </div>
            
            <div class="stat-card paid">
                <div class="stat-number">₹<?php echo number_format($paid_amount, 2); ?></div>
                <div class="stat-label">Paid Amount</div>
            </div>
        </div>

        <!-- Late Fees Records Table -->
        <div class="records-table">
            <div class="table-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Late Fee Details
                </h3>
                <div class="filters">
                    <button class="filter-btn active" onclick="filterRecords('all')">All</button>
                    <button class="filter-btn" onclick="filterRecords('unpaid')">Unpaid</button>
                    <button class="filter-btn" onclick="filterRecords('paid')">Paid</button>
                </div>
            </div>

            <?php if (!empty($late_fees)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php if ($role !== 'member'): ?>
                                    <th>Member Name</th>
                                <?php endif; ?>
                                <th>Cycle No.</th>
                                <th>Due Date</th>
                                <th>Payment Date</th>
                                <th>Days Late</th>
                                <th>Original Amount</th>
                                <th>Fine Amount</th>
                                <th>Status</th>
                                <?php if ($role === 'member'): ?>
                                    <th>Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($late_fees as $fee): ?>
                            <tr class="record-row" data-status="<?php echo $fee['is_paid'] ? 'paid' : 'unpaid'; ?>">
                                <?php if ($role !== 'member'): ?>
                                    <td><?php echo htmlspecialchars($fee['member_name']); ?></td>
                                <?php endif; ?>
                                <td><?php echo $fee['cycle_no']; ?></td>
                                <td><?php echo date('d M, Y', strtotime($fee['due_date'])); ?></td>
                                <td>
                                    <?php echo $fee['payment_date'] ? date('d M, Y', strtotime($fee['payment_date'])) : 'Not Paid'; ?>
                                </td>
                                <td><?php echo $fee['days_late']; ?></td>
                                <td class="amount-cell">₹<?php echo number_format($fee['payment_amount'], 2); ?></td>
                                <td class="amount-cell <?php echo $fee['is_paid'] ? 'amount-paid' : 'amount-unpaid'; ?>">
                                    ₹<?php echo number_format($fee['fine_amount'], 2); ?>
                                </td>
                                <td>
                                    <?php if ($fee['is_paid']): ?>
                                        <span class="status-badge status-paid">Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge status-unpaid">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($role === 'member' && !$fee['is_paid']): ?>
                                    <td>
                                        <a href="make_payment.php?type=late_fee&late_fee_id=<?php echo $fee['id']; ?>" class="action-btn">
                                            <i class="fas fa-credit-card"></i>
                                            Pay Now
                                        </a>
                                    </td>
                                <?php elseif ($role === 'member'): ?>
                                    <td>-</td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-records">
                    <i class="fas fa-file-invoice"></i>
                    <h3>No Late Fee Records Found</h3>
                    <p>There are no late fee records in your <?php echo $role === 'member' ? 'account' : 'group'; ?>.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Additional Information -->
        <div class="payment-info">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <h4>About Late Fees</h4>
                    <ul>
                        <li>Late fees are calculated automatically based on payment cycles</li>
                        <li><strong>Unpaid</strong> late fees are marked in red</li>
                        <li><strong>Paid</strong> late fees are marked in green</li>
                        <li>Status shows if the late fee has been paid or not</li>
                        <?php if ($role === 'member'): ?>
                            <li>Click "Pay Now" to pay any unpaid late fee</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <footer class="dashboard-footer">
        <p>
            <span class="hindi">© 2024 समूह प्लेटफॉर्म। सर्वाधिकार सुरक्षित।</span>
            <span class="english">© 2024 Samuh Platform. All rights reserved.</span>
        </p>
    </footer>

    <script>
        function filterRecords(status) {
            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide rows based on status
            const rows = document.querySelectorAll('.record-row');
            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else if (row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>