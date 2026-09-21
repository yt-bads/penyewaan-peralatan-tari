<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Proteksi halaman pelanggan
requireRole('pelanggan');
$user = getCurrentUser();

// Ambil parameter filter & pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

// Bangun query prepared statement
$query = "SELECT * FROM peralatan WHERE stok > 0 AND kondisi = 'baik'";
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
    <title>Katalog Peralatan Tari - Sewa Tari</title>
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

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .catalog-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: var(--transition);
        }

        .catalog-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .catalog-card-header {
            margin-bottom: 1rem;
        }

        .catalog-card-kategori {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .catalog-card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.3;
        }

        .catalog-card-body {
            margin-bottom: 1.5rem;
        }

        .catalog-card-desc {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.7rem;
        }

        .catalog-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }

        .catalog-card-price {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text);
        }

        .catalog-card-stock {
            color: var(--text-muted);
        }

        .stok-badge {
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
        }

        .stok-tersedia {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .stok-habis {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .btn-sewa {
            width: 100%;
            padding: 0.7rem;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="katalog.php" class="nav-link active">Katalog</a>
            <a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-pelanggan">Pelanggan</span>
            <h1 style="margin-top: 0.5rem;">Katalog Peralatan Tari</h1>
            <p>Telusuri kostum, aksesoris, dan alat musik tari tradisional yang siap disewa.</p>
        </div>

        <!-- Section Filter & Pencarian -->
        <div class="filter-section">
            <form action="katalog.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search" class="form-label">Cari Peralatan</label>
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
                        <a href="katalog.php" class="btn btn-secondary" style="width: auto; padding: 0.7rem 1.5rem; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Grid Katalog -->
        <div class="catalog-grid">
            <?php if (count($peralatan_list) > 0): ?>
                <?php foreach ($peralatan_list as $row): ?>
                    <div class="catalog-card">
                        <div class="catalog-card-image" style="width: 100%; height: 180px; overflow: hidden; border-radius: var(--radius-md); margin-bottom: 1rem; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.15);">
                            <?php if (!empty($row['foto'])): ?>
                                <img src="../uploads/foto_peralatan/<?= htmlspecialchars($row['foto']) ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Foto <?= htmlspecialchars($row['nama_alat']) ?>">
                            <?php else: ?>
                                <img src="../assets/img/no_foto.png" style="width: 100%; height: 100%; object-fit: cover;" alt="No image available">
                            <?php endif; ?>
                        </div>
                        <div class="catalog-card-header">
                            <div class="catalog-card-kategori">
                                <?= htmlspecialchars(str_replace('_', ' ', $row['kategori_alat'])) ?>
                            </div>
                            <h3 class="catalog-card-title"><?= htmlspecialchars($row['nama_alat']) ?></h3>
                        </div>

                        <div class="catalog-card-body">
                            <p class="catalog-card-desc">
                                <?= htmlspecialchars($row['deskripsi'] ?? 'Tidak ada deskripsi untuk perlengkapan tari ini.') ?>
                            </p>
                            <div class="catalog-card-meta">
                                <div class="catalog-card-price">
                                    <?= formatRupiah($row['harga_sewa']) ?> <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">/ flat</span>
                                </div>
                                <div>
                                    <?php if ($row['stok'] > 0): ?>
                                        <span class="stok-badge stok-clean stok-tersedia">Stok: <?= $row['stok'] ?></span>
                                    <?php else: ?>
                                        <span class="stok-badge stok-clean stok-habis">Habis</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <?php if ($row['stok'] > 0): ?>
                                <a href="pesan.php?item_id=<?= $row['id'] ?>" class="btn btn-primary btn-sewa">Sewa Sekarang</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sewa" disabled style="cursor: not-allowed; opacity: 0.6;">Stok Habis</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                    Tidak ada peralatan tari yang sesuai dengan kriteria pencarian Anda.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
