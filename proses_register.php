<?php
session_start();
require_once 'includes/db.php';

// Pastikan hanya request POST yang dapat memproses registrasi
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /penyewaan-peralatan-tari/register.php');
    exit();
}

$nama                = isset($_POST['nama']) ? trim($_POST['nama']) : '';
$username            = isset($_POST['username']) ? trim($_POST['username']) : '';
$password            = isset($_POST['password']) ? $_POST['password'] : '';
$konfirmasi_password = isset($_POST['konfirmasi_password']) ? $_POST['konfirmasi_password'] : '';
$no_telp             = isset($_POST['no_telp']) ? trim($_POST['no_telp']) : '';
$alamat              = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';

// Validasi input kosong
if (empty($nama) || empty($username) || empty($password) || empty($konfirmasi_password) || empty($no_telp) || empty($alamat)) {
    header('Location: /penyewaan-peralatan-tari/register.php?error=empty');
    exit();
}

// Validasi kesamaan password
if ($password !== $konfirmasi_password) {
    header('Location: /penyewaan-peralatan-tari/register.php?error=mismatch');
    exit();
}

try {
    // Periksa apakah username sudah terdaftar
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        header('Location: /penyewaan-peralatan-tari/register.php?error=exists');
        exit();
    }

    // Hash password menggunakan bcrypt aman
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Simpan user baru ke database
    $stmtInsert = $pdo->prepare("
        INSERT INTO users (nama, username, password, role, no_telp, alamat) 
        VALUES (?, ?, ?, 'pelanggan', ?, ?)
    ");
    
    $stmtInsert->execute([
        $nama,
        $username,
        $hashed_password,
        $no_telp,
        $alamat
    ]);

    // Berikan flash message sukses dan redirect ke halaman login
    $_SESSION['success'] = "Pendaftaran berhasil! Silakan masuk dengan akun baru Anda.";
    header('Location: /penyewaan-peralatan-tari/login.php');
    exit();

} catch (PDOException $e) {
    // Tangani error sistem / database
    $_SESSION['error'] = "Pendaftaran gagal karena kesalahan sistem. Silakan coba kembali.";
    header('Location: /penyewaan-peralatan-tari/register.php');
    exit();
}
