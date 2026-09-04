<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use StarDust\StarDust;
use StarDust\Read\EntryQuery;
use StarDust\Filter\Ast\LeafNode;
use StarDust\Filter\Ast\AndNode;
use StarDust\Write\EntryPayload;
use StarDust\Write\BulkIngestOptions;
use App\Support\SkuGenerator;
use Faker\Factory as FakerFactory;

class InventoryController extends Controller
{
    public function __construct(private readonly StarDust $stardust)
    {
    }

    private function tenantId(): int
    {
        return (int) config('stardust.tenant_id', 1);
    }

    private function modelId(int $tenantId, string $modelName): int
    {
        $models = $this->stardust->listModels($tenantId);
        $model = collect($models)->firstWhere('name', $modelName);

        return $model ? $model->modelId : 1;
    }

    private function loadWarehouses(int $tenantId, int $gudangModelId)
    {
        $page = $this->stardust->read(new EntryQuery(
            tenantId: $tenantId,
            modelId: $gudangModelId,
            pageSize: 200,
        ));

        return collect($page->rows)->map(function ($row) {
            return (object) array_merge(['id' => $row->id], $row->fields);
        });
    }

    private function resolveContext(Request $request): array
    {
        $tenantId = $this->tenantId();
        $gudangModelId = $this->modelId($tenantId, config('stardust.warehouse_model_name', 'gudang'));
        $barangModelId = $this->modelId($tenantId, config('stardust.item_model_name', 'barang'));

        $warehouses = $this->loadWarehouses($tenantId, $gudangModelId);

        $requestedId = $request->input('warehouse', session('active_warehouse'));
        $warehouseId = $requestedId !== null ? (int) $requestedId : null;

        $activeWarehouse = $warehouseId !== null ? $warehouses->firstWhere('id', $warehouseId) : null;

        if (!$activeWarehouse) {
            $activeWarehouse = $warehouses->first();
            $warehouseId = $activeWarehouse->id ?? null;
        }

        session(['active_warehouse' => $warehouseId]);

        return [$tenantId, $barangModelId, $warehouseId, $activeWarehouse, $warehouses];
    }

    /**
     * Display listing of inventory items from StarDust engine for active warehouse.
     */
    public function index(Request $request)
    {
        [$tenantId, $modelId, $warehouseId, $activeWarehouse, $warehouses] = $this->resolveContext($request);

        $search = $request->input('search');
        $category = $request->input('category');
        $lowStock = $request->boolean('low_stock');
        $cursor = $request->input('cursor');

        $filterNodes = [];

        if ($warehouseId !== null) {
            $filterNodes[] = LeafNode::local('id_warehouse', 'eq', $warehouseId);
        }

        if ($category) {
            $filterNodes[] = LeafNode::local('category', 'eq', $category);
        }

        if ($search) {
            $filterNodes[] = LeafNode::local('name', 'prefix', $search);
        }

        $filter = null;
        if (count($filterNodes) === 1) {
            $filter = $filterNodes[0];
        } elseif (count($filterNodes) > 1) {
            $filter = new AndNode($filterNodes);
        }

        // Bounded cursor-paginated read using StarDust engine
        $entryQuery = new EntryQuery(
            tenantId: $tenantId,
            modelId: $modelId,
            filter: $filter,
            pageSize: 20,
            cursor: $cursor ? \StarDust\Read\Cursor::decode($cursor) : null
        );

        $entryPage = $this->stardust->read($entryQuery);

         // Fetch all items IN THIS WAREHOUSE for stats & category filter options
        $warehouseFilter = $warehouseId !== null ? LeafNode::local('id_warehouse', 'eq', $warehouseId) : null;

        $allEntriesPage = $this->stardust->read(new EntryQuery(
            tenantId: $tenantId,
            modelId: $modelId,
            filter: $warehouseFilter,
            pageSize: 500
        ));

        $allItems = collect($allEntriesPage->rows)->map(function ($row) {
            return (object) array_merge(['id' => $row->id], $row->fields);
        });

        $categories = $allItems
            ->pluck('category')
            ->filter()
            ->unique()
            ->values();

        $items = collect($entryPage->rows)->map(function ($row) {
            return (object) array_merge(['id' => $row->id], $row->fields);
        });

        if ($lowStock) {
            $items = $items->filter(function ($item) {
                return isset($item->quantity, $item->min_stock) && (int)$item->quantity <= (int)$item->min_stock;
            });
        }

        $stats = [
            'total_items' => $allItems->count(),
            'total_stock' => $allItems->sum(fn($i) => (int)($i->quantity ?? 0)),
            'total_value' => $allItems->sum(fn($i) => ((int)($i->quantity ?? 0)) * ((int)($i->price ?? 0))),
            'low_stock_count' => $allItems->filter(fn($i) => (int)($i->quantity ?? 0) <= (int)($i->min_stock ?? 0))->count(),
        ];

        return view('inventory.index', [
            'items' => $items,
            'nextCursor' => $entryPage->nextCursor?->opaque,
            'categories' => $categories,
            'stats' => $stats,
            'currentSearch' => $search,
            'currentCategory' => $category,
            'currentLowStock' => $lowStock,
            'activeWarehouse' => $activeWarehouse,
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
        ]);
    }

    /**
     * Show form to create new item.
     */
    public function create(Request $request)
    {
        [$tenantId, $modelId, $warehouseId, $activeWarehouse, $warehouses] = $this->resolveContext($request);
        return view('inventory.create', compact('activeWarehouse', 'warehouses', 'warehouseId'));
    }

    /**
     * Store a newly created item into StarDust engine.
     */
    public function store(Request $request)
    {
        [$tenantId, $modelId, $warehouseId] = $this->resolveContext($request);

        $validated = $request->validate([
            'warehouse' => 'required|integer',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'quantity' => 'required|integer|min:0',
            'price' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'supplier' => 'required|string|max:255',
            'location' => 'required|string|max:100',
            'min_stock' => 'required|integer|min:0',
            'description' => 'nullable|string',

            // Dynamic schemaless custom attributes
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'warranty_months' => 'nullable|integer|min:0',
            'serial_number' => 'nullable|string|max:100',
        ]);

        $idWarehouse = (int) $validated['warehouse'];
        unset($validated['warehouse']);

        $validated['quantity'] = (int) $validated['quantity'];
        $validated['price'] = (int) $validated['price'];
        $validated['min_stock'] = (int) $validated['min_stock'];

        if (isset($validated['warranty_months']) && $validated['warranty_months'] !== '') {
            $validated['warranty_months'] = (int) $validated['warranty_months'];
        }

        if (!empty($validated['expiry_date'])) {
            $validated['expiry_date'] = date('Y-m-d H:i:s', strtotime($validated['expiry_date']));
        }

        // Clean out nulls for extra dynamic fields
        $fields = array_filter($validated, fn($val) => $val !== null && $val !== '');
        $fields['id_warehouse'] = $idWarehouse;

        $payload = new EntryPayload(
            tenantId: $tenantId,
            modelId: $modelId,
            fields: $fields
        );

        $result = $this->stardust->write($payload);

        return redirect()->route('inventory.index', ['warehouse' => $tenantId])
            ->with('success', "Barang '{$validated['name']}' berhasil disimpan ke StarDust Engine! (Entry ID: {$result->entryId})");
    }

    /**
     * Display a specific inventory item.
     */
    public function show(Request $request, int $id)
    {
        [$tenantId, $modelId, $warehouseId, $activeWarehouse, $warehouses] = $this->resolveContext($request);

        $entry = $this->stardust->get($tenantId, $id);

        if (!$entry) {
            abort(404, 'Barang tidak ditemukan di StarDust engine.');
        }

        $item = (object) array_merge(['id' => $entry->id], $entry->fields);

        return view('inventory.show', compact('item', 'activeWarehouse', 'warehouses', 'tenantId'));
    }

    /**
     * Show edit form for an item.
     */
    public function edit(Request $request, int $id)
    {
        [$tenantId, $modelId, $warehouseId, $activeWarehouse, $warehouses] = $this->resolveContext($request);

        $entry = $this->stardust->get($tenantId, $id);

        if (!$entry) {
            abort(404, 'Barang tidak ditemukan di StarDust engine.');
        }

        $item = (object) array_merge(['id' => $entry->id], $entry->fields);

        return view('inventory.edit', compact('item', 'activeWarehouse', 'warehouses', 'tenantId'));
    }

    /**
     * Update an item in StarDust engine.
     */
    public function update(Request $request, int $id)
    {
        [$tenantId, $modelId, $warehouseId] = $this->resolveContext($request);

        $validated = $request->validate([
            'warehouse' => 'required|integer',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'quantity' => 'required|integer|min:0',
            'price' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'supplier' => 'required|string|max:255',
            'location' => 'required|string|max:100',
            'min_stock' => 'required|integer|min:0',
            'description' => 'nullable|string',

            // Dynamic schemaless custom attributes
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'warranty_months' => 'nullable|integer|min:0',
            'serial_number' => 'nullable|string|max:100',
        ]);

        $idWarehouse = (int) $validated['warehouse'];
        unset($validated['warehouse']);

        $validated['quantity'] = (int) $validated['quantity'];
        $validated['price'] = (int) $validated['price'];
        $validated['min_stock'] = (int) $validated['min_stock'];

        if (isset($validated['warranty_months']) && $validated['warranty_months'] !== '') {
            $validated['warranty_months'] = (int) $validated['warranty_months'];
        }
        if (!empty($validated['expiry_date'])) {
            $validated['expiry_date'] = date('Y-m-d H:i:s', strtotime($validated['expiry_date']));
        }

        $fields = array_filter($validated, fn($val) => $val !== null && $val !== '');
        $fields['id_warehouse'] = $idWarehouse;

        $this->stardust->updateEntry($tenantId, $id, $fields);

        return redirect()->route('inventory.show', ['inventory' => $id, 'warehouse' => $tenantId])
            ->with('success', "Barang '{$validated['name']}' berhasil diperbarui di StarDust Engine!");
    }

    /**
     * Perform quick Stock In (+ qty) movement.
     */
    public function stockIn(Request $request, int $id)
    {
        [$tenantId] = $this->resolveContext($request);

        $amount = (int) $request->input('amount', 1);
        if ($amount <= 0) $amount = 1;

        $entry = $this->stardust->get($tenantId, $id);
        if (!$entry) {
            return redirect()->back()->with('error', 'Barang tidak ditemukan di StarDust.');
        }

        $fields = $entry->fields;
        $fields['quantity'] = ((int) ($fields['quantity'] ?? 0)) + $amount;

        $this->stardust->updateEntry($tenantId, $id, $fields);

        return redirect()->back()->with('success', "Stok barang '{$fields['name']}' berhasil ditambah (+{$amount}) di StarDust Engine!");
    }

    /**
     * Perform quick Stock Out (- qty) movement.
     */
    public function stockOut(Request $request, int $id)
    {
        [$tenantId] = $this->resolveContext($request);

        $amount = (int) $request->input('amount', 1);
        if ($amount <= 0) $amount = 1;

        $entry = $this->stardust->get($tenantId, $id);
        if (!$entry) {
            return redirect()->back()->with('error', 'Barang tidak ditemukan di StarDust.');
        }

        $fields = $entry->fields;
        $currentQty = (int) ($fields['quantity'] ?? 0);
        if ($currentQty < $amount) {
            return redirect()->back()->with('error', "Stok tidak mencukupi! Stok saat ini: {$currentQty}");
        }

        $fields['quantity'] = $currentQty - $amount;

        $this->stardust->updateEntry($tenantId, $id, $fields);

        return redirect()->back()->with('success', "Stok barang '{$fields['name']}' berhasil dikurangi (-{$amount}) di StarDust Engine!");
    }

        /**
     * StarDust Bulk Ingestion — dikustomisasi lewat form: jumlah barang,
     * ukuran chunk, delay antar-chunk, dan mode (sync/async).
     */
    public function bulkImport(Request $request)
    {
        [$tenantId, $modelId, $warehouseId] = $this->resolveContext($request);

        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:5000',
            'chunk_size' => 'nullable|integer|min:1|max:1000',
            'delay_ms' => 'nullable|integer|min:0|max:5000',
            'mode' => 'required|in:sync,async',
        ]);

        $count = (int) $validated['count'];
        $chunkSize = (int) ($validated['chunk_size'] ?? 500);
        $delayMs = (int) ($validated['delay_ms'] ?? 0);
        $mode = $validated['mode'];

        // Ambil kode gudang aktif supaya SKU dummy tetap konsisten formatnya
        $gudangModelId = $this->modelId($tenantId, config('stardust.warehouse_model_name', 'gudang'));
        $activeWh = $this->loadWarehouses($tenantId, $gudangModelId)->firstWhere('id', $warehouseId);
        $warehouseCode = $activeWh->code ?? 'GEN';

        $faker = FakerFactory::create('id_ID');
        $categories = ['Elektronik', 'Aksesori', 'Peralatan Kantor', 'Bahan Konsumsi', 'Perawatan', 'Power & Battery', 'Logistik', 'Hardware Gudang', 'Kabel & Adaptor'];
        $sequenceByCategory = [];
        $payloads = [];

        for ($i = 0; $i < $count; $i++) {
            $category = $faker->randomElement($categories);
            $sequenceByCategory[$category] = ($sequenceByCategory[$category] ?? 0) + 1;

            $item = [
                'id_warehouse' => $warehouseId,
                'name' => ucfirst($faker->words(3, true)),
                'sku' => SkuGenerator::generate($category, $warehouseCode, $sequenceByCategory[$category]),
                'category' => $category,
                'quantity' => $faker->numberBetween(0, 200),
                'price' => $faker->numberBetween(15, 25000) * 1000,
                'unit' => $faker->randomElement(['Pcs', 'Unit', 'Box', 'Botol', 'Bungkus']),
                'supplier' => $faker->company(),
                'location' => 'Rak ' . strtoupper($faker->lexify('?')) . '-' . $faker->numberBetween(1, 20),
                'min_stock' => $faker->numberBetween(2, 20),
                'description' => $faker->sentence(6),
            ];

            $payloads[] = new EntryPayload(tenantId: $tenantId, modelId: $modelId, fields: $item);
        }

        // --- Mode ASYNC: masuk antrian (stardust_import_jobs), diproses
        //     oleh proses Reconciler yang jalan terpisah (`vendor/bin/stardust reconciler`).
        if ($mode === 'async') {
            try {
                $jobId = $this->stardust->submitBulkWrite($tenantId, $payloads);

                return redirect()->route('inventory.index', ['warehouse' => $warehouseId])
                    ->with('success', "Bulk write ASYNC disubmit! Job #{$jobId->jobId} ({$count} barang). Job ini BARU diproses kalau proses Reconciler dijalankan terpisah: `vendor\\bin\\stardust reconciler`. Cek status: " . route('inventory.bulk-import.status', $jobId->jobId));
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Gagal submit async bulk write: ' . $e->getMessage());
            }
        }

        // --- Mode SYNC: langsung ditulis saat itu juga, dibagi per-chunk.
        try {
            $options = new BulkIngestOptions(chunkSize: $chunkSize, interChunkDelayMicros: $delayMs * 1000);
            $result = $this->stardust->bulkWrite($payloads, $options);

            return redirect()->route('inventory.index', ['warehouse' => $warehouseId])
                ->with('success', "Bulk write SYNC berhasil! {$result->entriesCommitted} barang tersimpan dalam " . count($result->chunks) . " chunk (ukuran chunk: {$chunkSize}, delay: {$delayMs}ms).");
        } catch (\StarDust\Exception\PayloadTooLargeException $e) {
            return redirect()->back()->with('error', "Jumlah {$count} terlalu besar untuk mode Sync (maksimal 1000). Gunakan mode Async untuk jumlah lebih besar.");
        }
    }

    /**
     * Cek status job bulk write async (dibaca dari stardust_import_jobs).
     */
    public function bulkImportStatus(Request $request, int $jobId)
    {
        [$tenantId, , $warehouseId] = $this->resolveContext($request);

        $job = $this->stardust->getImportJob($tenantId, $jobId);

        if (!$job) {
            return redirect()->route('inventory.index', ['warehouse' => $warehouseId])
                ->with('error', "Job import #{$jobId} tidak ditemukan.");
        }

        $message = "Job #{$job->id} — status: {$job->status} — {$job->entriesWritten}/{$job->entryCount} entri tertulis"
            . ($job->chunks !== null ? " ({$job->chunks} chunk selesai)" : '')
            . ($job->status === 'pending' ? '. Jalankan `vendor\\bin\\stardust reconciler` untuk memproses.' : '.');

        return redirect()->route('inventory.index', ['warehouse' => $warehouseId])
            ->with($job->status === 'failed' ? 'error' : 'success', $message);
    }

    /**
     * Soft-delete an item from StarDust engine.
     */
    public function destroy(Request $request, int $id)
    {
        [$tenantId, $modelId, $warehouseId] = $this->resolveContext($request);

        $deleted = $this->stardust->deleteEntry($tenantId, $id);

        if (!$deleted) {
            return redirect()->route('inventory.index', ['warehouse' => $warehouseId])
                ->with('error', 'Gagal menghapus barang atau barang tidak ditemukan.');
        }

        return redirect()->route('inventory.index', ['warehouse' => $warehouseId])
            ->with('success', 'Barang berhasil dihapus dari StarDust Engine!');
    }
}
