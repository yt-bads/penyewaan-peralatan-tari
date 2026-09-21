<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_pengelola
requireRole('admin_pengelola');
$user = getCurrentUser();
$userId = $_SESSION['user_id'];

// Proses POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    if ($action === 'edit_profil') {
        $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';

        if ($nama === '') {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Nama lengkap wajib diisi.',
                'form' => 'profile'
            ];
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET nama = ? WHERE id = ?");
                $stmt->execute([$nama, $userId]);
                $_SESSION['user_nama'] = $nama; // Update nama di session
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Profil berhasil diperbarui.',
                    'form' => 'profile'
                ];
            } catch (PDOException $e) {
                $_SESSION['flash'] = [
                    'type' => 'danger',
                    'message' => 'Kesalahan database saat memperbarui profil.',
                    'form' => 'profile'
                ];
            }
        }
        header("Location: profil.php");
        exit();

    } elseif ($action === 'ganti_password') {
        $password_lama = isset($_POST['password_lama']) ? $_POST['password_lama'] : '';
        $password_baru = isset($_POST['password_baru']) ? $_POST['password_baru'] : '';
        $konfirmasi = isset($_POST['konfirmasi_password_baru']) ? $_POST['konfirmasi_password_baru'] : '';

        // Ambil data password lama dari DB
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user_hash = $stmt->fetchColumn();

        if (empty($password_lama) || empty($password_baru) || empty($konfirmasi)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Semua kolom password wajib diisi.',
                'form' => 'password'
            ];
        } elseif (strlen($password_baru) < 6) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Password baru minimal 6 karakter.',
                'form' => 'password'
            ];
        } elseif ($password_baru !== $konfirmasi) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Konfirmasi password baru tidak cocok.',
                'form' => 'password'
            ];
        } elseif (!password_verify($password_lama, $user_hash)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Password lama salah.',
                'form' => 'password'
            ];
        } elseif (password_verify($password_baru, $user_hash)) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Password baru tidak boleh sama dengan password lama.',
                'form' => 'password'
            ];
        } else {
            try {
                $new_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_hash, $userId]);
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Password berhasil diperbarui.',
                    'form' => 'password'
                ];
            } catch (PDOException $e) {
                $_SESSION['flash'] = [
                    'type' => 'danger',
                    'message' => 'Kesalahan database saat memperbarui password.',
                    'form' => 'password'
                ];
            }
        }
        header("Location: profil.php");
        exit();
    }
}

// Ambil data profil terbaru untuk pre-fill
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$profile = $stmtUser->fetch();

// Pecah pesan flash
$flash_profile = null;
$flash_password = null;
if (isset($_SESSION['flash'])) {
    if ($_SESSION['flash']['form'] === 'profile') {
        $flash_profile = $_SESSION['flash'];
    } elseif ($_SESSION['flash']['form'] === 'password') {
        $flash_password = $_SESSION['flash'];
    }
    unset($_SESSION['flash']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Admin Pengelola</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        @media(min-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .profile-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .profile-card h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.75rem;
            color: var(--text);
        }

        .username-display {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            color: var(--text-muted);
            font-family: var(--font-sans);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Admin</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="daftar_pesanan.php" class="nav-link">Daftar Pesanan</a>
            <a href="laporan.php" class="nav-link">Laporan</a>
            <a href="profil.php" class="nav-link active">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-admin-pengelola">Admin Pengelola</span>
            <h1 style="margin-top: 0.5rem;">Profil Saya</h1>
            <p>Kelola informasi akun dan perbarui kata sandi Anda.</p>
        </div>

        <div class="profile-grid">
            <!-- Form 1: Edit Profil -->
            <div class="profile-card">
                <h2>Data Profil</h2>

                <?php if ($flash_profile): ?>
                    <div class="alert alert-<?= $flash_profile['type'] ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <?php if ($flash_profile['type'] === 'success'): ?>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            <?php else: ?>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            <?php endif; ?>
                        </svg>
                        <span><?= htmlspecialchars($flash_profile['message']) ?></span>
                    </div>
                <?php endif; ?>

                <form action="profil.php" method="POST">
                    <input type="hidden" name="action" value="edit_profil">
                    
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <div class="username-display"><?= htmlspecialchars($profile['username']) ?></div>
                    </div>

                    <div class="form-group">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" id="nama" class="form-control" value="<?= htmlspecialchars($profile['nama']) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Simpan Perubahan</button>
                </form>
            </div>

            <!-- Form 2: Ganti Password -->
            <div class="profile-card">
                <h2>Ganti Password</h2>

                <?php if ($flash_password): ?>
                    <div class="alert alert-<?= $flash_password['type'] ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <?php if ($flash_password['type'] === 'success'): ?>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            <?php else: ?>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            <?php endif; ?>
                        </svg>
                        <span><?= htmlspecialchars($flash_password['message']) ?></span>
                    </div>
                <?php endif; ?>

                <form action="profil.php" method="POST">
                    <input type="hidden" name="action" value="ganti_password">

                    <div class="form-group">
                        <label for="password_lama" class="form-label">Password Lama</label>
                        <input type="password" name="password_lama" id="password_lama" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="password_baru" class="form-label">Password Baru (min. 6 karakter)</label>
                        <input type="password" name="password_baru" id="password_baru" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="konfirmasi_password_baru" class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="konfirmasi_password_baru" id="konfirmasi_password_baru" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
