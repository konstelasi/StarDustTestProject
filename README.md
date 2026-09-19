# StarDust Warehouse & Inventory Management System

Aplikasi Manajemen Inventory dan Logistik Multi-Tenant berbasis **Laravel** dan **StarDust Engine** ([damarbob/StarDust](https://github.com/damarbob/StarDust)).

Aplikasi ini mengimplementasikan arsitektur *Schemaless Dynamic Database Pattern* (SDDPG) untuk mengelola data barang dan gudang dengan performa tinggi, pencarian berbasis AST (Abstract Syntax Tree), pengurutan dinamis (Ascending & Descending), cursor pagination, serta penulisan massal (*bulk write*) baik secara sinkron maupun asinkron.

---

## Daftar Isi

- [Tentang Aplikasi](#tentang-aplikasi)
- [Fitur Utama](#fitur-utama)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Langkah-Langkah Instalasi](#langkah-langkah-instalasi)
- [Struktur Perintah Artisan](#struktur-perintah-artisan)
- [Arsitektur & Konsep StarDust Engine](#arsitektur--konsep-stardust-engine)
- [Pengujian Otomatis](#pengujian-otomatis)
- [Lisensi](#lisensi)

---

## Tentang Aplikasi

StarDust Warehouse System dirancang untuk menangani manajemen inventaris berskala besar yang membutuhkan fleksibilitas atribut produk tanpa mengorbankan performa pencarian database relasional. 

Seluruh penyimpanan dan pembacaan data inventaris diproses secara murni melalui API Facade & DTO dari **StarDust Engine**, memisahkan *primary data storage* (tabel `entry_data`) dari *index slot tables* (tabel `entry_slots_page_X`).

---

## Fitur Utama

- **Multi-Tenant Warehouse Context**: Pengelolaan stok inventaris yang terisolasi berdasarkan lokasi gudang (misal: Gudang Utama Jakarta, Gudang Cabang Surabaya, Gudang Logistik Bandung, Gudang Transit Medan, Gudang Hub Makassar).
- **Dynamic Schemaless Attributes**: Mendukung penambahan bidang data fleksibel seperti tanggal kedaluwarsa (*expiry date* dengan rentang tahun beragam 2021–2030), berat barang (*weight kg*), volume (*volume cbm*), tanggal penerimaan (*received at*), garansi, nomor seri (*serial number*), dan nomor batch tanpa mengubah skema tabel database relasional.
- **Full Column Sorting (ASC & DESC)**: Pengurutan presisi pada setiap kolom field filterable (`sku`, `name`, `category`, `quantity`, `price`, `location`, `weight_kg`, `expiry_date`, `supplier`) memanfaatkan `SortSpec::byField()` & `SortSpec::byId()`.
- **High-Performance AST Filtering**: Pencarian dan penyaringan data menggunakan simpul AST (*LeafNode*, *AndNode*) untuk operasi presisi seperti *prefix match* dan *equality filter*.
- **Opaque Cursor Pagination**: Navigasi halaman yang stabil dan cepat menggunakan token cursor (`Cursor`), menghindari penurunan performa pada *offset pagination* tradisional.
- **Seeder 50 Data Barang Lengkap**: Menyediakan `InventorySeeder` dengan 50+ data sampel barang realistis yang terdistribusi di berbagai gudang.
- **Faker Dummy Generator**: Fitur generate data pengujian cepat via konsol (`php artisan inventory:seed-fake`) maupun antarmuka web.
- **Bulk Ingestion (Sync & Async)**:
  - **Sync Mode**: Penulisan massal langsung per-chunk dengan jeda mikrodetik (*inter-chunk delay*).
  - **Async Mode**: Antrian impor latar belakang (*background ingestion*) melalui tabel `stardust_import_jobs` yang diproses oleh *Reconciler Daemon*.
- **Role-Based Access & Authentication**: Pembagian hak akses antara System Administrator dan Staff Gudang.
- **Stock Movement Stepper**: Penyesuaian stok masuk (*stock-in*) dan stok keluar (*stock-out*) secara cepat.
- **Classic Industrial UI**: Antarmuka responsif bertema *Dark Industrial Logbook* dengan indikator stok rendah dan ringkasan statistik aset.

---

## Persyaratan Sistem

Pastikan lingkungan server atau lokal Anda memenuhi persyaratan berikut:

- **PHP**: ^8.2 / 8.4 (dengan ekstensi `pdo`, `pdo_mysql`, `json`, `mbstring`)
- **Composer**: ^2.0
- **Database**: MySQL 8.0+ / MariaDB 10.4+ / SQLite (untuk pengujian)
- **Web Server**: Nginx, Apache, atau Laravel Built-in Dev Server (`php artisan serve`)

---

## Langkah-Langkah Instalasi

### 1. Clone Repository
```bash
git clone https://github.com/damarbob/StarDustTestProject.git
cd StarDustTestProject
```

### 2. Install Dependensi PHP
```bash
composer install
```

### 3. Konfigurasi Environment File
Salin file konfigurasi lingkungan `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```

Buka file `.env` dan sesuaikan kredensial database MySQL Anda:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory_stardust
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Inisialisasi Database & Seeding StarDust Engine
Jalankan perintah pengesetan StarDust Engine berikut untuk membuat tabel metadata, mendaftarkan skema model (`gudang` & `barang`), memesan slot terindeks, dan mengisi 50 data seeder barang inventaris lengkap:
```bash
php artisan inventory:setup --fresh --seed
```

> **Catatan:** Opsi `--fresh` akan menghapus dan membuat ulang seluruh tabel StarDust, sedangkan `--seed` akan memasukkan 50+ data seeder barang lengkap.

### 6. Jalankan Dev Server
```bash
php artisan serve
```
Akses aplikasi melalui peramban web di: `http://localhost:8000/inventory`

---

## Struktur Perintah Artisan

Aplikasi ini dilengkapi dengan perintah Artisan khusus untuk pengelolaan engine dan data testing:

| Perintah | Deskripsi |
| :--- | :--- |
| `php artisan inventory:setup` | Menginisialisasi skema StarDust Engine dan mendaftarkan model gudang & barang. |
| `php artisan inventory:setup --seed` | Menjalankan inisialisasi sekaligus mengisi 50 data seeder barang inventaris lengkap. |
| `php artisan inventory:setup --fresh --seed` | Menghapus database StarDust, mengulang skema dari nol, dan mengisi 50 data seeder. |
| `php artisan inventory:seed-fake` | Membangkitkan gudang & barang dummy acak via Faker (mencakup expiry date & weight). |
| `vendor/bin/stardust reconciler` | Menjalankan worker daemon untuk memproses job impor massal async. |

---

## Arsitektur & Konsep StarDust Engine

Secara internal, StarDust memisahkan data menjadi dua lapisan utama:

1. **Primary Data Storage (`entry_data`)**
   Tabel tempat menyimpan dokumen data lengkap dalam bentuk JSON pada kolom `fields`, beserta kolom acuan `id`, `tenant_id`, `model_id`, `created_at`, `updated_at`, dan `deleted_at`.

2. **Shared Index Slot Storage (`entry_slots_page_X`)**
   Tabel slot berukuran tetap (60 slot per halaman: 25 `str`, 15 `int`, 10 `num`, 10 `dt`) yang memetakan bidang-bidang terfilter (`isFilterable: true`) secara dinamis.

```text
[HTTP Request / Controller]
          │
          ▼
   [StarDust Facade]
     ├── write() / bulkWrite() ───────► entry_data (JSON Document)
     │                                    │
     └── SlotReserver & Provisioner ──────┼──► entry_slots_page_X (Index Slots)
                                          │
                                          ▼
   [EntryQuery Read + SortSpec] ────► INNER JOIN (entry_data + entry_slots_page_X)
```

---

## Pengujian Otomatis

Seluruh pengujian alur fitur (*feature tests*) dan integrasi engine dikemas dalam suite PHPUnit Laravel.

Untuk menjalankan pengujian otomatis:
```bash
php artisan test
```
atau
```bash
vendor/bin/phpunit
```

Aplikasi telah lulus **100% (24 Tests Passed)** pada suite pengujian fitur inventaris.

---

## Lisensi

Proyek ini dirilis di bawah lisensi [MIT License](LICENSE).
