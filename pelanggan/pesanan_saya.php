<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Proteksi halaman pelanggan
requireRole('pelanggan');
$user = getCurrentUser();

// Ambil parameter filter status sewa
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Query data penyewaan milik user aktif
$query = "SELECT * FROM penyewaan WHERE user_id = ?";
$params = [$user['id']];

if ($status_filter !== '') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    $_SESSION['error'] = 'Gagal mengambil riwayat pesanan sewa.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Sewa Tari</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }

        .filter-tab {
            padding: 0.5rem 1.25rem;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .filter-tab:hover, .filter-tab.active {
            color: #ffffff;
            background: var(--surface-hover);
            border-color: var(--primary);
        }

        .table-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            overflow-x: auto;
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

        /* Status Sewa Badges */
        .status-menunggu { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .status-aktif { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
        .status-selesai { background: rgba(16, 185, 129, 0.15); color: #22c55e; }
        .status-terlambat { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        /* Status Pembayaran Badges */
        .pembayaran-belum_bayar { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .pembayaran-menunggu_verifikasi { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .pembayaran-lunas { background: rgba(16, 185, 129, 0.15); color: #22c55e; }

        .btn-action {
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-detail {
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .btn-detail:hover {
            background: var(--primary);
            color: #ffffff;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="katalog.php" class="nav-link">Katalog</a>
            <a href="pesanan_saya.php" class="nav-link active">Pesanan Saya</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-pelanggan">Pelanggan</span>
            <h1 style="margin-top: 0.5rem;">Riwayat Sewa Saya</h1>
            <p>Pantau dan kelola pesanan sewa peralatan tari Anda.</p>
        </div>

        <!-- Filter Status Sewa -->
        <div class="filter-tabs">
            <a href="pesanan_saya.php" class="filter-tab <?= $status_filter === '' ? 'active' : '' ?>">Semua Transaksi</a>
            <a href="pesanan_saya.php?status=menunggu" class="filter-tab <?= $status_filter === 'menunggu' ? 'active' : '' ?>">Menunggu</a>
            <a href="pesanan_saya.php?status=aktif" class="filter-tab <?= $status_filter === 'aktif' ? 'active' : '' ?>">Aktif (Disewa)</a>
            <a href="pesanan_saya.php?status=selesai" class="filter-tab <?= $status_filter === 'selesai' ? 'active' : '' ?>">Selesai</a>
            <a href="pesanan_saya.php?status=terlambat" class="filter-tab <?= $status_filter === 'terlambat' ? 'active' : '' ?>">Terlambat</a>
        </div>

        <!-- Tabel Riwayat Pesanan -->
        <div class="table-section">
            <?php if (count($orders) > 0): ?>
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Tanggal Sewa</th>
                            <th>Batas Kembali</th>
                            <th>Total Biaya</th>
                            <th>Status Sewa</th>
                            <th>Status Pembayaran</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $row): ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= $row['id'] ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_sewa']) ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_kembali']) ?></td>
                                <td style="font-weight: 700; color: #ffffff;"><?= formatRupiah($row['total_biaya']) ?></td>
                                <td>
                                    <?php 
                                        $statusClass = 'status-menunggu';
                                        $statusText = 'Menunggu';
                                        if ($row['status'] === 'aktif') {
                                            $statusClass = 'status-aktif';
                                            $statusText = 'Aktif (Disewa)';
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
                                            $bayarText = 'Menunggu Verifikasi';
                                        } elseif ($row['status_pembayaran'] === 'lunas') {
                                            $bayarClass = 'pembayaran-lunas';
                                            $bayarText = 'Lunas';
                                        }
                                    ?>
                                    <span class="status-badge <?= $bayarClass ?>"><?= $bayarText ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="detail_pesanan.php?id=<?= $row['id'] ?>" class="btn-action btn-detail">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                    Belum ada riwayat transaksi penyewaan.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
