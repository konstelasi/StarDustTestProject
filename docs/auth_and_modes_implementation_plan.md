# Rencana Implementasi: Autentikasi User/Admin & Mode Aplikasi (Normal vs Testing)

Dokumen ini berisi rancangan arsitektur dan alur teknis sebelum mengeksekusi fitur autentikasi perbandingan peran (**Admin vs User/Staff**) serta pengoperasian **Mode Aplikasi (Normal vs Testing)** pada **StarDust Warehouse System**.

---

## 1. Pemisahan Peran & Hak Akses (Admin vs User)

### A. Struktur Data Pengguna (Laravel RDBMS)
Data autentikasi pengguna menggunakan tabel MySQL standar Laravel (`users`) via Eloquent ORM.

* **Skema Tabel `users`:**
  - `id` (bigint, PK)
  - `name` (string)
  - `email` (string, unique)
  - `password` (hashed string)
  - `role` (enum: `'admin'`, `'user'`)
  - `id_warehouse` (unsigned bigint, nullable - mengikat user biasa ke gudang tertentu)
  - `created_at`, `updated_at`

### B. Matriks Hak Akses (Role Matrix)

| Fitur / Akses | Admin (`role: admin`) | User / Staff (`role: user`) |
| :--- | :--- | :--- |
| **Login & Logout** | Allowed | Allowed |
| **Ganti Warehouse Context** | Bebas berpindah ke gudang apa saja | Dibatasi hanya pada gudang tempat dia ditugaskan |
| **Lihat Ledger Inventory (Index & Show)** | Allowed | Allowed |
| **Stock In / Stock Out (Penyesuaian Stok)** | Allowed | Allowed |
| **Registrasi & Edit Barang Baru** | Allowed | Allowed |
| **Hapus Barang (Delete Entry)** | **Allowed** | **Denied (Dilarang)** |
| **Bulk Write (Sync & Async Import)** | **Allowed** | **Denied (Dilarang)** |
| **Switch Mode Aplikasi (Normal / Testing)** | **Allowed** | **Denied (Dilarang)** |

---

## 2. Mode Aplikasi (Normal Mode vs Testing Mode)

### A. Konsep Mode Aplikasi
Aplikasi memiliki dua mode operasi yang dapat dikonfigurasi melalui `.env` atau tombol toggle Admin:
1. **Mode Normal (`APP_MODE=normal`)**:
   - Digunakan untuk operasional logistik harian asli.
   - Tanpa tombol generator data dummy di UI.
2. **Mode Testing (`APP_MODE=testing`)**:
   - Digunakan untuk pengujian performa dan simulasi data masif.
   - Menampilkan badge visual "TESTING MODE" di header aplikasi.
   - Menyediakan fitur generate data dummy massal.

### B. Aturan Pembangkitan Data Dummy (Faker vs SkuGenerator)
Pada **Mode Testing**, pembuatan data dummy menggunakan library `Faker/Factory`:
- **Menggunakan Faker**: `name` (nama barang), `category`, `quantity`, `price`, `unit`, `supplier`, `location`, `min_stock`, `description`, `batch_number`, `expiry_date`, `serial_number`.
- **PENGECUALIAN (SKU)**: **TIDAK menggunakan Faker string**. SKU diproses murni menggunakan kelas algoritma terisolasi **`App\Support\SkuGenerator`**:
  ```text
  Format SKU: [KODE_KATEGORI]-[KODE_GUDANG]-[NOMOR_URUT_SEQUENCE]
  Contoh: LAP-WHJKT-001, KBL-WHSBY-002, KOP-WHBDG-003
  ```

---

## 3. Komponen & Berkas yang Akan Dibuat / Dimodifikasi

### A. Berkas Baru (`[NEW]`)
1. **`docs/auth_and_modes_implementation_plan.md`**: Dokumen perencanaan teknis (file ini).
2. **`database/migrations/xxxx_xx_xx_add_role_and_warehouse_to_users_table.php`**: Migrasi penambahan kolom `role` dan `id_warehouse` pada tabel `users`.
3. **`app/Http/Middleware/EnsureRole.php`**: Middleware pemeriksaan peran pengguna.
4. **`app/Http/Controllers/AuthController.php`**: Controller penanganan Login, Auth, dan Logout.
5. **`resources/views/auth/login.blade.php`**: Tampilan halaman login bertema Classic Dark Industrial Logbook.
6. **`database/seeders/UserSeeder.php`**: Seeder akun standar Admin & Staff.

### B. Berkas Modifikasi (`[MODIFY]`)
1. **`app/Http/Controllers/InventoryController.php`**:
   - Penambahan proteksi middleware peran per action (Admin vs User).
   - Pengikatan context gudang otomatis untuk akun `role: user`.
2. **`resources/views/layouts/app.blade.php`**:
   - Penambahan info profil pengguna yang sedang login & tombol Logout.
   - Penambahan badge "TESTING MODE" dan tombol switcher mode (khusus Admin).
3. **`routes/web.php`**: Penambahan rute autentikasi (`/login`, `/logout`) dan proteksi rute inventory via middleware `auth` dan `role`.

---

## 4. Rencana Verifikasi & Pengujian

1. **Pengujian Login & Role Isolation**:
   - Login sebagai `admin@stardust.com` ➔ Pastikan semua tombol (Delete, Bulk Write, Switch Warehouse) muncul.
   - Login sebagai `staff@stardust.com` ➔ Pastikan tombol Hapus dan Bulk Write tersembunyi, dan gudang terkunci pada gudang tugasnya.
2. **Pengujian Mode Testing & SKU Algorithm**:
   - Aktifkan Mode Testing ➔ Jalankan generator data dummy.
   - Verifikasi bahwa field lain digenerate oleh Faker, namun kolom `sku` mematuhi algoritma `SkuGenerator` (misal: `ELE-WHJKT-001`).
3. **Pengujian Automated Tests**:
   - Jalankan `php artisan test` untuk memastikan seluruh fitur baru lulus 100%.
