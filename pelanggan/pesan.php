<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Proteksi halaman pelanggan
requireRole('pelanggan');
$user = getCurrentUser();

$error_msg = null;
$old = [];

// Tangkap flash error & data lama dari session
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['old_input'])) {
    $old = $_SESSION['old_input'];
    unset($_SESSION['old_input']);
}

// Ambil item_id dari GET parameter (jika diakses dari tombol Sewa di katalog)
$preselect_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Ambil semua peralatan yang tersedia (stok > 0 dan kondisi baik)
try {
    $stmt = $pdo->query("SELECT * FROM peralatan WHERE stok > 0 AND kondisi = 'baik' ORDER BY nama_alat ASC");
    $peralatan_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = 'Gagal memuat daftar peralatan tari.';
    $peralatan_list = [];
}

// Proses POST Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_sewa    = isset($_POST['tanggal_sewa']) ? trim($_POST['tanggal_sewa']) : '';
    $tanggal_kembali = isset($_POST['tanggal_kembali']) ? trim($_POST['tanggal_kembali']) : '';
    $catatan         = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';
    $selected_items  = isset($_POST['items']) ? $_POST['items'] : []; // Array of equipment IDs
    $qtys            = isset($_POST['qty']) ? $_POST['qty'] : [];     // Associative array [id => qty]

    $_SESSION['old_input'] = $_POST;

    // 1. Validasi input dasar
    if ($tanggal_sewa === '' || $tanggal_kembali === '') {
        $_SESSION['error'] = 'Tanggal sewa dan tanggal kembali wajib diisi.';
        header('Location: pesan.php');
        exit();
    }

    if (empty($selected_items)) {
        $_SESSION['error'] = 'Anda harus memilih minimal 1 peralatan untuk disewa.';
        header('Location: pesan.php');
        exit();
    }

    // 2. Validasi selisih tanggal (maksimal 3 hari)
    try {
        $tgl_sewa_dt = new DateTime($tanggal_sewa);
        $tgl_kembali_dt = new DateTime($tanggal_kembali);
        $diff = $tgl_sewa_dt->diff($tgl_kembali_dt);
        
        // Cek jika tanggal kembali mendahului tanggal sewa
        if ($tgl_kembali_dt < $tgl_sewa_dt) {
            $_SESSION['error'] = 'Tanggal kembali tidak boleh sebelum tanggal sewa.';
            header('Location: pesan.php');
            exit();
        }
        
        // Cek selisih hari
        if ($diff->days > 3) {
            $_SESSION['error'] = 'Durasi sewa maksimal adalah 3 hari.';
            header('Location: pesan.php');
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Format tanggal tidak valid.';
        header('Location: pesan.php');
        exit();
    }

    // 3. Database Transaction
    try {
        $pdo->beginTransaction();

        $total_biaya = 0;
        $items_to_save = [];

        // Validasi ketersediaan stok & hitung subtotal untuk setiap item terpilih
        foreach ($selected_items as $item_id) {
            $item_id = (int)$item_id;
            $qty_input = isset($qtys[$item_id]) ? (int)$qtys[$item_id] : 0;

            if ($qty_input <= 0) {
                throw new Exception('Jumlah sewa untuk item terpilih harus lebih dari 0.');
            }

            // Ambil stok & harga dari DB secara aman
            $stmtCheck = $pdo->prepare("SELECT nama_alat, stok, harga_sewa FROM peralatan WHERE id = ? FOR UPDATE");
            $stmtCheck->execute([$item_id]);
            $item_db = $stmtCheck->fetch();

            if (!$item_db) {
                throw new Exception('Peralatan tidak ditemukan.');
            }

            if ($qty_input > $item_db['stok']) {
                throw new Exception("Stok untuk peralatan '{$item_db['nama_alat']}' tidak mencukupi (Tersedia: {$item_db['stok']}, diminta: {$qty_input}).");
            }

            // Kalkulasi subtotal (flat rate per item × jumlah_pinjam)
            $subtotal = $qty_input * (float)$item_db['harga_sewa'];
            $total_biaya += $subtotal;

            $items_to_save[] = [
                'id' => $item_id,
                'qty' => $qty_input
            ];
        }

        // INSERT penyewaan (status='menunggu', status_pembayaran='belum_bayar')
        // JANGAN kurangi stok di sini!
        $stmtInsertSewa = $pdo->prepare("
            INSERT INTO penyewaan (user_id, tanggal_sewa, tanggal_kembali, total_biaya, status, status_pembayaran, catatan)
            VALUES (?, ?, ?, ?, 'menunggu', 'belum_bayar', ?)
        ");
        $stmtInsertSewa->execute([
            $user['id'],
            $tanggal_sewa,
            $tanggal_kembali,
            $total_biaya,
            $catatan === '' ? null : $catatan
        ]);

        $penyewaan_id = $pdo->lastInsertId();

        // INSERT detail_penyewaan
        $stmtInsertDetail = $pdo->prepare("
            INSERT INTO detail_penyewaan (penyewaan_id, peralatan_id, jumlah_pinjam)
            VALUES (?, ?, ?)
        ");
        foreach ($items_to_save as $save_item) {
            $stmtInsertDetail->execute([
                $penyewaan_id,
                $save_item['id'],
                $save_item['qty']
            ]);
        }

        $pdo->commit();
        unset($_SESSION['old_input']);

        $_SESSION['success'] = 'Pesanan sewa berhasil dibuat! Silakan lakukan pembayaran.';
        header("Location: detail_pesanan.php?id=" . $penyewaan_id);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Gagal menyimpan pesanan sewa: ' . $e->getMessage();
        header('Location: pesan.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Pemesanan Sewa - Sewa Tari</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

        .item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .item-qty-input {
            width: 70px;
            text-align: center;
        }

        .price-summary {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.25rem;
            font-weight: 700;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--border);
            color: #ffffff;
        }
        
        .checkbox-container {
            position: relative;
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
        }

        .checkbox-container input {
            cursor: pointer;
            width: 1.2rem;
            height: 1.2rem;
        }

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
            <h1 style="margin-top: 0.5rem;">Formulir Penyewaan</h1>
            <p>Tentukan tanggal sewa dan perlengkapan tari yang ingin Anda pesan.</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger" id="pesan-error-alert">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form action="pesan.php" method="POST" id="sewa-form">
            <div class="form-layout">
                <div class="booking-card">
                    <h2 style="font-weight: 700; margin-bottom: 1.5rem;">Detail Penyewaan</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tanggal_sewa" class="form-label">Tanggal Mulai Sewa</label>
                            <input type="text" readonly name="tanggal_sewa" id="tanggal_sewa" class="form-control" value="<?= htmlspecialchars($old['tanggal_sewa'] ?? date('Y-m-d')) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="tanggal_kembali" class="form-label">Tanggal Batas Kembali</label>
                            <input type="text" readonly name="tanggal_kembali" id="tanggal_kembali" class="form-control" value="<?= htmlspecialchars($old['tanggal_kembali'] ?? date('Y-m-d', strtotime('+3 days'))) ?>" required>
                            <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Durasi sewa maksimal 3 hari.</small>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 2rem;">
                        <h3 style="font-weight: 600; margin-bottom: 1rem; font-size: 1.1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Pilih Item yang Disewa</h3>
                        
                        <?php if (count($peralatan_list) > 0): ?>
                            <?php foreach ($peralatan_list as $row): ?>
                                <?php 
                                    // Deteksi pre-selected item dari query string
                                    $is_checked = false;
                                    if ($preselect_id > 0 && (int)$row['id'] === $preselect_id) {
                                        $is_checked = true;
                                    } elseif (isset($old['items']) && in_array($row['id'], $old['items'])) {
                                        $is_checked = true;
                                    }
                                    
                                    $old_qty = isset($old['qty'][$row['id']]) ? (int)$old['qty'][$row['id']] : 1;
                                ?>
                                <div class="item-row">
                                    <div class="item-info">
                                        <label class="checkbox-container">
                                            <input type="checkbox" name="items[]" value="<?= $row['id'] ?>" class="item-checkbox" data-harga="<?= $row['harga_sewa'] ?>" data-nama="<?= htmlspecialchars($row['nama_alat']) ?>" <?= $is_checked ? 'checked' : '' ?>>
                                        </label>
                                        <div style="margin-left: 0.75rem;">
                                            <span style="font-weight: 500; display: block;"><?= htmlspecialchars($row['nama_alat']) ?></span>
                                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: capitalize;"><?= htmlspecialchars($row['kategori_alat']) ?> • <?= formatRupiah($row['harga_sewa']) ?></span>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <span style="font-size: 0.8rem; color: var(--text-muted);">Maks: <?= $row['stok'] ?></span>
                                        <input type="number" name="qty[<?= $row['id'] ?>]" class="form-control item-qty-input" min="1" max="<?= $row['stok'] ?>" value="<?= $old_qty ?>" <?= $is_checked ? '' : 'disabled' ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--text-muted); text-align: center; padding: 1rem 0;">Tidak ada peralatan tari kondisi baik yang tersedia saat ini.</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label for="catatan" class="form-label">Catatan Tambahan (Opsional)</label>
                        <textarea name="catatan" id="catatan" class="form-control" placeholder="Tuliskan catatan khusus terkait penjaminan, ukuran kostum, atau request lainnya..."><?= htmlspecialchars($old['catatan'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="summary-card">
                    <h2 style="font-weight: 700; margin-bottom: 1.5rem;">Ringkasan Pesanan</h2>
                    
                    <div id="summary-items-list" style="min-height: 100px; max-height: 250px; overflow-y: auto; margin-bottom: 1rem;">
                        <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding-top: 2rem;">Belum ada item terpilih.</p>
                    </div>

                    <div class="price-summary">
                        <div class="price-row" style="color: var(--text-muted);">
                            <span>Sistem Harga</span>
                            <span>Tarif Flat (Bukan Per Hari)</span>
                        </div>
                        <div class="price-row" style="color: var(--text-muted);">
                            <span>Durasi Pinjam</span>
                            <span id="summary-duration">0 Hari</span>
                        </div>
                        <div class="total-row">
                            <span>Total Biaya</span>
                            <span id="summary-total-cost">Rp 0</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem; font-size: 1.05rem;">Konfirmasi & Sewa Barang</button>
                    <a href="katalog.php" class="btn btn-secondary" style="margin-top: 0.5rem; text-decoration: none;">Batal</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('sewa-form');
            const checkboxes = document.querySelectorAll('.item-checkbox');
            const qtyInputs = document.querySelectorAll('.item-qty-input');
            const tanggalSewaInput = document.getElementById('tanggal_sewa');
            const tanggalKembaliInput = document.getElementById('tanggal_kembali');
            const summaryList = document.getElementById('summary-items-list');
            const summaryDuration = document.getElementById('summary-duration');
            const summaryTotalCost = document.getElementById('summary-total-cost');

            // Toggle input qty berdasarkan status checkbox
            checkboxes.forEach((cb, index) => {
                cb.addEventListener('change', () => {
                    qtyInputs[index].disabled = !cb.checked;
                    if (cb.checked) {
                        qtyInputs[index].focus();
                    }
                    updateSummary();
                });
            });

            // Update summary saat qty diubah
            qtyInputs.forEach(input => {
                input.addEventListener('input', () => {
                    const max = parseInt(input.getAttribute('max')) || 0;
                    const val = parseInt(input.value) || 0;
                    if (val > max) input.value = max;
                    if (val < 1) input.value = 1;
                    updateSummary();
                });
            });

            // Update summary saat tanggal berubah
            tanggalSewaInput.addEventListener('change', updateSummary);
            tanggalKembaliInput.addEventListener('change', updateSummary);

            function formatRupiahJS(number) {
                return 'Rp ' + number.toLocaleString('id-ID');
            }

            function updateSummary() {
                // 1. Hitung Durasi Sewa
                let durasiHari = 0;
                if (tanggalSewaInput.value && tanggalKembaliInput.value) {
                    const tglSewa = new Date(tanggalSewaInput.value);
                    const tglKembali = new Date(tanggalKembaliInput.value);
                    
                    // Reset time
                    tglSewa.setHours(0,0,0,0);
                    tglKembali.setHours(0,0,0,0);

                    const diffTime = tglKembali - tglSewa;
                    durasiHari = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                }

                if (durasiHari < 0) {
                    summaryDuration.innerText = 'Tanggal Kembali Tidak Valid';
                    summaryDuration.style.color = 'var(--danger)';
                } else {
                    summaryDuration.innerText = durasiHari + ' Hari';
                    summaryDuration.style.color = 'var(--text)';
                }

                // 2. Render item terpilih ke panel ringkasan & hitung total
                let totalCost = 0;
                let selectedHtml = '';

                checkboxes.forEach((cb, index) => {
                    if (cb.checked) {
                        const nama = cb.getAttribute('data-nama');
                        const harga = parseFloat(cb.getAttribute('data-harga')) || 0;
                        const qty = parseInt(qtyInputs[index].value) || 1;
                        const subtotal = harga * qty;
                        totalCost += subtotal;

                        selectedHtml += `
                            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                <span>${nama} <strong style="color: var(--primary);">x${qty}</strong></span>
                                <span>${formatRupiahJS(subtotal)}</span>
                            </div>
                        `;
                    }
                });

                if (selectedHtml === '') {
                    summaryList.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding-top: 2rem;">Belum ada item terpilih.</p>';
                } else {
                    summaryList.innerHTML = selectedHtml;
                }

                summaryTotalCost.innerText = formatRupiahJS(totalCost);
            }

            // Validasi submit form di sisi klien
            form.addEventListener('submit', (e) => {
                // Check if any item selected
                const selectedCount = document.querySelectorAll('.item-checkbox:checked').length;
                if (selectedCount === 0) {
                    alert('Silakan pilih minimal 1 peralatan tari yang ingin disewa.');
                    e.preventDefault();
                    return;
                }

                // Check date validation
                const tglSewa = new Date(tanggalSewaInput.value);
                const tglKembali = new Date(tanggalKembaliInput.value);
                tglSewa.setHours(0,0,0,0);
                tglKembali.setHours(0,0,0,0);

                if (tglKembali < tglSewa) {
                    alert('Tanggal batas pengembalian tidak boleh sebelum tanggal mulai sewa.');
                    e.preventDefault();
                    return;
                }

                const diffTime = tglKembali - tglSewa;
                const durasiHari = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                if (durasiHari > 3) {
                    alert('Durasi sewa tidak boleh lebih dari 3 hari sesuai dengan aturan sanggar.');
                    e.preventDefault();
                    return;
                }
            });

            // Jalankan inisialisasi awal
            updateSummary();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      flatpickr("#tanggal_sewa", {
        dateFormat: "Y-m-d",
        minDate: "today",
        disableMobile: false,
        onChange: function(selectedDates, dateStr) {
          // Update minDate tanggal_kembali saat tanggal_sewa dipilih
          var minKembali = new Date(selectedDates[0]);
          minKembali.setDate(minKembali.getDate() + 1);
          var maxKembali = new Date(selectedDates[0]);
          maxKembali.setDate(maxKembali.getDate() + 3);
          pickerKembali.set('minDate', minKembali);
          pickerKembali.set('maxDate', maxKembali);
          
          // Trigger native change event for summary calculation
          document.getElementById('tanggal_sewa').dispatchEvent(new Event('change'));
        }
      });
      var pickerKembali = flatpickr("#tanggal_kembali", {
        dateFormat: "Y-m-d",
        disableMobile: false,
        onChange: function(selectedDates, dateStr) {
          // Trigger native change event for summary calculation
          document.getElementById('tanggal_kembali').dispatchEvent(new Event('change'));
        }
      });
    </script>
    <script>
    // Auto-trigger kalkulasi jika ada item pre-selected
    document.addEventListener('DOMContentLoaded', function() {
        var preChecked = document.querySelector(
            'input[type="checkbox"]:checked'
        );
        if (preChecked) {
            preChecked.dispatchEvent(new Event('change',
                { bubbles: true }));
        }
    });
    </script>
</body>
</html>
