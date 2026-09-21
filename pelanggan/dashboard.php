<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role pelanggan
requireRole('pelanggan');
$user = getCurrentUser();
$userId = $user['id'];

// 1. Hitung Total Pesanan Saya
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM penyewaan WHERE user_id = ?");
$stmtTotal->execute([$userId]);
$totalPesanan = $stmtTotal->fetchColumn();

// 2. Hitung Sedang Aktif
$stmtAktif = $pdo->prepare("SELECT COUNT(*) FROM penyewaan WHERE user_id = ? AND status = 'aktif'");
$stmtAktif->execute([$userId]);
$aktifPesanan = $stmtAktif->fetchColumn();

// 3. Hitung Menunggu Verifikasi
$stmtMenunggu = $pdo->prepare("SELECT COUNT(*) FROM penyewaan WHERE user_id = ? AND status_pembayaran = 'menunggu_verifikasi'");
$stmtMenunggu->execute([$userId]);
$menungguPesanan = $stmtMenunggu->fetchColumn();

// 4. Hitung Total Denda
$stmtDenda = $pdo->prepare("
    SELECT SUM(k.denda_keterlambatan) 
    FROM pengembalian k
    JOIN penyewaan p ON k.penyewaan_id = p.id
    WHERE p.user_id = ?
");
$stmtDenda->execute([$userId]);
$totalDenda = (float)$stmtDenda->fetchColumn();

// 5. Ambil 5 pesanan terbaru
$stmtList = $pdo->prepare("
    SELECT p.*, k.denda_keterlambatan
    FROM penyewaan p
    LEFT JOIN pengembalian k ON k.penyewaan_id = p.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmtList->execute([$userId]);
$latestPesanan = $stmtList->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pelanggan - Sistem Informasi Penyewaan Peralatan Tari</title>
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

        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-menunggu { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .status-aktif { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
        .status-selesai { background: rgba(16, 185, 129, 0.15); color: #22c55e; }
        .status-terlambat { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .pembayaran-belum_bayar { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .pembayaran-menunggu_verifikasi { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .pembayaran-lunas { background: rgba(16, 185, 129, 0.15); color: #22c55e; }

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

        .cta-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            margin-bottom: 2rem;
        }

        .btn-inline {
            width: auto;
            padding: 0.6rem 1.25rem;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link active">Dashboard</a>
            <a href="katalog.php" class="nav-link">Katalog</a>
            <a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-pelanggan">Pelanggan</span>
            <h1 style="margin-top: 0.5rem;">Dashboard Pelanggan</h1>
            <p>Kelola sewa peralatan tari Anda di sini.</p>
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
            <div class="stat-card card-primary">
                <span class="stat-label">Total Pesanan Saya</span>
                <span class="stat-value"><?= $totalPesanan ?></span>
            </div>
            <div class="stat-card card-info">
                <span class="stat-label">Sedang Aktif</span>
                <span class="stat-value"><?= $aktifPesanan ?></span>
            </div>
            <div class="stat-card card-warning">
                <span class="stat-label">Menunggu Verifikasi</span>
                <span class="stat-value"><?= $menungguPesanan ?></span>
            </div>
            <div class="stat-card card-danger">
                <span class="stat-label">Total Denda</span>
                <span class="stat-value"><?= formatRupiah($totalDenda) ?></span>
            </div>
        </div>

        <!-- Tombol CTA Cepat -->
        <div class="cta-buttons">
            <a href="katalog.php" class="btn btn-primary btn-inline">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Sewa Sekarang
            </a>
            <a href="pesanan_saya.php" class="btn btn-secondary btn-inline">Lihat Semua Pesanan</a>
        </div>

        <!-- Tabel 5 Transaksi Terbaru -->
        <div class="section-header">
            <h2>5 Pesanan Terbaru</h2>
        </div>

        <div class="table-section">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Tanggal Sewa</th>
                        <th>Tanggal Kembali</th>
                        <th>Status Sewa</th>
                        <th>Status Bayar</th>
                        <th>Total Biaya</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($latestPesanan) > 0): ?>
                        <?php foreach ($latestPesanan as $row): ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= $row['id'] ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_sewa']) ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_kembali']) ?></td>
                                <td>
                                    <?php 
                                        $statusClass = 'status-menunggu';
                                        $statusText = 'Menunggu';
                                        if ($row['status'] === 'aktif') {
                                            $statusClass = 'status-aktif';
                                            $statusText = 'Aktif';
                                        } elseif ($row['status'] === 'selesai') {
                                            $statusClass = 'status-selesai';
                                            $statusText = 'Selesai';
                                        } elseif ($row['status'] === 'terlambat') {
                                            $statusClass = 'status-terlambat';
                                            $statusText = 'Terlambat';
                                        }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $bayarClass = 'pembayaran-belum_bayar';
                                        $bayarText = 'Belum Bayar';
                                        if ($row['status_pembayaran'] === 'menunggu_verifikasi') {
                                            $bayarClass = 'pembayaran-menunggu_verifikasi';
                                            $bayarText = 'Verifikasi';
                                        } elseif ($row['status_pembayaran'] === 'lunas') {
                                            $bayarClass = 'pembayaran-lunas';
                                            $bayarText = 'Lunas';
                                        }
                                    ?>
                                    <span class="status-badge <?= $bayarClass ?>"><?= $bayarText ?></span>
                                </td>
                                <td style="font-weight: 700; color: #ffffff;"><?= formatRupiah($row['total_biaya']) ?></td>
                                <td>
                                    <a href="detail_pesanan.php?id=<?= $row['id'] ?>" class="btn btn-secondary" style="width: auto; padding: 0.4rem 0.8rem; font-size: 0.85rem; text-decoration: none;">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Anda belum pernah memesan peralatan tari.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
