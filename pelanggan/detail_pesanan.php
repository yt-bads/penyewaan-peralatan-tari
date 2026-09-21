<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Proteksi halaman pelanggan
requireRole('pelanggan');
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

// Validasi parameter ID transaksi
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID Transaksi tidak valid.';
    header('Location: pesanan_saya.php');
    exit();
}

// Ambil data sewa dan check ownership
try {
    $stmt = $pdo->prepare("SELECT * FROM penyewaan WHERE id = ?");
    $stmt->execute([$id]);
    $penyewaan = $stmt->fetch();

    if (!$penyewaan) {
        $_SESSION['error'] = 'Transaksi penyewaan tidak ditemukan.';
        header('Location: pesanan_saya.php');
        exit();
    }

    // PROTEKSI PEMILIK: pastikan user yang login adalah pemilik pesanan ini
    if ((int)$penyewaan['user_id'] !== (int)$user['id']) {
        // Redirect ke halaman 403 Akses Ditolak
        header('Location: /penyewaan-peralatan-tari/403.php');
        exit();
    }

    // Ambil detail item yang disewa
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
            SELECT pen.denda_keterlambatan, pen.status_denda, pen.bukti_bayar_denda
            FROM pengembalian pen
            WHERE pen.penyewaan_id = ?
        ");
        $stmtPengembalian->execute([$id]);
        $pengembalian = $stmtPengembalian->fetch();
    }

} catch (PDOException $e) {
    $_SESSION['error'] = 'Kesalahan sistem saat memuat detail pesanan.';
    header('Location: pesanan_saya.php');
    exit();
}

// Proses POST Upload Bukti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bukti'])) {
    $file = $_FILES['bukti'];

    // 1. Validasi error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Terjadi kesalahan saat mengunggah file bukti pembayaran.';
        header("Location: detail_pesanan.php?id=" . $id);
        exit();
    }

    // 2. Validasi format file (jpg, jpeg, png, pdf)
    $filename = $file['name'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($extension, $allowed_extensions)) {
        $_SESSION['error'] = 'Format file tidak didukung. Harap upload file gambar (JPG, JPEG, PNG) atau PDF.';
        header("Location: detail_pesanan.php?id=" . $id);
        exit();
    }

    // 3. Validasi ukuran file (maksimal 2MB)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = 'Ukuran file terlalu besar. Maksimal ukuran file adalah 2 MB.';
        header("Location: detail_pesanan.php?id=" . $id);
        exit();
    }

    // 4. Proses pemindahan berkas & update DB
    $new_filename = uniqid('bukti_', true) . '.' . $extension;
    $target_dir = '../uploads/bukti/';
    
    // Pastikan direktori uploads/bukti/ sudah ada
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $target_path = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Path relatif untuk disimpan ke DB: uploads/bukti/nama_file
        $db_path = 'uploads/bukti/' . $new_filename;
        
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE penyewaan 
                SET bukti_pembayaran = ?, status_pembayaran = 'menunggu_verifikasi'
                WHERE id = ?
            ");
            $stmtUpdate->execute([$db_path, $id]);

            $_SESSION['success'] = 'Bukti pembayaran berhasil diunggah! Menunggu verifikasi admin pengelola.';
            header("Location: detail_pesanan.php?id=" . $id);
            exit();

        } catch (PDOException $e) {
            // Hapus file yang terlanjur diupload jika DB error
            if (file_exists($target_path)) {
                unlink($target_path);
            }
            $_SESSION['error'] = 'Kesalahan database saat menyimpan bukti pembayaran.';
            header("Location: detail_pesanan.php?id=" . $id);
            exit();
        }
    } else {
        $_SESSION['error'] = 'Gagal menyimpan berkas di server.';
        header("Location: detail_pesanan.php?id=" . $id);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?= $id ?> - Sewa Tari</title>
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
        }

        .detail-row-info:last-child {
            border-bottom: none;
        }

        .detail-row-info span:first-child {
            color: var(--text-muted);
        }

        .detail-row-info span:last-child {
            font-weight: 500;
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

        @media (max-width: 992px) {
            .detail-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sewa Tari</a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="katalog.php" class="nav-link">Katalog</a>
            <a href="pesanan_saya.php" class="nav-link active">Pesanan Saya</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-pelanggan">Pelanggan</span>
            <h1 style="margin-top: 0.5rem;">Detail Transaksi #<?= $id ?></h1>
            <p>Rincian data sewa, status pembayaran, dan tagihan Anda.</p>
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
                <!-- Info Status -->
                <div class="card-detail">
                    <h2 style="font-weight: 700; margin-bottom: 1.25rem;">Informasi Pesanan</h2>
                    
                    <div class="detail-row-info">
                        <span>No. Transaksi</span>
                        <span>#<?= $penyewaan['id'] ?></span>
                    </div>
                    <div class="detail-row-info">
                        <span>Tanggal Mulai Sewa</span>
                        <span><?= formatTanggalIndo($penyewaan['tanggal_sewa']) ?></span>
                    </div>
                    <div class="detail-row-info">
                        <span>Batas Tanggal Kembali</span>
                        <span><?= formatTanggalIndo($penyewaan['tanggal_kembali']) ?></span>
                    </div>
                    <div class="detail-row-info">
                        <span>Status Sewa</span>
                        <span>
                            <?php 
                                $statusClass = 'status-menunggu';
                                $statusText = 'Menunggu';
                                if ($penyewaan['status'] === 'aktif') {
                                    $statusClass = 'status-aktif';
                                    $statusText = 'Aktif (Sedang Disewa)';
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
                                <td colspan="4" style="text-align: right; font-weight: 700; color: var(--text-muted);">Total Tagihan:</td>
                                <td style="font-weight: 700; font-size: 1.1rem; color: var(--primary);"><?= formatRupiah($penyewaan['total_biaya']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Panel Pembayaran -->
            <div>
                <div class="card-detail">
                    <h2 style="font-weight: 700; margin-bottom: 1rem;">Status Pembayaran</h2>
                    
                    <div class="detail-row-info" style="margin-bottom: 1rem;">
                        <span>Status Bayar</span>
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

                    <?php if ($penyewaan['status_pembayaran'] === 'belum_bayar'): ?>
                        <div style="background: rgba(255, 255, 255, 0.02); border: 1px dashed var(--border); padding: 1.25rem; border-radius: var(--radius-sm); font-size: 0.9rem;">
                            <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Informasi Rekening Transfer:</h4>
                            <p style="color: var(--text-muted);">Bank Mandiri: <strong>142-000-123456-7</strong></p>
                            <p style="color: var(--text-muted); margin-bottom: 0.5rem;">A.N. <strong>Sanggar Seni Bedug Yudha</strong></p>
                            <p style="font-size: 0.85rem; color: var(--primary);">Harap transfer sesuai nominal <strong><?= formatRupiah($penyewaan['total_biaya']) ?></strong> lalu upload bukti di bawah ini.</p>
                        </div>

                        <form action="detail_pesanan.php?id=<?= $id ?>" method="POST" enctype="multipart/form-data" style="margin-top: 1.5rem;">
                            <div class="form-group">
                                <label for="bukti" class="form-label">Upload Bukti Transfer</label>
                                <input type="file" name="bukti" id="bukti" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Mendukung format JPG, JPEG, PNG, PDF. Maksimal 2MB.</small>
                            </div>
                            <button type="submit" class="btn btn-primary">Unggah Bukti</button>
                        </form>

                    <?php elseif ($penyewaan['status_pembayaran'] === 'menunggu_verifikasi'): ?>
                        <div class="alert alert-success" style="background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                            <span>Bukti pembayaran sudah diunggah dan sedang dalam proses verifikasi oleh Admin Pengelola.</span>
                        </div>
                        
                        <?php if (!empty($penyewaan['bukti_pembayaran'])): ?>
                            <h4 style="font-weight: 600; margin-top: 1.5rem; font-size: 0.95rem;">Preview Bukti Pembayaran:</h4>
                            <?php 
                                $ext = strtolower(pathinfo($penyewaan['bukti_pembayaran'], PATHINFO_EXTENSION));
                                if ($ext === 'pdf'): 
                            ?>
                                <a href="../<?= htmlspecialchars($penyewaan['bukti_pembayaran']) ?>" target="_blank" class="btn btn-secondary" style="margin-top: 0.5rem; text-decoration: none;">Buka File PDF Bukti</a>
                            <?php else: ?>
                                <img src="../<?= htmlspecialchars($penyewaan['bukti_pembayaran']) ?>" class="proof-preview" alt="Bukti Transfer">
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php elseif ($penyewaan['status_pembayaran'] === 'lunas'): ?>
                        <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.15); color: #10b981;">
                            <span>Pembayaran Anda telah diverifikasi! Silakan ambil barang sewaan di sanggar sesuai tanggal mulai sewa.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Section Konfirmasi Pengembalian (Hanya jika status sewa aktif) -->
                <?php if ($penyewaan['status'] === 'aktif'): ?>
                    <div class="card-detail" style="border-left: 4px solid var(--primary, #6366f1);">
                        <h2 style="font-weight: 700; margin-bottom: 1rem; color: #a5b4fc;">Konfirmasi Pengembalian</h2>

                        <?php if (empty($penyewaan['foto_konfirmasi_pelanggan'])): ?>
                            <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.25rem;">
                                Setelah barang dikembalikan secara fisik ke gudang, upload foto barang yang sudah dikembalikan sebagai bukti konfirmasi.
                            </p>

                            <form enctype="multipart/form-data" method="POST" action="upload_foto_pengembalian.php">
                                <input type="hidden" name="penyewaan_id" value="<?= $id ?>">
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label for="foto_pengembalian" class="form-label">Upload Foto Pengembalian</label>
                                    <input type="file" name="foto_pengembalian" id="foto_pengembalian" class="form-control" accept=".jpg,.jpeg,.png" required>
                                    <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Hanya format JPG, JPEG, PNG. Maksimal 2MB.</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Upload Foto Pengembalian</button>
                                <small style="display: block; color: var(--text-muted); margin-top: 0.75rem; font-size: 0.8rem;">
                                    * Foto akan dilihat oleh admin gudang untuk konfirmasi.
                                </small>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.15); color: #10b981; margin-bottom: 1rem;">
                                <span>Foto pengembalian sudah diterima. Admin gudang akan mencatat pengembalian secara resmi.</span>
                            </div>

                            <h4 style="font-weight: 600; font-size: 0.95rem; margin-bottom: 0.5rem;">Foto Bukti Pengembalian:</h4>
                            <?php 
                                $fotoPengembalianPath = $penyewaan['foto_konfirmasi_pelanggan'];
                                if (strpos($fotoPengembalianPath, 'uploads/') !== 0) {
                                    $fotoPengembalianPath = 'uploads/foto_pengembalian/' . $fotoPengembalianPath;
                                }
                            ?>
                            <img src="../<?= htmlspecialchars($fotoPengembalianPath) ?>" class="proof-preview" alt="Foto Pengembalian">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Section Informasi Denda (Hanya jika status terlambat dan ada record pengembalian) -->
                <?php if ($penyewaan['status'] === 'terlambat' && $pengembalian): ?>
                    <div class="card-detail" style="border-left: 4px solid #ef4444;">
                        <h2 style="font-weight: 700; margin-bottom: 1rem; color: #f87171;">Informasi Denda</h2>
                        
                        <div class="detail-row-info" style="margin-bottom: 0.75rem;">
                            <span>Nominal Denda</span>
                            <span style="color: #ef4444; font-weight: 700; font-size: 1.1rem;"><?= formatRupiah($pengembalian['denda_keterlambatan']) ?></span>
                        </div>

                        <div class="detail-row-info" style="margin-bottom: 1rem;">
                            <span>Status Denda</span>
                            <span>
                                <?php if ($pengembalian['status_denda'] === 'lunas'): ?>
                                    <span class="status-badge" style="background: rgba(16, 185, 129, 0.15); color: #22c55e;">Denda Lunas</span>
                                <?php else: ?>
                                    <span class="status-badge" style="background: rgba(239, 68, 68, 0.15); color: #f87171;">Belum Bayar</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if ($pengembalian['status_denda'] === 'belum_bayar' && empty($pengembalian['bukti_bayar_denda'])): ?>
                            <div style="background: rgba(255, 255, 255, 0.02); border: 1px dashed var(--border); padding: 1.25rem; border-radius: var(--radius-sm); font-size: 0.9rem; margin-top: 1rem;">
                                <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Informasi Rekening Tujuan Pembayaran Denda:</h4>
                                <p style="color: var(--text-muted);">Bank Mandiri: <strong>142-000-123456-7</strong></p>
                                <p style="color: var(--text-muted); margin-bottom: 0.5rem;">A.N. <strong>Sanggar Seni Bedug Yudha</strong></p>
                                <p style="font-size: 0.85rem; color: #f87171;">Harap transfer nominal denda sebesar <strong><?= formatRupiah($pengembalian['denda_keterlambatan']) ?></strong> lalu upload bukti di bawah ini.</p>
                            </div>

                            <form enctype="multipart/form-data" method="POST" action="upload_bukti_denda.php" style="margin-top: 1.5rem;">
                                <input type="hidden" name="penyewaan_id" value="<?= $id ?>">
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label for="bukti_denda" class="form-label">Upload Bukti Bayar Denda</label>
                                    <input type="file" name="bukti_denda" id="bukti_denda" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Hanya JPG, JPEG, PNG, PDF. Maksimal 2MB.</small>
                                </div>
                                <button type="submit" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444;">Upload Bukti Bayar Denda</button>
                            </form>

                        <?php elseif ($pengembalian['status_denda'] === 'belum_bayar' && !empty($pengembalian['bukti_bayar_denda'])): ?>
                            <div class="alert alert-success" style="background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.15); color: #fbbf24; margin-top: 1rem;">
                                <span>Bukti pembayaran denda sedang menunggu verifikasi admin.</span>
                            </div>

                            <h4 style="font-weight: 600; margin-top: 1.5rem; font-size: 0.95rem;">Bukti Pembayaran Denda:</h4>
                            <?php 
                                $buktiDendaPath = $pengembalian['bukti_bayar_denda'];
                                if (strpos($buktiDendaPath, 'uploads/') !== 0) {
                                    $buktiDendaPath = 'uploads/bukti_denda/' . $buktiDendaPath;
                                }
                                $extDenda = strtolower(pathinfo($buktiDendaPath, PATHINFO_EXTENSION));
                                if ($extDenda === 'pdf'): 
                            ?>
                                <a href="../<?= htmlspecialchars($buktiDendaPath) ?>" target="_blank" class="btn btn-secondary" style="margin-top: 0.5rem; text-decoration: none; display: inline-flex;">Buka File PDF Bukti Denda</a>
                            <?php else: ?>
                                <img src="../<?= htmlspecialchars($buktiDendaPath) ?>" class="proof-preview" alt="Bukti Pembayaran Denda">
                            <?php endif; ?>

                        <?php elseif ($pengembalian['status_denda'] === 'lunas'): ?>
                            <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.15); color: #10b981; margin-top: 1rem;">
                                <span>Denda keterlambatan telah lunas diverifikasi.</span>
                            </div>

                            <?php if (!empty($pengembalian['bukti_bayar_denda'])): ?>
                                <h4 style="font-weight: 600; margin-top: 1rem; font-size: 0.95rem;">Bukti Pembayaran Denda:</h4>
                                <?php 
                                    $buktiDendaPath = $pengembalian['bukti_bayar_denda'];
                                    if (strpos($buktiDendaPath, 'uploads/') !== 0) {
                                        $buktiDendaPath = 'uploads/bukti_denda/' . $buktiDendaPath;
                                    }
                                    $extDenda = strtolower(pathinfo($buktiDendaPath, PATHINFO_EXTENSION));
                                    if ($extDenda === 'pdf'): 
                                ?>
                                    <a href="../<?= htmlspecialchars($buktiDendaPath) ?>" target="_blank" class="btn btn-secondary" style="margin-top: 0.5rem; text-decoration: none; display: inline-flex;">Buka File PDF Bukti Denda</a>
                                <?php else: ?>
                                    <img src="../<?= htmlspecialchars($buktiDendaPath) ?>" class="proof-preview" alt="Bukti Pembayaran Denda">
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <a href="pesanan_saya.php" class="btn btn-secondary" style="text-decoration: none;">Kembali ke Riwayat</a>
            </div>
        </div>
    </div>
</body>
</html>
