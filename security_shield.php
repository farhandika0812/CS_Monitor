<?php
// security_shield.php - Minimal version
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    session_start();
}

define('AKSES_AMAN', true);
?>