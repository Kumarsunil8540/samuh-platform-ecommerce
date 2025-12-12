<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>समूह प्लेटफॉर्म - Samuh Platform</title>
    <link rel="stylesheet" href="main_page.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <?php include("header.php"); ?>
    
    <!-- ===== Main Page Content ===== -->
    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-container">
                <h1 class="hero-title">
                    <span class="hindi">समूह प्लेटफॉर्म में आपका स्वागत है</span>
                    <span class="english">Welcome to Samuh Platform</span>
                </h1>
                <p class="hero-subtitle">
                    <span class="hindi">अपनी बचत यात्रा एक साथ शुरू करने के लिए समूह में शामिल हों या बनाएँ</span>
                    <span class="english">Join or create a group to start your saving journey together</span>
                </p>
                <div class="hero-buttons">
                    <a href="group_signup.php" class="btn btn-primary">
                        <span class="hindi">नया समूह बनाएँ</span>
                        <span class="english">Create New Group</span>
                    </a>
                    <a href="member_request.php" class="btn btn-secondary">
                        <span class="hindi">सदस्य बनें</span>
                        <span class="english">Become Member</span>
                    </a>
                    <!-- Token System Button -->
                    <a href="token_system.php" class="btn btn-token">
                        <span class="hindi">🤝 सदस्यता टोकन</span>
                        <span class="english">🤝 Membership Token</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features">
            <div class="container">
                <h2 class="section-title">
                    <span class="hindi">हमारी विशेषताएं</span>
                    <span class="english">Our Features</span>
                </h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">👥</div>
                        <h3 class="hindi">समूह प्रबंधन</h3>
                        <h3 class="english">Group Management</h3>
                        <p class="hindi">आसानी से समूह बनाएं और प्रबंधित करें</p>
                        <p class="english">Easily create and manage groups</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">💳</div>
                        <h3 class="hindi">सुरक्षित भुगतान</h3>
                        <h3 class="english">Secure Payments</h3>
                        <p class="hindi">सुरक्षित और पारदर्शी भुगतान प्रणाली</p>
                        <p class="english">Secure and transparent payment system</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3 class="hindi">विस्तृत रिपोर्ट</h3>
                        <h3 class="english">Detailed Reports</h3>
                        <p class="hindi">विस्तृत रिपोर्ट और एनालिटिक्स</p>
                        <p class="english">Detailed reports and analytics</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3 class="hindi">सुरक्षा</h3>
                        <h3 class="english">Security</h3>
                        <p class="hindi">उन्नत सुरक्षा और गोपनीयता</p>
                        <p class="english">Advanced security and privacy</p>
                    </div>

                    <!-- New Token Feature Card -->
                    <div class="feature-card">
                        <div class="feature-icon">🤝</div>
                        <h3 class="hindi">सदस्यता टोकन</h3>
                        <h3 class="english">Membership Token</h3>
                        <p class="hindi">समूह की विशेष सदस्यता प्राप्त करें</p>
                        <p class="english">Get special membership of the group</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="how-it-works">
            <div class="container">
                <h2 class="section-title">
                    <span class="hindi">यह कैसे काम करता है</span>
                    <span class="english">How It Works</span>
                </h2>
                <div class="steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h3 class="hindi">समूह बनाएं</h3>
                        <h3 class="english">Create Group</h3>
                        <p class="hindi">नया समूह बनाएं और कोर सदस्यों को जोड़ें</p>
                        <p class="english">Create new group and add core members</p>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <h3 class="hindi">सदस्य जोड़ें</h3>
                        <h3 class="english">Add Members</h3>
                        <p class="hindi">सदस्यों को जोड़ें और उन्हें अनुमोदित करें</p>
                        <p class="english">Add members and get them approved</p>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <h3 class="hindi">भुगतान शुरू करें</h3>
                        <h3 class="english">Start Payments</h3>
                        <p class="hindi">भुगतान एकत्रित करना शुरू करें</p>
                        <p class="english">Start collecting payments</p>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">4</div>
                        <h3 class="hindi">ट्रैक करें</h3>
                        <h3 class="english">Track Progress</h3>
                        <p class="hindi">प्रगति और रिपोर्ट ट्रैक करें</p>
                        <p class="english">Track progress and reports</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat">
                        <div class="stat-number" data-count="50">0</div>
                        <div class="stat-label hindi">सक्रिय समूह</div>
                        <div class="stat-label english">Active Groups</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number" data-count="500">0</div>
                        <div class="stat-label hindi">सदस्य</div>
                        <div class="stat-label english">Members</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number" data-count="25">0</div>
                        <div class="stat-label hindi">लाख रुपये जमा</div>
                        <div class="stat-label english">Lakhs Collected</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number" data-count="99">0</div>
                        <div class="stat-label hindi">% संतुष्टि</div>
                        <div class="stat-label english">% Satisfaction</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta">
            <div class="container">
                <h2 class="cta-title hindi">अपना समूह आज ही शुरू करें!</h2>
                <h2 class="cta-title english">Start Your Group Today!</h2>
                <p class="cta-subtitle hindi">हजारों लोगों में शामिल हों जो पहले से ही समूह प्लेटफॉर्म का उपयोग कर रहे हैं</p>
                <p class="cta-subtitle english">Join thousands of people already using Samuh Platform</p>
                <a href="group_signup.php" class="btn btn-large">
                    <span class="hindi">अभी शुरू करें</span>
                    <span class="english">Get Started Now</span>
                </a>
            </div>
        </section>
    </main>
    
    <?php include("footer.php"); ?>

    <!-- JavaScript for animations -->
    <script src="main_page.js"></script>
</body>
</html>