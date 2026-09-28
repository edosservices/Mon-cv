<?php

use App\Http\Controllers\ActiveUserController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WifiSessionController;
use App\Http\Controllers\WifiShopController;
use App\Http\Controllers\WifiZoneController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/wifi/{slug}', [WifiShopController::class, 'show'])->name('shop.show');
Route::post('/wifi/{slug}', [WifiShopController::class, 'checkout'])->middleware('throttle:20,1')->name('shop.checkout');
Route::get('/wifi/{slug}/commande/{token}', [WifiShopController::class, 'order'])->name('shop.order');
Route::get('/ticket/{token}', [WifiShopController::class, 'ticket'])->name('tickets.public');

Route::middleware(['auth', 'tenant', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
    Route::get('/tenants', [PlatformController::class, 'tenants'])->name('tenants');
    Route::patch('/tenants/{tenant}', [PlatformController::class, 'updateTenant'])->name('tenants.update');
    Route::delete('/tenants/{tenant}', [PlatformController::class, 'destroyTenant'])->name('tenants.destroy');
    Route::get('/plans', [PlatformController::class, 'plans'])->name('plans');
    Route::patch('/plans/{saasPlan}', [PlatformController::class, 'updatePlan'])->name('plans.update');
    Route::get('/payments', [PlatformController::class, 'payments'])->name('payments');
    Route::get('/logs', [PlatformController::class, 'logs'])->name('logs');
    Route::post('/payments/{paymentId}/confirm', [PlatformController::class, 'confirmPayment'])->name('payments.confirm');
});

Route::middleware(['auth', 'tenant', 'tenant.active', 'role:entrepreneur,staff'])->group(function () {
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('/subscription', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::middleware('subscription')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::resource('wifi-zones', WifiZoneController::class)->except('show')->parameters(['wifi-zones' => 'wifiZone'])->middleware('permission:zones.manage');
        Route::resource('mikrotiks', MikrotikController::class)->except('show')->middleware('permission:mikrotiks.manage');
        Route::post('/mikrotiks/{mikrotik}/test', [MikrotikController::class, 'test'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.test');
        Route::post('/mikrotiks/{mikrotik}/profiles', [MikrotikController::class, 'syncProfiles'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.profiles');
        Route::resource('plans', PlanController::class)->except('show')->middleware('permission:plans.manage');
        Route::get('/vouchers', [VoucherController::class, 'index'])->middleware('permission:vouchers.manage')->name('vouchers.index');
        Route::post('/vouchers', [VoucherController::class, 'store'])->middleware('permission:vouchers.manage')->name('vouchers.store');
        Route::get('/vouchers/{voucher}', [VoucherController::class, 'show'])->middleware('permission:vouchers.manage')->name('vouchers.show');
        Route::get('/vouchers/{voucher}/pdf', [VoucherController::class, 'pdf'])->middleware('permission:vouchers.manage')->name('vouchers.pdf');
        Route::patch('/vouchers/{voucher}', [VoucherController::class, 'updateStatus'])->middleware('permission:vouchers.manage')->name('vouchers.status');
        Route::delete('/vouchers/{voucher}', [VoucherController::class, 'destroy'])->middleware('permission:vouchers.manage')->name('vouchers.destroy');
        Route::post('/vouchers/{voucher}/sell', [SaleController::class, 'sellVoucher'])->middleware('permission:sales.confirm')->name('vouchers.sell');
        Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.manage')->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.manage')->name('customers.show');
        Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->middleware('permission:sales.view')->name('sales.show');
        Route::post('/sales/{sale}/confirm', [SaleController::class, 'confirm'])->middleware('permission:sales.confirm')->name('sales.confirm');
        Route::get('/active-users', [ActiveUserController::class, 'index'])->middleware('permission:sessions.view')->name('active-users.index');
        Route::post('/active-users/disconnect', [ActiveUserController::class, 'disconnect'])->middleware('permission:sessions.disconnect')->name('active-users.disconnect');
        Route::get('/sessions', [WifiSessionController::class, 'index'])->middleware('permission:sessions.view')->name('sessions.index');
        Route::get('/statistics', StatisticsController::class)->middleware('permission:statistics.view')->name('statistics');
        Route::get('/settings', [SettingController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
        Route::get('/staff', [StaffController::class, 'index'])->middleware('permission:staff.manage')->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->middleware('permission:staff.manage')->name('staff.store');
    });
});
