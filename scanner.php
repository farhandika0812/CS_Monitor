<?php
/**
 * PHP Contextual Static Application Security Testing (SAST) Engine
 * MAXIMUM SECURITY EDITION - Advanced Stateful & Multi-line Source/Sink Analysis
 * * Karakteristik:
 * - Mampu mendeteksi tracking variabel dari baris yang berbeda (Stateful Tracking).
 * - Mampu mengeliminasi block comment  dengan presisi.
 * - Proteksi Runtime & Memory Optimized.
 */

namespace SecurityAuditorPlatinum;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Exception;

// =========================================================================
// 1. HARDENED ACCESS CONTROL (Bebas dari Parse Error)
// =========================================================================
if (php_sapi_name() !== 'cli') {
    $allowedIps = ['127.0.0.1', '::1'];
    if (!isset($_SERVER['REMOTE_ADDR']) || !in_array($_SERVER['REMOTE_ADDR'], $allowedIps, true)) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
        die("Akses Ditolak: Lingkungan audit terisolasi.");
    }
}

class PlatinumSecurityScanner {
    private string $targetDir;
    private array $ignoredDirs = ['vendor', 'node_modules', '.git', 'storage', 'cache', 'assets', 'tests'];
    private array $ignoredFiles = [];
    private array $scanResults = ['MEDIUM' => [], 'HIGH' => [], 'CRITICAL' => []];
    private array $metrics = [
        'total_files' => 0, 'total_lines' => 0,
        'medium_count' => 0, 'high_count' => 0, 'critical_count' => 0,
        'scan_time' => 0
    ];

    public function __construct(string $targetDir) {
        $realPath = realpath($targetDir);
        if (!$realPath || !is_dir($realPath)) {
            throw new Exception("Target direktori tidak valid.");
        }
        $this->targetDir = $realPath;
        $this->ignoredFiles[] = basename(__FILE__);
    }

    private function getAdvancedRules(): array {
        return [
            'SQL Injection (Tainted Variable)' => [
                'type' => 'taint_sink',
                'trigger_regex' => '/\b(select|insert|update|delete|replace)\b/i',
                'level' => 'CRITICAL',
                'desc' => 'Query database mentah mendeteksi adanya variabel dinamis yang membawa data tidak bersih (tainted).',
                'fix' => 'Gunakan Prepared Statements / Parameterized Query via PDO.'
            ],
            'Remote Code Execution (RCE)' => [
                'type' => 'regex_direct',
                'regex' => '/\b(eval|assert|passthru|shell_exec|system|proc_open|popen)\s*\(.*?\)/i',
                'level' => 'CRITICAL',
                'desc' => 'Eksekusi fungsi sistem atau evaluasi string dinamis yang sangat berbahaya.',
                'fix' => 'Hindari fungsi eksekusi runtime OS. Gunakan strict hashing/whitelisting jika mendesak.'
            ],
            'Insecure Deserialization' => [
                'type' => 'regex_direct',
                'regex' => '/\bunserialize\s*\(\s*.*?(?:\$_(GET|POST|REQUEST|COOKIE)|(?<!\$)\$[a-zA-Z0-9_]+)/i',
                'level' => 'CRITICAL',
                'desc' => 'Proses deserialisasi objek dari input luar tanpa verifikasi, berpotensi memicu Object Injection / RCE.',
                'fix' => 'Gunakan format data aman yang terstandarisasi seperti JSON lewat <code class="bg-slate-200 dark:bg-slate-800 px-1 rounded text-emerald-400">json_decode()</code>.'
            ],
            'Local/Remote File Inclusion (LFI/RFI)' => [
                'type' => 'taint_sink',
                'trigger_regex' => '/\b(include|require|include_once|require_once)\b/i',
                'level' => 'CRITICAL',
                'desc' => 'Pemuatan file lokal/remote memanfaatkan variabel dinamis tercemar tanpa whitelist.',
                'fix' => 'Gunakan arsitektur map array statis atau bersihkan input menggunakan fungsi basename().'
            ],
            'Cross-Site Scripting (Reflected XSS)' => [
                'type' => 'taint_sink',
                'trigger_regex' => '/\b(echo|print|printf|print_r)\b/i',
                'level' => 'HIGH',
                'desc' => 'Output data mentah langsung dilempar ke browser tanpa melewati fungsi sanitasi/encoding HTML.',
                'fix' => 'Bungkus variabel luaran dengan fungsi pengaman: <code class="bg-slate-200 dark:bg-slate-800 px-1 rounded text-emerald-400">htmlspecialchars($var, ENT_QUOTES, \'UTF-8\')</code>'
            ],
            'Directory Traversal Flaw' => [
                'type' => 'taint_sink',
                'trigger_regex' => '/\b(file_get_contents|readfile|file|fopen|unlink|file_put_contents)\b/i',
                'level' => 'HIGH',
                'desc' => 'Fungsi I/O file berinteraksi langsung dengan variabel dinamis, membuka celah manipulasi path (../).',
                'fix' => 'Validasi path menggunakan realpath() dan batasi ruang lingkup direktori yang diizinkan.'
            ],
            'Insecure Cryptographic Storage' => [
                'type' => 'regex_direct',
                'regex' => '/\b(md5|sha1)\s*\(.*?(password|pass|pwd|token|credential)/i',
                'level' => 'HIGH',
                'desc' => 'Penggunaan algoritma hashing usang (MD5/SHA1) untuk kredensial sensitif yang rentan terhadap serangan collision & rainbow table.',
                'fix' => 'Gunakan fungsi hashing modern bawaan PHP: <code class="bg-slate-200 dark:bg-slate-800 px-1 rounded text-emerald-400">password_hash()</code>.'
            ],
            'Hardcoded Secrets & JWT Tokens' => [
                'type' => 'regex_direct',
                'regex' => '/\b(api_key|secret_key|jwt_secret|db_pass|db_password)\b\s*=\s*[\'"][a-zA-Z0-9_\-\.\/]{8,}[\'"]/i',
                'level' => 'MEDIUM',
                'desc' => 'Ditemukan kebocoran string rahasia/kredensial yang tertanam kaku di dalam kode sumber.',
                'fix' => 'Pindahkan string kredensial ke file konfigurasi lingkungan terisolasi (.env).'
            ],
            'Weak Session Protection' => [
                'type' => 'regex_direct',
                'regex' => '/session_start\s*\(\s*\)/i',
                'level' => 'MEDIUM',
                'desc' => 'Inisialisasi sesi bawaan tanpa konfigurasi parameter pelindung cookie cookie_httponly dan cookie_secure.',
                'fix' => 'Ubah inisialisasi sesi menjadi: <code class="bg-slate-200 dark:bg-slate-800 px-1 rounded text-emerald-400">session_start([\'cookie_httponly\' => true, \'cookie_secure\' => true]);</code>'
            ]
        ];
    }

    public function execute(): array {
        $startTime = microtime(true);
        $directory = new RecursiveDirectoryIterator($this->targetDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory);
        $rules = $this->getAdvancedRules();

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $relativePath = str_replace($this->targetDir, '', $file->getPathname());
            $pathParts = explode(DIRECTORY_SEPARATOR, trim($relativePath, DIRECTORY_SEPARATOR));
            if (array_intersect($pathParts, $this->ignoredDirs)) continue;

            $filename = $file->getFilename();
            if (in_array($filename, ['.env', 'config.json', 'wp-config.php'], true)) {
                $this->metrics['total_files']++;
                $this->logIssue($file->getPathname(), 0, 'Infrastruktur Data', 'Exposed Configuration Leak', 'CRITICAL', 'File kredensial sensitif terekspos di area kerja.', 'Pindahkan file ini keluar dari root public.');
                continue;
            }

            if ($file->getExtension() === 'php' && !in_array($filename, $this->ignoredFiles, true)) {
                $this->metrics['total_files']++;
                $this->deepScanFile($file->getPathname(), $rules);
            }
        }

        $this->metrics['scan_time'] = round(microtime(true) - $startTime, 4);
        return ['metrics' => $this->metrics, 'results' => $this->scanResults];
    }

    private function deepScanFile(string $filePath, array $rules): void {
        $handle = fopen($filePath, "r");
        if (!$handle) return;

        $lineNum = 0;
        $inBlockComment = false;
        $taintedVariables = []; 

        while (($lineContent = fgets($handle)) !== false) {
            $lineNum++;
            $this->metrics['total_lines']++;
            $line = trim($lineContent);

            if ($inBlockComment) {
                if (str_contains($line, '*/')) {
                    $inBlockComment = false;
                    $line = substr($line, strpos($line, '*/') + 2);
                } else {
                    continue; 
                }
            }
            if (str_contains($line, '/*')) {
                if (!str_contains($line, '*/')) {
                    $inBlockComment = true;
                    $line = substr($line, 0, strpos($line, '/*'));
                } else {
                    $line = preg_replace('/\/\\*.*?\\*\//', '', $line);
                }
            }

            if (preg_match('/^(\/\/|#)/', $line)) continue;
            if (empty($line)) continue;

            if (preg_match('/(\$[a-zA-Z0-9_]+)\s*=\s*.*?(?:\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES))/i', $line, $matches)) {
                $varName = $matches[1];
                $taintedVariables[$varName] = [
                    'line' => $lineNum,
                    'source' => $matches[0]
                ];
            }

            foreach ($rules as $ruleName => $meta) {
                if ($meta['type'] === 'regex_direct') {
                    if (preg_match($meta['regex'], $line)) {
                        $this->logIssue($filePath, $lineNum, $line, $ruleName, $meta['level'], $meta['desc'], $meta['fix']);
                    }
                } 
                elseif ($meta['type'] === 'taint_sink') {
                    if (preg_match($meta['trigger_regex'], $line)) {
                        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE|SERVER)/', $line)) {
                            $this->logIssue($filePath, $lineNum, $line, $ruleName, $meta['level'], $meta['desc'], $meta['fix']);
                            continue;
                        }

                        foreach ($taintedVariables as $taintedVar => $sourceMeta) {
                            if (preg_match('/' . preg_quote($taintedVar, '/') . '\b/', $line)) {
                                $extendedDesc = $meta['desc'] . " <br><b class='text-red-400'>Jalur Kontaminasi:</b> Variabel <code class='bg-red-500/20 text-red-300 px-1 rounded'>{$taintedVar}</code> menerima data luar yang tidak steril di baris <b>#{$sourceMeta['line']}</b>.";
                                $this->logIssue($filePath, $lineNum, $line, $ruleName, $meta['level'], $extendedDesc, $meta['fix']);
                                break;
                            }
                        }
                    }
                }
            }
        }
        fclose($handle);
    }

    private function logIssue($path, $line, $code, $name, $level, $desc, $fix): void {
        $this->scanResults[$level][] = [
            'file' => htmlspecialchars(str_replace($this->targetDir, '.', $path), ENT_QUOTES, 'UTF-8'),
            'line' => (int)$line,
            'code' => htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
            'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'desc' => $desc, 
            'fix'  => $fix
        ];

        if ($level === 'MEDIUM') $this->metrics['medium_count']++;
        elseif ($level === 'HIGH') $this->metrics['high_count']++;
        elseif ($level === 'CRITICAL') $this->metrics['critical_count']++;
    }
}

try {
    $scanner = new PlatinumSecurityScanner(__DIR__);
    $report = $scanner->execute();
    $metrics = $report['metrics'];
    $results = $report['results'];
} catch (Exception $e) {
    die("Sistem Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

if (php_sapi_name() === 'cli') {
    echo "==================================================\n";
    echo " 🛡️ PLATINUM SAST ENGINE - ADVANCED DEEP SCANNED \n";
    echo "==================================================\n";
    echo "Total File PHP       : {$metrics['total_files']}\n";
    echo "Total Baris Kode     : {$metrics['total_lines']}\n";
    echo "--------------------------------------------------\n";
    echo "🚨 Critical Issues   : {$metrics['critical_count']}\n";
    echo "🔥 High Issues       : {$metrics['high_count']}\n";
    echo "⚠️ Medium Issues     : {$metrics['medium_count']}\n";
    echo "Waktu Pemrosesan     : {$metrics['scan_time']} Detik\n";
    echo "==================================================\n";
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platinum Deep Security Auditor</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-main: #f8fafc; --bg-card: #ffffff; --bg-header: #f1f5f9; --text-main: #1e293b; --text-muted: #64748b; --text-title: #0f172a; --border-color: #e2e8f0; }
        html[data-theme="dark"] { --bg-main: #0b0f19; --bg-card: #111827; --bg-header: #1f2937; --text-main: #e2e8f0; --text-muted: #94a3b8; --text-title: #ffffff; --border-color: #1f2937; }
        body { background-color: var(--bg-main); color: var(--text-main); }
        .custom-card { background-color: var(--bg-card); border-color: var(--border-color); }
        .text-title-custom { color: var(--text-title); }
        .text-muted-custom { color: var(--text-muted); }
    </style>
    <script>
        (function() { document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark'); })();
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            document.getElementById('theme-icon').className = newTheme === 'dark' ? "fa-solid fa-sun mr-2 text-amber-400" : "fa-solid fa-moon mr-2 text-indigo-600";
        }
    </script>
</head>
<body class="min-h-screen p-4 md:p-6 font-sans transition-colors duration-200">

    <div class="max-w-[1600px] mx-auto">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-slate-200 dark:border-slate-800 pb-5 mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-black tracking-tight flex items-center gap-2 text-title-custom">
                    <i class="fa-solid fa-shield-halved text-indigo-500"></i> Platinum Deep Auditor <span class="text-xs font-normal opacity-60">v5.0 Deep Scan Engine</span>
                </h1>
                <p class="text-xs text-muted-custom mt-1">Advanced Contextual Stateful SAST Security Analyzer.</p>
            </div>
            <button onclick="toggleTheme()" class="w-full sm:w-auto px-5 py-2.5 text-xs font-bold rounded-lg border custom-card text-title-custom flex items-center justify-center cursor-pointer">
                <script>
                    const currentIcon = (localStorage.getItem('theme') || 'dark') === 'dark' ? 'fa-sun text-amber-400' : 'fa-moon text-indigo-600';
                    document.write('<i id="theme-icon" class="fa-solid ' + currentIcon + ' mr-2"></i>');
                </script>
                Ubah Mode Tema
            </button>
        </header>

        <section class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="border p-3 rounded-xl custom-card">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Target File PHP</span>
                <div class="text-xl font-black text-title-custom mt-0.5"><i class="fa-regular fa-file-code text-indigo-500 mr-1"></i><?= $metrics['total_files'] ?></div>
            </div>
            <div class="border p-3 rounded-xl custom-card">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Baris Kode Diproses</span>
                <div class="text-xl font-bold text-title-custom mt-0.5"><i class="fa-solid fa-code text-slate-400 mr-1"></i><?= number_format($metrics['total_lines']) ?></div>
            </div>
            <div class="bg-amber-500/10 border border-amber-500/20 p-3 rounded-xl">
                <span class="text-[10px] text-amber-500 font-bold uppercase tracking-wider block">⚠️ Total Medium</span>
                <div class="text-xl font-black text-amber-500 mt-0.5"><?= $metrics['medium_count'] ?></div>
            </div>
            <div class="bg-orange-500/10 border border-orange-500/20 p-3 rounded-xl">
                <span class="text-[10px] text-orange-500 font-bold uppercase tracking-wider block">🔥 Total High</span>
                <div class="text-xl font-black text-orange-500 mt-0.5"><?= $metrics['high_count'] ?></div>
            </div>
            <div class="bg-red-500/10 border border-red-500/20 p-3 rounded-xl">
                <span class="text-[10px] text-red-500 font-bold uppercase tracking-wider block">🚨 Total Critical</span>
                <div class="text-xl font-black text-red-500 mt-0.5"><?= $metrics['critical_count'] ?></div>
            </div>
        </section>

        <main class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
            
            <?php foreach (['MEDIUM' => 'text-amber-500', 'HIGH' => 'text-orange-500', 'CRITICAL' => 'text-red-500'] as $level => $colorClass): ?>
                <div class="border rounded-xl p-4 custom-card h-full flex flex-col">
                    <div class="flex items-center justify-between border-b pb-3 mb-4 sticky top-0 z-10 custom-card">
                        <h2 class="text-sm font-black tracking-wider <?= $colorClass ?> uppercase flex items-center gap-2">
                            <i class="fa-solid <?= $level === 'CRITICAL' ? 'fa-skull-crossbones' : ($level === 'HIGH' ? 'fa-triangle-exclamation' : 'fa-circle-exclamation') ?>"></i> 
                            <?= $level ?> SEVERITY
                        </h2>
                        <span class="bg-slate-100 dark:bg-slate-800 text-title-custom text-xs px-2.5 py-0.5 rounded-full font-bold">
                            <?= count($results[$level]) ?>
                        </span>
                    </div>

                    <div class="space-y-4 flex-grow">
                        <?php if(!empty($results[$level])): ?>
                            <?php foreach($results[$level] as $issue): ?>
                                <div class="border rounded-lg p-4 shadow-sm space-y-3 custom-card">
                                    <div class="text-[11px] font-mono text-indigo-400 dark:text-emerald-400 break-all border-b pb-1.5 flex justify-between items-center border-slate-100 dark:border-slate-800">
                                        <span><i class="fa-regular fa-file mr-1"></i><?= $issue['file'] ?></span>
                                        <span class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-[10px] text-title-custom">Line <?= $issue['line'] ?></span>
                                    </div>
                                    <h3 class="font-bold text-xs text-title-custom"><?= $issue['name'] ?></h3>
                                    <p class="text-[11px] leading-relaxed text-muted-custom"><?= $issue['desc'] ?></p>
                                    <pre class="bg-slate-950 text-red-400 p-2 rounded text-[11px] overflow-x-auto border border-slate-900 shadow-inner"><code><?= $issue['code'] ?></code></pre>
                                    <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-md p-2.5 text-[11px] text-emerald-700 dark:text-emerald-400">
                                        <div class="font-bold mb-1"><i class="fa-solid fa-magic-wand-bits mr-1"></i>Remediasi Profesional:</div>
                                        <?= $issue['fix'] ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted-custom text-xs py-8 h-full flex items-center justify-center">
                                <div>🎉 Clean! Tidak ada temuan di tingkat ini.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        </main>

        <footer class="mt-12 border-t border-slate-200 dark:border-slate-800 pt-6 text-center text-[10px] text-slate-500 tracking-wide">
            Platinum Deep Auditor Engine • Dirancang khusus untuk Taint & Flow Vulnerability Tracker Contextual.
        </footer>
    </div>
</body>
</html>