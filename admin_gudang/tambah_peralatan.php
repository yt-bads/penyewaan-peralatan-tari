<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_gudang
requireRole('admin_gudang');
$user = getCurrentUser();

$error_msg = null;
$old = [];

// Tangkap flash error & data lama dari session
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['old_input'])) {
    $old = $_SESSION['old_input'];
    unset($_SESSION['old_input']);
}

// Proses POST form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_alat     = isset($_POST['nama_alat']) ? trim($_POST['nama_alat']) : '';
    $kategori_alat = isset($_POST['kategori_alat']) ? trim($_POST['kategori_alat']) : '';
    $stok          = isset($_POST['stok']) ? trim($_POST['stok']) : '';
    $harga_sewa    = isset($_POST['harga_sewa']) ? trim($_POST['harga_sewa']) : '';
    $kondisi       = isset($_POST['kondisi']) ? trim($_POST['kondisi']) : 'baik';
    $deskripsi     = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';

    // Simpan input lama jika terjadi kesalahan
    $_SESSION['old_input'] = $_POST;

    // Validasi field kosong
    if ($nama_alat === '' || $kategori_alat === '' || $stok === '' || $harga_sewa === '' || $kondisi === '') {
        $_SESSION['error'] = 'Semua field wajib diisi (kecuali deskripsi).';
        header('Location: tambah_peralatan.php');
        exit();
    }

    // Validasi tipe data stok & non-negatif
    if (!filter_var($stok, FILTER_VALIDATE_INT) && $stok !== '0') {
        $_SESSION['error'] = 'Stok harus berupa bilangan bulat.';
        header('Location: tambah_peralatan.php');
        exit();
    }
    $stok_int = (int)$stok;
    if ($stok_int < 0) {
        $_SESSION['error'] = 'Stok tidak boleh bernilai negatif.';
        header('Location: tambah_peralatan.php');
        exit();
    }

    // Validasi tipe data harga_sewa & non-negatif
    if (!is_numeric($harga_sewa)) {
        $_SESSION['error'] = 'Harga sewa harus berupa angka.';
        header('Location: tambah_peralatan.php');
        exit();
    }
    $harga_float = (float)$harga_sewa;
    if ($harga_float < 0) {
        $_SESSION['error'] = 'Harga sewa tidak boleh bernilai negatif.';
        header('Location: tambah_peralatan.php');
        exit();
    }

    $harga_sewa = (int)$_POST['harga_sewa'];
    if ($harga_sewa < 1000 || $harga_sewa > 10000000) {
        $_SESSION['error'] = 'Harga sewa harus antara Rp 1.000 hingga Rp 10.000.000.';
        header('Location: ' . $_SERVER['PHP_SELF'] .
               (isset($_GET['id']) ? '?id='.$_GET['id'] : ''));
        exit();
    }

    // Validasi enum kategori
    $allowed_kategori = ['kostum', 'aksesoris', 'alat_musik'];
    if (!in_array($kategori_alat, $allowed_kategori)) {
        $_SESSION['error'] = 'Kategori peralatan tidak valid.';
        header('Location: tambah_peralatan.php');
        exit();
    }

    // Validasi enum kondisi
    $allowed_kondisi = ['baik', 'rusak', 'perlu_perbaikan'];
    if (!in_array($kondisi, $allowed_kondisi)) {
        $_SESSION['error'] = 'Kondisi peralatan tidak valid.';
        header('Location: tambah_peralatan.php');
        exit();
    }

    // Logika proses upload foto
    $nama_foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Terjadi kesalahan saat mengunggah file foto.';
            header('Location: tambah_peralatan.php');
            exit();
        }
        
        $file_tmp = $_FILES['foto']['tmp_name'];
        $file_type = $_FILES['foto']['type'];
        $file_size = $_FILES['foto']['size'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        
        if (!in_array($ext, $allowed_exts) || !in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = 'Format file tidak valid. Hanya JPG, JPEG, dan PNG yang diperbolehkan.';
            header('Location: tambah_peralatan.php');
            exit();
        }
        
        if ($file_size > 2097152) {
            $_SESSION['error'] = 'Ukuran file melebihi batas maksimal 2MB.';
            header('Location: tambah_peralatan.php');
            exit();
        }
        
        $nama_foto = uniqid('foto_', true) . '.' . $ext;
        $target_dir = '../uploads/foto_peralatan/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        if (!move_uploaded_file($file_tmp, $target_dir . $nama_foto)) {
            $_SESSION['error'] = 'Gagal memindahkan file foto ke direktori tujuan.';
            header('Location: tambah_peralatan.php');
            exit();
        }
    }

    // Insert ke database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO peralatan (nama_alat, kategori_alat, stok, harga_sewa, kondisi, deskripsi, foto)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nama_alat,
            $kategori_alat,
            $stok_int,
            $harga_float,
            $kondisi,
            $deskripsi === '' ? null : $deskripsi,
            $nama_foto
        ]);

        unset($_SESSION['old_input']);
        $_SESSION['success'] = "Peralatan '{$nama_alat}' berhasil ditambahkan!";
        header('Location: daftar_peralatan.php');
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal menyimpan data karena kesalahan sistem: ' . $e->getMessage();
        header('Location: tambah_peralatan.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Peralatan Baru - Admin Gudang</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            max-width: 650px;
            margin: 0 auto;
            box-shadow: var(--shadow-lg);
        }
        .btn-submit-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Gudang</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="daftar_peralatan.php" class="nav-link active">Data Peralatan</a>
            <a href="riwayat_stok.php" class="nav-link">Riwayat Stok</a>
            <a href="daftar_pengembalian.php" class="nav-link">Pengembalian</a>
            <a href="laporan_denda.php" class="nav-link">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header" style="max-width: 650px; margin: 0 auto 2rem auto;">
            <span class="role-badge badge-admin-gudang">Admin Gudang</span>
            <h1 style="margin-top: 0.5rem;">Tambah Peralatan Baru</h1>
            <p>Masukkan detail perlengkapan tari baru yang akan disewakan.</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger" style="max-width: 650px; margin: 0 auto 1.5rem auto;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form action="tambah_peralatan.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="nama_alat" class="form-label">Nama Peralatan</label>
                    <input type="text" name="nama_alat" id="nama_alat" class="form-control" placeholder="Contoh: Kostum Tari Pendet Bali" value="<?= htmlspecialchars($old['nama_alat'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="kategori_alat" class="form-label">Kategori</label>
                        <select name="kategori_alat" id="kategori_alat" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="kostum" <?= isset($old['kategori_alat']) && $old['kategori_alat'] === 'kostum' ? 'selected' : '' ?>>Kostum</option>
                            <option value="aksesoris" <?= isset($old['kategori_alat']) && $old['kategori_alat'] === 'aksesoris' ? 'selected' : '' ?>>Aksesoris</option>
                            <option value="alat_musik" <?= isset($old['kategori_alat']) && $old['kategori_alat'] === 'alat_musik' ? 'selected' : '' ?>>Alat Musik</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="kondisi" class="form-label">Kondisi</label>
                        <select name="kondisi" id="kondisi" class="form-control" required>
                            <option value="baik" <?= isset($old['kondisi']) && $old['kondisi'] === 'baik' ? 'selected' : '' ?>>Baik</option>
                            <option value="rusak" <?= isset($old['kondisi']) && $old['kondisi'] === 'rusak' ? 'selected' : '' ?>>Rusak</option>
                            <option value="perlu_perbaikan" <?= isset($old['kondisi']) && $old['kondisi'] === 'perlu_perbaikan' ? 'selected' : '' ?>>Perlu Perbaikan</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="stok" class="form-label">Jumlah Stok</label>
                        <input type="number" name="stok" id="stok" class="form-control" min="0" placeholder="0" value="<?= htmlspecialchars($old['stok'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="harga_sewa" class="form-label">Harga Sewa (Rp)</label>
                        <input type="number" name="harga_sewa" id="harga_sewa" class="form-control" min="1000" max="10000000" step="500" placeholder="Nominal tanpa tanda titik" value="<?= htmlspecialchars($old['harga_sewa'] ?? '') ?>" required>
                        <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Harga minimum Rp 1.000 — maksimum Rp 10.000.000</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="foto" class="form-label">Foto Peralatan (Opsional)</label>
                    <input type="file" name="foto" id="foto" class="form-control" accept="image/jpeg,image/png,image/jpg">
                    <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Format: JPG, PNG. Maks 2MB.</small>
                </div>

                <div class="form-group">
                    <label for="deskripsi" class="form-label">Deskripsi / Detail Tambahan (Opsional)</label>
                    <textarea name="deskripsi" id="deskripsi" class="form-control" placeholder="Masukkan spesifikasi produk, kelengkapan, ukuran, atau panduan perawatan..."><?= htmlspecialchars($old['deskripsi'] ?? '') ?></textarea>
                </div>

                <div class="btn-submit-group">
                    <button type="submit" class="btn btn-primary">Simpan Barang</button>
                    <a href="daftar_peralatan.php" class="btn btn-secondary" style="text-decoration: none;">Kembali</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
