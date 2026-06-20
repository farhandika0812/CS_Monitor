<?php
// config.php - Koneksi database dan konfigurasi umum

// Cek apakah session sudah aktif sebelum memulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$user = "cspanglimanet";
$pass = "bcwJl%yLs1r/)8v2";
$db   = "cs_panglima";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Fungsi untuk log activity
function logActivity($username, $aksi, $kategori = 'Akses', $detail = null) {
    global $pdo;
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $pdo->prepare("INSERT INTO log_aktivitas (username, ip_address, aksi, kategori, detail, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$username, $ip_address, $aksi, $kategori, $detail, $user_agent]);
    } catch (Exception $e) {
        // Abaikan error logging
    }
}
?>