# Rekapitulasi Audit Pelanggaran SOLID Principles

Dokumen ini berisi inventarisasi file dalam proyek `StarDustTestProject` yang melanggar prinsip desain perangkat lunak **SOLID** (*Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion*).

> **Petunjuk:** Berikan tanda centang `[x]` pada kotak `[ ]` di samping file/item jika sudah selesai diperbaiki.

---

## 1. Matriks Rekapitulasi & Status Perbaikan

| No | Status | File Path | S (SRP) | O (OCP) | L (LSP) | I (ISP) | D (DIP) | Tingkat Keparahan |
|:---:|:---:|:---|:---:|:---:|:---:|:---:|:---:|:---:|
| 1 | [ ] | `app/Http/Controllers/InventoryController.php` | ❌ | ❌ | ➖ | ➖ | ❌ | **Tinggi (Critical)** |
| 2 | [ ] | `app/Models/Warehouse.php` | ➖ | ➖ | ❌ | ➖ | ➖ | **Tinggi** |
| 3 | [ ] | `app/Http/Controllers/AuthController.php` | ❌ | ➖ | ➖ | ➖ | ❌ | **Tinggi** |
| 4 | [ ] | `app/Http/Controllers/WarehouseStaffController.php` | ❌ | ❌ | ➖ | ➖ | ❌ | **Sedang** |
| 5 | [ ] | `app/Http/Controllers/CustomFieldController.php` | ❌ | ➖ | ➖ | ➖ | ❌ | **Sedang** |
| 6 | [ ] | `app/Http/Middleware/EnsureRole.php` | ➖ | ❌ | ➖ | ➖ | ❌ | **Sedang** |
| 7 | [ ] | `app/Models/User.php` | ➖ | ❌ | ➖ | ❌ | ❌ | **Sedang** |
| 8 | [ ] | `app/Support/TenantProvisioner.php` | ➖ | ❌ | ➖ | ➖ | ❌ | **Rendah** |
| 9 | [ ] | `app/Console/Commands/SetupInventoryModel.php` | ❌ | ➖ | ➖ | ➖ | ❌ | **Rendah** |
| 10 | [ ] | `app/Console/Commands/SeedFakeInventory.php` | ❌ | ➖ | ➖ | ➖ | ➖ | **Rendah** |

---

## 2. Analisis Detail Pelanggaran per File

---

### 1. `app/Http/Controllers/InventoryController.php` (831 baris)
*Tingkat Keparahan: **Tinggi (Critical - God Object Controller)***

#### Prinsip yang Dilanggar:
- **Single Responsibility Principle (SRP)**
- **Open/Closed Principle (OCP)**
- **Dependency Inversion Principle (DIP)**

#### Bukti & Lokasi Pelanggaran:
1. **SRP (Multi-Domain Responsibilities):**
   - Menangani HTTP routing & responses.
   - Mengelola context multi-tenant dan sesi active warehouse (`resolveContext`, `loadWarehouses`).
   - Menyusun query AST kompleks (*Abstract Syntax Tree*) untuk StarDust filter nodes (`LeafNode`, `AndNode`, `SortSpec`).
   - Melakukan schema definition dan slot reservation secara on-the-fly (`ensureStarDustFieldSlot`).
   - Menjalankan logika bisnis pergerakan inventaris (`stockIn`, `stockOut`).
   - Men-generate dummy data menggunakan `Faker` di dalam controller (`bulkImport` baris 716–759).
   - Menjalankan koordinasi antrean bulk import sync vs async.
   - Melakukan direct database manipulation secara fisik (`DB::table('entry_data')->where(...)->delete()` baris 823–826).
2. **OCP (Tertutup untuk Ekstensi):**
   - Method `castCustomFieldValue()` (baris 570) dan `ensureStarDustFieldSlot()` menggunakan conditional `switch`/`match` hardcoded. Menambah tipe kustom baru memaksa edit method internal controller.
3. **DIP (Coupling ke Concrete & Globals):**
   - Bergantung langsung pada fasad statis global: `Tenant::id()`, `Auth::user()`, `session()`, `DB::table()`, dan `FakerFactory::create()`.
   - Mengabaikan enkapsulasi engine StarDust dengan menghapus langsung baris tabel SQL `entry_data`.

#### Rekomendasi Refactoring:
- Ekstrak logika query StarDust ke dalam `InventoryQueryService` atau `InventoryRepository`.
- Ekstrak aksi stok ke dalam `StockMovementService` (`stockIn`, `stockOut`).
- Pindahkan logika bulk generation dummy ke seeder / command terpisah (`InventoryBulkImportService`).
- Pindahkan logika casting custom fields ke `CustomFieldCaster` (Strategy Pattern).

---

### 2. `app/Models/Warehouse.php`
*Tingkat Keparahan: **Tinggi (Pelanggaran Kontrak Model)***

#### Prinsip yang Dilanggar:
- **Liskov Substitution Principle (LSP)**

#### Bukti & Lokasi Pelanggaran:
```php
class Warehouse extends Model
{
    protected $table = 'warehouse_staff';
    protected $primaryKey = 'warehouse_id';
    public $timestamps = false;
    ...
}
```
- `Warehouse` meng-extend `Illuminate\Database\Eloquent\Model`, namun ia **bukanlah representasi entitas tabel warehouse SQL**, melainkan hanya proxy/intermediary ke tabel pivot `warehouse_staff` karena data gudang sebenarnya berada di engine StarDust.
- **Konsekuensi LSP:** Jika suatu method menerima parameter bertipe `Model` atau `Warehouse` lalu memanggil method standar Eloquent seperti `$warehouse->save()`, `Warehouse::create([...])`, atau `$warehouse->delete()`, perilakunya akan merusak atau menghapus isi tabel pivot `warehouse_staff` alih-alih entitas gudang. Kelas turunan gagal memenuhi kontrak superclass `Model`.

#### Rekomendasi Refactoring:
- Hapus pengalihan tabel semu `protected $table = 'warehouse_staff'` dari model `Warehouse`.
- Buat tabel fisik `warehouses` di database (atau buat kelas DTO / Value Object terpisah jika gudang murni entitas StarDust).
- Pastikan instansiasi standar Eloquent (`Warehouse::create`, `$warehouse->save()`, `$warehouse->delete()`) tidak merusak isi tabel pivot `warehouse_staff`.

---

### 3. `app/Http/Controllers/AuthController.php`
*Tingkat Keparahan: **Tinggi***

#### Prinsip yang Dilanggar:
- **Single Responsibility Principle (SRP)**
- **Dependency Inversion Principle (DIP)**

#### Bukti & Lokasi Pelanggaran:
1. **SRP (Terlalu Banyak Domain):**
   - Melayani Autentikasi Pengguna (`login`, `logout`).
   - Melayani Registrasi Pengguna oleh Administrator (`register`, `showRegisterForm`).
   - Melayani Reset & Pengaturan Password (`sendResetLink`, `resetPassword`, `showResetPasswordForm`).
   - Melayani Konfigurasi Global Sistem / App Mode Toggle (`toggleAppMode`).
   - Mengambil data katalog gudang dari engine StarDust (`getWarehouseList`).
   - Menulis langsung ke tabel pivot `warehouse_staff` via query builder `DB::table('warehouse_staff')->updateOrInsert(...)`.
2. **DIP:**
   - Memanggil `Password::sendResetLink()` dan query database mentah tanpa service layer.

#### Rekomendasi Refactoring:
- Pecah `AuthController` menjadi:
  - `AuthenticatedSessionController` (login, logout).
  - `UserRegistrationController` (khusus pendaftaran staf/admin).
  - `PasswordResetController` (forgot password & reset token).
  - `AppModeController` (toggle konfigurasi sistem/environment mode).

---

### 4. `app/Http/Controllers/WarehouseStaffController.php`
*Tingkat Keparahan: **Sedang***

#### Prinsip yang Dilanggar:
- **Single Responsibility Principle (SRP)**
- **Open/Closed Principle (OCP)**
- **Dependency Inversion Principle (DIP)**

#### Bukti & Lokasi Pelanggaran:
1. **SRP:**
   - Melakukan komputasi agregasi mentah menggabungkan data pivot `warehouse_staff` dan kolom warisan `users.id_warehouse` secara manual di controller (baris 40–72).
   - Melakukan direct delete dan batch insert SQL mentah pada pivot table:
     `DB::table('warehouse_staff')->where('warehouse_id', $warehouseId)->delete();`
     `DB::table('warehouse_staff')->insert($rows);`
   - Menduplikasi logika `getWarehouseList()` yang sama persis dengan `AuthController`.
2. **OCP:**
   - Mengecualikan role tertentu dengan hardcoded string `whereNotIn('role', ['admin_platform', 'admin'])`.
3. **DIP:**
   - Tidak memanfaatkan relasi Eloquent `$warehouse->users()->sync(...)` atau Service Class, melainkan bergantung pada query builder `DB::table()`.

#### Rekomendasi Refactoring:
- Pindahkan logika penugasan staf ke `WarehouseAssignmentService`.
- Manfaatkan relasi Eloquent `belongsToMany` dengan method `sync()` atau `syncWithPivotValues()`.

---

### 5. `app/Http/Controllers/CustomFieldController.php`
*Tingkat Keparahan: **Sedang***

#### Prinsip yang Dilanggar:
- **Single Responsibility Principle (SRP)**
- **Dependency Inversion Principle (DIP)**

#### Bukti & Lokasi Pelanggaran:
```php
$reserver = new SlotReserver(
    $this->stardust->pdo(),
    $this->stardust->config()->clock,
    $this->stardust->logger()
);
$reserver->reserve($fieldId);
```
- Controller tingkat tinggi membocorkan detail implementasi internal database engine StarDust.
- Menginstansiasi langsung `new SlotReserver` dan mengambil koneksi `pdo()` secara telanjang di dalam controller.
- Menggabungkan tanggung jawab presentation (HTTP web form) dengan orkestrasi skema database tingkat rendah.

#### Rekomendasi Refactoring:
- Buat service `CustomFieldSchemaService` yang mengenkapsulasi reservasi slot dan pendaftaran skema.
- Injeksikan service tersebut ke controller.

---

### 6. `app/Http/Middleware/EnsureRole.php`
*Tingkat Keparahan: **Sedang***

#### Prinsip yang Dilanggar:
- **Open/Closed Principle (OCP)**
- **Dependency Inversion Principle (DIP)**

#### Bukti & Lokasi Pelanggaran:
```php
foreach ($roles as $role) {
    if ($role === 'admin_platform' && $user->isAdminPlatform()) { ... }
    if ($role === 'admin_perusahaan' && $user->isAdminPerusahaan()) { ... }
    if ($role === 'admin_gudang' && $user->isAdminGudang()) { ... }
    if ($role === 'staff_gudang' && $user->isStaffGudang()) { ... }
    ...
}
```
- Middleware tidak tertutup untuk modifikasi. Jika sistem menambah peran baru (misalnya `supervisor`, `auditor`, `vendor`), developer harus membuka dan mengubah kode `EnsureRole.php`.
- Bergantung erat pada implementasi metode spesifik di class `User`.

#### Rekomendasi Refactoring:
- Gunakan Laravel Authorization standard (Gates / Policies) atau Interface `HasRoles`:
  `$user->hasRole($role)` atau `$user->can($role)`.

---

### 7. `app/Models/User.php`
*Tingkat Keparahan: **Sedang***

#### Prinsip yang Dilanggar:
- **Open/Closed Principle (OCP)**
- **Interface Segregation Principle (ISP)**
- **Dependency Inversion Principle (DIP)**

#### Bukti & Lokasi Pelanggaran:
1. **OCP:**
   - Semua role dicek melalui hardcoded match/in_array (`isAdminPlatform`, `isAdminPerusahaan`, `isAdminGudang`, `isStaffGudang`, `getRoleLabelAttribute`).
2. **ISP:**
   - Satu model `User` memaksakan seluruh jenis akun memiliki method yang tidak relevan bagi perannya (misal: Admin Platform dipaksa memiliki method gudang `assignedWarehouses`, Staf Gudang dipaksa memiliki method perusahaan `managedCompanies`).
3. **DIP:**
   - Method `warehouseIds()` (baris 224) melakukan panggilan langsung ke `\Illuminate\Support\Facades\DB::table('warehouse_staff')` di dalam model, mengabaikan relasi Eloquent yang sudah didefinisikan.

#### Rekomendasi Refactoring:
- Gunakan `$this->assignedWarehouses()->pluck('warehouses.id')->toArray()` daripada query DB mentah.
- Gunakan Enum `UserRole` dengan method bawaan untuk label dan otorisasi.

---

### 8. `app/Support/TenantProvisioner.php`
*Tingkat Keparahan: **Rendah***

#### Prinsip yang Dilanggar:
- **Dependency Inversion Principle (DIP)**
- **Open/Closed Principle (OCP)**

#### Bukti & Lokasi Pelanggaran:
- Menginstansiasi concrete class `new SlotReserver($this->stardust->pdo(), ...)` secara manual daripada menerima instance lewat constructor injection.
- Mapping tipe data `match (strtolower(trim($cdef->type)))` hardcoded di dalam method private `itemFields()`.

#### Rekomendasi Refactoring:
- Daftarkan `SlotReserver` ke Service Container dan injeksikan lewat constructor.

---

### 9. `app/Console/Commands/SetupInventoryModel.php`
*Tingkat Keparahan: **Rendah***

#### Prinsip yang Dilanggar:
- **Single Responsibility Principle (SRP)**
- **Dependency Inversion Principle (DIP)**

#### Bukti & Lokasi Pelanggaran:
- Menduplikasi definisi array `$gudangFields` dan `$barangFields` yang sudah ada di `TenantProvisioner`.
- Menjalankan raw SQL `DROP TABLE IF EXISTS` dan inspeksi metadata driver database SQLite/MySQL secara manual di dalam command console.

#### Rekomendasi Refactoring:
- Gunakan `TenantProvisioner::provision($tenantId)` untuk mendaftarkan skema.
- Buat class migrasi/seeder terisolasi untuk proses wipe tabel StarDust.

---

### 10. `app/Console/Commands/SeedFakeInventory.php`
*Tingkat Keparahan: **Rendah***

#### Prinsip yang Dilanggar:
- **Single Responsibility Principle (SRP)**

#### Bukti & Lokasi Pelanggaran:
- Mengandung algoritma pembuatan payload item dummy Faker (`CATEGORIES`, loop item, serial number, batch number) yang diduplikasi hampir 100% dengan method `InventoryController::bulkImport`.

#### Rekomendasi Refactoring:
- Buat `InventoryDataFactory` atau `FakeInventoryGenerator` bersama yang dipakai oleh command console maupun bulk import controller.
