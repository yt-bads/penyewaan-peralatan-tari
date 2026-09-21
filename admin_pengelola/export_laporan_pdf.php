<?php
ob_start();
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role admin_pengelola
requireRole('admin_pengelola');

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

// Tentukan mode
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
    die("Kesalahan database saat mengekstrak data laporan: " . $e->getMessage());
}

// Persiapkan statement untuk detail item (hanya mode header)
if (!$is_detail_mode) {
    $stmtItems = $pdo->prepare("
        SELECT dp.jumlah_pinjam, p.nama_alat 
        FROM detail_penyewaan dp
        JOIN peralatan p ON dp.peralatan_id = p.id
        WHERE dp.penyewaan_id = ?
    ");
}

// Convert logo to base64 for PDF embedding
$logo_path = realpath(__DIR__ . '/../assets/img/logo.png');
$logo_base64 = '';
$logo_html = '';
if ($logo_path && file_exists($logo_path)) {
    $logo_base64 = base64_encode(file_get_contents($logo_path));
    $logo_src = 'data:image/png;base64,' . $logo_base64;
    $logo_html = '<img src="' . $logo_src . '" style="height:70px; display: block; margin: 0 auto 10px auto;">';
}

$kategori_label = ($kategori_filter !== '') ? htmlspecialchars($kategori_filter) : 'Semua';

// Bangun HTML String
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penyewaan Peralatan Tari</title>
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 18px; margin: 0 0 5px 0; text-transform: uppercase; font-weight: bold; }
        .header p { margin: 0; font-size: 12px; color: #666; }
        .meta-info { margin-bottom: 15px; font-size: 11px; }
        .report-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .report-table th { background-color: #f2f2f2; border: 1px solid #ddd; padding: 8px; font-weight: bold; text-align: left; text-transform: uppercase; font-size: 10px; }
        .report-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        .total-row { font-weight: bold; background-color: #fafafa; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .status-menunggu { background-color: #fef3c7; color: #d97706; }
        .status-aktif { background-color: #e0f2fe; color: #0284c7; }
        .status-selesai { background-color: #d1fae5; color: #059669; }
        .status-terlambat { background-color: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
    <div class="header">
        ' . $logo_html . '
        <h1>Laporan Penyewaan Peralatan Tari</h1>
        <p>Sanggar Seni Bedug Yudha</p>
    </div>

    <div class="meta-info">
        <strong>Tanggal Cetak:</strong> ' . formatTanggalIndo(date('Y-m-d')) . '<br>
        <strong>Filter Periode:</strong> ' . ($tanggal_mulai !== '' ? formatTanggalIndo($tanggal_mulai) : 'Semua') . ' s/d ' . ($tanggal_akhir !== '' ? formatTanggalIndo($tanggal_akhir) : 'Semua') . '<br>
        <strong>Filter Status:</strong> ' . ($status_filter !== '' ? strtoupper($status_filter) : 'Semua') . '<br>
        <strong>Filter Kategori:</strong> ' . $kategori_label . '
    </div>

    <table class="report-table">
        <thead>
            <tr>';

if ($is_detail_mode) {
    $html .= '
                <th style="width: 10%;">No. Pesanan</th>
                <th style="width: 18%;">Nama Pelanggan</th>
                <th>Nama Peralatan</th>
                <th style="width: 8%; text-align: center;">Jumlah</th>
                <th style="width: 12%;">Tgl Sewa</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 14%; text-align: right;">Biaya Item</th>';
} else {
    $html .= '
                <th style="width: 8%;">No. Transaksi</th>
                <th style="width: 15%;">Nama Pelanggan</th>
                <th style="width: 10%;">Tgl Sewa</th>
                <th style="width: 10%;">Batas Kembali</th>
                <th>Daftar Peralatan</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 10%;">Pembayaran</th>
                <th style="width: 10%;">Denda</th>
                <th style="width: 12%; text-align: right;">Total Biaya</th>';
}

$html .= '
            </tr>
        </thead>
        <tbody>';

$total_pendapatan = 0;

if ($is_detail_mode) {
    // Mode detail-level
    if (count($report_list) > 0) {
        foreach ($report_list as $row) {
            $subtotal = (float)$row['subtotal_item'];
            if ($row['status_pembayaran'] === 'lunas') {
                $total_pendapatan += $subtotal;
            }

            $badge_status = '<span class="badge status-' . $row['status'] . '">' . $row['status'] . '</span>';

            $html .= '
            <tr>
                <td style="font-weight: bold;">#' . $row['id'] . '</td>
                <td>' . htmlspecialchars($row['nama_pelanggan']) . '</td>
                <td>' . htmlspecialchars($row['nama_alat']) . '</td>
                <td style="text-align: center;">' . $row['jumlah_pinjam'] . ' unit</td>
                <td>' . formatTanggalIndo($row['tanggal_sewa']) . '</td>
                <td>' . $badge_status . '</td>
                <td style="font-weight: bold; text-align: right;">' . formatRupiah($subtotal) . '</td>
            </tr>';
        }
    } else {
        $html .= '
        <tr>
            <td colspan="7" style="text-align: center; color: #999;">Tidak ada data peralatan kategori ' . htmlspecialchars($kategori_filter) . '.</td>
        </tr>';
    }

    $html .= '
        <tr class="total-row">
            <td colspan="6" style="text-align: right; font-size: 11px; padding: 10px;">Total Pendapatan Terverifikasi (Lunas):</td>
            <td style="text-align: right; color: #059669; font-size: 12px; padding: 10px;">' . formatRupiah($total_pendapatan) . '</td>
        </tr>';
} else {
    // Mode header-level
    if (count($report_list) > 0) {
        foreach ($report_list as $row) {
            $stmtItems->execute([$row['id']]);
            $row_items = $stmtItems->fetchAll();
            $item_summaries = [];
            foreach ($row_items as $item) {
                $item_summaries[] = $item['nama_alat'] . " (" . $item['jumlah_pinjam'] . ")";
            }
            $item_text = implode(', ', $item_summaries);

            if ($row['status_pembayaran'] === 'lunas') {
                $total_pendapatan += (float)$row['total_biaya'];
            }

            $badge_status = '<span class="badge status-' . $row['status'] . '">' . $row['status'] . '</span>';
            $badge_bayar = '<span class="badge status-' . ($row['status_pembayaran'] === 'belum_bayar' ? 'terlambat' : ($row['status_pembayaran'] === 'menunggu_verifikasi' ? 'menunggu' : 'selesai')) . '">' . $row['status_pembayaran'] . '</span>';

            $html .= '
            <tr>
                <td style="font-weight: bold;">#' . $row['id'] . '</td>
                <td>' . htmlspecialchars($row['nama_pelanggan']) . '</td>
                <td>' . formatTanggalIndo($row['tanggal_sewa']) . '</td>
                <td>' . formatTanggalIndo($row['tanggal_kembali']) . '</td>
                <td>' . ($item_text === '' ? '-' : htmlspecialchars($item_text)) . '</td>
                <td>' . $badge_status . '</td>
                <td>' . $badge_bayar . '</td>
                <td>' . ($row['denda_keterlambatan'] > 0 ? formatRupiah($row['denda_keterlambatan']) : 'Rp 0') . '</td>
                <td style="font-weight: bold; text-align: right;">' . formatRupiah($row['total_biaya']) . '</td>
            </tr>';
        }
    } else {
        $html .= '
        <tr>
            <td colspan="9" style="text-align: center; color: #999;">Tidak ada data laporan penyewaan.</td>
        </tr>';
    }

    $html .= '
        <tr class="total-row">
            <td colspan="8" style="text-align: right; font-size: 11px; padding: 10px;">Total Pendapatan Terverifikasi (Lunas):</td>
            <td style="text-align: right; color: #059669; font-size: 12px; padding: 10px;">' . formatRupiah($total_pendapatan) . '</td>
        </tr>';
}

$html .= '
        </tbody>
    </table>

<div style="margin-top: 50px; text-align: right;
            padding-right: 60px; font-family: Arial, sans-serif;
            font-size: 12px;">
  <p style="margin-bottom: 0;">Serang, ' . formatTanggalIndo(date('Y-m-d')) . '</p>
  <p style="font-weight: bold; margin-top: 4px;">Ketua Sanggar Seni Bedug Yudha</p>
  <br><br><br>
  <p style="border-top: 1px solid #000;
            display: inline-block; min-width: 180px;
            padding-top: 4px;"></p>
</div>

</body>
</html>';

// Load Dompdf dan render PDF
require_once '../vendor/autoload.php';
$dompdf = new Dompdf\Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
ob_end_clean();
$dompdf->render();

// Output unduh
$filename = 'laporan_penyewaan';
if ($kategori_filter !== '') {
    $filename .= '_' . strtolower(str_replace(' ', '_', $kategori_filter));
}
$filename .= '_' . date('Y-m-d') . '.pdf';

$dompdf->stream($filename, ['Attachment' => true]);
exit();
