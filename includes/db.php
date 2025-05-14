<?php
$host = '0.0.0.0';
$dbname = 'auction28';
$username = 'root';
$password = 'root';
$port = '8080';
date_default_timezone_set('Asia/Kolkata');
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('<p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
           role="alert"  data-bs-dismiss="alert" 
                  aria-label="Close" 
           style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
          Database connection failed: '. $e->getMessage().'
        </p>');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>