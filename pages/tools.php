<?php
// pages/tools.php - Halaman Tools untuk Admin & Super Admin

// Cek akses (double protection)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$role = $_SESSION['role'] ?? 'cs_user';
if ($role != 'super_admin' && $role != 'admin') {
    header('Location: ?page=dashboard');
    exit;
}

// ============================================
// PROSES POST UNTUK PERBAIKAN (RESET & SYNC ULANG)
// ============================================
$message = '';
$message_type = '';
$sync_results = [];
$total_synced = 0;
$total_failed = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reset_and_sync') {
    try {
        // 1. TRUNCATE tabel pelanggan (hapus semua data)
        $pdo->exec("TRUNCATE TABLE pelanggan");
        $sync_results[] = "🗑️ Tabel pelanggan telah dikosongkan (TRUNCATE)";
        
        // 2. Ambil semua router aktif
        $stmt_router = $pdo->query("SELECT id, nama, ip_address, port, username, password, api_port FROM router_list WHERE is_active = 1 ORDER BY id");
        $routers = $stmt_router->fetchAll();
        
        if (empty($routers)) {
            $sync_results[] = "⚠️ Tidak ada router aktif yang ditemukan!";
        } else {
            $sync_results[] = "📡 Ditemukan " . count($routers) . " router aktif, memulai sinkronisasi ulang...";
            $sync_results[] = "---";
            
            foreach ($routers as $router) {
                $router_id = $router['id'];
                $router_name = $router['nama'];
                $router_ip = $router['ip_address'];
                $router_port = $router['api_port'] ?: 8728;
                $router_user = $router['username'];
                $router_pass = $router['password'];
                
                $sync_results[] = "🔄 Menyinkronkan router: $router_name ($router_ip)";
                
                // Koneksi ke MikroTik via API
                $socket = @fsockopen($router_ip, $router_port, $errno, $errstr, 5);
                
                if (!$socket) {
                    $sync_results[] = "   ❌ Gagal koneksi ke router $router_name: $errstr";
                    $total_failed++;
                    continue;
                }
                
                // Login ke MikroTik
                fwrite($socket, "/login\r\n");
                $response = '';
                while (!feof($socket)) {
                    $buffer = fread($socket, 4096);
                    $response .= $buffer;
                    if (strpos($buffer, '!done') !== false) break;
                }
                
                fwrite($socket, "/login\r\n");
                fwrite($socket, "=name=" . $router_user . "\r\n");
                fwrite($socket, "=password=" . $router_pass . "\r\n");
                $response = '';
                while (!feof($socket)) {
                    $buffer = fread($socket, 4096);
                    $response .= $buffer;
                    if (strpos($buffer, '!done') !== false) break;
                }
                
                if (strpos($response, '!done') === false) {
                    $sync_results[] = "   ❌ Login gagal untuk router $router_name (cek username/password)";
                    fclose($socket);
                    $total_failed++;
                    continue;
                }
                
                // Ambil data secret (PPPoE users)
                fwrite($socket, "/ppp/secret/print\r\n");
                $response = '';
                while (!feof($socket)) {
                    $buffer = fread($socket, 4096);
                    $response .= $buffer;
                    if (strpos($buffer, '!done') !== false) break;
                }
                
                // Parse response
                $lines = explode("\n", $response);
                $secrets = [];
                $current = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    if ($line == '!done') {
                        if (!empty($current)) {
                            $secrets[] = $current;
                            $current = [];
                        }
                        break;
                    } elseif ($line == '!re') {
                        if (!empty($current)) {
                            $secrets[] = $current;
                            $current = [];
                        }
                    } elseif (strpos($line, '=') === 0) {
                        $parts = explode('=', substr($line, 1), 2);
                        if (count($parts) == 2) {
                            $current[$parts[0]] = $parts[1];
                        }
                    }
                }
                
                // Ambil data active connections
                fwrite($socket, "/ppp/active/print\r\n");
                $response = '';
                while (!feof($socket)) {
                    $buffer = fread($socket, 4096);
                    $response .= $buffer;
                    if (strpos($buffer, '!done') !== false) break;
                }
                
                $lines = explode("\n", $response);
                $actives = [];
                $current = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    if ($line == '!done') {
                        if (!empty($current)) {
                            $actives[] = $current;
                            $current = [];
                        }
                        break;
                    } elseif ($line == '!re') {
                        if (!empty($current)) {
                            $actives[] = $current;
                            $current = [];
                        }
                    } elseif (strpos($line, '=') === 0) {
                        $parts = explode('=', substr($line, 1), 2);
                        if (count($parts) == 2) {
                            $current[$parts[0]] = $parts[1];
                        }
                    }
                }
                
                fclose($socket);
                
                // Buat array active users untuk pengecekan cepat
                $active_users = [];
                foreach ($actives as $act) {
                    if (isset($act['name'])) {
                        $active_users[$act['name']] = true;
                    }
                }
                
                $inserted = 0;
                
                foreach ($secrets as $secret) {
                    $username = $secret['name'] ?? '';
                    $password = $secret['password'] ?? '';
                    $profile = $secret['profile'] ?? '';
                    $service = $secret['service'] ?? 'pppoe';
                    $disabled = isset($secret['disabled']) && $secret['disabled'] == 'true';
                    $comment = $secret['comment'] ?? '';
                    
                    if (empty($username)) continue;
                    
                    // Tentukan status berlangganan
                    $status_berlangganan = $disabled ? 'Nonaktif' : 'Aktif';
                    
                    // Tentukan status online
                    $is_online = isset($active_users[$username]);
                    $status_ping = $is_online ? 'ONLINE' : 'OFFLINE';
                    
                    // Parse comment untuk mendapatkan nama, alamat, telepon
                    $nama = $username;
                    $alamat = '';
                    $no_telp = '';
                    $ip_modem = '';
                    
                    if (!empty($comment)) {
                        $parts = explode('|', $comment);
                        if (count($parts) >= 1) $nama = trim($parts[0]) ?: $username;
                        if (count($parts) >= 2) $alamat = trim($parts[1]);
                        if (count($parts) >= 3) $no_telp = trim($parts[2]);
                        if (count($parts) >= 4) $ip_modem = trim($parts[3]);
                    }
                    
                    // Insert data pelanggan
                    $stmt = $pdo->prepare("
                        INSERT INTO pelanggan 
                        (id_pelanggan, nama, alamat, no_telp, ip_modem, pppoe_profile, 
                         status_ping, status_berlangganan, service_name, router_id, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $username, $nama, $alamat, $no_telp, $ip_modem,
                        $profile, $status_ping, $status_berlangganan,
                        $service, $router_id
                    ]);
                    $inserted++;
                }
                
                // Update statistik router
                $stmt_count = $pdo->prepare("
                    SELECT COUNT(*) as total FROM pelanggan 
                    WHERE router_id = ? AND status_berlangganan = 'Aktif' AND (pppoe_profile != 'ISOLIR' OR pppoe_profile IS NULL)
                ");
                $stmt_count->execute([$router_id]);
                $total_users = $stmt_count->fetch()['total'];
                
                $stmt_active = $pdo->prepare("
                    SELECT COUNT(*) as total FROM pelanggan 
                    WHERE router_id = ? AND status_berlangganan = 'Aktif' AND status_ping = 'ONLINE' AND (pppoe_profile != 'ISOLIR' OR pppoe_profile IS NULL)
                ");
                $stmt_active->execute([$router_id]);
                $total_active = $stmt_active->fetch()['total'];
                
                $stmt_update = $pdo->prepare("UPDATE router_list SET total_users = ?, total_active = ?, last_sync = NOW(), last_sync_status = 'success' WHERE id = ?");
                $stmt_update->execute([$total_users, $total_active, $router_id]);
                
                $sync_results[] = "   ✅ $router_name: Insert $inserted pelanggan, Total: $total_users, Online: $total_active";
                $total_synced++;
            }
        }
        
        $sync_results[] = "---";
        $sync_results[] = "✅ Sinkronisasi ulang selesai! $total_synced router berhasil, $total_failed gagal.";
        
        $message = "✅ Reset dan sinkronisasi ulang selesai! $total_synced router berhasil disinkronkan.";
        $message_type = "success";
        
        // Refresh statistik setelah sync
        $total_aktif = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE status_berlangganan = 'Aktif'")->fetchColumn();
        $total_isolir = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE pppoe_profile = 'ISOLIR' AND status_berlangganan = 'Aktif'")->fetchColumn();
        $total_offline = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE status_ping = 'OFFLINE' AND status_berlangganan = 'Aktif' AND (pppoe_profile != 'ISOLIR' OR pppoe_profile IS NULL)")->fetchColumn();
        $total_online_real = max(0, $total_aktif - $total_isolir - $total_offline);
        $total_logs = $pdo->query("SELECT COUNT(*) FROM log_aktivitas")->fetchColumn();
        
        // Log activity
        if (function_exists('logActivity')) {
            logActivity($_SESSION['username'], "Reset dan sinkronisasi ulang semua router - $total_synced router sukses", 'Tools');
        }
        
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "error";
        $sync_results[] = "❌ Error: " . $e->getMessage();
    }
}

// HAPUS LOG LAMA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'clear_logs') {
    try {
        $days = intval($_POST['days'] ?? 30);
        $stmt = $pdo->prepare("DELETE FROM log_aktivitas WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        $deleted = $stmt->rowCount();
        
        $message = "✅ Berhasil menghapus $deleted log yang lebih dari $days hari";
        $message_type = "success";
        
        // Refresh total log setelah hapus
        $total_logs = $pdo->query("SELECT COUNT(*) FROM log_aktivitas")->fetchColumn();
        
        if (function_exists('logActivity')) {
            logActivity($_SESSION['username'], "Menghapus log lama ($days hari, $deleted record)", 'Tools');
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// ============================================
// HITUNG STATISTIK
// ============================================
$total_aktif = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE status_berlangganan = 'Aktif'")->fetchColumn();
$total_isolir = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE pppoe_profile = 'ISOLIR' AND status_berlangganan = 'Aktif'")->fetchColumn();
$total_offline = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE status_ping = 'OFFLINE' AND status_berlangganan = 'Aktif' AND (pppoe_profile != 'ISOLIR' OR pppoe_profile IS NULL)")->fetchColumn();
$total_online_real = max(0, $total_aktif - $total_isolir - $total_offline);
$total_router = $pdo->query("SELECT COUNT(*) FROM router_list WHERE is_active = 1")->fetchColumn();
$total_logs = $pdo->query("SELECT COUNT(*) FROM log_aktivitas")->fetchColumn();
$total_system_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

// Database size
$db_size = 0;
$stmt = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.tables WHERE table_schema = DATABASE()");
$db_size = $stmt->fetchColumn() ?? 0;

$php_version = phpversion();

// Ambil daftar router untuk ditampilkan
$routers_list = $pdo->query("SELECT id, nama, ip_address, total_users, total_active FROM router_list WHERE is_active = 1 ORDER BY id")->fetchAll();
?>
<style>
/* ============================================ */
/* TOOLS CSS */
/* ============================================ */
.stats-grid {
    display: flex;
    flex-wrap: nowrap;
    gap: 20px;
    margin-bottom: 25px;
    overflow-x: auto;
    padding-bottom: 5px;
}

.stat-card {
    flex: 1;
    min-width: 180px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    padding: 18px 15px;
    border-radius: 16px;
    color: white;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.15);
}

.stat-card h4 {
    margin: 0 0 8px 0;
    font-size: 13px;
    font-weight: 500;
    opacity: 0.9;
}

.stat-card .value {
    font-size: 28px;
    font-weight: 700;
}

.warning-badge {
    background: #f39c12;
    color: white;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 10px;
    margin-left: 8px;
}

@media (max-width: 1100px) {
    .stats-grid { gap: 12px; }
    .stat-card { min-width: 150px; padding: 14px 12px; }
    .stat-card .value { font-size: 24px; }
}

@media (max-width: 900px) {
    .stat-card { min-width: 130px; }
    .stat-card .value { font-size: 20px; }
    .stat-card h4 { font-size: 11px; }
}

.tools-container {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin-top: 20px;
}

.tools-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e9ecef;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.tools-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.tools-card h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tools-card .icon-large {
    font-size: 40px;
    margin-bottom: 15px;
}

.tools-card p {
    color: #6c757d;
    font-size: 13px;
    margin-bottom: 20px;
    line-height: 1.5;
}

.tools-card .btn-tool {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    width: 100%;
    text-align: center;
}

.tools-card .btn-tool:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52,152,219,0.3);
}

.tools-card .btn-tool-danger {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
}

.tools-card .btn-tool-danger:hover {
    box-shadow: 0 4px 12px rgba(231,76,60,0.3);
}

.tools-card .btn-tool-warning {
    background: linear-gradient(135deg, #f39c12, #e67e22);
}

.tools-card .btn-tool-warning:hover {
    box-shadow: 0 4px 12px rgba(243,156,18,0.3);
}

.form-group-inline {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.form-group-inline label {
    font-size: 13px;
    font-weight: 500;
    color: #2c3e50;
}

.form-group-inline input {
    width: 80px;
    padding: 8px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    text-align: center;
}

.stats-mini {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-around;
    text-align: center;
}

.stats-mini .stat {
    flex: 1;
}

.stats-mini .stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
}

.stats-mini .stat-label {
    font-size: 11px;
    color: #7f8c8d;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0 0 15px 0;
}

.info-list li {
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
}

.info-list li:last-child {
    border-bottom: none;
}

.info-list .label {
    font-weight: 600;
    color: #2c3e50;
}

.info-list .value {
    color: #6c757d;
    font-family: monospace;
}

.router-list {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 12px;
    margin-top: 15px;
}

.router-list table {
    width: 100%;
    font-size: 12px;
}

.router-list th, .router-list td {
    padding: 6px 4px;
    text-align: left;
}

.router-list th {
    font-weight: 600;
    color: #2c3e50;
}

.router-check-result {
    margin-top: 15px;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
}

.card-section {
    margin-top: auto;
}

.alert-message {
    padding: 12px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border-left: 4px solid #17a2b8;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-left: 4px solid #f39c12;
}

.toast-notification {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    background: #34495e;
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    font-size: 13px;
    z-index: 100000;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    animation: slideUp 0.3s ease;
}

.toast-notification.error { background: #e74c3c; }
.toast-notification.success { background: #27ae60; }
.toast-notification.warning { background: #f39c12; }
.toast-notification.info { background: #3498db; }

.toast-notification .toast-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    cursor: pointer;
    font-size: 16px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

@keyframes slideUp {
    from { bottom: 0; opacity: 0; }
    to { bottom: 30px; opacity: 1; }
}

@media (max-width: 768px) {
    .stats-grid { flex-direction: column; }
    .tools-container { grid-template-columns: 1fr; }
    .stats-mini { flex-direction: column; gap: 10px; }
}

/* SweetAlert ukuran kecil */
.swal2-popup { font-size: 0.85rem !important; width: 28rem !important; padding: 0.8rem !important; }
.swal2-title { font-size: 1.2rem !important; padding: 0.5rem 0 0 !important; }
.swal2-html-container { font-size: 0.8rem !important; }
.swal2-confirm, .swal2-cancel { font-size: 0.8rem !important; padding: 0.5rem 1.2rem !important; }
.swal2-icon { width: 3rem !important; height: 3rem !important; margin: 0.5rem auto 0 !important; }
.swal2-icon .swal2-icon-content { font-size: 1.5rem !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- STATS GRID -->
<div class="stats-grid">
    <div class="stat-card">
        <h4>📊 Total Pelanggan Aktif</h4>
        <div class="value"><?php echo number_format($total_aktif); ?></div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #27ae60, #2ecc71);">
        <h4>🌐 Online (Real)</h4>
        <div class="value"><?php echo number_format($total_online_real); ?></div>
        <div style="font-size: 10px; opacity: 0.8;">Total - ISOLIR - Offline</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
        <h4>🔌 Offline</h4>
        <div class="value"><?php echo number_format($total_offline); ?></div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #8e44ad, #9b59b6);">
        <h4>🔒 PPPoE ISOLIR</h4>
        <div class="value"><?php echo number_format($total_isolir); ?></div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
        <h4>🖧 Total Router</h4>
        <div class="value"><?php echo number_format($total_router); ?></div>
    </div>
</div>

<!-- Tampilkan pesan hasil jika ada -->
<?php if ($message): ?>
<div class="alert-message alert-<?php echo $message_type; ?>" style="margin-bottom: 20px;">
    <?php echo $message; ?>
    <?php if (!empty($sync_results) && $message_type == 'success'): ?>
    <button type="button" onclick="showDetailResult()" style="background: none; border: none; color: inherit; text-decoration: underline; cursor: pointer; margin-left: 10px;">📋 Lihat Detail</button>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="tools-container">
    
    <!-- TOOL 1: Reset & Sync Ulang Router -->
    <div class="tools-card">
        <div class="icon-large">🔄</div>
        <h3>Reset & Sync Ulang Router</h3>
        <p><strong>⚠️ PERINGATAN:</strong> Proses ini akan MENGHAPUS SEMUA data pelanggan yang ada, kemudian mengambil ulang data dari semua router MikroTik yang aktif.</p>
        <div class="card-section">
            <form method="POST" onsubmit="return confirmResetAndSync()">
                <input type="hidden" name="action" value="reset_and_sync">
                <button type="submit" class="btn-tool btn-tool-danger" id="btnResetSync">⚠️ Reset & Sync Ulang Semua Router</button>
            </form>
            <div class="router-list">
                <strong>📡 Daftar Router Aktif:</strong>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Nama Router</th><th>IP Address</th><th>Users</th><th>Active</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($routers_list as $router): ?>
                        <tr>
                            <td><?php echo $router['id']; ?></td>
                            <td><?php echo htmlspecialchars($router['nama']); ?></td>
                            <td><?php echo htmlspecialchars($router['ip_address']); ?></td>
                            <td><?php echo number_format($router['total_users']); ?></td>
                            <td><?php echo number_format($router['total_active']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($routers_list)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #e74c3c;">⚠️ Tidak ada router aktif</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- TOOL 2: Hapus Log Lama -->
    <div class="tools-card">
        <div class="icon-large">🗑️</div>
        <h3>Hapus Log Lama</h3>
        <p>Menghapus data log aktivitas yang lebih dari periode tertentu. Berguna untuk membersihkan database dan mempercepat performa.</p>
        <div class="card-section">
            <div class="stats-mini" style="margin-bottom: 15px;">
                <div class="stat">
                    <div class="stat-value"><?php echo number_format($total_logs); ?></div>
                    <div class="stat-label">Total Log Saat Ini</div>
                </div>
            </div>
            <form method="POST" onsubmit="return confirmClearLogs()">
                <input type="hidden" name="action" value="clear_logs">
                <div class="form-group-inline">
                    <label>Hapus log lebih dari:</label>
                    <input type="number" name="days" value="30" min="1" max="365">
                    <span>hari</span>
                </div>
                <button type="submit" class="btn-tool btn-tool-warning">🗑️ Hapus Log Lama</button>
            </form>
        </div>
    </div>
    
    <!-- TOOL 3: Info Sistem -->
    <div class="tools-card">
        <div class="icon-large">ℹ️</div>
        <h3>Info Sistem</h3>
        <p>Informasi tentang server dan database untuk keperluan monitoring.</p>
        <ul class="info-list">
            <li><span class="label">📊 Total Pelanggan:</span><span class="value"><?php echo number_format($total_aktif); ?></span></li>
            <li><span class="label">📝 Total Log:</span><span class="value"><?php echo number_format($total_logs); ?></span></li>
            <li><span class="label">👥 Total User Sistem:</span><span class="value"><?php echo $total_system_users; ?></span></li>
            <li><span class="label">🐘 PHP Version:</span><span class="value"><?php echo $php_version; ?></span></li>
            <li><span class="label">💾 Database Size:</span><span class="value"><?php echo number_format($db_size, 2); ?> MB</span></li>
        </ul>
    </div>
    
    <!-- TOOL 4: Cek Koneksi Router -->
    <div class="tools-card">
        <div class="icon-large">🌐</div>
        <h3>Cek Koneksi Router</h3>
        <p>Menguji koneksi ke semua router yang terdaftar. Berguna untuk mendiagnosis masalah sinkronisasi.</p>
        <div class="card-section">
            <button type="button" class="btn-tool" onclick="checkRouters()" id="btnCheckRouters">🔍 Cek Semua Router</button>
            <div id="routerCheckResult" class="router-check-result"></div>
        </div>
    </div>
</div>

<!-- Modal Detail Hasil Sync -->
<div id="detailModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; backdrop-filter: blur(3px);">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 16px; width: 650px; max-width: 94%; padding: 0; box-shadow: 0 20px 40px -12px rgba(0,0,0,0.25);">
        <div style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 16px 22px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 17px;">📋 Detail Hasil Reset & Sync</h3>
            <button onclick="closeDetailModal()" style="background: rgba(255,255,255,0.2); border: none; font-size: 24px; cursor: pointer; color: white; width: 32px; height: 32px; border-radius: 50%;">&times;</button>
        </div>
        <div style="padding: 20px 24px; max-height: 60vh; overflow-y: auto; font-family: monospace; font-size: 12px;">
            <?php if (!empty($sync_results)): ?>
                <?php foreach ($sync_results as $result): ?>
                    <div style="padding: 6px 0; border-bottom: 1px solid #e9ecef;">
                        <?php echo htmlspecialchars($result); ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #666;">Belum ada data sync. Jalankan reset & sync terlebih dahulu.</div>
            <?php endif; ?>
        </div>
        <div style="padding: 16px 24px 20px; display: flex; justify-content: flex-end; border-top: 1px solid #eef2f5;">
            <button onclick="closeDetailModal()" style="padding: 9px 22px; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border: none; border-radius: 8px; cursor: pointer;">Tutup</button>
        </div>
    </div>
</div>

<script>
// ============================================
// KONFIRMASI RESET & SYNC ULANG (SWEETALERT)
// ============================================
function confirmResetAndSync() {
    let routerCount = <?php echo count($routers_list); ?>;
    
    Swal.fire({
        title: '⚠️ PERINGATAN!',
        html: `<div style="text-align:left">
                   <p style="color:#e74c3c; font-weight:bold; margin-bottom:10px;">Proses ini akan:</p>
                   <ul style="margin-bottom:15px;">
                       <li>🗑️ <strong>MENGHAPUS SEMUA</strong> data pelanggan yang ada</li>
                       <li>🔄 Mengambil ulang data dari <strong>${routerCount} router</strong> MikroTik</li>
                       <li>⏱️ Proses bisa memakan waktu beberapa menit</li>
                   </ul>
                   <p style="color:#e74c3c; font-weight:bold;">⚠️ Data yang dihapus TIDAK DAPAT DIKEMBALIKAN!</p>
                   <p style="margin-top:15px;">Apakah Anda yakin ingin melanjutkan?</p>
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: '⚠️ Ya, Reset & Sync Ulang!',
        cancelButtonText: '❌ Batal',
        reverseButtons: true,
        background: '#fff',
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                html: 'Menghapus data dan menyinkronkan ulang semua router...<br><span style="font-size:12px;">Mohon tunggu, proses ini bisa memakan waktu beberapa menit</span>',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            return true;
        }
        return false;
    });
    return true;
}

// ============================================
// KONFIRMASI HAPUS LOG (SWEETALERT)
// ============================================
function confirmClearLogs() {
    let days = document.querySelector('input[name="days"]').value;
    
    Swal.fire({
        title: 'Hapus Data Log?',
        html: `Hapus data log <strong>lebih dari ${days} hari</strong>?<br><span style="color:#e74c3c;">⚠️ Data yang dihapus tidak dapat dikembalikan</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        background: '#fff',
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Menghapus data log',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            return true;
        }
        return false;
    });
    return true;
}

// Show detail modal hasil sync
function showDetailResult() {
    document.getElementById('detailModal').style.display = 'block';
}

function closeDetailModal() {
    document.getElementById('detailModal').style.display = 'none';
}

// ============================================
// CEK SEMUA ROUTER
// ============================================
function checkRouters() {
    let btn = document.getElementById('btnCheckRouters');
    let resultDiv = document.getElementById('routerCheckResult');
    btn.disabled = true;
    btn.innerHTML = '⏳ Mengecek...';
    resultDiv.innerHTML = '<div class="alert-message alert-info">📡 Mengambil daftar router...</div>';
    
    fetch('get_active_routers.php')
        .then(res => res.json())
        .then(routers => {
            if (!routers || routers.length === 0) {
                resultDiv.innerHTML = '<div class="alert-message alert-info">❌ Tidak ada router aktif</div>';
                btn.disabled = false;
                btn.innerHTML = '🔍 Cek Semua Router';
                return;
            }
            
            let html = '<div style="margin-top:10px"><strong>Hasil Cek ' + routers.length + ' Router:</strong></div>';
            let completed = 0;
            
            routers.forEach(router => {
                let url = `http://${router.ip_address}:${router.port}/rest/system/identity`;
                fetch(url, { headers: { 'Authorization': 'Basic ' + btoa('network:banjaran123.') } })
                    .then(res => {
                        if (res.ok) html += `<div style="color:green">✅ ${router.nama} (${router.ip_address}) - ONLINE</div>`;
                        else if (res.status === 401) html += `<div style="color:orange">⚠️ ${router.nama} - Unauthorized</div>`;
                        else html += `<div style="color:red">❌ ${router.nama} - HTTP ${res.status}</div>`;
                        completed++;
                        if (completed === routers.length) { resultDiv.innerHTML = html; btn.disabled = false; btn.innerHTML = '🔍 Cek Semua Router'; }
                    })
                    .catch(() => {
                        html += `<div style="color:red">❌ ${router.nama} (${router.ip_address}) - Connection failed</div>`;
                        completed++;
                        if (completed === routers.length) { resultDiv.innerHTML = html; btn.disabled = false; btn.innerHTML = '🔍 Cek Semua Router'; }
                    });
            });
        })
        .catch(err => {
            resultDiv.innerHTML = `<div class="alert-message alert-info">❌ Gagal: ${err.message}</div>`;
            btn.disabled = false;
            btn.innerHTML = '🔍 Cek Semua Router';
        });
}

// Tutup modal jika klik di luar
window.onclick = function(event) {
    let modal = document.getElementById('detailModal');
    if (event.target == modal) {
        closeDetailModal();
    }
}
</script>