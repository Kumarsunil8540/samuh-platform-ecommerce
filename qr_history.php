<?php
session_start();
include("config.php");

// Check if user is logged in (all roles allowed)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: core_member_login.php?role=member");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];
$group_id = $_SESSION['group_id'];

// Fetch QR history for the group
try {
    $history_sql = "SELECT qr.*, 
                   CASE 
                       WHEN qr.is_active = TRUE THEN 'Active'
                       WHEN qr.verified_by IS NOT NULL THEN 'Verified'
                       ELSE 'Pending'
                   END as status_display
                   FROM qr_records qr 
                   WHERE qr.group_id = ? 
                   ORDER BY qr.upload_date DESC";
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->execute([$group_id]);
    $qr_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get group name
    $group_sql = "SELECT group_name FROM groups WHERE id = ?";
    $group_stmt = $conn->prepare($group_sql);
    $group_stmt->execute([$group_id]);
    $group = $group_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("QR History Error: " . $e->getMessage());
    $error_message = "❌ Database error occurred!";
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR History - Samuh</title>
    <link rel="stylesheet" href="qr_history.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="history-header">
        <div class="header-left">
            <div class="logo">
                <i class="fas fa-history"></i>
                <span>QR History</span>
            </div>
            <div class="group-info">
                <span class="group-name">Group: <?php echo htmlspecialchars($group['group_name']); ?></span>
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
            
            <div class="header-actions">
                <?php if (in_array($role, ['accountant', 'leader'])): ?>
                    <a href="qr_change_page.php" class="action-btn">
                        <i class="fas fa-qrcode"></i>
                        <span>Manage QR</span>
                    </a>
                <?php endif; ?>
                
                <a href="<?php echo $role . '_dashboard.php'; ?>" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>
    </header>

    <main class="history-main">
        <!-- Page Header -->
        <section class="page-header">
            <h1>QR Code History</h1>
            <p>Complete history of all QR codes uploaded for your group</p>
            
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number">
                            <?php 
                            $active_count = 0;
                            foreach ($qr_history as $qr) {
                                if ($qr['is_active']) $active_count++;
                            }
                            echo $active_count;
                            ?>
                        </span>
                        <span class="stat-label">Active QR</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon verified">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number">
                            <?php 
                            $verified_count = 0;
                            foreach ($qr_history as $qr) {
                                if ($qr['verified_by'] && !$qr['is_active']) $verified_count++;
                            }
                            echo $verified_count;
                            ?>
                        </span>
                        <span class="stat-label">Verified</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number">
                            <?php 
                            $pending_count = 0;
                            foreach ($qr_history as $qr) {
                                if (!$qr['verified_by']) $pending_count++;
                            }
                            echo $pending_count;
                            ?>
                        </span>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo count($qr_history); ?></span>
                        <span class="stat-label">Total QR</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- QR History Table -->
        <section class="history-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> All QR Records</h2>
                <div class="section-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by uploaded by...">
                    </div>
                </div>
            </div>

            <?php if (!empty($qr_history)): ?>
                <div class="history-grid" id="historyGrid">
                    <?php foreach ($qr_history as $qr): ?>
                        <div class="history-card" data-uploader="<?php echo strtolower($qr['uploaded_by']); ?>">
                            <div class="card-header">
                                <div class="qr-status">
                                    <?php if ($qr['is_active']): ?>
                                        <span class="status-badge active">
                                            <i class="fas fa-check-circle"></i>
                                            Active
                                        </span>
                                    <?php elseif ($qr['verified_by']): ?>
                                        <span class="status-badge verified">
                                            <i class="fas fa-check"></i>
                                            Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge pending">
                                            <i class="fas fa-clock"></i>
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-actions">
                                    <button class="view-btn" onclick="viewQR('<?php echo htmlspecialchars($qr['qr_image']); ?>')">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="qr-image-container">
                                <img src="<?php echo htmlspecialchars($qr['qr_image']); ?>" 
                                     alt="QR Code" 
                                     class="qr-image"
                                     onclick="viewQR('<?php echo htmlspecialchars($qr['qr_image']); ?>')">
                            </div>
                            
                            <div class="card-details">
                                <div class="detail-item">
                                    <i class="fas fa-user-upload"></i>
                                    <div class="detail-content">
                                        <span class="detail-label">Uploaded By</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($qr['uploaded_by']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <i class="fas fa-calendar-plus"></i>
                                    <div class="detail-content">
                                        <span class="detail-label">Upload Date</span>
                                        <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($qr['upload_date'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($qr['verified_by']): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-user-check"></i>
                                        <div class="detail-content">
                                            <span class="detail-label">Verified By</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($qr['verified_by']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div class="detail-content">
                                            <span class="detail-label">Verified Date</span>
                                            <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($qr['verify_date'])); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-history">
                    <div class="no-history-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3>No QR History Found</h3>
                    <p>No QR codes have been uploaded for your group yet.</p>
                    <?php if (in_array($role, ['accountant', 'leader'])): ?>
                        <a href="qr_change_page.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Upload First QR
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- QR Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>QR Code Preview</h3>
                <button class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <img id="modalQrImage" src="" alt="QR Code" class="modal-qr-image">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    Close
                </button>
                <button class="btn btn-primary" onclick="downloadQR()">
                    <i class="fas fa-download"></i>
                    Download
                </button>
            </div>
        </div>
    </div>

    <footer class="history-footer">
        <p>&copy; 2025 Samuh Platform. All rights reserved.</p>
    </footer>

    <script src="qr_history.js"></script>
</body>
</html>