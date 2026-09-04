<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use StarDust\StarDust;
use StarDust\Read\EntryQuery;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Feed the warehouse-context bar in layouts.app with the real
        // 'gudang' entries from the (single-tenant) StarDust engine.
        View::composer('layouts.app', function ($view) {
            $tenantId = (int) config('stardust.tenant_id', 1);

            try {
                $stardust = app(StarDust::class);
                $models = $stardust->listModels($tenantId);
                $gudangModel = collect($models)->firstWhere('name', config('stardust.warehouse_model_name', 'gudang'));

                $warehouses = collect();
                if ($gudangModel) {
                    $page = $stardust->read(new EntryQuery(
                        tenantId: $tenantId,
                        modelId: $gudangModel->modelId,
                        pageSize: 200,
                    ));

                    $warehouses = collect($page->rows)->map(
                        fn ($row) => (object) array_merge(['id' => $row->id], $row->fields)
                    );
                }
            } catch (\Throwable $e) {
                $warehouses = collect();
            }

            $currentId = session('active_warehouse');
            $current = $currentId !== null
                ? $warehouses->firstWhere('id', (int) $currentId)
                : null;

            $view->with('navWarehouses', $warehouses);
            $view->with('navCurrentWarehouse', $current ?? $warehouses->first());
        });
    }
}