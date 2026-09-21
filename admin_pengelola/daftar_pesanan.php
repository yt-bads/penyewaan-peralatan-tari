<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_pengelola
requireRole('admin_pengelola');
$user = getCurrentUser();

// Ambil parameter filter
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$bayar_filter  = isset($_GET['status_pembayaran']) ? trim($_GET['status_pembayaran']) : '';

// Query data penyewaan seluruh pelanggan dengan JOIN
$query = "SELECT p.*, u.nama AS nama_pelanggan, u.username AS username_pelanggan
          FROM penyewaan p
          JOIN users u ON p.user_id = u.id
          WHERE 1=1";
$params = [];

if ($status_filter !== '') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($bayar_filter !== '') {
    $query .= " AND p.status_pembayaran = ?";
    $params[] = $bayar_filter;
}

// Urutkan prioritas: 'menunggu_verifikasi' di atas, sisanya diurutkan berdasarkan ID terbaru
$query .= " ORDER BY CASE WHEN p.status_pembayaran = 'menunggu_verifikasi' THEN 0 ELSE 1 END ASC, p.id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    $_SESSION['error'] = 'Kesalahan database saat memuat daftar pesanan.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesanan Penyewaan - Admin Pengelola</title>
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

        .highlight-row {
            background: rgba(245, 158, 11, 0.03);
            border-left: 3px solid var(--warning);
        }

        .premium-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .highlight-row:hover {
            background: rgba(245, 158, 11, 0.06);
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
        .pembayaran-menunggu_verifikasi { background: rgba(245, 158, 11, 0.15); color: #fbbf24; box-shadow: 0 0 8px rgba(245, 158, 11, 0.2); }
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

        .priority-label {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--warning);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari - Admin</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="daftar_pesanan.php" class="nav-link active">Daftar Pesanan</a>
            <a href="laporan.php" class="nav-link">Laporan</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-admin-pengelola">Admin Pengelola</span>
            <h1 style="margin-top: 0.5rem;">Daftar Transaksi Penyewaan</h1>
            <p>Kelola verifikasi pembayaran bukti transfer dan update status sewa pelanggan.</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= htmlspecialchars($_SESSION['success']) ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?= htmlspecialchars($_SESSION['error']) ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Section Filter & Pencarian -->
        <div class="filter-section">
            <form action="daftar_pesanan.php" method="GET" class="filter-form">
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
                    <label for="status_pembayaran" class="form-label">Status Pembayaran</label>
                    <select name="status_pembayaran" id="status_pembayaran" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="belum_bayar" <?= $bayar_filter === 'belum_bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                        <option value="menunggu_verifikasi" <?= $bayar_filter === 'menunggu_verifikasi' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                        <option value="lunas" <?= $bayar_filter === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                    </select>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 0.7rem 1.5rem;">Filter</button>
                    <?php if ($status_filter !== '' || $bayar_filter !== ''): ?>
                        <a href="daftar_pesanan.php" class="btn btn-secondary" style="width: auto; padding: 0.7rem 1.5rem; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Transaksi -->
        <div class="table-section">
            <?php if (count($orders) > 0): ?>
                <table class="premium-table" id="tbl-pesanan">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Nama Pelanggan</th>
                            <th>Tgl Sewa</th>
                            <th>Tgl Kembali</th>
                            <th>Total Biaya</th>
                            <th>Status Sewa</th>
                            <th>Status Bayar</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $row): ?>
                            <?php 
                                $is_priority = $row['status_pembayaran'] === 'menunggu_verifikasi';
                                $row_class = $is_priority ? 'highlight-row' : '';
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td style="font-weight: 600;">
                                    #<?= $row['id'] ?>
                                    <?php if ($is_priority): ?>
                                        <div class="priority-label">
                                            <span style="display:inline-block; width: 6px; height: 6px; background-color: var(--warning); border-radius: 50%;"></span>
                                            Butuh Verifikasi
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 500; display: block;"><?= htmlspecialchars($row['nama_pelanggan']) ?></span>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">@<?= htmlspecialchars($row['username_pelanggan']) ?></span>
                                </td>
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
                                    <a href="detail_pesanan.php?id=<?= $row['id'] ?>" class="btn-action btn-detail">Detail & Aksi</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                    Tidak ada data pesanan sewa yang cocok.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../assets/js/pagination.js"></script>
    <script>initPagination(document.getElementById('tbl-pesanan'));</script>
</body>
</html>
