<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InventoryController;

Route::get('/', function () {
    return redirect()->route('inventory.index');
});

Route::post('inventory/bulk-import', [InventoryController::class, 'bulkImport'])->name('inventory.bulk-import');
Route::get('inventory/bulk-import/status/{jobId}', [InventoryController::class, 'bulkImportStatus'])->name('inventory.bulk-import.status');
Route::post('inventory/{inventory}/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
Route::post('inventory/{inventory}/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');

Route::resource('inventory', InventoryController::class);
