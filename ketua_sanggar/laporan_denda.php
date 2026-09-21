<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role ketua_sanggar
requireRole('ketua_sanggar');
$user = getCurrentUser();

$tanggal_mulai = isset($_GET['tanggal_mulai']) ? trim($_GET['tanggal_mulai']) : '';
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : '';
$kategori_filter = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

// Mapping value dropdown ke enum database
$kategori_map = [
    'Kostum'     => 'kostum',
    'Aksesoris'  => 'aksesoris',
    'Alat Musik' => 'alat_musik',
];
$kategori_db = isset($kategori_map[$kategori_filter]) ? $kategori_map[$kategori_filter] : '';

// Tentukan mode: detail-level jika kategori dipilih
$is_detail_mode = ($kategori_db !== '');

$params = [];

if ($is_detail_mode) {
    // Mode detail-level: JOIN ke detail_penyewaan → peralatan, filter kategori
    // Denda disimpan di level header (pengembalian), bukan per-item.
    // Hitung proporsi denda: (jumlah_pinjam item kategori / total item transaksi) × denda_header
    $query = "
        SELECT k.penyewaan_id, k.tanggal_dikembalikan, k.denda_keterlambatan,
               p.tanggal_kembali,
               u.nama AS nama_pelanggan,
               ua.nama AS dicatat_oleh_nama,
               alat.nama_alat, alat.kategori_alat,
               dp.jumlah_pinjam,
               (SELECT SUM(dp2.jumlah_pinjam) FROM detail_penyewaan dp2 WHERE dp2.penyewaan_id = k.penyewaan_id) AS total_item_transaksi,
               ROUND(k.denda_keterlambatan * dp.jumlah_pinjam / 
                   (SELECT SUM(dp3.jumlah_pinjam) FROM detail_penyewaan dp3 WHERE dp3.penyewaan_id = k.penyewaan_id), 2
               ) AS denda_proporsional
        FROM pengembalian k
        JOIN penyewaan p ON k.penyewaan_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN users ua ON k.dicatat_oleh = ua.id
        JOIN detail_penyewaan dp ON dp.penyewaan_id = k.penyewaan_id
        JOIN peralatan alat ON dp.peralatan_id = alat.id
        WHERE k.denda_keterlambatan > 0
          AND alat.kategori_alat = ?
    ";
    $params[] = $kategori_db;

    if ($tanggal_mulai !== '') {
        $query .= " AND k.tanggal_dikembalikan >= ?";
        $params[] = $tanggal_mulai;
    }
    if ($tanggal_akhir !== '') {
        $query .= " AND k.tanggal_dikembalikan <= ?";
        $params[] = $tanggal_akhir;
    }

    $query .= " ORDER BY k.created_at DESC, alat.nama_alat ASC";
} else {
    // Mode header-level: query existing (per-transaksi)
    $query = "
        SELECT k.*, p.tanggal_kembali, u.nama as nama_pelanggan, ua.nama as dicatat_oleh_nama,
               (SELECT SUM(jumlah_pinjam) FROM detail_penyewaan WHERE penyewaan_id = k.penyewaan_id) AS total_item
        FROM pengembalian k
        JOIN penyewaan p ON k.penyewaan_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN users ua ON k.dicatat_oleh = ua.id
        WHERE k.denda_keterlambatan > 0
    ";

    if ($tanggal_mulai !== '') {
        $query .= " AND k.tanggal_dikembalikan >= ?";
        $params[] = $tanggal_mulai;
    }
    if ($tanggal_akhir !== '') {
        $query .= " AND k.tanggal_dikembalikan <= ?";
        $params[] = $tanggal_akhir;
    }

    $query .= " ORDER BY k.created_at DESC";
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $denda_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $denda_list = [];
    $_SESSION['error'] = 'Kesalahan database saat memuat laporan denda.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Denda Keterlambatan - Ketua Sanggar</title>
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

        .date-shortcut-group {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .btn-shortcut {
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-shortcut:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
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

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .info-notice {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: var(--radius-sm);
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-notice .info-icon {
            font-size: 1.1rem;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Pimpinan</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="pantau_penyewaan.php" class="nav-link">Pantau Transaksi</a>
            <a href="laporan.php" class="nav-link">Laporan Sewa</a>
            <a href="laporan_denda.php" class="nav-link active">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="header-actions">
            <div class="dashboard-header" style="margin-bottom: 0;">
                <span class="role-badge badge-ketua-sanggar">Ketua Sanggar</span>
                <h1 style="margin-top: 0.5rem;">Laporan Denda</h1>
                <p>Pantau laporan denda keterlambatan pengembalian peralatan tari.</p>
            </div>
            <div>
                <?php 
                    $pdf_url = "export_denda_pdf.php?" . http_build_query([
                        'tanggal_mulai' => $tanggal_mulai,
                        'tanggal_akhir' => $tanggal_akhir,
                        'kategori' => $kategori_filter
                    ]);
                ?>
                <a href="<?= htmlspecialchars($pdf_url) ?>" class="btn btn-primary" style="width: auto;">Unduh Laporan Denda (PDF)</a>
            </div>
        </div>

        <!-- Section Filter Laporan -->
        <div class="filter-section">
            <form action="laporan_denda.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="tanggal_mulai" class="form-label">Tanggal Mulai Dikembalikan</label>
                    <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" value="<?= htmlspecialchars($tanggal_mulai) ?>">
                </div>

                <div class="form-group">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir Dikembalikan</label>
                    <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggal_akhir) ?>">
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
                    <?php if ($tanggal_mulai !== '' || $tanggal_akhir !== '' || $kategori_filter !== ''): ?>
                        <a href="laporan_denda.php" class="btn btn-secondary" style="width: auto; padding: 0.7rem 1.5rem; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="date-shortcut-group" style="margin-top: 1rem;">
                <span style="font-size: 0.8rem; color: var(--text-muted); margin-right: 0.25rem; align-self: center;">Shortcut:</span>
                <button type="button" class="btn-shortcut" onclick="setDateRange('minggu_ini')">Minggu Ini</button>
                <button type="button" class="btn-shortcut" onclick="setDateRange('bulan_ini')">Bulan Ini</button>
                <button type="button" class="btn-shortcut" onclick="setDateRange('bulan_lalu')">Bulan Lalu</button>
            </div>
        </div>

        <?php if ($is_detail_mode): ?>
        <div class="info-notice">
            <span class="info-icon">ℹ️</span>
            <span>Denda dicatat per transaksi, bukan per item. Nominal yang ditampilkan adalah <strong>denda proporsional</strong> berdasarkan rasio jumlah item kategori <strong><?= htmlspecialchars($kategori_filter) ?></strong> terhadap total item dalam transaksi.</span>
        </div>
        <?php endif; ?>

        <!-- Tabel Laporan -->
        <div class="table-section">
            <table class="premium-table" id="tbl-laporan-denda">
                <thead>
                    <tr>
                    <?php if ($is_detail_mode): ?>
                        <th>No. Pesanan</th>
                        <th>Nama Pelanggan</th>
                        <th>Nama Peralatan</th>
                        <th style="text-align: center;">Jumlah</th>
                        <th>Batas Jatuh Tempo</th>
                        <th>Tgl Dikembalikan</th>
                        <th>Denda Proporsional</th>
                    <?php else: ?>
                        <th>No. Pesanan</th>
                        <th>Nama Pelanggan</th>
                        <th>Batas Jatuh Tempo</th>
                        <th>Tanggal Dikembalikan</th>
                        <th style="text-align: center;">Total Item</th>
                        <th>Dicatat Oleh</th>
                        <th>Nominal Denda</th>
                    <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $total_denda = 0;

                    if ($is_detail_mode):
                        // === MODE DETAIL-LEVEL (kategori dipilih) ===
                        if (count($denda_list) > 0):
                            foreach ($denda_list as $row):
                                $denda_prop = (float)$row['denda_proporsional'];
                                $total_denda += $denda_prop;
                ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= $row['penyewaan_id'] ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                <td><?= htmlspecialchars($row['nama_alat']) ?></td>
                                <td style="text-align: center; font-weight: 600;"><?= $row['jumlah_pinjam'] ?> unit</td>
                                <td><?= formatTanggalIndo($row['tanggal_kembali']) ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_dikembalikan']) ?></td>
                                <td style="font-weight: 700; color: var(--danger);"><?= formatRupiah($denda_prop) ?></td>
                            </tr>
                <?php
                            endforeach;
                        else:
                ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Tidak ada data denda untuk kategori <strong><?= htmlspecialchars($kategori_filter) ?></strong> dalam periode ini.
                            </td>
                        </tr>
                <?php
                        endif;
                    else:
                        // === MODE HEADER-LEVEL (semua kategori) ===
                        if (count($denda_list) > 0):
                            foreach ($denda_list as $row):
                                $total_denda += (float)$row['denda_keterlambatan'];
                ?>
                            <tr>
                                <td style="font-weight: 600;">#<?= $row['penyewaan_id'] ?></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_kembali']) ?></td>
                                <td><?= formatTanggalIndo($row['tanggal_dikembalikan']) ?></td>
                                <td style="text-align: center; font-weight: 600;"><?= $row['total_item'] ?> unit</td>
                                <td><?= htmlspecialchars($row['dicatat_oleh_nama']) ?></td>
                                <td style="font-weight: 700; color: var(--danger);"><?= formatRupiah($row['denda_keterlambatan']) ?></td>
                            </tr>
                <?php 
                            endforeach; 
                        else:
                ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Tidak ada data denda keterlambatan dalam periode ini.
                            </td>
                        </tr>
                <?php
                        endif;
                    endif;
                ?>
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid var(--border); font-weight: 700; background-color: rgba(255, 255, 255, 0.01);">
                    <?php if ($is_detail_mode): ?>
                        <td colspan="6" style="text-align: right; padding: 1rem; color: var(--text-muted); font-size: 1rem;">Total Denda Proporsional — <?= htmlspecialchars($kategori_filter) ?>:</td>
                        <td style="color: var(--danger); font-size: 1.15rem; padding: 1rem;"><?= formatRupiah($total_denda) ?></td>
                    <?php else: ?>
                        <td colspan="6" style="text-align: right; padding: 1rem; color: var(--text-muted); font-size: 1rem;">Total Akumulasi Denda:</td>
                        <td style="color: var(--danger); font-size: 1.15rem; padding: 1rem;"><?= formatRupiah($total_denda) ?></td>
                    <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <script src="../assets/js/pagination.js"></script>
    <script>initPagination(document.getElementById('tbl-laporan-denda'));</script>
    <script>
    function setDateRange(type) {
        var mulai = document.getElementById('tanggal_mulai');
        var akhir = document.getElementById('tanggal_akhir');
        var today = new Date();
        var startDate, endDate;

        if (type === 'minggu_ini') {
            var day = today.getDay(); // 0=Minggu, 1=Senin, ...
            var diffToMonday = (day === 0) ? -6 : 1 - day;
            startDate = new Date(today);
            startDate.setDate(today.getDate() + diffToMonday);
            endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 6);
        } else if (type === 'bulan_ini') {
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else if (type === 'bulan_lalu') {
            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth(), 0);
        }

        mulai.value = formatDateYMD(startDate);
        akhir.value = formatDateYMD(endDate);
    }

    function formatDateYMD(date) {
        var y = date.getFullYear();
        var m = ('0' + (date.getMonth() + 1)).slice(-2);
        var d = ('0' + date.getDate()).slice(-2);
        return y + '-' + m + '-' + d;
    }
    </script>
</body>
</html>

