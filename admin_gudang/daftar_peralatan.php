<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_gudang
requireRole('admin_gudang');
$user = getCurrentUser();

// Ambil parameter filter & pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

// Bangun query prepared statement
$query = "SELECT * FROM peralatan WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND nama_alat LIKE ?";
    $params[] = '%' . $search . '%';
}

if ($kategori !== '') {
    $query .= " AND kategori_alat = ?";
    $params[] = $kategori;
}

$query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$peralatan_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Peralatan - Admin Gudang</title>
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
            min-width: 200px;
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

        .kondisi-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .kondisi-baik { background: rgba(16, 185, 129, 0.15); color: #22c55e; }
        .kondisi-rusak { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .kondisi-perbaikan { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }

        .btn-action-group {
            display: flex;
            gap: 0.5rem;
        }

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

        .btn-edit {
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .btn-edit:hover {
            background: var(--primary);
            color: #ffffff;
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: #ffffff;
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
            <a href="daftar_peralatan.php" class="nav-link active">Data Peralatan</a>
            <a href="riwayat_stok.php" class="nav-link">Riwayat Stok</a>
            <a href="daftar_pengembalian.php" class="nav-link">Pengembalian</a>
            <a href="laporan_denda.php" class="nav-link">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="header-actions">
            <div class="dashboard-header" style="margin-bottom: 0;">
                <span class="role-badge badge-admin-gudang">Admin Gudang</span>
                <h1 style="margin-top: 0.5rem;">Manajemen Peralatan</h1>
                <p>Kelola daftar barang, stok, dan tarif sewa sanggar tari.</p>
            </div>
            <div>
                <a href="tambah_peralatan.php" class="btn btn-primary" style="width: auto;">+ Tambah Peralatan</a>
            </div>
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
            <form action="daftar_peralatan.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search" class="form-label">Cari Nama Alat</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Cari nama peralatan..." value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="form-group">
                    <label for="kategori" class="form-label">Kategori</label>
                    <select name="kategori" id="kategori" class="form-control">
                        <option value="">Semua Kategori</option>
                        <option value="kostum" <?= $kategori === 'kostum' ? 'selected' : '' ?>>Kostum</option>
                        <option value="aksesoris" <?= $kategori === 'aksesoris' ? 'selected' : '' ?>>Aksesoris</option>
                        <option value="alat_musik" <?= $kategori === 'alat_musik' ? 'selected' : '' ?>>Alat Musik</option>
                    </select>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 0.7rem 1.5rem;">Cari</button>
                    <?php if ($search !== '' || $kategori !== ''): ?>
                        <a href="daftar_peralatan.php" class="btn btn-secondary" style="width: auto; padding: 0.7rem 1.5rem; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Peralatan -->
        <div class="table-section">
            <?php if (count($peralatan_list) > 0): ?>
                <table class="premium-table" id="tbl-peralatan">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Foto</th>
                            <th>Nama Peralatan</th>
                            <th>Kategori</th>
                            <th style="text-align: center;">Stok</th>
                            <th>Harga Sewa</th>
                            <th>Kondisi</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($peralatan_list as $row): ?>
                            <tr>
                                <td>#<?= $row['id'] ?></td>
                                <td>
                                    <?php if (!empty($row['foto'])): ?>
                                        <img src="../uploads/foto_peralatan/<?= htmlspecialchars($row['foto']) ?>" style="max-height: 50px; border-radius: var(--radius-sm); border: 1px solid var(--border);" alt="Foto <?= htmlspecialchars($row['nama_alat']) ?>">
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 600;"><?= htmlspecialchars($row['nama_alat']) ?></td>
                                <td style="text-transform: capitalize;"><?= str_replace('_', ' ', $row['kategori_alat']) ?></td>
                                <td style="text-align: center; font-weight: 700;"><?= $row['stok'] ?></td>
                                <td><?= formatRupiah($row['harga_sewa']) ?></td>
                                <td>
                                    <?php 
                                        $kondisiClass = 'kondisi-baik';
                                        $kondisiText = 'Baik';
                                        if ($row['kondisi'] === 'rusak') {
                                            $kondisiClass = 'kondisi-rusak';
                                            $kondisiText = 'Rusak';
                                        } elseif ($row['kondisi'] === 'perlu_perbaikan') {
                                            $kondisiClass = 'kondisi-perbaikan';
                                            $kondisiText = 'Perlu Perbaikan';
                                        }
                                    ?>
                                    <span class="kondisi-badge <?= $kondisiClass ?>"><?= $kondisiText ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <div class="btn-action-group" style="justify-content: center;">
                                        <a href="edit_peralatan.php?id=<?= $row['id'] ?>" class="btn-action btn-edit">Edit</a>
                                        <form action="hapus_peralatan.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus peralatan \'<?= htmlspecialchars($row['nama_alat']) ?>\'?')" style="display: inline;">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn-action btn-delete">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    Tidak ada data peralatan ditemukan.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../assets/js/pagination.js"></script>
    <script>initPagination(document.getElementById('tbl-peralatan'));</script>
</body>
</html>
