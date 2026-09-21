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
    // 1. Ambil data penyewaan & pengembalian
    $stmt = $pdo->prepare("
        SELECT p.id, p.status, pen.status_denda, pen.id AS pengembalian_id
        FROM penyewaan p
        JOIN pengembalian pen ON p.id = pen.penyewaan_id
        WHERE p.id = ?
    ");
    $stmt->execute([$penyewaan_id]);
    $data = $stmt->fetch();

    if (!$data) {
        $_SESSION['error'] = 'Data transaksi sewa atau data pengembalian tidak ditemukan.';
        header('Location: daftar_pesanan.php');
        exit();
    }

    // 2. Validasi: penyewaan.status HARUS 'terlambat'
    if ($data['status'] !== 'terlambat') {
        $_SESSION['error'] = 'Verifikasi denda hanya dapat dilakukan untuk transaksi dengan status terlambat.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 3. Validasi: status_denda HARUS 'belum_bayar'
    if ($data['status_denda'] !== 'belum_bayar') {
        $_SESSION['error'] = 'Denda untuk transaksi ini sudah berstatus lunas.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();
    }

    // 4. UPDATE pengembalian SET status_denda = 'lunas' WHERE penyewaan_id = [id]
    $stmtUpdate = $pdo->prepare("
        UPDATE pengembalian 
        SET status_denda = 'lunas' 
        WHERE penyewaan_id = ?
    ");
    $stmtUpdate->execute([$penyewaan_id]);

    // 5. Redirect ke detail_pesanan.php?id=[id] dengan flash sukses
    $_SESSION['success'] = 'Pembayaran denda berhasil diverifikasi.';
    header("Location: detail_pesanan.php?id=" . $penyewaan_id);
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = 'Kesalahan database saat memverifikasi denda: ' . $e->getMessage();
    header("Location: detail_pesanan.php?id=" . $penyewaan_id);
    exit();
}
