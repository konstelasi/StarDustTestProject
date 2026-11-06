# Komparasi StarDust v0.3.0-alpha.1 vs v0.3.0-alpha.2

Dokumen ini menyajikan perbandingan mendalam antara **StarDust `v0.3.0-alpha.1`** dan **StarDust `v0.3.0-alpha.2`**, mencakup ringkasan rilis, fitur baru, perubahan arsitektur, optimasi query, perbaikan bug, perbandingan API, dan panduan migrasi (upgrade guide).

---

## 1. Ikhtisar Rilis

| Parameter | v0.3.0-alpha.1 | v0.3.0-alpha.2 |
|---|---|---|
| **Tanggal Rilis** | 23 September 2026 | 31 Oktober 2026 |
| **Git Commit Reference** | `f185aa27a14f68e439414e7a54ffdea467cd6c46` | `f9b1c1f83fcf960bc61afde3c892bb283de71208` |
| **Arsitektur Dasar** | Vertical Schema Partitioning (VSP) | Vertical Schema Partitioning (VSP) + Hashed Text Keys |
| **Persyaratan Minimum** | PHP ^8.1, MySQL 8.0.13+ / MariaDB 10.11+ | PHP ^8.1, MySQL 8.0.13+ / MariaDB 10.11+ (InnoDB page ≥ 8 KB) |
| **Dukungan PDO Emulated Prepares** | Wajib `PDO::ATTR_EMULATE_PREPARES => false` | Fleksibel (Mendukung `true` maupun `false`) |

---

## 2. Fitur Baru & Perubahan Utama

### 2.1. Penempatan Nilai Null pada Pengurutan Field (`NullPlacement`)
- **Pada alpha.1**: Nilai kolom yang kosong (`NULL`) selalu mengikuti urutan default MySQL (urutan ASC = `NULL` di awal, DESC = `NULL` di akhir). Pengguna tidak dapat menentukan posisi nilai `NULL`.
- **Pada alpha.2**: Ditambahkan enum `StarDust\Read\NullPlacement` dengan nilai `First` dan `Last`.
  ```php
  use StarDust\Read\SortSpec;
  use StarDust\Read\SortDirection;
  use StarDust\Read\NullPlacement;

  // Nilai NULL selalu diletakkan di akhir meskipun urutan ASC
  $sort = SortSpec::byField('expired_at', SortDirection::Asc, NullPlacement::Last);
  ```
- **Cursor Keyset Pagination**: Token kursor pagination secara otomatis menyimpan `NullPlacement` jika berbeda dari default native, dan mencegah inkonsistensi pagination dengan melemparkan `InvalidCursorException` jika arah null berubah.

---

### 2.2. Indexing Field Teks Berbasis Hash SHA-256
- **Pada alpha.1**: Field bertipe `text` diindeks hanya menggunakan 766 karakter pertama (prefix indexing). Hal ini membatasi pencocokan nilai teks panjang dan memicu error MySQL `1713` jika banyak nilai teks panjang diperbarui dalam satu baris.
- **Pada alpha.2**: Halaman partisi baru (`page`) mengindeks field teks menggunakan key hash SHA-256 (~50 byte) yang dihitung dari keseluruhan nilai teks.
  - Pencarian filter exact match bekerja akurat tanpa batasan panjang karakter.
  - Baris dengan beberapa teks panjang dapat diperbarui (`update`) dan dihapus (`delete`) tanpa kendala row-size limit.
  - *Catatan*: Membutuhkan fungsi SHA-256 pada database server (jika tidak didukung, sistem melempar `UnsupportedServerException`). Halaman partisi lama tetap dipertahankan tanpa perubahan schema mendadak.

---

### 2.3. Optimasi Read dan Filtered Queries
- **Single Check Schema Version**: Pada alpha.1, query berfilter atau ber-cursor memeriksa schema version dua kali. Pada alpha.2, schema version hanya diperiksa satu kali melalui snapshot.
- **Single-statement Point Read**: Pemanggilan `get()` pada data yang sudah ada di warm cache kini hanya membutuhkan **1 query SQL** (sebelumnya membutuhkan multiple round-trips untuk version handshake).
- **Index-driven Equality Scan (MySQL)**: Query filter equality (`=`) pada field bertipe integer, numeric, atau datetime langsung memanfaatkan indeks spesifik field tersebut sebagai pemindai utama (driving index), membatasi pemindaian hanya ~1 halaman baris data tanpa full table scanning.

---

### 2.4. Optimasi Bulk Writes & Background Operations
- **Konstanta Multi-Row Writes**: Pada alpha.1, penulisan batch (`bulkWrite`) mengirim beberapa statement per entri. Pada alpha.2, penulisan batch dikelompokkan menjadi multi-row statement SQL dengan jumlah query konstan.
- **Background Retype & Promotion**: Proses background copy saat perubahan tipe data atau promosi field ke indeks sekarang dijalankan dalam 1 multi-row statement per chunk.

---

### 2.5. Validasi Server saat Bootstrap
- **Pada alpha.1**: Perintah `bootstrap()` tidak memeriksa ukuran halaman InnoDB database. Jika ukuran InnoDB page < 8 KB, proses baru gagal saat daemon Watcher mencoba membuat halaman partisi pertama.
- **Pada alpha.2**: `bootstrap()` langsung melempar `UnsupportedServerException` di awal jika ukuran InnoDB page < 8 KB (standar normal MySQL/MariaDB adalah 16 KB).

---

### 2.6. Perbaikan Bug (Bug Fixes)
1. **Race Condition Promosi/Retype Field**:
   - *alpha.1*: Field yang sedang menunggu slot kosong berisiko melayani query filter sebelum data lama selesai disalin, menyebabkan hasil filter tidak lengkap.
   - *alpha.2*: Slot indeks dikunci dalam status `copying`, dan query filter ditolak secara aman sampai proses penyalinan selesai.
2. **Dukungan PDO Emulated Prepares**:
   - *alpha.1*: Error syntax MySQL terjadi jika `PDO::ATTR_EMULATE_PREPARES` tidak disetel manual ke `false`.
   - *alpha.2*: Query compiler telah diperbaiki sehingga bekerja secara normal pada kedua mode (`true` atau `false`).
3. **Pesan Exception yang Lebih Jelas**:
   - Jika field telah didaftarkan sebagai filterable namun indeksnya belum siap, engine melemparkan `FieldNotFilterableException` dengan pesan deskriptif yang menginformasikan status indeks field tersebut.

---

## 3. Matriks Perbandingan API

| Komponen / Method | v0.3.0-alpha.1 | v0.3.0-alpha.2 | Keterangan |
|---|---|---|---|
| `SortSpec::byField()` | `byField(string, SortDirection)` | `byField(string, SortDirection, ?NullPlacement)` | Mendukung penempatan null (`First` / `Last`) |
| `NullPlacement` Enum | *Belum ada* | `NullPlacement::First`, `NullPlacement::Last` | Enum baru di namespace `StarDust\Read` |
| `SortSpec::effectiveNulls()` | *Belum ada* | `effectiveNulls(): NullPlacement` | Helper internal untuk resolusi ordering cursor |
| `PDO::ATTR_EMULATE_PREPARES` | Wajib `false` | Boleh `true` atau `false` | Kompatibilitas luas dengan konfigurasi Laravel bawaan |
| Engine `bootstrap()` | Lolos ukuran page berapapun | Validasi ukuran InnoDB page ≥ 8 KB | Early failure guard |
| `bulkWrite()` internals | Iterative statements | Batch multi-row statements | Peningkatan throughput write |

---

## 4. Panduan Upgrade (Migration Guide)

### Langkah 1: Update Dependency Composer
Dalam `composer.json`:
```json
"damarbob/stardust": "0.3.0-alpha.2"
```
Jalankan pembaruan:
```bash
composer update damarbob/stardust
```

### Langkah 2: Restart Daemon / Background Worker
Jika aplikasi menjalankan daemon background StarDust (`watcher`, `reconciler`, `liberator`, `chronicler`):
> [!IMPORTANT]
> Hentikan semua proses daemon alpha.1 **sebelum** daemon alpha.2 membangun halaman (`page`) baru pertamanya. Daemon alpha.1 tidak dapat mengenali slot teks yang menggunakan SHA-256 key, sehingga akan terus melakukan provisioning ulang jika tidak di-restart.

Jika menggunakan cron / scheduler (`stardust tick`), pastikan proses cron yang berjalan selanjutnya telah menggunakan codebase alpha.2.

### Langkah 3: Eksekusi Bootstrap
Jalankan ulang perintah bootstrap untuk memastikan schema dan validasi lingkungan siap:
```bash
php artisan stardust:bootstrap # atau vendor/bin/stardust bootstrap
```
> [!NOTE]
> Perintah `bootstrap()` bersifat idempoten (aman dijalankan berulang kali tanpa menghapus atau merusak data yang sudah ada).
