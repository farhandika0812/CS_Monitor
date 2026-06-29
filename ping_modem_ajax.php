<?php
// ping_modem_ajax.php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';

$ip_modem = isset($_GET['ip']) ? trim($_GET['ip']) : '';
$router_id = isset($_GET['router_id']) ? intval($_GET['router_id']) : 0;

if (empty($ip_modem) || $ip_modem == '-') {
    echo json_encode(['status' => 'OFFLINE', 'time' => '-', 'message' => 'IP tidak valid']);
    exit;
}

// Ambil data router dari database
$stmt = $pdo->prepare("SELECT * FROM router_list WHERE id = ? AND is_active = 1");
$stmt->execute([$router_id]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Router tidak ditemukan']);
    exit;
}

// Eksekusi Ping via REST API Mikrotik (ROS v7)
$url = "http://{$router['ip_address']}:{$router['port']}/rest/ping";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout cepat agar tidak lag
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $router['username'] . ":" . $router['password']);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);

// Payload JSON: ping ke IP Modem, hitung 1 kali saja
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'address' => $ip_modem, 
    'count' => 1
]));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    
    // Mengecek apakah packet ping dibalas (received > 0)
    if (isset($data[0]) && isset($data[0]['received']) && $data[0]['received'] > 0) {
        $time_ms = isset($data[0]['time']) ? $data[0]['time'] : '<1ms';
        echo json_encode([
            'status' => 'ONLINE',
            'time' => $time_ms
        ]);
    } else {
        echo json_encode([
            'status' => 'OFFLINE',
            'time' => 'RTO (Timeout)'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'ERROR',
        'time' => '-',
        'message' => 'Gagal konek ke API'
    ]);
}
?>