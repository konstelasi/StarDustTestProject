<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseStaffController;
use Illuminate\Support\Facades\Route;

// --- Guest Authentication Routes ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// --- Authenticated App Routes ---
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('inventory.index');
    });

    // Platform Admin Only Actions (App Mode Toggle, User Registration, Warehouse Creation, Bulk Import)
    Route::middleware('role:admin_platform,admin')->group(function () {
        Route::post('/app-mode/toggle', [AuthController::class, 'toggleAppMode'])->name('app-mode.toggle');
        Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->name('register.post');

        Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');

        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::post('companies/{company}/select', [CompanyController::class, 'select'])->name('companies.select');

        Route::post('inventory/bulk-import', [InventoryController::class, 'bulkImport'])->name('inventory.bulk-import');
    });

    // Admin Actions (Platform Admin & Warehouse Admin: Inventory Delete & Staff Access Management)
    Route::middleware('role:admin_platform,admin,admin_gudang')->group(function () {
        Route::delete('inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

        Route::get('/staff', [WarehouseStaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/{warehouse}/edit', [WarehouseStaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{warehouse}', [WarehouseStaffController::class, 'update'])->name('staff.update');
    });

    // General Inventory Routes (Admin Platform, Admin Gudang, Staff Gudang)
    Route::get('inventory/bulk-import/status/{jobId}', [InventoryController::class, 'bulkImportStatus'])->name('inventory.bulk-import.status');
    Route::post('inventory/{inventory}/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
    Route::post('inventory/{inventory}/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');

    Route::resource('inventory', InventoryController::class)->except(['destroy']);
});
