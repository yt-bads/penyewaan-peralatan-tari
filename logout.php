<?php
session_start();

// Hapus semua data session
$_SESSION = [];

// Hancurkan session cookie jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session di server
session_destroy();

// Mulai session baru hanya untuk mengirimkan flash message sukses logout
session_start();
$_SESSION['success'] = "Anda telah berhasil keluar dari sistem.";

header('Location: /penyewaan-peralatan-tari/login.php');
exit();
