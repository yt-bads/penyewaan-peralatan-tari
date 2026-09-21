# Sistem Informasi Penyewaan Peralatan Tari

<p align="center">
  <strong>Platform berbasis web untuk mengelola penyewaan peralatan tari dari proses pemesanan hingga pengembalian.</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL%2FMariaDB-Database-4479A1?logo=mysql&logoColor=white" alt="MySQL/MariaDB">
  <img src="https://img.shields.io/badge/PDO-Database%20Access-777BB4" alt="PDO">
  <img src="https://img.shields.io/badge/Dompdf-PDF%20Report-4CAF50" alt="Dompdf">
  <img src="https://img.shields.io/badge/Project-Portfolio-24292F?logo=github&logoColor=white" alt="Portfolio Project">
</p>

<p align="center">
  <a href="https://github.com/yt-bads/penyewaan-peralatan-tari">Repository</a>
  &nbsp;&bull;&nbsp;
  <a href="#fitur-utama">Fitur</a>
  &nbsp;&bull;&nbsp;
  <a href="#instalasi">Instalasi</a>
  &nbsp;&bull;&nbsp;
  <a href="#arsitektur-aplikasi">Arsitektur</a>
</p>

---

## Tentang Project

**Sistem Informasi Penyewaan Peralatan Tari** adalah aplikasi web untuk membantu pengelolaan proses penyewaan peralatan tari secara terstruktur, mulai dari katalog dan pemesanan, verifikasi pembayaran, pengelolaan stok, pengembalian barang, perhitungan denda keterlambatan, hingga monitoring dan pembuatan laporan.

Project ini dirancang untuk mendukung kebutuhan operasional **Sanggar Seni Bedug Yudha** dengan pembagian akses berdasarkan peran pengguna.

Fokus utama aplikasi:

- digitalisasi proses penyewaan peralatan tari;
- pengelolaan data pelanggan, peralatan, transaksi, stok, dan pengembalian;
- verifikasi pembayaran dan administrasi transaksi;
- monitoring status penyewaan secara terpusat;
- pencatatan histori stok;
- perhitungan dan pengelolaan denda keterlambatan;
- pembuatan laporan dalam format PDF.

---

## Tujuan

Aplikasi ini dibuat untuk mengurangi proses pencatatan manual dan membantu pengelola sanggar memperoleh informasi transaksi secara lebih terorganisir.

Dari sisi pelanggan, sistem menyediakan katalog dan alur pemesanan yang lebih jelas. Dari sisi internal, setiap proses dapat dikelola berdasarkan role sehingga pekerjaan pengelolaan transaksi, gudang, dan monitoring dapat dipisahkan.

---

## Fitur Utama

### 1. Pelanggan

Pelanggan dapat:

- membuat akun dan melakukan login;
- mengelola profil;
- melihat katalog peralatan yang tersedia;
- mencari peralatan berdasarkan nama;
- memfilter peralatan berdasarkan kategori;
- membuat pesanan penyewaan;
- memilih beberapa peralatan dalam satu transaksi;
- menentukan jumlah item yang disewa;
- menentukan tanggal sewa dan pengembalian;
- melihat ringkasan dan total biaya;
- mengunggah bukti pembayaran;
- melihat status pembayaran dan status penyewaan;
- melihat riwayat pesanan;
- mengunggah bukti foto pengembalian;
- mengunggah bukti pembayaran denda.

### 2. Admin Pengelola

Admin pengelola dapat:

- melihat dashboard pengelolaan;
- melihat seluruh pesanan pelanggan;
- memfilter transaksi berdasarkan status penyewaan;
- memfilter transaksi berdasarkan status pembayaran;
- melihat detail pesanan;
- memverifikasi pembayaran;
- memperbarui status transaksi;
- memverifikasi pembayaran denda;
- membuat laporan penyewaan;
- melakukan filter laporan berdasarkan periode, status, dan kategori;
- mengekspor laporan penyewaan ke PDF.

### 3. Admin Gudang

Admin gudang bertanggung jawab terhadap operasional inventaris dan pengembalian:

- melihat dashboard gudang;
- menambah data peralatan;
- mengubah data peralatan;
- menghapus data peralatan;
- mengelola stok;
- melihat kondisi peralatan;
- melihat histori keluar-masuk stok;
- mencatat pengembalian;
- mencatat kondisi barang saat dikembalikan;
- menghitung denda keterlambatan;
- melihat laporan denda;
- mengekspor laporan denda ke PDF.

### 4. Ketua Sanggar

Ketua sanggar mendapatkan fungsi monitoring dan pelaporan:

- melihat dashboard monitoring;
- memantau transaksi penyewaan;
- melihat ringkasan jumlah transaksi;
- melihat nilai transaksi;
- melihat total denda;
- memfilter data monitoring;
- melihat histori transaksi;
- melihat laporan penyewaan;
- melihat laporan denda;
- mengekspor laporan ke PDF.

---

## Alur Bisnis

```text
Pelanggan
   │
   ├── Registrasi / Login
   │
   ├── Melihat Katalog
   │
   ├── Memilih Peralatan
   │
   ├── Membuat Pesanan
   │
   ├── Mengunggah Bukti Pembayaran
   │
   ▼
Admin Pengelola
   │
   ├── Verifikasi Pembayaran
   ├── Mengelola Status Pesanan
   │
   ▼
Admin Gudang
   │
   ├── Menyiapkan Peralatan
   ├── Mengurangi / Mencatat Stok
   ├── Mencatat Pengembalian
   ├── Menghitung Denda
   └── Menambahkan Kembali Stok
   │
   ▼
Pelanggan
   │
   └── Melakukan Pengembalian / Pembayaran Denda jika diperlukan
   │
   ▼
Ketua Sanggar
   │
   └── Monitoring & Laporan
```

---

## Role & Access Control

| Role | Fokus | Akses Utama |
|---|---|---|
| **Pelanggan** | Pemesanan | Katalog, pesanan, pembayaran, profil, pengembalian |
| **Admin Pengelola** | Administrasi transaksi | Pesanan, verifikasi pembayaran, status transaksi, laporan |
| **Admin Gudang** | Inventaris | Peralatan, stok, pengembalian, denda, laporan |
| **Ketua Sanggar** | Monitoring | Dashboard, monitoring transaksi, laporan |

Setiap halaman internal menggunakan pemeriksaan login dan role sebelum fitur dapat diakses.

---

## Teknologi yang Digunakan

### Backend
- PHP 8.0+
- PDO
- PHP Session
- Prepared Statement
- Database Transaction

### Frontend
- HTML5
- CSS3
- JavaScript
- Custom responsive UI
- Flatpickr untuk input tanggal

### Database
- MySQL / MariaDB
- Relational database
- Foreign key dan indexing
- Transactional data management

### Reporting
- [Dompdf](https://github.com/dompdf/dompdf)
- Export laporan penyewaan ke PDF
- Export laporan denda ke PDF

### Development Environment
- XAMPP
- Apache
- PHP
- MySQL / MariaDB
- Composer

---

## Arsitektur Aplikasi

Struktur utama project:

```text
penyewaan-peralatan-tari/
├── admin_gudang/
│   ├── dashboard.php
│   ├── daftar_peralatan.php
│   ├── tambah_peralatan.php
│   ├── edit_peralatan.php
│   ├── hapus_peralatan.php
│   ├── catat_pengembalian.php
│   ├── daftar_pengembalian.php
│   ├── riwayat_stok.php
│   ├── laporan_denda.php
│   └── export_denda_pdf.php
│
├── admin_pengelola/
│   ├── dashboard.php
│   ├── daftar_pesanan.php
│   ├── detail_pesanan.php
│   ├── update_status.php
│   ├── verifikasi_bayar.php
│   ├── verifikasi_denda.php
│   ├── laporan.php
│   └── export_laporan_pdf.php
│
├── ketua_sanggar/
│   ├── dashboard.php
│   ├── pantau_penyewaan.php
│   ├── laporan.php
│   ├── laporan_denda.php
│   ├── export_laporan_pdf.php
│   └── export_denda_pdf.php
│
├── pelanggan/
│   ├── dashboard.php
│   ├── katalog.php
│   ├── pesan.php
│   ├── pesanan_saya.php
│   ├── detail_pesanan.php
│   ├── upload_bukti_denda.php
│   ├── upload_foto_pengembalian.php
│   └── profil.php
│
├── includes/
│   ├── auth.php
│   ├── db.php
│   └── functions.php
│
├── assets/
│   ├── css/
│   ├── img/
│   └── js/
│
├── database/
│   └── penyewaan_tari.sql
│
├── libs/
│   └── dompdf/
│
├── uploads/
│
├── composer.json
├── index.php
├── login.php
├── register.php
├── proses_login.php
├── proses_register.php
├── logout.php
└── 403.php
```

---

## Database

Sistem menggunakan database relasional dengan tabel utama:

| Tabel | Fungsi |
|---|---|
| `users` | Data pengguna dan role |
| `peralatan` | Data peralatan, kategori, stok, harga, kondisi |
| `penyewaan` | Data transaksi penyewaan |
| `detail_penyewaan` | Detail item dan jumlah pada setiap transaksi |
| `pengembalian` | Data pengembalian dan denda |
| `riwayat_stok` | Histori perubahan stok |

Relasi data dibangun menggunakan foreign key untuk menjaga hubungan antara pengguna, transaksi, item penyewaan, pengembalian, dan histori stok.

---

## Business Rules

Beberapa aturan bisnis yang diterapkan di aplikasi:

1. Pelanggan wajib login untuk melakukan pemesanan.
2. Hanya peralatan dengan stok tersedia dan kondisi baik yang ditampilkan untuk disewa.
3. Satu transaksi dapat berisi beberapa jenis peralatan.
4. Jumlah item tidak boleh melebihi stok tersedia.
5. Durasi penyewaan dibatasi maksimal **3 hari**.
6. Pesanan baru dibuat dengan status **menunggu** dan pembayaran **belum bayar**.
7. Bukti pembayaran perlu diverifikasi sebelum transaksi diproses lebih lanjut.
8. Pengurangan stok dilakukan ketika transaksi memasuki proses sewa aktif.
9. Pengembalian menambahkan kembali stok dan dicatat pada histori stok.
10. Keterlambatan pengembalian dapat menghasilkan denda.
11. Sistem mendukung pencatatan bukti pembayaran denda dan foto konfirmasi pengembalian.

---

## Instalasi

### Prasyarat

Pastikan telah terpasang:

- XAMPP
- PHP 8.0 atau lebih baru
- MySQL / MariaDB
- Composer

### 1. Clone Repository

```bash
git clone https://github.com/yt-bads/penyewaan-peralatan-tari.git
```

Masuk ke folder project:

```bash
cd penyewaan-peralatan-tari
```

Untuk XAMPP, letakkan project pada:

```text
C:/xampp/htdocs/penyewaan-peralatan-tari
```

### 2. Buat Database

Buka **phpMyAdmin**, lalu buat database:

```text
penyewaan_tari
```

Import file:

```text
database/penyewaan_tari.sql
```

### 3. Konfigurasi Database

Buka:

```text
includes/db.php
```

Sesuaikan konfigurasi dengan environment lokal:

```php
$host   = 'localhost';
$dbname = 'penyewaan_tari';
$user   = 'root';
$pass   = '';
```

### 4. Install Dependency

Jalankan:

```bash
composer install
```

Dependency utama project adalah **Dompdf** untuk pembuatan dokumen PDF.

### 5. Jalankan Aplikasi

Nyalakan:

- Apache
- MySQL

Kemudian buka:

```text
http://localhost/penyewaan-peralatan-tari/
```

---

## Konfigurasi Upload

Project menyediakan direktori upload untuk kebutuhan:

```text
uploads/
├── bukti/
└── foto_pengembalian/
```

Pastikan Apache/PHP memiliki izin untuk menulis ke direktori tersebut pada environment lokal.

Untuk deployment production, validasi tipe file, ukuran file, nama file, dan lokasi penyimpanan sebaiknya diperketat.

---

## Keamanan

Project menerapkan beberapa mekanisme dasar keamanan aplikasi:

- autentikasi berbasis session;
- role-based access control;
- prepared statement dengan PDO;
- password hashing menggunakan `password_hash()`;
- validasi input pada proses registrasi dan transaksi;
- database transaction pada proses yang memengaruhi stok.

Untuk deployment production, tetap lakukan hardening tambahan seperti:

- menggunakan credential database melalui environment variable;
- menghindari penyimpanan data dummy atau data operasional nyata di repository publik;
- memperketat validasi upload;
- membatasi akses direktori `uploads`;
- menonaktifkan output error database ke pengguna;
- meninjau CSRF protection, session security, dan access-control pada seluruh endpoint.

---

## Laporan & PDF

Sistem menyediakan pembuatan laporan dalam format PDF menggunakan Dompdf.

Jenis laporan meliputi:

- laporan penyewaan;
- laporan berdasarkan periode;
- laporan berdasarkan status;
- laporan berdasarkan kategori peralatan;
- laporan denda keterlambatan;
- total pendapatan terverifikasi berdasarkan transaksi lunas.

---

## Use Case Ringkas

```text
                    ┌──────────────────────┐
                    │       Pelanggan      │
                    └──────────┬───────────┘
                               │
             ┌─────────────────┼─────────────────┐
             ▼                 ▼                 ▼
          Katalog           Pesanan          Pembayaran
             │                 │                 │
             └─────────────────┼─────────────────┘
                               ▼
                    ┌──────────────────────┐
                    │  Admin Pengelola     │
                    └──────────┬───────────┘
                               │
                    Verifikasi & Status
                               │
                               ▼
                    ┌──────────────────────┐
                    │    Admin Gudang      │
                    └──────────┬───────────┘
                               │
              ┌────────────────┼────────────────┐
              ▼                ▼                ▼
           Stok           Pengembalian        Denda
              │                │                │
              └────────────────┼────────────────┘
                               ▼
                    ┌──────────────────────┐
                    │    Ketua Sanggar     │
                    └──────────────────────┘
                         Monitoring & Report
```

---

## Project Highlights

Project ini menunjukkan implementasi beberapa konsep pengembangan sistem informasi:

- role-based access control;
- CRUD dan relational database;
- transaction processing;
- inventory/stock management;
- rental order management;
- payment verification workflow;
- return management;
- fine calculation;
- reporting;
- PDF document generation;
- responsive web interface;
- prepared statement dan database transaction.

---

## Status Project

**Project Type:** Portfolio / Information System Project

Repository ini berisi source code aplikasi, struktur database, assets, dependency, serta implementasi alur bisnis penyewaan peralatan tari.

---

## Catatan Pengembangan

Beberapa pengembangan yang dapat dilakukan selanjutnya:

- integrasi payment gateway;
- notifikasi WhatsApp atau email;
- kalender ketersediaan peralatan;
- audit log aktivitas pengguna;
- dashboard analytics yang lebih interaktif;
- deployment ke server production;
- automated testing;
- API untuk integrasi dengan aplikasi lain.

---

## Author

**yt-bads**

GitHub:  
https://github.com/yt-bads

Repository:  
https://github.com/yt-bads/penyewaan-peralatan-tari

---
