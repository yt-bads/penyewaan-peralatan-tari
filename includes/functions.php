<?php
// includes/functions.php

/**
 * Format angka ke format mata uang Rupiah
 * Contoh: 50000 -> Rp 50.000
 */
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

/**
 * Cek apakah tanggal pengembalian terlambat dibanding tanggal jatuh tempo sewa
 */
function cekKeterlambatan($tanggal_kembali, $tanggal_dikembalikan) {
    return strtotime($tanggal_dikembalikan) > strtotime($tanggal_kembali);
}

/**
 * Hitung nominal denda keterlambatan (flat Rp 50.000 × total unit item)
 */
function hitungDenda($penyewaan_id, $tanggal_dikembalikan, $pdo) {
    // Ambil tanggal_kembali dari transaksi penyewaan
    $stmt = $pdo->prepare("SELECT tanggal_kembali FROM penyewaan WHERE id = ?");
    $stmt->execute([$penyewaan_id]);
    $penyewaan = $stmt->fetch();
    
    if (!$penyewaan) {
        return 0;
    }
    
    if (cekKeterlambatan($penyewaan['tanggal_kembali'], $tanggal_dikembalikan)) {
        // Ambil total unit item yang disewa dalam transaksi ini
        $stmt = $pdo->prepare("SELECT SUM(jumlah_pinjam) AS total_item FROM detail_penyewaan WHERE penyewaan_id = ?");
        $stmt->execute([$penyewaan_id]);
        $result = $stmt->fetch();
        $total_item = $result['total_item'] ?? 0;
        
        return 50000 * $total_item;
    }
    
    return 0;
}

/**
 * Kurangi stok peralatan tari saat sewa menjadi 'aktif'
 * Dilakukan dalam Database Transaction internal
 */
function kurangiStok($penyewaan_id, $pdo) {
    try {
        $startedTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }
        
        // Ambil semua item di detail_penyewaan
        $stmt = $pdo->prepare("
            SELECT dp.peralatan_id, dp.jumlah_pinjam, p.nama_alat, p.stok 
            FROM detail_penyewaan dp
            JOIN peralatan p ON dp.peralatan_id = p.id
            WHERE dp.penyewaan_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$penyewaan_id]);
        $items = $stmt->fetchAll();
        
        foreach ($items as $item) {
            $peralatan_id = $item['peralatan_id'];
            $jumlah_pinjam = $item['jumlah_pinjam'];
            $nama_alat = $item['nama_alat'];
            $stok_sekarang = $item['stok'];
            
            // Validasi apakah stok mencukupi
            if ($stok_sekarang < $jumlah_pinjam) {
                throw new Exception("Stok untuk alat '{$nama_alat}' tidak mencukupi (Stok saat ini: {$stok_sekarang}, diminta: {$jumlah_pinjam}).");
            }
            
            // Kurangi stok di tabel peralatan
            $stmtUpdate = $pdo->prepare("UPDATE peralatan SET stok = stok - ? WHERE id = ?");
            $stmtUpdate->execute([$jumlah_pinjam, $peralatan_id]);
            
            // Catat ke log riwayat_stok (keluar)
            $stmtLog = $pdo->prepare("
                INSERT INTO riwayat_stok (peralatan_id, penyewaan_id, jenis, jumlah, keterangan) 
                VALUES (?, ?, 'keluar', ?, 'Disewa')
            ");
            $stmtLog->execute([$peralatan_id, $penyewaan_id, $jumlah_pinjam]);
        }
        
        if ($startedTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        if (isset($startedTransaction) && $startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Tambah kembali stok peralatan tari saat pengembalian dicatat
 * Dilakukan dalam Database Transaction internal
 */
function tambahStok($penyewaan_id, $pdo) {
    try {
        $startedTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }
        
        // Ambil semua item di detail_penyewaan
        $stmt = $pdo->prepare("SELECT peralatan_id, jumlah_pinjam FROM detail_penyewaan WHERE penyewaan_id = ?");
        $stmt->execute([$penyewaan_id]);
        $items = $stmt->fetchAll();
        
        foreach ($items as $item) {
            $peralatan_id = $item['peralatan_id'];
            $jumlah_pinjam = $item['jumlah_pinjam'];
            
            // Tambahkan stok kembali ke tabel peralatan
            $stmtUpdate = $pdo->prepare("UPDATE peralatan SET stok = stok + ? WHERE id = ?");
            $stmtUpdate->execute([$jumlah_pinjam, $peralatan_id]);
            
            // Catat ke log riwayat_stok (masuk)
            $stmtLog = $pdo->prepare("
                INSERT INTO riwayat_stok (peralatan_id, penyewaan_id, jenis, jumlah, keterangan) 
                VALUES (?, ?, 'masuk', ?, 'Dikembalikan')
            ");
            $stmtLog->execute([$peralatan_id, $penyewaan_id, $jumlah_pinjam]);
        }
        
        if ($startedTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        if (isset($startedTransaction) && $startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Helper untuk melakukan redirect URL dengan aman, 
 * menyesuaikan dengan prefix folder proyek di localhost
 */
function redirect($path) {
    $path = ltrim($path, '/');
    header('Location: /penyewaan-peralatan-tari/' . $path);
    exit();
}

if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo($dateStr) {
        if (!$dateStr) return '-';
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $time = strtotime($dateStr);
        if (!$time) return '-';
        $d = date('d', $time);
        $m = (int)date('m', $time);
        $y = date('Y', $time);
        
        return "{$d} {$months[$m]} {$y}";
    }
}

if (!function_exists('formatTanggalWaktuIndo')) {
    function formatTanggalWaktuIndo($dateTimeStr) {
        if (!$dateTimeStr) return '-';
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $time = strtotime($dateTimeStr);
        if (!$time) return '-';
        $d = date('d', $time);
        $m = (int)date('m', $time);
        $y = date('Y', $time);
        $h_i = date('H:i', $time);
        
        return "{$d} {$months[$m]} {$y} {$h_i}";
    }
}
