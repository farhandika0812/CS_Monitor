<?php
// pages/performa_cs.php

// Ambil data performa CS dari view
$stmt = $pdo->query("SELECT * FROM v_performa_cs ORDER BY total_aktivitas DESC");
$csPerformances = $stmt->fetchAll();

// Hitung total keseluruhan
$totalActivities = array_sum(array_column($csPerformances, 'total_aktivitas'));
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
    <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <h4>👥 Total CS Aktif</h4>
        <div class="value"><?php echo count($csPerformances); ?></div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);">
        <h4>📋 Total Aktivitas</h4>
        <div class="value"><?php echo number_format($totalActivities); ?></div>
    </div>
</div>

<!-- Filter Form - Satu Baris (untuk konsistensi) -->
<form method="GET" class="filter-bar">
    <input type="hidden" name="page" value="performa_cs">
    <input type="text" name="search" placeholder="🔍 Cari username..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
    <select name="sort_by">
        <option value="total_aktivitas" <?php echo ($sort_by ?? 'total_aktivitas') == 'total_aktivitas' ? 'selected' : ''; ?>>📊 Urutkan: Total Aktivitas</option>
        <option value="hari_aktif" <?php echo ($sort_by ?? '') == 'hari_aktif' ? 'selected' : ''; ?>>📅 Urutkan: Hari Aktif</option>
        <option value="last_active" <?php echo ($sort_by ?? '') == 'last_active' ? 'selected' : ''; ?>>🕐 Urutkan: Last Active</option>
    </select>
    <button type="submit" class="btn-primary">🔍 Filter</button>
    <a href="?page=performa_cs" class="btn-warning">🔄 Reset</a>
</form>

<!-- Tabel Data -->
<div style="overflow-x: auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>🏆 Rank</th>
                <th>👤 Username</th>
                <th>📊 Total Aktivitas</th>
                <th>🔐 Login</th>
                <th>🔍 Pencarian</th>
                <th>🔄 Sync</th>
                <th>🌐 Router</th>
                <th>📅 Hari Aktif</th>
                <th>🕐 Last Active</th>
            </tr>
        </thead>
        <tbody>
            <?php $rank = 1; foreach ($csPerformances as $cs): ?>
            <tr>
                <td>
                    <?php 
                    if ($rank == 1) echo '🥇';
                    elseif ($rank == 2) echo '🥈';
                    elseif ($rank == 3) echo '🥉';
                    else echo $rank;
                    ?>
                </td>
                <td><?php echo htmlspecialchars($cs['username']); ?></td>
                <td><strong><?php echo number_format($cs['total_aktivitas']); ?></strong></td>
                <td><?php echo number_format($cs['total_login']); ?></td>
                <td><?php echo number_format($cs['total_pencarian']); ?></td>
                <td><?php echo number_format($cs['total_sync']); ?></td>
                <td><?php echo number_format($cs['total_router']); ?></td>
                <td><?php echo number_format($cs['hari_aktif']); ?> hari</td>
                <td><?php echo $cs['last_active'] ? date('d/m/Y H:i', strtotime($cs['last_active'])) : '-'; ?></td>
            </tr>
            <?php $rank++; endforeach; ?>
            
            <?php if (empty($csPerformances)): ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                    📭 Belum ada data performa CS
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Info Data -->
<div class="data-info">
    Menampilkan <?php echo count($csPerformances); ?> data Customer Service
</div>