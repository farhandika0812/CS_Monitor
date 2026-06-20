<?php
set_time_limit(0);
ini_set('memory_limit', '512M');

// --- KONFIGURASI ---
$dirToScan = './'; 
$currentFile = basename(__FILE__);

// Keyword Entry Point
$entryPointKeywords = ['index.php', 'api.php', 'login.php', 'register.php', 'config.php', 'route.php', 'main.php'];

// 🔒 Daftar file yang DILINDUNGI (TIDAK BOLEH DIHAPUS)
$protectedFiles = [
    'security_shield.php'
];

if (!is_dir($dirToScan)) {
    die("Error: Folder '$dirToScan' tidak ditemukan.");
}

$rootPath = str_replace('\\', '/', realpath($dirToScan)) . '/';

// --- FITUR PROSES HAPUS FILE VIA AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    header('Content-Type: application/json');
    $fileToDelete = $_POST['file_path'] ?? '';

    // Validasi Keamanan Ke-1: Cegah manipulasi path (Path Traversal)
    $realTarget = realpath($rootPath . $fileToDelete);
    if (!$realTarget || strpos(str_replace('\\', '/', $realTarget), $rootPath) !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'Akses ilegal! Anda tidak bisa menghapus file di luar folder target.']);
        exit;
    }

    // Validasi Keamanan Ke-2: Jangan ijinkan menghapus file analyzer ini sendiri
    if (basename($realTarget) === $currentFile) {
        echo json_encode(['status' => 'error', 'message' => 'Aksi ditolak! Anda tidak bisa menghapus file script analyzer ini sendiri.']);
        exit;
    }
    
    // 🔒 Validasi Keamanan Ke-3: Cek apakah file termasuk protected
    $fileName = basename($realTarget);
    if (in_array($fileName, $protectedFiles)) {
        echo json_encode(['status' => 'error', 'message' => 'Aksi ditolak! File ' . $fileName . ' adalah file sistem yang dilindungi dan tidak boleh dihapus.']);
        exit;
    }

    // Proses eksekusi hapus
    if (file_exists($realTarget)) {
        if (@unlink($realTarget)) {
            echo json_encode(['status' => 'success', 'message' => 'File berhasil dihapus permanen.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus file. Periksa izin folder/file (Permission) Anda.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File sudah tidak ada atau telah dihapus sebelumnya.']);
    }
    exit;
}

// Helper rekursif untuk mengambil file PHP
function getPhpFiles($dir) {
    $results = [];
    $files = scandir($dir);
    foreach ($files as $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                $results[] = str_replace('\\', '/', $path);
            }
        } else if ($value != "." && $value != "..") {
            $results = array_merge($results, getPhpFiles($path));
        }
    }
    return $results;
}

$allFiles = getPhpFiles($dirToScan);

$safeToDelete = [];
$cannotDelete = [];
$stats = ['total' => 0, 'safe' => 0, 'used' => 0];

if (!empty($allFiles)) {
    foreach ($allFiles as $file) {
        if (basename($file) === $currentFile) continue;
        
        $stats['total']++;
        $fileName = basename($file);
        $relativePath = str_replace($rootPath, '', $file);
        
        // 🔒 CEK APAKAH FILE TERMASUK PROTECTED
        $isProtected = in_array($fileName, $protectedFiles);
        
        // Jika file protected, anggap sebagai "TERPAKAI" (tidak aman dihapus)
        if ($isProtected) {
            $cannotDelete[] = [
                'name' => $fileName . ' 🔒',
                'path' => $relativePath,
                'callers' => ['🔒 FILE SISTEM - DILINDUNGI'],
                'size' => round(filesize($file) / 1024, 2) . ' KB'
            ];
            $stats['used']++;
            continue;
        }
        
        $isUsed = false;
        $callers = [];

        if (in_array(strtolower($fileName), $entryPointKeywords)) {
            $isUsed = true;
            $callers[] = "Sistem (Dideteksi sebagai Entry Point Utama)";
        }

        foreach ($allFiles as $searchInFile) {
            if ($file === $searchInFile) continue;

            $content = file_get_contents($searchInFile);
            $searchName = pathinfo($fileName, PATHINFO_FILENAME);
            
            if (strpos($content, $fileName) !== false || strpos($content, "'".$searchName."'") !== false || strpos($content, '"'.$searchName.'"') !== false) {
                $isUsed = true;
                $callers[] = str_replace($rootPath, '', $searchInFile);
            }
        }

        if (!$isUsed) {
            $safeToDelete[] = [
                'name' => $fileName,
                'path' => $relativePath,
                'size' => round(filesize($file) / 1024, 2) . ' KB',
                'modified' => date("Y-m-d H:i:s", filemtime($file))
            ];
            $stats['safe']++;
        } else {
            $cannotDelete[] = [
                'name' => $fileName,
                'path' => $relativePath,
                'callers' => array_unique($callers),
                'size' => round(filesize($file) / 1024, 2) . ' KB'
            ];
            $stats['used']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Code Dependency Analyzer</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col font-sans">

    <header class="bg-slate-800 border-b border-slate-700 p-6 sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-emerald-400 flex items-center gap-2">
                    <span>🔍</span> PHP Dependency Analyzer
                </h1>
                <p class="text-sm text-slate-400 mt-1">Target Analisis: <code class="bg-slate-950 px-2 py-0.5 rounded text-amber-400 font-mono"><?= htmlspecialchars($rootPath) ?></code></p>
            </div>
            <div class="flex gap-4 w-full md:w-auto">
                <div class="bg-slate-950 px-4 py-2 rounded-lg border border-slate-700 text-center flex-1 md:flex-none">
                    <span class="block text-xs text-slate-400 uppercase">Total File</span>
                    <span class="text-xl font-bold text-blue-400"><?= $stats['total'] ?></span>
                </div>
                <div class="bg-slate-950 px-4 py-2 rounded-lg border border-slate-700 text-center flex-1 md:flex-none">
                    <span class="block text-xs text-slate-400 uppercase">Aman Dihapus</span>
                    <span class="text-xl font-bold text-rose-400" id="stat-safe"><?= $stats['safe'] ?></span>
                </div>
                <div class="bg-slate-950 px-4 py-2 rounded-lg border border-slate-700 text-center flex-1 md:flex-none">
                    <span class="block text-xs text-slate-400 uppercase">Terhubung</span>
                    <span class="text-xl font-bold text-emerald-400"><?= $stats['used'] ?></span>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 p-6 max-w-7xl w-full mx-auto grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        
        <section class="bg-slate-800 rounded-xl border border-slate-700 p-5 shadow-xl h-[calc(100vh-180px)] flex flex-col min-h-[500px]">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-700">
                <h2 class="text-lg font-semibold text-rose-400 flex items-center gap-2">
                    ⚠️ Mungkin Aman Dihapus <span id="badge-safe" class="bg-rose-500/10 text-rose-400 text-xs px-2 py-0.5 rounded-full"><?= count($safeToDelete) ?></span>
                </h2>
                <span class="text-xs text-slate-400">Tidak terdeteksi dependensi</span>
            </div>
            
            <div id="safe-list-container" class="overflow-y-auto flex-1 pr-2 space-y-3 custom-scrollbar">
                <?php if (empty($safeToDelete)): ?>
                    <div class="text-center text-slate-500 my-auto py-12">Tidak ada file tak terpakai yang terdeteksi.</div>
                <?php else: ?>
                    <?php foreach ($safeToDelete as $file): ?>
                        <div id="file-card-<?= md5($file['path']) ?>" class="p-3 bg-slate-900 rounded-lg border border-slate-700/50 hover:border-rose-500/30 transition group flex flex-col justify-between gap-2">
                            <div class="flex justify-between items-start gap-2">
                                <div class="break-all pr-2">
                                    <span class="font-medium text-slate-200 group-hover:text-rose-400 transition block"><?= $file['name'] ?></span>
                                    <p class="text-xs text-slate-500 mt-0.5 font-mono"><?= $file['path'] ?></p>
                                </div>
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    <span class="text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded"><?= $file['size'] ?></span>
                                    
                                    <button onclick="konfirmasiHapus('<?= addslashes($file['path']) ?>', '<?= addslashes($file['name']) ?>', '<?= md5($file['path']) ?>')" 
                                            class="text-xs bg-rose-950/40 hover:bg-rose-600 text-rose-400 hover:text-white px-2.5 py-1 rounded border border-rose-500/30 transition duration-200 flex items-center gap-1 cursor-pointer">
                                        🗑️ Hapus
                                    </button>
                                </div>
                            </div>
                            <div class="text-[10px] text-slate-500 border-t border-slate-800/60 pt-2 mt-1">
                                <span>Modifikasi terakhir: <?= $file['modified'] ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="bg-slate-800 rounded-xl border border-slate-700 p-5 shadow-xl h-[calc(100vh-180px)] flex flex-col min-h-[500px]">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-700">
                <h2 class="text-lg font-semibold text-emerald-400 flex items-center gap-2">
                    ✅ Jangan Dihapus <span class="bg-emerald-500/10 text-emerald-400 text-xs px-2 py-0.5 rounded-full"><?= count($cannotDelete) ?></span>
                </h2>
                <span class="text-xs text-slate-400">Saling terhubung / Entry Point</span>
            </div>
            
            <div class="overflow-y-auto flex-1 pr-2 space-y-3 custom-scrollbar">
                <?php if (empty($cannotDelete)): ?>
                    <div class="text-center text-slate-500 my-auto py-12">Semua file bersih dari relasi.</div>
                <?php else: ?>
                    <?php foreach ($cannotDelete as $file): ?>
                        <div class="p-3 bg-slate-900 rounded-lg border border-slate-700/50">
                            <div class="flex justify-between items-start gap-2">
                                <span class="font-medium text-slate-200 break-all"><?= $file['name'] ?></span>
                                <span class="text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded shrink-0"><?= $file['size'] ?></span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1 break-all font-mono"><?= $file['path'] ?></p>
                            
                            <div class="mt-2 pt-2 border-t border-slate-800/60">
                                <span class="text-[10px] text-slate-400 uppercase tracking-wider block mb-1">Dipanggil Oleh:</span>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($file['callers'] as $caller): ?>
                                        <span class="text-[11px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded border border-slate-700/40 truncate max-w-xs" title="<?= $caller ?>">
                                            <?= basename($caller) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <footer class="bg-slate-950 text-slate-500 text-center py-3 text-xs border-t border-slate-800">
        <p>💡 <strong>Tips Keamanan:</strong> Fitur hapus akan menghapus file secara permanen dari server. Pastikan Anda sudah mem-backup code sebelum bersih-bersih.</p>
    </footer>

    <script>
        function konfirmasiHapus(filePath, fileName, cardId) {
            // Membuka alert konfirmasi bawaan SweetAlert2 dengan tema dark yang serasi
            Swal.fire({
                title: 'Apakah Anda Yakin?',
                text: `File "${fileName}" akan dihapus permanen dari direktori server.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f43f5e', // warna rose-500
                cancelButtonColor: '#475569',  // warna slate-600
                confirmButtonText: 'Ya, Hapus File!',
                cancelButtonText: 'Batal',
                background: '#1e293b',         // tema warna slate-800 agar sinkron
                color: '#f8fafc'               // text slate-50
            }).then((result) => {
                // Jika user mengonfirmasi tombol "Ya, Hapus File!"
                if (result.isConfirmed) {
                    eksekusiHapusFile(filePath, cardId);
                }
            });
        }

        function eksekusiHapusFile(filePath, cardId) {
            // Tampilkan loading spinner saat proses hapus berjalan
            Swal.fire({
                title: 'Menghapus...',
                text: 'Sedang memproses permintaan Anda.',
                allowOutsideClick: false,
                background: '#1e293b',
                color: '#f8fafc',
                didOpen: () => { Swal.showLoading(); }
            });

            // Kirim request ke file ini sendiri via AJAX Post
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_file&file_path=${encodeURIComponent(filePath)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: data.message,
                        icon: 'success',
                        background: '#1e293b',
                        color: '#f8fafc'
                    });

                    // Hapus kartu file secara visual dari layar tanpa reload halaman
                    const fileCard = document.getElementById(`file-card-${cardId}`);
                    if (fileCard) {
                        fileCard.remove();
                        
                        // Update counter statistik di bagian header & badge secara realtime
                        const safeStat = document.getElementById('stat-safe');
                        const safeBadge = document.getElementById('badge-safe');
                        
                        let currentCount = parseInt(safeStat.innerText) - 1;
                        safeStat.innerText = currentCount;
                        safeBadge.innerText = currentCount;

                        if (currentCount === 0) {
                            document.getElementById('safe-list-container').innerHTML = 
                                '<div class="text-center text-slate-500 my-auto py-12">Tidak ada file tak terpakai yang terdeteksi.</div>';
                        }
                    }
                } else {
                    // Tampilkan alert jika terjadi error (gagal permission dll)
                    Swal.fire({
                        title: 'Gagal!',
                        text: data.message,
                        icon: 'error',
                        background: '#1e293b',
                        color: '#f8fafc'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'System Error!',
                    text: 'Terjadi kegagalan koneksi saat mencoba menghapus file.',
                    icon: 'error',
                    background: '#1e293b',
                    color: '#f8fafc'
                });
            });
        }
    </script>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #64748b; }
    </style>
</body>
</html>