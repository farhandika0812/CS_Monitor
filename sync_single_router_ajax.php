<?php
// sync_single_router_ajax.php - FINAL VERSION
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';

$router_id = isset($_GET['router_id']) ? intval($_GET['router_id']) : 0;

if ($router_id <= 0) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Router ID tidak valid']);
    exit;
}

// Ambil data router
$stmt = $pdo->prepare("SELECT * FROM router_list WHERE id = ? AND is_active = 1");
$stmt->execute([$router_id]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Router tidak ditemukan atau tidak aktif']);
    exit;
}

// ============================================
// 1. AMBIL DATA PPPoE USER DARI MIKROTIK
// ============================================
$url = "http://{$router['ip_address']}:{$router['port']}/rest/ppp/secret";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $router['username'] . ":" . $router['password']);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code !== 200) {
    echo json_encode([
        'status' => 'ERROR',
        'message' => "Gagal konek ke router (HTTP $http_code)" . ($curl_error ? " - $curl_error" : "")
    ]);
    exit;
}

$users = json_decode($response, true);
if (!is_array($users)) {
    echo json_encode([
        'status' => 'ERROR',
        'message' => 'Data dari router tidak valid'
    ]);
    exit;
}

// ============================================
// 2. AMBIL DATA ACTIVE SESSION
// ============================================
$active_url = "http://{$router['ip_address']}:{$router['port']}/rest/ppp/active";
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $active_url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 60);
curl_setopt($ch2, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch2, CURLOPT_USERPWD, $router['username'] . ":" . $router['password']);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$active_response = curl_exec($ch2);
$active_http_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$active_users = [];
if ($active_http_code === 200) {
    $active_users_raw = json_decode($active_response, true);
    if (is_array($active_users_raw)) {
        foreach ($active_users_raw as $active) {
            if (isset($active['name'])) {
                $active_users[$active['name']] = $active;
            }
        }
    }
}

// ============================================
// 3. PROSES SINKRONISASI
// ============================================
$inserted = 0;
$updated = 0;

foreach ($users as $user) {
    $id_pelanggan = $user['name'] ?? '';
    if (empty($id_pelanggan)) continue;
    
    $is_disabled = isset($user['disabled']) && $user['disabled'] == 'true';
    $status_berlangganan = $is_disabled ? 'Nonaktif' : 'Aktif';
    
    // Cek online status
    $is_active_online = isset($active_users[$id_pelanggan]);
    $status_ping = $is_active_online ? 'ONLINE' : 'OFFLINE';
    
    // IP Modem dari active session
    $ip_modem = '-';
    if ($is_active_online && isset($active_users[$id_pelanggan]['address'])) {
        $ip_modem = $active_users[$id_pelanggan]['address'];
    }
    
    $pppoe_profile = $user['profile'] ?? '';
    $rate_limit = $user['rate-limit'] ?? '';
    $service_name = $user['service'] ?? 'pppoe';
    
    // Cek apakah sudah ada
    $stmt_cek = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
    $stmt_cek->execute([$id_pelanggan]);
    
    if ($stmt_cek->fetch()) {
        // UPDATE
        $sql = "UPDATE pelanggan SET 
            nama = ?,
            ip_modem = ?,
            status_ping = ?,
            status_berlangganan = ?,
            pppoe_profile = ?,
            rate_limit = ?,
            service_name = ?,
            router_id = ?,
            sync_count = sync_count + 1,
            last_sync = NOW()
            WHERE id_pelanggan = ?";
        $stmt_up = $pdo->prepare($sql);
        $stmt_up->execute([
            $user['name'],
            $ip_modem,
            $status_ping,
            $status_berlangganan,
            $pppoe_profile,
            $rate_limit,
            $service_name,
            $router_id,
            $id_pelanggan
        ]);
        $updated++;
    } else {
        // INSERT
        $sql = "INSERT INTO pelanggan (
            id_pelanggan, nama, alamat, ip_modem, status_ping,
            status_pembayaran, tagihan, no_telp,
            status_berlangganan, pppoe_profile, rate_limit, service_name,
            router_id, sync_count, last_sync, created_at
        ) VALUES (
            ?, ?, 'Alamat belum diisi', ?, ?,
            'belum bayar', 0, NULL,
            ?, ?, ?, ?,
            ?, 1, NOW(), NOW()
        )";
        $stmt_in = $pdo->prepare($sql);
        $stmt_in->execute([
            $id_pelanggan,
            $user['name'],
            $ip_modem,
            $status_ping,
            $status_berlangganan,
            $pppoe_profile,
            $rate_limit,
            $service_name,
            $router_id
        ]);
        $inserted++;
    }
}

// ============================================
// 4. UPDATE STATISTIK ROUTER
// ============================================
$stmt_total = $pdo->prepare("SELECT COUNT(*) as total FROM pelanggan WHERE router_id = ? AND status_berlangganan = 'Aktif' AND (pppoe_profile != 'ISOLIR' OR pppoe_profile IS NULL)");
$stmt_total->execute([$router_id]);
$total_users = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

$stmt_online = $pdo->prepare("SELECT COUNT(*) as total FROM pelanggan WHERE router_id = ? AND status_berlangganan = 'Aktif' AND status_ping = 'ONLINE' AND (pppoe_profile != 'ISOLIR' OR pppoe_profile IS NULL)");
$stmt_online->execute([$router_id]);
$total_online = $stmt_online->fetch(PDO::FETCH_ASSOC)['total'];

$stmt_router = $pdo->prepare("UPDATE router_list SET total_users = ?, total_active = ?, last_sync = NOW(), last_sync_status = 'success' WHERE id = ?");
$stmt_router->execute([$total_users, $total_online, $router_id]);

// ============================================
// 5. KIRIM RESPONSE
// ============================================
echo json_encode([
    'status' => 'SUCCESS',
    'message' => "Sync berhasil! Total user: " . number_format($total_users) . " | Online: " . number_format($total_online),
    'total_users' => $total_users,
    'total_active' => $total_online,
    'updated' => $updated,
    'inserted' => $inserted,
    'router_name' => $router['nama']
]);
?>