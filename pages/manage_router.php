<?php
// pages/manage_router.php
// Menggunakan logika yang SAMA dengan script lama yang berhasil

// Handle test connection (TAMBAHKAN KODE INI)
if (isset($_GET['action']) && $_GET['action'] == 'test_router') {
    header('Content-Type: application/json');
    error_reporting(0);
    ini_set('display_errors', 0);
    
    $ip = trim($_GET['ip'] ?? '');
    $port = intval($_GET['port'] ?? 80);
    $username = trim($_GET['username'] ?? '');
    $password = $_GET['password'] ?? '';
    
    if (empty($ip)) {
        echo json_encode(['status' => 'error', 'message' => 'IP Address harus diisi']);
        exit;
    }
    
    if (empty($username)) {
        echo json_encode(['status' => 'error', 'message' => 'Username harus diisi']);
        exit;
    }
    
    $test_url = "http://{$ip}:{$port}/rest/system/identity";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200) {
        $identity = json_decode($response, true);
        $identity_name = $identity['name'] ?? 'MikroTik Router';
        echo json_encode([
            'status' => 'success',
            'message' => "✅ Koneksi berhasil!<br><small>Router: <strong>" . htmlspecialchars($identity_name) . "</strong></small>"
        ]);
    } elseif ($http_code === 401) {
        echo json_encode(['status' => 'error', 'message' => "❌ Username atau Password salah!"]);
    } elseif ($curl_error) {
        echo json_encode(['status' => 'error', 'message' => "❌ Gagal terhubung ke $ip:$port"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "❌ Koneksi gagal (HTTP $http_code)"]);
    }
    exit;
}

// Handle add/edit/delete router
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (!isset($_POST['action']) || $_POST['action'] != 'sync')) {
    $action = $_POST['action'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $ip_address = trim($_POST['ip_address'] ?? '');
    $port = intval($_POST['port'] ?? 80);
    $username = trim($_POST['username_router'] ?? '');
    $password = trim($_POST['password_router'] ?? '');
    $api_port = intval($_POST['api_port'] ?? 8728);
    $ssh_port = intval($_POST['ssh_port'] ?? 22);
    
    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO router_list (nama, ip_address, port, username, password, api_port, ssh_port) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nama, $ip_address, $port, $username, $password, $api_port, $ssh_port]);
        if (function_exists('logActivity')) {
            logActivity($_SESSION['username'] ?? 'System', "Tambah router: $nama", 'Router');
        }
        $_SESSION['swal_message'] = [
            'title' => '✅ Berhasil!', 
            'text' => "Router <strong>" . htmlspecialchars($nama) . "</strong> berhasil ditambahkan", 
            'icon' => 'success'
        ];
        echo '<script>window.location.href="?page=manage_router&swal=1";</script>';
        exit;
    } elseif ($action == 'edit') {
        $id = intval($_POST['id']);
        
        // Jika password kosong, jangan update password (pertahankan yang lama)
        if (empty($password)) {
            $stmt = $pdo->prepare("UPDATE router_list SET nama=?, ip_address=?, port=?, username=?, api_port=?, ssh_port=? WHERE id=?");
            $stmt->execute([$nama, $ip_address, $port, $username, $api_port, $ssh_port, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE router_list SET nama=?, ip_address=?, port=?, username=?, password=?, api_port=?, ssh_port=? WHERE id=?");
            $stmt->execute([$nama, $ip_address, $port, $username, $password, $api_port, $ssh_port, $id]);
        }
        if (function_exists('logActivity')) {
            logActivity($_SESSION['username'] ?? 'System', "Edit router: $nama", 'Router');
        }
        $_SESSION['swal_message'] = [
            'title' => '✅ Berhasil!', 
            'text' => "Router <strong>" . htmlspecialchars($nama) . "</strong> berhasil diperbarui", 
            'icon' => 'success'
        ];
        echo '<script>window.location.href="?page=manage_router&swal=1";</script>';
        exit;
    } elseif ($action == 'delete') {
        $id = intval($_POST['id']);
        $nama = $_POST['nama'];
        $stmt = $pdo->prepare("DELETE FROM router_list WHERE id=?");
        $stmt->execute([$id]);
        if (function_exists('logActivity')) {
            logActivity($_SESSION['username'] ?? 'System', "Hapus router: $nama", 'Router');
        }
        $_SESSION['swal_message'] = [
            'title' => '🗑️ Terhapus!', 
            'text' => "Router <strong>" . htmlspecialchars($nama) . "</strong> berhasil dihapus", 
            'icon' => 'success'
        ];
        echo '<script>window.location.href="?page=manage_router&swal=1";</script>';
        exit;
    }
}

// Tampilkan SweetAlert jika ada pesan dari session
if (isset($_GET['swal']) && isset($_SESSION['swal_message'])) {
    $swal = $_SESSION['swal_message'];
    unset($_SESSION['swal_message']);
    $swal_title = addslashes($swal['title']);
    $swal_text = addslashes($swal['text']);
    $swal_icon = $swal['icon'];
    echo "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: '{$swal_title}',
            html: '{$swal_text}',
            icon: '{$swal_icon}',
            confirmButtonColor: '#3498db',
            confirmButtonText: 'OK',
            background: '#fff',
            backdrop: 'rgba(0,0,0,0.4)',
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: true,
            customClass: {
                popup: 'swal2-popup-custom'
            }
        }).then(function() {
            window.location.href = '?page=manage_router';
        });
    });
    </script>";
}

// Pagination - 10 data per halaman
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query dengan filter pencarian
$where = [];
$params = [];

if ($search) {
    $where[] = "(nama LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Query
$baseSql = "FROM router_list $whereClause";

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) as total $baseSql");
$countStmt->execute($params);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $perPage);

// Get data
$sql = "SELECT * $baseSql ORDER BY id LIMIT $offset, $perPage";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$routers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// HITUNG STATISTIK (DIPERBAIKI)
// ============================================

// Total router
$stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM router_list");
$totalRouters = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

// Router aktif (is_active = 1)
$stmtActive = $pdo->query("SELECT COUNT(*) as total FROM router_list WHERE is_active = 1");
$activeRouters = $stmtActive->fetch(PDO::FETCH_ASSOC)['total'];

// Router tidak aktif (is_active = 0)
$stmtInactive = $pdo->query("SELECT COUNT(*) as total FROM router_list WHERE is_active = 0");
$inactiveRouters = $stmtInactive->fetch(PDO::FETCH_ASSOC)['total'];

// Hitung total users online dari tabel pelanggan (LEBIH AKURAT)
// Tidak termasuk user dengan profile ISOLIR
$stmtUsersOnline = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE status_berlangganan = 'Aktif' AND status_ping = 'ONLINE' AND (pppoe_profile != 'ISOLIR' OR pppoe_profile IS NULL)");
$totalUsersOnline = $stmtUsersOnline->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Juga hitung total user aktif (tanpa ISOLIR) untuk perbandingan (opsional)
$stmtTotalActive = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE status_berlangganan = 'Aktif' AND (pppoe_profile != 'ISOLIR' OR pppoe_profile IS NULL)");
$totalActiveUsers = $stmtTotalActive->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ============================================
// END OF STATISTIK
// ============================================
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Router - PanglimaNet</title>
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { box-sizing: border-box; }
        
        body { 
            padding-top: 20px;
            background-color: #f0f2f5;
            font-size: 14px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Filter bar - satu baris */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
        }
        .filter-bar input, .filter-bar select, .filter-bar button, .filter-bar a {
            padding: 8px 12px;
            font-size: 13px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        .filter-bar input {
            flex: 2;
            min-width: 180px;
        }
        .filter-bar .btn-primary {
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
        }
        .filter-bar .btn-warning {
            background: #95a5a6;
            color: white;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .filter-bar .btn-success {
            background: #27ae60;
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        /* STATISTIK CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            border-radius: 10px;
            padding: 15px 20px;
            color: white;
            transition: transform 0.2s;
            cursor: default;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 500;
            opacity: 0.95;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }
        
        /* Tabel */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 8px rgba(0,0,0,0.05);
        }
        .data-table th, .data-table td {
            padding: 8px 10px;
            font-size: 13px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .data-table .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        .badge-active {
            background: #27ae60;
            color: white;
        }
        .badge-inactive {
            background: #e74c3c;
            color: white;
        }
        
        /* Pagination */
        .pagination {
            margin-top: 20px;
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: #3498db;
            border: 1px solid #ddd;
            background: white;
            font-size: 13px;
        }
        .pagination a:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        .pagination span.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        /* Info data */
        .data-info {
            text-align: center;
            margin-top: 10px;
            color: #7f8c8d;
            font-size: 12px;
        }
        
        /* Button */
        .btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
        }
        /* Tombol Test warna kuning */
        .btn-test {
            background: #f1c40f;
            color: #2c3e50;
        }
        .btn-test:hover {
            background: #e67e22;
            color: white;
        }
        
        /* Sync Button */
        .btn-sync {
            background: #9b59b6;
            color: white;
        }
        .btn-sync:hover {
            background: #8e44ad;
        }
        .btn-sync:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        /* Button Sync All */
        .btn-sync-all {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-sync-all:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(155, 89, 182, 0.3);
        }
        
        /* MODAL CRUD - COMPACT & PROFESIONAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            width: 90%;
            max-width: 460px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .modal-content h3 {
            margin: 0 0 16px 0;
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        .modal-content label {
            display: block;
            margin-bottom: 4px;
            font-weight: 500;
            font-size: 12px;
            color: #555;
        }
        .modal-content input, .modal-content select {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 13px;
            transition: all 0.2s;
        }
        .modal-content input:focus, .modal-content select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.1);
        }
        .modal-content input::placeholder {
            color: #bbb;
            font-size: 12px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 16px;
        }
        .modal-actions .btn {
            padding: 7px 16px;
            font-size: 12px;
            flex: 1;
            text-align: center;
        }
        
        /* Progress Sync All */
        .sync-progress {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            width: 350px;
            display: none;
            overflow: hidden;
        }
        .sync-progress.show { display: block; }
        .sync-progress-header {
            background: #1a1a2e;
            color: white;
            padding: 10px 14px;
            font-size: 12px;
        }
        .sync-progress-body {
            padding: 10px;
            max-height: 300px;
            overflow-y: auto;
            font-size: 11px;
        }
        .sync-item {
            padding: 6px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .sync-item.success { color: #28a745; }
        .sync-item.error { color: #dc3545; }
        .sync-item.pending { color: #ffc107; }
        
        /* Sync Custom Modal */
        .sync-custom-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        .sync-custom-modal.fade-out {
            animation: fadeOut 0.2s ease forwards;
        }

        .sync-custom-modal-content {
            background: white;
            border-radius: 16px;
            width: 420px;
            max-width: 90%;
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: slideUp 0.2s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .sync-custom-modal-header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }

        .sync-router-icon {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .sync-router-info {
            flex: 1;
        }

        .sync-router-info h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
        }

        .sync-router-info p {
            margin: 2px 0 0;
            font-size: 11px;
            opacity: 0.8;
        }

        .sync-close-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            width: 26px;
            height: 26px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
        }

        .sync-close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .sync-custom-modal-body {
            padding: 16px;
        }

        .sync-main-progress {
            background: #e2e8f0;
            border-radius: 20px;
            height: 5px;
            position: relative;
            margin-bottom: 18px;
            overflow: hidden;
        }

        .sync-main-progress-bar {
            background: linear-gradient(90deg, #2563eb, #10b981);
            height: 100%;
            border-radius: 20px;
            width: 0%;
            transition: width 0.3s ease;
        }

        .sync-main-progress-text {
            position: absolute;
            right: 0;
            top: -16px;
            font-size: 10px;
            font-weight: 600;
            color: #2563eb;
        }

        .sync-steps-container {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sync-step-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 10px;
            background: #f8fafc;
            border-radius: 10px;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .sync-step-item.active {
            background: #eff6ff;
            border-left-color: #2563eb;
        }

        .sync-step-item.completed {
            background: #ecfdf5;
            border-left-color: #10b981;
        }

        .sync-step-item.error {
            background: #fef2f2;
            border-left-color: #ef4444;
        }

        .sync-step-icon {
            width: 26px;
            height: 26px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #64748b;
        }

        .sync-step-item.active .sync-step-icon {
            background: #2563eb;
            color: white;
        }

        .sync-step-item.completed .sync-step-icon {
            background: #10b981;
            color: white;
        }

        .sync-step-item.error .sync-step-icon {
            background: #ef4444;
            color: white;
        }

        .sync-step-info {
            flex: 1;
        }

        .sync-step-title {
            font-size: 11px;
            font-weight: 600;
            color: #1e293b;
        }

        .sync-step-desc {
            font-size: 9px;
            color: #64748b;
        }

        .sync-step-status {
            min-width: 65px;
            text-align: right;
        }

        .sync-status-badge {
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 20px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .sync-status-badge.pending {
            background: #e2e8f0;
            color: #64748b;
        }

        .sync-status-badge.progress {
            background: #2563eb;
            color: white;
        }

        .sync-status-badge.success {
            background: #10b981;
            color: white;
        }

        .sync-status-badge.error {
            background: #ef4444;
            color: white;
        }

        .sync-result-summary {
            display: none;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 10px;
        }

        .sync-result-icon {
            font-size: 36px;
            margin-bottom: 8px;
        }

        .sync-result-text h4 {
            margin: 0 0 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .sync-stats {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 10px 0;
        }

        .sync-stat {
            text-align: center;
        }

        .sync-stat-value {
            display: block;
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .sync-stat-label {
            font-size: 9px;
            color: #64748b;
        }

        .sync-message {
            font-size: 10px;
            color: #10b981;
            background: #dcfce7;
            padding: 5px 10px;
            border-radius: 8px;
            margin: 6px 0 0;
        }

        .sync-error-message {
            font-size: 10px;
            color: #ef4444;
            background: #fee2e2;
            padding: 5px 10px;
            border-radius: 8px;
            margin: 6px 0;
        }

        .sync-tips {
            font-size: 9px;
            color: #64748b;
            margin-top: 6px;
        }

        .sync-custom-modal-footer {
            padding: 10px 16px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .sync-btn {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .sync-btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }

        .sync-btn-primary:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }

        .sync-btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .sync-btn-secondary:hover:not(:disabled) {
            background: #cbd5e1;
        }

        .sync-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* PERBAIKAN: SweetAlert selalu di atas modal apapun dan font lebih kecil */
        .swal2-container {
            z-index: 99999 !important;
        }
        
        /* Memperkecil font SweetAlert */
        .swal2-popup {
            font-size: 12px !important;
        }
        .swal2-title {
            font-size: 16px !important;
        }
        .swal2-html-container {
            font-size: 12px !important;
        }
        .swal2-confirm, .swal2-cancel {
            font-size: 12px !important;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding-top: 10px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .stat-card .value {
                font-size: 24px;
            }
            .stat-card {
                padding: 12px 16px;
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
                margin-bottom: 15px;
            }
            .filter-bar input, .filter-bar select, .filter-bar button, .filter-bar a {
                width: 100%;
            }
            .data-table th, .data-table td {
                font-size: 11px;
                padding: 6px 8px;
            }
            .pagination a, .pagination span {
                padding: 4px 8px;
                font-size: 11px;
            }
            .btn-sync, .btn-primary, .btn-danger {
                padding: 4px 6px;
                font-size: 10px;
            }
            .modal-content {
                padding: 16px;
                max-width: 94%;
            }
            .modal-content input, .modal-content select {
                padding: 6px 8px;
                margin-bottom: 10px;
            }
            .modal-actions .btn {
                padding: 5px 12px;
            }
        }
        
        /* Custom SweetAlert2 styling */
        .swal2-popup-custom {
            border-radius: 16px !important;
            font-family: inherit !important;
        }
    </style>
</head>
<body>

<!-- STATISTIK GRID -->
<div class="stats-grid">
    <div class="stat-card" style="background: linear-gradient(135deg, #3498db, #2980b9);">
        <h4>🖧 Total Router</h4>
        <div class="value"><?php echo number_format($totalRouters); ?></div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #27ae60, #2ecc71);">
        <h4>✅ Aktif</h4>
        <div class="value"><?php echo number_format($activeRouters); ?></div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
        <h4>❌ Tidak Aktif</h4>
        <div class="value"><?php echo number_format($inactiveRouters); ?></div>
    </div>

    <div class="stat-card" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
        <h4>🟢 Users Online</h4>
        <div class="value"><?php echo number_format($totalUsersOnline); ?></div>
    </div>
</div>

<!-- Filter Form - Satu Baris -->
<form method="GET" class="filter-bar">
    <input type="hidden" name="page" value="manage_router">
    <input type="text" name="search" placeholder="🔍 Cari nama router atau IP..." value="<?php echo htmlspecialchars($search); ?>">
    <button type="submit" class="btn-primary">🔍 Filter</button>
    <a href="?page=manage_router" class="btn-warning">🔄 Reset</a>
    
    <button type="button" class="btn-sync-all" id="btnSyncAllRouter">
        <i class="fa-solid fa-cloud-arrow-up"></i> Sync Semua Router
    </button>
    
    <button type="button" class="btn-success" onclick="showAddModal()">➕ Tambah Router</button>
</form>

<!-- Tabel Data -->
<div style="overflow-x: auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>🆔 ID</th>
                <th>🏷️ Nama Router</th>
                <th>🌐 IP Address</th>
                <th>🔌 Port</th>
                <th>👥 Total Users</th>
                <th>🟢 Active</th>
                <th>🔄 Last Sync</th>
                <th>📊 Status</th>
                <th>⚙️ Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($routers as $router): ?>
            <tr id="router-row-<?php echo $router['id']; ?>">
                <td><?php echo $router['id']; ?></td>
                <td><?php echo htmlspecialchars($router['nama']); ?></td>
                <td><?php echo htmlspecialchars($router['ip_address']); ?></td>
                <td><?php echo $router['port']; ?></td>
                <td><?php echo number_format($router['total_users'] ?? 0); ?></td>
                <td><?php echo number_format($router['total_active'] ?? 0); ?></td>
                <td><?php echo $router['last_sync'] ? date('d/m/Y H:i', strtotime($router['last_sync'])) : '-'; ?></td>
                <td>
                    <span class="badge <?php echo ($router['is_active'] ?? 0) ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo ($router['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?>
                    </span>
                </td>
                <td style="white-space: nowrap;">
                    <button class="btn btn-sync btn-sm btn-sync-single" 
                            data-id="<?php echo $router['id']; ?>" 
                            data-nama="<?php echo htmlspecialchars($router['nama']); ?>"
                            data-ip="<?php echo htmlspecialchars($router['ip_address']); ?>"
                            data-port="<?php echo $router['port']; ?>">
                        🔄 Sync
                    </button>
                    <button class="btn btn-primary btn-sm" onclick='editRouter(<?php echo json_encode($router); ?>)'>✏️ Edit</button>
                    <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $router['id']; ?>, '<?php echo htmlspecialchars($router['nama']); ?>')">🗑️ Hapus</button>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($routers)): ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                    📭 Tidak ada data router
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?page=manage_router&page_num=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">« Prev</a>
    <?php endif; ?>
    
    <?php
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++):
    ?>
        <?php if ($i == $page): ?>
        <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
        <a href="?page=manage_router&page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
    <a href="?page=manage_router&page_num=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next »</a>
    <?php endif; ?>
</div>
<div class="data-info">
    Menampilkan <?php echo count($routers); ?> dari <?php echo number_format($total); ?> data router
</div>
<?php endif; ?>

<!-- MODAL TAMBAH/EDIT ROUTER -->
<div id="routerModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">➕ Tambah Router</h3>
        <form method="POST" id="routerForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="routerId">
            
            <label>🏷️ Nama Router</label>
            <input type="text" name="nama" id="routerNama" required placeholder="Contoh: Router Utama">
            
            <label>🌐 IP Address</label>
            <input type="text" name="ip_address" id="routerIp" required placeholder="Contoh: 192.168.1.1">
            
            <label>🔌 Port (Web)</label>
            <input type="number" name="port" id="routerPort" value="80" placeholder="80">
            
            <label>👤 Username Router</label>
            <input type="text" name="username_router" id="routerUser" placeholder="admin">
            
            <label>🔑 Password Router</label>
            <input type="password" name="password_router" id="routerPass" placeholder="******** (kosongkan jika tidak diubah)">
            
            <label>📡 API Port</label>
            <input type="number" name="api_port" id="routerApiPort" value="8728" placeholder="8728">
            
            <label>💻 SSH Port</label>
            <input type="number" name="ssh_port" id="routerSshPort" value="22" placeholder="22">
            
            <div class="modal-actions">
                <button type="button" class="btn btn-test" onclick="testRouterConnection()">🔍 Test</button>
                <button type="button" class="btn btn-danger" onclick="closeModal()">❌ Batal</button>
                <button type="submit" class="btn btn-primary">💾 Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Progress Sync All -->
<div id="syncProgress" class="sync-progress">
    <div class="sync-progress-header">Sinkronisasi Router</div>
    <div class="sync-progress-body" id="syncProgressBody">
        <div class="sync-item">Menyiapkan sinkronisasi...</div>
    </div>
</div>

<script>
// =============================================================
// UTILITY FUNCTIONS
// =============================================================

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// =============================================================
// CRUD FUNCTIONS
// =============================================================

function showAddModal() {
    document.getElementById('modalTitle').innerHTML = '➕ Tambah Router';
    document.getElementById('formAction').value = 'add';
    document.getElementById('routerId').value = '';
    document.getElementById('routerNama').value = '';
    document.getElementById('routerIp').value = '';
    document.getElementById('routerPort').value = '80';
    document.getElementById('routerUser').value = '';
    document.getElementById('routerPass').value = '';
    document.getElementById('routerPass').placeholder = '********';
    document.getElementById('routerApiPort').value = '8728';
    document.getElementById('routerSshPort').value = '22';
    document.getElementById('routerModal').style.display = 'flex';
}

function editRouter(router) {
    document.getElementById('modalTitle').innerHTML = '✏️ Edit Router';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('routerId').value = router.id;
    document.getElementById('routerNama').value = router.nama;
    document.getElementById('routerIp').value = router.ip_address;
    document.getElementById('routerPort').value = router.port;
    document.getElementById('routerUser').value = router.username || '';
    document.getElementById('routerPass').value = '';
    document.getElementById('routerPass').placeholder = '******** (kosongkan jika tidak diubah)';
    document.getElementById('routerApiPort').value = router.api_port || 8728;
    document.getElementById('routerSshPort').value = router.ssh_port || 22;
    document.getElementById('routerModal').style.display = 'flex';
}

function confirmDelete(id, nama) {
    Swal.fire({
        title: '⚠️ Hapus Router',
        html: `Apakah Anda yakin ingin menghapus router <strong style="color:#e74c3c;">${escapeHtml(nama)}</strong>?<br><span style="font-size:12px; color:#7f8c8d;">Data yang dihapus tidak dapat dikembalikan!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: '✅ Ya, Hapus!',
        cancelButtonText: '❌ Batal',
        background: '#fff',
        backdrop: 'rgba(0,0,0,0.4)',
        customClass: { popup: 'swal2-popup-custom' }
    }).then((result) => {
        if (result.isConfirmed) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="nama" value="' + escapeHtml(nama) + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function closeModal() {
    document.getElementById('routerModal').style.display = 'none';
}

window.onclick = function(event) {
    var modal = document.getElementById('routerModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// =============================================================
// TEST ROUTER CONNECTION
// =============================================================
function testRouterConnection() {
    var ip = $('#routerIp').val();
    var port = $('#routerPort').val();
    var username = $('#routerUser').val();
    var password = $('#routerPass').val();
    
    if (!ip) {
        Swal.fire('Peringatan', 'IP Address harus diisi', 'warning');
        return;
    }
    if (!username) {
        Swal.fire('Peringatan', 'Username harus diisi', 'warning');
        return;
    }
    if (!port) port = 80;
    
    Swal.fire({
        title: 'Menguji koneksi...',
        text: 'Menghubungi MikroTik',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    $.ajax({
        url: '?page=manage_router&action=test_router',
        type: 'GET',
        data: { ip: ip, port: port, username: username, password: password },
        dataType: 'json',
        timeout: 15000,
        success: function(data) {
            if (data.status === 'success') {
                Swal.fire('✅ Berhasil', data.message, 'success');
            } else {
                Swal.fire('❌ Gagal', data.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            Swal.fire('Error', 'Gagal: ' + error, 'error');
        }
    });
}

// ============================================================
// SYNC SINGLE ROUTER - LENGKAP DENGAN SEMUA FUNGSI
// ============================================================

let isSyncing = false;

// ============================================================
// FUNGSI REFRESH - PASTI BERHASIL
// ============================================================
function refreshPageNow() {
    window.location.href = '?page=manage_router&refresh=' + new Date().getTime();
}

// ============================================================
// FUNGSI MENUTUP MODAL SAJA (TANPA REFRESH)
// ============================================================
function closeModalOnly() {
    const modal = document.getElementById('syncCustomModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = '';
    }
    isSyncing = false;
}

// ============================================================
// FUNGSI UPDATE PROGRESS STEP
// ============================================================
function updateSyncStep(step, status, message, progressPercent = null) {
    const stepElement = document.getElementById(`step${step}`);
    if (!stepElement) return;
    
    const badge = document.querySelector(`#step${step}Status .sync-status-badge`);
    const descElement = stepElement.querySelector('.sync-step-desc');
    const iconElement = stepElement.querySelector('.sync-step-icon i');
    
    if (badge) {
        badge.className = `sync-status-badge ${status}`;
        if (status === 'progress') {
            badge.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Proses...';
            if (iconElement) iconElement.className = 'fa-solid fa-spinner fa-spin';
        } else if (status === 'success') {
            badge.innerHTML = '<i class="fa-solid fa-check"></i> Selesai';
            if (iconElement) iconElement.className = 'fa-solid fa-circle-check';
        } else if (status === 'error') {
            badge.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Gagal';
            if (iconElement) iconElement.className = 'fa-solid fa-circle-exclamation';
        } else {
            badge.innerHTML = 'Menunggu';
            if (iconElement) iconElement.className = 'fa-regular fa-circle';
        }
    }
    
    if (descElement) descElement.innerHTML = message;
    
    // Update class step
    stepElement.classList.remove('active', 'completed', 'error');
    if (status === 'progress') stepElement.classList.add('active');
    else if (status === 'success') stepElement.classList.add('completed');
    else if (status === 'error') stepElement.classList.add('error');
    
    // Update progress bar
    if (progressPercent !== null) {
        const progressBar = document.getElementById('syncMainProgressBar');
        const progressText = document.getElementById('syncMainProgressText');
        if (progressBar) {
            progressBar.style.width = progressPercent + '%';
            if (progressText) progressText.innerHTML = progressPercent + '%';
        }
    }
}

// ============================================================
// FUNGSI MENAMPILKAN HASIL SINKRONISASI
// ============================================================
function showSyncResult(success, totalUsers, updated, inserted, message) {
    const stepsContainer = document.getElementById('syncStepsContainer');
    const resultSummary = document.getElementById('syncResultSummary');
    const resultDetail = document.getElementById('syncResultDetail');
    const resultTitle = document.getElementById('syncResultTitle');
    const resultIcon = document.querySelector('#syncResultSummary .sync-result-icon i');
    
    if (stepsContainer) stepsContainer.style.display = 'none';
    if (resultSummary) resultSummary.style.display = 'flex';
    
    if (success) {
        if (resultIcon) {
            resultIcon.className = 'fa-solid fa-circle-check';
            resultIcon.style.color = '#10b981';
        }
        if (resultTitle) resultTitle.innerHTML = '✅ Sinkronisasi Selesai!';
        if (resultDetail) {
            resultDetail.innerHTML = `
                <div class="sync-stats">
                    <div class="sync-stat">
                        <span class="sync-stat-value">${formatNumber(totalUsers)}</span>
                        <span class="sync-stat-label">Total User Aktif</span>
                    </div>
                    <div class="sync-stat">
                        <span class="sync-stat-value">${formatNumber(updated)}</span>
                        <span class="sync-stat-label">Data Update</span>
                    </div>
                    <div class="sync-stat">
                        <span class="sync-stat-value">${formatNumber(inserted)}</span>
                        <span class="sync-stat-label">Data Baru</span>
                    </div>
                </div>
                <p class="sync-message">${escapeHtml(message)}</p>
                <p style="margin-top: 10px; font-size: 12px; color: #f39c12; background: #fff3e0; padding: 8px; border-radius: 8px;">
                    <i class="fa-solid fa-circle-info"></i> <strong>INFO:</strong> Klik tombol <strong style="color:#f39c12">"Refresh"</strong> di bawah untuk melihat data terbaru!
                </p>
            `;
        }
    } else {
        if (resultIcon) {
            resultIcon.className = 'fa-solid fa-circle-exclamation';
            resultIcon.style.color = '#ef4444';
        }
        if (resultTitle) resultTitle.innerHTML = '❌ Sinkronisasi Gagal!';
        if (resultDetail) {
            resultDetail.innerHTML = `
                <p class="sync-error-message">${escapeHtml(message)}</p>
                <p class="sync-tips">💡 Tips: Periksa koneksi jaringan, username, dan password router.</p>
            `;
        }
    }
    
    isSyncing = false;
}

// ============================================================
// PROSES SINKRONISASI UTAMA
// ============================================================
async function startSyncProcess(routerId, routerNama) {
    updateSyncStep(1, 'progress', 'Menghubungkan ke router...', 10);
    await delay(300);
    
    updateSyncStep(2, 'progress', 'Memverifikasi kredensial...', 25);
    await delay(300);
    
    updateSyncStep(3, 'progress', 'Mengambil data dari router...', 50);
    
    try {
        const response = await fetch(`sync_single_router_ajax.php?router_id=${routerId}&t=${new Date().getTime()}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        
        const textResponse = await response.text();
        let result;
        
        try {
            result = JSON.parse(textResponse);
        } catch (e) {
            throw new Error('Respons dari server tidak valid');
        }
        
        if (result.status === 'SUCCESS') {
            updateSyncStep(1, 'success', 'Berhasil terhubung ke router', 30);
            updateSyncStep(2, 'success', 'Autentikasi berhasil', 55);
            updateSyncStep(3, 'success', `Berhasil mengambil ${formatNumber(result.total_users || 0)} user data`, 75);
            updateSyncStep(4, 'progress', 'Menyimpan ke database...', 85);
            await delay(500);
            updateSyncStep(4, 'success', `${formatNumber(result.updated || 0)} update, ${formatNumber(result.inserted || 0)} baru`, 95);
            updateSyncStep(5, 'progress', 'Menyelesaikan sinkronisasi...', 98);
            await delay(500);
            updateSyncStep(5, 'success', 'Sinkronisasi selesai', 100);
            
            showSyncResult(true, result.total_users, result.updated, result.inserted, result.message);
        } else {
            updateSyncStep(3, 'error', result.message || 'Gagal mengambil data', 50);
            showSyncResult(false, 0, 0, 0, result.message || 'Terjadi kesalahan saat sinkronisasi');
        }
    } catch (error) {
        console.error('Sync error:', error);
        updateSyncStep(3, 'error', error.message, 50);
        showSyncResult(false, 0, 0, 0, error.message);
    }
}

// ============================================================
// MENAMPILKAN MODAL DAN MEMULAI SYNC
// ============================================================
function showAndStartSync(routerId, routerNama, routerIp, routerPort) {
    if (isSyncing) {
        Swal.fire('Info', 'Sinkronisasi sedang berjalan, harap tunggu...', 'info');
        return;
    }
    
    if (document.getElementById('syncCustomModal')) {
        document.getElementById('syncCustomModal').remove();
    }
    
    isSyncing = true;
    
    const modalHtml = `
        <div id="syncCustomModal" class="sync-custom-modal">
            <div class="sync-custom-modal-content">
                <div class="sync-custom-modal-header">
                    <div class="sync-router-icon">
                        <i class="fa-solid fa-server"></i>
                    </div>
                    <div class="sync-router-info">
                        <h3>${escapeHtml(routerNama)}</h3>
                        <p>${routerIp}:${routerPort}</p>
                    </div>
                    <button class="sync-close-btn" onclick="closeModalOnly()">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                
                <div class="sync-custom-modal-body">
                    <div class="sync-main-progress">
                        <div class="sync-main-progress-bar" id="syncMainProgressBar"></div>
                        <div class="sync-main-progress-text" id="syncMainProgressText">0%</div>
                    </div>
                    
                    <div class="sync-steps-container" id="syncStepsContainer">
                        <div class="sync-step-item" id="step1">
                            <div class="sync-step-icon">
                                <i class="fa-solid fa-plug"></i>
                            </div>
                            <div class="sync-step-info">
                                <div class="sync-step-title">Koneksi ke Router</div>
                                <div class="sync-step-desc">Menghubungkan ke server...</div>
                            </div>
                            <div class="sync-step-status" id="step1Status">
                                <span class="sync-status-badge pending">Menunggu</span>
                            </div>
                        </div>
                        
                        <div class="sync-step-item" id="step2">
                            <div class="sync-step-icon">
                                <i class="fa-solid fa-key"></i>
                            </div>
                            <div class="sync-step-info">
                                <div class="sync-step-title">Autentikasi</div>
                                <div class="sync-step-desc">Memverifikasi kredensial...</div>
                            </div>
                            <div class="sync-step-status" id="step2Status">
                                <span class="sync-status-badge pending">Menunggu</span>
                            </div>
                        </div>
                        
                        <div class="sync-step-item" id="step3">
                            <div class="sync-step-icon">
                                <i class="fa-solid fa-arrows-spin"></i>
                            </div>
                            <div class="sync-step-info">
                                <div class="sync-step-title">Sinkronisasi Data</div>
                                <div class="sync-step-desc">Mengambil data dari router...</div>
                            </div>
                            <div class="sync-step-status" id="step3Status">
                                <span class="sync-status-badge pending">Menunggu</span>
                            </div>
                        </div>
                        
                        <div class="sync-step-item" id="step4">
                            <div class="sync-step-icon">
                                <i class="fa-solid fa-database"></i>
                            </div>
                            <div class="sync-step-info">
                                <div class="sync-step-title">Update Database</div>
                                <div class="sync-step-desc">Menyimpan data ke database...</div>
                            </div>
                            <div class="sync-step-status" id="step4Status">
                                <span class="sync-status-badge pending">Menunggu</span>
                            </div>
                        </div>
                        
                        <div class="sync-step-item" id="step5">
                            <div class="sync-step-icon">
                                <i class="fa-solid fa-flag-checkered"></i>
                            </div>
                            <div class="sync-step-info">
                                <div class="sync-step-title">Finalisasi</div>
                                <div class="sync-step-desc">Menyelesaikan sinkronisasi...</div>
                            </div>
                            <div class="sync-step-status" id="step5Status">
                                <span class="sync-status-badge pending">Menunggu</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sync-result-summary" id="syncResultSummary">
                        <div class="sync-result-icon">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="sync-result-text">
                            <h4 id="syncResultTitle">Sinkronisasi Selesai</h4>
                            <div id="syncResultDetail"></div>
                        </div>
                    </div>
                </div>
                
                <div class="sync-custom-modal-footer">
                    <button class="sync-btn sync-btn-secondary" onclick="closeModalOnly()">
                        <i class="fa-solid fa-times"></i> Tutup
                    </button>
                    <button class="sync-btn" onclick="refreshPageNow()" style="background: #f39c12; color: white; border: none; cursor: pointer; padding: 5px 12px; border-radius: 20px; font-size: 11px;">
                        <i class="fa-solid fa-arrows-rotate"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.body.style.overflow = 'hidden';
    startSyncProcess(routerId, routerNama);
}

// ============================================================
// EVENT LISTENER UNTUK TOMBOL SYNC SINGLE
// ============================================================
document.querySelectorAll('.btn-sync-single').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (isSyncing) {
            Swal.fire('Info', 'Sinkronisasi sedang berjalan, harap tunggu...', 'info');
            return;
        }
        const id = this.dataset.id;
        const nama = this.dataset.nama;
        const ip = this.dataset.ip;
        const port = this.dataset.port;
        showAndStartSync(id, nama, ip, port);
    });
});

// =============================================================
// SYNC ALL ROUTERS
// =============================================================

function syncAllRouters() {
    var btn = $('#btnSyncAllRouter');
    if (btn.data('syncing') === true) { 
        Swal.fire({ title: 'Info', text: 'Sync sedang berjalan...', icon: 'info' });
        return; 
    }
    
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Syncing...');
    btn.data('syncing', true);
    $('#syncProgress').addClass('show');
    $('#syncProgressBody').html('<div class="sync-item pending"><i class="fa-solid fa-spinner fa-spin me-2"></i> Mengambil daftar router...</div>');
    
    $.ajax({
        url: 'get_active_routers.php',
        type: 'GET',
        dataType: 'json',
        timeout: 10000,
        success: function(routers) {
            if (!routers || routers.length === 0) {
                $('#syncProgressBody').html('<div class="sync-item error">Tidak ada router aktif!</div>');
                setTimeout(function() { $('#syncProgress').removeClass('show'); }, 3000);
                resetSyncButton(btn);
                Swal.fire({ title: 'Gagal', text: 'Tidak ada router aktif', icon: 'error' });
                return;
            }
            
            var total = routers.length, completed = 0, successCount = 0, failCount = 0;
            $('#syncProgressBody').html('');
            var summaryHtml = '<div class="sync-item pending mb-2" id="syncSummary"><i class="fa-solid fa-info-circle me-2"></i> Menyinkronkan ' + total + ' router...</div>';
            $('#syncProgressBody').append(summaryHtml);
            
            routers.forEach(function(router) {
                var syncItem = $('<div class="sync-item pending"> <strong>' + escapeHtml(router.nama) + '</strong> (' + router.ip_address + ') - Menyinkronkan...</div>');
                $('#syncProgressBody').append(syncItem);
                
                $.ajax({
                    url: 'sync_single_router_ajax.php',
                    type: 'GET',
                    data: { router_id: router.id },
                    dataType: 'json',
                    timeout: 120000,
                    success: function(r) {
                        if (r.status === 'SUCCESS') { 
                            successCount++; 
                            syncItem.html('✅ ' + escapeHtml(router.nama) + ' - Berhasil! (' + formatNumber(r.total_users || 0) + ' user)'); 
                            syncItem.addClass('success'); 
                        } else { 
                            failCount++; 
                            syncItem.html('❌ ' + escapeHtml(router.nama) + ' - Gagal: ' + (r.message || '')); 
                            syncItem.addClass('error'); 
                        }
                        completed++;
                        updateSyncAllProgress(completed, total, successCount, failCount);
                        if (completed === total) finalizeSyncAll(successCount, failCount, btn);
                    },
                    error: function() {
                        failCount++; 
                        syncItem.html('❌ ' + escapeHtml(router.nama) + ' - Error koneksi'); 
                        syncItem.addClass('error');
                        completed++;
                        updateSyncAllProgress(completed, total, successCount, failCount);
                        if (completed === total) finalizeSyncAll(successCount, failCount, btn);
                    }
                });
            });
        },
        error: function() {
            $('#syncProgressBody').html('<div class="sync-item error">Gagal mengambil daftar router!</div>');
            setTimeout(function() { $('#syncProgress').removeClass('show'); }, 3000);
            resetSyncButton(btn);
            Swal.fire({ title: 'Gagal', text: 'Gagal mengambil daftar router', icon: 'error' });
        }
    });
}

function updateSyncAllProgress(completed, total, successCount, failCount) {
    var summary = $('#syncSummary');
    if (summary.length) summary.html('Progres: ' + completed + '/' + total + ' | ✅ ' + successCount + ' | ❌ ' + failCount);
    $('#syncProgress .sync-progress-header').html('Sinkronisasi (' + completed + '/' + total + ')');
}

function finalizeSyncAll(successCount, failCount, btn) {
    $('#syncProgress .sync-progress-header').html('Selesai (' + successCount + ' sukses, ' + failCount + ' gagal)');
    setTimeout(function() { $('#syncProgress').removeClass('show'); window.location.reload(); }, 3000);
    resetSyncButton(btn);
    
    if (failCount === 0 && successCount > 0) {
        Swal.fire({ title: 'Selesai', text: successCount + ' router berhasil disinkronkan', icon: 'success' });
    } else if (successCount > 0 && failCount > 0) {
        Swal.fire({ title: 'Sebagian Berhasil', text: successCount + ' berhasil, ' + failCount + ' gagal', icon: 'warning' });
    } else {
        Swal.fire({ title: 'Gagal', text: 'Semua router gagal disinkronkan', icon: 'error' });
    }
}

function resetSyncButton(btn) {
    btn.prop('disabled', false).html('<i class="fa-solid fa-cloud-arrow-up"></i> Sync Semua Router');
    btn.data('syncing', false);
}

$('#btnSyncAllRouter').on('click', syncAllRouters);
</script>
</body>
</html>