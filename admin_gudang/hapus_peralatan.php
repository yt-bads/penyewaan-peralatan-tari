<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Validasi role admin_gudang
requireRole('admin_gudang');

// Pastikan hanya memproses request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daftar_peralatan.php');
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = 'ID Peralatan tidak valid.';
    header('Location: daftar_peralatan.php');
    exit();
}

try {
    // Jalankan query delete
    $stmt = $pdo->prepare("DELETE FROM peralatan WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = 'Peralatan berhasil dihapus.';
    } else {
        $_SESSION['error'] = 'Peralatan tidak ditemukan atau sudah dihapus sebelumnya.';
    }
} catch (PDOException $e) {
    // Tangani jika data masih terikat dengan transaksi sewa (Integrity Constraint Violation)
    if ($e->getCode() == '23000') {
        $_SESSION['error'] = 'Gagal menghapus! Peralatan ini sedang digunakan dalam transaksi penyewaan atau log riwayat stok.';
    } else {
        $_SESSION['error'] = 'Gagal menghapus peralatan karena kesalahan sistem: ' . $e->getMessage();
    }
}

header('Location: daftar_peralatan.php');
exit();
