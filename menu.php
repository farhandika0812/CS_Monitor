<?php
// menu.php - Menu Dinamis Berdasarkan Role (dengan toggle dark/light mode)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$activeMenu = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$role = $_SESSION['role'] ?? 'cs_user';

// Definisikan menu berdasarkan role
$menus = [];

if ($role == 'super_admin') {
    $menus = [
        ['page' => 'dashboard', 'icon' => '📊', 'label' => 'Dashboard'],
        ['page' => 'manage_router', 'icon' => '🌐', 'label' => 'Manage Router'],
        ['page' => 'manage_user', 'icon' => '👥', 'label' => 'Manage User'],
        ['page' => 'performa_cs', 'icon' => '📈', 'label' => 'Performa CS'],
        ['page' => 'log_cs', 'icon' => '📝', 'label' => 'Log CS'],
        ['page' => 'tools', 'icon' => '🔧', 'label' => 'Tools']  // <-- MENU BARU
    ];
} elseif ($role == 'admin') {
    $menus = [
        ['page' => 'dashboard', 'icon' => '📊', 'label' => 'Dashboard'],
        ['page' => 'manage_user', 'icon' => '👥', 'label' => 'Manage User'],
        ['page' => 'performa_cs', 'icon' => '📈', 'label' => 'Performa CS'],
        ['page' => 'log_cs', 'icon' => '📝', 'label' => 'Log CS'],
        ['page' => 'tools', 'icon' => '🔧', 'label' => 'Tools']  // <-- MENU BARU
    ];
} else { // CS, cs_user, teknisi, viewer
    $menus = [
        ['page' => 'dashboard', 'icon' => '📊', 'label' => 'Dashboard'],
        ['page' => 'log_cs', 'icon' => '📝', 'label' => 'Log CS']
        // Tools TIDAK muncul untuk CS/viewer
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>PanglimaNet - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ========== CSS VARIABLES (LIGHT MODE DEFAULT) ========== */
        :root {
            --bg-body: #f0f2f5;
            --bg-header: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            --bg-menu: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            --bg-card: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #666666;
            --border-color: #eeeeee;
            --table-header-bg: #f8f9fa;
            --table-row-hover: #f8f9fa;
            --btn-primary-bg: #3498db;
            --btn-primary-hover: #2980b9;
            --btn-danger-bg: #e74c3c;
            --btn-warning-bg: #f39c12;
            --modal-bg: #ffffff;
            --shadow-color: rgba(0,0,0,0.05);
            --menu-text: rgba(255,255,255,0.8);
            --menu-text-hover: white;
            --menu-active-bg: rgba(231,76,60,0.2);
            --header-text: white;
        }

        /* ========== DARK MODE ========== */
        [data-theme="dark"] {
            --bg-body: #121212;
            --bg-header: linear-gradient(135deg, #0a0a1a 0%, #0f0f2a 100%);
            --bg-menu: linear-gradient(180deg, #0a0a1a 0%, #0f0f2a 100%);
            --bg-card: #1e1e2e;
            --text-primary: #e0e0e0;
            --text-secondary: #aaaaaa;
            --border-color: #333333;
            --table-header-bg: #2a2a3a;
            --table-row-hover: #2a2a3a;
            --btn-primary-bg: #2980b9;
            --btn-primary-hover: #1f6d9b;
            --btn-danger-bg: #c0392b;
            --btn-warning-bg: #e67e22;
            --modal-bg: #2c2c3a;
            --shadow-color: rgba(0,0,0,0.3);
            --menu-text: rgba(255,255,255,0.7);
            --menu-text-hover: white;
            --menu-active-bg: rgba(231,76,60,0.3);
            --header-text: #f0f0f0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-body);
            overflow-x: hidden;
            transition: background 0.3s ease;
        }

        /* ========== FIXED HEADER ========== */
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: var(--bg-header);
            color: var(--header-text);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: background 0.3s ease;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-toggle-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 1002;
        }

        .menu-toggle-btn:hover {
            background: #e74c3c;
            transform: scale(1.05);
        }

        .menu-toggle-btn.active {
            background: #e74c3c;
        }

        .logo {
            font-size: 20px;
            font-weight: bold;
        }

        .logo span {
            color: #e74c3c;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn {
            background: rgba(231,76,60,0.2);
            border: 1px solid #e74c3c;
            color: #e74c3c;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #e74c3c;
            color: white;
        }

        /* ========== THEME TOGGLE BUTTON ========== */
        .theme-toggle-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .theme-toggle-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }

        /* ========== FLOATING MENU ========== */
        .floating-menu {
            position: fixed;
            top: 60px;
            left: -280px;
            width: 280px;
            height: calc(100% - 60px);
            background: var(--bg-menu);
            z-index: 999;
            transition: left 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 2px 0 20px rgba(0,0,0,0.2);
            overflow-y: auto;
        }

        .floating-menu.active {
            left: 0;
        }

        .menu-overlay {
            position: fixed;
            top: 60px;
            left: 0;
            width: 100%;
            height: calc(100% - 60px);
            background: rgba(0,0,0,0.5);
            z-index: 998;
            display: none;
        }

        .menu-overlay.active {
            display: block;
        }

        .menu-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }

        .menu-header .avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 32px;
            border: 3px solid rgba(255,255,255,0.3);
        }

        .menu-header h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .menu-header p {
            color: rgba(255,255,255,0.6);
            font-size: 11px;
        }

        .menu-items {
            list-style: none;
            padding: 10px 0;
        }

        .menu-items li {
            margin-bottom: 5px;
            opacity: 0;
            transform: translateX(-20px);
            transition: all 0.3s ease;
        }

        .floating-menu.active .menu-items li {
            opacity: 1;
            transform: translateX(0);
        }

        .menu-items li:nth-child(1) { transition-delay: 0.05s; }
        .menu-items li:nth-child(2) { transition-delay: 0.1s; }
        .menu-items li:nth-child(3) { transition-delay: 0.15s; }
        .menu-items li:nth-child(4) { transition-delay: 0.2s; }
        .menu-items li:nth-child(5) { transition-delay: 0.25s; }
        .menu-items li:nth-child(6) { transition-delay: 0.3s; }
        .menu-items li:nth-child(7) { transition-delay: 0.35s; }

        .menu-items a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--menu-text);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            position: relative;
        }

        .menu-items a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 28px;
            color: var(--menu-text-hover);
        }

        .menu-items a.active {
            background: var(--menu-active-bg);
            border-left: 4px solid #e74c3c;
            color: white;
        }

        .menu-items .icon {
            margin-right: 12px;
            font-size: 18px;
            min-width: 28px;
            text-align: center;
        }

        .menu-items .badge {
            position: absolute;
            right: 20px;
            background: #e74c3c;
            color: white;
            padding: 2px 6px;
            border-radius: 20px;
            font-size: 9px;
        }

        .logout-item {
            margin-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 10px;
        }

        .logout-item a {
            color: #e74c3c !important;
        }

        .logout-item a:hover {
            background: rgba(231,76,60,0.2);
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-top: 60px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .content-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px var(--shadow-color);
            max-width: 1400px;
            margin: 0 auto;
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }

        .content-card h2 {
            color: var(--text-primary);
            margin-bottom: 20px;
            border-bottom: 3px solid #e74c3c;
            padding-bottom: 10px;
            display: inline-block;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h4 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .data-table th {
            background: var(--table-header-bg);
            font-weight: 600;
            color: var(--text-primary);
        }

        .data-table tr:hover {
            background: var(--table-row-hover);
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--btn-primary-bg);
            color: white;
        }

        .btn-primary:hover {
            background: var(--btn-primary-hover);
        }

        .btn-danger {
            background: var(--btn-danger-bg);
            color: white;
        }

        .btn-warning {
            background: var(--btn-warning-bg);
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .badge-active {
            background: #27ae60;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .badge-inactive {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .filter-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-bar input,
        .filter-bar select {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            flex: 1;
            min-width: 150px;
            background: var(--bg-card);
            color: var(--text-primary);
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-primary);
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

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
            background: var(--modal-bg);
            border-radius: 12px;
            padding: 25px;
            width: 90%;
            max-width: 500px;
            color: var(--text-primary);
        }

        .modal-content h3 {
            margin-bottom: 20px;
        }

        .modal-content .form-group {
            margin-bottom: 15px;
        }

        .modal-content .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .modal-content .form-group input,
        .modal-content .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-card);
            color: var(--text-primary);
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .access-denied {
            text-align: center;
            padding: 50px;
            color: var(--text-primary);
        }

        .access-denied .icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: #e74c3c;
        }

        @media (max-width: 768px) {
            .fixed-header {
                padding: 0 15px;
            }
            .logo {
                font-size: 16px;
            }
            .user-name span:first-child {
                display: none;
            }
            .logout-btn {
                padding: 5px 10px;
                font-size: 12px;
            }
            .floating-menu {
                width: 260px;
                left: -260px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .data-table {
                font-size: 12px;
                display: block;
                overflow-x: auto;
            }
            .content-card {
                padding: 15px;
            }
            .filter-bar {
                flex-direction: column;
            }
        }

        .floating-menu::-webkit-scrollbar {
            width: 5px;
        }
        .floating-menu::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        .floating-menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 5px;
        }
    </style>
</head>
<body>

<!-- FIXED HEADER -->
<div class="fixed-header">
    <div class="logo-area">
        <button class="menu-toggle-btn" id="menuToggleBtn">☰</button>
        <div class="logo">Panglima<span>Net</span></div>
    </div>
    
    <div class="user-info">
        <div class="user-name">
            <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
        </div>
        <!-- TOMBOL THEME TOGGLE -->
        <button id="themeToggleBtn" class="theme-toggle-btn" aria-label="Toggle dark/light mode">
            <span class="theme-icon">🌙</span>
        </button>
        <a href="logout.php" class="logout-btn">🚪 Logout</a>
    </div>
</div>

<div class="menu-overlay" id="menuOverlay"></div>

<!-- FLOATING MENU -->
<div class="floating-menu" id="floatingMenu">
    <div class="menu-header">
        <div class="avatar">
            <?php
            if ($role == 'super_admin') echo '👑';
            elseif ($role == 'admin') echo '⚙️';
            else echo '💬';
            ?>
        </div>
        <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></h3>
    </div>
    
    <ul class="menu-items">
        <?php foreach ($menus as $menu): ?>
        <li>
            <a href="?page=<?php echo $menu['page']; ?>" class="<?php echo $activeMenu == $menu['page'] ? 'active' : ''; ?>">
                <span class="icon"><?php echo $menu['icon']; ?></span>
                <span><?php echo $menu['label']; ?></span>
            </a>
        </li>
        <?php endforeach; ?>
        <li class="logout-item">
            <a href="logout.php">
                <span class="icon">🚪</span>
                <span>Log Out</span>
            </a>
        </li>
    </ul>
</div>

<!-- MAIN CONTENT -->
<div class="main-content" id="mainContent">
    <div class="content-card">
        <?php
        // Definisikan halaman yang diizinkan berdasarkan role
        $allowedPages = [];
        if ($role == 'super_admin') {
            // TAMBAHKAN 'import_csv' DI SINI
            $allowedPages = ['dashboard', 'manage_router', 'manage_user', 'performa_cs', 'log_cs', 'tools', 'import_csv'];
        } elseif ($role == 'admin') {
            // TAMBAHKAN 'import_csv' DI SINI
            $allowedPages = ['dashboard', 'manage_user', 'performa_cs', 'log_cs', 'tools', 'import_csv'];
        } else {
            $allowedPages = ['dashboard', 'log_cs'];
        }
        
        if (in_array($activeMenu, $allowedPages)) {
            $pageFile = 'pages/' . $activeMenu . '.php';
            if (file_exists($pageFile)) {
                include $pageFile;
            } else {
                include 'pages/dashboard.php';
            }
        } else {
            ?>
            <div class="access-denied">
                <div class="icon">⛔</div>
                <h2>Akses Ditolak</h2>
                <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
                <a href="?page=dashboard" class="btn btn-primary" style="margin-top: 20px;">Kembali ke Dashboard</a>
            </div>
            <?php
        }
        ?>
    </div>
</div>

<script>
    // Menu toggle functionality
    const menuToggleBtn = document.getElementById('menuToggleBtn');
    const floatingMenu = document.getElementById('floatingMenu');
    const menuOverlay = document.getElementById('menuOverlay');

    function openMenu() {
        floatingMenu.classList.add('active');
        menuOverlay.classList.add('active');
        menuToggleBtn.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        floatingMenu.classList.remove('active');
        menuOverlay.classList.remove('active');
        menuToggleBtn.classList.remove('active');
        document.body.style.overflow = '';
    }

    function toggleMenu() {
        if (floatingMenu.classList.contains('active')) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    menuToggleBtn.addEventListener('click', toggleMenu);
    menuOverlay.addEventListener('click', closeMenu);

    const menuLinks = document.querySelectorAll('.menu-items a');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (this.getAttribute('href') !== 'logout.php') {
                setTimeout(closeMenu, 300);
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && floatingMenu.classList.contains('active')) {
            closeMenu();
        }
    });

    // ========== DARK/LIGHT MODE TOGGLE ==========
    (function() {
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeIcon = themeToggleBtn.querySelector('.theme-icon');
        
        // Cek preferensi tersimpan atau sistem
        const storedTheme = localStorage.getItem('theme');
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        let currentTheme = storedTheme || (systemDark ? 'dark' : 'light');
        
        // Terapkan tema awal
        if (currentTheme === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            themeIcon.textContent = '☀️';
        } else {
            document.body.setAttribute('data-theme', 'light');
            themeIcon.textContent = '🌙';
        }
        
        // Fungsi toggle tema
        function toggleTheme() {
            if (document.body.getAttribute('data-theme') === 'dark') {
                document.body.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
                themeIcon.textContent = '🌙';
            } else {
                document.body.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                themeIcon.textContent = '☀️';
            }
        }
        
        themeToggleBtn.addEventListener('click', toggleTheme);
    })();
</script>

</body>
</html>