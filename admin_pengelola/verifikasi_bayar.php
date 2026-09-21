<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_pengelola
requireRole('admin_pengelola');

// Pastikan hanya memproses request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daftar_pesanan.php');
    exit();
}

$penyewaan_id = isset($_POST['penyewaan_id']) ? (int)$_POST['penyewaan_id'] : 0;

if ($penyewaan_id <= 0) {
    $_SESSION['error'] = 'ID Pesanan tidak valid.';
    header('Location: daftar_pesanan.php');
    exit();
}

try {
    // 1. Ambil data sewa & validasi status pembayaran harus 'menunggu_verifikasi'
    $stmt = $pdo->prepare("SELECT status_pembayaran, status FROM penyewaan WHERE id = ? FOR UPDATE");
    $stmt->execute([$penyewaan_id]);
    $penyewaan = $stmt->fetch();

    if (!$penyewaan) {
        $_SESSION['error'] = 'Pesanan sewa tidak ditemukan.';
        header('Location: daftar_pesanan.php');
        exit();
    }

    if ($penyewaan['status_pembayaran'] !== 'menunggu_verifikasi') {
        $_SESSION['error'] = 'Transaksi ini tidak membutuhkan verifikasi pembayaran saat ini.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 2. Mulai DB Transaction
    $pdo->beginTransaction();

    // Update status sewa ke 'aktif' dan status pembayaran ke 'lunas'
    $stmtUpdate = $pdo->prepare("
        UPDATE penyewaan 
        SET status = 'aktif', status_pembayaran = 'lunas' 
        WHERE id = ?
    ");
    $stmtUpdate->execute([$penyewaan_id]);

    // Reduksi stok peralatan & catat riwayat log
    // kurangiStok menggunakan pdo transaction yang sama
    kurangiStok($penyewaan_id, $pdo);

    // Commit jika semua sukses
    $pdo->commit();

    $_SESSION['success'] = 'Pembayaran berhasil diverifikasi! Transaksi sewa kini AKTIF dan stok barang sudah disesuaikan.';
    header("Location: detail_pesanan.php?id=" . $penyewaan_id);
    exit();

} catch (Exception $e) {
    // Rollback jika terjadi kegagalan (misalnya stok tidak mencukupi)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = 'Verifikasi pembayaran gagal: ' . $e->getMessage();
    header("Location: detail_pesanan.php?id=" . $penyewaan_id);
    exit();
}
