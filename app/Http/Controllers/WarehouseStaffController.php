<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use StarDust\Read\EntryQuery;
use StarDust\StarDust;

class WarehouseStaffController extends Controller
{
    public function __construct(
        private readonly StarDust $stardust
    ) {}

    public function index()
    {
        $warehouses = $this->getWarehouseList();
        $staffList = User::where('role', '!=', 'admin')->get();

        $assignments = DB::table('warehouse_staff')
            ->join('users', 'users.id', '=', 'warehouse_staff.user_id')
            ->select('warehouse_staff.warehouse_id', 'users.id as user_id', 'users.name')
            ->get()
            ->groupBy('warehouse_id');

        $usersWithWarehouseCol = User::whereNotNull('id_warehouse')->get()->groupBy('id_warehouse');

        foreach ($warehouses as $warehouse) {
            $staffFromPivot = $assignments->get($warehouse->id, collect());
            $staffFromCol = $usersWithWarehouseCol->get($warehouse->id, collect());

            $pivotIds = $staffFromPivot->pluck('user_id')->map(fn ($id) => (int) $id)->toArray();
            $colIds = $staffFromCol->pluck('id')->map(fn ($id) => (int) $id)->toArray();

            $assignedIds = array_values(array_unique(array_merge($pivotIds, $colIds)));
            $warehouse->assigned_staff_ids = $assignedIds;

            $namesFromPivot = $staffFromPivot->pluck('name');
            $namesFromCol = $staffFromCol->pluck('name');
            $names = $namesFromPivot->merge($namesFromCol)->unique();
            $warehouse->staff_names = $names->isNotEmpty() ? $names->implode(', ') : '-';
        }

        return view('staff.index', compact('warehouses', 'staffList'));
    }

    public function edit($warehouseId)
    {
        return redirect()->route('staff.index');
    }

    public function update(Request $request, $warehouseId)
    {
        $request->validate([
            'staff_ids' => 'array',
            'staff_ids.*' => 'exists:users,id',
        ]);

        $staffIds = array_map('intval', $request->input('staff_ids', []));

        DB::table('warehouse_staff')->where('warehouse_id', $warehouseId)->delete();

        $now = now();
        $rows = collect($staffIds)->map(fn ($userId) => [
            'warehouse_id' => $warehouseId,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        if (! empty($rows)) {
            DB::table('warehouse_staff')->insert($rows);
        }

        if (! empty($staffIds)) {
            User::whereIn('id', $staffIds)->update(['id_warehouse' => $warehouseId]);
        }

        $warehouse = collect($this->getWarehouseList())->firstWhere('id', (int) $warehouseId);
        $warehouseName = $warehouse->name ?? $warehouse->fields['name'] ?? 'Gudang';

        return redirect()->route('staff.index')->with('success', "Akses staff untuk {$warehouseName} berhasil diperbarui.");
    }

    /**
     * Ambil daftar gudang dari StarDust lewat method read().
     */
    private function getWarehouseList()
    {
        $tenantId = (int) config('stardust.tenant_id', 1);
        $models = $this->stardust->listModels($tenantId);
        $gudangModel = collect($models)->firstWhere('name', config('stardust.warehouse_model_name', 'gudang'));

        if (! $gudangModel) {
            return collect();
        }

        $page = $this->stardust->read(new EntryQuery(
            tenantId: $tenantId,
            modelId: $gudangModel->modelId,
        ));

        return collect($page->rows)->map(function ($row) {
            $obj = (object) array_merge(['id' => $row->id], $row->fields);
            $obj->fields = $row->fields;

            return $obj;
        });
    }
}
