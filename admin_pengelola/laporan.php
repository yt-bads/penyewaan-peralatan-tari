<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_pengelola
requireRole('admin_pengelola');
$user = getCurrentUser();

$tanggal_mulai = isset($_GET['tanggal_mulai']) ? trim($_GET['tanggal_mulai']) : '';
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$kategori_filter = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

// Mapping value dropdown ke enum database
$kategori_map = [
    'Kostum'     => 'kostum',
    'Aksesoris'  => 'aksesoris',
    'Alat Musik' => 'alat_musik',
];
$kategori_db = isset($kategori_map[$kategori_filter]) ? $kategori_map[$kategori_filter] : '';

// Tentukan mode: detail-level jika kategori dipilih, header-level jika tidak
$is_detail_mode = ($kategori_db !== '');

$params = [];

if ($is_detail_mode) {
    // Mode detail-level: JOIN ke detail_penyewaan → peralatan, filter kategori
    $query = "
        SELECT p.id, p.tanggal_sewa, p.tanggal_kembali, p.status, p.status_pembayaran,
               u.nama AS nama_pelanggan,
               alat.nama_alat, alat.kategori_alat, alat.harga_sewa,
               dp.jumlah_pinjam,
               (dp.jumlah_pinjam * alat.harga_sewa) AS subtotal_item
        FROM penyewaan p
        JOIN users u ON p.user_id = u.id
        JOIN detail_penyewaan dp ON dp.penyewaan_id = p.id
        JOIN peralatan alat ON dp.peralatan_id = alat.id
        WHERE alat.kategori_alat = ?
    ";
    $params[] = $kategori_db;

    if ($tanggal_mulai !== '') {
        $query .= " AND p.tanggal_sewa >= ?";
        $params[] = $tanggal_mulai;
    }
    if ($tanggal_akhir !== '') {
        $query .= " AND p.tanggal_sewa <= ?";
        $params[] = $tanggal_akhir;
    }
    if ($status_filter !== '') {
        $query .= " AND p.status = ?";
        $params[] = $status_filter;
    }

    $query .= " ORDER BY p.created_at DESC, alat.nama_alat ASC";
} else {
    // Mode header-level: query existing (per-transaksi)
    $query = "
        SELECT p.*, u.nama as nama_pelanggan, k.denda_keterlambatan
        FROM penyewaan p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN pengembalian k ON k.penyewaan_id = p.id
        WHERE 1=1
    ";

    if ($tanggal_mulai !== '') {
        $query .= " AND p.tanggal_sewa >= ?";
        $params[] = $tanggal_mulai;
    }
    if ($tanggal_akhir !== '') {
        $query .= " AND p.tanggal_sewa <= ?";
        $params[] = $tanggal_akhir;
    }
    if ($status_filter !== '') {
        $query .= " AND p.status = ?";
        $params[] = $status_filter;
    }

    $query .= " ORDER BY p.created_at DESC";
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $report_list = [];
    $_SESSION['error'] = 'Kesalahan database saat memuat data laporan.';
}

// Persiapkan statement untuk mengambil detail item per baris (hanya mode header)
if (!$is_detail_mode) {
    $stmtItems = $pdo->prepare("
        SELECT dp.jumlah_pinjam, p.nama_alat 
        FROM detail_penyewaan dp
        JOIN peralatan p ON dp.peralatan_id = p.id
        WHERE dp.penyewaan_id = ?
    ");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penyewaan - Admin Pengelola</title>
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

        .status-menunggu { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .status-aktif { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
        .status-selesai { background: rgba(16, 185, 129, 0.15); color: #22c55e; }
        .status-terlambat { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .pembayaran-belum_bayar { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .pembayaran-menunggu_verifikasi { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .pembayaran-lunas { background: rgba(16, 185, 129, 0.15); color: #22c55e; }

        .items-summary {
            font-size: 0.85rem;
            color: var(--text-muted);
            max-width: 250px;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Admin</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="daftar_pesanan.php" class="nav-link">Daftar Pesanan</a>
            <a href="laporan.php" class="nav-link active">Laporan</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="header-actions">
            <div class="dashboard-header" style="margin-bottom: 0;">
                <span class="role-badge badge-admin-pengelola">Admin Pengelola</span>
                <h1 style="margin-top: 0.5rem;">Laporan Penyewaan</h1>
                <p>Pantau laporan transaksi penyewaan keseluruhan sanggar tari.</p>
            </div>
            <div>
                <?php 
                    $pdf_url = "export_laporan_pdf.php?" . http_build_query([
                        'tanggal_mulai' => $tanggal_mulai,
                        'tanggal_akhir' => $tanggal_akhir,
                        'status' => $status_filter,
                        'kategori' => $kategori_filter
                    ]);
                ?>
                <a href="<?= htmlspecialchars($pdf_url) ?>" class="btn btn-primary" style="width: auto;">Unduh Laporan (PDF)</a>
            </div>
        </div>

        <!-- Section Filter Laporan -->
        <div class="filter-section">
            <form action="laporan.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" value="<?= htmlspecialchars($tanggal_mulai) ?>">
                </div>

                <div class="form-group">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggal_akhir) ?>">
                </div>

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
                    <label for="kategori" class="form-label">Kategori Peralatan</label>
                    <select name="kategori" id="kategori" class="form-control">
                        <option value="">Semua Kategori</option>
                        <option value="Kostum" <?= $kategori_filter === 'Kostum' ? 'selected' : '' ?>>Kostum</option>
                        <option value="Aksesoris" <?= $kategori_filter === 'Aksesoris' ? 'selected' : '' ?>>Aksesoris</option>
                        <option value="Alat Musik" <?= $kategori_filter === 'Alat Musik' ? 'selected' : '' ?>>Alat Musik</option>
                    </select>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 0.7rem 1.5rem;">Filter</button>
                    <?php if ($tanggal_mulai !== '' || $tanggal_akhir !== '' || $status_filter !== '' || $kategori_filter !== ''): ?>
                        <a href="laporan.php" class="btn btn-secondary" style="width: auto; padding: 0.7rem 1.5rem; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Laporan -->
        <div class="table-section">
            <table class="premium-table" id="tbl-laporan">
                <thead>
                    <tr>
                    <?php if ($is_detail_mode): ?>
                        <th>No. Pesanan</th>
                        <th>Nama Pelanggan</th>
                        <th>Nama Peralatan</th>
                        <th style="text-align: center;">Jumlah</th>
                        <th>Tanggal Sewa</th>
                        <th>Status Sewa</th>
                        <th>Biaya Item</th>
                    <?php else: ?>
                        <th>No. Pesanan</th>
                        <th>Nama Pelanggan</th>
                        <th>Tanggal Sewa</th>
                        <th>Batas Kembali</th>
                        <th>Daftar Peralatan</th>
                        <th>Status Sewa</th>
                        <th>Status Bayar</th>
                        <th>Denda</th>
                        <th>Total Biaya</th>
                    <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $total_pendapatan = 0;

                    if ($is_detail_mode):
                        // === MODE DETAIL-LEVEL (kategori dipilih) ===
                        if (count($report_list) > 0):
                            foreach ($report_list as $row):
                                $subtotal = (float)$row['subtotal_item'];
                                // Akumulasi total hanya jika status pembayaran lunas
                                if ($row['status_pembayaran'] === 'lunas') {
                                    $total_pendapatan += $subtotal;
                                }
                ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= $row['id'] ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                <td><?= htmlspecialchars($row['nama_alat']) ?></td>
                                <td style="text-align: center;"><?= $row['jumlah_pinjam'] ?> unit</td>
                                <td><?= formatTanggalIndo($row['tanggal_sewa']) ?></td>
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
                                <td style="font-weight: 700; color: #ffffff;"><?= formatRupiah($subtotal) ?></td>
                            </tr>
                <?php
                            endforeach;
                        else:
                ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Tidak ada data peralatan kategori <strong><?= htmlspecialchars($kategori_filter) ?></strong> ditemukan.
                            </td>
                        </tr>
                <?php
                        endif;
                    else:
                        // === MODE HEADER-LEVEL (semua kategori) ===
                        if (count($report_list) > 0):
                            foreach ($report_list as $row):
                                // Ambil daftar item sewa
                                $stmtItems->execute([$row['id']]);
                                $row_items = $stmtItems->fetchAll();
                                $item_summaries = [];
                                foreach ($row_items as $item) {
                                    $item_summaries[] = htmlspecialchars($item['nama_alat']) . " (" . $item['jumlah_pinjam'] . ")";
                                }
                                $item_text = implode(', ', $item_summaries);

                                // Akumulasikan total pendapatan jika pembayaran berstatus lunas
                                if ($row['status_pembayaran'] === 'lunas') {
                                    $total_pendapatan += (float)$row['total_biaya'];
                                }
                ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= $row['id'] ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_sewa']) ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_kembali']) ?></td>
                                <td>
                                    <div class="items-summary"><?= $item_text === '' ? '-' : $item_text ?></div>
                                </td>
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
                                <td style="font-weight: 600;">
                                    <?= $row['denda_keterlambatan'] > 0 ? formatRupiah($row['denda_keterlambatan']) : 'Rp 0' ?>
                                </td>
                                <td style="font-weight: 700; color: #ffffff;"><?= formatRupiah($row['total_biaya']) ?></td>
                            </tr>
                <?php 
                            endforeach; 
                        else:
                ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Tidak ada data laporan penyewaan.
                            </td>
                        </tr>
                <?php 
                        endif;
                    endif;
                ?>
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid var(--border); font-weight: 700; background-color: rgba(255, 255, 255, 0.01);">
                        <td colspan="<?= $is_detail_mode ? '6' : '8' ?>" style="text-align: right; padding: 1rem; color: var(--text-muted); font-size: 1rem;">Total Pendapatan Terverifikasi (Lunas):</td>
                        <td style="color: var(--success); font-size: 1.15rem; padding: 1rem;"><?= formatRupiah($total_pendapatan) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <script src="../assets/js/pagination.js"></script>
    <script>initPagination(document.getElementById('tbl-laporan'));</script>
</body>
</html>
