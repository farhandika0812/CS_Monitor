<?php
// logout.php
// session_start();
session_start(['cookie_httponly' => true, 'cookie_secure' => true, 'cookie_samesite' => 'Lax']);

// Catat log aktivitas logout
if (isset($_SESSION['username']) && file_exists('config.php')) {
    @include_once 'config.php';
    if (isset($pdo)) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO log_aktivitas (username, ip_address, aksi, kategori, user_agent) VALUES (?, ?, 'Logout', 'Login', ?)");
            $stmt->execute([$_SESSION['username'], $ip, $userAgent]);
        } catch (Exception $e) {
            // Abaikan error logging
        }
    }
}

// Hapus semua session
session_destroy();

// Redirect ke halaman login
header('Location: login.php');
exit;
?>