<?php

namespace Database\Seeders;

use App\Support\SkuGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use StarDust\StarDust;
use StarDust\Write\EntryPayload;

class InventorySeeder extends Seeder
{
    private const CATEGORIES = [
        'Elektronik',
        'Aksesori',
        'Peralatan Kantor',
        'Bahan Konsumsi',
        'Perawatan',
        'Power & Battery',
        'Logistik',
        'Hardware Gudang',
        'Kabel & Adaptor',
    ];

    public function run(): void
    {
        /** @var StarDust $stardust */
        $stardust = app(StarDust::class);
        $tenantId = (int) config('stardust.tenant_id', 1);

        $warehouseModelName = config('stardust.warehouse_model_name', 'gudang');
        $itemModelName = config('stardust.item_model_name', 'barang');

        $models = $stardust->listModels($tenantId);
        $gudangModel = collect($models)->firstWhere('name', $warehouseModelName);
        $barangModel = collect($models)->firstWhere('name', $itemModelName);

        if (! $gudangModel || ! $barangModel) {
            $this->command?->warn('Model Gudang / Barang belum terdaftar di StarDust Engine. Jalankan `php artisan inventory:setup` terlebih dahulu.');

            return;
        }

        $gudangModelId = $gudangModel->modelId;
        $barangModelId = $barangModel->modelId;

        // 1. Pastikan gudang-gudang tersedia
        $warehouseIds = DB::table('entry_data')
            ->where('tenant_id', $tenantId)
            ->where('model_id', $gudangModelId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $warehouseCodes = [];

        if (count($warehouseIds) === 0) {
            $seedWarehouses = config('stardust.seed_warehouses', [
                ['name' => 'Gudang Utama Jakarta', 'code' => 'WH-JKT-01', 'location' => 'Jl. Industri Raya No. 45, Jakarta Barat', 'manager' => 'Budi Santoso'],
                ['name' => 'Gudang Cabang Surabaya', 'code' => 'WH-SUB-02', 'location' => 'Kawasan Industri Rungkut Industri III No. 12, Surabaya', 'manager' => 'Siti Rahmawati'],
                ['name' => 'Gudang Logistik Bandung', 'code' => 'WH-BDG-03', 'location' => 'Jl. Soekarno-Hatta No. 210, Bandung', 'manager' => 'Ahmad Hidayat'],
                ['name' => 'Gudang Transit Medan', 'code' => 'WH-MDN-04', 'location' => 'Jl. Yos Sudarso KM 7.5, Medan', 'manager' => 'Rudi Hermawan'],
                ['name' => 'Gudang Hub Makassar', 'code' => 'WH-MKS-05', 'location' => 'Kawasan Industri Makassar (KIMA) Blok A No. 8', 'manager' => 'Andi Wijaya'],
            ]);

            foreach ($seedWarehouses as $wh) {
                $res = $stardust->write(new EntryPayload(
                    tenantId: $tenantId,
                    modelId: $gudangModelId,
                    fields: $wh,
                ));
                $warehouseIds[] = $res->entryId;
                $warehouseCodes[$res->entryId] = $wh['code'];
            }
        } else {
            $rows = DB::table('entry_data')
                ->whereIn('id', $warehouseIds)
                ->get(['id', 'fields']);

            foreach ($rows as $r) {
                $f = json_decode($r->fields, true) ?? [];
                $warehouseCodes[$r->id] = $f['code'] ?? ('WH-'.str_pad((string) $r->id, 2, '0', STR_PAD_LEFT));
            }
        }

        // 2. Data 50 items barang yang lengkap & realistis
        $sampleItems = [
            // Sektor Elektronik & IT
            ['name' => 'Laptop Asus ROG Strix G15', 'category' => 'Elektronik', 'qty' => 12, 'price' => 18500000, 'unit' => 'Unit', 'supp' => 'PT Asus Indonesia', 'loc' => 'Sektor A - Rak 01', 'min' => 5, 'weight' => 2.30, 'cbm' => 0.015, 'rec' => '2024-02-10 09:00:00', 'exp' => '2028-02-10 00:00:00', 'desc' => 'AMD Ryzen 7, RAM 16GB, RTX 3060 6GB', 'warranty' => 24, 'sn' => 'ROG-99281-JKT'],
            ['name' => 'Printer HP LaserJet Pro M404dn', 'category' => 'Peralatan Kantor', 'qty' => 2, 'price' => 4300000, 'unit' => 'Unit', 'supp' => 'PT Anugerah Printer', 'loc' => 'Zona 1 - Blok C', 'min' => 4, 'weight' => 8.20, 'cbm' => 0.060, 'rec' => '2023-07-05 08:30:00', 'exp' => '2027-07-05 00:00:00', 'desc' => 'Monochrome Laser Printer Auto Duplex', 'warranty' => 12],
            ['name' => 'Monitor Dell UltraSharp 27 Inch 4K', 'category' => 'Elektronik', 'qty' => 8, 'price' => 7200000, 'unit' => 'Unit', 'supp' => 'PT Dell Technologies', 'loc' => 'Sektor A - Rak 02', 'min' => 3, 'weight' => 6.50, 'cbm' => 0.045, 'rec' => '2024-05-15 11:30:00', 'exp' => '2029-05-15 00:00:00', 'desc' => 'IPS 4K UHD, USB-C Hub, Color Calibrated', 'warranty' => 36, 'sn' => 'DELL-4412-JKT'],
            ['name' => 'Server Rack HP ProLiant DL380 Gen10', 'category' => 'Elektronik', 'qty' => 3, 'price' => 45000000, 'unit' => 'Unit', 'supp' => 'PT Hardware Utama', 'loc' => 'Sektor A - Server Room', 'min' => 1, 'weight' => 24.50, 'cbm' => 0.120, 'rec' => '2023-11-01 10:00:00', 'exp' => '2028-11-01 00:00:00', 'desc' => 'Dual Xeon Gold, 128GB ECC RAM, 4x2TB SAS', 'warranty' => 36, 'sn' => 'HPE-DL380-009'],
            ['name' => 'Switch Router Cisco Catalyst 9300 48-Port', 'category' => 'Elektronik', 'qty' => 6, 'price' => 28500000, 'unit' => 'Unit', 'supp' => 'PT Hardware Utama', 'loc' => 'Sektor A - Rak Net', 'min' => 2, 'weight' => 7.80, 'cbm' => 0.035, 'rec' => '2023-08-12 14:20:00', 'exp' => '2028-08-12 00:00:00', 'desc' => 'Layer 3 Managed Switch, PoE+ 437W', 'warranty' => 24, 'sn' => 'CSCO-CAT93-48P'],
            ['name' => 'Proyektor Epson EB-FH52 Full HD', 'category' => 'Elektronik', 'qty' => 5, 'price' => 11200000, 'unit' => 'Unit', 'supp' => 'PT Anugerah Printer', 'loc' => 'Sektor A - Rak 03', 'min' => 2, 'weight' => 3.10, 'cbm' => 0.018, 'rec' => '2025-01-20 09:15:00', 'exp' => '2029-01-20 00:00:00', 'desc' => '4000 Lumens, Wireless AV, Auto Keystone', 'warranty' => 24, 'sn' => 'EPS-FH52-771'],
            ['name' => 'Tablet Samsung Galaxy Tab S9 Ultra', 'category' => 'Elektronik', 'qty' => 14, 'price' => 16999000, 'unit' => 'Unit', 'supp' => 'CV Gadget Mania', 'loc' => 'Sektor A - Rak Sec', 'min' => 4, 'weight' => 0.73, 'cbm' => 0.003, 'rec' => '2025-03-05 13:00:00', 'exp' => '2028-03-05 00:00:00', 'desc' => '14.6 Inch Dynamic AMOLED 2X, S-Pen included', 'warranty' => 12, 'sn' => 'SAMSUNG-S9U-021'],

            // Aksesori & Peripheral
            ['name' => 'Keyboard Mechanical Keychron K2', 'category' => 'Aksesori', 'qty' => 25, 'price' => 1450000, 'unit' => 'Pcs', 'supp' => 'CV Gadget Mania', 'loc' => 'Sektor B - Rak 01', 'min' => 10, 'weight' => 0.85, 'cbm' => 0.005, 'rec' => '2024-06-20 14:00:00', 'exp' => '2027-06-20 00:00:00', 'desc' => 'Wireless RGB, Gateron Brown Switch', 'warranty' => 12],
            ['name' => 'Mouse Logitech MX Master 3S', 'category' => 'Aksesori', 'qty' => 3, 'price' => 1650000, 'unit' => 'Pcs', 'supp' => 'CV Gadget Mania', 'loc' => 'Sektor B - Rak 02', 'min' => 5, 'weight' => 0.30, 'cbm' => 0.002, 'rec' => '2024-07-22 10:15:00', 'exp' => '2027-07-22 00:00:00', 'desc' => 'Ergonomic 8K DPI Quiet Clicks', 'warranty' => 12],
            ['name' => 'Webcam Logitech Brio 4K Ultra HD', 'category' => 'Aksesori', 'qty' => 15, 'price' => 2450000, 'unit' => 'Pcs', 'supp' => 'CV Gadget Mania', 'loc' => 'Sektor B - Rak 03', 'min' => 5, 'weight' => 0.35, 'cbm' => 0.003, 'rec' => '2024-09-10 11:00:00', 'exp' => '2027-09-10 00:00:00', 'desc' => '4K HDR, Dual Noise-Canceling Microphones', 'warranty' => 12],
            ['name' => 'Headset Jabra Evolve2 65 Flex', 'category' => 'Aksesori', 'qty' => 10, 'price' => 3800000, 'unit' => 'Pcs', 'supp' => 'CV Gadget Mania', 'loc' => 'Sektor B - Rak 04', 'min' => 4, 'weight' => 0.45, 'cbm' => 0.004, 'rec' => '2025-02-18 16:30:00', 'exp' => '2028-02-18 00:00:00', 'desc' => 'Active Noise Cancellation, Foldable Bluetooth', 'warranty' => 24],
            ['name' => 'Docking Station Anker 13-in-1 USB-C', 'category' => 'Aksesori', 'qty' => 18, 'price' => 1950000, 'unit' => 'Pcs', 'supp' => 'CV Gadget Mania', 'loc' => 'Sektor B - Rak 05', 'min' => 6, 'weight' => 0.60, 'cbm' => 0.003, 'rec' => '2025-04-12 10:45:00', 'exp' => '2028-04-12 00:00:00', 'desc' => '85W Pass-Through Charging, Triple Display', 'warranty' => 18],

            // Peralatan Kantor
            ['name' => 'Printer Multifunction Canon PIXMA G3010 Ink Tank', 'category' => 'Peralatan Kantor', 'qty' => 5, 'price' => 2250000, 'unit' => 'Unit', 'supp' => 'PT Anugerah Printer', 'loc' => 'Zona 1 - Blok C2', 'min' => 2, 'weight' => 6.30, 'cbm' => 0.040, 'rec' => '2024-09-10 10:00:00', 'exp' => '2028-09-10 00:00:00', 'desc' => 'Wireless All-in-One High Volume Ink Tank Printer', 'warranty' => 24],
            ['name' => 'Mesin Penghancur Kertas Fellowes Powershred 79Ci', 'category' => 'Peralatan Kantor', 'qty' => 7, 'price' => 3850000, 'unit' => 'Unit', 'supp' => 'PT Anugerah Printer', 'loc' => 'Zona 1 - Blok D', 'min' => 3, 'weight' => 12.80, 'cbm' => 0.085, 'rec' => '2024-01-14 13:20:00', 'exp' => '2029-01-14 00:00:00', 'desc' => '100% Jam Proof, Cross-Cut 16 Sheets', 'warranty' => 24],
            ['name' => 'Scanner Canon ImageFORMULA DR-C225 II', 'category' => 'Peralatan Kantor', 'qty' => 4, 'price' => 6700000, 'unit' => 'Unit', 'supp' => 'PT Anugerah Printer', 'loc' => 'Zona 1 - Blok E', 'min' => 2, 'weight' => 2.70, 'cbm' => 0.020, 'rec' => '2024-03-30 09:40:00', 'exp' => '2028-03-30 00:00:00', 'desc' => 'Compact High-Speed Sheetfed Scanner 25ppm', 'warranty' => 12],
            ['name' => 'Kertas HVS PaperOne A4 80GSM (1 Box 5 Ream)', 'category' => 'Peralatan Kantor', 'qty' => 120, 'price' => 245000, 'unit' => 'Box', 'supp' => 'PT Anugerah Printer', 'loc' => 'Zona 1 - Pallet A', 'min' => 30, 'weight' => 12.50, 'cbm' => 0.025, 'rec' => '2026-01-10 08:00:00', 'exp' => '2031-01-10 00:00:00', 'desc' => 'Ultra White ProDigi HD Print Technology'],
            ['name' => 'Tinta Refill Epson 003 Black 65ml', 'category' => 'Peralatan Kantor', 'qty' => 85, 'price' => 95000, 'unit' => 'Botol', 'supp' => 'PT Anugerah Printer', 'loc' => 'Zona 1 - Rak Tinta', 'min' => 20, 'weight' => 0.10, 'cbm' => 0.001, 'rec' => '2025-11-15 14:00:00', 'exp' => '2027-11-15 00:00:00', 'desc' => 'Original Ink for L3110 / L3150 / L5190', 'batch' => 'BATCH-INK-2025-11'],

            // Bahan Konsumsi & Perawatan (Perishable items - Tahun Kedaluwarsa Lampau & Mendatang)
            ['name' => 'Kopi Arabika Premium Gayo 1KG', 'category' => 'Bahan Konsumsi', 'qty' => 40, 'price' => 175000, 'unit' => 'Bungkus', 'supp' => 'CV Kopi Nusantara', 'loc' => 'Zona 3 - Cold Room', 'min' => 10, 'weight' => 1.05, 'cbm' => 0.003, 'rec' => '2023-01-10 07:00:00', 'exp' => '2024-01-10 00:00:00', 'desc' => 'Medium Dark Roast Single Origin (EXPIRED 2024)', 'batch' => 'BATCH-2023-KOP-01'],
            ['name' => 'Kopi Robusta Toraja Blend 1KG', 'category' => 'Bahan Konsumsi', 'qty' => 50, 'price' => 135000, 'unit' => 'Bungkus', 'supp' => 'CV Kopi Nusantara', 'loc' => 'Zona 3 - Cold Room', 'min' => 15, 'weight' => 1.05, 'cbm' => 0.003, 'rec' => '2024-05-01 08:00:00', 'exp' => '2025-05-01 00:00:00', 'desc' => 'Full Body Roast Blend (EXPIRED 2025)', 'batch' => 'BATCH-2024-KOP-05'],
            ['name' => 'Susu UHT Full Cream 1 Liter (1 Karton 12 Pcs)', 'category' => 'Bahan Konsumsi', 'qty' => 35, 'price' => 210000, 'unit' => 'Karton', 'supp' => 'CV Kopi Nusantara', 'loc' => 'Zona 3 - Rak S-02', 'min' => 10, 'weight' => 12.60, 'cbm' => 0.018, 'rec' => '2022-03-15 10:00:00', 'exp' => '2022-12-15 00:00:00', 'desc' => 'Susu Segar UHT Steril (EXPIRED 2022)', 'batch' => 'BATCH-MILK-2022-A'],
            ['name' => 'Gula Pasir Kristal Putih 1KG', 'category' => 'Bahan Konsumsi', 'qty' => 90, 'price' => 18500, 'unit' => 'Bungkus', 'supp' => 'CV Kopi Nusantara', 'loc' => 'Zona 3 - Rak S-03', 'min' => 25, 'weight' => 1.00, 'cbm' => 0.002, 'rec' => '2025-08-01 09:30:00', 'exp' => '2027-08-01 00:00:00', 'desc' => 'Gula Pasir Tebu Olahan Premium', 'batch' => 'BATCH-GLA-2025-08'],
            ['name' => 'Air Mineral Galon 19 Liter', 'category' => 'Bahan Konsumsi', 'qty' => 60, 'price' => 22000, 'unit' => 'Galon', 'supp' => 'CV Kopi Nusantara', 'loc' => 'Zona 3 - Area Galon', 'min' => 20, 'weight' => 19.50, 'cbm' => 0.040, 'rec' => '2026-02-01 07:30:00', 'exp' => '2028-02-01 00:00:00', 'desc' => 'Air Pegunungan Alami Terfiltrasi', 'batch' => 'BATCH-AQUA-2026'],

            // Perawatan & Cleaning Chemicals
            ['name' => 'Cairan Pembersih Layar Screen Clean 500ml', 'category' => 'Perawatan', 'qty' => 100, 'price' => 45000, 'unit' => 'Botol', 'supp' => 'PT Chemical Care', 'loc' => 'Rak D-09', 'min' => 20, 'weight' => 0.55, 'cbm' => 0.001, 'rec' => '2023-09-10 15:20:00', 'exp' => '2025-09-10 00:00:00', 'desc' => 'Anti-Static Screen Cleaning Solution (EXPIRED 2025)', 'batch' => 'BATCH-CLN-102'],
            ['name' => 'Hand Sanitizer Gel 5 Liter', 'category' => 'Perawatan', 'qty' => 15, 'price' => 165000, 'unit' => 'Jerigen', 'supp' => 'PT Chemical Care', 'loc' => 'Rak D-10', 'min' => 5, 'weight' => 5.20, 'cbm' => 0.008, 'rec' => '2021-04-12 11:00:00', 'exp' => '2023-04-12 00:00:00', 'desc' => 'Alcohol 75% Antiseptic Gel (EXPIRED 2023)', 'batch' => 'BATCH-SAN-2021'],
            ['name' => 'Disinfectant Spray Sanitizer 400ml', 'category' => 'Perawatan', 'qty' => 45, 'price' => 38000, 'unit' => 'Kaleng', 'supp' => 'PT Chemical Care', 'loc' => 'Rak D-11', 'min' => 15, 'weight' => 0.42, 'cbm' => 0.001, 'rec' => '2024-10-05 14:15:00', 'exp' => '2026-10-05 00:00:00', 'desc' => 'Aerosol Disinfectant Kills 99.9% Bacteria', 'batch' => 'BATCH-DSF-2024'],
            ['name' => 'Sabun Cuci Tangan Liquid Anti-Bakteri 4 Liter', 'category' => 'Perawatan', 'qty' => 22, 'price' => 88000, 'unit' => 'Jerigen', 'supp' => 'PT Chemical Care', 'loc' => 'Rak D-12', 'min' => 8, 'weight' => 4.15, 'cbm' => 0.006, 'rec' => '2025-06-20 10:00:00', 'exp' => '2027-06-20 00:00:00', 'desc' => 'Refill Soap Jerigen Aroma Lemon', 'batch' => 'BATCH-SOAP-2025'],
            ['name' => 'Kain Lap Microfiber Premium 40x40cm (Pack 10 Pcs)', 'category' => 'Perawatan', 'qty' => 60, 'price' => 75000, 'unit' => 'Pack', 'supp' => 'PT Chemical Care', 'loc' => 'Rak D-13', 'min' => 15, 'weight' => 0.35, 'cbm' => 0.002, 'rec' => '2025-12-01 13:00:00', 'exp' => '2030-12-01 00:00:00', 'desc' => 'Ultra Soft Lint-Free Microfiber Cleaning Cloth'],

            // Power & Battery
            ['name' => 'UPS APC Back-UPS 1100VA 660W', 'category' => 'Power & Battery', 'qty' => 5, 'price' => 2100000, 'unit' => 'Unit', 'supp' => 'PT Schneider Electric', 'loc' => 'Rak Heavy-01', 'min' => 2, 'weight' => 12.00, 'cbm' => 0.035, 'rec' => '2023-06-18 10:00:00', 'exp' => '2027-06-18 00:00:00', 'desc' => 'Battery Backup & Surge Protector', 'warranty' => 24],
            ['name' => 'Baterai VRLA Aki Dry Lead-Acid 12V 100Ah', 'category' => 'Power & Battery', 'qty' => 8, 'price' => 2850000, 'unit' => 'Unit', 'supp' => 'PT Schneider Electric', 'loc' => 'Rak Heavy-02', 'min' => 3, 'weight' => 31.50, 'cbm' => 0.045, 'rec' => '2022-11-20 11:30:00', 'exp' => '2024-11-20 00:00:00', 'desc' => 'Deep Cycle Battery for Industrial UPS/Solar (EXPIRED 2024)', 'batch' => 'BATCH-BAT-2022'],
            ['name' => 'Baterai AA Alkaline Panasonic Eneloop Pro (Pack 4 Pcs)', 'category' => 'Power & Battery', 'qty' => 75, 'price' => 225000, 'unit' => 'Pack', 'supp' => 'PT Schneider Electric', 'loc' => 'Rak P-04', 'min' => 20, 'weight' => 0.12, 'cbm' => 0.001, 'rec' => '2024-07-15 09:00:00', 'exp' => '2029-07-15 00:00:00', 'desc' => 'Ni-MH Rechargeable High Capacity 2550mAh', 'batch' => 'BATCH-BAT-2024'],
            ['name' => 'Powerbank Anker 737 Power Core 24000mAh 140W', 'category' => 'Power & Battery', 'qty' => 12, 'price' => 2150000, 'unit' => 'Pcs', 'supp' => 'PT Schneider Electric', 'loc' => 'Rak P-05', 'min' => 4, 'weight' => 0.63, 'cbm' => 0.002, 'rec' => '2025-01-10 15:45:00', 'exp' => '2028-01-10 00:00:00', 'desc' => 'Smart Digital Display 140W Fast Output', 'warranty' => 18, 'sn' => 'ANK-737-0891'],
            ['name' => 'Stavol Matsunaga 5000VA Servo Motor Automatic Voltage Regulator', 'category' => 'Power & Battery', 'qty' => 3, 'price' => 4800000, 'unit' => 'Unit', 'supp' => 'PT Schneider Electric', 'loc' => 'Rak Heavy-03', 'min' => 1, 'weight' => 18.20, 'cbm' => 0.065, 'rec' => '2023-04-25 10:20:00', 'exp' => '2028-04-25 00:00:00', 'desc' => 'Single Phase Servo Motor Stabilizer 5000W', 'warranty' => 24],

            // Kabel & Adaptor
            ['name' => 'Kabel HDMI 2.1 4K 2 Meter', 'category' => 'Kabel & Adaptor', 'qty' => 60, 'price' => 85000, 'unit' => 'Pcs', 'supp' => 'PT Cable Solution', 'loc' => 'Zona 2 - Bin 15', 'min' => 15, 'weight' => 0.15, 'cbm' => 0.001, 'rec' => '2024-08-01 13:45:00', 'exp' => '2030-08-01 00:00:00', 'desc' => '8K 60Hz / 4K 120Hz Braided Nylon'],
            ['name' => 'Kabel UTP Cat6 Belden 305 Meter Box', 'category' => 'Kabel & Adaptor', 'qty' => 14, 'price' => 1850000, 'unit' => 'Box', 'supp' => 'PT Cable Solution', 'loc' => 'Zona 2 - Bin 16', 'min' => 4, 'weight' => 13.50, 'cbm' => 0.040, 'rec' => '2024-11-12 10:30:00', 'exp' => '2034-11-12 00:00:00', 'desc' => 'Pure Copper Unshielded Twisted Pair 23AWG'],
            ['name' => 'Adaptor Charger USB-C GaN 100W Fast Charger', 'category' => 'Kabel & Adaptor', 'qty' => 30, 'price' => 450000, 'unit' => 'Pcs', 'supp' => 'PT Cable Solution', 'loc' => 'Zona 2 - Bin 17', 'min' => 10, 'weight' => 0.22, 'cbm' => 0.001, 'rec' => '2025-02-05 14:00:00', 'exp' => '2028-02-05 00:00:00', 'desc' => 'Quad Port 3x Type-C + 1x USB-A GaN Tech', 'warranty' => 12],
            ['name' => 'Kabel Patch Cord Fiber Optic LC to LC Duplex 5m', 'category' => 'Kabel & Adaptor', 'qty' => 45, 'price' => 65000, 'unit' => 'Pcs', 'supp' => 'PT Cable Solution', 'loc' => 'Zona 2 - Bin 18', 'min' => 15, 'weight' => 0.08, 'cbm' => 0.001, 'rec' => '2024-04-18 11:15:00', 'exp' => '2032-04-18 00:00:00', 'desc' => 'Single Mode OS2 Yellow Zipcord Patch Lead'],
            ['name' => 'Stop Kontak Arde Kabel Extension 5 Lubang 5 Meter', 'category' => 'Kabel & Adaptor', 'qty' => 40, 'price' => 125000, 'unit' => 'Pcs', 'supp' => 'PT Cable Solution', 'loc' => 'Zona 2 - Bin 19', 'min' => 12, 'weight' => 0.75, 'cbm' => 0.002, 'rec' => '2025-05-20 09:50:00', 'exp' => '2030-05-20 00:00:00', 'desc' => 'Overload Switch & Child Protection Socket', 'warranty' => 12],

            // Logistik & Packaging
            ['name' => 'Stretch Film Roll 50cm x 300m Bening', 'category' => 'Logistik', 'qty' => 95, 'price' => 78000, 'unit' => 'Roll', 'supp' => 'PT Logistik Global', 'loc' => 'Rak Packing-01', 'min' => 25, 'weight' => 2.10, 'cbm' => 0.004, 'rec' => '2024-12-05 08:30:00', 'exp' => '2029-12-05 00:00:00', 'desc' => 'Industrial Grade Pallet Wrap Film 17 Micron'],
            ['name' => 'Bubble Wrap Heavy Duty Roll 1.25m x 50m', 'category' => 'Logistik', 'qty' => 28, 'price' => 145000, 'unit' => 'Roll', 'supp' => 'PT Logistik Global', 'loc' => 'Rak Packing-02', 'min' => 8, 'weight' => 4.50, 'cbm' => 0.180, 'rec' => '2025-03-12 11:00:00', 'exp' => '2030-03-12 00:00:00', 'desc' => 'Double Layer Shock Proof Packaging Material'],
            ['name' => 'Lakban Bening Opp Tape 48mm x 90 Yard (1 DUS 72 Roll)', 'category' => 'Logistik', 'qty' => 18, 'price' => 540000, 'unit' => 'Dus', 'supp' => 'PT Logistik Global', 'loc' => 'Rak Packing-03', 'min' => 5, 'weight' => 14.20, 'cbm' => 0.035, 'rec' => '2025-07-01 10:00:00', 'exp' => '2028-07-01 00:00:00', 'desc' => 'Super Sticky Adhesive Packaging Tape 45 Micron'],
            ['name' => 'Kardus Box Polos Medium 40x30x30cm (Pack 25 Pcs)', 'category' => 'Logistik', 'qty' => 50, 'price' => 185000, 'unit' => 'Pack', 'supp' => 'PT Logistik Global', 'loc' => 'Rak Packing-04', 'min' => 15, 'weight' => 8.50, 'cbm' => 0.045, 'rec' => '2025-10-18 14:30:00', 'exp' => '2032-10-18 00:00:00', 'desc' => 'Double Wall Corrugated Cardboard Shipping Box'],
            ['name' => 'Tali Strapping Band Plastik PP 15mm 7kg', 'category' => 'Logistik', 'qty' => 20, 'price' => 195000, 'unit' => 'Roll', 'supp' => 'PT Logistik Global', 'loc' => 'Rak Packing-05', 'min' => 6, 'weight' => 7.00, 'cbm' => 0.020, 'rec' => '2024-09-05 16:00:00', 'exp' => '2029-09-05 00:00:00', 'desc' => 'Heavy Duty Strapping Belt for Box Binding'],

            // Hardware Gudang & Material Handling
            ['name' => 'Hand Pallet Truck Hydraulic 3 Ton', 'category' => 'Hardware Gudang', 'qty' => 4, 'price' => 3950000, 'unit' => 'Unit', 'supp' => 'PT Hardware Utama', 'loc' => 'Area Loading Bay', 'min' => 2, 'weight' => 75.00, 'cbm' => 0.450, 'rec' => '2023-03-10 09:00:00', 'exp' => '2033-03-10 00:00:00', 'desc' => 'Heavy Duty Manual Pallet Jack 685mm Fork Width', 'warranty' => 12, 'sn' => 'PALLET-3T-004'],
            ['name' => 'Timbangan Digital Industrial Floor Scale 500kg', 'category' => 'Hardware Gudang', 'qty' => 3, 'price' => 5200000, 'unit' => 'Unit', 'supp' => 'PT Hardware Utama', 'loc' => 'Area Timbang', 'min' => 1, 'weight' => 38.00, 'cbm' => 0.250, 'rec' => '2024-02-28 10:45:00', 'exp' => '2029-02-28 00:00:00', 'desc' => 'Platform Scale 60x80cm LED Stainless Indicator', 'warranty' => 24, 'sn' => 'SCALE-500K-002'],
            ['name' => 'Barcode Scanner Handheld Wireless Bluetooth 2D', 'category' => 'Hardware Gudang', 'qty' => 16, 'price' => 850000, 'unit' => 'Unit', 'supp' => 'PT Hardware Utama', 'loc' => 'Rak Tool-01', 'min' => 5, 'weight' => 0.35, 'cbm' => 0.002, 'rec' => '2025-01-15 13:20:00', 'exp' => '2028-01-15 00:00:00', 'desc' => 'QR & PDF417 Long Range Wireless Scanner', 'warranty' => 12, 'sn' => 'SCAN-2D-0881'],
            ['name' => 'Thermal Label Printer ZEBRA ZD220T 203dpi', 'category' => 'Hardware Gudang', 'qty' => 5, 'price' => 3100000, 'unit' => 'Unit', 'supp' => 'PT Hardware Utama', 'loc' => 'Rak Tool-02', 'min' => 2, 'weight' => 2.50, 'cbm' => 0.015, 'rec' => '2024-08-20 11:00:00', 'exp' => '2028-08-20 00:00:00', 'desc' => 'Direct Thermal & Thermal Transfer Shipping Printer', 'warranty' => 12, 'sn' => 'ZEB-ZD220-05'],
            ['name' => 'Sticker Label Thermal 100x150mm (Roll 500 Pcs)', 'category' => 'Hardware Gudang', 'qty' => 80, 'price' => 42000, 'unit' => 'Roll', 'supp' => 'PT Hardware Utama', 'loc' => 'Rak Tool-03', 'min' => 20, 'weight' => 0.85, 'cbm' => 0.002, 'rec' => '2025-11-10 15:00:00', 'exp' => '2030-11-10 00:00:00', 'desc' => 'Waterproof Barcode Shipping Label Sticker'],

            // Additional items to reach exactly 50
            ['name' => 'Tangga Aluminium Teleskopik Lipat 4.4 Meter', 'category' => 'Hardware Gudang', 'qty' => 6, 'price' => 1650000, 'unit' => 'Unit', 'supp' => 'PT Hardware Utama', 'loc' => 'Rak Maintenance', 'min' => 2, 'weight' => 13.20, 'cbm' => 0.080, 'rec' => '2024-06-15 09:30:00', 'exp' => '2034-06-15 00:00:00', 'desc' => 'Multi-Purpose Folding Ladder Heavy Duty EN131', 'warranty' => 12],
            ['name' => 'Helm Safety Proyek V-Gard Full Brim ABS', 'category' => 'Hardware Gudang', 'qty' => 30, 'price' => 125000, 'unit' => 'Pcs', 'supp' => 'PT Hardware Utama', 'loc' => 'Rak Safety-01', 'min' => 10, 'weight' => 0.45, 'cbm' => 0.004, 'rec' => '2025-04-01 10:00:00', 'exp' => '2030-04-01 00:00:00', 'desc' => 'Standard Industrial Safety Helmet SNI ANSI Z89.1'],
            ['name' => 'Rompi Safety K3 Proyek High Visibility Reflective', 'category' => 'Hardware Gudang', 'qty' => 50, 'price' => 45000, 'unit' => 'Pcs', 'supp' => 'PT Hardware Utama', 'loc' => 'Rak Safety-02', 'min' => 15, 'weight' => 0.20, 'cbm' => 0.001, 'rec' => '2025-07-20 14:15:00', 'exp' => '2030-07-20 00:00:00', 'desc' => 'Polyester Mesh Vest 4 Scothlite Strips'],
            ['name' => 'Sepatu Safety Steel Toe Cap Krushers 41', 'category' => 'Hardware Gudang', 'qty' => 12, 'price' => 580000, 'unit' => 'Pasang', 'supp' => 'PT Hardware Utama', 'loc' => 'Rak Safety-03', 'min' => 4, 'weight' => 1.65, 'cbm' => 0.010, 'rec' => '2024-10-10 11:30:00', 'exp' => '2029-10-10 00:00:00', 'desc' => 'Oil & Acid Resistant Soles Steel Cap Boots', 'warranty' => 6],
        ];

        $totalItems = count($sampleItems);
        $whCount = count($warehouseIds);

        $payloads = [];
        $seqByCategory = [];

        foreach ($sampleItems as $idx => $item) {
            $whId = $warehouseIds[$idx % $whCount];
            $whCode = $warehouseCodes[$whId] ?? 'WH01';
            $category = $item['category'];

            $seqByCategory[$category] = ($seqByCategory[$category] ?? 0) + 1;
            $sku = SkuGenerator::generate($category, $whCode, $seqByCategory[$category]);

            $fields = [
                'id_warehouse' => $whId,
                'name' => $item['name'],
                'sku' => $sku,
                'category' => $category,
                'quantity' => $item['qty'],
                'price' => $item['price'],
                'unit' => $item['unit'],
                'supplier' => $item['supp'],
                'location' => $item['loc'],
                'min_stock' => $item['min'],
                'weight_kg' => $item['weight'],
                'volume_cbm' => $item['cbm'],
                'received_at' => $item['rec'],
                'expiry_date' => $item['exp'],
                'description' => $item['desc'],
            ];

            if (! empty($item['batch'])) {
                $fields['batch_number'] = $item['batch'];
            }

            if (! empty($item['warranty'])) {
                $fields['warranty_months'] = $item['warranty'];
            }

            if (! empty($item['sn'])) {
                $fields['serial_number'] = $item['sn'];
            }

            $payloads[] = new EntryPayload(
                tenantId: $tenantId,
                modelId: $barangModelId,
                fields: $fields
            );
        }

        $res = $stardust->bulkWrite($payloads);
        $this->command?->info("✓ InventorySeeder: {$res->entriesCommitted} dari {$totalItems} barang inventaris lengkap berhasil ditambahkan ke {$whCount} gudang!");
    }
}
