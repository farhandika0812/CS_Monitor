<?php
// debug_router_sync.php
// Professional Debugging Tool for Router Sync Issues
// Location: pages/debug_router_sync.php

session_start();

// =============================================================
// FIXED PATH - Menyesuaikan dengan struktur folder yang benar
// =============================================================

// Coba beberapa kemungkinan path untuk db_connection
$possible_paths = [
    '../config/db_connection.php',
    '../includes/db_connection.php',
    'config/db_connection.php',
    'includes/db_connection.php',
    '../../config/db_connection.php',
    '../db_connection.php'
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

// Jika masih tidak ditemukan, coba cari file koneksi dari manage_router.php
if (!$db_loaded && file_exists('manage_router.php')) {
    $content = file_get_contents('manage_router.php');
    if (preg_match('/require_once\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        $db_path = $matches[1];
        if (file_exists($db_path)) {
            require_once $db_path;
            $db_loaded = true;
        }
    }
}

// Jika tetap tidak ditemukan, buat koneksi manual
if (!$db_loaded) {
    // Database configuration - SESUAIKAN DENGAN KONFIGURASI ANDA
    $db_host = 'localhost';
    $db_name = 'db_panglimanet'; // Ganti dengan nama database Anda
    $db_user = 'root';         // Ganti dengan username database Anda
    $db_pass = '';             // Ganti dengan password database Anda
    
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Error: Tidak dapat terhubung ke database.<br>
             Pastikan file config/db_connection.php ada atau setting database manual.<br>
             Error: " . $e->getMessage());
    }
}

// Set execution limits for debugging
set_time_limit(120);
ini_set('memory_limit', '256M');

// Check if user is logged in (sesuaikan dengan sistem login Anda)
if (!isset($_SESSION['username']) && !isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
    // Jika tidak ada session, cek cookie atau biarkan akses untuk debugging
    // Hapus komentar baris di bawah jika memang perlu login
    // header('Location: login.php');
    // exit;
}

// Get router ID from parameter
$selected_router_id = isset($_GET['router_id']) ? intval($_GET['router_id']) : 0;
$run_all = isset($_GET['run_all']) ? true : false;
$ajax_mode = isset($_GET['ajax']) ? true : false;

// If AJAX mode, return JSON
if ($ajax_mode) {
    header('Content-Type: application/json');
    $router_id = intval($_GET['router_id'] ?? 0);
    if (!$router_id) {
        echo json_encode(['error' => 'Router ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM router_list WHERE id = ?");
    $stmt->execute([$router_id]);
    $router = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$router) {
        echo json_encode(['error' => 'Router not found']);
        exit;
    }
    
    $result = analyzeRouter($router, $pdo);
    echo json_encode($result);
    exit;
}

// Get all routers for selection
$routers = [];
try {
    $stmt = $pdo->query("SELECT id, nama, ip_address, port, username, is_active FROM router_list ORDER BY id");
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabel mungkin belum ada
    $routers = [];
}

// If run_all is true, analyze all routers
if ($run_all && !$selected_router_id && !empty($routers)) {
    $all_results = [];
    foreach ($routers as $router) {
        $all_results[$router['id']] = analyzeRouter($router, $pdo);
    }
}
// Analyze single router
elseif ($selected_router_id) {
    $stmt = $pdo->prepare("SELECT * FROM router_list WHERE id = ?");
    $stmt->execute([$selected_router_id]);
    $selected_router = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($selected_router) {
        $single_result = analyzeRouter($selected_router, $pdo);
    }
}

/**
 * Analyze a single router for all possible issues
 */
function analyzeRouter($router, $pdo) {
    $result = [
        'router_id' => $router['id'],
        'router_name' => $router['nama'],
        'ip_address' => $router['ip_address'],
        'port' => $router['port'],
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => [],
        'overall_status' => 'unknown',
        'recommendations' => []
    ];
    
    // =============================================================
    // CHECK 1: Basic Connectivity (Ping)
    // =============================================================
    $ping_result = [
        'name' => 'Koneksi Dasar (Ping)',
        'status' => 'pending',
        'message' => '',
        'details' => []
    ];
    
    // Windows uses ping -n, Linux uses ping -c
    $ping_cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
        ? "ping -n 1 -w 2 " . escapeshellarg($router['ip_address'])
        : "ping -c 1 -W 2 " . escapeshellarg($router['ip_address']);
    
    $ping_output = [];
    $ping_return = 0;
    exec($ping_cmd, $ping_output, $ping_return);
    
    if ($ping_return == 0) {
        $ping_result['status'] = 'success';
        $ping_result['message'] = 'Router merespon ping';
        // Extract ping time
        if (preg_match('/(\\d+)ms/', implode(' ', $ping_output), $matches)) {
            $ping_result['details']['latency'] = $matches[1] . ' ms';
        }
    } else {
        $ping_result['status'] = 'error';
        $ping_result['message'] = 'Router tidak merespon ping - periksa kabel/switch/firewall';
    }
    $result['checks'][] = $ping_result;
    
    // =============================================================
    // CHECK 2: Port Connectivity (telnet style)
    // =============================================================
    $ports_to_check = [
        ['port' => 80, 'name' => 'HTTP (Web)', 'service' => 'www'],
        ['port' => 443, 'name' => 'HTTPS (Web SSL)', 'service' => 'www-ssl'],
        ['port' => 8728, 'name' => 'API (Native)', 'service' => 'api'],
        ['port' => 8729, 'name' => 'API SSL', 'service' => 'api-ssl'],
        ['port' => 22, 'name' => 'SSH', 'service' => 'ssh'],
        ['port' => 23, 'name' => 'Telnet', 'service' => 'telnet'],
    ];
    
    foreach ($ports_to_check as $port_info) {
        $port_result = [
            'name' => $port_info['name'],
            'status' => 'pending',
            'message' => '',
            'details' => []
        ];
        
        $connection = @fsockopen($router['ip_address'], $port_info['port'], $errno, $errstr, 3);
        if ($connection) {
            $port_result['status'] = 'success';
            $port_result['message'] = "Port {$port_info['port']} terbuka";
            fclose($connection);
            
            // Try to get service banner for SSH
            if ($port_info['port'] == 22) {
                $ssh = @fsockopen($router['ip_address'], 22, $errno, $errstr, 2);
                if ($ssh) {
                    $banner = fread($ssh, 255);
                    if (preg_match('/SSH-([\\d\\.]+)/', $banner, $matches)) {
                        $port_result['details']['ssh_version'] = $matches[1];
                    }
                    fclose($ssh);
                }
            }
        } else {
            $port_result['status'] = 'warning';
            $port_result['message'] = "Port {$port_info['port']} tidak terbuka: $errstr";
        }
        $result['checks'][] = $port_result;
    }
    
    // =============================================================
    // CHECK 3: REST API Availability (if port 80/443 open)
    // =============================================================
    $rest_result = [
        'name' => 'REST API',
        'status' => 'pending',
        'message' => '',
        'details' => []
    ];
    
    // Try REST API via HTTP
    $rest_urls = [
        "http://{$router['ip_address']}:{$router['port']}/rest/system/resource",
        "http://{$router['ip_address']}:{$router['port']}/rest/system/identity",
        "https://{$router['ip_address']}:{$router['port']}/rest/system/resource"
    ];
    
    $rest_working = false;
    foreach ($rest_urls as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $router['username'] . ':' . $router['password']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $rest_working = true;
            $rest_result['status'] = 'success';
            $rest_result['message'] = 'REST API aktif dan merespon';
            $rest_result['details']['url'] = $url;
            $rest_result['details']['http_code'] = $http_code;
            
            // Parse response to get RouterOS version
            $data = json_decode($response, true);
            if ($data && isset($data['version'])) {
                $rest_result['details']['routeros_version'] = $data['version'];
            }
            break;
        } elseif ($http_code == 401) {
            $rest_result['status'] = 'warning';
            $rest_result['message'] = 'REST API memerlukan autentikasi - cek username/password';
            $rest_result['details']['url'] = $url;
            $rest_result['details']['http_code'] = $http_code;
            break;
        } elseif ($http_code == 404) {
            $rest_result['status'] = 'warning';
            $rest_result['message'] = 'REST API endpoint tidak ditemukan - mungkin REST API tidak aktif';
            $rest_result['details']['url'] = $url;
            break;
        }
    }
    
    if (!$rest_working && $rest_result['status'] == 'pending') {
        $rest_result['status'] = 'error';
        $rest_result['message'] = 'REST API tidak dapat diakses - pastikan service www aktif dan REST API di-enable';
        $result['recommendations'][] = 'Aktifkan REST API dengan perintah: /ip service enable www';
    }
    $result['checks'][] = $rest_result;
    
    // =============================================================
    // CHECK 4: Mikrotik API (Port 8728) - Jika file API tersedia
    // =============================================================
    $api_result = [
        'name' => 'Mikrotik API (Native)',
        'status' => 'pending',
        'message' => '',
        'details' => []
    ];
    
    // Cari file MikrotikAPI.php
    $api_paths = [
        '../includes/MikrotikAPI.php',
        'includes/MikrotikAPI.php',
        '../MikrotikAPI.php',
        'MikrotikAPI.php'
    ];
    
    $api_loaded = false;
    foreach ($api_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $api_loaded = true;
            break;
        }
    }
    
    if ($api_loaded && class_exists('RouterosAPI')) {
        $API = new RouterosAPI();
        if (method_exists($API, 'setTimeout')) {
            $API->setTimeout(10);
        }
        
        $api_port = $router['api_port'] ?? 8728;
        
        if ($API->connect($router['ip_address'], $router['username'], $router['password'], $api_port)) {
            // Try to get system resource
            $resource = $API->comm('/system/resource/print');
            if ($resource && count($resource) > 0) {
                $api_result['status'] = 'success';
                $api_result['message'] = 'API berhasil terkoneksi';
                $api_result['details']['board_name'] = $resource[0]['board-name'] ?? 'N/A';
                $api_result['details']['version'] = $resource[0]['version'] ?? 'N/A';
                $api_result['details']['cpu_load'] = ($resource[0]['cpu-load'] ?? '0') . '%';
                $api_result['details']['free_memory'] = isset($resource[0]['free-memory']) ? formatBytes($resource[0]['free-memory']) : 'N/A';
                $api_result['details']['total_memory'] = isset($resource[0]['total-memory']) ? formatBytes($resource[0]['total-memory']) : 'N/A';
                $api_result['details']['uptime'] = $resource[0]['uptime'] ?? 'N/A';
                
                // Check RouterOS version for REST API support
                $version = $resource[0]['version'] ?? '';
                if (version_compare($version, '6.43', '>=')) {
                    $api_result['details']['rest_support'] = 'Ya (v' . $version . ' ≥ 6.43)';
                } else {
                    $api_result['details']['rest_support'] = 'Tidak (v' . $version . ' < 6.43)';
                    $result['recommendations'][] = 'Upgrade RouterOS ke v6.43+ untuk mendukung REST API yang lebih cepat';
                }
            } else {
                $api_result['status'] = 'warning';
                $api_result['message'] = 'API terkoneksi tapi gagal mengambil data';
            }
            
            // Try to get PPPoE data
            $pppoe = $API->comm('/ppp/active/print');
            if ($pppoe && count($pppoe) > 0) {
                $api_result['details']['pppoe_active_count'] = count($pppoe);
            }
            
            $API->disconnect();
        } else {
            $api_result['status'] = 'error';
            $api_result['message'] = 'Gagal koneksi ke API - periksa username/password dan API port';
            $result['recommendations'][] = 'Pastikan service API aktif di router: /ip service enable api';
        }
    } else {
        $api_result['status'] = 'info';
        $api_result['message'] = 'File MikrotikAPI.php tidak ditemukan - skip pengecekan API native';
    }
    $result['checks'][] = $api_result;
    
    // =============================================================
    // CHECK 5: Database Sync History
    // =============================================================
    $db_result = [
        'name' => 'Database Status',
        'status' => 'pending',
        'message' => '',
        'details' => []
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT last_sync, total_users, total_active, is_active FROM router_list WHERE id = ?");
        $stmt->execute([$router['id']]);
        $db_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($db_data) {
            $db_result['status'] = 'success';
            $db_result['message'] = 'Data router tersimpan di database';
            $db_result['details']['last_sync'] = $db_data['last_sync'] ?? 'Belum pernah sync';
            $db_result['details']['total_users_db'] = number_format($db_data['total_users'] ?? 0);
            $db_result['details']['total_active_db'] = number_format($db_data['total_active'] ?? 0);
            $db_result['details']['is_active_db'] = ($db_data['is_active'] ?? 0) ? 'Aktif' : 'Tidak Aktif';
            
            if (($db_data['is_active'] ?? 0) == 0) {
                $result['recommendations'][] = "Router {$router['nama']} terdaftar sebagai tidak aktif di database. Lakukan sync manual untuk mengaktifkan kembali.";
            }
        } else {
            $db_result['status'] = 'error';
            $db_result['message'] = 'Data router tidak ditemukan di database';
        }
    } catch (PDOException $e) {
        $db_result['status'] = 'error';
        $db_result['message'] = 'Error database: ' . $e->getMessage();
    }
    $result['checks'][] = $db_result;
    
    // =============================================================
    // Determine Overall Status
    // =============================================================
    $error_count = 0;
    $warning_count = 0;
    $success_count = 0;
    
    foreach ($result['checks'] as $check) {
        if ($check['status'] == 'error') $error_count++;
        if ($check['status'] == 'warning') $warning_count++;
        if ($check['status'] == 'success') $success_count++;
    }
    
    if ($error_count == 0 && $warning_count == 0) {
        $result['overall_status'] = 'success';
        $result['overall_message'] = '✅ Semua sistem berfungsi normal. Router siap disinkronisasi.';
    } elseif ($error_count > 0) {
        $result['overall_status'] = 'error';
        $result['overall_message'] = "❌ Ditemukan {$error_count} error yang perlu segera diperbaiki";
    } else {
        $result['overall_status'] = 'warning';
        $result['overall_message'] = "⚠️ Ditemukan {$warning_count} peringatan, sinkronisasi mungkin bermasalah";
    }
    
    return $result;
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'success': return '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Berhasil</span>';
        case 'warning': return '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Peringatan</span>';
        case 'error': return '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Gagal</span>';
        case 'info': return '<span class="badge badge-info"><i class="fas fa-info-circle"></i> Info</span>';
        default: return '<span class="badge badge-secondary">Unknown</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Router Sync Debugger - Diagnostic Tool</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .header h1 i {
            color: #667eea;
            margin-right: 12px;
        }
        
        .header p {
            color: #718096;
            font-size: 14px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .card-header h2 i {
            margin-right: 10px;
            color: #667eea;
        }
        
        .card-body {
            padding: 24px;
        }
        
        .router-selector {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .router-selector select {
            padding: 10px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: white;
            min-width: 250px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .router-selector select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: #4a5568;
        }
        
        .btn-outline:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .diagnostic-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .diagnostic-table tr {
            border-bottom: 1px solid #edf2f7;
        }
        
        .diagnostic-table tr:last-child {
            border-bottom: none;
        }
        
        .diagnostic-table td {
            padding: 16px 12px;
            vertical-align: top;
        }
        
        .diagnostic-table td:first-child {
            width: 220px;
            font-weight: 600;
            color: #2d3748;
            background: #fafbfc;
        }
        
        .check-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-warning {
            background: #feebc8;
            color: #7c2d12;
        }
        
        .badge-danger {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .badge-info {
            background: #bee3f8;
            color: #2c5282;
        }
        
        .badge-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        .loading-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .recommendations {
            background: #fffaf0;
            border-left: 4px solid #ed8936;
            padding: 15px 20px;
            border-radius: 12px;
            margin-top: 15px;
        }
        
        .recommendations h4 {
            color: #c05621;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .recommendations ul {
            margin-left: 20px;
            color: #744210;
        }
        
        .recommendations li {
            margin: 6px 0;
            font-size: 13px;
        }
        
        .overall-status-banner {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .routers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .routers-grid {
                grid-template-columns: 1fr;
            }
            .diagnostic-table td:first-child {
                width: 130px;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .comparison-table th,
        .comparison-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .comparison-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #2d3748;
        }
        
        .comparison-table tr:hover {
            background: #fafbfc;
        }
        
        .text-success { color: #38a169; }
        .text-warning { color: #ed8936; }
        .text-danger { color: #e53e3e; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-stethoscope"></i>
            Router Sync Diagnostic Tool
        </h1>
        <p>
            <i class="fas fa-info-circle"></i>
            Tool ini menganalisa semua kemungkinan penyebab kegagalan sinkronisasi router
        </p>
    </div>
    
    <!-- Router Selection -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-router"></i> Pilih Router untuk Diagnosa</h2>
            <div class="router-selector">
                <select id="routerSelect">
                    <option value="">-- Pilih Router --</option>
                    <?php foreach ($routers as $router): ?>
                        <option value="<?php echo $router['id']; ?>" <?php echo ($selected_router_id == $router['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($router['nama']); ?> (<?php echo $router['ip_address']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" id="diagnoseBtn" onclick="diagnoseSingle()">
                    <i class="fas fa-play"></i> Diagnosa Sekarang
                </button>
                <?php if (count($routers) > 1): ?>
                <button class="btn btn-outline" id="diagnoseAllBtn" onclick="diagnoseAll()">
                    <i class="fas fa-layer-group"></i> Diagnosa Semua Router
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3>Sedang Menganalisa Router...</h3>
            <p>Mohon tunggu, proses ini mungkin memakan waktu beberapa detik</p>
        </div>
    </div>
    
    <!-- Results Container -->
    <div id="resultsContainer">
        <?php if ($run_all && !empty($all_results)): ?>
            <div class="routers-grid">
                <?php foreach ($all_results as $router_id => $result): ?>
                    <div class="card">
                        <?php echo renderRouterResult($result); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Comparison Table -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Perbandingan Semua Router</h2>
                </div>
                <div class="card-body">
                    <?php echo renderComparisonTable($all_results); ?>
                </div>
            </div>
        <?php elseif ($selected_router_id && isset($single_result)): ?>
            <div class="card">
                <?php echo renderRouterResult($single_result); ?>
            </div>
        <?php elseif (empty($routers)): ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 40px;">
                    <i class="fas fa-database" style="font-size: 48px; color: #cbd5e0; margin-bottom: 20px;"></i>
                    <h3 style="color: #4a5568;">Belum Ada Data Router</h3>
                    <p style="color: #718096; margin-top: 10px;">Silakan tambahkan router terlebih dahulu melalui halaman Manajemen Router</p>
                    <a href="?page=manage_router" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i> Tambah Router
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function diagnoseSingle() {
    const routerId = document.getElementById('routerSelect').value;
    if (!routerId) {
        Swal.fire({
            title: 'Pilih Router',
            text: 'Silakan pilih router terlebih dahulu',
            icon: 'warning',
            confirmButtonColor: '#667eea'
        });
        return;
    }
    
    showLoading();
    window.location.href = `?router_id=${routerId}`;
}

function diagnoseAll() {
    showLoading();
    window.location.href = '?run_all=1';
}
</script>

</body>
</html>

<?php
function renderRouterResult($result) {
    $html = '
    <div class="card-header">
        <h2>
            <i class="fas fa-server"></i>
            ' . htmlspecialchars($result['router_name']) . '
            <small style="font-size:12px; color:#718096;"> (' . $result['ip_address'] . ')</small>
        </h2>
        <div>' . getStatusBadge($result['overall_status']) . '</div>
    </div>
    <div class="card-body">';
    
    $bg_color = $result['overall_status'] == 'success' ? '#c6f6d5' : ($result['overall_status'] == 'warning' ? '#feebc8' : '#fed7d7');
    $text_color = $result['overall_status'] == 'success' ? '#22543d' : ($result['overall_status'] == 'warning' ? '#7c2d12' : '#742a2a');
    
    $html .= '
        <div class="overall-status-banner" style="background: ' . $bg_color . '; color: ' . $text_color . ';">
            <i class="fas fa-' . ($result['overall_status'] == 'success' ? 'check-circle' : ($result['overall_status'] == 'warning' ? 'exclamation-triangle' : 'times-circle')) . '"></i>
            ' . htmlspecialchars($result['overall_message']) . '
        </div>
        
        <table class="diagnostic-table">
            <tbody>';
    
    foreach ($result['checks'] as $check) {
        $icon = $check['status'] == 'success' ? 'check-circle' : ($check['status'] == 'warning' ? 'exclamation-triangle' : ($check['status'] == 'error' ? 'times-circle' : 'info-circle'));
        $icon_color = $check['status'] == 'success' ? '#38a169' : ($check['status'] == 'warning' ? '#ed8936' : ($check['status'] == 'error' ? '#e53e3e' : '#3182ce'));
        
        $html .= '
            <tr>
                <td>
                    <div class="check-name">
                        <i class="fas fa-' . $icon . '" style="color: ' . $icon_color . ';"></i>
                        ' . htmlspecialchars($check['name']) . '
                    </div>
                </td>
                <td>
                    ' . getStatusBadge($check['status']) . '
                    <div style="margin-top: 8px; font-size: 13px; color: #4a5568;">' . htmlspecialchars($check['message']) . '</div>';
        
        if (!empty($check['details'])) {
            $html .= '<details style="margin-top: 8px;">
                        <summary style="cursor: pointer; font-size: 12px; color: #667eea;">📋 Lihat Detail</summary>
                        <div style="margin-top: 8px; padding: 10px; background: #f7fafc; border-radius: 8px; font-family: monospace; font-size: 11px;">';
            foreach ($check['details'] as $key => $value) {
                if (is_array($value)) {
                    $html .= '<strong>' . htmlspecialchars($key) . ':</strong><br>';
                    foreach ($value as $subkey => $subvalue) {
                        if (is_array($subvalue)) {
                            $html .= '&nbsp;&nbsp;' . htmlspecialchars($subkey) . ': ' . htmlspecialchars(print_r($subvalue, true)) . '<br>';
                        } else {
                            $html .= '&nbsp;&nbsp;' . htmlspecialchars($subvalue) . '<br>';
                        }
                    }
                } else {
                    $html .= '<strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '<br>';
                }
            }
            $html .= '</div></details>';
        }
        
        $html .= '</td>
            </tr>';
    }
    
    $html .= '
            </tbody>
        </table>';
    
    if (!empty($result['recommendations'])) {
        $html .= '
        <div class="recommendations">
            <h4><i class="fas fa-lightbulb"></i> Rekomendasi Perbaikan</h4>
            <ul>';
        foreach ($result['recommendations'] as $rec) {
            $html .= '<li>' . htmlspecialchars($rec) . '</li>';
        }
        $html .= '
            </ul>
        </div>';
    }
    
    $html .= '
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #a0aec0; text-align: right;">
            <i class="fas fa-clock"></i> Diagnosa terakhir: ' . $result['timestamp'] . '
        </div>
    </div>';
    
    return $html;
}

function renderComparisonTable($results) {
    $html = '
    <table class="comparison-table">
        <thead>
            <tr>
                <th>Router</th>
                <th>IP Address</th>
                <th>Status</th>
                <th>REST API</th>
                <th>API Native</th>
                <th>Port 80</th>
                <th>Port 8728</th>
                <th>Last Sync</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($results as $result) {
        $rest_status = 'N/A';
        $api_status = 'N/A';
        $port80_status = 'N/A';
        $port8728_status = 'N/A';
        $last_sync = '-';
        
        foreach ($result['checks'] as $check) {
            if ($check['name'] == 'REST API') {
                $rest_status = $check['status'];
            }
            if ($check['name'] == 'Mikrotik API (Native)') {
                $api_status = $check['status'];
            }
            if ($check['name'] == 'HTTP (Web)') {
                $port80_status = $check['status'];
            }
            if ($check['name'] == 'API (Native)') {
                $port8728_status = $check['status'];
            }
            if ($check['name'] == 'Database Status' && isset($check['details']['last_sync'])) {
                $last_sync = $check['details']['last_sync'];
            }
        }
        
        $status_class = $result['overall_status'] == 'success' ? 'text-success' : ($result['overall_status'] == 'warning' ? 'text-warning' : 'text-danger');
        $status_icon = $result['overall_status'] == 'success' ? 'check-circle' : ($result['overall_status'] == 'warning' ? 'exclamation-triangle' : 'times-circle');
        
        $html .= '
            <tr>
                <td><strong>' . htmlspecialchars($result['router_name']) . '</strong></td>
                <td>' . htmlspecialchars($result['ip_address']) . '</td>
                <td class="' . $status_class . '"><i class="fas fa-' . $status_icon . '"></i> ' . ucfirst($result['overall_status']) . '</td>
                <td>' . getStatusBadge($rest_status) . '</td>
                <td>' . getStatusBadge($api_status) . '</td>
                <td>' . getStatusBadge($port80_status) . '</td>
                <td>' . getStatusBadge($port8728_status) . '</td>
                <td>' . htmlspecialchars($last_sync) . '</td>
            </tr>';
    }
    
    $html .= '
        </tbody>
    </table>';
    
    return $html;
}
?>