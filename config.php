<?php
$hostName = "localhost";
$userName = "root";
$password = ""; 
$databaseName = "samuh_platform";

try {
    $conn = new PDO("mysql:host=$hostName;dbname=$databaseName;charset=utf8mb4", $userName, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    

} catch (PDOException $err) {
    echo "Connection failed: " . $err->getMessage();
    exit(); // connection fail hone par script rok do
}
?>
