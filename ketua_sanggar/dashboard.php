<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role ketua_sanggar
requireRole('ketua_sanggar');
$user = getCurrentUser();

// 1. Penyewaan Aktif Saat Ini
$stmtAktif = $pdo->prepare("SELECT COUNT(*) FROM penyewaan WHERE status = 'aktif'");
$stmtAktif->execute();
$aktifCount = $stmtAktif->fetchColumn();

// 2. Total Transaksi Bulan Ini
$stmtTotalBulanIni = $pdo->prepare("
    SELECT COUNT(*) 
    FROM penyewaan 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stmtTotalBulanIni->execute();
$totalBulanIni = $stmtTotalBulanIni->fetchColumn();

// 3. Pendapatan Bulan Ini (Lunas)
$stmtPendapatanBulanIni = $pdo->prepare("
    SELECT SUM(total_biaya) 
    FROM penyewaan 
    WHERE status_pembayaran = 'lunas' 
      AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stmtPendapatanBulanIni->execute();
$pendapatanBulanIni = (float)$stmtPendapatanBulanIni->fetchColumn();

// 4. Total Denda Bulan Ini
$stmtDendaBulanIni = $pdo->prepare("
    SELECT SUM(denda_keterlambatan) 
    FROM pengembalian 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stmtDendaBulanIni->execute();
$dendaBulanIni = (float)$stmtDendaBulanIni->fetchColumn();

// 5. Ambil 10 transaksi terbaru dari semua pelanggan
$stmtLatest = $pdo->prepare("
    SELECT p.*, u.nama as nama_pelanggan
    FROM penyewaan p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmtLatest->execute();
$latestTransactions = $stmtLatest->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Ketua Sanggar - Sistem Informasi Penyewaan Peralatan Tari</title>
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
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Pimpinan</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link active">Dashboard</a>
            <a href="pantau_penyewaan.php" class="nav-link">Pantau Transaksi</a>
            <a href="laporan.php" class="nav-link">Laporan Sewa</a>
            <a href="laporan_denda.php" class="nav-link">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-ketua-sanggar">Ketua Sanggar</span>
            <h1 style="margin-top: 0.5rem;">Dashboard Ketua Sanggar</h1>
            <p>Pantau seluruh aktivitas sewa, denda, dan laporan sanggar di sini.</p>
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
            <div class="stat-card card-info">
                <span class="stat-label">Penyewaan Aktif</span>
                <span class="stat-value"><?= $aktifCount ?></span>
            </div>
            <div class="stat-card card-primary">
                <span class="stat-label">Transaksi Bulan Ini</span>
                <span class="stat-value"><?= $totalBulanIni ?></span>
            </div>
            <div class="stat-card card-success">
                <span class="stat-label">Pendapatan Bulan Ini</span>
                <span class="stat-value"><?= formatRupiah($pendapatanBulanIni) ?></span>
            </div>
            <div class="stat-card card-danger">
                <span class="stat-label">Denda Bulan Ini</span>
                <span class="stat-value"><?= formatRupiah($dendaBulanIni) ?></span>
            </div>
        </div>

        <!-- Tabel 10 Transaksi Terbaru -->
        <div class="section-header">
            <h2>10 Transaksi Terbaru</h2>
        </div>

        <div class="table-section">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">No. Pesanan</th>
                        <th>Nama Pelanggan</th>
                        <th>Tanggal Sewa</th>
                        <th>Tanggal Kembali</th>
                        <th>Status Sewa</th>
                        <th>Status Bayar</th>
                        <th>Total Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($latestTransactions) > 0): ?>
                        <?php foreach ($latestTransactions as $row): ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= $row['id'] ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
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
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Belum ada riwayat transaksi penyewaan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
