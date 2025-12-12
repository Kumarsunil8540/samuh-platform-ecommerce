<?php
// यह सुनिश्चित करता है कि सेशन शुरू हो गया है
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user role and name for display
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="header.css"> 
    <script src="header.js" defer></script> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Samuh Platform</title>
</head>
<body>
<header>
    <div class="container header-container">
        <div class="logo">
            <a href="index.php">समूह (Samuh)</a>
        </div>
        
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">होम (Home)</a></li>
                <li><a href="about.php">हमारे बारे में (About)</a></li>
                <li><a href="contact.php">संपर्क करें (Contact)</a></li>
                
                <?php if(isset($_SESSION['user_id']) && isset($_SESSION['role'])): ?>
                    <!-- Logged In User -->
                    <li class="user-welcome">
                        <span>नमस्ते (Hello), 
                            <?php echo htmlspecialchars($user_name ?: 'User'); ?> 
                            (<?php echo htmlspecialchars($user_role); ?>)
                        </span>
                    </li>
                    
                    <!-- Role-based Dashboard Links -->
                    <?php if($user_role === 'member'): ?>
                        <li><a href="member_dashboard.php" class="btn-dashboard">मेरा डैशबोर्ड (My Dashboard)</a></li>
                    <?php elseif($user_role === 'leader'): ?>
                        <li><a href="leader_dashboard.php" class="btn-dashboard">लीडर डैशबोर्ड (Leader Dashboard)</a></li>
                    <?php elseif($user_role === 'accountant'): ?>
                        <li><a href="accountant_dashboard.php" class="btn-dashboard">अकाउंटेंट डैशबोर्ड (Accountant Dashboard)</a></li>
                    <?php elseif($user_role === 'admin'): ?>
                        <li><a href="admin_dashboard.php" class="btn-dashboard">एडमिन डैशबोर्ड (Admin Dashboard)</a></li>
                    <?php endif; ?>
                    
                    <li><a href="logout.php" class="btn-logout">लॉगआउट (Logout)</a></li>
                    
                <?php else: ?>
                    <!-- Not Logged In -->
                    <li class="nav-dropdown-parent login-dropdown-parent">
                        <button type="button" class="btn-login nav-dropdown-toggle">
                            लॉगिन (Login) ▼
                        </button>
                        <ul class="dropdown-menu login-menu">
                            <li><a href="core_member_login.php?role=leader">ग्रुप मालिक लॉगिन (Group Leader Login)</a></li>
                            <li><a href="core_member_login.php?role=admin">ग्रुप एडमिन लॉगिन (Group Admin Login)</a></li>
                            <li><a href="core_member_login.php?role=accountant">ग्रुप अकाउंटेंट लॉगिन (Group Accountant Login)</a></li>
                            <li><a href="core_member_login.php?role=member">सदस्य लॉगिन (Member Login)</a></li>
                        </ul>
                    </li>

                    <li class="nav-dropdown-parent signup-dropdown-parent">
                        <button type="button" class="btn-signup nav-dropdown-toggle">
                            साइनअप (Signup) ▼
                        </button>
                        <ul class="dropdown-menu signup-menu">
                            <li><a href="group_signup.php">नया समूह बनाएँ (Create New Group)</a></li>
                            <li><a href="member_request.php">सदस्य पंजीकरण (Member Registration)</a></li>
                            <li class="dropdown-divider"></li>
                            <li class="dropdown-subtitle">दस्तावेज़ अपलोड (Document Upload):</li>
                            <li><a href="check_group_core_member.php?role=leader">लीडर दस्तावेज़ (Leader Documents)</a></li>
                            <li><a href="check_group_core_member.php?role=admin">एडमिन दस्तावेज़ (Admin Documents)</a></li>
                            <li><a href="check_group_core_member.php?role=accountant">अकाउंटेंट दस्तावेज़ (Accountant Documents)</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            
            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </nav>
    </div>
</header>