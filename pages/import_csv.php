<?php
// pages/import_csv.php
// TIDAK PERLU session_start() dan KONEKSI DB, karena otomatis mewarisi dari index.php & config.php

// Keamanan ekstra: cegah akses file langsung dari URL
if (!isset($pdo)) {
    die("Akses langsung tidak diizinkan.");
}

$pesan = '';

if (isset($_POST['upload_csv']) && isset($_FILES['file_csv'])) {
    $file = $_FILES['file_csv']['tmp_name'];
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        $baris = 0;
        $berhasil_update = 0;
        
        // Loop membaca setiap baris CSV
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $baris++;
            // Lewati baris pertama (Header CSV)
            if ($baris == 1) continue; 
            
            // Mapping Kolom berdasarkan file Data-members.csv
            $va_id       = $data[1] ?? '';  
            $nama_mentah = $data[2] ?? '';  
            $user_ppp    = $data[30] ?? ''; 
            
            // Jika User PPP kosong, lewati baris ini
            if (empty(trim($user_ppp))) continue;

            // Bersihkan nama (Potong teks setelah koma sebelum kata " ID")
            $nama_bersih = trim(explode(', ID', $nama_mentah)[0]);
            
            // Cek apakah User PPP ini ada di database CS Monitor
            $stmt_cek = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
            $stmt_cek->execute([$user_ppp]);
            
            if ($stmt_cek->fetch()) {
                // Update Nama yang bersih dan masukkan ID Billing
                $stmt_update = $pdo->prepare("UPDATE pelanggan SET nama = ?, id_billing = ? WHERE id_pelanggan = ?");
                $stmt_update->execute([$nama_bersih, $va_id, $user_ppp]);
                $berhasil_update++;
            }
        }
        fclose($handle);
        $pesan = "<div style='padding: 15px; background: #27ae60; color: white; border-radius: 8px; margin-bottom: 20px;'>✅ Berhasil memperbarui dan merapikan data $berhasil_update pelanggan!</div>";
    } else {
        $pesan = "<div style='padding: 15px; background: #e74c3c; color: white; border-radius: 8px; margin-bottom: 20px;'>❌ Gagal membaca file CSV. Pastikan formatnya benar.</div>";
    }
}
?>

<div style="max-width: 600px; margin: 20px auto; background: var(--bg-card); padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px var(--shadow-color); color: var(--text-primary);">
    <h2 style="margin-top: 0; border-bottom: none; display: flex; align-items: center; gap: 10px;">
        📂 Import Data CSV Pelanggan
    </h2>
    <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 25px; line-height: 1.6;">
        Unggah file CSV dari web perusahaan. Sistem akan otomatis membersihkan tulisan ID pada Nama dan mengaitkan ID Pelanggan (VA) ke akun WinBox yang sesuai.
    </p>
    
    <?php echo $pesan; ?>
    
    <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 15px;">
        <div style="border: 2px dashed var(--border-color); padding: 20px; border-radius: 8px; text-align: center;">
            <input type="file" name="file_csv" accept=".csv" required style="width: 100%; color: var(--text-primary);">
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
            <a href="?page=dashboard" class="btn" style="background: var(--text-secondary); color: white; text-decoration: none;">
                ◀ Kembali
            </a>
            <button type="submit" name="upload_csv" class="btn btn-primary" style="font-weight: bold;">
                🚀 Proses Import CSV
            </button>
        </div>
    </form>
</div>