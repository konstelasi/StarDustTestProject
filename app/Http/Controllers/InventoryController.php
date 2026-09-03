<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use StarDust\StarDust;
use StarDust\Read\EntryQuery;
use StarDust\Filter\Ast\LeafNode;
use StarDust\Filter\Ast\AndNode;
use StarDust\Write\EntryPayload;

class InventoryController extends Controller
{
    public function __construct(private readonly StarDust $stardust)
    {
    }

    /**
     * Resolve active warehouse tenant ID and model ID.
     */
    private function resolveTenantAndModel(Request $request): array
    {
        $warehouses = config('stardust.warehouses', []);
        $tenantId = (int) $request->input('warehouse', session('active_warehouse', config('stardust.tenant_id', 1)));
        
        if (!isset($warehouses[$tenantId])) {
            $tenantId = 1;
        }

        session(['active_warehouse' => $tenantId]);
        $activeWarehouse = $warehouses[$tenantId];

        $models = $this->stardust->listModels($tenantId);
        $productModel = collect($models)->firstWhere('name', config('stardust.model_name', 'product'));
        $modelId = $productModel ? $productModel->modelId : 1;

        return [$tenantId, $modelId, $activeWarehouse, $warehouses];
    }

    /**
     * Display listing of inventory items from StarDust engine for active warehouse.
     */
    public function index(Request $request)
    {
        [$tenantId, $modelId, $activeWarehouse, $warehouses] = $this->resolveTenantAndModel($request);

        $search = $request->input('search');
        $category = $request->input('category');
        $lowStock = $request->boolean('low_stock');
        $cursor = $request->input('cursor');

        $filterNodes = [];

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

        // Fetch all items for stats & category filter options
        $allEntriesPage = $this->stardust->read(new EntryQuery(
            tenantId: $tenantId,
            modelId: $modelId,
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
            'tenantId' => $tenantId,
        ]);
    }

    /**
     * Show form to create new item.
     */
    public function create(Request $request)
    {
        [$tenantId, $modelId, $activeWarehouse, $warehouses] = $this->resolveTenantAndModel($request);
        return view('inventory.create', compact('activeWarehouse', 'warehouses', 'tenantId'));
    }

    /**
     * Store a newly created item into StarDust engine.
     */
    public function store(Request $request)
    {
        [$tenantId, $modelId] = $this->resolveTenantAndModel($request);

        $validated = $request->validate([
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
            'expiry_date' => 'nullable|string|max:50',
            'warranty_months' => 'nullable|integer|min:0',
            'serial_number' => 'nullable|string|max:100',
        ]);

        $validated['quantity'] = (int) $validated['quantity'];
        $validated['price'] = (int) $validated['price'];
        $validated['min_stock'] = (int) $validated['min_stock'];

        if (isset($validated['warranty_months']) && $validated['warranty_months'] !== '') {
            $validated['warranty_months'] = (int) $validated['warranty_months'];
        }

        // Clean out nulls for extra dynamic fields
        $fields = array_filter($validated, fn($val) => $val !== null && $val !== '');

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
        [$tenantId, $modelId, $activeWarehouse, $warehouses] = $this->resolveTenantAndModel($request);

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
        [$tenantId, $modelId, $activeWarehouse, $warehouses] = $this->resolveTenantAndModel($request);

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
        [$tenantId, $modelId] = $this->resolveTenantAndModel($request);

        $validated = $request->validate([
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
            'expiry_date' => 'nullable|string|max:50',
            'warranty_months' => 'nullable|integer|min:0',
            'serial_number' => 'nullable|string|max:100',
        ]);

        $validated['quantity'] = (int) $validated['quantity'];
        $validated['price'] = (int) $validated['price'];
        $validated['min_stock'] = (int) $validated['min_stock'];

        if (isset($validated['warranty_months']) && $validated['warranty_months'] !== '') {
            $validated['warranty_months'] = (int) $validated['warranty_months'];
        }

        $fields = array_filter($validated, fn($val) => $val !== null && $val !== '');

        $this->stardust->updateEntry($tenantId, $id, $fields);

        return redirect()->route('inventory.show', ['inventory' => $id, 'warehouse' => $tenantId])
            ->with('success', "Barang '{$validated['name']}' berhasil diperbarui di StarDust Engine!");
    }

    /**
     * Perform quick Stock In (+ qty) movement.
     */
    public function stockIn(Request $request, int $id)
    {
        [$tenantId] = $this->resolveTenantAndModel($request);

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
        [$tenantId] = $this->resolveTenantAndModel($request);

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
     * Perform StarDust Bulk Ingestion (`bulkWrite`).
     */
    public function bulkImport(Request $request)
    {
        [$tenantId, $modelId] = $this->resolveTenantAndModel($request);

        $sampleBulk = [
            [
                'name' => 'Barcode Scanner Honeywell 1470g',
                'sku' => 'SCN-HON-1470',
                'category' => 'Hardware Gudang',
                'quantity' => 15,
                'price' => 2400000,
                'unit' => 'Unit',
                'supplier' => 'PT AutoID Indonesia',
                'location' => 'Rak D-01',
                'min_stock' => 5,
                'description' => '2D Barcode scanner USB high speed',
            ],
            [
                'name' => 'Thermal Label Printer Zebra ZT230',
                'sku' => 'PRN-ZEB-ZT230',
                'category' => 'Hardware Gudang',
                'quantity' => 6,
                'price' => 11200000,
                'unit' => 'Unit',
                'supplier' => 'PT AutoID Indonesia',
                'location' => 'Rak D-02',
                'min_stock' => 2,
                'description' => 'Industrial Thermal Transfer Printer 203dpi',
            ],
            [
                'name' => 'Pallet Plastik Heavy Duty 120x100cm',
                'sku' => 'PLT-HD-120100',
                'category' => 'Logistik',
                'quantity' => 40,
                'price' => 650000,
                'unit' => 'Pcs',
                'supplier' => 'CV Pallet Utama',
                'location' => 'Area Loading Bay',
                'min_stock' => 10,
                'description' => 'Kapasitas statis 4 ton, dinamis 1.5 ton',
            ],
        ];

        $payloads = [];
        foreach ($sampleBulk as $item) {
            $payloads[] = new EntryPayload(
                tenantId: $tenantId,
                modelId: $modelId,
                fields: $item
            );
        }

        $result = $this->stardust->bulkWrite($payloads);

        return redirect()->route('inventory.index', ['warehouse' => $tenantId])
            ->with('success', "Simulasi StarDust bulkWrite() berhasil! Memproses {$result->entriesCommitted} barang secara kolektif.");
    }

    /**
     * Soft-delete an item from StarDust engine.
     */
    public function destroy(Request $request, int $id)
    {
        [$tenantId] = $this->resolveTenantAndModel($request);

        $deleted = $this->stardust->deleteEntry($tenantId, $id);

        if (!$deleted) {
            return redirect()->route('inventory.index', ['warehouse' => $tenantId])
                ->with('error', 'Gagal menghapus barang atau barang tidak ditemukan.');
        }

        return redirect()->route('inventory.index', ['warehouse' => $tenantId])
            ->with('success', 'Barang berhasil dihapus dari StarDust Engine!');
    }
}
