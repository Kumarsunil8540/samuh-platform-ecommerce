<?php
require_once 'config.php';
session_start();

// Enhanced session validation
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'leader' || !isset($_SESSION['group_id'])) {
    header('Location: login.php');
    exit();
}

$group_id = $_SESSION['group_id'];
$user_id = $_SESSION['user_id'];
$results = [];
$group_details = [];

// First, verify leader has access to this group
try {
    $verify_sql = "SELECT id FROM leaders WHERE id = ? AND group_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->execute([$user_id, $group_id]);
    $leader_verified = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leader_verified) {
        throw new Exception("Unauthorized access to this group!");
    }

    // Get group details - ONLY for leader's group
    $group_sql = "SELECT id, group_name, expected_amount, payment_cycle, 
                         payment_start_date, late_fee_type, late_fee_value 
                  FROM groups 
                  WHERE id = ?";
    $group_stmt = $conn->prepare($group_sql);
    $group_stmt->execute([$group_id]);
    $group_details = $group_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group_details) {
        throw new Exception("Group not found or access denied!");
    }
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    error_log("Late Fee Manager Security Error - User: {$_SESSION['user_id']}, Group: {$group_id}, Error: " . $e->getMessage());
    header('Location: unauthorized.php');
    exit();
}

// Progressive late fee calculation function
function calculateProgressiveLateFee($payment_amount, $days_late) {
    $progressive_rules = [
        ['min_days' => 0, 'max_days' => 3, 'fee_percentage' => 0, 'label' => '0-3 days'],
        ['min_days' => 4, 'max_days' => 7, 'fee_percentage' => 5, 'label' => '4-7 days'],
        ['min_days' => 8, 'max_days' => 10, 'fee_percentage' => 7, 'label' => '8-10 days'],
        ['min_days' => 11, 'max_days' => 15, 'fee_percentage' => 9, 'label' => '11-15 days'],
        ['min_days' => 16, 'max_days' => 20, 'fee_percentage' => 11, 'label' => '16-20 days'],
        ['min_days' => 21, 'max_days' => 25, 'fee_percentage' => 13, 'label' => '21-25 days'],
        ['min_days' => 26, 'max_days' => 30, 'fee_percentage' => 15, 'label' => '26-30 days'],
        ['min_days' => 31, 'max_days' => null, 'fee_percentage' => 17, 'label' => '31+ days']
    ];
    
    $applicable_percentage = 0;
    $applicable_rule = '';
    
    foreach ($progressive_rules as $rule) {
        $min_days = $rule['min_days'];
        $max_days = $rule['max_days'];
        
        if ($days_late >= $min_days && ($max_days === null || $days_late <= $max_days)) {
            $applicable_percentage = $rule['fee_percentage'];
            $applicable_rule = $rule['label'];
            break;
        }
    }
    
    $fine_amount = ($payment_amount * $applicable_percentage) / 100;
    return [
        'amount' => round($fine_amount, 2),
        'percentage' => $applicable_percentage,
        'rule' => $applicable_rule
    ];
}

// Calculate fine amount based on late fee type
function calculateFineAmount($late_fee_type, $late_fee_value, $expected_amount, $days_late) {
    switch ($late_fee_type) {
        case 'fixed':
            return [
                'amount' => $late_fee_value,
                'calculation' => "Fixed: ₹{$late_fee_value}"
            ];
            
        case 'per_day':
            $amount = $late_fee_value * $days_late;
            return [
                'amount' => $amount,
                'calculation' => "₹{$late_fee_value}/day × {$days_late} days = ₹{$amount}"
            ];
            
        case 'percent':
            $amount = ($expected_amount * $late_fee_value * $days_late) / 100;
            return [
                'amount' => $amount,
                'calculation' => "{$late_fee_value}% of ₹{$expected_amount} × {$days_late} days = ₹{$amount}"
            ];
            
        case 'progressive':
            $result = calculateProgressiveLateFee($expected_amount, $days_late);
            return [
                'amount' => $result['amount'],
                'calculation' => "Progressive ({$result['rule']}): {$result['percentage']}% of ₹{$expected_amount} = ₹{$result['amount']}"
            ];
            
        default:
            $amount = 5.00 * $days_late;
            return [
                'amount' => $amount,
                'calculation' => "Default ₹5/day × {$days_late} days = ₹{$amount}"
            ];
    }
}

// Process late fee calculation when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['calculate_late_fees'])) {
        try {
            // Double-check group access
            if ($group_details['id'] != $group_id) {
                throw new Exception("Security violation: Group ID mismatch!");
            }

            $group_name = $group_details['group_name'];
            $expected_amount = $group_details['expected_amount'];
            $payment_cycle = $group_details['payment_cycle'];
            $payment_start_date = $group_details['payment_start_date'];
            $late_fee_type = $group_details['late_fee_type'] ?? 'per_day';
            $late_fee_value = $group_details['late_fee_value'] ?? 5.00;
            
            $results = [
                'group_name' => $group_name,
                'members_checked' => 0,
                'late_fees_created' => 0,
                'total_fine' => 0,
                'member_details' => [],
                'calculation_summary' => [
                    'late_fee_type' => $late_fee_type,
                    'late_fee_value' => $late_fee_value,
                    'expected_amount' => $expected_amount,
                    'payment_cycle' => $payment_cycle
                ]
            ];
            
            // Get all ACTIVE members of THIS GROUP ONLY
            $members_sql = "SELECT id, full_name, mobile, total_contributions 
                           FROM members 
                           WHERE group_id = ? AND member_status = 'active'";
            $members_stmt = $conn->prepare($members_sql);
            $members_stmt->execute([$group_id]);
            $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results['members_checked'] = count($members);
            
            foreach ($members as $member) {
                $member_id = $member['id'];
                $member_name = $member['full_name'];
                $member_mobile = $member['mobile'];
                $total_contributions = $member['total_contributions'];
                
                $member_result = [
                    'member_name' => $member_name,
                    'member_mobile' => $member_mobile,
                    'total_contributions' => $total_contributions,
                    'late_fees' => 0,
                    'total_fine' => 0,
                    'cycles' => []
                ];
                
                // Calculate cycles from payment start date to today
                $start_date = new DateTime($payment_start_date);
                $today = new DateTime();
                $cycle_days = ($payment_cycle == 'weekly') ? 7 : 30;
                $days_diff = $start_date->diff($today)->days;
                $total_cycles = ceil($days_diff / $cycle_days);
                
                for ($cycle_no = 1; $cycle_no <= $total_cycles; $cycle_no++) {
                    $due_date = clone $start_date;
                    $due_date->modify("+" . (($cycle_no - 1) * $cycle_days) . " days");
                    
                    // Check if payment exists for this cycle - ONLY FOR THIS MEMBER IN THIS GROUP
                    $payment_sql = "SELECT payment_date, payment_amount 
                                   FROM payment_request 
                                   WHERE member_id = ? 
                                   AND group_id = ?
                                   AND payment_type = 'regular'
                                   AND payment_status = 'verified'
                                   AND DATE(payment_date) BETWEEN ? AND ?";
                    
                    $cycle_start = clone $due_date;
                    $cycle_end = clone $due_date;
                    $cycle_end->modify("+$cycle_days days");
                    
                    $payment_stmt = $conn->prepare($payment_sql);
                    $payment_stmt->execute([
                        $member_id, 
                        $group_id,
                        $cycle_start->format('Y-m-d'),
                        $cycle_end->format('Y-m-d')
                    ]);
                    $payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Check if late fee already exists - ONLY FOR THIS MEMBER IN THIS GROUP
                    $check_sql = "SELECT id, fine_amount FROM late_fees 
                                 WHERE member_id = ? AND group_id = ? AND cycle_no = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->execute([$member_id, $group_id, $cycle_no]);
                    $existing_fee = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $cycle_result = [
                        'cycle_no' => $cycle_no,
                        'due_date' => $due_date->format('Y-m-d'),
                        'status' => 'paid_on_time',
                        'payment_date' => null,
                        'days_late' => 0,
                        'fine_amount' => 0,
                        'calculation' => '',
                        'action' => 'none'
                    ];
                    
                    if ($existing_fee) {
                        $cycle_result['status'] = 'already_exists';
                        $cycle_result['fine_amount'] = $existing_fee['fine_amount'];
                        $cycle_result['action'] = 'existing';
                    } else {
                        $payment_made = false;
                        $payment_date_obj = null;
                        
                        if ($payment) {
                            $payment_made = true;
                            $payment_date_obj = new DateTime($payment['payment_date']);
                            $cycle_result['payment_date'] = $payment['payment_date'];
                        }
                        
                        $days_late = 0;
                        $fine_amount = 0;
                        $fine_calculation = '';
                        
                        // Case 1: Payment made but LATE
                        if ($payment_made && $payment_date_obj > $due_date) {
                            $days_late = $due_date->diff($payment_date_obj)->days;
                            $fine_calc = calculateFineAmount($late_fee_type, $late_fee_value, $expected_amount, $days_late);
                            $fine_amount = $fine_calc['amount'];
                            $fine_calculation = $fine_calc['calculation'];
                        }
                        // Case 2: No payment and due date PASSED
                        else if (!$payment_made && $today > $due_date) {
                            $days_late = $due_date->diff($today)->days;
                            $fine_calc = calculateFineAmount($late_fee_type, $late_fee_value, $expected_amount, $days_late);
                            $fine_amount = $fine_calc['amount'];
                            $fine_calculation = $fine_calc['calculation'];
                        }
                        
                        // Create late fee record if applicable
                        if ($fine_amount > 0) {
                            // Determine payment_date - use today's date if no payment made
                            $effective_payment_date = $payment_made ? $payment_date_obj->format('Y-m-d') : date('Y-m-d');
                            
                            $insert_sql = "
                                INSERT INTO late_fees 
                                (member_id, group_id, cycle_no, payment_date, due_date, 
                                 days_late, fine_amount, payment_amount, is_paid)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
                            ";
                            
                            $insert_stmt = $conn->prepare($insert_sql);
                            $insert_success = $insert_stmt->execute([
                                $member_id, 
                                $group_id, 
                                $cycle_no,
                                $effective_payment_date, // ✅ FIXED: Never NULL
                                $due_date->format('Y-m-d'),
                                $days_late,
                                $fine_amount,
                                $expected_amount
                            ]);
                            
                            if ($insert_success) {
                                $member_result['late_fees']++;
                                $member_result['total_fine'] += $fine_amount;
                                $results['late_fees_created']++;
                                $results['total_fine'] += $fine_amount;
                                
                                $cycle_result['status'] = 'late_fee_created';
                                $cycle_result['days_late'] = $days_late;
                                $cycle_result['fine_amount'] = $fine_amount;
                                $cycle_result['calculation'] = $fine_calculation;
                                $cycle_result['action'] = 'created';
                            }
                        }
                    }
                    
                    $member_result['cycles'][] = $cycle_result;
                }
                
                $results['member_details'][] = $member_result;
            }
            
            $success_message = "✅ Late fee calculation completed successfully!";
            
        } catch (Exception $e) {
            $error_message = "❌ Error: " . $e->getMessage();
            error_log("Late Fee Calculation Error - User: {$_SESSION['user_id']}, Group: {$group_id}, Error: " . $e->getMessage());
        }
    }
}

// Get existing late fees for display - ONLY FOR CURRENT GROUP
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$late_fees_sql = "SELECT SQL_CALC_FOUND_ROWS lf.*, m.full_name, m.mobile 
                 FROM late_fees lf 
                 JOIN members m ON lf.member_id = m.id 
                 WHERE lf.group_id = ? 
                 ORDER BY lf.is_paid ASC, lf.due_date DESC 
                 LIMIT $limit OFFSET $offset";
$late_fees_stmt = $conn->prepare($late_fees_sql);
$late_fees_stmt->execute([$group_id]);
$existing_late_fees = $late_fees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination - ONLY FOR CURRENT GROUP
$total_count_sql = "SELECT FOUND_ROWS() as total";
$total_count_stmt = $conn->query($total_count_sql);
$total_count = $total_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_count / $limit);

// Get late fee statistics - ONLY FOR CURRENT GROUP
$stats_sql = "SELECT 
                COUNT(*) as total_fees,
                SUM(fine_amount) as total_fine_amount,
                SUM(CASE WHEN is_paid = 1 THEN fine_amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN is_paid = 0 THEN fine_amount ELSE 0 END) as total_pending
              FROM late_fees 
              WHERE group_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute([$group_id]);
$late_fee_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Late Fee Manager - <?php echo htmlspecialchars($group_details['group_name'] ?? 'Samuh Platform'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card-stat {
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-2px);
        }
        .cycle-badge {
            font-size: 0.7rem;
            margin: 1px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.025);
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .security-alert {
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Security Header -->
        <div class="alert alert-info security-alert">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-shield-alt"></i> 
                    <strong>Secure Access:</strong> You are managing late fees for 
                    <strong><?php echo htmlspecialchars($group_details['group_name'] ?? 'Your Group'); ?></strong>
                </div>
                <div class="text-muted small">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?> 
                    | <i class="fas fa-users"></i> Group ID: <?php echo $group_id; ?>
                </div>
            </div>
        </div>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="fas fa-money-bill-wave text-primary"></i> Late Fee Manager</h2>
                <p class="text-muted mb-0">Manage late fees for your group members securely</p>
            </div>
            <a href="leader_dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Group Info Card -->
        <?php if ($group_details): ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users"></i> Group Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Group Name:</strong><br>
                        <span class="h6"><?php echo htmlspecialchars($group_details['group_name']); ?></span>
                    </div>
                    <div class="col-md-2">
                        <strong>Payment Cycle:</strong><br>
                        <span class="badge bg-info"><?php echo ucfirst($group_details['payment_cycle']); ?></span>
                    </div>
                    <div class="col-md-2">
                        <strong>Expected Amount:</strong><br>
                        <span class="h6">₹<?php echo number_format($group_details['expected_amount'], 2); ?></span>
                    </div>
                    <div class="col-md-2">
                        <strong>Late Fee Type:</strong><br>
                        <span class="badge bg-warning"><?php echo ucfirst(str_replace('_', ' ', $group_details['late_fee_type'])); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Group ID:</strong><br>
                        <code class="text-muted"><?php echo $group_id; ?></code>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card card-stat bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Late Fees</h6>
                                <h3><?php echo $late_fee_stats['total_fees'] ?? 0; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-receipt fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Collected</h6>
                                <h3>₹<?php echo number_format($late_fee_stats['total_paid'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Pending Collection</h6>
                                <h3>₹<?php echo number_format($late_fee_stats['total_pending'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Fine Amount</h6>
                                <h3>₹<?php echo number_format($late_fee_stats['total_fine_amount'] ?? 0, 2); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-rupee-sign fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calculation Section -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calculator"></i> Calculate Late Fees
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <p class="text-muted mb-3">
                            This will scan all payment cycles for <strong>active members in your group only</strong> and automatically create late fee records.
                        </p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Security Note:</strong> You can only manage late fees for your assigned group (Group ID: <?php echo $group_id; ?>)
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <form method="POST">
                            <button type="submit" name="calculate_late_fees" class="btn btn-primary btn-lg">
                                <i class="fas fa-bolt"></i> Calculate Late Fees
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger mt-3">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calculation Results -->
        <?php if (!empty($results)): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar"></i> Calculation Results
                    <span class="badge bg-light text-dark">Group: <?php echo htmlspecialchars($results['group_name']); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Members Checked</h6>
                                <h2 class="text-primary"><?php echo $results['members_checked']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Late Fees Created</h6>
                                <h2 class="text-warning"><?php echo $results['late_fees_created']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Total Fine Amount</h6>
                                <h2 class="text-success">₹<?php echo number_format($results['total_fine'], 2); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title text-muted">Fee Type</h6>
                                <span class="badge bg-info fs-6"><?php echo ucfirst(str_replace('_', ' ', $results['calculation_summary']['late_fee_type'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Member-wise Details -->
                <h6 class="border-bottom pb-2">
                    <i class="fas fa-list"></i> Member-wise Details
                    <span class="badge bg-secondary"><?php echo count($results['member_details']); ?> members</span>
                </h6>
                
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Member</th>
                                <th>Mobile</th>
                                <th>Total Paid</th>
                                <th>Late Fees</th>
                                <th>Total Fine</th>
                                <th>Late Cycles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['member_details'] as $member): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['member_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($member['member_mobile']); ?></td>
                                <td>₹<?php echo number_format($member['total_contributions'], 2); ?></td>
                                <td>
                                    <span class="badge bg-warning"><?php echo $member['late_fees']; ?></span>
                                </td>
                                <td>
                                    <span class="text-danger fw-bold">₹<?php echo number_format($member['total_fine'], 2); ?></span>
                                </td>
                                <td>
                                    <?php foreach ($member['cycles'] as $cycle): ?>
                                        <?php if ($cycle['action'] == 'created'): ?>
                                            <span class="badge bg-danger cycle-badge" 
                                                  data-bs-toggle="tooltip" 
                                                  title="Cycle <?php echo $cycle['cycle_no']; ?>: <?php echo $cycle['days_late']; ?> days late - <?php echo $cycle['calculation']; ?>">
                                                C<?php echo $cycle['cycle_no']; ?> (₹<?php echo $cycle['fine_amount']; ?>)
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Existing Late Fees -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Existing Late Fees
                    <span class="badge bg-primary"><?php echo $total_count; ?> records</span>
                    <small class="text-muted">(Group ID: <?php echo $group_id; ?>)</small>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($existing_late_fees)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No late fees found</h5>
                        <p class="text-muted">Run the calculation to generate late fees for your group.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Member</th>
                                    <th>Mobile</th>
                                    <th>Cycle</th>
                                    <th>Due Date</th>
                                    <th>Payment Date</th>
                                    <th>Days Late</th>
                                    <th>Fine Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existing_late_fees as $fee): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($fee['full_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($fee['mobile']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $fee['cycle_no']; ?></span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($fee['due_date'])); ?></td>
                                    <td>
                                        <?php if ($fee['payment_date']): ?>
                                            <?php echo date('d M Y', strtotime($fee['payment_date'])); ?>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?php echo $fee['days_late']; ?> days</span>
                                    </td>
                                    <td>
                                        <strong class="text-success">₹<?php echo number_format($fee['fine_amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($fee['is_paid']): ?>
                                            <span class="badge bg-success status-badge">Paid</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning status-badge">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Late fees pagination">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>