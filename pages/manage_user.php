<?php
// pages/manage_user.php - Halaman Manage User (menggunakan PDO)
if (!isset($_SESSION['user_id'])) {
    exit('Akses ditolak');
}

// Koneksi database menggunakan PDO
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database_pdo.php';
}

$error = null; // Inisialisasi variable error

// Proses tambah user - menggunakan MD5
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $username = trim($_POST['username']);
        $password = md5($_POST['password']); // MD5
        $full_name = trim($_POST['full_name']);
        $role = trim($_POST['role']);
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        
        // Cek username duplikat
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetch()) {
            $_SESSION['swal_message'] = [
                'title' => '❌ Gagal!',
                'text' => "Username <strong>" . htmlspecialchars($username) . "</strong> sudah terdaftar!",
                'icon' => 'error'
            ];
            echo '<script>window.location.href="?page=manage_user&swal=1";</script>';
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, is_active, created_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            if ($stmt->execute([$username, $password, $full_name, $role, $is_active])) {
                $_SESSION['swal_message'] = [
                    'title' => '✅ Berhasil!',
                    'text' => "User <strong>" . htmlspecialchars($username) . "</strong> berhasil ditambahkan",
                    'icon' => 'success'
                ];
                echo '<script>window.location.href="?page=manage_user&swal=1";</script>';
                exit;
            } else {
                $_SESSION['swal_message'] = [
                    'title' => '❌ Gagal!',
                    'text' => "Gagal menambahkan user: " . htmlspecialchars(implode(" ", $stmt->errorInfo())),
                    'icon' => 'error'
                ];
                echo '<script>window.location.href="?page=manage_user&swal=1";</script>';
                exit;
            }
        }
    }
    
    // Proses edit user - menggunakan MD5
    if ($_POST['action'] == 'edit') {
        $id = (int)$_POST['id'];
        $full_name = trim($_POST['full_name']);
        $role = trim($_POST['role']);
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        
        // Jika password tidak kosong, update password juga (MD5)
        if (!empty($_POST['password'])) {
            $password = md5($_POST['password']); // MD5
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, password=?, role=?, is_active=? WHERE id=?");
            $result = $stmt->execute([$full_name, $password, $role, $is_active, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, role=?, is_active=? WHERE id=?");
            $result = $stmt->execute([$full_name, $role, $is_active, $id]);
        }
        
        if ($result) {
            if (function_exists('logActivity')) {
                logActivity($_SESSION['username'] ?? 'System', "Edit user: $full_name", 'User');
            }
            $_SESSION['swal_message'] = [
                'title' => '✅ Berhasil!',
                'text' => "User <strong>" . htmlspecialchars($full_name) . "</strong> berhasil diperbarui",
                'icon' => 'success'
            ];
            echo '<script>window.location.href="?page=manage_user&swal=1";</script>';
            exit;
        } else {
            $_SESSION['swal_message'] = [
                'title' => '❌ Gagal!',
                'text' => "Gagal mengupdate user",
                'icon' => 'error'
            ];
            echo '<script>window.location.href="?page=manage_user&swal=1";</script>';
            exit;
        }
    }
    
    // Proses hapus user
    if ($_POST['action'] == 'delete') {
        $id = (int)$_POST['id'];
        
        // Cegah menghapus diri sendiri
        if ($id == $_SESSION['user_id']) {
            $_SESSION['swal_message'] = [
                'title' => '⚠️ Peringatan!',
                'text' => "Anda tidak dapat menghapus akun sendiri!",
                'icon' => 'warning'
            ];
            echo '<script>window.location.href="?page=manage_user&swal=1";</script>';
            exit;
        } else {
            $stmtName = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmtName->execute([$id]);
            $username = $stmtName->fetchColumn();
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            if ($stmt->execute([$id])) {
                if (function_exists('logActivity')) {
                    logActivity($_SESSION['username'] ?? 'System', "Hapus user: $username", 'User');
                }
                $_SESSION['swal_message'] = [
                    'title' => '🗑️ Terhapus!',
                    'text' => "User berhasil dihapus",
                    'icon' => 'success'
                ];
                echo '<script>window.location.href="?page=manage_user&swal=1";</script>';
                exit;
            } else {
                $_SESSION['swal_message'] = [
                    'title' => '❌ Gagal!',
                    'text' => "Gagal menghapus user",
                    'icon' => 'error'
                ];
                echo '<script>window.location.href="?page=manage_user&swal=1";</script>';
                exit;
            }
        }
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
            width: '380px',
            padding: '0.8rem',
            customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                htmlContainer: 'swal2-html-custom',
                confirmButton: 'swal2-confirm-custom'
            }
        }).then(function() {
            window.location.href = '?page=manage_user';
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
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';

// Build query dengan filter pencarian
$where = [];
$params = [];

if ($search) {
    $where[] = "(username LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role_filter) {
    $where[] = "role = ?";
    $params[] = $role_filter;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Query untuk total data
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $whereClause");
$countStmt->execute($params);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $perPage);

// Get data dengan pagination
$sql = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $offset, $perPage";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik
$stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

$stats = [];
$statQuery = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $statQuery->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['role']] = $row['count'];
}

// Hitung user aktif
$stmtActive = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
$activeUsers = $stmtActive->fetch(PDO::FETCH_ASSOC)['total'];

// Hitung user nonaktif
$stmtInactive = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 0");
$inactiveUsers = $stmtInactive->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User - PanglimaNet</title>
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { box-sizing: border-box; }
        
        /* PERBAIKAN: SweetAlert font lebih kecil dan proporsional */
        .swal2-popup-custom {
            border-radius: 12px !important;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
            font-size: 12px !important;
        }
        .swal2-title-custom {
            font-size: 16px !important;
            font-weight: 600 !important;
            padding: 0 0 8px 0 !important;
        }
        .swal2-html-custom {
            font-size: 12px !important;
        }
        .swal2-confirm-custom {
            font-size: 12px !important;
            padding: 6px 18px !important;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .stat-box:hover {
            transform: translateY(-3px);
        }
        .stat-box h4 {
            margin: 0 0 8px 0;
            font-size: 12px;
            opacity: 0.9;
        }
        .stat-box .number {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }
        
        /* Filter bar */
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
        .filter-bar input, .filter-bar select, .filter-bar button {
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
        .filter-bar .btn-secondary {
            background: #95a5a6;
            color: white;
            border: none;
            cursor: pointer;
        }
        .filter-bar .btn-success {
            background: #27ae60;
            color: white;
            border: none;
            cursor: pointer;
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        /* PERBAIKAN: Role badge dengan lebar yang sama */
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            min-width: 110px;
            text-align: center;
            white-space: nowrap;
        }
        .role-super_admin { background: #e74c3c; color: white; }
        .role-admin { background: #f39c12; color: white; }
        .role-cs { background: #3498db; color: white; }
        .role-viewer { background: #95a5a6; color: white; }
        .status-active { color: #27ae60; font-weight: bold; }
        .status-inactive { color: #e74c3c; font-weight: bold; }
        
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
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .btn-warning:hover {
            background: #e67e22;
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
        
        /* Modal */
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
        }
        .modal-content input:focus, .modal-content select:focus {
            outline: none;
            border-color: #3498db;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 16px;
        }
        
        .data-info {
            text-align: center;
            margin-top: 10px;
            color: #7f8c8d;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .user-stats {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-bar input, .filter-bar select, .filter-bar button {
                width: 100%;
            }
            .data-table th, .data-table td {
                font-size: 11px;
                padding: 6px 8px;
            }
            .action-buttons {
                flex-direction: column;
            }
            .modal-content {
                padding: 16px;
            }
            /* Pada mobile, badge boleh lebih kecil */
            .role-badge {
                min-width: 90px;
                font-size: 10px;
                padding: 3px 6px;
            }
        }
    </style>
</head>
<body>

<!-- STATISTIK GRID - HAPUS DUPLICATE SUPER ADMIN -->
<div class="user-stats">
    <div class="stat-box">
        <h4>👥 Total User</h4>
        <div class="number"><?php echo number_format($totalUsers); ?></div>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, #27ae60, #2ecc71);">
        <h4>✅ Aktif</h4>
        <div class="number"><?php echo number_format($activeUsers); ?></div>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
        <h4>❌ Nonaktif</h4>
        <div class="number"><?php echo number_format($inactiveUsers); ?></div>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
        <h4>👑 Super Admin</h4>
        <div class="number"><?php echo number_format($stats['super_admin'] ?? 0); ?></div>
    </div>
    <div class="stat-box" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
        <h4>⚙️ Admin</h4>
        <div class="number"><?php echo number_format($stats['admin'] ?? 0); ?></div>
    </div>
</div>

<!-- Filter Form -->
<div class="filter-bar">
    <input type="text" id="searchInput" placeholder="🔍 Cari username atau nama..." value="<?php echo htmlspecialchars($search); ?>">
    <select id="roleFilter">
        <option value="">Semua Role</option>
        <option value="super_admin" <?php echo $role_filter == 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
        <option value="cs" <?php echo $role_filter == 'cs' ? 'selected' : ''; ?>>CS User</option>
        <option value="viewer" <?php echo $role_filter == 'viewer' ? 'selected' : ''; ?>>Viewer</option>
    </select>
    <button class="btn-primary" onclick="applyFilter()">🔍 Filter</button>
    <button class="btn-secondary" onclick="resetFilter()">🔄 Reset</button>
    <button class="btn-success" onclick="openAddModal()">➕ Tambah User</button>
</div>

<!-- Tabel User -->
<div style="overflow-x: auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Nama Lengkap</th>
                <th>Role</th>
                <th>Status</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($users) > 0): ?>
                <?php foreach ($users as $user): ?>
                <tr id="user-row-<?php echo $user['id']; ?>">
                    <td><?php echo $user['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td>
                        <span class="role-badge role-<?php echo $user['role']; ?>">
                            <?php 
                            $role_labels = [
                                'super_admin' => '👑 Super Admin',
                                'admin' => '⚙️ Admin',
                                'cs' => '💬 CS User',
                                'viewer' => '👁️ Viewer'
                            ];
                            echo $role_labels[$user['role']] ?? $user['role'];
                            ?>
                        </span>
                    </span>
                    <td>
                        <span class="<?php echo ($user['is_active'] ?? 1) == 1 ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo ($user['is_active'] ?? 1) == 1 ? '✅ Aktif' : '❌ Nonaktif'; ?>
                        </span>
                    </span>
                    <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></span>
                    <td class="action-buttons">
                        <button class="btn btn-warning btn-sm" onclick='editUser(<?php echo json_encode($user); ?>)'>✏️ Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">🗑️ Hapus</button>
                    </span>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                        📭 Tidak ada data user
                    </span>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?page=manage_user&page_num=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">« Prev</a>
    <?php endif; ?>
    
    <?php
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++):
    ?>
        <?php if ($i == $page): ?>
        <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
        <a href="?page=manage_user&page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
    <a href="?page=manage_user&page_num=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">Next »</a>
    <?php endif; ?>
</div>
<div class="data-info">
    Menampilkan <?php echo count($users); ?> dari <?php echo number_format($total); ?> data user
</div>
<?php endif; ?>

<!-- MODAL TAMBAH/EDIT USER -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">➕ Tambah User</h3>
        <form method="POST" id="userForm" action="?page=manage_user">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="userId">
            
            <label>Username *</label>
            <input type="text" name="username" id="username" required>
            
            <label>Password <span id="passwordHint" style="font-size: 11px; color: #888; display: none;">(kosongkan jika tidak diubah)</span></label>
            <input type="password" name="password" id="password">
            
            <label>Nama Lengkap *</label>
            <input type="text" name="full_name" id="full_name" required>
            
            <label>Role *</label>
            <select name="role" id="role" required>
                <option value="">Pilih Role</option>
                <option value="super_admin">Super Admin</option>
                <option value="admin">Admin</option>
                <option value="cs">CS User</option>
                <option value="viewer">Viewer</option>
            </select>
            
            <div id="statusGroup">
                <label>Status</label>
                <select name="is_active" id="is_active">
                    <option value="1">✅ Aktif</option>
                    <option value="0">❌ Nonaktif</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" onclick="closeModal()">❌ Batal</button>
                <button type="submit" class="btn btn-primary">💾 Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '➕ Tambah User';
    document.getElementById('formAction').value = 'add';
    document.getElementById('userId').value = '';
    document.getElementById('username').value = '';
    document.getElementById('username').disabled = false;
    document.getElementById('username').required = true;
    document.getElementById('password').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordHint').style.display = 'none';
    document.getElementById('full_name').value = '';
    document.getElementById('role').value = '';
    document.getElementById('is_active').value = '1';
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('userModal').style.display = 'flex';
}

function editUser(user) {
    document.getElementById('modalTitle').innerHTML = '✏️ Edit User';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('userId').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('username').disabled = true;
    document.getElementById('username').required = false;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('passwordHint').style.display = 'inline';
    document.getElementById('full_name').value = user.full_name;
    document.getElementById('role').value = user.role;
    document.getElementById('is_active').value = user.is_active || 1;
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('userModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
    document.getElementById('username').disabled = false;
    document.getElementById('username').required = true;
    document.getElementById('password').required = false;
    document.getElementById('password').value = '';
    document.getElementById('formAction').value = 'add';
}

function confirmDelete(userId, username) {
    Swal.fire({
        title: '⚠️ Hapus User',
        html: `Apakah Anda yakin ingin menghapus user <strong style="color:#e74c3c;">${escapeHtml(username)}</strong>?<br><span style="font-size:12px; color:#7f8c8d;">Data yang dihapus tidak dapat dikembalikan!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: '✅ Ya, Hapus!',
        cancelButtonText: '❌ Batal',
        background: '#fff',
        backdrop: 'rgba(0,0,0,0.4)',
        width: '380px',
        padding: '0.8rem',
        customClass: {
            popup: 'swal2-popup-custom',
            title: 'swal2-title-custom',
            htmlContainer: 'swal2-html-custom',
            confirmButton: 'swal2-confirm-custom'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '?page=manage_user';
            form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + userId + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function applyFilter() {
    const search = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    window.location.href = `?page=manage_user&search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}`;
}

function resetFilter() {
    window.location.href = '?page=manage_user';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Tutup modal klik di luar
window.onclick = function(event) {
    const modal = document.getElementById('userModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>
</body>
</html>