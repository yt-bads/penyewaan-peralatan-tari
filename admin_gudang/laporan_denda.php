<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_gudang
requireRole('admin_gudang');
$user = getCurrentUser();

$tanggal_mulai = isset($_GET['tanggal_mulai']) ? trim($_GET['tanggal_mulai']) : '';
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : '';

// Query data laporan denda
$query = "
    SELECT k.*, p.tanggal_kembali, u.nama as nama_pelanggan, ua.nama as dicatat_oleh_nama,
           (SELECT SUM(jumlah_pinjam) FROM detail_penyewaan WHERE penyewaan_id = k.penyewaan_id) AS total_item
    FROM pengembalian k
    JOIN penyewaan p ON k.penyewaan_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN users ua ON k.dicatat_oleh = ua.id
    WHERE k.denda_keterlambatan > 0
";
$params = [];

if ($tanggal_mulai !== '') {
    $query .= " AND k.tanggal_dikembalikan >= ?";
    $params[] = $tanggal_mulai;
}

if ($tanggal_akhir !== '') {
    $query .= " AND k.tanggal_dikembalikan <= ?";
    $params[] = $tanggal_akhir;
}

$query .= " ORDER BY k.created_at DESC";

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
    <title>Laporan Denda Keterlambatan - Admin Gudang</title>
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
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Gudang</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="daftar_peralatan.php" class="nav-link">Data Peralatan</a>
            <a href="riwayat_stok.php" class="nav-link">Riwayat Stok</a>
            <a href="daftar_pengembalian.php" class="nav-link">Pengembalian</a>
            <a href="laporan_denda.php" class="nav-link active">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="header-actions">
            <div class="dashboard-header" style="margin-bottom: 0;">
                <span class="role-badge badge-admin-gudang">Admin Gudang</span>
                <h1 style="margin-top: 0.5rem;">Laporan Denda</h1>
                <p>Pantau laporan denda keterlambatan pengembalian peralatan tari.</p>
            </div>
            <div>
                <?php 
                    $pdf_url = "export_denda_pdf.php?" . http_build_query([
                        'tanggal_mulai' => $tanggal_mulai,
                        'tanggal_akhir' => $tanggal_akhir
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

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 0.7rem 1.5rem;">Filter</button>
                    <?php if ($tanggal_mulai !== '' || $tanggal_akhir !== ''): ?>
                        <a href="laporan_denda.php" class="btn btn-secondary" style="width: auto; padding: 0.7rem 1.5rem; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Laporan -->
        <div class="table-section">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Nama Pelanggan</th>
                        <th>Batas Jatuh Tempo</th>
                        <th>Tanggal Dikembalikan</th>
                        <th style="text-align: center;">Total Item</th>
                        <th>Dicatat Oleh</th>
                        <th>Nominal Denda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $total_denda = 0;
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
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid var(--border); font-weight: 700; background-color: rgba(255, 255, 255, 0.01);">
                        <td colspan="6" style="text-align: right; padding: 1rem; color: var(--text-muted); font-size: 1rem;">Total Akumulasi Denda:</td>
                        <td style="color: var(--danger); font-size: 1.15rem; padding: 1rem;"><?= formatRupiah($total_denda) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>
