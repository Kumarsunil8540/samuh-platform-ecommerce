<?php
session_start();
require_once 'config.php'; // Assuming this file contains your PDO database connection setup

// ------------------------------------------
// 1. Authentication and Authorization Check
// ------------------------------------------

// Check if user is logged in
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_group_id = $_SESSION['group_id'];
$message = '';

// Check if the role is authorized to view this page
$allowed_roles = ['admin', 'leader', 'accountant','member'];
if (!in_array($user_role, $allowed_roles)) {
    // Redirect unauthorized users (e.g., if a member tries to access this)
    header("Location: unauthorized.php");
    exit();
}

// ------------------------------------------
// 2. Group Selection Logic
// ------------------------------------------

$groups = [];
$selected_group_id = null; // Default to null

// If the user is associated with a group (Leader, Accountant), default to their group
if ($user_group_id) {
    $selected_group_id = $user_group_id;
}

if ($user_role === 'admin') {
    // Admin can see all active groups
    try {
        $stmt = $conn->prepare("SELECT id, group_name FROM groups WHERE status = 'active' ORDER BY group_name");
        $stmt->execute();
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If admin hasn't selected a group yet, and there are groups, pre-select the first one
        if (!$selected_group_id && count($groups) > 0) {
            $selected_group_id = $groups[0]['id'];
        }

    } catch (PDOException $e) {
        $message = "Error fetching groups: " . $e->getMessage();
    }
} else {
    // Other users can only see their own active group
    try {
        $stmt = $conn->prepare("SELECT id, group_name FROM groups WHERE id = ? AND status = 'active'");
        $stmt->execute([$user_group_id]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Error fetching user group: " . $e->getMessage();
    }
}

// Handle POST/GET group selection override (primarily for Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
    $selected_group_id = $_POST['group_id'];
} elseif (isset($_GET['group_id'])) {
    $selected_group_id = $_GET['group_id'];
}

// ------------------------------------------
// 3. Fetch Data for Selected Group
// ------------------------------------------

$group_summary = [];
$members_data = [];
$total_verified_amount = 0;

if ($selected_group_id) {
    try {
        // A. Get group name and total verified amount
        $stmt = $conn->prepare("
            SELECT 
                g.group_name,
                COALESCE(SUM(pr.payment_amount), 0) as total_verified_amount
            FROM groups g
            LEFT JOIN payment_request pr ON g.id = pr.group_id AND pr.payment_status = 'verified'
            WHERE g.id = ?
            GROUP BY g.id
        ");
        $stmt->execute([$selected_group_id]);
        $group_summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_verified_amount = $group_summary['total_verified_amount'] ?? 0;

        // B. Fetch all ACTIVE members with their payment stats
        // *** FIX APPLIED HERE: m.is_active is replaced by m.member_status = 'active' ***
        $sql = "
            SELECT 
                m.id,
                m.full_name,
                m.photo_path,
                m.mobile,
                m.email,
                m.joining_date,
                COALESCE(SUM(CASE WHEN pr.payment_status = 'verified' THEN pr.payment_amount ELSE 0 END), 0) as total_verified_amount,
                COUNT(CASE WHEN pr.payment_status = 'verified' THEN 1 END) as verified_payments_count,
                COUNT(CASE WHEN pr.payment_status = 'pending' THEN 1 END) as pending_payments_count,
                COUNT(CASE WHEN pr.payment_status = 'rejected' THEN 1 END) as rejected_payments_count
            FROM members m
            LEFT JOIN payment_request pr ON m.id = pr.member_id
            WHERE m.group_id = ? AND m.member_status = 'active'
            GROUP BY m.id
            ORDER BY m.full_name
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$selected_group_id]);
        $members_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Display a general error message while logging the detailed one if necessary
        $message = "Database error: Failed to fetch payment data. (" . $e->getMessage() . ")";
    }
}

// Determine dashboard link based on role
$dashboard_link = $user_role === 'admin' ? 'admin_dashboard.php' : ($user_role === 'leader' ? 'leader_dashboard.php' : 'accountant_dashboard.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Payment History - Samuh Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --light-bg: #f8f9fa;
            --card-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.12);
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        
        .card {
            box-shadow: var(--card-shadow);
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px); /* Slightly less transform for subtleness */
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eaeaea;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .summary-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .member-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
            transform: scale(1.00); /* Removed extra scale for cleaner look */
            transition: all 0.2s ease;
        }
        
        .table-hover tbody tr:hover .member-photo {
            border-color: var(--primary-color);
        }
        
        .amount-badge {
            font-size: 0.9em;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .stats-badge {
            font-size: 0.75em;
            padding: 4px 8px;
            margin: 1px;
            border-radius: 12px;
        }
        
        .back-btn {
            margin-bottom: 20px;
        }
        
        .page-header {
            border-bottom: none;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .page-title {
            color: #2d3748;
            font-weight: 700;
            position: relative;
            display: inline-block;
        }
        
        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-color), var(--success-color));
            border-radius: 2px;
        }
        
        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            background: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stats-card:hover {
             transform: translateY(-5px);
             box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        
        .stats-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            display: inline-block;
            padding: 15px;
            border-radius: 12px;
        }
        
        .stats-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .verified-icon {
            background: rgba(76, 201, 240, 0.15);
            color: #4cc9f0;
        }
        
        .pending-icon {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .rejected-icon {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
        }
        
        .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #6c757d;
            background-color: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px 12px;
            background-color: #f8fafc;
        }
        
        .table td {
            padding: 15px 12px;
            vertical-align: middle;
        }
        
        @media (max-width: 768px) {
            .summary-card {
                padding: 20px;
            }
            
            .stats-value {
                font-size: 1.5rem;
            }
            
            .table-responsive {
                border-radius: 12px;
                border: 1px solid #eaeaea;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="back-btn">
            <a href="<?php echo $dashboard_link; ?>" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-history me-2"></i>
                    Group Payment History
                </h1>
                <p class="text-muted mt-2">View payment records for all **active** group members</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group">
                    <span class="badge bg-primary p-2 px-3">
                        <i class="fas fa-user me-1"></i>Role: <?php echo ucfirst($user_role); ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div><?php echo $message; ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($user_role === 'admin' && count($groups) > 0): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Select Group
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <label class="form-label">Choose a group to view payment history</label>
                        <select class="form-select" name="group_id" onchange="this.form.submit()">
                            <option value="">-- Select Group --</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>" 
                                    <?php echo $selected_group_id == $group['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($group['group_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($selected_group_id && $group_summary): ?>
        
        <div class="row mb-4">
            <div class="col-12">
                <div class="summary-card">
                    <div class="row align-items-center position-relative" style="z-index: 1;">
                        <div class="col-md-8">
                            <h3 class="mb-2 fw-bold"><?php echo htmlspecialchars($group_summary['group_name']); ?></h3>
                            <p class="mb-0 opacity-75">Total verified payments across all members</p>
                        </div>
                        <div class="col-md-4 text-md-end text-start mt-3 mt-md-0">
                            <h1 class="display-4 fw-bold mb-0">₹<?php echo number_format($total_verified_amount, 2); ?></h1>
                            <span class="badge bg-light text-dark fs-6 mt-2">
                                <i class="fas fa-check-circle me-1"></i>Verified Amount
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <?php
            // Calculate statistics
            $total_members = count($members_data);
            $total_verified = array_sum(array_column($members_data, 'verified_payments_count'));
            $total_pending = array_sum(array_column($members_data, 'pending_payments_count'));
            $total_rejected = array_sum(array_column($members_data, 'rejected_payments_count'));
            ?>
            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                <div class="stats-card">
                    <div class="stats-icon verified-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-value"><?php echo $total_members; ?></div>
                    <div class="stats-label">Active Members</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                <div class="stats-card">
                    <div class="stats-icon verified-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-value"><?php echo $total_verified; ?></div>
                    <div class="stats-label">Verified Payments</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3 mb-sm-0">
                <div class="stats-card">
                    <div class="stats-icon pending-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-value"><?php echo $total_pending; ?></div>
                    <div class="stats-label">Pending Payments</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-icon rejected-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stats-value"><?php echo $total_rejected; ?></div>
                    <div class="stats-label">Rejected Payments</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list-alt me-2"></i>
                    Members Payment Summary
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($members_data) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Member</th>
                                    <th>Contact</th>
                                    <th>Verified Amount</th>
                                    <th>Payment Stats (V/P/R)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members_data as $member): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                    $photo_path = !empty($member['photo_path']) && file_exists($member['photo_path']) ? htmlspecialchars($member['photo_path']) : 'assets/images/default-user.png'; // Assuming a default image path
                                                ?>
                                                <img src="<?php echo $photo_path; ?>" 
                                                    alt="<?php echo htmlspecialchars($member['full_name']); ?>" 
                                                    class="member-photo me-3">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        Joined: <?php echo date('d M Y', strtotime($member['joining_date'] ?? 'N/A')); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($member['mobile']); ?>
                                                </small>
                                                <small class="text-muted">
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($member['email']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success amount-badge">
                                                ₹<?php echo number_format($member['total_verified_amount'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php if ($member['verified_payments_count'] > 0): ?>
                                                    <span class="badge bg-success stats-badge" title="Verified Payments">
                                                        <i class="fas fa-check me-1"></i><?php echo $member['verified_payments_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($member['pending_payments_count'] > 0): ?>
                                                    <span class="badge bg-warning stats-badge text-dark" title="Pending Payments">
                                                        <i class="fas fa-clock me-1"></i><?php echo $member['pending_payments_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($member['rejected_payments_count'] > 0): ?>
                                                    <span class="badge bg-danger stats-badge" title="Rejected Payments">
                                                        <i class="fas fa-times me-1"></i><?php echo $member['rejected_payments_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($member['verified_payments_count'] == 0 && $member['pending_payments_count'] == 0 && $member['rejected_payments_count'] == 0): ?>
                                                    <span class="badge bg-secondary stats-badge">No Payments</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="member_payment_details.php?member_id=<?php echo $member['id']; ?>&group_id=<?php echo $selected_group_id; ?>" 
                                                class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>No Active Members</h4>
                        <p class="text-muted">No active members found or no payment data recorded in this group.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php elseif ($selected_group_id): ?>
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h4>Group Not Found / Inactive</h4>
                <p class="text-muted">The selected group was not found or is currently inactive.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <h4>Select a Group</h4>
                <p class="text-muted">Please select a group to view payment history.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
        
        // Add animation to cards on page load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .summary-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>