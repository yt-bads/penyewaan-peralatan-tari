<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Pastikan hanya request POST yang dapat memproses login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /penyewaan-peralatan-tari/login.php');
    exit();
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validasi input kosong
if (empty($username) || empty($password)) {
    header('Location: /penyewaan-peralatan-tari/login.php?error=empty');
    exit();
}

try {
    // Cari user berdasarkan username menggunakan prepared statement
    $stmt = $pdo->prepare("SELECT id, nama, username, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verifikasi keberadaan user dan kecocokan hash password
    if ($user && password_verify($password, $user['password'])) {
        // Simpan data ke session
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_nama'] = $user['nama'];
        $_SESSION['user_role'] = $user['role'];

        // Buat flash message sukses login
        $_SESSION['success'] = "Selamat datang kembali, " . $user['nama'] . "!";

        // Redirect sesuai role masing-masing
        switch ($user['role']) {
            case 'pelanggan':
                header('Location: /penyewaan-peralatan-tari/pelanggan/dashboard.php');
                break;
            case 'admin_pengelola':
                header('Location: /penyewaan-peralatan-tari/admin_pengelola/dashboard.php');
                break;
            case 'admin_gudang':
                header('Location: /penyewaan-peralatan-tari/admin_gudang/dashboard.php');
                break;
            case 'ketua_sanggar':
                header('Location: /penyewaan-peralatan-tari/ketua_sanggar/dashboard.php');
                break;
            default:
                // Fallback jika ada role tidak terdefinisi
                header('Location: /penyewaan-peralatan-tari/login.php?error=invalid');
                break;
        }
        exit();
    } else {
        // Username tidak ditemukan atau password salah
        header('Location: /penyewaan-peralatan-tari/login.php?error=invalid');
        exit();
    }
} catch (PDOException $e) {
    // Tangani error database
    $_SESSION['error'] = 'Terjadi kesalahan sistem saat mencoba login. Silakan coba lagi.';
    header('Location: /penyewaan-peralatan-tari/login.php');
    exit();
}
