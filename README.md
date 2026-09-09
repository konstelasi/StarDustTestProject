# StarDust Warehouse & Inventory Management System

Aplikasi Manajemen Inventory dan Logistik Multi-Tenant berbasis **Laravel** dan **StarDust Engine** ([damarbob/StarDust](https://github.com/damarbob/StarDust)).

Aplikasi ini mengimplementasikan arsitektur *Schemaless Dynamic Database Pattern* (SDDPG) untuk mengelola data barang dan gudang dengan performa tinggi, pencarian berbasis AST (Abstract Syntax Tree), cursor pagination, serta penulisan massal (*bulk write*) baik secara sinkron maupun asinkron.

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

- **Multi-Tenant Warehouse Context**: Pengelolaan stok inventaris yang terisolasi berdasarkan lokasi gudang (misal: Gudang Utama Jakarta, Gudang Cabang Surabaya, Gudang Logistik Bandung).
- **Dynamic Schemaless Attributes**: Mendukung penambahan bidang data fleksibel seperti tanggal kadaluarsa (*expiry date*), garansi, nomor seri (*serial number*), dan nomor batch tanpa perlu mengubah skema database relasional.
- **High-Performance AST Filtering**: Pencarian dan penyaringan data menggunakan simpul AST (*LeafNode*, *AndNode*) untuk operasi presisi seperti *prefix match* dan *equality filter*.
- **Opaque Cursor Pagination**: Navigasi halaman yang stabil dan cepat menggunakan token cursor, menghindari penurunan performa pada *offset pagination* tradisional.
- **Bulk Ingestion (Sync & Async)**:
  - **Sync Mode**: Penulisan massal langsung per-chunk dengan jeda mikrodetik (*inter-chunk delay*).
  - **Async Mode**: Antrian impor latar belakang (*background ingestion*) melalui tabel `stardust_import_jobs` yang diproses oleh *Reconciler Daemon*.
- **Stock Movement Stepper**: Fitur penyesuaian stok masuk (*stock-in*) dan stok keluar (*stock-out*) secara cepat.
- **Classic Industrial UI**: Antarmuka responsif bertema *Dark Industrial Logbook* dengan indikator stok rendah dan grafik statistik aset.

---

## Persyaratan Sistem

Pastikan lingkungan server atau lokal Anda memenuhi persyaratan berikut:

- **PHP**: ^8.2 (dengan ekstensi `pdo`, `pdo_mysql`, `json`, `mbstring`)
- **Composer**: ^2.0
- **Database**: MySQL 8.0+ / MariaDB 10.4+
- **Web Server**: Nginx, Apache, atau Laravel Built-in Dev Server

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
Jalankan perintah pengesetan StarDust Engine berikut untuk membuat tabel metadata, mendaftarkan skema model (`gudang` & `barang`), memesan slot terindeks, dan mengisi data sampel:
```bash
php artisan inventory:setup --fresh --seed
```

> **Catatan:** Opsi `--fresh` akan menghapus dan membuat ulang seluruh tabel StarDust, sedangkan `--seed` akan memasukkan data sampel gudang dan barang inventaris.

### 6. Jalankan Dev Server
```bash
php artisan serve
```
Akses aplikasi melalui peramban web di: `http://localhost:8000/inventory`

---

## Struktur Perintah Artisan

Aplikasi ini dilengkapi dengan perintah Artisan khusus untuk pengelolaan engine:

| Perintah | Deskripsi |
| :--- | :--- |
| `php artisan inventory:setup` | Menginisialisasi skema StarDust Engine dan mendaftarkan model. |
| `php artisan inventory:setup --seed` | Menjalankan inisialisasi sekaligus mengisi data sampel inventaris. |
| `php artisan inventory:setup --fresh --seed` | Wiping bersih database, mengulang skema dari nol, dan mengisi data sampel. |
| `vendor/bin/stardust reconciler` | Menjalankan proses latar belakang (*daemon worker*) untuk memproses job impor massal async. |

---

## Arsitektur & Konsep StarDust Engine

Secara internal, StarDust memisahkan data menjadi dua tabel utama:

1. **Primary Data Storage (`entry_data`)**
   Tabel tempat menyimpan dokumen data lengkap dalam bentuk JSON pada kolom `fields`, beserta kolom acuan `id`, `tenant_id`, `model_id`, `created_at`, `updated_at`, dan `deleted_at`.

2. **Shared Index Slot Storage (`entry_slots_page_X`)**
   Tabel slot berukuran tetap (60 slot per halaman: 25 `str`, 15 `int`, 10 `num`, 10 `dt`) yang memetakan bidang-bidang terfilter (`isFilterable: true`) secara dinamis.

```text
[HTTP Request / Controller]
          │
          ▼
   [StarDust Facade]
     ├── write() / updateEntry() ──► entry_data (JSON Document)
     │                                    │
     └── SlotReserver & Provisioner ──────┼──► entry_slots_page_X (Index Slots)
                                          │
                                          ▼
   [EntryQuery Read] ────────────► INNER JOIN (entry_data + entry_slots_page_X)
```

---

## Pengujian Otomatis

Seluruh pengujian alur fitur (*feature tests*) dan pengujian integrasi engine dikemas dalam suite PHPUnit Laravel.

Untuk menjalankan pengujian otomatis:
```bash
php artisan test
```

Aplikasi telah lulus 100% pada suite pengujian fitur inventaris (`Tests\Feature\InventoryTest`).

---

## Lisensi

Proyek ini dirilis di bawah lisensi [MIT License](LICENSE).
