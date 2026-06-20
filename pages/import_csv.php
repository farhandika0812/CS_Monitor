<?php
// pages/import_csv.php
session_start();

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
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
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
            // Index array dimulai dari 0
            $va_id       = $data[1];  // Kolom VA (ID Billing: 988636630-...)
            $nama_mentah = $data[2];  // Kolom Nama (Ada tempelan ID-nya)
            $user_ppp    = $data[30]; // Kolom User PPP (Untuk mencocokkan ke Winbox)
            
            // Jika User PPP kosong di CSV, lewati
            if (empty(trim($user_ppp))) continue;

            // Bersihkan nama (Potong teks setelah koma sebelum kata " ID")
            // Contoh: "APRILIANA WINDA ARISONA, ID : 988..." menjadi "APRILIANA WINDA ARISONA"
            $nama_bersih = trim(explode(', ID', $nama_mentah)[0]);
            
            // Cek apakah User PPP ini ada di database CS Monitor
            $stmt_cek = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
            $stmt_cek->execute([$user_ppp]);
            
            // Jika ketemu, update Nama yang bersih dan masukkan ID Billing-nya
            if ($stmt_cek->fetch()) {
                $stmt_update = $pdo->prepare("UPDATE pelanggan SET nama = ?, id_billing = ? WHERE id_pelanggan = ?");
                $stmt_update->execute([$nama_bersih, $va_id, $user_ppp]);
                $berhasil_update++;
            }
        }
        fclose($handle);
        $pesan = "<div style='padding: 15px; background: #27ae60; color: white; border-radius: 8px; margin-bottom: 20px;'>✅ Berhasil memperbarui data $berhasil_update pelanggan!</div>";
    } else {
        $pesan = "<div style='padding: 15px; background: #e74c3c; color: white; border-radius: 8px; margin-bottom: 20px;'>❌ Gagal membaca file CSV.</div>";
    }
}
?>

<div style="max-width: 600px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); font-family: sans-serif;">
    <h2 style="margin-top: 0; color: #2c3e50;">📂 Import Data CSV Pelanggan</h2>
    <p style="color: #7f8c8d; font-size: 14px; margin-bottom: 25px;">
        Unggah file CSV dari web perusahaan. Sistem akan otomatis membersihkan nama dan mengaitkan ID Pelanggan (VA) ke akun WinBox yang sesuai.
    </p>
    
    <?php echo $pesan; ?>
    
    <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 15px;">
        <input type="file" name="file_csv" accept=".csv" required style="padding: 10px; border: 2px dashed #bdc3c7; border-radius: 8px; cursor: pointer;">
        
        <button type="submit" name="upload_csv" style="background: #3498db; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;">
            🚀 Proses Import CSV
        </button>
    </form>
</div>