<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_pengelola
requireRole('admin_pengelola');
$user = getCurrentUser();

$error_msg = null;
$success_msg = null;

// Tangkap flash messages dari session
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Validasi parameter ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID Pesanan tidak valid.';
    header('Location: daftar_pesanan.php');
    exit();
}

try {
    // Ambil data sewa dan info pelanggan
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama AS nama_pelanggan, u.no_telp, u.alamat, u.username
        FROM penyewaan p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $penyewaan = $stmt->fetch();

    if (!$penyewaan) {
        $_SESSION['error'] = 'Pesanan sewa tidak ditemukan.';
        header('Location: daftar_pesanan.php');
        exit();
    }

    // Ambil detail barang yang disewa
    $stmtDetail = $pdo->prepare("
        SELECT dp.jumlah_pinjam, p.nama_alat, p.kategori_alat, p.harga_sewa 
        FROM detail_penyewaan dp
        JOIN peralatan p ON dp.peralatan_id = p.id
        WHERE dp.penyewaan_id = ?
    ");
    $stmtDetail->execute([$id]);
    $items = $stmtDetail->fetchAll();

    // Query data pengembalian & denda jika status terlambat
    $pengembalian = null;
    if ($penyewaan['status'] === 'terlambat') {
        $stmtPengembalian = $pdo->prepare("
            SELECT denda_keterlambatan, status_denda, bukti_bayar_denda
            FROM pengembalian 
            WHERE penyewaan_id = ?
        ");
        $stmtPengembalian->execute([$id]);
        $pengembalian = $stmtPengembalian->fetch();
    }

} catch (PDOException $e) {
    $_SESSION['error'] = 'Kesalahan database saat memuat detail pesanan.';
    header('Location: daftar_pesanan.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?= $id ?> - Admin Pengelola</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .detail-layout {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 2rem;
            align-items: start;
        }

        .card-detail {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
        }

        .detail-row-info {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            font-size: 0.95rem;
        }

        .detail-row-info:last-child {
            border-bottom: none;
        }

        .detail-row-info span:first-child {
            color: var(--text-muted);
        }

        .detail-row-info span:last-child {
            font-weight: 500;
            text-align: right;
        }

        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Status Badges */
        .status-menunggu { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .status-aktif { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
        .status-selesai { background: rgba(16, 185, 129, 0.15); color: #22c55e; }
        .status-terlambat { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .pembayaran-belum_bayar { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .pembayaran-menunggu_verifikasi { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .pembayaran-lunas { background: rgba(16, 185, 129, 0.15); color: #22c55e; }

        .premium-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95rem;
            margin-top: 1rem;
        }

        .premium-table th {
            padding: 0.75rem 1rem;
            border-bottom: 2px solid var(--border);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
        }

        .premium-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        .proof-preview {
            max-width: 100%;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            margin-top: 1rem;
        }

        .action-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        @media (max-width: 992px) {
            .detail-layout {
                grid-template-columns: 1fr;
            }
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
            <h1 style="margin-top: 0.5rem;">Detail Pesanan #<?= $id ?></h1>
            <p>Kelola verifikasi pembayaran dan status pemesanan sewa.</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= htmlspecialchars($success_msg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <div class="detail-layout">
            <div>
                <!-- Info Pelanggan & Transaksi -->
                <div class="card-detail">
                    <h2 style="font-weight: 700; margin-bottom: 1.25rem;">Informasi Pelanggan</h2>
                    
                    <div class="detail-row-info">
                        <span>Nama Pelanggan</span>
                        <span><?= htmlspecialchars($penyewaan['nama_pelanggan']) ?> (@<?= htmlspecialchars($penyewaan['username']) ?>)</span>
                    </div>
                    <div class="detail-row-info">
                        <span>Nomor Telepon</span>
                        <span><?= htmlspecialchars($penyewaan['no_telp'] ?? '-') ?></span>
                    </div>
                    <div class="detail-row-info">
                        <span>Alamat Lengkap</span>
                        <span><?= htmlspecialchars($penyewaan['alamat'] ?? '-') ?></span>
                    </div>
                </div>

                <div class="card-detail">
                    <h2 style="font-weight: 700; margin-bottom: 1.25rem;">Rincian Transaksi</h2>
                    
                    <div class="detail-row-info">
                        <span>Tanggal Mulai Sewa</span>
                        <span><?= formatTanggalIndo($penyewaan['tanggal_sewa']) ?></span>
                    </div>
                    <div class="detail-row-info">
                        <span>Tanggal Batas Kembali</span>
                        <span><?= formatTanggalIndo($penyewaan['tanggal_kembali']) ?></span>
                    </div>
                    <div class="detail-row-info">
                        <span>Catatan Pelanggan</span>
                        <span><?= htmlspecialchars($penyewaan['catatan'] ?? '-') ?></span>
                    </div>
                </div>

                <!-- Rincian Item Tabel -->
                <div class="card-detail">
                    <h2 style="font-weight: 700; margin-bottom: 1.25rem;">Rincian Barang yang Disewa</h2>
                    
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Nama Alat</th>
                                <th>Kategori</th>
                                <th style="text-align: center;">Jumlah</th>
                                <th>Harga Sewa (Flat)</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $row): ?>
                                <?php $sub = $row['jumlah_pinjam'] * $row['harga_sewa']; ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($row['nama_alat']) ?></td>
                                    <td style="text-transform: capitalize;"><?= str_replace('_', ' ', $row['kategori_alat']) ?></td>
                                    <td style="text-align: center;"><?= $row['jumlah_pinjam'] ?></td>
                                    <td><?= formatRupiah($row['harga_sewa']) ?></td>
                                    <td style="font-weight: 600; color: #ffffff;"><?= formatRupiah($sub) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="border-top: 2px solid var(--border);">
                                <td colspan="4" style="text-align: right; font-weight: 700; color: var(--text-muted);">Total Biaya:</td>
                                <td style="font-weight: 700; font-size: 1.1rem; color: var(--primary);"><?= formatRupiah($penyewaan['total_biaya']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Panel Aksi Verifikasi & Status -->
            <div>
                <!-- Status Box -->
                <div class="card-detail">
                    <h2 style="font-weight: 700; margin-bottom: 1.25rem;">Status Transaksi</h2>
                    
                    <div class="detail-row-info" style="margin-bottom: 0.5rem;">
                        <span>Status Sewa</span>
                        <span>
                            <?php 
                                $statusClass = 'status-menunggu';
                                $statusText = 'Menunggu';
                                if ($penyewaan['status'] === 'aktif') {
                                    $statusClass = 'status-aktif';
                                    $statusText = 'Aktif (Disewa)';
                                } elseif ($penyewaan['status'] === 'selesai') {
                                    $statusClass = 'status-selesai';
                                    $statusText = 'Selesai';
                                } elseif ($penyewaan['status'] === 'terlambat') {
                                    $statusClass = 'status-terlambat';
                                    $statusText = 'Terlambat';
                                }
                            ?>
                            <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                        </span>
                    </div>

                    <div class="detail-row-info" style="margin-bottom: 1.5rem;">
                        <span>Status Pembayaran</span>
                        <span>
                            <?php 
                                $bayarClass = 'pembayaran-belum_bayar';
                                $bayarText = 'Belum Bayar';
                                if ($penyewaan['status_pembayaran'] === 'menunggu_verifikasi') {
                                    $bayarClass = 'pembayaran-menunggu_verifikasi';
                                    $bayarText = 'Menunggu Verifikasi';
                                } elseif ($penyewaan['status_pembayaran'] === 'lunas') {
                                    $bayarClass = 'pembayaran-lunas';
                                    $bayarText = 'Lunas';
                                }
                            ?>
                            <span class="status-badge <?= $bayarClass ?>"><?= $bayarText ?></span>
                        </span>
                    </div>

                    <!-- Tombol Verifikasi & Aktifkan -->
                    <?php if ($penyewaan['status_pembayaran'] === 'menunggu_verifikasi'): ?>
                        <div class="action-box">
                            <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--warning);">Verifikasi Pembayaran</h3>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                                Periksa apakah bukti transfer di bawah ini bernilai valid dan sesuai dengan total tagihan. Jika valid, klik tombol di bawah untuk mengaktifkan penyewaan ini (akan mengurangi stok barang secara otomatis).
                            </p>
                            <form action="verifikasi_bayar.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memverifikasi pembayaran ini dan mengaktifkan pesanan sewa?')">
                                <input type="hidden" name="penyewaan_id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-primary">Verifikasi & Aktifkan</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Form Update Status Sewa secara Manual -->
                    <div class="action-box" style="margin-top: 1.5rem;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem;">Ubah Status Sewa Manual</h3>
                        <form action="update_status.php" method="POST">
                            <input type="hidden" name="penyewaan_id" value="<?= $id ?>">
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <select name="status_baru" class="form-control" required>
                                    <option value="menunggu" <?= $penyewaan['status'] === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                                    <option value="aktif" <?= $penyewaan['status'] === 'aktif' ? 'selected' : '' ?>>Aktif (Disewa)</option>
                                    <option value="selesai" <?= $penyewaan['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                    <option value="terlambat" <?= $penyewaan['status'] === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-secondary">Simpan Status</button>
                        </form>
                    </div>
                </div>

                <!-- Preview Bukti Pembayaran -->
                <?php if ($penyewaan['bukti_pembayaran'] !== null): ?>
                    <div class="card-detail">
                        <h2 style="font-weight: 700; margin-bottom: 1rem;">Bukti Pembayaran Pelanggan</h2>
                        <?php 
                            $ext = strtolower(pathinfo($penyewaan['bukti_pembayaran'], PATHINFO_EXTENSION));
                            if ($ext === 'pdf'): 
                        ?>
                            <a href="../<?= htmlspecialchars($penyewaan['bukti_pembayaran']) ?>" target="_blank" class="btn btn-secondary" style="text-decoration: none; display: inline-flex;">Buka File PDF Bukti</a>
                        <?php else: ?>
                            <img src="../<?= htmlspecialchars($penyewaan['bukti_pembayaran']) ?>" class="proof-preview" alt="Bukti Transfer">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Section Pembayaran Denda (Hanya jika status terlambat dan ada record pengembalian) -->
                <?php if ($penyewaan['status'] === 'terlambat' && $pengembalian): ?>
                    <div class="card-detail" style="border-left: 4px solid #ef4444;">
                        <h2 style="font-weight: 700; margin-bottom: 1.25rem; color: #f87171;">Pembayaran Denda</h2>
                        
                        <div class="detail-row-info" style="margin-bottom: 0.75rem;">
                            <span>Nominal Denda</span>
                            <span style="color: #ef4444; font-weight: 700; font-size: 1.1rem;"><?= formatRupiah($pengembalian['denda_keterlambatan']) ?></span>
                        </div>

                        <div class="detail-row-info" style="margin-bottom: 1.25rem;">
                            <span>Status Denda</span>
                            <span>
                                <?php if ($pengembalian['status_denda'] === 'lunas'): ?>
                                    <span class="status-badge" style="background: rgba(16, 185, 129, 0.15); color: #22c55e;">Lunas</span>
                                <?php else: ?>
                                    <span class="status-badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171;">Belum Bayar</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if (!empty($pengembalian['bukti_bayar_denda'])): ?>
                            <h3 style="font-size: 0.95rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.5rem;">Bukti Pembayaran Denda:</h3>
                            <?php 
                                $buktiDendaPath = $pengembalian['bukti_bayar_denda'];
                                if (strpos($buktiDendaPath, 'uploads/') !== 0) {
                                    $buktiDendaPath = 'uploads/bukti_denda/' . $buktiDendaPath;
                                }
                                $extDenda = strtolower(pathinfo($buktiDendaPath, PATHINFO_EXTENSION));
                                if ($extDenda === 'pdf'): 
                            ?>
                                <a href="../<?= htmlspecialchars($buktiDendaPath) ?>" target="_blank" class="btn btn-secondary" style="text-decoration: none; display: inline-flex; margin-bottom: 1rem;">Buka File PDF Bukti Denda</a>
                            <?php else: ?>
                                <img src="../<?= htmlspecialchars($buktiDendaPath) ?>" class="proof-preview" alt="Bukti Pembayaran Denda" style="margin-bottom: 1rem;">
                            <?php endif; ?>
                        <?php else: ?>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">Pelanggan belum mengunggah bukti pembayaran denda.</p>
                        <?php endif; ?>

                        <?php if ($pengembalian['status_denda'] === 'belum_bayar' && !empty($pengembalian['bukti_bayar_denda'])): ?>
                            <div class="action-box" style="margin-top: 1rem;">
                                <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; color: #22c55e;">Verifikasi Pembayaran Denda</h3>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                                    Periksa apakah bukti pembayaran denda di atas valid. Klik tombol di bawah untuk memverifikasi status denda menjadi lunas.
                                </p>
                                <form action="verifikasi_denda.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memverifikasi pembayaran denda ini sebagai LUNAS?')">
                                    <input type="hidden" name="penyewaan_id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-primary" style="background: #10b981; border-color: #10b981;">✅ Verifikasi Lunas</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <a href="daftar_pesanan.php" class="btn btn-secondary" style="text-decoration: none;">Kembali ke Daftar</a>
            </div>
        </div>
    </div>
</body>
</html>
