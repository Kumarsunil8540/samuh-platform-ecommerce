<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is leader
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'leader') {
    $_SESSION['error'] = "Access Denied - Only group leaders can manage late fee policy.";
    // Use the stored role to redirect correctly
    $redirect_page = ($_SESSION['role'] === 'admin') ? 'admin_dashboard.php' : 'member_login.php';
    header("Location: " . $redirect_page); 
    exit();
}

$leader_group_id = $_SESSION['group_id'];
$message = '';
$message_type = '';

// Fetch current group data and late fee settings
$group_data = null;
try {
    $stmt = $conn->prepare("SELECT group_name, late_fee_type, late_fee_value FROM groups WHERE id = ?");
    $stmt->execute([$leader_group_id]);
    $group_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group_data) {
        $message = "Group not found!";
        $message_type = 'error';
    }
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// Determine the currently selected type for PHP/HTML initial rendering
$selected_type = $_POST['late_fee_type'] ?? $group_data['late_fee_type'] ?? '';


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_late_fee'])) {
    $late_fee_type = $_POST['late_fee_type'] ?? '';
    $late_fee_value = $_POST['late_fee_value'] ?? '';
    
    // Validate inputs
    if (empty($late_fee_type)) {
        $message = "Please select late fee type.";
        $message_type = 'error';
    } elseif ($late_fee_type !== 'progressive' && (empty($late_fee_value) || $late_fee_value <= 0)) {
        $message = "Late fee value must be greater than 0.";
        $message_type = 'error';
    } else {
        try {
            // For progressive type, set value to 0 since we don't need it
            // Sanitize the value before use
            $final_late_fee_value = ($late_fee_type === 'progressive') ? 0 : floatval($late_fee_value);
            
            // Update late fee settings
            $update_stmt = $conn->prepare("UPDATE groups SET late_fee_type = ?, late_fee_value = ? WHERE id = ?");
            $update_stmt->execute([$late_fee_type, $final_late_fee_value, $leader_group_id]);
            
            if ($update_stmt->rowCount() > 0 || $update_stmt->rowCount() === 0) { // Allow 0 rows affected if settings are unchanged
                $message = "Late Fee Policy updated successfully!";
                $message_type = 'success';
                
                // Refresh group data and selected type
                $stmt = $conn->prepare("SELECT group_name, late_fee_type, late_fee_value FROM groups WHERE id = ?");
                $stmt->execute([$leader_group_id]);
                $group_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $selected_type = $group_data['late_fee_type'] ?? ''; // Update selected type after success
            } else {
                $message = "Failed to update late fee policy.";
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Late Fee Policy - Samuh Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: 1px solid #e3e6f0;
            border-radius: 10px;
        }
        .header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .current-settings {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .btn-save {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
        }
        .progressive-rules {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .value-input-group {
            /* Set to flex by default, JS handles initial visibility and changes */
            display: flex; 
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="mb-3">
            <a href="leader_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="header-card text-center">
                    <h1 class="h3 mb-2">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        Set Late Fee Policy
                    </h1>
                    <p class="mb-0 opacity-75">Define how late payments will be charged for members in your group</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($group_data && ($group_data['late_fee_type'] || $group_data['late_fee_value'])): ?>
                <div class="current-settings">
                    <h6 class="text-primary mb-2">
                        <i class="fas fa-info-circle me-2"></i>Current Late Fee Settings
                    </h6>
                    <p class="mb-0">
                        <?php
                        $current_type = $group_data['late_fee_type'];
                        $current_value = $group_data['late_fee_value'];
                        
                        if ($current_type === 'fixed') {
                            echo "Fixed Fine: ₹" . number_format($current_value, 2) . " per late payment";
                        } elseif ($current_type === 'per_day') {
                            echo "Per Day Fine: ₹" . number_format($current_value, 2) . " per late day";
                        } elseif ($current_type === 'percent') {
                            echo "Percentage Fine: " . number_format($current_value, 2) . "% of payment amount per late day";
                        } elseif ($current_type === 'progressive') {
                            echo "Progressive Fine: Badhta hua system (3 days free, then 5%, 7%, 9%...)";
                        } else {
                            echo "No late fee policy set yet.";
                        }
                        ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" id="lateFeeForm">
                            <div class="mb-4">
                                <label for="late_fee_type" class="form-label">
                                    <i class="fas fa-calculator me-2"></i>Late Fee Type
                                </label>
                                <select class="form-select" id="late_fee_type" name="late_fee_type" required>
                                    <option value="">-- Select Fee Type --</option>
                                    <option value="fixed" <?php echo ($selected_type === 'fixed') ? 'selected' : ''; ?>>
                                        Fixed Amount (One-time fine)
                                    </option>
                                    <option value="per_day" <?php echo ($selected_type === 'per_day') ? 'selected' : ''; ?>>
                                        Per Day (Daily fine)
                                    </option>
                                    <option value="percent" <?php echo ($selected_type === 'percent') ? 'selected' : ''; ?>>
                                        Percentage (Based on payment amount)
                                    </option>
                                    <option value="progressive" <?php echo ($selected_type === 'progressive') ? 'selected' : ''; ?>>
                                        Progressive (Badhta hua system)
                                    </option>
                                </select>
                            </div>

                            <div class="mb-4 value-input-group" id="valueInputGroup"
                                style="display: <?php echo ($selected_type === 'progressive' || empty($selected_type)) ? 'none' : 'flex'; ?>;">
                                <label for="late_fee_value" class="form-label">
                                    <i class="fas fa-rupee-sign me-2"></i>Late Fee Value
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text" id="value_prefix">
                                        <?php echo ($selected_type === 'percent') ? '%' : '₹'; ?>
                                    </span>
                                    <input type="number" 
                                            class="form-control" 
                                            id="late_fee_value" 
                                            name="late_fee_value" 
                                            value="<?php echo htmlspecialchars($group_data['late_fee_value'] ?? ''); ?>" 
                                            step="0.01" 
                                            min="0.01" 
                                            placeholder="0.00"
                                            <?php echo ($selected_type !== 'progressive' && !empty($selected_type)) ? 'required' : ''; ?>>
                                </div>
                                <div class="form-text">Enter the fee value (minimum 0.01)</div>
                            </div>

                            <div class="progressive-rules" id="progressiveRules" 
                                style="display: <?php echo ($selected_type === 'progressive') ? 'block' : 'none'; ?>;">
                                <h6 class="text-warning mb-3">
                                    <i class="fas fa-chart-line me-2"></i>Progressive Late Fee Rules
                                </h6>
                                <div class="row small">
                                    <div class="col-md-6">
                                        <strong>Days Late</strong><br>
                                        0-3 days: 0% (No fee)<br>
                                        4-7 days: 5%<br>
                                        8-10 days: 7%<br>
                                        11-15 days: 9%<br>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Days Late</strong><br>
                                        16-20 days: 11%<br>
                                        21-25 days: 13%<br>
                                        26-30 days: 15%<br>
                                        30+ days: 17%<br>
                                    </div>
                                </div>
                                <p class="mb-0 mt-2 text-muted">
                                    <small>Note: This system automatically increases late fees based on how many days payment is delayed.</small>
                                </p>
                            </div>

                            <div class="info-box" id="infoBox">
                                <i class="fas fa-lightbulb me-2 text-warning"></i>
                                <span id="infoText">
                                    <?php
                                    $info_text = [
                                        'fixed' => 'Fixed amount will be charged once for each late payment, regardless of how many days late.',
                                        'per_day' => 'Fine amount will be multiplied by the number of late days.',
                                        'percent' => 'Fine = (Late days × Payment amount × Percentage value) / 100',
                                        'progressive' => 'Fine amount increases progressively based on number of late days (3 days free, then 5%, 7%, 9%...)'
                                    ];
                                    echo $info_text[$selected_type] ?? 'Select a fee type to see calculation details.';
                                    ?>
                                </span>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="leader_dashboard.php" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" name="save_late_fee" class="btn btn-save">
                                    <i class="fas fa-save me-2"></i>Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-question-circle me-2"></i>
                            Late Fee Examples
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <h6>Fixed Amount</h6>
                                <small class="text-muted">
                                    If set to ₹50:<br>
                                    • 1 day late = ₹50<br>
                                    • 10 days late = ₹50
                                </small>
                            </div>
                            <div class="col-md-3">
                                <h6>Per Day</h6>
                                <small class="text-muted">
                                    If set to ₹5:<br>
                                    • 1 day late = ₹5<br>
                                    • 10 days late = ₹50
                                </small>
                            </div>
                            <div class="col-md-3">
                                <h6>Percentage</h6>
                                <small class="text-muted">
                                    If set to 2%:<br>
                                    • ₹1000 payment, 1 day late = ₹20<br>
                                    • ₹1000 payment, 5 days late = ₹100
                                </small>
                            </div>
                            <div class="col-md-3">
                                <h6>Progressive</h6>
                                <small class="text-muted">
                                    ₹1000 payment:<br>
                                    • 5 days late = ₹50 (5%)<br>
                                    • 12 days late = ₹90 (9%)<br>
                                    • 25 days late = ₹130 (13%)
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const feeTypeSelect = document.getElementById('late_fee_type');
            const valuePrefix = document.getElementById('value_prefix');
            const valueInputGroup = document.getElementById('valueInputGroup');
            const infoText = document.getElementById('infoText');
            const progressiveRules = document.getElementById('progressiveRules');
            const lateFeeValueInput = document.getElementById('late_fee_value');
            
            const infoMessages = {
                'fixed': 'Fixed amount will be charged once for each late payment, regardless of how many days late.',
                'per_day': 'Fine amount will be multiplied by the number of late days.',
                'percent': 'Fine = (Late days × Payment amount × Percentage value) / 100',
                'progressive': 'Fine amount increases progressively based on number of late days (3 days free, then 5%, 7%, 9%...)'
            };
            
            // Update UI when fee type changes
            function updateUI(selectedType) {
                // Update prefix and placeholder
                if (selectedType === 'percent') {
                    valuePrefix.textContent = '%';
                    lateFeeValueInput.placeholder = 'e.g., 2 for 2%';
                } else {
                    valuePrefix.textContent = '₹';
                    lateFeeValueInput.placeholder = '0.00';
                }
                
                // Show/hide value input for progressive
                if (selectedType === 'progressive') {
                    valueInputGroup.style.display = 'none';
                    progressiveRules.style.display = 'block';
                    
                    // Remove required attribute and clear value (as it's not used)
                    lateFeeValueInput.removeAttribute('required');
                    lateFeeValueInput.value = ''; 

                } else if (selectedType) { // For fixed, per_day, percent
                    valueInputGroup.style.display = 'flex';
                    progressiveRules.style.display = 'none';
                    
                    // Add required attribute back
                    lateFeeValueInput.setAttribute('required', 'required');
                } else { // Handle empty selection initially
                     valueInputGroup.style.display = 'none';
                     progressiveRules.style.display = 'none';
                     lateFeeValueInput.removeAttribute('required');
                }
                
                // Update info text
                if (infoMessages[selectedType]) {
                    infoText.textContent = infoMessages[selectedType];
                } else {
                    infoText.textContent = 'Select a fee type to see calculation details.';
                }
            }
            
            // Event listener for fee type change
            feeTypeSelect.addEventListener('change', function() {
                const selectedType = this.value;
                updateUI(selectedType);
            });
            
            // Initialize UI on page load using the current or default selected type
            const initialType = feeTypeSelect.value;
            // Check if initialType is set, if not, updateUI will handle the default empty state
            updateUI(initialType);
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>