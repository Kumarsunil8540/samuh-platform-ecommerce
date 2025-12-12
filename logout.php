<?php
// logout.php — सीधे इस फाइल को सेव करो, और ब्राउज़र में खोलो
// कोई भी आउटपुट (स्पेस/HTML) इस से पहले नहीं होना चाहिए

// अगर session शुरू नहीं है तो शुरू करो
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// उपयोगकर्ता के session डेटा को खाली करो
$_SESSION = [];

// अगर session cookie मौजूद है तो उसे भी हटाओ
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// session destroy करो
session_destroy();

// Redirect करो — header से और fallback के लिए HTML भी दिया है
header("Location: index.php");
exit;
?>
<!-- अगर किसी कारण से header redirect काम न करे तो नीचे एक छोटा fallback दिया है -->
<!doctype html>
<html lang="hi">
<head>
  <meta charset="utf-8">
  <meta http-equiv="refresh" content="0;url=index.php">
  <title>Logging out...</title>
</head>
<body>
  <p>आपका सत्र समाप्त किया जा रहा है — अगर आप तुरंत रीडायरेक्ट नहीं होते हैं तो <a href="index.php">यहाँ क्लिक करें</a>.</p>
</body>
</html>
