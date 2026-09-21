<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_pengelola
requireRole('admin_pengelola');

// Pastikan hanya request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daftar_pesanan.php');
    exit();
}

$penyewaan_id = isset($_POST['penyewaan_id']) ? (int)$_POST['penyewaan_id'] : 0;
$status_baru  = isset($_POST['status_baru']) ? trim($_POST['status_baru']) : '';

if ($penyewaan_id <= 0 || $status_baru === '') {
    $_SESSION['error'] = 'Parameter tidak lengkap.';
    header('Location: daftar_pesanan.php');
    exit();
}

try {
    // Ambil data transaksi sewa saat ini
    $stmt = $pdo->prepare("SELECT status, status_pembayaran FROM penyewaan WHERE id = ?");
    $stmt->execute([$penyewaan_id]);
    $penyewaan = $stmt->fetch();

    if (!$penyewaan) {
        $_SESSION['error'] = 'Pesanan sewa tidak ditemukan.';
        header('Location: daftar_pesanan.php');
        exit();
    }

    $current_status = $penyewaan['status'];

    // Jika status baru sama dengan status saat ini, tidak perlu diproses
    if ($status_baru === $current_status) {
        $_SESSION['success'] = 'Status berhasil diperbarui (tidak ada perubahan).';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // Pendefinisian tingkat prioritas status untuk mencegah transisi mundur
    $status_priorities = [
        'menunggu' => 1,
        'aktif' => 2,
        'selesai' => 3,
        'terlambat' => 3
    ];

    if (!isset($status_priorities[$status_baru])) {
        $_SESSION['error'] = 'Status baru tidak valid.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 1. Cegah perubahan status mundur (misal aktif -> menunggu)
    if ($status_priorities[$status_baru] < $status_priorities[$current_status]) {
        $_SESSION['error'] = "Ubah status gagal! Tidak boleh mengubah status mundur (dari '{$current_status}' ke '{$status_baru}').";
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 2. Cegah perubahan status dari final state (selesai/terlambat)
    if ($current_status === 'selesai' || $current_status === 'terlambat') {
        $_SESSION['error'] = 'Ubah status gagal! Transaksi yang sudah selesai atau terlambat tidak dapat diubah statusnya lagi.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 3. Cegah melompati status dari 'menunggu' langsung ke 'selesai'/'terlambat'
    if ($current_status === 'menunggu' && ($status_baru === 'selesai' || $status_baru === 'terlambat')) {
        $_SESSION['error'] = 'Ubah status gagal! Transaksi sewa harus diaktifkan terlebih dahulu.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 4. Cegah mengubah status ke 'aktif' secara manual jika pembayaran belum lunas
    if ($current_status === 'menunggu' && $status_baru === 'aktif') {
        if ($penyewaan['status_pembayaran'] !== 'lunas') {
            $_SESSION['error'] = 'Ubah status gagal! Silakan lakukan verifikasi bukti transfer terlebih dahulu untuk mengaktifkan sewa.';
            header("Location: detail_pesanan.php?id=" . $penyewaan_id);
            exit();
        }
    }

    // Mulai Database Transaction
    $pdo->beginTransaction();

    // Jika beralih dari 'menunggu' ke 'aktif', kurangi stok peralatan dan catat log
    if ($current_status === 'menunggu' && $status_baru === 'aktif') {
        kurangiStok($penyewaan_id, $pdo);
    }

    // Jalankan UPDATE status
    $stmtUpdate = $pdo->prepare("UPDATE penyewaan SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$status_baru, $penyewaan_id]);

    $pdo->commit();

    $_SESSION['success'] = "Status sewa berhasil diubah menjadi '" . strtoupper($status_baru) . "'.";
    header("Location: detail_pesanan.php?id=" . $penyewaan_id);
    exit();

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = 'Gagal memperbarui status sewa: ' . $e->getMessage();
    header("Location: detail_pesanan.php?id=" . $penyewaan_id);
    exit();
}
