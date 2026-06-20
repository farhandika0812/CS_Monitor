<?php
// pages/dashboard.php

// ============================================
// KONEKSI DATABASE
// ============================================
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
// ============================================

$role = $_SESSION['role'] ?? 'cs_user';

// Konfigurasi Pagination
$limit = 10;
$page = isset($_POST['page']) ? (int)$_POST['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Search
$search = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $search = trim($_POST['search']);
} elseif (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Sorting
$sort_column = isset($_POST['sort']) ? $_POST['sort'] : (isset($_GET['sort']) ? $_GET['sort'] : 'nama');
$sort_order = isset($_POST['order']) ? $_POST['order'] : (isset($_GET['order']) ? $_GET['order'] : 'asc');
$sort_order_sql = ($sort_order == 'desc') ? 'DESC' : 'ASC';

$allowed_columns = ['id_pelanggan', 'nama', 'alamat', 'ip_modem', 'pppoe_profile', 'status_ping'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'nama';
}

$where_clause = "WHERE status_berlangganan = 'Aktif'";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (id_pelanggan LIKE :search OR nama LIKE :search OR ip_modem LIKE :search OR caller_id LIKE :search OR alamat LIKE :search OR pppoe_profile LIKE :search)";
    $params[':search'] = "%$search%";
}

// Hitung total data
$total_sql = "SELECT COUNT(*) as total FROM pelanggan $where_clause";
$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetch()['total'];
$total_pages = ($total_rows > 0) ? ceil($total_rows / $limit) : 1;

// Ambil data pelanggan
$sql = "SELECT id_pelanggan, nama, alamat, ip_modem, caller_id, status_ping, pppoe_profile, live_latency,
               status_pembayaran, no_telp, service_name, status_berlangganan, router_id, id_billing 
        FROM pelanggan 
        $where_clause
        ORDER BY 
            CASE WHEN status_ping = 'ONLINE' THEN 0 ELSE 1 END,
            $sort_column $sort_order_sql
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$pelanggan_list = $stmt->fetchAll();

// ============================================
// HITUNG DATA UNTUK STATISTIK (DIPERBAIKI)
// ============================================

// Total pelanggan dengan status AKTIF
$total_aktif = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE status_berlangganan = 'Aktif'")->fetch()['total'];

// Total ISOLIR (user dengan profile ISOLIR)
$total_isolir = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE pppoe_profile = 'ISOLIR' AND status_berlangganan = 'Aktif'")->fetch()['total'];

// Total OFFLINE (status_ping = OFFLINE) - TIDAK termasuk ISOLIR
$total_offline = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE status_ping = 'OFFLINE' AND status_berlangganan = 'Aktif' AND (pppoe_profile != 'ISOLIR' OR pppoe_profile IS NULL)")->fetch()['total'];

// Online real = total aktif - isolir - offline (tidak termasuk ISOLIR)
$total_online_real = $total_aktif - $total_isolir - $total_offline;
if ($total_online_real < 0) $total_online_real = 0;

// Total router aktif
$total_router = $pdo->query("SELECT COUNT(*) as total FROM router_list WHERE is_active = 1")->fetch()['total'];

// ============================================
// END OF STATISTIK
// ============================================
?>

<style>
/* ============================================ */
/* DASHBOARD CSS - PANG LIMANET */
/* ============================================ */

/* Stats Grid - 1 baris */
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

/* Responsive stats grid */
@media (max-width: 1100px) {
    .stats-grid {
        gap: 12px;
    }
    .stat-card {
        min-width: 150px;
        padding: 14px 12px;
    }
    .stat-card .value {
        font-size: 24px;
    }
}

@media (max-width: 900px) {
    .stat-card {
        min-width: 130px;
    }
    .stat-card .value {
        font-size: 20px;
    }
    .stat-card h4 {
        font-size: 11px;
    }
}

/* Modal Popup Style */
.modal {
    display: none;
    position: fixed;
    z-index: 99999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(3px);
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 0;
    border-radius: 16px;
    width: 560px;
    max-width: 94%;
    box-shadow: 0 20px 40px -12px rgba(0,0,0,0.25);
}

/* Modal Sync - lebih lebar */
#syncModal .modal-content {
    width: 650px;
    max-width: 94%;
}

.modal-header {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 16px 22px;
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 17px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-modal {
    background: rgba(255,255,255,0.2);
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.close-modal:hover {
    background: rgba(255,255,255,0.3);
    transform: scale(1.05);
}

.modal-body {
    padding: 20px 24px;
    max-height: 70vh;
    overflow-y: auto;
}

/* Sync Modal Styles */
.sync-progress-container {
    margin-bottom: 20px;
}

.sync-progress-bar {
    width: 100%;
    height: 10px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.sync-progress-fill {
    width: 0%;
    height: 100%;
    background: linear-gradient(90deg, #27ae60, #2ecc71);
    border-radius: 10px;
    transition: width 0.3s ease;
}

.sync-progress-text {
    font-size: 13px;
    color: #666;
    text-align: center;
}

.sync-log-container {
    background: #1a1a2e;
    border-radius: 12px;
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
}

.sync-log-entry {
    padding: 8px 12px;
    margin-bottom: 5px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeIn 0.3s ease;
}

.sync-log-entry.success {
    background: rgba(39, 174, 96, 0.2);
    border-left: 3px solid #27ae60;
    color: #27ae60;
}

.sync-log-entry.error {
    background: rgba(231, 76, 60, 0.2);
    border-left: 3px solid #e74c3c;
    color: #e74c3c;
}

.sync-log-entry.info {
    background: rgba(52, 152, 219, 0.2);
    border-left: 3px solid #3498db;
    color: #3498db;
}

.sync-log-entry.warning {
    background: rgba(243, 156, 18, 0.2);
    border-left: 3px solid #f39c12;
    color: #f39c12;
}

.sync-log-icon {
    font-size: 16px;
}

.sync-log-message {
    flex: 1;
}

.sync-log-time {
    font-size: 10px;
    opacity: 0.7;
}

.sync-summary {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 12px;
    display: flex;
    justify-content: space-around;
    text-align: center;
}

.sync-summary-item {
    flex: 1;
}

.sync-summary-label {
    font-size: 11px;
    color: #888;
    margin-bottom: 5px;
}

.sync-summary-value {
    font-size: 20px;
    font-weight: bold;
}

.sync-summary-value.success { color: #27ae60; }
.sync-summary-value.error { color: #e74c3c; }
.sync-summary-value.info { color: #3498db; }

.sync-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 10px;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateX(-10px); }
    to { opacity: 1; transform: translateX(0); }
}

/* Modal untuk detail pelanggan lebih lebar */
#detailModal .modal-content {
    width: 580px;
}

.info-row {
    display: flex;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f5;
}

.info-label {
    width: 140px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 13px;
}

.info-value {
    flex: 1;
    color: #555;
    font-family: monospace;
    font-size: 13px;
    word-break: break-word;
}

.info-value a {
    color: #25D366;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.info-value a:hover {
    text-decoration: underline;
}

.modal-footer {
    padding: 16px 24px 20px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    border-top: 1px solid #eef2f5;
    background: #fafbfc;
    border-radius: 0 0 16px 16px;
}

.btn-close {
    padding: 9px 22px;
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-close:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(52,152,219,0.3);
}

.btn-secondary {
    background: #95a5a6;
}

.btn-secondary:hover {
    background: #7f8c8d;
    box-shadow: 0 2px 8px rgba(127,140,141,0.3);
}

.btn-whatsapp {
    background: #25D366;
}

.btn-whatsapp:hover {
    background: #128C7E;
    box-shadow: 0 2px 8px rgba(37,211,102,0.3);
}

/* Tombol Sinkronisasi Router */
.btn-sync {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    font-size: 13px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-sync:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(243,156,18,0.3);
}

.btn-sync.disabled, .btn-sync:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.status-badge.aktif {
    background: #27ae60;
    color: white;
}

.status-badge.nonaktif {
    background: #e74c3c;
    color: white;
}

.status-badge.suspend {
    background: #f39c12;
    color: white;
}

.status-badge.lunas {
    background: #27ae60;
    color: white;
}

.status-badge.belum {
    background: #e74c3c;
    color: white;
}

/* Toast Notification */
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

.toast-notification.error {
    background: #e74c3c;
}

.toast-notification.success {
    background: #27ae60;
}

.toast-notification.warning {
    background: #f39c12;
}

.toast-notification.info {
    background: #3498db;
}

.toast-notification .toast-icon {
    font-size: 18px;
}

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

.toast-notification .toast-close:hover {
    background: rgba(255,255,255,0.3);
}

@keyframes slideUp {
    from {
        bottom: 0;
        opacity: 0;
    }
    to {
        bottom: 30px;
        opacity: 1;
    }
}

/* Grafik Ping Style */
.ping-stats {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.ping-stat-card {
    flex: 1;
    background: #f8f9fa;
    padding: 8px 10px;
    border-radius: 10px;
    text-align: center;
}

.ping-stat-label {
    font-size: 10px;
    color: #888;
    margin-bottom: 3px;
}

.ping-stat-value {
    font-size: 16px;
    font-weight: bold;
    font-family: monospace;
}

.ping-stat-value.min { color: #27ae60; }
.ping-stat-value.max { color: #e74c3c; }
.ping-stat-value.avg { color: #3498db; }
.ping-stat-value.current { color: #f39c12; }

.chart-container {
    background: #f0f4f8;
    border-radius: 10px;
    padding: 12px;
    margin: 10px 0;
}

canvas {
    width: 100%;
    height: 150px;
    background: white;
    border-radius: 8px;
    display: block;
}

.chart-legend {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 10px;
    font-size: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
}

.chart-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
}

.ping-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.ping-status.online {
    background: #27ae60;
    color: white;
}

.ping-status.offline {
    background: #e74c3c;
    color: white;
}

.ping-refresh {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    font-size: 11px;
    transition: all 0.2s;
}

.ping-refresh:hover {
    background: #2980b9;
}

.loading {
    text-align: center;
    padding: 30px;
    color: #999;
}

.loading-spinner {
    width: 30px;
    height: 30px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Tabel Style */
.data-table {
    font-size: 13px;
}

.data-table tr:hover {
    background: #f8f9fa;
}

.customer-table-container h3 {
    color: #2c3e50;
    font-weight: 600;
    margin: 0;
    font-size: 15px;
}

.data-table th a {
    transition: color 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    color: #333;
    font-size: 13px;
    font-weight: 600;
}

.data-table th a:hover {
    color: #3498db !important;
}

.data-table th {
    padding: 12px 12px;
    font-size: 13px;
}

.data-table td {
    padding: 10px 12px;
    vertical-align: middle;
    font-size: 13px;
}

.nama-link {
    color: #2c3e50;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
}

.nama-link:hover {
    color: #3498db;
    text-decoration: underline;
}

.pagination button {
    font-size: 13px;
    padding: 6px 12px;
    cursor: pointer;
}

.pagination button:hover {
    opacity: 0.8;
    transform: translateY(-1px);
}

/* Status Icon - Menggunakan icon sitemap / network topology */
.status-online, .status-offline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s;
}

.status-online {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    box-shadow: 0 2px 8px rgba(39,174,96,0.3);
    animation: pulse 2s infinite;
}

.status-offline {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    box-shadow: 0 2px 8px rgba(231,76,60,0.3);
}

.status-online:hover, .status-offline:hover {
    transform: scale(1.08);
    filter: brightness(1.05);
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(39,174,96,0.4); }
    70% { box-shadow: 0 0 0 8px rgba(39,174,96,0); }
    100% { box-shadow: 0 0 0 0 rgba(39,174,96,0); }
}

/* IP Link */
.ip-link {
    color: #3498db;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    background: #f0f7ff;
    border-radius: 20px;
    transition: all 0.2s;
    font-family: monospace;
    font-size: 12px;
}

.ip-link:hover {
    background: #3498db;
    color: white;
    transform: translateY(-1px);
}

/* Filter Bar Style - Profesional */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 25px;
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

/* Responsive */
@media (max-width: 768px) {
    .table-header {
        flex-direction: column;
        align-items: flex-start !important;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar input, .filter-bar select, .filter-bar button, .filter-bar a {
        width: 100%;
    }
    .data-table th, .data-table td {
        font-size: 11px;
        padding: 8px 6px !important;
    }
    .status-online, .status-offline {
        width: 28px;
        height: 28px;
        font-size: 14px;
    }
    .modal-content, #detailModal .modal-content, #syncModal .modal-content {
        width: 95%;
    }
    .ping-stats {
        gap: 6px;
    }
    .ping-stat-value {
        font-size: 13px;
    }
    canvas {
        height: 120px;
    }
    .info-label {
        width: 110px;
        font-size: 11px;
    }
    .info-value {
        font-size: 11px;
    }
}
</style>

<div class="stats-grid">
    <div class="stat-card">
        <h4>📊 Total Pelanggan Aktif</h4>
        <div class="value">
            <?php echo number_format($total_aktif); ?>
        </div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #27ae60, #2ecc71);">
        <h4>🌐 Online (Real)</h4>
        <div class="value">
            <?php echo number_format($total_online_real); ?>
        </div>
        <div style="font-size: 10px; opacity: 0.8; margin-top: 5px;">
            Total - ISOLIR - Offline
        </div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
        <h4>🔌 Offline</h4>
        <div class="value">
            <?php echo number_format($total_offline); ?>
        </div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #8e44ad, #9b59b6);">
        <h4>🔒 PPPoE ISOLIR</h4>
        <div class="value">
            <?php echo number_format($total_isolir); ?>
        </div>
    </div>
    <?php if ($role == 'super_admin' || $role == 'admin'): ?>
    <div class="stat-card" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
        <h4>🖧 Total Router</h4>
        <div class="value">
            <?php echo number_format($total_router); ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tabel Pelanggan -->
<div class="customer-table-container" style="margin-top: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <h3 style="margin: 0; color: #2c3e50; font-weight: 600; font-size: 16px;">
            📋 Daftar Pelanggan Aktif
        </h3>
    </div>
    
    <form method="POST" action="" id="searchForm" class="filter-bar">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="sort" value="<?php echo $sort_column; ?>">
        <input type="hidden" name="order" value="<?php echo $sort_order; ?>">
        
        <input type="text" name="search" placeholder="🔍 Cari ID, Nama, IP, MAC, Alamat, atau Profile..." 
               value="<?php echo htmlspecialchars($search); ?>">
        
        <button type="submit" class="btn-primary">🔍 Cari</button>
        
        <?php if (!empty($search)): ?>
            <a href="#" onclick="resetSearch(); return false;" class="btn-warning">✖ Reset</a>
        <?php endif; ?>

        <button type="button" class="btn-sync" id="syncRouterBtn" onclick="syncAllRoutersWithModal()">
            🔄 Sinkronisasi Router
        </button>
        
        <a href="index.php?page=import_csv" class="btn-sync" style="background: linear-gradient(135deg, #27ae60, #2ecc71); text-decoration: none; margin-left: 5px;">
    📂 Import CSV
</a>
    </form>
    
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 8px rgba(0,0,0,0.05);">
            <thead>
                <tr>
                    <th style="padding: 12px 12px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;"><a href="#" onclick="sortTable('id_pelanggan'); return false;">🆔 ID Pelanggan <?php echo $sort_column == 'id_pelanggan' ? ($sort_order == 'asc' ? '↑' : '↓') : '⇅'; ?></a></th>
                    <th style="padding: 12px 12px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;"><a href="#" onclick="sortTable('nama'); return false;">👤 Nama <?php echo $sort_column == 'nama' ? ($sort_order == 'asc' ? '↑' : '↓') : '⇅'; ?></a></th>
                    <th style="padding: 12px 12px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;"><a href="#" onclick="sortTable('alamat'); return false;">📍 Alamat <?php echo $sort_column == 'alamat' ? ($sort_order == 'asc' ? '↑' : '↓') : '⇅'; ?></a></th>
                    <th style="padding: 12px 12px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;"><a href="#" onclick="sortTable('ip_modem'); return false;">🌐 IP Modem <?php echo $sort_column == 'ip_modem' ? ($sort_order == 'asc' ? '↑' : '↓') : '⇅'; ?></a></th>
                    <th style="padding: 12px 12px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #e9ecef;"><a href="#" onclick="sortTable('pppoe_profile'); return false;">⚙️ PPPoE Profile <?php echo $sort_column == 'pppoe_profile' ? ($sort_order == 'asc' ? '↑' : '↓') : '⇅'; ?></a></th>
                    <th style="padding: 12px 12px; text-align: center; background: #f8f9fa; border-bottom: 2px solid #e9ecef;"><a href="#" onclick="sortTable('status_ping'); return false;">🔗 Status <?php echo $sort_column == 'status_ping' ? ($sort_order == 'asc' ? '↑' : '↓') : '⇅'; ?></a></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pelanggan_list) > 0): ?>
                    <?php foreach ($pelanggan_list as $pelanggan): ?>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 10px 12px; font-family: monospace; word-break: break-word;">
                                <?php echo htmlspecialchars(substr($pelanggan['id_pelanggan'], 0, 30)) . (strlen($pelanggan['id_pelanggan']) > 30 ? '...' : ''); ?>
                            </td>
                            <td style="padding: 10px 12px; font-weight: 500; word-break: break-word;">
                                <a href="#" onclick="showDetailModal(
                                    '<?php echo htmlspecialchars(addslashes($pelanggan['nama'])); ?>',
                                    '<?php echo htmlspecialchars(addslashes($pelanggan['alamat'] ?? '-')); ?>',
                                    '<?php echo htmlspecialchars($pelanggan['status_pembayaran'] ?? 'belum bayar'); ?>',
                                    '<?php echo htmlspecialchars($pelanggan['no_telp'] ?? '-'); ?>',
                                    '<?php echo htmlspecialchars(addslashes($pelanggan['service_name'] ?? '-')); ?>',
                                    '<?php echo htmlspecialchars($pelanggan['status_berlangganan'] ?? 'Aktif'); ?>',
                                    '<?php echo htmlspecialchars($pelanggan['router_id'] ?? '-'); ?>'
                                ); return false;" class="nama-link">
                                    <?php echo htmlspecialchars($pelanggan['nama']); ?>
                                </a>
                            </td>
                            <td style="padding: 10px 12px; color: #666; word-break: break-word;">
                                <?php echo htmlspecialchars($pelanggan['alamat'] ?? '-'); ?>
                            </td>
                            <td style="padding: 10px 12px;">
                                <?php if (!empty($pelanggan['ip_modem']) && $pelanggan['ip_modem'] != '-' && filter_var($pelanggan['ip_modem'], FILTER_VALIDATE_IP)): ?>
                                    <a href="#" onclick="showRemoteModal('<?php echo htmlspecialchars($pelanggan['ip_modem']); ?>', '<?php echo htmlspecialchars($pelanggan['nama']); ?>'); return false;" class="ip-link">
                                        🖥️ <?php echo htmlspecialchars($pelanggan['ip_modem']); ?>
                                    </a>
                                    <div style="font-size: 11px; color: #7f8c8d; margin-top: 4px; font-family: monospace; padding-left: 5px;">
                                        🔑 <?php echo htmlspecialchars($pelanggan['caller_id'] ?? '-'); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999;">❓ <?php echo htmlspecialchars($pelanggan['ip_modem']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 12px; font-family: monospace; word-break: break-word;">
                                <?php 
                                $profile = $pelanggan['pppoe_profile'] ?? '-';
                                echo htmlspecialchars(substr($profile, 0, 25)) . (strlen($profile) > 25 ? '...' : '');
                                ?>
                            </div>
                            <td style="padding: 10px 12px; text-align: center;">
                                <div onclick="showPingModal('<?php echo htmlspecialchars($pelanggan['ip_modem']); ?>', '<?php echo htmlspecialchars($pelanggan['nama']); ?>')" 
                                     class="<?php echo $pelanggan['status_ping'] == 'ONLINE' ? 'status-online' : 'status-offline'; ?>" 
                                     title="Klik untuk melihat grafik ping">
                                    <!-- Icon Sitemap / Network Topology (Font Awesome sitemap) -->
                                    <i class="fas fa-sitemap" style="color: white; font-size: 16px;"></i>
                                </div>
                            </div>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding: 40px; text-align: center; color: #999;">
                            <span style="font-size: 40px; margin-bottom: 8px; display: block;">📭</span>
                            Tidak ada data pelanggan ditemukan
                         </td>
                    </tr>
                <?php endif; ?>
            </tbody>
          </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="display: flex; justify-content: center; gap: 6px; margin-top: 18px; flex-wrap: wrap;">
        <?php if ($page > 1): ?>
            <button onclick="goToPage(<?php echo $page-1; ?>)" style="padding: 6px 12px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer;">◀ Prev</button>
        <?php endif; ?>
        
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1) {
            echo '<button onclick="goToPage(1)" style="padding: 6px 12px; background: #f8f9fa; color: #333; border: 1px solid #ddd; border-radius: 6px; cursor: pointer;">1</button>';
            if ($start_page > 2) echo '<span style="padding: 0 5px;">...</span>';
        }
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <button onclick="goToPage(<?php echo $i; ?>)" 
                    style="padding: 6px 12px; background: <?php echo $i == $page ? '#3498db' : '#f8f9fa'; ?>; 
                           color: <?php echo $i == $page ? 'white' : '#333'; ?>; border: <?php echo $i == $page ? 'none' : '1px solid #ddd'; ?>; 
                           border-radius: 6px; cursor: pointer;">
                <?php echo $i; ?>
            </button>
        <?php endfor; ?>
        
        <?php
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) echo '<span style="padding: 0 5px;">...</span>';
            echo '<button onclick="goToPage(' . $total_pages . ')" style="padding: 6px 12px; background: #f8f9fa; color: #333; border: 1px solid #ddd; border-radius: 6px; cursor: pointer;">' . $total_pages . '</button>';
        }
        ?>
        
        <?php if ($page < $total_pages): ?>
            <button onclick="goToPage(<?php echo $page+1; ?>)" style="padding: 6px 12px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer;">Next ▶</button>
        <?php endif; ?>
    </div>
    <div style="text-align: center; margin-top: 8px; color: #666; font-size: 12px;">
        Menampilkan <?php echo count($pelanggan_list); ?> dari <?php echo number_format($total_rows); ?> data
    </div>
    <?php endif; ?>
</div>

<!-- Modal Sync Profesional -->
<div id="syncModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <span>🔄</span> 
                Sinkronisasi Router
            </h3>
            <button class="close-modal" onclick="closeSyncModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Progress Bar -->
            <div class="sync-progress-container">
                <div class="sync-progress-bar">
                    <div class="sync-progress-fill" id="syncProgressFill"></div>
                </div>
                <div class="sync-progress-text" id="syncProgressText">Memulai sinkronisasi...</div>
            </div>
            
            <!-- Log Container -->
            <div class="sync-log-container" id="syncLogContainer">
                <div class="sync-log-entry info">
                    <span class="sync-log-icon">🔄</span>
                    <span class="sync-log-message">Memulai sinkronisasi semua router...</span>
                    <span class="sync-log-time"><?php echo date('H:i:s'); ?></span>
                </div>
            </div>
            
            <!-- Summary -->
            <div class="sync-summary" id="syncSummary" style="display: none;">
                <div class="sync-summary-item">
                    <div class="sync-summary-label">✅ Sukses</div>
                    <div class="sync-summary-value success" id="syncSuccessCount">0</div>
                </div>
                <div class="sync-summary-item">
                    <div class="sync-summary-label">❌ Gagal</div>
                    <div class="sync-summary-value error" id="syncFailedCount">0</div>
                </div>
                <div class="sync-summary-item">
                    <div class="sync-summary-label">📊 Total Router</div>
                    <div class="sync-summary-value info" id="syncTotalCount">0</div>
                </div>
                <div class="sync-summary-item">
                    <div class="sync-summary-label">👥 Updated</div>
                    <div class="sync-summary-value success" id="syncUpdatedCount">0</div>
                </div>
                <div class="sync-summary-item">
                    <div class="sync-summary-label">➕ Inserted</div>
                    <div class="sync-summary-value info" id="syncInsertedCount">0</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-close btn-secondary" id="syncCloseBtn" onclick="closeSyncModal()" disabled>Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Detail Pelanggan -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>👤 Detail Pelanggan</h3>
            <button class="close-modal" onclick="closeModal('detailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="info-row"><div class="info-label">👤 Nama</div><div class="info-value" id="detailNama">-</div></div>
            <div class="info-row"><div class="info-label">📍 Alamat</div><div class="info-value" id="detailAlamat">-</div></div>
            <div class="info-row"><div class="info-label">💰 Status Pembayaran</div><div class="info-value" id="detailPembayaran">-</div></div>
            <div class="info-row"><div class="info-label">📞 No. Telepon</div><div class="info-value" id="detailTelp">-</div></div>
            <div class="info-row"><div class="info-label">🔧 Service Name</div><div class="info-value" id="detailService">-</div></div>
            <div class="info-row"><div class="info-label">📋 Status Berlangganan</div><div class="info-value" id="detailStatusLangganan">-</div></div>
            <div class="info-row"><div class="info-label">🖧 Router ID</div><div class="info-value" id="detailRouterId">-</div></div>
        </div>
        <div class="modal-footer">
            <button class="btn-close btn-secondary" onclick="closeModal('detailModal')">Tutup</button>
            <button class="btn-close btn-whatsapp" id="whatsappBtn" onclick="sendWhatsApp()">💬 Hubungi WhatsApp</button>
        </div>
    </div>
</div>

<!-- Modal Remote Modem -->
<div id="remoteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🖥️ Remote Modem</h3>
            <button class="close-modal" onclick="closeModal('remoteModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="info-row"><div class="info-label">👤 Nama Pelanggan</div><div class="info-value" id="modalNama">-</div></div>
            <div class="info-row"><div class="info-label">🌐 IP Address</div><div class="info-value" id="modalIp">-</div></div>
            <div class="info-row"><div class="info-label">🔌 Port</div><div class="info-value">80 (HTTP)</div></div>
        </div>
        <div class="modal-footer">
            <button class="btn-close btn-secondary" onclick="closeModal('remoteModal')">Tutup</button>
            <button class="btn-close" onclick="remoteModem()">🌐 Buka Remote</button>
        </div>
    </div>
</div>

<!-- Modal Grafik Ping -->
<div id="pingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📡 Grafik Ping Modem</h3>
            <button class="close-modal" onclick="closePingModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="info-row"><div class="info-label">👤 Nama Pelanggan</div><div class="info-value" id="pingModalNama">-</div></div>
            <div class="info-row"><div class="info-label">🌐 IP Address</div><div class="info-value" id="pingModalIp">-</div></div>
            
            <div class="ping-stats">
                <div class="ping-stat-card"><div class="ping-stat-label">📊 Min</div><div class="ping-stat-value min" id="pingMin">-</div></div>
                <div class="ping-stat-card"><div class="ping-stat-label">📈 Max</div><div class="ping-stat-value max" id="pingMax">-</div></div>
                <div class="ping-stat-card"><div class="ping-stat-label">📉 Avg</div><div class="ping-stat-value avg" id="pingAvg">-</div></div>
                <div class="ping-stat-card"><div class="ping-stat-label">⚡ Current</div><div class="ping-stat-value current" id="pingCurrent">-</div></div>
            </div>
            
            <div class="chart-container">
                <canvas id="pingLineChart" width="500" height="150" style="width:100%; height:150px;"></canvas>
                <div class="chart-legend">
                    <div class="legend-item"><div class="legend-color" style="background: #3498db;"></div> Latency (ms)</div>
                    <div class="legend-item"><div class="legend-color" style="background: #e74c3c;"></div> Offline / Timeout</div>
                </div>
            </div>
            
            <div class="chart-controls">
                <div id="pingStatus" class="ping-status online">🌐 Online</div>
                <button class="ping-refresh" onclick="refreshPingData()">🔄 Refresh</button>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-close" onclick="closePingModal()">Tutup</button>
        </div>
    </div>
</div>

<form method="POST" id="sortForm" style="display: none;">
    <input type="hidden" name="sort" id="sort_input" value="">
    <input type="hidden" name="order" id="order_input" value="">
    <input type="hidden" name="page" id="page_input" value="1">
    <input type="hidden" name="search" id="search_input" value="<?php echo htmlspecialchars($search); ?>">
</form>

<!-- Font Awesome untuk icon sitemap -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<script>
let remoteData = { ip: '', nama: '' };
let pingData = { ip: '', nama: '', history: [] };
let pingInterval = null;
let pingChart = null;
let currentWhatsAppNumber = '';

// Sync Modal variables
let syncInProgress = false;

function showToast(message, type = 'info') {
    let existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    let toast = document.createElement('div');
    toast.className = 'toast-notification ' + type;
    let icon = type === 'error' ? '❌' : (type === 'success' ? '✅' : (type === 'warning' ? '⚠️' : 'ℹ️'));
    toast.innerHTML = `<span class="toast-icon">${icon}</span><span>${message}</span><button class="toast-close" onclick="this.parentElement.remove()">✕</button>`;
    document.body.appendChild(toast);
    setTimeout(() => { if(toast && toast.parentElement) toast.remove(); }, 4000);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

function addSyncLog(message, type = 'info') {
    const logContainer = document.getElementById('syncLogContainer');
    const time = new Date().toLocaleTimeString();
    const logEntry = document.createElement('div');
    logEntry.className = `sync-log-entry ${type}`;
    
    let icon = '';
    if (type === 'success') icon = '✅';
    else if (type === 'error') icon = '❌';
    else if (type === 'warning') icon = '⚠️';
    else icon = '🔄';
    
    logEntry.innerHTML = `
        <span class="sync-log-icon">${icon}</span>
        <span class="sync-log-message">${message}</span>
        <span class="sync-log-time">${time}</span>
    `;
    
    logContainer.appendChild(logEntry);
    logContainer.scrollTop = logContainer.scrollHeight;
}

function updateSyncProgress(current, total, message) {
    const percent = (current / total) * 100;
    const fillBar = document.getElementById('syncProgressFill');
    const text = document.getElementById('syncProgressText');
    
    fillBar.style.width = percent + '%';
    text.innerHTML = `${message} (${current}/${total})`;
}

function showSyncModal() {
    document.getElementById('syncModal').style.display = 'block';
    // Reset UI
    document.getElementById('syncProgressFill').style.width = '0%';
    document.getElementById('syncProgressText').innerHTML = 'Memulai sinkronisasi...';
    document.getElementById('syncLogContainer').innerHTML = '';
    document.getElementById('syncSummary').style.display = 'none';
    document.getElementById('syncCloseBtn').disabled = true;
    
    // Add initial log
    addSyncLog('Memulai sinkronisasi semua router...', 'info');
}

function closeSyncModal() {
    if (!syncInProgress) {
        document.getElementById('syncModal').style.display = 'none';
    } else {
        showToast('Sinkronisasi sedang berjalan, harap tunggu!', 'warning');
    }
}

function updateSyncSummary(successCount, failedCount, totalRouters, updatedCount, insertedCount) {
    document.getElementById('syncSuccessCount').innerText = successCount;
    document.getElementById('syncFailedCount').innerText = failedCount;
    document.getElementById('syncTotalCount').innerText = totalRouters;
    document.getElementById('syncUpdatedCount').innerText = updatedCount;
    document.getElementById('syncInsertedCount').innerText = insertedCount;
    document.getElementById('syncSummary').style.display = 'flex';
}

// ============================================
// FUNGSI SINKRONISASI ROUTER
// ============================================
async function syncAllRoutersWithModal() {
    if (syncInProgress) {
        showToast('Sinkronisasi sedang berjalan!', 'warning');
        return;
    }
    
    const syncBtn = document.getElementById('syncRouterBtn');
    const originalText = syncBtn.innerHTML;
    
    syncBtn.disabled = true;
    syncBtn.innerHTML = '⏳ Menyinkronkan...';
    syncBtn.classList.add('disabled');
    
    // Tampilkan modal
    showSyncModal();
    syncInProgress = true;
    
    try {
        addSyncLog('Mengambil daftar router aktif...', 'info');
        
        // Ambil daftar router aktif
        const response = await fetch('get_active_routers.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            cache: 'no-store'
        });
        
        const routers = await response.json();
        
        if (!routers || routers.length === 0) {
            addSyncLog('Tidak ada router aktif!', 'error');
            updateSyncProgress(0, 1, 'Gagal!');
            document.getElementById('syncCloseBtn').disabled = false;
            syncInProgress = false;
            showToast('Tidak ada router aktif', 'error');
            syncBtn.disabled = false;
            syncBtn.innerHTML = originalText;
            syncBtn.classList.remove('disabled');
            return;
        }
        
        const total = routers.length;
        let completed = 0;
        let successCount = 0;
        let failCount = 0;
        let totalUpdated = 0;
        let totalInserted = 0;
        
        addSyncLog(`Ditemukan ${total} router aktif, memulai sinkronisasi...`, 'info');
        
        // Sinkronisasi satu per satu
        for (let i = 0; i < routers.length; i++) {
            const router = routers[i];
            const current = i + 1;
            
            updateSyncProgress(current, total, `Memproses router ${current}/${total}`);
            addSyncLog(`Memproses router ${router.nama} (${router.ip_address})...`, 'info');
            
            try {
                const syncResponse = await fetch(`sync_single_router_ajax.php?router_id=${router.id}`, {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' },
                    cache: 'no-store'
                });
                
                const result = await syncResponse.json();
                
                if (result.status === 'SUCCESS') {
                    successCount++;
                    totalUpdated += result.updated || 0;
                    totalInserted += result.inserted || 0;
                    addSyncLog(`✅ ${router.nama} - Berhasil! (Total user: ${formatNumber(result.total_users || 0)}, update: ${result.updated || 0}, baru: ${result.inserted || 0})`, 'success');
                } else {
                    failCount++;
                    addSyncLog(`❌ ${router.nama} - Gagal: ${result.message || 'Unknown error'}`, 'error');
                }
            } catch (error) {
                failCount++;
                addSyncLog(`❌ ${router.nama} - Error: ${error.message}`, 'error');
            }
            
            completed++;
            updateSyncProgress(completed, total, `Selesai ${completed}/${total}`);
            
            // Delay kecil untuk menghindari overload
            await new Promise(resolve => setTimeout(resolve, 500));
        }
        
        updateSyncProgress(total, total, 'Selesai!');
        updateSyncSummary(successCount, failCount, total, totalUpdated, totalInserted);
        
        addSyncLog(`✅ Sinkronisasi selesai! ${successCount} dari ${total} router berhasil.`, 'success');
        addSyncLog(`📊 Total update: ${formatNumber(totalUpdated)} data, Insert: ${formatNumber(totalInserted)} data`, 'info');
        
        showToast('✅ Sinkronisasi selesai! Halaman akan di-refresh.', 'success');
        
        // Enable close button setelah selesai
        document.getElementById('syncCloseBtn').disabled = false;
        syncInProgress = false;
        
        // Refresh halaman setelah 2 detik
        setTimeout(() => {
            closeSyncModal();
            window.location.reload();
        }, 2000);
        
    } catch (error) {
        console.error('Error:', error);
        addSyncLog(`❌ Error: ${error.message}`, 'error');
        updateSyncProgress(0, 1, 'Error!');
        document.getElementById('syncCloseBtn').disabled = false;
        syncInProgress = false;
        showToast('❌ Gagal terhubung ke server: ' + error.message, 'error');
    } finally {
        syncBtn.disabled = false;
        syncBtn.innerHTML = originalText;
        syncBtn.classList.remove('disabled');
    }
}

function sortTable(column) {
    let currentSort = '<?php echo $sort_column; ?>';
    let currentOrder = '<?php echo $sort_order; ?>';
    let newOrder = (currentSort == column && currentOrder == 'asc') ? 'desc' : 'asc';
    document.getElementById('sort_input').value = column;
    document.getElementById('order_input').value = newOrder;
    document.getElementById('page_input').value = 1;
    document.getElementById('sortForm').submit();
}

function goToPage(page) {
    document.getElementById('sort_input').value = '<?php echo $sort_column; ?>';
    document.getElementById('order_input').value = '<?php echo $sort_order; ?>';
    document.getElementById('page_input').value = page;
    document.getElementById('sortForm').submit();
}

function resetSearch() {
    document.getElementById('sort_input').value = '<?php echo $sort_column; ?>';
    document.getElementById('order_input').value = '<?php echo $sort_order; ?>';
    document.getElementById('page_input').value = 1;
    document.getElementById('search_input').value = '';
    document.getElementById('sortForm').submit();
}

function showDetailModal(nama, alamat, pembayaran, telp, service, statusLangganan, routerId) {
    document.getElementById('detailNama').innerText = nama;
    document.getElementById('detailAlamat').innerText = alamat;
    let pembayaranHtml = (pembayaran.toLowerCase() == 'sudah bayar' || pembayaran.toLowerCase() == 'lunas') 
        ? '<span class="status-badge lunas">✅ Lunas</span>' 
        : '<span class="status-badge belum">⚠️ Belum Bayar</span>';
    document.getElementById('detailPembayaran').innerHTML = pembayaranHtml;
    
    if (telp && telp !== '-' && telp.trim() !== '') {
        let cleanTelp = telp.replace(/[^0-9]/g, '');
        if (cleanTelp.startsWith('0')) cleanTelp = '62' + cleanTelp.substring(1);
        else if (!cleanTelp.startsWith('62')) cleanTelp = '62' + cleanTelp;
        currentWhatsAppNumber = cleanTelp;
        document.getElementById('detailTelp').innerHTML = telp + ' <span style="font-size:10px;color:#25D366;">(WA)</span>';
    } else {
        currentWhatsAppNumber = '';
        document.getElementById('detailTelp').innerHTML = '<span style="color:#e74c3c;">❌ Tidak tersedia</span>';
    }
    document.getElementById('detailService').innerText = service;
    let statusHtml = statusLangganan.toLowerCase() == 'aktif' ? '<span class="status-badge aktif">🟢 Aktif</span>' : 
                     (statusLangganan.toLowerCase() == 'nonaktif' ? '<span class="status-badge nonaktif">🔴 Nonaktif</span>' : 
                     '<span class="status-badge suspend">🟡 Suspend</span>');
    document.getElementById('detailStatusLangganan').innerHTML = statusHtml;
    document.getElementById('detailRouterId').innerText = routerId;
    document.getElementById('detailModal').style.display = 'block';
}

function sendWhatsApp() {
    if (currentWhatsAppNumber && currentWhatsAppNumber.length > 8) {
        let nama = document.getElementById('detailNama').innerText;
        window.open('https://wa.me/' + currentWhatsAppNumber + '?text=' + encodeURIComponent('Halo ' + nama + ', saya dari PanglimaNet'), '_blank');
    } else {
        showToast('Nomor telepon tidak valid', 'warning');
    }
}

function showRemoteModal(ip, nama) {
    remoteData = { ip, nama };
    document.getElementById('modalNama').innerText = nama;
    document.getElementById('modalIp').innerText = ip;
    document.getElementById('remoteModal').style.display = 'block';
}

function remoteModem() {
    if (remoteData.ip) { window.open('http://' + remoteData.ip, '_blank'); closeModal('remoteModal'); }
}

function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

function closePingModal() {
    if (pingInterval) clearInterval(pingInterval);
    if (pingChart) pingChart.destroy();
    document.getElementById('pingModal').style.display = 'none';
}

function showPingModal(ip, nama) {
    if (!ip || ip === '-') { showToast('IP Modem tidak valid', 'warning'); return; }
    pingData = { ip, nama, history: [] };
    document.getElementById('pingModalNama').innerText = nama;
    document.getElementById('pingModalIp').innerText = ip;
    document.getElementById('pingModal').style.display = 'block';
    document.getElementById('pingMin').innerText = '-';
    document.getElementById('pingMax').innerText = '-';
    document.getElementById('pingAvg').innerText = '-';
    document.getElementById('pingCurrent').innerText = '-';
    document.getElementById('pingStatus').className = 'ping-status online';
    document.getElementById('pingStatus').innerHTML = '🌐 Online';
    if (pingChart) pingChart.destroy();
    fetchPingData();
    if (pingInterval) clearInterval(pingInterval);
    pingInterval = setInterval(fetchPingData, 5000);
}

async function fetchPingData() {
    if (!pingData.ip) return;
    let randomLatency = Math.floor(Math.random() * 100) + 1;
    let isOnline = randomLatency < 80;
    pingData.history.push({
        time: new Date().toLocaleTimeString().slice(0,8),
        latency: isOnline ? randomLatency : null,
        status: isOnline ? 'online' : 'offline'
    });
    if (pingData.history.length > 12) pingData.history.shift();
    updatePingChartUI();
}

function updatePingChartUI() {
    let history = pingData.history;
    if (history.length === 0) return;
    let valid = history.filter(p => p.latency !== null).map(p => p.latency);
    if (valid.length === 0) {
        document.getElementById('pingMin').innerHTML = '-';
        document.getElementById('pingMax').innerHTML = '-';
        document.getElementById('pingAvg').innerHTML = '-';
        document.getElementById('pingCurrent').innerHTML = '-';
        document.getElementById('pingStatus').className = 'ping-status offline';
        document.getElementById('pingStatus').innerHTML = '🔌 Offline';
    } else {
        let minLat = Math.min(...valid), maxLat = Math.max(...valid), avgLat = Math.round(valid.reduce((a,b)=>a+b,0)/valid.length);
        let curr = history[history.length-1].latency;
        document.getElementById('pingMin').innerHTML = minLat + ' ms';
        document.getElementById('pingMax').innerHTML = maxLat + ' ms';
        document.getElementById('pingAvg').innerHTML = avgLat + ' ms';
        document.getElementById('pingCurrent').innerHTML = (curr ? curr + ' ms' : '-');
        document.getElementById('pingStatus').className = 'ping-status online';
        document.getElementById('pingStatus').innerHTML = '🌐 Online';
    }
    renderLineChart(history);
}

function renderLineChart(history) {
    let canvas = document.getElementById('pingLineChart');
    if (!canvas) return;
    let ctx = canvas.getContext('2d');
    let container = canvas.parentElement;
    let width = container.clientWidth - 20;
    let height = 140;
    canvas.width = width;
    canvas.height = height;
    ctx.clearRect(0, 0, width, height);
    if (history.length === 0) {
        ctx.fillStyle = '#999';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Tidak ada data', width/2, height/2);
        return;
    }
    let padding = { left: 35, right: 10, top: 12, bottom: 20 };
    let chartWidth = width - padding.left - padding.right;
    let chartHeight = height - padding.top - padding.bottom;
    let stepX = chartWidth / (history.length - 1);
    let maxLat = Math.max(...history.filter(p => p.latency).map(p => p.latency), 80);
    
    ctx.beginPath();
    ctx.strokeStyle = '#e0e0e0';
    ctx.lineWidth = 0.5;
    for (let i = 0; i <= 3; i++) {
        let y = padding.top + (chartHeight / 3) * i;
        ctx.moveTo(padding.left, y);
        ctx.lineTo(width - padding.right, y);
        ctx.stroke();
        ctx.fillStyle = '#aaa';
        ctx.font = '9px monospace';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxLat - (maxLat / 3) * i) + 'ms', padding.left - 5, y + 3);
    }
    
    ctx.beginPath();
    ctx.strokeStyle = '#3498db';
    ctx.lineWidth = 2;
    let first = true;
    let points = [];
    for (let i = 0; i < history.length; i++) {
        let x = padding.left + stepX * i;
        let y = padding.top + chartHeight;
        if (history[i].latency !== null && history[i].status === 'online') {
            y = padding.top + chartHeight - (history[i].latency / maxLat) * chartHeight;
        }
        points.push({x, y, latency: history[i].latency, status: history[i].status, time: history[i].time});
        if (first) { ctx.moveTo(x, y); first = false; }
        else { ctx.lineTo(x, y); }
    }
    ctx.stroke();
    
    for (let i = 0; i < points.length; i++) {
        let p = points[i];
        ctx.beginPath();
        if (p.latency !== null && p.status === 'online') {
            ctx.fillStyle = '#3498db';
            ctx.arc(p.x, p.y, 4, 0, 2 * Math.PI);
            ctx.fill();
            ctx.fillStyle = 'white';
            ctx.arc(p.x, p.y, 2, 0, 2 * Math.PI);
            ctx.fill();
            ctx.fillStyle = '#2c3e50';
            ctx.font = '9px monospace';
            ctx.textAlign = 'center';
            ctx.fillText(p.latency + 'ms', p.x, p.y - 7);
        } else {
            ctx.fillStyle = '#e74c3c';
            ctx.arc(p.x, p.y, 4, 0, 2 * Math.PI);
            ctx.fill();
            ctx.fillStyle = '#e74c3c';
            ctx.font = '8px monospace';
            ctx.textAlign = 'center';
            ctx.fillText('offline', p.x, p.y - 7);
        }
        if (i % 3 === 0 || i === history.length - 1) {
            ctx.fillStyle = '#aaa';
            ctx.font = '8px monospace';
            ctx.textAlign = 'center';
            ctx.fillText(p.time || (i+1+''), p.x, height - 5);
        }
    }
}

function refreshPingData() { if (pingData.ip) fetchPingData(); }

window.onclick = function(event) {
    if (event.target == document.getElementById('remoteModal')) closeModal('remoteModal');
    if (event.target == document.getElementById('pingModal')) closePingModal();
    if (event.target == document.getElementById('detailModal')) closeModal('detailModal');
    if (event.target == document.getElementById('syncModal')) {
        if (!syncInProgress) closeSyncModal();
    }
}

// === FITUR LIVE SEARCH (SEARCH AS YOU TYPE) ===
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const searchForm = document.getElementById('searchForm');
    let debounceTimer;

    if (searchInput && searchForm) {
        // Deteksi setiap perubahan karakter saat mengetik
        searchInput.addEventListener('input', function(e) {
            clearTimeout(debounceTimer); // Reset timer jika masih mengetik
            
            // Sinkronisasi keyword ke hidden input agar tidak hilang saat pindah halaman (pagination)
            const sortSearchInput = document.getElementById('search_input');
            if (sortSearchInput) sortSearchInput.value = e.target.value;

            // Tunda pencarian 400ms (Debounce) agar performa server tetap aman
            debounceTimer = setTimeout(function() {
                const tbody = document.querySelector('.data-table tbody');
                if (tbody) tbody.style.opacity = '0.3'; // Beri efek redup sebagai indikator loading

                const formData = new FormData(searchForm);
                
                // Kirim request di background
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // 1. Ganti Isi Tabel Secara Halus
                    const currentTableWrapper = document.querySelector('.data-table').parentElement;
                    const newTableWrapper = doc.querySelector('.data-table').parentElement;
                    
                    if (currentTableWrapper && newTableWrapper) {
                        currentTableWrapper.innerHTML = newTableWrapper.innerHTML;
                        currentTableWrapper.querySelector('.data-table tbody').style.opacity = '1';
                    }

                    // 2. Perbarui Pagination dan Info Text
                    // Hapus elemen pagination & info lama
                    let sibling = currentTableWrapper.nextElementSibling;
                    while (sibling) {
                        let next = sibling.nextElementSibling;
                        sibling.remove();
                        sibling = next;
                    }

                    // Masukkan elemen pagination & info baru yang didapat dari server
                    let newSibling = newTableWrapper.nextElementSibling;
                    while (newSibling) {
                        currentTableWrapper.parentElement.appendChild(newSibling.cloneNode(true));
                        newSibling = newSibling.nextElementSibling;
                    }

                    // 3. Munculkan/Sembunyikan Tombol Reset
                    const currentResetBtn = searchForm.querySelector('.btn-warning');
                    const newResetBtn = doc.getElementById('searchForm').querySelector('.btn-warning');

                    if (newResetBtn && !currentResetBtn) {
                        const searchBtn = searchForm.querySelector('.btn-primary');
                        searchBtn.insertAdjacentElement('afterend', newResetBtn.cloneNode(true));
                    } else if (!newResetBtn && currentResetBtn) {
                        currentResetBtn.remove();
                    }
                })
                .catch(error => {
                    console.error('Error saat Live Search:', error);
                    if (tbody) tbody.style.opacity = '1';
                });
            }, 400); 
        });

        // Cegah halaman me-reload ketika menekan tombol Enter
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    }
});
</script>