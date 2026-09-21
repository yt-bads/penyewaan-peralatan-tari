<?php
// Database configuration.
// Values can be supplied through environment variables for production deployments.
// XAMPP/local development keeps the existing defaults for convenience.

$host   = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'penyewaan_tari';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Log the technical error, but do not expose database details to users.
    error_log('Database connection failed: ' . $e->getMessage());
    die('Koneksi database gagal. Silakan periksa konfigurasi aplikasi.');
}
