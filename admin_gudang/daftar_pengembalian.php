<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_gudang
requireRole('admin_gudang');
$user = getCurrentUser();

$flash_msg = null;
if (isset($_SESSION['flash'])) {
    $flash_msg = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// 1. Ambil Data SECTION A — Belum Dikembalikan (status = 'aktif' dan belum ada di tabel pengembalian)
$query_unreturned = "
    SELECT p.*, u.nama AS nama_pelanggan, u.username AS username_pelanggan
    FROM penyewaan p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN pengembalian k ON p.id = k.penyewaan_id
    WHERE p.status = 'aktif' AND k.id IS NULL
    ORDER BY p.tanggal_kembali ASC
";

// 2. Ambil Data SECTION B — Riwayat Pengembalian
$query_history = "
    SELECT k.*, p.tanggal_sewa, p.tanggal_kembali, p.total_biaya, u.nama AS nama_pelanggan, ua.nama AS nama_admin
    FROM pengembalian k
    JOIN penyewaan p ON k.penyewaan_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN users ua ON k.dicatat_oleh = ua.id
    ORDER BY k.tanggal_dikembalikan DESC, k.id DESC
";

try {
    $unreturned_list = $pdo->query($query_unreturned)->fetchAll();
    $history_list = $pdo->query($query_history)->fetchAll();
} catch (PDOException $e) {
    $unreturned_list = [];
    $history_list = [];
    $flash_msg = 'Terjadi kesalahan sistem saat memuat data pengembalian: ' . $e->getMessage();
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengembalian & Denda - Admin Gudang</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .section-header {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #ffffff;
            border-left: 4px solid var(--primary);
            padding-left: 0.75rem;
        }

        .table-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            overflow-x: auto;
            margin-bottom: 3rem;
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

        .row-late {
            background: rgba(239, 68, 68, 0.03);
            border-left: 3px solid var(--danger);
        }

        .row-late:hover {
            background: rgba(239, 68, 68, 0.06);
        }

        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-terlambat-label {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            font-weight: 700;
            font-size: 0.75rem;
            padding: 0.15rem 0.5rem;
            border-radius: var(--radius-sm);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-block;
            margin-top: 0.25rem;
        }

        .btn-action {
            padding: 0.45rem 1rem;
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

        .btn-return {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
        }

        .btn-return:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(99, 102, 241, 0.35);
            color: #ffffff;
        }

        .denda-text {
            font-weight: 700;
            color: #ffffff;
        }

        .denda-positive {
            color: #f87171;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Gudang</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="daftar_peralatan.php" class="nav-link">Data Peralatan</a>
            <a href="riwayat_stok.php" class="nav-link">Riwayat Stok</a>
            <a href="daftar_pengembalian.php" class="nav-link active">Pengembalian</a>
            <a href="laporan_denda.php" class="nav-link">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-admin-gudang">Admin Gudang</span>
            <h1 style="margin-top: 0.5rem;">Pengembalian & Denda</h1>
            <p>Catat pengembalian barang sewa dari pelanggan dan tinjau riwayat denda.</p>
        </div>

        <?php if ($flash_msg): ?>
            <div class="alert alert-info" id="flash-alert" style="background-color: var(--surface); border-color: var(--primary);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= htmlspecialchars($flash_msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- SECTION A — Belum Dikembalikan -->
        <h2 class="section-header">Belum Dikembalikan (Sewa Aktif)</h2>
        <div class="table-section">
            <?php if (count($unreturned_list) > 0): ?>
                <table class="premium-table" id="tbl-belum-kembali">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Nama Pelanggan</th>
                            <th>Tanggal Sewa</th>
                            <th>Jatuh Tempo Kembali</th>
                            <th>Total Tagihan</th>
                            <th>Status Jatuh Tempo</th>
                            <th>Konfirmasi Pelanggan</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unreturned_list as $row): ?>
                            <?php 
                                $is_late = $row['tanggal_kembali'] < $today;
                                $row_class = $is_late ? 'row-late' : '';
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td style="font-weight: 600;">#<?= $row['id'] ?></td>
                                <td>
                                    <span style="font-weight: 500; display: block;"><?= htmlspecialchars($row['nama_pelanggan']) ?></span>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">@<?= htmlspecialchars($row['username_pelanggan']) ?></span>
                                </td>
                                <td><?= formatTanggalIndo($row['tanggal_sewa']) ?></td>
                                <td>
                                    <span style="font-weight: 500;"><?= formatTanggalIndo($row['tanggal_kembali']) ?></span>
                                </td>
                                <td><?= formatRupiah($row['total_biaya']) ?></td>
                                <td>
                                    <?php if ($is_late): ?>
                                        <span class="status-terlambat-label">Terlambat</span>
                                    <?php else: ?>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);">Belum Jatuh Tempo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['foto_konfirmasi_pelanggan'])): ?>
                                        <span class="status-badge" style="background: rgba(16, 185, 129, 0.15); color: #22c55e;">📷 Sudah Upload</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="catat_pengembalian.php?id=<?= $row['id'] ?>" class="btn-action btn-return">Catat Pengembalian</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    Tidak ada transaksi sewa aktif yang perlu dikembalikan saat ini.
                </div>
            <?php endif; ?>
        </div>

        <!-- SECTION B — Riwayat Pengembalian -->
        <h2 class="section-header">Riwayat Pengembalian Terakhir</h2>
        <div class="table-section">
            <?php if (count($history_list) > 0): ?>
                <table class="premium-table" id="tbl-riwayat-kembali">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Nama Pelanggan</th>
                            <th>Jatuh Tempo</th>
                            <th>Tgl Dikembalikan</th>
                            <th>Denda</th>
                            <th>Kondisi saat Kembali</th>
                            <th>Dicatat Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_list as $row): ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= $row['penyewaan_id'] ?></td>
                                <td><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_kembali']) ?></td>
                                <td>
                                    <span style="font-weight: 500; color: #ffffff;"><?= formatTanggalIndo($row['tanggal_dikembalikan']) ?></span>
                                </td>
                                <td>
                                    <?php if ($row['denda_keterlambatan'] > 0): ?>
                                        <span class="denda-text denda-positive"><?= formatRupiah($row['denda_keterlambatan']) ?></span>
                                    <?php else: ?>
                                        <span class="denda-text" style="color: var(--text-muted);">Rp 0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.9rem; color: var(--text-muted); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= htmlspecialchars($row['kondisi_peralatan'] ?? '-') ?>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($row['nama_admin']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    Belum ada riwayat pengembalian yang dicatat.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../assets/js/pagination.js"></script>
    <script>
        initPagination(document.getElementById('tbl-belum-kembali'));
        initPagination(document.getElementById('tbl-riwayat-kembali'));
    </script>
</body>
</html>
