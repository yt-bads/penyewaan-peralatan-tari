<?php
session_start();
require_once 'includes/auth.php';

// Jika sudah login, redirect langsung ke dashboard masing-masing role
if (isLoggedIn()) {
    $user = getCurrentUser();
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
    }
    exit();
}

$error_msg = null;
$success_msg = null;

// Tangkap error dari query string
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'unauthorized') {
        $error_msg = 'Anda harus login terlebih dahulu untuk mengakses halaman tersebut.';
    } elseif ($_GET['error'] === 'invalid') {
        $error_msg = 'Username atau password salah.';
    } elseif ($_GET['error'] === 'empty') {
        $error_msg = 'Semua field wajib diisi.';
    }
}

// Tangkap flash messages dari session
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Informasi Penyewaan Peralatan Tari</title>
    <meta name="description" content="Halaman login aplikasi penyewaan peralatan tari sanggar.">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <img src="assets/img/logo.png" alt="Logo" style="max-width: 120px; display: block; margin: 0 auto; margin-bottom: 16px;">
                    <h2>Peralatan Tari</h2>
                    <p>Sistem Informasi Penyewaan</p>
                </div>

                <?php if ($error_msg): ?>
                    <div class="alert alert-danger" id="login-error-alert">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span><?= htmlspecialchars($error_msg) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success" id="login-success-alert">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span><?= htmlspecialchars($success_msg) ?></span>
                    </div>
                <?php endif; ?>

                <form action="proses_login.php" method="POST" id="login-form">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Masuk ke Akun</button>
                </form>

                <div class="auth-footer">
                    Belum memiliki akun? <a href="register.php">Daftar sekarang</a>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/script.js"></script>
</body>
</html>
