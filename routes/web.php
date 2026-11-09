<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\VendorController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('invoices.index'));
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Invoice and vendor URLs use random IDs (see App\Models\Concerns\HasPublicId), never counting numbers.
    Route::resource('invoices', InvoiceController::class)->where(['invoice' => Invoice::PUBLIC_ID_PATTERN]);

    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
    Route::get('/vendors/export', [VendorController::class, 'export'])->name('vendors.export');
    Route::get('/vendors/lookup', [VendorController::class, 'lookup'])->name('vendors.lookup');
    Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->where('vendor', Vendor::PUBLIC_ID_PATTERN)->name('vendors.show');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->where('vendor', Vendor::PUBLIC_ID_PATTERN)->name('vendors.update');
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->where('vendor', Vendor::PUBLIC_ID_PATTERN)->name('vendors.destroy');

    Route::get('/business', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/business', [ProfileController::class, 'update'])->name('profile.update');

    // Super admin panel: every account, read-only. Anyone else gets "Not Found".
    Route::middleware(EnsureSuperAdmin::class)->prefix('super-admin')->name('admin.')->group(function () {
        Route::get('/', [SuperAdminController::class, 'index'])->name('index');
        Route::get('/accounts/{account}', [SuperAdminController::class, 'show'])
            ->where('account', User::PUBLIC_ID_PATTERN)->name('show');
    });
});
