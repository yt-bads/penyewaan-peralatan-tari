<?php
session_start();
require_once 'includes/auth.php';

$redirect_url = '/penyewaan-peralatan-tari/login.php';
$button_label = 'Kembali ke Login';

if (isLoggedIn()) {
    $user = getCurrentUser();
    $button_label = 'Kembali ke Dashboard';
    switch ($user['role']) {
        case 'pelanggan':
            $redirect_url = '/penyewaan-peralatan-tari/pelanggan/dashboard.php';
            break;
        case 'admin_pengelola':
            $redirect_url = '/penyewaan-peralatan-tari/admin_pengelola/dashboard.php';
            break;
        case 'admin_gudang':
            $redirect_url = '/penyewaan-peralatan-tari/admin_gudang/dashboard.php';
            break;
        case 'ketua_sanggar':
            $redirect_url = '/penyewaan-peralatan-tari/ketua_sanggar/dashboard.php';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak (403) - Sistem Informasi Penyewaan Peralatan Tari</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .error-card {
            text-align: center;
            max-width: 450px;
            width: 100%;
            margin: auto;
        }
        .error-code {
            font-size: 5rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #ef4444 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .error-description {
            color: #64748b;
            margin-bottom: 2rem;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="error-card auth-card">
            <div class="error-code">403</div>
            <h2 style="margin-bottom: 0.5rem; font-weight: 700;">Akses Ditolak!</h2>
            <p class="error-description">
                Maaf, Anda tidak memiliki izin/hak akses untuk melihat halaman ini. Silakan kembali ke halaman yang sesuai dengan wewenang Anda.
            </p>
            <a href="<?= htmlspecialchars($redirect_url) ?>" class="btn btn-primary"><?= htmlspecialchars($button_label) ?></a>
        </div>
    </div>
</body>
</html>