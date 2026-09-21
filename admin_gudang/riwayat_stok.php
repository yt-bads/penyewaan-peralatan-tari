<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_gudang
requireRole('admin_gudang');
$user = getCurrentUser();

// Ambil parameter filter GET
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$jenis_filter = isset($_GET['jenis']) ? trim($_GET['jenis']) : '';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? trim($_GET['tanggal_mulai']) : '';
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : '';

// Bangun WHERE clause
$where = "WHERE 1=1";
$params = [];

if ($keyword !== '') {
    $where .= " AND peralatan.nama_alat LIKE ?";
    $params[] = "%" . $keyword . "%";
}

if ($jenis_filter === 'keluar' || $jenis_filter === 'masuk') {
    $where .= " AND riwayat_stok.jenis = ?";
    $params[] = $jenis_filter;
}

if ($tanggal_mulai !== '') {
    $where .= " AND DATE(riwayat_stok.dicatat_pada) >= ?";
    $params[] = $tanggal_mulai;
}

if ($tanggal_akhir !== '') {
    $where .= " AND DATE(riwayat_stok.dicatat_pada) <= ?";
    $params[] = $tanggal_akhir;
}

// 1. Hitung Ringkasan (Total Keluar / Masuk berdasarkan filter aktif)
$summaryQuery = "
    SELECT 
        SUM(CASE WHEN riwayat_stok.jenis = 'keluar' THEN riwayat_stok.jumlah ELSE 0 END) AS total_keluar,
        SUM(CASE WHEN riwayat_stok.jenis = 'masuk' THEN riwayat_stok.jumlah ELSE 0 END) AS total_masuk
    FROM riwayat_stok
    JOIN peralatan ON riwayat_stok.peralatan_id = peralatan.id
    JOIN penyewaan ON riwayat_stok.penyewaan_id = penyewaan.id
    JOIN users ON penyewaan.user_id = users.id
    $where
";
try {
    $stmtSummary = $pdo->prepare($summaryQuery);
    $stmtSummary->execute($params);
    $summary = $stmtSummary->fetch();
    $totalKeluar = (int)($summary['total_keluar'] ?? 0);
    $totalMasuk = (int)($summary['total_masuk'] ?? 0);
} catch (PDOException $e) {
    $totalKeluar = 0;
    $totalMasuk = 0;
}

// 2. Hitung total baris untuk pagination
$countQuery = "
    SELECT COUNT(*) 
    FROM riwayat_stok
    JOIN peralatan ON riwayat_stok.peralatan_id = peralatan.id
    JOIN penyewaan ON riwayat_stok.penyewaan_id = penyewaan.id
    JOIN users ON penyewaan.user_id = users.id
    $where
";
try {
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->execute($params);
    $totalRows = (int)$stmtCount->fetchColumn();
} catch (PDOException $e) {
    $totalRows = 0;
}

// Pengaturan Pagination
$limit = 20;
$totalPages = ceil($totalRows / $limit);
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

// 3. Ambil data dengan limit & offset
$mainQuery = "
    SELECT riwayat_stok.*, peralatan.nama_alat, peralatan.kategori_alat,
           penyewaan.id as no_pesanan,
           users.nama as nama_pelanggan
    FROM riwayat_stok
    JOIN peralatan ON riwayat_stok.peralatan_id = peralatan.id
    JOIN penyewaan ON riwayat_stok.penyewaan_id = penyewaan.id
    JOIN users ON penyewaan.user_id = users.id
    $where
    ORDER BY riwayat_stok.dicatat_pada DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

try {
    $stmtRows = $pdo->prepare($mainQuery);
    $stmtRows->execute($params);
    $rows = $stmtRows->fetchAll();
} catch (PDOException $e) {
    $rows = [];
    $_SESSION['error'] = 'Kesalahan database saat memuat riwayat stok.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Stok Peralatan Tari - Admin Gudang</title>
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

        .summary-card.out { border-left: 4px solid var(--danger); }
        .summary-card.in { border-left: 4px solid var(--success); }

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

        .jenis-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .jenis-keluar { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .jenis-masuk { background: rgba(16, 185, 129, 0.15); color: #22c55e; }

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
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Gudang</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="daftar_peralatan.php" class="nav-link">Data Peralatan</a>
            <a href="riwayat_stok.php" class="nav-link active">Riwayat Stok</a>
            <a href="daftar_pengembalian.php" class="nav-link">Pengembalian</a>
            <a href="laporan_denda.php" class="nav-link">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-admin-gudang">Admin Gudang</span>
            <h1 style="margin-top: 0.5rem;">Riwayat Stok Peralatan</h1>
            <p>Pantau dan filter mutasi keluar-masuk stok peralatan tari sanggar.</p>
        </div>

        <!-- Section Filter -->
        <div class="filter-section">
            <form action="riwayat_stok.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="keyword" class="form-label">Nama Alat</label>
                    <input type="text" name="keyword" id="keyword" class="form-control" placeholder="Cari nama alat..." value="<?= htmlspecialchars($keyword) ?>">
                </div>

                <div class="form-group">
                    <label for="jenis" class="form-label">Jenis Mutasi</label>
                    <select name="jenis" id="jenis" class="form-control">
                        <option value="">Semua</option>
                        <option value="keluar" <?= $jenis_filter === 'keluar' ? 'selected' : '' ?>>Keluar (Disewa)</option>
                        <option value="masuk" <?= $jenis_filter === 'masuk' ? 'selected' : '' ?>>Masuk (Kembali)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" value="<?= htmlspecialchars($tanggal_mulai) ?>">
                </div>

                <div class="form-group">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggal_akhir) ?>">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 0.7rem 1.5rem;">Filter</button>
                    <?php if ($keyword !== '' || $jenis_filter !== '' || $tanggal_mulai !== '' || $tanggal_akhir !== ''): ?>
                        <a href="riwayat_stok.php" class="btn btn-secondary" style="width: auto; padding: 0.7rem 1.5rem; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Banner Ringkasan -->
        <div class="summary-banner">
            <div class="summary-card out">
                <span class="summary-label">Total Keluar</span>
                <span class="summary-value"><?= $totalKeluar ?> unit</span>
            </div>
            <div class="summary-card in">
                <span class="summary-label">Total Masuk</span>
                <span class="summary-value"><?= $totalMasuk ?> unit</span>
            </div>
        </div>

        <!-- Tabel Hasil -->
        <div class="table-section">
            <table class="premium-table" id="tbl-riwayat">
                <thead>
                    <tr>
                        <th style="width: 5%;">No.</th>
                        <th style="width: 18%;">Tanggal & Jam</th>
                        <th>Nama Alat</th>
                        <th style="width: 12%;">Kategori</th>
                        <th style="width: 10%;">Jenis</th>
                        <th style="width: 8%;">Jumlah</th>
                        <th style="width: 12%;">No Pesanan</th>
                        <th style="width: 15%;">Nama Pelanggan</th>
                        <th style="width: 15%;">Keterangan</th>
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
                                <td><?= formatTanggalWaktuIndo($row['dicatat_pada']) ?></td>
                                <td style="font-weight: 600;"><?= htmlspecialchars($row['nama_alat']) ?></td>
                                <td style="text-transform: capitalize;"><?= str_replace('_', ' ', $row['kategori_alat']) ?></td>
                                <td>
                                    <?php if ($row['jenis'] === 'keluar'): ?>
                                        <span class="jenis-badge jenis-keluar">Keluar</span>
                                    <?php else: ?>
                                        <span class="jenis-badge jenis-masuk">Masuk</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 600;"><?= $row['jumlah'] ?> unit</td>
                                <td>
                                    <span style="font-weight: 600;">#<?= $row['no_pesanan'] ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Tidak ada data riwayat stok sesuai kriteria filter.
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
    <script>initPagination(document.getElementById('tbl-riwayat'));</script>
</body>
</html>
