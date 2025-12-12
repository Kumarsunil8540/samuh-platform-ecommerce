<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Download</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            text-align: center;
        }
        
        .container {
            max-width: 600px;
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .logo {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 20px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 40px;
            color: #6a11cb;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .download-btn {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 15px 40px;
            font-size: 1.3rem;
            font-weight: bold;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
            margin-bottom: 20px;
        }
        
        .download-btn:hover {
            background: #ff5252;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.6);
        }
        
        .download-btn:active {
            transform: translateY(1px);
        }
        
        .features {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .feature {
            flex: 1;
            min-width: 150px;
            margin: 10px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        
        .feature i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .instructions {
            margin-top: 30px;
            text-align: left;
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
        }
        
        .instructions h3 {
            margin-bottom: 10px;
        }
        
        .instructions ol {
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 10px;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            p {
                font-size: 1rem;
            }
            
            .download-btn {
                padding: 12px 30px;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-mobile-alt"></i>
        </div>
        <h1>Mera App Download Karein</h1>
        <p>Hamara official app download karein aur sabhi features mobile par enjoy karein. Fast, secure aur easy-to-use.</p>
        
        <a href="app3811989-q4y5gh.apk" class="download-btn" download>
            <i class="fas fa-download"></i> App Download Karein
        </a>
        
        <p style="font-size: 0.9rem; opacity: 0.8;">File Size: ~25 MB | Version: 1.0</p>
        
        <div class="features">
            <div class="feature">
                <i class="fas fa-bolt"></i>
                <h3>Fast</h3>
            </div>
            <div class="feature">
                <i class="fas fa-shield-alt"></i>
                <h3>Secure</h3>
            </div>
            <div class="feature">
                <i class="fas fa-heart"></i>
                <h3>User Friendly</h3>
            </div>
        </div>
        
        <div class="instructions">
            <h3>Installation Instructions:</h3>
            <ol>
                <li>Download button par click karein</li>
                <li>APK file download hone ke baad open karein</li>
                <li>"Install from unknown sources" allow karein (agar required ho)</li>
                <li>Install button par click karein</li>
                <li>App open karein aur enjoy karein!</li>
            </ol>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>