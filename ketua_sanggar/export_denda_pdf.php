<?php
ob_start();
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Validasi role ketua_sanggar
requireRole('ketua_sanggar');

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

// Tentukan mode
$is_detail_mode = ($kategori_db !== '');

$params = [];

if ($is_detail_mode) {
    // Mode detail-level: denda proporsional per item kategori
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
    die("Kesalahan database saat mengekstrak data laporan denda: " . $e->getMessage());
}

// Convert logo to base64 for PDF embedding
// Gunakan __DIR__ untuk path yang konsisten di semua komputer
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
    <title>Laporan Denda Keterlambatan</title>
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 18px; margin: 0 0 5px 0; text-transform: uppercase; font-weight: bold; }
        .header p { margin: 0; font-size: 12px; color: #666; }
        .meta-info { margin-bottom: 15px; font-size: 11px; }
        .info-note { background-color: #eef2ff; border: 1px solid #c7d2fe; padding: 8px 12px; margin-bottom: 15px; font-size: 10px; color: #4338ca; border-radius: 4px; }
        .report-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .report-table th { background-color: #f2f2f2; border: 1px solid #ddd; padding: 8px; font-weight: bold; text-align: left; text-transform: uppercase; font-size: 10px; }
        .report-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        .total-row { font-weight: bold; background-color: #fafafa; }
    </style>
</head>
<body>
    <div class="header">
        ' . $logo_html . '
        <h1>Laporan Denda Keterlambatan Pengembalian</h1>
        <p>Sanggar Seni Bedug Yudha</p>
    </div>

    <div class="meta-info">
        <strong>Tanggal Cetak:</strong> ' . formatTanggalIndo(date('Y-m-d')) . '<br>
        <strong>Filter Periode Pengembalian:</strong> ' . ($tanggal_mulai !== '' ? formatTanggalIndo($tanggal_mulai) : 'Semua') . ' s/d ' . ($tanggal_akhir !== '' ? formatTanggalIndo($tanggal_akhir) : 'Semua') . '<br>
        <strong>Kategori Peralatan:</strong> ' . $kategori_label . '
    </div>';

if ($is_detail_mode) {
    $html .= '
    <div class="info-note">
        <strong>Catatan:</strong> Denda dicatat per transaksi, bukan per item. Nominal yang ditampilkan adalah denda proporsional berdasarkan rasio jumlah item kategori ' . $kategori_label . ' terhadap total item dalam transaksi.
    </div>';
}

$html .= '
    <table class="report-table">
        <thead>
            <tr>';

if ($is_detail_mode) {
    $html .= '
                <th style="width: 10%;">No. Transaksi</th>
                <th>Nama Pelanggan</th>
                <th>Nama Peralatan</th>
                <th style="width: 8%; text-align: center;">Jumlah</th>
                <th style="width: 14%;">Batas Jatuh Tempo</th>
                <th style="width: 14%;">Tgl Dikembalikan</th>
                <th style="width: 14%; text-align: right;">Denda Proporsional</th>';
} else {
    $html .= '
                <th style="width: 10%;">No. Transaksi</th>
                <th>Nama Pelanggan</th>
                <th style="width: 15%;">Batas Jatuh Tempo</th>
                <th style="width: 15%;">Tgl Dikembalikan</th>
                <th style="width: 12%; text-align: center;">Total Item</th>
                <th style="width: 18%;">Dicatat Oleh</th>
                <th style="width: 15%; text-align: right;">Nominal Denda</th>';
}

$html .= '
            </tr>
        </thead>
        <tbody>';

$total_denda = 0;

if ($is_detail_mode) {
    // === MODE DETAIL-LEVEL ===
    if (count($denda_list) > 0) {
        foreach ($denda_list as $row) {
            $denda_prop = (float)$row['denda_proporsional'];
            $total_denda += $denda_prop;

            $html .= '
            <tr>
                <td style="font-weight: bold;">#' . $row['penyewaan_id'] . '</td>
                <td>' . htmlspecialchars($row['nama_pelanggan']) . '</td>
                <td>' . htmlspecialchars($row['nama_alat']) . '</td>
                <td style="text-align: center;">' . $row['jumlah_pinjam'] . ' unit</td>
                <td>' . formatTanggalIndo($row['tanggal_kembali']) . '</td>
                <td>' . formatTanggalIndo($row['tanggal_dikembalikan']) . '</td>
                <td style="font-weight: bold; text-align: right; color: #dc2626;">' . formatRupiah($denda_prop) . '</td>
            </tr>';
        }
    } else {
        $html .= '
        <tr>
            <td colspan="7" style="text-align: center; color: #999;">Tidak ada data denda untuk kategori ' . $kategori_label . '.</td>
        </tr>';
    }

    $html .= '
        <tr class="total-row">
            <td colspan="6" style="text-align: right; font-size: 11px; padding: 10px;">Total Denda Proporsional — ' . $kategori_label . ':</td>
            <td style="text-align: right; color: #dc2626; font-size: 12px; padding: 10px;">' . formatRupiah($total_denda) . '</td>
        </tr>';
} else {
    // === MODE HEADER-LEVEL ===
    if (count($denda_list) > 0) {
        foreach ($denda_list as $row) {
            $total_denda += (float)$row['denda_keterlambatan'];

            $html .= '
            <tr>
                <td style="font-weight: bold;">#' . $row['penyewaan_id'] . '</td>
                <td>' . htmlspecialchars($row['nama_pelanggan']) . '</td>
                <td>' . formatTanggalIndo($row['tanggal_kembali']) . '</td>
                <td>' . formatTanggalIndo($row['tanggal_dikembalikan']) . '</td>
                <td style="text-align: center;">' . $row['total_item'] . ' unit</td>
                <td>' . htmlspecialchars($row['dicatat_oleh_nama']) . '</td>
                <td style="font-weight: bold; text-align: right; color: #dc2626;">' . formatRupiah($row['denda_keterlambatan']) . '</td>
            </tr>';
        }
    } else {
        $html .= '
        <tr>
            <td colspan="7" style="text-align: center; color: #999;">Tidak ada data laporan denda keterlambatan.</td>
        </tr>';
    }

    $html .= '
        <tr class="total-row">
            <td colspan="6" style="text-align: right; font-size: 11px; padding: 10px;">Total Akumulasi Denda:</td>
            <td style="text-align: right; color: #dc2626; font-size: 12px; padding: 10px;">' . formatRupiah($total_denda) . '</td>
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
$filename = 'laporan_denda';
if ($kategori_filter !== '') {
    $filename .= '_' . strtolower(str_replace(' ', '_', $kategori_filter));
}
$filename .= '_' . date('Y-m-d') . '.pdf';

$dompdf->stream($filename, ['Attachment' => true]);
exit();
