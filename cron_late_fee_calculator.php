<?php
require_once 'config.php';

// Same calculateProgressiveLateFee function as before
function calculateProgressiveLateFee($payment_amount, $days_late) {
    $progressive_rules = [
        ['min_days' => 0, 'max_days' => 3, 'fee_percentage' => 0],
        ['min_days' => 4, 'max_days' => 7, 'fee_percentage' => 5],
        ['min_days' => 8, 'max_days' => 10, 'fee_percentage' => 7],
        ['min_days' => 11, 'max_days' => 15, 'fee_percentage' => 9],
        ['min_days' => 16, 'max_days' => 20, 'fee_percentage' => 11],
        ['min_days' => 21, 'max_days' => 25, 'fee_percentage' => 13],
        ['min_days' => 26, 'max_days' => 30, 'fee_percentage' => 15],
        ['min_days' => 31, 'max_days' => null, 'fee_percentage' => 17]
    ];
    
    $applicable_percentage = 0;
    foreach ($progressive_rules as $rule) {
        $min_days = $rule['min_days'];
        $max_days = $rule['max_days'];
        
        if ($days_late >= $min_days && ($max_days === null || $days_late <= $max_days)) {
            $applicable_percentage = $rule['fee_percentage'];
            break;
        }
    }
    
    $fine_amount = ($payment_amount * $applicable_percentage) / 100;
    return round($fine_amount, 2);
}

// Automatic late fee calculation for ALL groups
function calculateAllGroupsLateFees($conn) {
    $results = [
        'total_groups_processed' => 0,
        'total_members_checked' => 0,
        'total_late_fees_created' => 0,
        'total_fine_amount' => 0,
        'group_details' => [],
        'errors' => []
    ];
    
    try {
        // Get all active groups
        $sql = "SELECT id, group_name, expected_amount, payment_cycle, 
                       payment_start_date, late_fee_type, late_fee_value 
                FROM groups 
                WHERE status = 'active' 
                AND payment_start_date IS NOT NULL";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results['total_groups_processed'] = count($groups);
        
        foreach ($groups as $group) {
            $group_id = $group['id'];
            $group_name = $group['group_name'];
            $expected_amount = $group['expected_amount'];
            $payment_cycle = $group['payment_cycle'];
            $payment_start_date = $group['payment_start_date'];
            $late_fee_type = $group['late_fee_type'] ?? 'per_day';
            $late_fee_value = $group['late_fee_value'] ?? 5.00;
            
            $group_result = [
                'group_id' => $group_id,
                'group_name' => $group_name,
                'members_checked' => 0,
                'late_fees_created' => 0,
                'total_fine' => 0
            ];
            
            // Get all active members
            $members_sql = "SELECT id, full_name FROM members 
                           WHERE group_id = ? AND is_active = 1";
            $members_stmt = $conn->prepare($members_sql);
            $members_stmt->execute([$group_id]);
            $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $group_result['members_checked'] = count($members);
            $results['total_members_checked'] += count($members);
            
            foreach ($members as $member) {
                $member_id = $member['id'];
                $member_name = $member['full_name'];
                
                // Calculate cycles
                $start_date = new DateTime($payment_start_date);
                $today = new DateTime();
                $cycle_days = ($payment_cycle == 'weekly') ? 7 : 30;
                $days_diff = $start_date->diff($today)->days;
                $total_cycles = ceil($days_diff / $cycle_days);
                
                for ($cycle_no = 1; $cycle_no <= $total_cycles; $cycle_no++) {
                    $due_date = clone $start_date;
                    $due_date->modify("+" . (($cycle_no - 1) * $cycle_days) . " days");
                    
                    // Check if payment exists
                    $payment_sql = "SELECT payment_date, payment_amount 
                                   FROM payment_request 
                                   WHERE member_id = ? 
                                   AND group_id = ?
                                   AND payment_status = 'verified'
                                   AND payment_date BETWEEN ? AND ?";
                    
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
                    
                    // Check if late fee already exists
                    $check_sql = "SELECT id FROM late_fees 
                                 WHERE member_id = ? AND group_id = ? AND cycle_no = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->execute([$member_id, $group_id, $cycle_no]);
                    $existing_fee = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$existing_fee) {
                        // No payment found and due date passed
                        if (!$payment && $today > $due_date) {
                            $days_late = $due_date->diff($today)->days;
                            $fine_amount = calculateFineAmount($late_fee_type, $late_fee_value, $expected_amount, $days_late);
                            
                            if ($fine_amount > 0) {
                                insertLateFee($conn, $member_id, $group_id, $cycle_no, $due_date, null, $days_late, $fine_amount, $expected_amount);
                                $group_result['late_fees_created']++;
                                $group_result['total_fine'] += $fine_amount;
                                $results['total_late_fees_created']++;
                                $results['total_fine_amount'] += $fine_amount;
                            }
                        }
                        // Payment exists but is late
                        else if ($payment) {
                            $payment_date = new DateTime($payment['payment_date']);
                            if ($payment_date > $due_date) {
                                $days_late = $due_date->diff($payment_date)->days;
                                $fine_amount = calculateFineAmount($late_fee_type, $late_fee_value, $expected_amount, $days_late);
                                
                                if ($fine_amount > 0) {
                                    insertLateFee($conn, $member_id, $group_id, $cycle_no, $due_date, $payment_date, $days_late, $fine_amount, $expected_amount);
                                    $group_result['late_fees_created']++;
                                    $group_result['total_fine'] += $fine_amount;
                                    $results['total_late_fees_created']++;
                                    $results['total_fine_amount'] += $fine_amount;
                                }
                            }
                        }
                    }
                }
            }
            
            $results['group_details'][] = $group_result;
        }
        
    } catch (PDOException $e) {
        $results['errors'][] = "Database error: " . $e->getMessage();
    }
    
    return $results;
}

function calculateFineAmount($late_fee_type, $late_fee_value, $expected_amount, $days_late) {
    switch ($late_fee_type) {
        case 'fixed':
            return $late_fee_value;
        case 'per_day':
            return $late_fee_value * $days_late;
        case 'percent':
            return ($expected_amount * $late_fee_value * $days_late) / 100;
        case 'progressive':
            return calculateProgressiveLateFee($expected_amount, $days_late);
        default:
            return 5.00 * $days_late; // default per day
    }
}

function insertLateFee($conn, $member_id, $group_id, $cycle_no, $due_date, $payment_date, $days_late, $fine_amount, $expected_amount) {
    $insert_sql = "
        INSERT INTO late_fees 
        (member_id, group_id, cycle_no, payment_date, due_date, 
         days_late, fine_amount, payment_amount, is_paid)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
    ";
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->execute([
        $member_id, 
        $group_id, 
        $cycle_no,
        $payment_date ? $payment_date->format('Y-m-d') : null,
        $due_date->format('Y-m-d'),
        $days_late,
        $fine_amount,
        $expected_amount
    ]);
}

// Execute automatic calculation
$results = calculateAllGroupsLateFees($conn);

// Log results
$log_message = date('Y-m-d H:i:s') . " - AUTO Late Fee Calculation: " . 
               "Groups: " . $results['total_groups_processed'] . 
               ", Members: " . $results['total_members_checked'] .
               ", Late Fees Created: " . $results['total_late_fees_created'] . 
               ", Total Fine: ₹" . number_format($results['total_fine_amount'], 2) . "\n";
file_put_contents('auto_late_fee_calculation.log', $log_message, FILE_APPEND);

// For CLI output
if (php_sapi_name() === 'cli') {
    echo "Late Fee Calculation Completed:\n";
    echo "Groups: " . $results['total_groups_processed'] . "\n";
    echo "Members: " . $results['total_members_checked'] . "\n";
    echo "Late Fees Created: " . $results['total_late_fees_created'] . "\n";
    echo "Total Fine: ₹" . number_format($results['total_fine_amount'], 2) . "\n";
}
?>