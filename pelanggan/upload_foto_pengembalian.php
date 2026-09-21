<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Proteksi role pelanggan
requireRole('pelanggan');
$user = getCurrentUser();

// Pastikan request via method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan_saya.php');
    exit();
}

$penyewaan_id = isset($_POST['penyewaan_id']) ? (int)$_POST['penyewaan_id'] : 0;
if ($penyewaan_id <= 0) {
    $_SESSION['error'] = 'ID Pesanan tidak valid.';
    header('Location: pesanan_saya.php');
    exit();
}

try {
    // 1. Ambil data penyewaan
    $stmt = $pdo->prepare("SELECT id, user_id, status, foto_konfirmasi_pelanggan FROM penyewaan WHERE id = ?");
    $stmt->execute([$penyewaan_id]);
    $penyewaan = $stmt->fetch();

    if (!$penyewaan) {
        $_SESSION['error'] = 'Transaksi penyewaan tidak ditemukan.';
        header('Location: pesanan_saya.php');
        exit();
    }

    // 2. Validasi: penyewaan.user_id HARUS = $_SESSION['user_id']
    if ((int)$penyewaan['user_id'] !== (int)$user['id']) {
        header('Location: /penyewaan-peralatan-tari/403.php');
        exit();
    }

    // 3. Validasi: penyewaan.status HARUS 'aktif'
    if ($penyewaan['status'] !== 'aktif') {
        $_SESSION['error'] = 'Konfirmasi pengembalian hanya dapat dilakukan untuk pesanan yang berstatus aktif.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 4. Validasi: foto_konfirmasi_pelanggan HARUS NULL (tidak boleh upload dua kali)
    if (!empty($penyewaan['foto_konfirmasi_pelanggan'])) {
        $_SESSION['error'] = 'Foto konfirmasi pengembalian sudah pernah diunggah sebelumnya.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 5. Validasi file: upload error, tipe (jpg/jpeg/png), dan ukuran (maks 2MB)
    if (!isset($_FILES['foto_pengembalian']) || $_FILES['foto_pengembalian']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Terjadi kesalahan saat mengunggah foto pengembalian.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    $file = $_FILES['foto_pengembalian'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png'];

    if (!in_array($extension, $allowed_extensions)) {
        $_SESSION['error'] = 'Format file tidak didukung. Harap upload file gambar (JPG, JPEG, PNG).';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = 'Ukuran file terlalu besar. Maksimal ukuran file adalah 2 MB.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 6. Rename file: uniqid('kembali_', true) + ekstensi
    $new_filename = uniqid('kembali_', true) . '.' . $extension;
    $target_dir = '../uploads/foto_pengembalian/';

    // 7. Pindahkan ke uploads/foto_pengembalian/
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $target_path = $target_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $db_path = 'uploads/foto_pengembalian/' . $new_filename;

        // 8. UPDATE penyewaan SET foto_konfirmasi_pelanggan = [nama_file] WHERE id = [id] AND user_id = $_SESSION['user_id']
        $stmtUpdate = $pdo->prepare("
            UPDATE penyewaan 
            SET foto_konfirmasi_pelanggan = ? 
            WHERE id = ? AND user_id = ?
        ");
        $stmtUpdate->execute([$db_path, $penyewaan_id, $user['id']]);

        // 9. Redirect ke detail_pesanan.php?id=[id] dengan flash sukses
        $_SESSION['success'] = 'Foto konfirmasi pengembalian berhasil diunggah! Admin gudang akan memeriksa dan mencatat pengembalian.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    } else {
        $_SESSION['error'] = 'Gagal menyimpan berkas di server.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

} catch (PDOException $e) {
    if (isset($target_path) && file_exists($target_path)) {
        unlink($target_path);
    }
    $_SESSION['error'] = 'Kesalahan database saat menyimpan foto pengembalian.';
    header("Location: detail_pesanan.php?id=" . $penyewaan_id);
    exit();
}
