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
    protected $signature = 'inventory:setup {--seed : Seed sample inventory data}';
    protected $description = 'Bootstrap StarDust engine, register multi-tenant warehouse models, and reserve indexed slots.';

    public function handle(StarDust $stardust): int
    {
        $this->info('1. Bootstrapping StarDust database engine schema...');
        $stardust->bootstrap();
        $this->info('✓ StarDust schema bootstrapped successfully.');

        $modelName = config('stardust.model_name', 'product');

        $this->info("2. Registering model '{$modelName}' & schemaless field definitions...");
        
        $fields = [
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
            new FieldDefinition('expiry_date',     'string', isFilterable: false),
            new FieldDefinition('warranty_months', 'int',    isFilterable: false),
            new FieldDefinition('serial_number',   'string', isFilterable: false),
        ];

        // Provision for all 3 warehouse tenants (Tenant 1, 2, 3)
        $warehouses = config('stardust.warehouses', []);
        $pdo = $stardust->pdo();

        $strSlots = ['i_str_01', 'i_str_02', 'i_str_03', 'i_str_04', 'i_str_05'];
        $intSlots = ['i_int_01', 'i_int_02', 'i_int_03'];

        (new PageProvisioner($pdo, $stardust->config()->clock, $stardust->logger()))
            ->provision(filterableSlots: array_merge($strSlots, $intSlots));

        $reserver = new SlotReserver($pdo, $stardust->config()->clock, $stardust->logger());

        foreach ($warehouses as $tenantId => $wh) {
            $modelSummary = $stardust->schemaBuilder()->createModel($tenantId, $modelName, $fields);
            $this->info("✓ Tenant {$tenantId} ({$wh['name']}): Model '{$modelName}' active (ID: {$modelSummary->modelId})");

            foreach ($fields as $field) {
                if ($field->isFilterable) {
                    try {
                        $fieldId = $modelSummary->fieldId($field->name);
                        $alreadyAssigned = DB::table('stardust_slot_assignments')
                            ->where('field_id', $fieldId)
                            ->whereIn('status', ['assigned', 'ready', 'backfilling'])
                            ->exists();

                        if (!$alreadyAssigned) {
                            $reserver->reserve($fieldId);
                            $this->line("  - Tenant {$tenantId}: Reserved slot for '{$field->name}' (ID: {$fieldId})");
                        }
                    } catch (\Throwable $e) {
                        // ignore if reserved
                    }
                }
            }
        }

        $this->info('✓ Slot reservations complete for all warehouses.');

        if ($this->option('seed') || $this->confirm('Do you want to seed multi-warehouse sample data?', true)) {
            $this->seedSampleData($stardust);
        }

        $this->info('🎉 Multi-Warehouse StarDust setup completed successfully!');
        return Command::SUCCESS;
    }

    private function seedSampleData(StarDust $stardust): void
    {
        $this->info('Seeding sample warehouse items via StarDust engine...');

        // Tenant 1: Gudang Utama Jakarta
        $itemsWh1 = [
            [
                'name' => 'Laptop Asus ROG Strix G15',
                'sku' => 'LAP-ROG-G15',
                'category' => 'Elektronik',
                'quantity' => 12,
                'price' => 18500000,
                'unit' => 'Unit',
                'supplier' => 'PT Asus Indonesia',
                'location' => 'Sektor A - Rak 01',
                'min_stock' => 5,
                'description' => 'AMD Ryzen 7, RAM 16GB, RTX 3060 6GB',
                'warranty_months' => 24,
                'serial_number' => 'ROG-99281-JKT',
            ],
            [
                'name' => 'Monitor Dell UltraSharp 27 Inch 4K',
                'sku' => 'MON-DELL-U27',
                'category' => 'Elektronik',
                'quantity' => 8,
                'price' => 7200000,
                'unit' => 'Unit',
                'supplier' => 'PT Dell Technologies',
                'location' => 'Sektor A - Rak 02',
                'min_stock' => 3,
                'description' => 'IPS 4K UHD, USB-C Hub, Color Calibrated',
                'warranty_months' => 36,
                'serial_number' => 'DELL-4412-JKT',
            ],
            [
                'name' => 'Keyboard Mechanical Keychron K2',
                'sku' => 'KEY-KCHR-K2',
                'category' => 'Aksesori',
                'quantity' => 25,
                'price' => 1450000,
                'unit' => 'Pcs',
                'supplier' => 'CV Gadget Mania',
                'location' => 'Sektor B - Rak 01',
                'min_stock' => 10,
                'description' => 'Wireless RGB, Gateron Brown Switch',
                'warranty_months' => 12,
            ],
            [
                'name' => 'Mouse Logitech MX Master 3S',
                'sku' => 'MOU-LOGI-3S',
                'category' => 'Aksesori',
                'quantity' => 3, // low stock!
                'price' => 1650000,
                'unit' => 'Pcs',
                'supplier' => 'CV Gadget Mania',
                'location' => 'Sektor B - Rak 02',
                'min_stock' => 5,
                'description' => 'Ergonomic 8K DPI Quiet Clicks',
                'warranty_months' => 12,
            ],
        ];

        // Tenant 2: Gudang Cabang Surabaya
        $itemsWh2 = [
            [
                'name' => 'Printer HP LaserJet Pro M404dn',
                'sku' => 'PRN-HP-M404',
                'category' => 'Peralatan Kantor',
                'quantity' => 2, // low stock!
                'price' => 4300000,
                'unit' => 'Unit',
                'supplier' => 'PT Anugerah Printer',
                'location' => 'Zona 1 - Blok C',
                'min_stock' => 4,
                'description' => 'Monochrome Laser Printer Auto Duplex',
                'warranty_months' => 12,
            ],
            [
                'name' => 'Kabel HDMI 2.1 4K 2 Meter',
                'sku' => 'KBL-HDMI-02M',
                'category' => 'Kabel & Adaptor',
                'quantity' => 60,
                'price' => 85000,
                'unit' => 'Pcs',
                'supplier' => 'PT Cable Solution',
                'location' => 'Zona 2 - Bin 15',
                'min_stock' => 15,
                'description' => '8K 60Hz / 4K 120Hz Braided Nylon',
            ],
            [
                'name' => 'Kopi Arabika Premium 1KG',
                'sku' => 'KOP-ARB-1KG',
                'category' => 'Bahan Konsumsi',
                'quantity' => 40,
                'price' => 175000,
                'unit' => 'Bungkus',
                'supplier' => 'CV Kopi Nusantara',
                'location' => 'Zona 3 - Cold Room',
                'min_stock' => 10,
                'batch_number' => 'BATCH-2026-08',
                'expiry_date' => '2027-08-30',
            ],
        ];

        // Tenant 3: Gudang Logistik Bandung
        $itemsWh3 = [
            [
                'name' => 'Cairan Pembersih Layar Screen Clean 500ml',
                'sku' => 'CLN-SCR-500ML',
                'category' => 'Perawatan',
                'quantity' => 100,
                'price' => 45000,
                'unit' => 'Botol',
                'supplier' => 'PT Chemical Care',
                'location' => 'Rak D-09',
                'min_stock' => 20,
                'batch_number' => 'BATCH-CLN-102',
                'expiry_date' => '2028-12-15',
            ],
            [
                'name' => 'UPS APC Back-UPS 1100VA 660W',
                'sku' => 'UPS-APC-1100',
                'category' => 'Power & Battery',
                'quantity' => 5,
                'price' => 2100000,
                'unit' => 'Unit',
                'supplier' => 'PT Schneider Electric',
                'location' => 'Rak Heavy-01',
                'min_stock' => 2,
                'warranty_months' => 24,
            ],
        ];

        $tenantMap = [
            1 => $itemsWh1,
            2 => $itemsWh2,
            3 => $itemsWh3,
        ];

        $modelName = config('stardust.model_name', 'product');

        foreach ($tenantMap as $tenantId => $items) {
            $models = $stardust->listModels($tenantId);
            $productModel = collect($models)->firstWhere('name', $modelName);
            if (!$productModel) continue;

            $existingCount = DB::table('entry_data')
                ->where('tenant_id', $tenantId)
                ->where('model_id', $productModel->modelId)
                ->count();

            if ($existingCount === 0) {
                foreach ($items as $item) {
                    $payload = new EntryPayload(
                        tenantId: $tenantId,
                        modelId: $productModel->modelId,
                        fields: $item
                    );
                    $stardust->write($payload);
                }
                $this->line("  + Seeded " . count($items) . " items for Tenant {$tenantId}");
            }
        }

        $this->info('✓ Seeding complete across all warehouses.');
    }
}
