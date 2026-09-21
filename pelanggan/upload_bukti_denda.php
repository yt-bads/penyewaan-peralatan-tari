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
    // 1. Ambil data penyewaan dan pengembalian
    $stmt = $pdo->prepare("
        SELECT p.id, p.user_id, p.status, pen.status_denda, pen.id AS pengembalian_id
        FROM penyewaan p
        LEFT JOIN pengembalian pen ON p.id = pen.penyewaan_id
        WHERE p.id = ?
    ");
    $stmt->execute([$penyewaan_id]);
    $data = $stmt->fetch();

    if (!$data) {
        $_SESSION['error'] = 'Transaksi penyewaan tidak ditemukan.';
        header('Location: pesanan_saya.php');
        exit();
    }

    // 2. Validasi: penyewaan.user_id HARUS = $_SESSION['user_id']
    if ((int)$data['user_id'] !== (int)$user['id']) {
        header('Location: /penyewaan-peralatan-tari/403.php');
        exit();
    }

    // 3. Validasi: penyewaan.status HARUS 'terlambat'
    if ($data['status'] !== 'terlambat') {
        $_SESSION['error'] = 'Pembayaran denda hanya berlaku untuk transaksi dengan status terlambat.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 4. Validasi: status_denda di tabel pengembalian HARUS 'belum_bayar'
    if (!$data['pengembalian_id'] || $data['status_denda'] !== 'belum_bayar') {
        $_SESSION['error'] = 'Denda untuk transaksi ini tidak membutuhkan pembayaran atau sudah lunas.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 5. Validasi file: tipe (jpg/jpeg/png/pdf) dan ukuran (maks 2MB)
    if (!isset($_FILES['bukti_denda']) || $_FILES['bukti_denda']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Terjadi kesalahan saat mengunggah file bukti pembayaran denda.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    $file = $_FILES['bukti_denda'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($extension, $allowed_extensions)) {
        $_SESSION['error'] = 'Format file tidak didukung. Harap upload file gambar (JPG, JPEG, PNG) atau PDF.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = 'Ukuran file terlalu besar. Maksimal ukuran file adalah 2 MB.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 6. Rename file: uniqid('denda_', true) + ekstensi
    $new_filename = uniqid('denda_', true) . '.' . $extension;
    $target_dir = '../uploads/bukti_denda/';

    // 7. Pindahkan ke uploads/bukti_denda/
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $target_path = $target_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $db_path = 'uploads/bukti_denda/' . $new_filename;

        // 8. UPDATE pengembalian SET bukti_bayar_denda = [nama_file] WHERE penyewaan_id = [id]
        $stmtUpdate = $pdo->prepare("
            UPDATE pengembalian 
            SET bukti_bayar_denda = ? 
            WHERE penyewaan_id = ?
        ");
        $stmtUpdate->execute([$db_path, $penyewaan_id]);

        // 9. Redirect ke detail_pesanan.php?id=[id] dengan flash sukses
        $_SESSION['success'] = 'Bukti pembayaran denda berhasil diunggah! Menunggu verifikasi admin.';
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
    $_SESSION['error'] = 'Kesalahan database saat menyimpan bukti pembayaran denda.';
    header("Location: detail_pesanan.php?id=" . $penyewaan_id);
    exit();
}
