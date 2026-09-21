<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_pengelola
requireRole('admin_pengelola');
$user = getCurrentUser();

// 1. Hitung Menunggu Verifikasi
$stmtMenunggu = $pdo->prepare("SELECT COUNT(*) FROM penyewaan WHERE status_pembayaran = 'menunggu_verifikasi'");
$stmtMenunggu->execute();
$menungguCount = $stmtMenunggu->fetchColumn();

// 2. Hitung Pesanan Aktif
$stmtAktif = $pdo->prepare("SELECT COUNT(*) FROM penyewaan WHERE status = 'aktif'");
$stmtAktif->execute();
$aktifCount = $stmtAktif->fetchColumn();

// 3. Hitung Selesai Bulan Ini
$stmtSelesaiBulanIni = $pdo->prepare("
    SELECT COUNT(*) 
    FROM penyewaan 
    WHERE status = 'selesai' 
      AND MONTH(updated_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(updated_at) = YEAR(CURRENT_DATE())
");
$stmtSelesaiBulanIni->execute();
$selesaiBulanIni = $stmtSelesaiBulanIni->fetchColumn();

// 4. Hitung Pendapatan Bulan Ini
$stmtPendapatanBulanIni = $pdo->prepare("
    SELECT SUM(total_biaya) 
    FROM penyewaan 
    WHERE status_pembayaran = 'lunas' 
      AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stmtPendapatanBulanIni->execute();
$pendapatanBulanIni = (float)$stmtPendapatanBulanIni->fetchColumn();

// 5. Ambil pesanan yang menunggu verifikasi
$stmtPriority = $pdo->prepare("
    SELECT p.*, u.nama as nama_pelanggan
    FROM penyewaan p
    JOIN users u ON p.user_id = u.id
    WHERE p.status_pembayaran = 'menunggu_verifikasi'
    ORDER BY p.created_at ASC
");
$stmtPriority->execute();
$priorityList = $stmtPriority->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Pengelola - Sistem Informasi Penyewaan Peralatan Tari</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .stat-label {
            font-size: 0.825rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--text);
        }

        .card-primary { border-left: 4px solid var(--primary); }
        .card-success { border-left: 4px solid var(--success); }
        .card-warning { border-left: 4px solid var(--warning); }
        .card-danger { border-left: 4px solid var(--danger); }
        .card-info { border-left: 4px solid var(--info); }

        .table-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            overflow-x: auto;
            margin-bottom: 2rem;
        }

        .premium-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95rem;
        }

        .premium-table th {
            padding: 1rem;
            border-bottom: 2px solid var(--border);
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
        }

        .premium-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .premium-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Admin</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link active">Dashboard</a>
            <a href="daftar_pesanan.php" class="nav-link">Daftar Pesanan</a>
            <a href="laporan.php" class="nav-link">Laporan</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-admin-pengelola">Admin Pengelola</span>
            <h1 style="margin-top: 0.5rem;">Dashboard Admin Pengelola</h1>
            <p>Kelola verifikasi pembayaran dan status sewa di sini.</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= htmlspecialchars($_SESSION['success']) ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Grid Statistik -->
        <div class="stats-grid">
            <div class="stat-card card-warning">
                <span class="stat-label">Menunggu Verifikasi</span>
                <span class="stat-value"><?= $menungguCount ?></span>
            </div>
            <div class="stat-card card-info">
                <span class="stat-label">Pesanan Aktif</span>
                <span class="stat-value"><?= $aktifCount ?></span>
            </div>
            <div class="stat-card card-success">
                <span class="stat-label">Selesai Bulan Ini</span>
                <span class="stat-value"><?= $selesaiBulanIni ?></span>
            </div>
            <div class="stat-card card-primary">
                <span class="stat-label">Pendapatan Bulan Ini</span>
                <span class="stat-value"><?= formatRupiah($pendapatanBulanIni) ?></span>
            </div>
        </div>

        <!-- Tabel Prioritas Verifikasi -->
        <div class="section-header">
            <h2>Prioritas Verifikasi Pembayaran</h2>
        </div>

        <div class="table-section">
            <table class="premium-table" id="tbl-prioritas">
                <thead>
                    <tr>
                        <th style="width: 10%;">No. Pesanan</th>
                        <th>Nama Pelanggan</th>
                        <th>Tanggal Pesan</th>
                        <th>Total Biaya</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($priorityList) > 0): ?>
                        <?php foreach ($priorityList as $row): ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= $row['id'] ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                <td><?= formatTanggalWaktuIndo($row['created_at']) ?></td>
                                <td style="font-weight: 700; color: #ffffff;"><?= formatRupiah($row['total_biaya']) ?></td>
                                <td>
                                    <a href="detail_pesanan.php?id=<?= $row['id'] ?>" class="btn btn-primary" style="width: auto; padding: 0.4rem 1rem; font-size: 0.85rem; text-decoration: none;">Verifikasi</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Tidak ada pesanan yang menunggu verifikasi pembayaran saat ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../assets/js/pagination.js"></script>
    <script>initPagination(document.getElementById('tbl-prioritas'));</script>
</body>
</html>
