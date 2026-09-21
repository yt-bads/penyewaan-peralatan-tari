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

// Tangkap flash error dari session
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Tangkap error dari query string
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'mismatch') {
        $error_msg = 'Password dan konfirmasi password tidak cocok.';
    } elseif ($_GET['error'] === 'exists') {
        $error_msg = 'Username sudah terdaftar di sistem.';
    } elseif ($_GET['error'] === 'empty') {
        $error_msg = 'Semua field wajib diisi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Baru - Sistem Informasi Penyewaan Peralatan Tari</title>
    <meta name="description" content="Pendaftaran akun pelanggan baru penyewaan peralatan tari.">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container" style="max-width: 500px;">
            <div class="auth-card">
                <div class="auth-logo">
                    <h2>Daftar Akun</h2>
                    <p>Registrasi pelanggan baru</p>
                </div>

                <?php if ($error_msg): ?>
                    <div class="alert alert-danger" id="register-error-alert">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span><?= htmlspecialchars($error_msg) ?></span>
                    </div>
                <?php endif; ?>

                <form action="proses_register.php" method="POST" id="register-form">
                    <div class="form-group">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" id="nama" class="form-control" placeholder="Masukkan nama lengkap" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Pilih username" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Buat password" required>
                        </div>
                        <div class="form-group">
                            <label for="konfirmasi_password" class="form-label">Konfirmasi</label>
                            <input type="password" name="konfirmasi_password" id="konfirmasi_password" class="form-control" placeholder="Ulangi password" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="no_telp" class="form-label">Nomor Telepon</label>
                        <input type="tel" name="no_telp" id="no_telp" class="form-control" placeholder="Contoh: 08123456789" required>
                    </div>

                    <div class="form-group">
                        <label for="alamat" class="form-label">Alamat Lengkap</label>
                        <textarea name="alamat" id="alamat" class="form-control" placeholder="Masukkan alamat pengiriman & penjamin" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Daftar Akun Baru</button>
                </form>

                <div class="auth-footer">
                    Sudah memiliki akun? <a href="login.php">Masuk disini</a>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/script.js"></script>
</body>
</html>
