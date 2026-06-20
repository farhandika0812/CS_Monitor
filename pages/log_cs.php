<?php
// pages/log_cs.php

// Cek role user yang login
$currentUserRole = $_SESSION['role'] ?? 'cs_user';
$currentUsername = $_SESSION['username'] ?? 'Guest';

// ========== ROLE-BASED FILTERING ==========
$allowedUsernames = [];
$roleLabel = '';
$roleColor = '';

if ($currentUserRole == 'cs_user') {
    $allowedUsernames = ['cs_user'];
    $roleLabel = 'CS';
    $roleColor = '#3498db';
} 
elseif ($currentUserRole == 'admin') {
    $allowedUsernames = ['admin', 'cs_user'];
    $roleLabel = 'Admin';
    $roleColor = '#f39c12';
} 
elseif ($currentUserRole == 'super_admin') {
    $allowedUsernames = [];
    $roleLabel = 'Super Admin';
    $roleColor = '#e74c3c';
}
else {
    $allowedUsernames = ['cs_user'];
    $roleLabel = 'CS';
    $roleColor = '#3498db';
}

// Pagination - 10 data per halaman
$page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query dengan role-based filtering
$where = [];
$params = [];

// Filter berdasarkan username
if (!empty($allowedUsernames)) {
    $placeholders = implode(',', array_fill(0, count($allowedUsernames), '?'));
    $where[] = "username IN ($placeholders)";
    foreach ($allowedUsernames as $username) {
        $params[] = $username;
    }
}

// Search filter
if ($search) {
    $where[] = "(username LIKE ? OR aksi LIKE ? OR detail LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Kategori filter
if ($kategori && $kategori != 'all') {
    $where[] = "kategori = ?";
    $params[] = $kategori;
}

// Date filters
if ($date_from) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Query
$baseSql = "FROM log_aktivitas $whereClause";

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) as total $baseSql");
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

// Get data
$sql = "SELECT * $baseSql ORDER BY created_at DESC LIMIT $offset, $perPage";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ========== Get unique categories for filter ==========
$catConditions = [];
$catParams = [];

if (!empty($allowedUsernames)) {
    $placeholders = implode(',', array_fill(0, count($allowedUsernames), '?'));
    $catConditions[] = "username IN ($placeholders)";
    foreach ($allowedUsernames as $username) {
        $catParams[] = $username;
    }
}
$catConditions[] = "kategori IS NOT NULL";

$catWhereClause = !empty($catConditions) ? "WHERE " . implode(" AND ", $catConditions) : "";
$catSql = "SELECT DISTINCT kategori FROM log_aktivitas $catWhereClause ORDER BY kategori";

$catStmt = $pdo->prepare($catSql);
$catStmt->execute($catParams);
$categories = $catStmt->fetchAll();

// ========== Statistik berdasarkan role ==========

// 1. Total aktivitas
if (!empty($allowedUsernames)) {
    $placeholders = implode(',', array_fill(0, count($allowedUsernames), '?'));
    $totalSql = "SELECT COUNT(*) as total FROM log_aktivitas WHERE username IN ($placeholders)";
    $totalParams = $allowedUsernames;
} else {
    $totalSql = "SELECT COUNT(*) as total FROM log_aktivitas";
    $totalParams = [];
}
$stmtTotal = $pdo->prepare($totalSql);
$stmtTotal->execute($totalParams);
$totalActivity = $stmtTotal->fetch()['total'];

// 2. Aktivitas hari ini
if (!empty($allowedUsernames)) {
    $placeholders = implode(',', array_fill(0, count($allowedUsernames), '?'));
    $todaySql = "SELECT COUNT(*) as total FROM log_aktivitas WHERE username IN ($placeholders) AND DATE(created_at) = CURDATE()";
    $todayParams = $allowedUsernames;
} else {
    $todaySql = "SELECT COUNT(*) as total FROM log_aktivitas WHERE DATE(created_at) = CURDATE()";
    $todayParams = [];
}
$stmtToday = $pdo->prepare($todaySql);
$stmtToday->execute($todayParams);
$todayActivity = $stmtToday->fetch()['total'];

// 3. Aktivitas 7 hari terakhir
if (!empty($allowedUsernames)) {
    $placeholders = implode(',', array_fill(0, count($allowedUsernames), '?'));
    $weekSql = "SELECT COUNT(*) as total FROM log_aktivitas WHERE username IN ($placeholders) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $weekParams = $allowedUsernames;
} else {
    $weekSql = "SELECT COUNT(*) as total FROM log_aktivitas WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $weekParams = [];
}
$stmtWeek = $pdo->prepare($weekSql);
$stmtWeek->execute($weekParams);
$weekActivity = $stmtWeek->fetch()['total'];
?>

<style>
    /* Filter bar - satu baris */
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
    .filter-bar select {
        flex: 1;
        min-width: 130px;
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
    
    /* STATISTIK CARDS */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    .stat-card {
        border-radius: 10px;
        padding: 18px 20px;
        color: white;
        transition: transform 0.2s;
        cursor: default;
    }
    .stat-card:hover {
        transform: translateY(-3px);
    }
    .stat-card h4 {
        margin: 0 0 10px 0;
        font-size: 15px;
        font-weight: 500;
        opacity: 0.95;
    }
    .stat-card .value {
        font-size: 32px;
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
    
    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .stat-card .value {
            font-size: 24px;
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
            padding: 6px 8px;
        }
        .pagination a, .pagination span {
            padding: 4px 8px;
            font-size: 11px;
        }
    }
</style>

<!-- STATISTIK GRID -->
<div class="stats-grid">
    <div class="stat-card" style="background: linear-gradient(135deg, #3498db, #2980b9);">
        <h4>📋 Total Aktivitas</h4>
        <div class="value"><?php echo number_format($totalActivity); ?></div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #27ae60, #2ecc71);">
        <h4>✅ Aktivitas Hari Ini</h4>
        <div class="value"><?php echo number_format($todayActivity); ?></div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
        <h4>📅 7 Hari Terakhir</h4>
        <div class="value"><?php echo number_format($weekActivity); ?></div>
    </div>
</div>

<!-- Filter Form - Satu Baris -->
<form method="GET" class="filter-bar">
    <input type="hidden" name="page" value="log_cs">
    <input type="text" name="search" placeholder="🔍 Cari user, aksi, detail..." value="<?php echo htmlspecialchars($search); ?>">
    <select name="kategori">
        <option value="all">📁 Semua Kategori</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?php echo htmlspecialchars($cat['kategori']); ?>" <?php echo $kategori == $cat['kategori'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($cat['kategori']); ?>
        </option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="📅 Dari tanggal">
    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="📅 Sampai tanggal">
    <button type="submit" class="btn-primary">🔍 Filter</button>
    <a href="?page=log_cs" class="btn-warning">🔄 Reset</a>
</form>

<!-- Tabel Data -->
<div style="overflow-x: auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>🕐 Waktu</th>
                <th>👤 Username</th>
                <th>🌐 IP Address</th>
                <th>⚡ Aksi</th>
                <th>📁 Kategori</th>
                <th>📝 Detail</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($log['username']); ?></td>
                <td><?php echo htmlspecialchars($log['ip_address']); ?></div>
                <td><?php echo htmlspecialchars($log['aksi']); ?></div>
                <td>
                    <span class="badge" style="background: <?php 
                        $colors = ['Login' => '#27ae60', 'Pencarian' => '#3498db', 'Sync' => '#f39c12', 'Router' => '#9b59b6', 'Akses' => '#1abc9c', 'System' => '#95a5a6'];
                        echo $colors[$log['kategori']] ?? '#7f8c8d';
                    ?>; color:white;">
                        <?php echo htmlspecialchars($log['kategori']); ?>
                    </span>
                </div>
                <td><?php echo htmlspecialchars($log['detail'] ?? '-'); ?></div>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                    📭 Tidak ada data log
                </div>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?page=log_cs&page_num=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kategori); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">« Prev</a>
    <?php endif; ?>
    
    <?php
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++):
    ?>
        <?php if ($i == $page): ?>
        <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
        <a href="?page=log_cs&page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kategori); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
    <a href="?page=log_cs&page_num=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kategori); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Next »</a>
    <?php endif; ?>
</div>
<div class="data-info">
    Menampilkan <?php echo count($logs); ?> dari <?php echo number_format($total); ?> data log
</div>
<?php endif; ?>