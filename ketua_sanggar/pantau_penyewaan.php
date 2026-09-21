<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role ketua_sanggar
requireRole('ketua_sanggar');
$user = getCurrentUser();

// Ambil parameter filter GET
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? trim($_GET['tanggal_mulai']) : '';
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : '';

// Bangun WHERE clause
$where = "WHERE 1=1";
$params = [];

if ($status_filter !== '') {
    $where .= " AND penyewaan.status = ?";
    $params[] = $status_filter;
}

if ($tanggal_mulai !== '') {
    $where .= " AND penyewaan.tanggal_sewa >= ?";
    $params[] = $tanggal_mulai;
}

if ($tanggal_akhir !== '') {
    $where .= " AND penyewaan.tanggal_sewa <= ?";
    $params[] = $tanggal_akhir;
}

// 1. Hitung Ringkasan (Total Transaksi, Total Nilai Sewa, Total Denda berdasarkan filter aktif)
$summaryQuery = "
    SELECT 
        COUNT(*) as total_transaksi,
        SUM(penyewaan.total_biaya) as total_nilai_sewa,
        SUM(pengembalian.denda_keterlambatan) as total_denda
    FROM penyewaan
    JOIN users ON penyewaan.user_id = users.id
    LEFT JOIN pengembalian ON pengembalian.penyewaan_id = penyewaan.id
    $where
";
try {
    $stmtSummary = $pdo->prepare($summaryQuery);
    $stmtSummary->execute($params);
    $summary = $stmtSummary->fetch();
    $totalTransaksi = (int)($summary['total_transaksi'] ?? 0);
    $totalNilaiSewa = (float)($summary['total_nilai_sewa'] ?? 0);
    $totalDenda = (float)($summary['total_denda'] ?? 0);
} catch (PDOException $e) {
    $totalTransaksi = 0;
    $totalNilaiSewa = 0;
    $totalDenda = 0;
}

// Pengaturan Pagination
$limit = 15;
$totalPages = ceil($totalTransaksi / $limit);
if ($totalPages < 1) {
    $totalPages = 1;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;

// 2. Ambil data dengan limit & offset
$mainQuery = "
    SELECT penyewaan.*, users.nama AS nama_pelanggan,
           pengembalian.tanggal_dikembalikan,
           pengembalian.denda_keterlambatan
    FROM penyewaan
    JOIN users ON penyewaan.user_id = users.id
    LEFT JOIN pengembalian ON pengembalian.penyewaan_id = penyewaan.id
    $where
    ORDER BY penyewaan.created_at DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

try {
    $stmtRows = $pdo->prepare($mainQuery);
    $stmtRows->execute($params);
    $rows = $stmtRows->fetchAll();
} catch (PDOException $e) {
    $rows = [];
    $_SESSION['error'] = 'Kesalahan database saat memuat data monitoring transaksi.';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantau Transaksi Penyewaan - Ketua Sanggar</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }

        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-form .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 180px;
        }

        .filter-form .btn-group {
            display: flex;
            gap: 0.5rem;
        }

        .summary-banner {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1rem 1.5rem;
            min-width: 200px;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            box-shadow: var(--shadow-md);
        }

        .summary-card.total { border-left: 4px solid var(--primary); }
        .summary-card.sewa { border-left: 4px solid var(--success); }
        .summary-card.denda { border-left: 4px solid var(--danger); }

        .summary-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
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
            text-transform: capitalize;
        }

        .status-menunggu { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .status-aktif { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
        .status-selesai { background: rgba(16, 185, 129, 0.15); color: #22c55e; }
        .status-terlambat { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .pembayaran-belum_bayar { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .pembayaran-menunggu_verifikasi { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .pembayaran-lunas { background: rgba(16, 185, 129, 0.15); color: #22c55e; }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .page-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text-muted);
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .page-link:hover, .page-link.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Pimpinan</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="pantau_penyewaan.php" class="nav-link active">Pantau Transaksi</a>
            <a href="laporan.php" class="nav-link">Laporan Sewa</a>
            <a href="laporan_denda.php" class="nav-link">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container" style="max-width: 1300px;">
        <div class="dashboard-header">
            <span class="role-badge badge-ketua-sanggar">Ketua Sanggar</span>
            <h1 style="margin-top: 0.5rem;">Pantau Semua Penyewaan</h1>
            <p>Tampilan riwayat transaksi penyewaan sanggar secara real-time (Read-Only).</p>
        </div>

        <!-- Section Filter -->
        <div class="filter-section">
            <form action="pantau_penyewaan.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status" class="form-label">Status Sewa</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="menunggu" <?= $status_filter === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                        <option value="aktif" <?= $status_filter === 'aktif' ? 'selected' : '' ?>>Aktif (Disewa)</option>
                        <option value="selesai" <?= $status_filter === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="terlambat" <?= $status_filter === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tanggal_mulai" class="form-label">Tanggal Mulai Sewa</label>
                    <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" value="<?= htmlspecialchars($tanggal_mulai) ?>">
                </div>

                <div class="form-group">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir Sewa</label>
                    <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggal_akhir) ?>">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 0.7rem 1.5rem;">Filter</button>
                    <?php if ($status_filter !== '' || $tanggal_mulai !== '' || $tanggal_akhir !== ''): ?>
                        <a href="pantau_penyewaan.php" class="btn btn-secondary" style="width: auto; padding: 0.7rem 1.5rem; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Banner Ringkasan -->
        <div class="summary-banner">
            <div class="summary-card total">
                <span class="summary-label">Total Transaksi</span>
                <span class="summary-value"><?= $totalTransaksi ?></span>
            </div>
            <div class="summary-card sewa">
                <span class="summary-label">Total Nilai Sewa</span>
                <span class="summary-value"><?= formatRupiah($totalNilaiSewa) ?></span>
            </div>
            <div class="summary-card denda">
                <span class="summary-label">Total Denda</span>
                <span class="summary-value"><?= formatRupiah($totalDenda) ?></span>
            </div>
        </div>

        <!-- Tabel Monitoring -->
        <div class="table-section">
            <table class="premium-table" id="tbl-pantau">
                <thead>
                    <tr>
                        <th style="width: 5%;">No.</th>
                        <th style="width: 10%;">No. Pesanan</th>
                        <th>Nama Pelanggan</th>
                        <th style="width: 12%;">Tanggal Sewa</th>
                        <th style="width: 12%;">Batas Kembali</th>
                        <th style="width: 10%;">Status Sewa</th>
                        <th style="width: 12%;">Status Bayar</th>
                        <th style="width: 12%;">Total Biaya</th>
                        <th style="width: 10%;">Denda</th>
                        <th style="width: 12%;">Tgl Kembali</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) > 0): ?>
                        <?php 
                            $startNo = $offset + 1;
                            foreach ($rows as $row): 
                        ?>
                            <tr>
                                <td><?= $startNo++ ?>.</td>
                                <td style="font-weight: 600;">#<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></td>
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
                                <td style="font-weight: 600; color: <?= $row['denda_keterlambatan'] > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                                    <?= $row['denda_keterlambatan'] > 0 ? formatRupiah($row['denda_keterlambatan']) : '-' ?>
                                </td>
                                <td><?= $row['tanggal_dikembalikan'] ? formatTanggalIndo($row['tanggal_dikembalikan']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Tidak ada data transaksi penyewaan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Navigasi Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">&laquo; Sebelumnya</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">Berikutnya &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="../assets/js/pagination.js"></script>
    <script>initPagination(document.getElementById('tbl-pantau'));</script>
</body>
</html>
