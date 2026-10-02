<?php

namespace App\Console\Commands;

use Database\Seeders\InventorySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use StarDust\Page\PageProvisioner;
use StarDust\Schema\FieldDefinition;
use StarDust\Slot\SlotReserver;
use StarDust\StarDust;
use Stardust\Support\ServerEngineDetector;

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
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                foreach ($tables as $table) {
                    $tableName = $table->name ?? '';
                    if (str_starts_with($tableName, 'stardust_') || str_starts_with($tableName, 'entry_')) {
                        DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                    }
                }
                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                $tables = DB::select('SHOW TABLES');
                $dbName = DB::getDatabaseName();
                $columnName = 'Tables_in_'.$dbName;
                foreach ($tables as $table) {
                    $tableName = $table->$columnName ?? reset($table);
                    if (str_starts_with($tableName, 'stardust_') || str_starts_with($tableName, 'entry_')) {
                        DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                    }
                }
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
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
            new FieldDefinition('name', 'string', isFilterable: true),
            new FieldDefinition('code', 'string', isFilterable: true),
            new FieldDefinition('location', 'string', isFilterable: false),
            new FieldDefinition('manager', 'string', isFilterable: false),
        ];

        $barangFields = [
            new FieldDefinition('id_warehouse', 'int', isFilterable: true),
            new FieldDefinition('name', 'string', isFilterable: true),
            new FieldDefinition('sku', 'string', isFilterable: true),
            new FieldDefinition('category', 'string', isFilterable: true),
            new FieldDefinition('quantity', 'int', isFilterable: true),
            new FieldDefinition('price', 'int', isFilterable: true),
            new FieldDefinition('unit', 'string', isFilterable: false),
            new FieldDefinition('supplier', 'string', isFilterable: true),
            new FieldDefinition('location', 'string', isFilterable: true),
            new FieldDefinition('min_stock', 'int', isFilterable: true),
            new FieldDefinition('weight_kg', 'numeric', isFilterable: true),
            new FieldDefinition('volume_cbm', 'numeric', isFilterable: true),
            new FieldDefinition('received_at', 'datetime', isFilterable: true),
            new FieldDefinition('expiry_date', 'datetime', isFilterable: true),
            new FieldDefinition('description', 'string', isFilterable: false),
            new FieldDefinition('batch_number', 'string', isFilterable: false),
            new FieldDefinition('warranty_months', 'int', isFilterable: false),
            new FieldDefinition('serial_number', 'string', isFilterable: false),
        ];

        $strSlots = ['i_str_01', 'i_str_02', 'i_str_03', 'i_str_04', 'i_str_05', 'i_str_06', 'i_str_07', 'i_str_08'];
        $intSlots = ['i_int_01', 'i_int_02', 'i_int_03', 'i_int_04'];
        $numSlots = ['i_num_01', 'i_num_02'];
        $dtSlots = ['i_dt_01', 'i_dt_02'];

        $pdo = $stardust->pdo();
        $engine = ServerEngineDetector::detect($pdo);

        $pageCount = (int) DB::table('stardust_pages')->count();
        if ($pageCount === 0) {
            (new PageProvisioner($pdo, $stardust->config()->clock, $stardust->logger(), $engine))
                ->provision(filterableSlots: array_merge($strSlots, $intSlots, $numSlots, $dtSlots));
        }

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
     * @param  FieldDefinition[]  $fields
     */
    private function reserveFilterableSlots(SlotReserver $reserver, $modelSummary, array $fields, string $modelLabel): void
    {
        foreach ($fields as $field) {
            if (! $field->isFilterable) {
                continue;
            }

            try {
                $fieldId = $modelSummary->fieldId($field->name);
                $alreadyAssigned = DB::table('stardust_slot_assignments')
                    ->where('field_id', $fieldId)
                    ->whereIn('status', ['assigned', 'ready', 'backfilling'])
                    ->exists();

                if (! $alreadyAssigned) {
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
        $existingItems = DB::table('entry_data')
            ->where('tenant_id', $tenantId)
            ->where('model_id', $barangModelId)
            ->whereNull('deleted_at')
            ->count();

        if ($existingItems > 0) {
            $this->line('  (items already seeded, skipping)');

            return;
        }

        $this->info('Seeding 50 complete sample items (barang) via InventorySeeder...');
        $seeder = new InventorySeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $this->info('✓ Seeding complete.');
    }
}
