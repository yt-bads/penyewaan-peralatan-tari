<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_gudang
requireRole('admin_gudang');
$user = getCurrentUser();

$error_msg = null;
$success_msg = null;

// Tangkap flash error/success dari session
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash'] = 'ID Pesanan tidak valid.';
    header('Location: daftar_pengembalian.php');
    exit();
}

try {
    // 1. Ambil data sewa & info pelanggan
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama AS nama_pelanggan, u.username
        FROM penyewaan p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $penyewaan = $stmt->fetch();

    if (!$penyewaan) {
        $_SESSION['flash'] = 'Transaksi penyewaan tidak ditemukan.';
        header('Location: daftar_pengembalian.php');
        exit();
    }

    // Pastikan status sewa adalah 'aktif'
    if ($penyewaan['status'] !== 'aktif') {
        $_SESSION['flash'] = 'Transaksi ini tidak berstatus AKTIF (tidak dapat dicatat pengembalian).';
        header('Location: daftar_pengembalian.php');
        exit();
    }

    // Pastikan belum pernah dicatat pengembalian sebelumnya (menghindari duplikasi)
    $stmtCheckReturn = $pdo->prepare("SELECT id FROM pengembalian WHERE penyewaan_id = ?");
    $stmtCheckReturn->execute([$id]);
    if ($stmtCheckReturn->fetch()) {
        $_SESSION['flash'] = 'Pengembalian untuk transaksi ini sudah pernah dicatat sebelumnya.';
        header('Location: daftar_pengembalian.php');
        exit();
    }

    // Ambil detail barang yang disewa & total item
    $stmtDetail = $pdo->prepare("
        SELECT dp.jumlah_pinjam, p.nama_alat, p.kategori_alat, p.harga_sewa 
        FROM detail_penyewaan dp
        JOIN peralatan p ON dp.peralatan_id = p.id
        WHERE dp.penyewaan_id = ?
    ");
    $stmtDetail->execute([$id]);
    $items = $stmtDetail->fetchAll();

    // Hitung total_item (total unit barang yang dipinjam)
    $total_item = 0;
    foreach ($items as $item) {
        $total_item += (int)$item['jumlah_pinjam'];
    }

} catch (PDOException $e) {
    $_SESSION['flash'] = 'Kesalahan database: ' . $e->getMessage();
    header('Location: daftar_pengembalian.php');
    exit();
}

// Proses POST Form Catat Pengembalian
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_dikembalikan = isset($_POST['tanggal_dikembalikan']) ? trim($_POST['tanggal_dikembalikan']) : '';
    $kondisi_peralatan    = isset($_POST['kondisi_peralatan']) ? trim($_POST['kondisi_peralatan']) : '';

    if ($tanggal_dikembalikan === '') {
        $_SESSION['error'] = 'Tanggal dikembalikan wajib diisi.';
        header("Location: catat_pengembalian.php?id=" . $id);
        exit();
    }

    try {
        // Cek jika tanggal dikembalikan mendahului tanggal sewa
        if (strtotime($tanggal_dikembalikan) < strtotime($penyewaan['tanggal_sewa'])) {
            throw new Exception('Tanggal dikembalikan tidak boleh mendahului tanggal mulai sewa (' . formatTanggalIndo($penyewaan['tanggal_sewa']) . ').');
        }

        // Mulai DB Transaction
        $pdo->beginTransaction();

        // Tentukan keterlambatan & denda
        $is_terlambat = strtotime($tanggal_dikembalikan) > strtotime($penyewaan['tanggal_kembali']);
        $denda = $is_terlambat ? (50000 * $total_item) : 0;
        $status_baru = $is_terlambat ? 'terlambat' : 'selesai';

        // 1. INSERT ke tabel pengembalian
        $stmtInsertReturn = $pdo->prepare("
            INSERT INTO pengembalian (penyewaan_id, tanggal_dikembalikan, denda_keterlambatan, kondisi_peralatan, dicatat_oleh)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtInsertReturn->execute([
            $id,
            $tanggal_dikembalikan,
            $denda,
            $kondisi_peralatan === '' ? null : $kondisi_peralatan,
            $user['id']
        ]);

        // 2. UPDATE status di tabel penyewaan
        $stmtUpdateSewa = $pdo->prepare("UPDATE penyewaan SET status = ? WHERE id = ?");
        $stmtUpdateSewa->execute([$status_baru, $id]);

        // 3. Kembalikan stok & catat riwayat log masuk (masuk)
        // memanggil tambahStok() yang berpartisipasi dalam transaksi aktif ini
        tambahStok($id, $pdo);

        // Commit transaction jika seluruh proses berhasil
        $pdo->commit();

        // Set flash message denda
        $_SESSION['flash'] = "Pengembalian dicatat dengan sukses. Status: " . strtoupper($status_baru) . ". Denda: " . formatRupiah($denda);
        header('Location: daftar_pengembalian.php');
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Gagal mencatat pengembalian: ' . $e->getMessage();
        header("Location: catat_pengembalian.php?id=" . $id);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catat Pengembalian #<?= $id ?> - Admin Gudang</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-layout {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 2rem;
            align-items: start;
        }

        .booking-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 2rem;
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
        }

        .premium-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        .premium-table th {
            padding: 0.75rem 1rem;
            border-bottom: 2px solid var(--border);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .premium-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        .preview-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            margin-top: 1.5rem;
            text-align: center;
        }

        .preview-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .preview-value {
            font-size: 1.4rem;
            font-weight: 800;
        }

        .text-ontime { color: #10b981; }
        .text-late { color: #ef4444; }

        @media (max-width: 992px) {
            .form-layout {
                grid-template-columns: 1fr;
            }
            .summary-card {
                position: static;
            }
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
            <a href="daftar_pengembalian.php" class="nav-link active">Pengembalian</a>
            <a href="laporan_denda.php" class="nav-link">Laporan Denda</a>
            <a href="profil.php" class="nav-link">Profil</a>
            <a href="../logout.php" class="btn btn-secondary btn-logout">Keluar</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <span class="role-badge badge-admin-gudang">Admin Gudang</span>
            <h1 style="margin-top: 0.5rem;">Pencatatan Pengembalian</h1>
            <p>Masukkan data pengembalian perlengkapan tari yang dikembalikan pelanggan.</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger" id="error-alert">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Tampilan Foto Konfirmasi Pengembalian Pelanggan -->
        <div class="booking-card" style="margin-bottom: 2rem; border-left: 4px solid <?= !empty($penyewaan['foto_konfirmasi_pelanggan']) ? '#10b981' : '#f59e0b' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h2 style="font-weight: 700; font-size: 1.2rem; margin: 0;">Konfirmasi Pengembalian oleh Pelanggan</h2>
                <?php if (!empty($penyewaan['foto_konfirmasi_pelanggan'])): ?>
                    <span class="status-badge" style="background: rgba(16, 185, 129, 0.15); color: #22c55e; font-size: 0.85rem; padding: 0.35rem 0.75rem;">Pelanggan sudah upload foto</span>
                <?php else: ?>
                    <span class="status-badge" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-size: 0.85rem; padding: 0.35rem 0.75rem;">Pelanggan belum upload foto</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($penyewaan['foto_konfirmasi_pelanggan'])): ?>
                <?php 
                    $fotoPath = $penyewaan['foto_konfirmasi_pelanggan'];
                    if (strpos($fotoPath, 'uploads/') !== 0) {
                        $fotoPath = 'uploads/foto_pengembalian/' . $fotoPath;
                    }
                ?>
                <div style="margin-top: 1rem; margin-bottom: 0.75rem;">
                    <img src="../<?= htmlspecialchars($fotoPath) ?>" alt="Foto Konfirmasi Pengembalian" style="max-height: 200px; border-radius: var(--radius-sm); border: 1px solid var(--border); object-fit: contain; background: #111;">
                </div>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                    Foto ini diupload pelanggan sebagai bukti pengembalian fisik.
                </p>
            <?php else: ?>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                    Pelanggan belum mengkonfirmasi pengembalian, tapi Anda tetap bisa mencatat pengembalian.
                </p>
            <?php endif; ?>
        </div>

        <?php if (empty($penyewaan['foto_konfirmasi_pelanggan'])): ?>
            <div style="
                background-color: #FEF9E7;
                border-left: 4px solid #F39C12;
                border: 1px solid #F39C12;
                border-radius: 6px;
                padding: 14px 16px;
                margin-bottom: 20px;
                display: flex;
                align-items: flex-start;
                gap: 10px;
            ">
                <span style="font-size: 18px; line-height: 1;">⚠️</span>
                <div>
                    <p style="margin: 0 0 4px 0; font-weight: bold;
                              color: #B7770D; font-size: 13px;">
                        Pelanggan Belum Upload Foto Pengembalian
                    </p>
                    <p style="margin: 0; color: #7D6608; font-size: 12px;">
                        Pastikan barang sudah benar-benar dikembalikan
                        secara fisik sebelum mencatat pengembalian ini.
                        Admin tetap dapat melanjutkan pencatatan.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <form action="catat_pengembalian.php?id=<?= $id ?>" method="POST" id="pengembalian-form">
            <div class="form-layout">
                <!-- Data Masukan -->
                <div class="booking-card">
                    <h2 style="font-weight: 700; margin-bottom: 1.5rem;">Detail Pengembalian</h2>
                    
                    <div class="form-group">
                        <label for="tanggal_dikembalikan" class="form-label">Tanggal Dikembalikan</label>
                        <input type="date" name="tanggal_dikembalikan" id="tanggal_dikembalikan" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="kondisi_peralatan" class="form-label">Catatan Kondisi Barang Saat Kembali</label>
                        <textarea name="kondisi_peralatan" id="kondisi_peralatan" class="form-control" placeholder="Contoh: 2 kostum kembali bersih, 1 kipas patah gagang... (opsional)"></textarea>
                    </div>

                    <h3 style="font-weight: 600; margin-top: 2rem; margin-bottom: 0.5rem; font-size: 1.05rem;">Rincian Barang yang Disewa</h3>
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Nama Alat</th>
                                <th>Kategori</th>
                                <th style="text-align: center;">Jumlah Pinjam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nama_alat']) ?></td>
                                    <td style="text-transform: capitalize;"><?= str_replace('_', ' ', $row['kategori_alat']) ?></td>
                                    <td style="text-align: center; font-weight: 600;"><?= $row['jumlah_pinjam'] ?> unit</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Preview Panel & Info Sewa -->
                <div class="summary-card">
                    <h2 style="font-weight: 700; margin-bottom: 1.5rem;">Info Batas Waktu</h2>
                    
                    <div class="detail-row-info">
                        <span>Penyewa</span>
                        <span><?= htmlspecialchars($penyewaan['nama_pelanggan']) ?></span>
                    </div>
                    <div class="detail-row-info">
                        <span>Tanggal Mulai Sewa</span>
                        <span><?= formatTanggalIndo($penyewaan['tanggal_sewa']) ?></span>
                    </div>
                    <div class="detail-row-info">
                        <span>Tanggal Batas Kembali</span>
                        <span><?= formatTanggalIndo($penyewaan['tanggal_kembali']) ?></span>
                    </div>
                    <div class="detail-row-info">
                        <span>Total Jumlah Item</span>
                        <span><?= $total_item ?> unit</span>
                    </div>

                    <!-- Panel Preview Denda Otomatis -->
                    <div class="preview-box">
                        <div class="preview-label">Kalkulasi Denda</div>
                        <div id="denda-preview-text" class="preview-value text-ontime">Rp 0</div>
                        <small id="denda-status-text" style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Tepat waktu — Tidak ada denda</small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem; font-size: 1.05rem;">Simpan Pengembalian</button>
                    <a href="daftar_pengembalian.php" class="btn btn-secondary" style="margin-top: 0.5rem; text-decoration: none;">Kembali</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tanggalDikembalikanInput = document.getElementById('tanggal_dikembalikan');
            const dendaPreviewText = document.getElementById('denda-preview-text');
            const dendaStatusText = document.getElementById('denda-status-text');

            // Data dari server-side
            const totalItem = <?= $total_item ?>;
            const tanggalKembaliStr = '<?= $penyewaan['tanggal_kembali'] ?>';
            const tanggalSewaStr = '<?= $penyewaan['tanggal_sewa'] ?>';

            function formatRupiahJS(number) {
                return 'Rp ' + number.toLocaleString('id-ID');
            }

            function updateDendaPreview() {
                if (!tanggalDikembalikanInput.value) return;

                const inputDate = new Date(tanggalDikembalikanInput.value);
                const limitDate = new Date(tanggalKembaliStr);
                const sewaDate = new Date(tanggalSewaStr);

                // Reset jam ke 00:00:00
                inputDate.setHours(0,0,0,0);
                limitDate.setHours(0,0,0,0);
                sewaDate.setHours(0,0,0,0);

                if (inputDate < sewaDate) {
                    dendaPreviewText.innerText = 'Tanggal Salah';
                    dendaPreviewText.className = 'preview-value text-late';
                    dendaStatusText.innerText = 'Sebelum tanggal mulai sewa!';
                    return;
                }

                // Bandingkan tanggal dikembalikan dengan batas kembali
                if (inputDate > limitDate) {
                    const dendaTotal = totalItem * 50000;
                    dendaPreviewText.innerText = formatRupiahJS(dendaTotal);
                    dendaPreviewText.className = 'preview-value text-late';
                    dendaStatusText.innerText = 'Terlambat — Denda flat Rp 50.000 × ' + totalItem + ' item';
                } else {
                    dendaPreviewText.innerText = 'Rp 0';
                    dendaPreviewText.className = 'preview-value text-ontime';
                    dendaStatusText.innerText = 'Tepat waktu — Bebas denda';
                }
            }

            // Jalankan saat input tanggal diubah
            tanggalDikembalikanInput.addEventListener('change', updateDendaPreview);

            // Inisialisasi awal
            updateDendaPreview();
        });
    </script>
</body>
</html>
