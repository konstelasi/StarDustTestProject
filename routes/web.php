<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseStaffController;
use Illuminate\Support\Facades\Route;

// --- Guest Authentication & Registration Routes ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
});

// --- Authenticated App Routes ---
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('inventory.index');
    });

    // Admin Only App Mode Toggle
    Route::post('/app-mode/toggle', [AuthController::class, 'toggleAppMode'])
        ->middleware('role:admin')
        ->name('app-mode.toggle');

    // Admin Only Actions (Warehouse Management & Inventory Admin Actions)
    Route::middleware('role:admin')->group(function () {
        Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');

        Route::post('inventory/bulk-import', [InventoryController::class, 'bulkImport'])->name('inventory.bulk-import');
        Route::delete('inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    });

    // General Inventory Routes (Admin & Staff)
    Route::get('inventory/bulk-import/status/{jobId}', [InventoryController::class, 'bulkImportStatus'])->name('inventory.bulk-import.status');
    Route::post('inventory/{inventory}/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
    Route::post('inventory/{inventory}/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');

    Route::resource('inventory', InventoryController::class)->except(['destroy']);

    Route::get('/staff', [WarehouseStaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/{warehouse}/edit', [WarehouseStaffController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{warehouse}', [WarehouseStaffController::class, 'update'])->name('staff.update');
});
