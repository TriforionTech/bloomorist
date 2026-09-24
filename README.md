# Bloomorist — Florist Management & Accounting System

Aplikasi manajemen operasional toko bunga (*florist*), mencakup Point of Sales (Invoicing), Manajemen Inventaris & Stok Fisik, Stock Opname, serta Pembukuan Akuntansi Lengkap berbasis metode periodik (Jurnal Umum, Buku Besar, Neraca Saldo, Laporan Laba Rugi, Neraca, Arus Kas, dan Tutup Buku).

---

## 🛠️ Tech Stack & Spesifikasi

- **Framework**: [Laravel 12](https://laravel.com)
- **Admin Panel**: [Filament v4](https://filamentphp.com)
- **Reactivity & Frontend**: [Livewire v3](https://livewire.laravel.com) & [Alpine.js](https://alpinejs.dev)
- **Styling**: [Tailwind CSS v4](https://tailwindcss.com)
- **Database**: MySQL / MariaDB (SQLite untuk Unit Testing)
- **PDF Engine**: Barryvdh DomPDF
- **PHP Version**: 8.4+ (Minimal 8.2)

---

## 🚀 Panduan Menjalankan Aplikasi (Setup Awal Clone)

Ikuti langkah-langkah berikut ketika pertama kali melakukan clone repository:

### 1. Clone Repository
```bash
git clone <repository-url>
cd bloomorist
```

### 2. Install Dependensi Backend (Composer)
```bash
composer install
```

### 3. Install Dependensi Frontend (NPM)
```bash
npm install
```

### 4. Konfigurasi Environment (`.env`)
Salin file `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Generate App Key:
```bash
php artisan key:generate
```

Buka file `.env` dan sesuaikan koneksi database Anda:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bloomorist
DB_USERNAME=root
DB_PASSWORD=
```
> **Catatan**: Pastikan database dengan nama `bloomorist` sudah dibuat di MySQL Anda.

### 5. Migrasi & Seeding Database
Jalankan migrasi fresh beserta seeder fondasi sistem:
```bash
php artisan migrate:fresh --seed
```

### 6. Jalankan Server Lokal
Jalankan dua terminal terpisah:

**Terminal 1 (Vite Dev Server)**:
```bash
npm run dev
```

**Terminal 2 (Laravel Web Server)**:
```bash
php artisan serve
```

### 7. Akses Aplikasi & Akun Login Default
Buka browser dan akses URL:
👉 **[http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin)**

**Kredensial Super Admin Default:**
- **Email**: `bloomorist@gmail.com`
- **Password**: `123`

---

## 🧪 Panduan Blackbox Testing & Reset Data

Jika Anda ingin menguji setiap alur kerja (*action button flow*) dari kondisi database bersih (data transaksi kosong):

### 1. Reset Database ke Kondisi Bersih
```bash
php artisan migrate:fresh --seed
```

**Kondisi Database Setelah Perintah Ini:**
- ✅ **Master Data Otomatis Tersedia**:
  - Super Admin User (`bloomorist@gmail.com`)
  - Chart of Accounts (COA lengkap: Kas 1101, Bank 1102, Persediaan 1104, Penjualan 4101, Beban-beban 6101-6110, dll.)
  - Periode Akuntansi Aktif (`OPEN`) untuk bulan berjalan
  - Data Kategori Membership & Template Laporan
  - Katalog Produk Awal (termasuk Box & Wrapping)
- 🧹 **Data Transaksi Bersih Kosong (0 Data)**:
  - Invoice: 0
  - Pengeluaran (Expenses): 0
  - Stock Opname: 0
  - Jurnal Umum: 0

### 2. Checklist Alur Pengujian Manual (1 per 1)
| No | Modul | Aksi / Flow Pengujian | Ekspektasi Hasil |
|---|---|---|---|
| 1 | **Customers** | Buat customer baru & edit data | Tersimpan dengan format kapitalisasi rapi |
| 2 | **Products** | Tambah produk baru | SKU ter-generate otomatis (`FLW-...`, `PKG-...`) |
| 3 | **Stock Movements** | Sesuaikan stok manual (Stock In / Stock Out) | Stok bertambah/berkurang, histori tercatat |
| 4 | **Generate Invoice** | Buat invoice status `Pending` | Invoice terbit, subtotal, diskon, dan ongkir akurat |
| 5 | **Invoice Status** | Ubah status invoice dari `Pending` ke `Paid` | Stok produk terpotong otomatis, Jurnal Penjualan terbentuk |
| 6 | **Expenses** | Input beban/pengeluaran baru & klik **Post** | Status berubah ke `Posted`, Jurnal Beban otomatis tercatat |
| 7 | **Stock Opname** | Buat form opname fisik & klik **Post** | Stok fisik ter-update, selisih masuk ke Jurnal Penyesuaian |
| 8 | **Stock Transfer** | Transfer stok dari satu produk ke produk lain | Stok sumber berkurang, target bertambah |
| 9 | **Buku Besar & Neraca Saldo** | Buka halaman Buku Besar & Neraca Saldo | Filter periode bekerja, saldo Debit & Kredit seimbang |
| 10 | **Financial Reports** | Cek Laba Rugi, Neraca, dan Arus Kas | Nilai sinkron, tombol Download PDF & CSV berjalan |
| 11 | **Accounting Periods** | Buka menu *Accounting Periods* & buat periode baru | Pilih Bulan & Tahun, tanggal 1 s/d akhir bulan & label terisi otomatis |
| 12 | **Tutup Buku** | Buka menu *Accounting Periods* & klik **Tutup Buku** | Periode terkunci (`CLOSED`), persediaan akhir dibawa ke bulan depan |

---

## 📊 Progress Pengerjaan Sistem

| Kategori | Fitur / Sub-Sistem | Status | Deskripsi |
|---|---|:---:|---|
| **Dashboard** | Executive Metrics & Visual Insights | `SELESAI` | Ringkasan omset, AOV, grafik tren, top 10 produk. Layout 2-baris simetris lega di layar laptop. |
| **Master Data** | Manajemen Produk & SKU Otomatis | `SELESAI` | Katalog produk, harga 3-tier (Toko, Vendor, Dekorator), multi-kategori, SKU generator otomatis. |
| | Manajemen Pelanggan & Membership | `SELESAI` | Pelanggan reguler & member, riwayat belanja, tier diskon membership otomatis. |
| | Chart of Accounts (COA) | `SELESAI` | Standar akuntansi florist (Aktiva Lancar, Tetap, Kewajiban, Modal, Pendapatan, HPP, Beban). |
| **Sales & POS** | Generate Invoice & Invoice Management | `SELESAI` | Repeater order produk, add-on Box & Wrapping, diskon persen/nominal, ongkos kirim. |
| | Cetak Invoice PDF | `SELESAI` | Template struk / invoice PDF rapi dan siap cetak/kirim ke pelanggan. |
| | Status Lifecycle & Integrasi Stok | `SELESAI` | Validasi stok saat pelunasan (`Pending` &rarr; `Paid`), pengembalian stok saat `Cancelled`. |
| **Inventaris** | Real-Time Stock Tracking | `SELESAI` | Pengurangan stok otomatis dari invoice lunas dan penyesuaian manual. |
| | Stock Opname (Fisik) | `SELESAI` | Input 2-kolom ringkas, kalkulasi varians otomatis, kartu overview total fisik & varians di header. |
| | Stock Transfer | `SELESAI` | Konversi antar produk bunga dengan penyesuaian nilai harga pokok. |
| **Akuntansi** | Periode Akuntansi (Sistem Kalender) | `SELESAI` | Pemilihan Bulan & Tahun kalender otomatis (tanggal 1 s/d akhir bulan, pencegahan duplikasi). |
| | Jurnal Umum Otomatis (Double-entry) | `SELESAI` | Integrasi jurnal dari penjualan invoice, pengeluaran kas, stock opname, dan depresiasi aset. |
| | Buku Besar (General Ledger) | `SELESAI` | Rekap mutasi debit/kredit dan saldo berjalan per akun COA per periode. |
| | Neraca Saldo (Trial Balance) | `SELESAI` | Validasi keseimbangan saldo debit dan kredit secara real-time. |
| | Laporan Laba Rugi (Metode Periodik) | `SELESAI` | Penjualan Bersih - HPP (Persediaan Awal + Pembelian - Persediaan Akhir) - Beban Operasional. |
| | Laporan Neraca (Balance Sheet) | `SELESAI` | Posisi Aset = Kewajiban + Ekuitas (termasuk Laba Ditahan berjalan). |
| | Laporan Arus Kas (Cash Flow) | `SELESAI` | Rekonsiliasi aktivitas Operasi, Investasi, dan Pendanaan vs Saldo Kas/Bank fisik. |
| | Aset Tetap & Penyusutan | `SELESAI` | Tracking masa manfaat aset dan posting jurnal penyusutan bulanan otomatis. |
| | Tutup Buku Bulanan (Period Closing) | `SELESAI` | Penguncian transaksi periode lampau dan carry-forward nilai persediaan akhir otomatis. |

---

## 🧪 Menjalankan Automated Testing

Proyek ini dilengkapi dengan rangkaian pengujian otomatis (**PHPUnit Feature & Unit Tests**):

Jalankan seluruh pengujian:
```bash
php artisan test --compact
```

Jalankan pengujian spesifik:
```bash
# Uji migrasi fresh & penanganan data kosong:
php artisan test --compact tests/Feature/FreshMigrationAndEmptyStateTest.php

# Uji manajemen periode akuntansi berbasis kalender:
php artisan test --compact tests/Feature/AccountingPeriodResourceTest.php

# Uji tampilan dan layout widget dashboard:
php artisan test --compact tests/Feature/DashboardWidgetTest.php

# Uji penutupan periode akuntansi (Tutup Buku):
php artisan test --compact tests/Feature/PeriodClosingTest.php

# Uji laporan laba rugi & persediaan periodik:
php artisan test --compact tests/Feature/PeriodicFinancialReportTest.php

# Uji alur Stock Opname:
php artisan test --compact tests/Feature/StockOpnameTest.php
```

---

## 🎨 Standarisasi Kode (Code Formatting)

Proyek ini menggunakan **Laravel Pint** untuk standarisasi format kode PHP:
```bash
vendor/bin/pint --dirty --format agent
```
