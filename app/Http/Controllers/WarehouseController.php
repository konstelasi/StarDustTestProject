<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use StarDust\StarDust;
use StarDust\Write\EntryPayload;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly StarDust $stardust
    ) {}

    public function create()
    {
        return view('warehouses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'location' => 'required|string|max:255',
            'manager' => 'required|string|max:255',
        ]);

        $tenantId = (int) config('stardust.tenant_id', 1);
        $models = $this->stardust->listModels($tenantId);
        $gudangModel = collect($models)->firstWhere('name', config('stardust.warehouse_model_name', 'gudang'));

        if (! $gudangModel) {
            return redirect()->back()->with('error', 'Model gudang belum terregistrasi di StarDust.')->withInput();
        }

        $result = $this->stardust->write(new EntryPayload(
            tenantId: $tenantId,
            modelId: $gudangModel->modelId,
            fields: [
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'location' => $validated['location'],
                'manager' => $validated['manager'],
            ],
        ));

        session(['active_warehouse' => $result->entryId]);

        return redirect()->route('inventory.index', ['warehouse' => $result->entryId])
            ->with('success', "Gudang '{$validated['name']}' ({$validated['code']}) berhasil ditambahkan ke StarDust!");
    }
}
