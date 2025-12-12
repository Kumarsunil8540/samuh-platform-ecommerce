<?php
session_start();
include("config.php");

// Check if user is logged in and is either accountant or leader
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['accountant', 'leader'])) {
    header("Location: core_member_login.php?role=" . $_SESSION['role'] ?? 'accountant');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];
$group_id = $_SESSION['group_id'];

$success_message = '';
$error_message = '';

// Handle QR Upload (Accountant only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_qr']) && $role === 'accountant') {
    if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['qr_image'];
        
        // Check file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $error_message = "❌ Only JPG, PNG, GIF images are allowed!";
        } else {
            // Create upload directory if not exists
            $upload_dir = "uploads/qr_codes/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = "qr_" . time() . "_" . uniqid() . "." . $file_extension;
            $file_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                try {
                    // Insert QR record
                    $sql = "INSERT INTO qr_records (group_id, uploaded_by, qr_image, is_active) 
                            VALUES (?, ?, ?, FALSE)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$group_id, $username, $file_path]);
                    
                    $success_message = "✅ QR uploaded successfully! Waiting for leader verification.";
                    
                } catch (PDOException $e) {
                    $error_message = "❌ Database error: " . $e->getMessage();
                }
            } else {
                $error_message = "❌ File upload failed!";
            }
        }
    } else {
        $error_message = "❌ Please select a QR image file!";
    }
}

// Handle QR Verification (Leader only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_qr']) && $role === 'leader') {
    $qr_id = $_POST['qr_id'];
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Deactivate all active QRs for this group
        $deactivate_sql = "UPDATE qr_records SET is_active = FALSE WHERE group_id = ?";
        $deactivate_stmt = $conn->prepare($deactivate_sql);
        $deactivate_stmt->execute([$group_id]);
        
        // Activate the selected QR
        $activate_sql = "UPDATE qr_records SET 
                        is_active = TRUE, 
                        verified_by = ?, 
                        verify_date = NOW() 
                        WHERE id = ? AND group_id = ?";
        $activate_stmt = $conn->prepare($activate_sql);
        $activate_stmt->execute([$username, $qr_id, $group_id]);
        
        $conn->commit();
        $success_message = "✅ QR Verified & Activated Successfully!";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "❌ Verification failed: " . $e->getMessage();
    }
}

// Handle QR Deactivation (Leader only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_qr']) && $role === 'leader') {
    $qr_id = $_POST['qr_id'];
    
    try {
        // Deactivate the QR
        $deactivate_sql = "UPDATE qr_records SET 
                          is_active = FALSE,
                          verified_by = NULL,
                          verify_date = NULL
                          WHERE id = ? AND group_id = ?";
        $deactivate_stmt = $conn->prepare($deactivate_sql);
        $deactivate_stmt->execute([$qr_id, $group_id]);
        
        $success_message = "✅ QR Deactivated Successfully!";
        
    } catch (PDOException $e) {
        $error_message = "❌ Deactivation failed: " . $e->getMessage();
    }
}

// Fetch data based on role
try {
    if ($role === 'accountant') {
        // Accountant: Show all QRs uploaded by them
        $qr_sql = "SELECT * FROM qr_records 
                  WHERE group_id = ? 
                  ORDER BY upload_date DESC";
        $qr_stmt = $conn->prepare($qr_sql);
        $qr_stmt->execute([$group_id]);
        $qr_records = $qr_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else if ($role === 'leader') {
        // Leader: Show pending QRs (not verified yet)
        $qr_sql = "SELECT * FROM qr_records 
                  WHERE group_id = ? AND verified_by IS NULL 
                  ORDER BY upload_date DESC";
        $qr_stmt = $conn->prepare($qr_sql);
        $qr_stmt->execute([$group_id]);
        $pending_qrs = $qr_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Also get currently active QR
        $active_sql = "SELECT * FROM qr_records 
                      WHERE group_id = ? AND is_active = TRUE 
                      LIMIT 1";
        $active_stmt = $conn->prepare($active_sql);
        $active_stmt->execute([$group_id]);
        $active_qr = $active_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get all verified QRs (including inactive ones)
        $verified_sql = "SELECT * FROM qr_records 
                        WHERE group_id = ? AND verified_by IS NOT NULL 
                        ORDER BY verify_date DESC";
        $verified_stmt = $conn->prepare($verified_sql);
        $verified_stmt->execute([$group_id]);
        $verified_qrs = $verified_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("QR Page Error: " . $e->getMessage());
    $error_message = "❌ Database error occurred!";
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Management - Samuh</title>
    <link rel="stylesheet" href="qr_change_page.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="qr-header">
        <div class="header-left">
            <div class="logo">
                <i class="fas fa-qrcode"></i>
                <span>QR Management</span>
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
                <a href="qr_history.php" class="action-btn">
                    <i class="fas fa-history"></i>
                    <span>View History</span>
                </a>
                <a href="<?php echo $role . '_dashboard.php'; ?>" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>
    </header>

    <main class="qr-main">
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

        <!-- QR Upload Section (Visible only to Accountant) -->
        <?php if ($role === 'accountant'): ?>
            <section class="upload-section">
                <div class="section-header">
                    <h2><i class="fas fa-upload"></i> QR Upload Section</h2>
                    <p>Upload new QR code for your group</p>
                </div>

                <div class="upload-form">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="qr_image">Select QR Image</label>
                            <div class="file-input-container">
                                <input type="file" name="qr_image" id="qr_image" accept="image/*" required class="file-input">
                                <label for="qr_image" class="file-input-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose QR Image File</span>
                                </label>
                            </div>
                            <small>Supported formats: JPG, PNG, GIF (Max: 2MB)</small>
                        </div>

                        <button type="submit" name="upload_qr" class="btn btn-primary btn-upload">
                            <i class="fas fa-upload"></i>
                            Upload New QR
                        </button>
                    </form>
                </div>

                <!-- Upload Success Message -->
                <div class="info-message">
                    <i class="fas fa-info-circle"></i>
                    <p>QR uploaded successfully. Waiting for leader verification.</p>
                </div>
            </section>
        <?php endif; ?>

        <!-- QR Verification Section (Visible only to Leader) -->
        <?php if ($role === 'leader'): ?>
            <section class="verification-section">
                <div class="section-header">
                    <h2><i class="fas fa-check-circle"></i> QR Management Section</h2>
                    <p>Manage QR codes for your group</p>
                </div>

                <!-- Currently Active QR -->
                <?php if ($active_qr): ?>
                    <div class="active-qr-card">
                        <h3>Currently Active QR</h3>
                        <div class="qr-preview">
                            <img src="<?php echo htmlspecialchars($active_qr['qr_image']); ?>" alt="Active QR Code">
                            <div class="qr-info">
                                <p><strong>Uploaded by:</strong> <?php echo htmlspecialchars($active_qr['uploaded_by']); ?></p>
                                <p><strong>Verified on:</strong> <?php echo date('M j, Y g:i A', strtotime($active_qr['verify_date'])); ?></p>
                                <span class="status-badge active">Active</span>
                            </div>
                        </div>
                        
                        <form method="POST" class="deactivate-form">
                            <input type="hidden" name="qr_id" value="<?php echo $active_qr['id']; ?>">
                            <button type="submit" name="deactivate_qr" class="btn btn-warning btn-deactivate" 
                                    onclick="return confirm('Are you sure you want to deactivate this QR code?')">
                                <i class="fas fa-times-circle"></i>
                                Deactivate QR
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="no-active-qr">
                        <i class="fas fa-qrcode"></i>
                        <p>No active QR code for your group</p>
                    </div>
                <?php endif; ?>

                <!-- Pending QR List -->
                <div class="pending-qr-section">
                    <h3>Pending QR Verification</h3>
                    
                    <?php if (!empty($pending_qrs)): ?>
                        <div class="qr-list">
                            <?php foreach ($pending_qrs as $qr): ?>
                                <div class="qr-item">
                                    <div class="qr-preview">
                                        <img src="<?php echo htmlspecialchars($qr['qr_image']); ?>" alt="QR Code">
                                        <div class="qr-details">
                                            <p><strong>Uploaded by:</strong> <?php echo htmlspecialchars($qr['uploaded_by']); ?></p>
                                            <p><strong>Uploaded on:</strong> <?php echo date('M j, Y g:i A', strtotime($qr['upload_date'])); ?></p>
                                            <span class="status-badge pending">Pending Verification</span>
                                        </div>
                                    </div>
                                    
                                    <form method="POST" class="verify-form">
                                        <input type="hidden" name="qr_id" value="<?php echo $qr['id']; ?>">
                                        <button type="submit" name="verify_qr" class="btn btn-success btn-verify">
                                            <i class="fas fa-check"></i>
                                            Verify & Activate
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-pending-qr">
                            <i class="fas fa-check"></i>
                            <p>No pending QR codes for verification</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Verified QR List (Inactive) -->
                <?php if (!empty($verified_qrs)): ?>
                    <div class="verified-qr-section">
                        <h3>Verified QR Codes (Inactive)</h3>
                        <div class="qr-list">
                            <?php foreach ($verified_qrs as $qr): ?>
                                <?php if (!$qr['is_active']): ?>
                                    <div class="qr-item">
                                        <div class="qr-preview">
                                            <img src="<?php echo htmlspecialchars($qr['qr_image']); ?>" alt="QR Code">
                                            <div class="qr-details">
                                                <p><strong>Uploaded by:</strong> <?php echo htmlspecialchars($qr['uploaded_by']); ?></p>
                                                <p><strong>Verified by:</strong> <?php echo htmlspecialchars($qr['verified_by']); ?></p>
                                                <p><strong>Verified on:</strong> <?php echo date('M j, Y g:i A', strtotime($qr['verify_date'])); ?></p>
                                                <span class="status-badge verified">Verified (Inactive)</span>
                                            </div>
                                        </div>
                                        
                                        <div class="action-buttons">
                                            <form method="POST" class="verify-form">
                                                <input type="hidden" name="qr_id" value="<?php echo $qr['id']; ?>">
                                                <button type="submit" name="verify_qr" class="btn btn-success btn-verify">
                                                    <i class="fas fa-check"></i>
                                                    Activate Again
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Verification Success Message -->
                <div class="info-message">
                    <i class="fas fa-info-circle"></i>
                    <p>QR Verified & Activated Successfully.</p>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="qr-footer">
        <p>&copy; 2025 Samuh Platform. All rights reserved.</p>
    </footer>
</body>
</html>