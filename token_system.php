<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Samuh Token System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            padding: 20px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .tokens-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .token-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
            border: 3px solid transparent;
        }
        
        .token-card:hover {
            transform: translateY(-10px);
        }
        
        .token-card.bronze {
            border-color: #CD7F32;
        }
        
        .token-card.silver {
            border-color: #C0C0C0;
        }
        
        .token-card.gold {
            border-color: #FFD700;
        }
        
        .token-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .token-card.bronze .token-icon {
            color: #CD7F32;
        }
        
        .token-card.silver .token-icon {
            color: #C0C0C0;
        }
        
        .token-card.gold .token-icon {
            color: #FFD700;
        }
        
        .token-card h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: #333;
        }
        
        .token-price {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .token-features {
            list-style: none;
            margin: 20px 0;
            text-align: left;
        }
        
        .token-features li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            color: #555;
        }
        
        .token-features li:before {
            content: "✓ ";
            color: #27ae60;
            font-weight: bold;
        }
        
        .btn-buy {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        
        .btn-buy:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .admin-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-top: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .footer {
            text-align: center;
            color: white;
            margin-top: 40px;
            padding: 20px;
            opacity: 0.8;
        }
        
        .note-box {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            color: white;
            border-left: 4px solid #FFD700;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🏦 Samuh Token System</h1>
            <p>समूह में जुड़ें, टोकन खरीदें, लाभ कमाएं</p>
        </div>

        <!-- Note Box -->
        <div class="note-box">
            <strong>📢 Important:</strong> कंपनी टोकन वापस खरीदने की कीमत एडमिन द्वारा तय की जाएगी
        </div>

        <!-- Tokens Grid -->
        <div class="tokens-grid">
            <!-- Bronze Token -->
            <div class="token-card bronze">
                <div class="token-icon">🟤</div>
                <h3>Bronze Token</h3>
                <div class="token-price">₹10</div>
                <ul class="token-features">
                    <li>बेसिक मेंबरशिप</li>
                    <li>कंपनी बायबैक: एडमिन डिसीजन</li>
                    <li>कभी भी बेच सकते हैं</li>
                    <li>रेफरल बोनस के लिए योग्य</li>
                </ul>
                <a href="token_login.php?type=bronze" class="btn-buy">Buy Now</a>
            </div>

            <!-- Silver Token -->
            <div class="token-card silver">
                <div class="token-icon">⚪</div>
                <h3>Silver Token</h3>
                <div class="token-price">₹50</div>
                <ul class="token-features">
                    <li>प्रीमियम मेंबरशिप</li>
                    <li>कंपनी बायबैक: एडमिन डिसीजन</li>
                    <li>प्रायोरिटी सपोर्ट</li>
                    <li>एडवांस्ड फीचर्स</li>
                </ul>
                <a href="token_login.php?type=silver" class="btn-buy">Buy Now</a>
            </div>

            <!-- Gold Token -->
            <div class="token-card gold">
                <div class="token-icon">🟡</div>
                <h3>Gold Token</h3>
                <div class="token-price">₹100</div>
                <ul class="token-features">
                    <li>VIP मेंबरशिप</li>
                    <li>कंपनी बायबैक: एडमिन डिसीजन</li>
                    <li>एक्सक्लूसिव बेनिफिट्स</li>
                    <li>हाई प्रायोरिटी सपोर्ट</li>
                </ul>
                <a href="token_login.php?type=gold" class="btn-buy">Buy Now</a>
            </div>
        </div>

        <!-- Admin Section -->
        <div class="admin-section">
            <h3>🚀 Already a Member?</h3>
            <p>Login to your account to manage tokens</p>
            <a href="token_login.php" class="btn-buy" style="background: #27ae60;">Member Login</a>
            
            <div style="margin-top: 20px;">
                <a href="token_login.php?admin=1" style="color: #667eea; text-decoration: none;">Admin Login</a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>© 2024 Samuh Token System. All rights reserved.</p>
            <p>📞 Contact: 7631952321 | ✉️ Email: microvisionaryminds@gmail.com</p>
        </div>
    </div>

    <script>
        // Simple animation for tokens
        document.addEventListener('DOMContentLoaded', function() {
            const tokenCards = document.querySelectorAll('.token-card');
            
            tokenCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(50px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });

        // Buy Now button click
        document.querySelectorAll('.btn-buy').forEach(button => {
            button.addEventListener('click', function(e) {
                const tokenType = this.closest('.token-card').querySelector('h3').textContent;
                console.log('Buying:', tokenType);
                // Redirect will happen via href
            });
        });
    </script>
</body>
</html>