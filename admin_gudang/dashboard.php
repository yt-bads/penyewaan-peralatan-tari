<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_gudang
requireRole('admin_gudang');
$user = getCurrentUser();

// 1. Total Jenis Peralatan
$stmtTotalAlat = $pdo->prepare("SELECT COUNT(*) FROM peralatan");
$stmtTotalAlat->execute();
$totalAlat = $stmtTotalAlat->fetchColumn();

// 2. Stok Kritis (stok <= 2)
$stmtStokKritis = $pdo->prepare("SELECT COUNT(*) FROM peralatan WHERE stok <= 2");
$stmtStokKritis->execute();
$stokKritisCount = $stmtStokKritis->fetchColumn();

// 3. Belum Dikembalikan (Penyewaan Aktif yang belum ada record di pengembalian)
$stmtBelumKembali = $pdo->prepare("
    SELECT COUNT(*) 
    FROM penyewaan 
    WHERE status = 'aktif' 
      AND id NOT IN (SELECT penyewaan_id FROM pengembalian)
");
$stmtBelumKembali->execute();
$belumKembaliCount = $stmtBelumKembali->fetchColumn();

// 4. Total Denda Bulan Ini
$stmtTotalDenda = $pdo->prepare("
    SELECT SUM(denda_keterlambatan) 
    FROM pengembalian 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stmtTotalDenda->execute();
$totalDendaBulanIni = (float)$stmtTotalDenda->fetchColumn();

// 5. Daftar peralatan dengan stok kritis (stok <= 2)
$stmtListKritis = $pdo->prepare("
    SELECT * 
    FROM peralatan 
    WHERE stok <= 2 
    ORDER BY stok ASC 
    LIMIT 10
");
$stmtListKritis->execute();
$listKritis = $stmtListKritis->fetchAll();

// 6. Daftar pengembalian mendesak (status aktif, tanggal_kembali < CURDATE(), belum dikembalikan)
$stmtMendesak = $pdo->prepare("
    SELECT p.*, u.nama as nama_pelanggan, DATEDIFF(CURDATE(), p.tanggal_kembali) as keterlambatan_hari
    FROM penyewaan p
    JOIN users u ON p.user_id = u.id
    WHERE p.status = 'aktif' 
      AND p.tanggal_kembali < CURDATE()
      AND p.id NOT IN (SELECT penyewaan_id FROM pengembalian)
    ORDER BY p.tanggal_kembali ASC
");
$stmtMendesak->execute();
$listMendesak = $stmtMendesak->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Gudang - Sistem Informasi Penyewaan Peralatan Tari</title>
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

        .dashboard-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media(min-width: 992px) {
            .dashboard-row {
                grid-template-columns: 1fr 1fr;
            }
        }

        .table-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            overflow-x: auto;
            margin-bottom: 1rem;
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
            margin-bottom: 1rem;
        }

        .section-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .badge-kritis {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            padding: 0.2rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Gudang</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link active">Dashboard</a>
            <a href="daftar_peralatan.php" class="nav-link">Data Peralatan</a>
            <a href="riwayat_stok.php" class="nav-link">Riwayat Stok</a>
            <a href="daftar_pengembalian.php" class="nav-link">Pengembalian</a>
            <a href="laporan_denda.php" class="nav-link">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container" style="max-width: 1300px;">
        <div class="dashboard-header">
            <span class="role-badge badge-admin-gudang">Admin Gudang</span>
            <h1 style="margin-top: 0.5rem;">Dashboard Admin Gudang</h1>
            <p>Kelola inventaris peralatan, stok, dan pencatatan pengembalian di sini.</p>
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
                <span class="stat-label">Total Jenis Peralatan</span>
                <span class="stat-value"><?= $totalAlat ?></span>
            </div>
            <div class="stat-card card-danger">
                <span class="stat-label">Stok Kritis</span>
                <span class="stat-value"><?= $stokKritisCount ?></span>
            </div>
            <div class="stat-card card-warning">
                <span class="stat-label">Belum Dikembalikan</span>
                <span class="stat-value"><?= $belumKembaliCount ?></span>
            </div>
            <div class="stat-card card-success">
                <span class="stat-label">Denda Bulan Ini</span>
                <span class="stat-value"><?= formatRupiah($totalDendaBulanIni) ?></span>
            </div>
        </div>

        <div class="dashboard-row">
            <!-- Kolom Kiri: Peringatan Stok Kritis -->
            <div>
                <div class="section-header">
                    <h2>Stok Kritis (≤ 2)</h2>
                </div>
                <div class="table-section">
                    <table class="premium-table" id="tbl-stok-kritis">
                        <thead>
                            <tr>
                                <th>Nama Alat</th>
                                <th>Kategori</th>
                                <th>Stok</th>
                                <th>Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($listKritis) > 0): ?>
                                <?php foreach ($listKritis as $row): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($row['nama_alat']) ?></td>
                                        <td style="text-transform: capitalize;"><?= str_replace('_', ' ', $row['kategori_alat']) ?></td>
                                        <td><span class="badge-kritis"><?= $row['stok'] ?> unit</span></td>
                                        <td style="text-transform: capitalize;"><?= htmlspecialchars($row['kondisi']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 2.2rem; color: var(--text-muted);">
                                        Semua stok peralatan aman.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kolom Kanan: Pengembalian Mendesak -->
            <div>
                <div class="section-header">
                    <h2>Pengembalian Terlambat</h2>
                </div>
                <div class="table-section">
                    <table class="premium-table" id="tbl-terlambat">
                        <thead>
                            <tr>
                                <th>No. Pesan</th>
                                <th>Pelanggan</th>
                                <th>Batas Kembali</th>
                                <th>Terlambat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($listMendesak) > 0): ?>
                                <?php foreach ($listMendesak as $row): ?>
                                    <tr>
                                        <td style="font-weight: 600;">#<?= $row['id'] ?></td>
                                        <td style="font-weight: 500;"><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                        <td><?= formatTanggalIndo($row['tanggal_kembali']) ?></td>
                                        <td style="color: var(--danger); font-weight: 600;"><?= $row['keterlambatan_hari'] ?> hari</td>
                                        <td>
                                            <a href="catat_pengembalian.php?id=<?= $row['id'] ?>" class="btn btn-primary" style="width: auto; padding: 0.4rem 0.8rem; font-size: 0.85rem; text-decoration: none;">Catat</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2.2rem; color: var(--text-muted);">
                                        Tidak ada pengembalian yang terlambat saat ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/pagination.js"></script>
    <script>
        initPagination(document.getElementById('tbl-stok-kritis'));
        initPagination(document.getElementById('tbl-terlambat'));
    </script>
</body>
</html>
