<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use StarDust\StarDust;
use StarDust\Schema\FieldDefinition;
use StarDust\Page\PageProvisioner;
use StarDust\Slot\SlotReserver;
use StarDust\Write\EntryPayload;
use Illuminate\Support\Facades\DB;

class SetupInventoryModel extends Command
{
    protected $signature = 'inventory:setup {--fresh : Wipe all StarDust tables before setup} {--seed : Seed sample inventory data}';
    protected $description = 'Bootstrap StarDust engine (single-tenant) and register the gudang & barang models.';

    public function handle(StarDust $stardust): int
    {
        $tenantId = (int) config('stardust.tenant_id', 1);
        $warehouseModelName = config('stardust.warehouse_model_name', 'gudang');
        $itemModelName = config('stardust.item_model_name', 'barang');

        if ($this->option('fresh')) {
            $this->warn('Wiping all StarDust database tables...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $tables = DB::select('SHOW TABLES');
            $dbName = DB::getDatabaseName();
            $columnName = "Tables_in_" . $dbName;
            foreach ($tables as $table) {
                $tableName = $table->$columnName ?? reset($table);
                if (str_starts_with($tableName, 'stardust_') || str_starts_with($tableName, 'entry_')) {
                    DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                }
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->info('✓ All StarDust tables dropped.');
        }

        $this->info('1. Bootstrapping StarDust database engine schema...');
        try {
            $stardust->bootstrap();
            $this->info('✓ StarDust schema bootstrapped successfully.');
        } catch (\Throwable $e) {
            $this->line('  (schema already bootstrapped, continuing)');
        }


        $gudangFields = [
            new FieldDefinition('name',     'string', isFilterable: true),
            new FieldDefinition('code',     'string', isFilterable: true),
            new FieldDefinition('location', 'string', isFilterable: false),
            new FieldDefinition('manager',  'string', isFilterable: false),
        ];

        $barangFields = [
            new FieldDefinition('id_warehouse',    'int',      isFilterable: true), 
            new FieldDefinition('name',            'string', isFilterable: true),
            new FieldDefinition('sku',             'string', isFilterable: true),
            new FieldDefinition('category',        'string', isFilterable: true),
            new FieldDefinition('quantity',        'int',    isFilterable: true),
            new FieldDefinition('price',           'int',    isFilterable: true),
            new FieldDefinition('unit',            'string', isFilterable: false),
            new FieldDefinition('supplier',        'string', isFilterable: true),
            new FieldDefinition('location',        'string', isFilterable: true),
            new FieldDefinition('min_stock',       'int',    isFilterable: true),
            new FieldDefinition('description',     'string', isFilterable: false),
            new FieldDefinition('batch_number',    'string', isFilterable: false),
            new FieldDefinition('expiry_date',     'datetime', isFilterable: false),
            new FieldDefinition('warranty_months', 'int',    isFilterable: false),
            new FieldDefinition('serial_number',   'string', isFilterable: false),
        ];

        $strSlots = ['i_str_01', 'i_str_02', 'i_str_03', 'i_str_04', 'i_str_05', 'i_str_06', 'i_str_07', 'i_str_08'];
        $intSlots = ['i_int_01', 'i_int_02', 'i_int_03', 'i_int_04', 'i_int_05'];
        $dtSlots  = ['i_dt_01', 'i_dt_02'];

        $pdo = $stardust->pdo();

        (new PageProvisioner($pdo, $stardust->config()->clock, $stardust->logger()))
            ->provision(filterableSlots: array_merge($strSlots, $intSlots));

        $reserver = new SlotReserver($pdo, $stardust->config()->clock, $stardust->logger());

        $this->info("2. Registering model '{$warehouseModelName}' (tenant {$tenantId})...");
        $gudangSummary = $stardust->schemaBuilder()->createModel($tenantId, $warehouseModelName, $gudangFields);
        $this->info("✓ Model '{$warehouseModelName}' active (ID: {$gudangSummary->modelId})");
        $this->reserveFilterableSlots($reserver, $gudangSummary, $gudangFields, $warehouseModelName);

        $this->info("3. Registering model '{$itemModelName}' (tenant {$tenantId})...");
        $barangSummary = $stardust->schemaBuilder()->createModel($tenantId, $itemModelName, $barangFields);
        $this->info("✓ Model '{$itemModelName}' active (ID: {$barangSummary->modelId})");
        $this->reserveFilterableSlots($reserver, $barangSummary, $barangFields, $itemModelName);

        $this->info('✓ Slot reservations complete.');

        if ($this->option('seed') || $this->confirm('Do you want to seed sample warehouse & inventory data?', true)) {
            $this->seedSampleData($stardust, $tenantId, $gudangSummary->modelId, $barangSummary->modelId);
        }

        $this->info('🎉 Single-Tenant StarDust setup completed successfully!');
        return Command::SUCCESS;
    }

     /**
     * @param FieldDefinition[] $fields
     */
    private function reserveFilterableSlots(SlotReserver $reserver, $modelSummary, array $fields, string $modelLabel): void
    {
        foreach ($fields as $field) {
            if (!$field->isFilterable) {
                continue;
            }

            try {
                $fieldId = $modelSummary->fieldId($field->name);
                $alreadyAssigned = DB::table('stardust_slot_assignments')
                    ->where('field_id', $fieldId)
                    ->whereIn('status', ['assigned', 'ready', 'backfilling'])
                    ->exists();

                if (!$alreadyAssigned) {
                    $reserver->reserve($fieldId);
                    $this->line("  - {$modelLabel}: Reserved slot for '{$field->name}' (ID: {$fieldId})");
                }
            } catch (\Throwable $e) {
                // Already reserved / no capacity yet — safe to ignore here,
                // the Watcher/Reconciler daemons pick up the rest later.
            }
        }
    }

    private function seedSampleData(StarDust $stardust, int $tenantId, int $gudangModelId, int $barangModelId): void
    {
        $existingWarehouses = DB::table('entry_data')
            ->where('tenant_id', $tenantId)
            ->where('model_id', $gudangModelId)
            ->count();

        // --- 1. Seed warehouses (gudang) first, and remember their real
        //        entry IDs — those IDs are what `id_warehouse` points to. ---
        if ($existingWarehouses === 0) {
            $this->info('Seeding warehouses (gudang)...');
            $seedWarehouses = config('stardust.seed_warehouses', []);

            $warehouseIds = [];
            foreach ($seedWarehouses as $wh) {
                $result = $stardust->write(new EntryPayload(
                    tenantId: $tenantId,
                    modelId: $gudangModelId,
                    fields: $wh,
                ));
                $warehouseIds[] = $result->entryId;
                $this->line("  + Gudang '{$wh['name']}' created (Entry ID: {$result->entryId})");
            }
        } else {
            $this->line('  (warehouses already seeded, reusing existing entries)');
            $rows = DB::table('entry_data')
                ->where('tenant_id', $tenantId)
                ->where('model_id', $gudangModelId)
                ->orderBy('id')
                ->pluck('id');
            $warehouseIds = $rows->all();
        }

        if (count($warehouseIds) < 3) {
            $this->warn('Fewer than 3 warehouses found — skipping item seeding.');
            return;
        }

        [$whJakarta, $whSurabaya, $whBandung] = array_slice($warehouseIds, 0, 3);

        $existingItems = DB::table('entry_data')
            ->where('tenant_id', $tenantId)
            ->where('model_id', $barangModelId)
            ->count();

        if ($existingItems > 0) {
            $this->line('  (items already seeded, skipping)');
            return;
        }

        $this->info('Seeding sample items (barang) via StarDust engine...');

        // Tenant 1: Gudang Utama Jakarta
        $items = [
            // Gudang Utama Jakarta
            ['id_warehouse' => $whJakarta, 'name' => 'Laptop Asus ROG Strix G15', 'sku' => 'LAP-ROG-G15', 'category' => 'Elektronik', 'quantity' => 12, 'price' => 18500000, 'unit' => 'Unit', 'supplier' => 'PT Asus Indonesia', 'location' => 'Sektor A - Rak 01', 'min_stock' => 5, 'description' => 'AMD Ryzen 7, RAM 16GB, RTX 3060 6GB', 'warranty_months' => 24, 'serial_number' => 'ROG-99281-JKT'],
            ['id_warehouse' => $whJakarta, 'name' => 'Monitor Dell UltraSharp 27 Inch 4K', 'sku' => 'MON-DELL-U27', 'category' => 'Elektronik', 'quantity' => 8, 'price' => 7200000, 'unit' => 'Unit', 'supplier' => 'PT Dell Technologies', 'location' => 'Sektor A - Rak 02', 'min_stock' => 3, 'description' => 'IPS 4K UHD, USB-C Hub, Color Calibrated', 'warranty_months' => 36, 'serial_number' => 'DELL-4412-JKT'],
            ['id_warehouse' => $whJakarta, 'name' => 'Keyboard Mechanical Keychron K2', 'sku' => 'KEY-KCHR-K2', 'category' => 'Aksesori', 'quantity' => 25, 'price' => 1450000, 'unit' => 'Pcs', 'supplier' => 'CV Gadget Mania', 'location' => 'Sektor B - Rak 01', 'min_stock' => 10, 'description' => 'Wireless RGB, Gateron Brown Switch', 'warranty_months' => 12],
            ['id_warehouse' => $whJakarta, 'name' => 'Mouse Logitech MX Master 3S', 'sku' => 'MOU-LOGI-3S', 'category' => 'Aksesori', 'quantity' => 3, 'price' => 1650000, 'unit' => 'Pcs', 'supplier' => 'CV Gadget Mania', 'location' => 'Sektor B - Rak 02', 'min_stock' => 5, 'description' => 'Ergonomic 8K DPI Quiet Clicks', 'warranty_months' => 12],

            // Gudang Cabang Surabaya
            ['id_warehouse' => $whSurabaya, 'name' => 'Printer HP LaserJet Pro M404dn', 'sku' => 'PRN-HP-M404', 'category' => 'Peralatan Kantor', 'quantity' => 2, 'price' => 4300000, 'unit' => 'Unit', 'supplier' => 'PT Anugerah Printer', 'location' => 'Zona 1 - Blok C', 'min_stock' => 4, 'description' => 'Monochrome Laser Printer Auto Duplex', 'warranty_months' => 12],
            ['id_warehouse' => $whSurabaya, 'name' => 'Kabel HDMI 2.1 4K 2 Meter', 'sku' => 'KBL-HDMI-02M', 'category' => 'Kabel & Adaptor', 'quantity' => 60, 'price' => 85000, 'unit' => 'Pcs', 'supplier' => 'PT Cable Solution', 'location' => 'Zona 2 - Bin 15', 'min_stock' => 15, 'description' => '8K 60Hz / 4K 120Hz Braided Nylon'],
            ['id_warehouse' => $whSurabaya, 'name' => 'Kopi Arabika Premium 1KG', 'sku' => 'KOP-ARB-1KG', 'category' => 'Bahan Konsumsi', 'quantity' => 40, 'price' => 175000, 'unit' => 'Bungkus', 'supplier' => 'CV Kopi Nusantara', 'location' => 'Zona 3 - Cold Room', 'min_stock' => 10, 'batch_number' => 'BATCH-2026-08', 'expiry_date' => '2027-08-30 00:00:00'],

            // Gudang Logistik Bandung
            ['id_warehouse' => $whBandung, 'name' => 'Cairan Pembersih Layar Screen Clean 500ml', 'sku' => 'CLN-SCR-500ML', 'category' => 'Perawatan', 'quantity' => 100, 'price' => 45000, 'unit' => 'Botol', 'supplier' => 'PT Chemical Care', 'location' => 'Rak D-09', 'min_stock' => 20, 'batch_number' => 'BATCH-CLN-102', 'expiry_date' => '2028-12-15 00:00:00'],
            ['id_warehouse' => $whBandung, 'name' => 'UPS APC Back-UPS 1100VA 660W', 'sku' => 'UPS-APC-1100', 'category' => 'Power & Battery', 'quantity' => 5, 'price' => 2100000, 'unit' => 'Unit', 'supplier' => 'PT Schneider Electric', 'location' => 'Rak Heavy-01', 'min_stock' => 2, 'warranty_months' => 24],
        ];

        $countByWarehouse = [];
        foreach ($items as $item) {
            $stardust->write(new EntryPayload(
                tenantId: $tenantId,
                modelId: $barangModelId,
                fields: $item,
            ));
            $countByWarehouse[$item['id_warehouse']] = ($countByWarehouse[$item['id_warehouse']] ?? 0) + 1;
        }

        foreach ($countByWarehouse as $whId => $count) {
            $this->line("  + Seeded {$count} items for warehouse (id_warehouse={$whId})");
        }

        $this->info('✓ Seeding complete.');
    }
}
