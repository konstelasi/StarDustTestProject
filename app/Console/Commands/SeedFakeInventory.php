<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use StarDust\StarDust;
use StarDust\Write\EntryPayload;
use App\Support\SkuGenerator;
use Faker\Factory as FakerFactory;

class SeedFakeInventory extends Command
{
    protected $signature = 'inventory:seed-fake
        {--warehouses=3 : How many extra fake warehouses (gudang) to create}
        {--items=15 : How many fake items (barang) to create per warehouse}
        {--locale=id_ID : Faker locale}
        {--fresh : Delete existing gudang & barang entries first (testing mode reset)}';

    protected $description = 'Seed dummy warehouses & items via Faker for testing mode (SKU uses its own algorithm, not Faker).';

    private const CATEGORIES = [
        'Elektronik', 'Aksesori', 'Peralatan Kantor', 'Bahan Konsumsi',
        'Perawatan', 'Power & Battery', 'Logistik', 'Hardware Gudang',
        'Kabel & Adaptor',
    ];

    public function handle(StarDust $stardust): int
    {
        $tenantId = (int) config('stardust.tenant_id', 1);
        $warehouseModelName = config('stardust.warehouse_model_name', 'gudang');
        $itemModelName = config('stardust.item_model_name', 'barang');

        $gudangModelId = $this->resolveModelId($stardust, $tenantId, $warehouseModelName);
        $barangModelId = $this->resolveModelId($stardust, $tenantId, $itemModelName);

        if (!$gudangModelId || !$barangModelId) {
            $this->error("Model '{$warehouseModelName}' / '{$itemModelName}' belum terdaftar. Jalankan `php artisan inventory:setup` dulu.");
            return Command::FAILURE;
        }

        if ($this->option('fresh')) {
            if (!$this->confirm('Ini akan MENGHAPUS semua data gudang & barang yang ada saat ini. Lanjutkan?', false)) {
                $this->info('Dibatalkan.');
                return Command::SUCCESS;
            }

            $this->wipeModel($tenantId, $gudangModelId);
            $this->wipeModel($tenantId, $barangModelId);
            $this->info('✓ Data lama dihapus.');
        }

        $faker = FakerFactory::create($this->option('locale'));

        $warehouseCount = max(1, (int) $this->option('warehouses'));
        $itemsPerWarehouse = max(1, (int) $this->option('items'));

        $this->info("Membuat {$warehouseCount} gudang dummy via Faker...");
        $warehouses = [];

        for ($i = 0; $i < $warehouseCount; $i++) {
            $city = $faker->city();
            $code = 'WH-' . strtoupper($faker->lexify('???')) . '-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);

            $fields = [
                'name' => "Gudang {$faker->companySuffix()} {$city}",
                'code' => $code,
                'location' => $faker->address(),
                'manager' => $faker->name(),
            ];

            $result = $stardust->write(new EntryPayload(
                tenantId: $tenantId,
                modelId: $gudangModelId,
                fields: $fields,
            ));

            $warehouses[] = ['id' => $result->entryId, 'code' => $code];
            $this->line("  + {$fields['name']} (Entry ID: {$result->entryId})");
        }

        $this->info("Membuat {$itemsPerWarehouse} barang dummy per gudang via Faker (SKU pakai algoritma sendiri)...");

        $sequenceByCategory = [];
        $totalCreated = 0;

        foreach ($warehouses as $warehouse) {
            $payloads = [];

            for ($j = 0; $j < $itemsPerWarehouse; $j++) {
                $category = $faker->randomElement(self::CATEGORIES);
                $sequenceByCategory[$category] = ($sequenceByCategory[$category] ?? 0) + 1;

                $sku = SkuGenerator::generate($category, $warehouse['code'], $sequenceByCategory[$category]);

                $hasExpiry = $faker->boolean(30);
                $quantity = $faker->numberBetween(0, 200);
                $minStock = $faker->numberBetween(2, 20);

                $fields = [
                    'id_warehouse' => $warehouse['id'],
                    'name' => ucfirst($faker->words(3, true)),
                    'sku' => $sku,
                    'category' => $category,
                    'quantity' => $quantity,
                    'price' => $faker->numberBetween(15, 25000) * 1000,
                    'unit' => $faker->randomElement(['Pcs', 'Unit', 'Box', 'Botol', 'Bungkus']),
                    'supplier' => $faker->company(),
                    'location' => 'Rak ' . strtoupper($faker->lexify('?')) . '-' . $faker->numberBetween(1, 20),
                    'min_stock' => $minStock,
                    'description' => $faker->sentence(8),
                ];

                if ($faker->boolean(20)) {
                    $fields['serial_number'] = strtoupper($faker->bothify('SN-####-???'));
                }

                if ($hasExpiry) {
                    $fields['batch_number'] = 'BATCH-' . $faker->numerify('####-##');
                    $fields['expiry_date'] = $faker->dateTimeBetween('now', '+2 years')->format('Y-m-d H:i:s');
                } else {
                    $fields['warranty_months'] = $faker->randomElement([6, 12, 24, 36]);
                }

                $payloads[] = new EntryPayload(
                    tenantId: $tenantId,
                    modelId: $barangModelId,
                    fields: $fields,
                );
            }

            $result = $stardust->bulkWrite($payloads);
            $totalCreated += $result->entriesCommitted;
            $this->line("  + {$result->entriesCommitted} barang dibuat untuk gudang (id={$warehouse['id']}, code={$warehouse['code']})");
        }

        $this->info("✓ Selesai. Total {$totalCreated} barang dummy dibuat di {$warehouseCount} gudang baru.");
        $this->line('  (Mode testing — data ini aman dihapus lagi lewat `php artisan inventory:seed-fake --fresh`)');

        return Command::SUCCESS;
    }

    private function resolveModelId(StarDust $stardust, int $tenantId, string $modelName): ?int
    {
        $model = collect($stardust->listModels($tenantId))->firstWhere('name', $modelName);
        return $model?->modelId;
    }

    private function wipeModel(int $tenantId, int $modelId): void
    {
        $ids = DB::table('entry_data')
            ->where('tenant_id', $tenantId)
            ->where('model_id', $modelId)
            ->pluck('id');

        foreach ($ids as $id) {
            app(StarDust::class)->deleteEntry($tenantId, $id);
        }
    }
}