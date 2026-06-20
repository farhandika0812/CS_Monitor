<?php
// config.php
// session_start();
session_start(['cookie_httponly' => true, 'cookie_secure' => true, 'cookie_samesite' => 'Lax']);

$host = "localhost";
$user = "cspanglimanet";
$pass = "bcwJl%yLs1r/)8v2";
$db   = "cs_panglima";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Cek login
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Cek role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] == $role;
}

// Log aktivitas
function logActivity($username, $aksi, $kategori = 'Akses', $detail = null) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("INSERT INTO log_aktivitas (username, ip_address, aksi, kategori, detail, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$username, $ip, $aksi, $kategori, $detail, $userAgent]);
}

// Jika belum login, redirect ke halaman login
if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header('Location: login.php');
    exit;
}
?>