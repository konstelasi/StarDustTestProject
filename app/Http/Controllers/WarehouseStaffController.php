<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use StarDust\Read\EntryQuery;
use StarDust\StarDust;

class WarehouseStaffController extends Controller
{
    public function __construct(
        private readonly StarDust $stardust
    ) {}

    public function index()
    {
        $authUser = auth()->user();
        $warehouses = $this->getWarehouseList();

        if ($authUser && $authUser->isAdminGudang()) {
            $assignedWhIds = DB::table('warehouse_staff')
                ->where('user_id', $authUser->id)
                ->pluck('warehouse_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
            if (empty($assignedWhIds) && $authUser->id_warehouse) {
                $assignedWhIds = [(int) $authUser->id_warehouse];
            }
            $warehouses = $warehouses->whereIn('id', $assignedWhIds)->values();
        }

        $staffList = User::whereNotIn('role', ['admin_platform', 'admin'])
            ->where('company_id', Tenant::id())
            ->get();

        $assignments = DB::table('warehouse_staff')
            ->join('users', 'users.id', '=', 'warehouse_staff.user_id')
            ->where('users.company_id', Tenant::id())
            ->select('warehouse_staff.warehouse_id', 'users.id as user_id', 'users.name')
            ->get()
            ->groupBy('warehouse_id');

        $usersWithWarehouseCol = User::whereNotNull('id_warehouse')
            ->where('company_id', Tenant::id())
            ->get()
            ->groupBy('id_warehouse');

        $allAssignedUserIds = [];

        foreach ($warehouses as $warehouse) {
            $staffFromPivot = $assignments->get($warehouse->id, collect());
            $staffFromCol = $usersWithWarehouseCol->get($warehouse->id, collect());

            $pivotIds = $staffFromPivot->pluck('user_id')->map(fn ($id) => (int) $id)->toArray();
            $colIds = $staffFromCol->pluck('id')->map(fn ($id) => (int) $id)->toArray();

            $assignedIds = array_values(array_unique(array_merge($pivotIds, $colIds)));
            $warehouse->assigned_staff_ids = $assignedIds;
            $allAssignedUserIds = array_merge($allAssignedUserIds, $assignedIds);

            $namesFromPivot = $staffFromPivot->pluck('name');
            $namesFromCol = $staffFromCol->pluck('name');
            $names = $namesFromPivot->merge($namesFromCol)->unique();
            $warehouse->staff_names = $names->isNotEmpty() ? $names->implode(', ') : '-';
        }

        $allAssignedUserIds = array_unique($allAssignedUserIds);
        $unassignedStaff = $staffList->reject(fn ($u) => in_array($u->id, $allAssignedUserIds))->values();

        return view('staff.index', compact('warehouses', 'staffList', 'unassignedStaff'));
    }

    public function edit($warehouseId)
    {
        return redirect()->route('staff.index');
    }

    public function update(Request $request, $warehouseId)
    {
        $authUser = auth()->user();
        if ($authUser && $authUser->isAdminGudang()) {
            $assignedWhIds = DB::table('warehouse_staff')
                ->where('user_id', $authUser->id)
                ->pluck('warehouse_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
            if (empty($assignedWhIds) && $authUser->id_warehouse) {
                $assignedWhIds = [(int) $authUser->id_warehouse];
            }
            if (! $this->getWarehouseList()->contains('id', (int) $warehouseId)) {
                abort(404, 'Gudang tidak ditemukan pada perusahaan ini.');
            }
        }

        $request->validate([
            'staff_ids' => 'array',
            'staff_ids.*' => [Rule::exists('users', 'id')->where('company_id', Tenant::id())],
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

        // Sync id_warehouse attribute on User model for consistency
        $allStaff = User::whereNotIn('role', ['admin_platform', 'admin'])
            ->where('company_id', Tenant::id())
            ->get();
        foreach ($allStaff as $st) {
            $firstWh = DB::table('warehouse_staff')->where('user_id', $st->id)->value('warehouse_id');
            $st->update(['id_warehouse' => $firstWh ? (int) $firstWh : null]);
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
        $tenantId = Tenant::id();
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
