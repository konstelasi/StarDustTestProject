<?php

namespace App\Support;

use App\Models\CustomFieldDefinition;
use Illuminate\Support\Facades\DB;
use StarDust\Schema\FieldDefinition;
use StarDust\Slot\SlotReserver;
use StarDust\StarDust;

class TenantProvisioner
{
    public function __construct(private readonly StarDust $stardust) {}

    /**
     * Daftarkan model gudang & barang untuk satu tenant (idempotent).
     *
     * @return array<string,int> [nama_model => modelId]
     */
    public function provision(int $tenantId): array
    {
        $reserver = new SlotReserver(
            $this->stardust->pdo(),
            $this->stardust->config()->clock,
            $this->stardust->logger()
        );

        $existing = collect($this->stardust->listModels($tenantId));
        $ids = [];

        $definitions = [
            config('stardust.warehouse_model_name', 'gudang') => $this->warehouseFields(),
            config('stardust.item_model_name', 'barang') => $this->itemFields(),
        ];

        foreach ($definitions as $name => $fields) {
            $found = $existing->firstWhere('name', $name);

            if ($found) {
                $ids[$name] = $found->modelId;

                continue;
            }

            $summary = $this->stardust->schemaBuilder()->createModel($tenantId, $name, $fields);
            $this->reserveFilterableSlots($reserver, $summary, $fields);
            $ids[$name] = $summary->modelId;
        }

        return $ids;
    }

    private function warehouseFields(): array
    {
        return [
            new FieldDefinition('name', 'string', isFilterable: true),
            new FieldDefinition('code', 'string', isFilterable: true),
            new FieldDefinition('location', 'string', isFilterable: false),
            new FieldDefinition('manager', 'string', isFilterable: false),
        ];
    }

    private function itemFields(): array
    {
        $fields = [
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

        try {
            foreach (CustomFieldDefinition::all() as $cdef) {
                $declaredType = match (strtolower(trim($cdef->type))) {
                    'number', 'numeric', 'float', 'double' => 'numeric',
                    'integer', 'int', 'boolean', 'bool' => 'int',
                    'date', 'datetime' => 'datetime',
                    default => 'string',
                };
                $fields[] = new FieldDefinition($cdef->name, $declaredType, isFilterable: true);
            }
        } catch (\Throwable $e) {
            // tabel custom field belum ada
        }

        return $fields;
    }

    private function reserveFilterableSlots(SlotReserver $reserver, $modelSummary, array $fields): void
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
                }
            } catch (\Throwable $e) {
                // sama seperti perilaku command lama
            }
        }
    }
}
