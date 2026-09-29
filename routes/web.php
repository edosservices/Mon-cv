<?php

use App\Http\Controllers\ActiveUserController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\Admin\ProductionCheckController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\HotspotSessionController;
use App\Http\Controllers\MikrotikAssistantController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ReportsController;
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

Route::prefix('client')->name('client.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [ClientPortalController::class, 'loginForm'])->name('login');
        Route::post('/login', [ClientPortalController::class, 'login'])->middleware('throttle:client-login');
        Route::get('/register', [ClientPortalController::class, 'registerForm'])->name('register');
        Route::post('/register', [ClientPortalController::class, 'register'])->middleware('throttle:client-register');
        Route::get('/forgot', [ClientPortalController::class, 'forgotForm'])->name('forgot');
        Route::post('/forgot', [ClientPortalController::class, 'forgot'])->middleware('throttle:client-recover');
        Route::get('/reset', [ClientPortalController::class, 'resetForm'])->name('reset');
        Route::post('/reset', [ClientPortalController::class, 'reset'])->middleware('throttle:client-recover');
    });

    Route::get('/acheter', [ClientPortalController::class, 'buy'])->name('buy');

    Route::middleware(['auth', 'role:client'])->group(function () {
        Route::get('/dashboard', [ClientPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/tickets', [ClientPortalController::class, 'tickets'])->name('tickets');
        Route::get('/historique', [ClientPortalController::class, 'history'])->name('history');
        Route::get('/profile', [ClientPortalController::class, 'profile'])->name('profile');
        Route::put('/profile', [ClientPortalController::class, 'updateProfile'])->name('profile.update');
    });
});

Route::get('/wifi/{slug}', [WifiShopController::class, 'show'])->name('shop.show');
Route::get('/wifi/{slug}/marque', [WifiShopController::class, 'brand'])->name('shop.brand');
Route::get('/wifi/{slug}/manifest.webmanifest', [WifiShopController::class, 'manifest'])->name('shop.manifest');
Route::get('/wifi/{slug}/forfait/{plan}', [WifiShopController::class, 'plan'])->whereNumber('plan')->name('shop.plan');
Route::post('/wifi/{slug}/forfait/{plan}', [WifiShopController::class, 'saveCustomer'])->whereNumber('plan')->middleware('throttle:20,1')->name('shop.customer');
Route::get('/wifi/{slug}/forfait/{plan}/paiement', [WifiShopController::class, 'pay'])->whereNumber('plan')->name('shop.pay');
Route::post('/wifi/{slug}', [WifiShopController::class, 'checkout'])->middleware('throttle:20,1')->name('shop.checkout');
Route::get('/wifi/{slug}/commande/{token}', [WifiShopController::class, 'order'])->name('shop.order');
Route::post('/wifi/{slug}/commande/{token}/actualiser', [WifiShopController::class, 'refreshPayment'])->middleware('throttle:30,1')->name('shop.payment.refresh');
// UniPay : POST /payments/unipay/webhook
Route::post('/payments/{provider}/webhook', PaymentWebhookController::class)->middleware('throttle:60,1')->name('payments.webhook');
Route::get('/wifi/{slug}/mes-tickets', [WifiShopController::class, 'tickets'])->name('shop.tickets');
Route::post('/wifi/{slug}/mes-tickets', [WifiShopController::class, 'lookup'])->middleware('throttle:10,1')->name('shop.lookup');
Route::get('/hotspot/session/{username}', [HotspotSessionController::class, 'show'])
    ->where('username', '[A-Za-z0-9_-]{1,80}')
    ->middleware('throttle:60,1')
    ->name('hotspot.session');
Route::get('/hotspot/session.js', [HotspotSessionController::class, 'script'])
    ->middleware('throttle:60,1')
    ->name('hotspot.session.script');
Route::get('/ticket/{token}', [WifiShopController::class, 'ticket'])->name('tickets.public');
Route::get('/ticket/{token}/pdf', [WifiShopController::class, 'pdf'])->name('tickets.pdf');
Route::post('/ticket/{token}/synchroniser', [WifiShopController::class, 'retrySync'])->middleware('throttle:10,1')->name('tickets.sync');

Route::middleware(['auth', 'tenant', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
    Route::get('/tenants', [PlatformController::class, 'tenants'])->name('tenants');
    Route::patch('/tenants/{tenant}', [PlatformController::class, 'updateTenant'])->name('tenants.update');
    Route::delete('/tenants/{tenant}', [PlatformController::class, 'destroyTenant'])->name('tenants.destroy');
    Route::get('/plans', [PlatformController::class, 'plans'])->name('plans');
    Route::patch('/plans/{saasPlan}', [PlatformController::class, 'updatePlan'])->name('plans.update');
    Route::get('/payments', [PlatformController::class, 'payments'])->name('payments');
    Route::post('/payments/providers/{provider}', [PlatformController::class, 'updateProvider'])->name('payments.providers');
    Route::get('/logs', [PlatformController::class, 'logs'])->name('logs');
    Route::get('/mikrotiks', [PlatformController::class, 'mikrotiks'])->name('mikrotiks');
    Route::get('/clients', [PlatformController::class, 'clients'])->name('clients');
    Route::get('/zones', [PlatformController::class, 'zones'])->name('zones');
    Route::get('/profiles', [PlatformController::class, 'profiles'])->name('profiles');
    Route::get('/users', [PlatformController::class, 'users'])->name('users');
    Route::get('/tickets', [PlatformController::class, 'tickets'])->name('tickets');
    Route::get('/sales', [PlatformController::class, 'sales'])->name('sales');
    Route::get('/reports', [PlatformController::class, 'reports'])->name('reports');
    Route::get('/settings', [PlatformController::class, 'settings'])->name('settings');
    Route::get('/production-check', [ProductionCheckController::class, 'show'])->name('production-check');
    Route::post('/production-check/mikrotiks/{mikrotik}/test', [ProductionCheckController::class, 'test'])->name('production-check.test');
    Route::post('/production-check/mikrotiks/{mikrotik}/test-user', [ProductionCheckController::class, 'createTestUser'])->name('production-check.test-user');
    Route::delete('/production-check/mikrotiks/{mikrotik}/test-user', [ProductionCheckController::class, 'deleteTestUser'])->name('production-check.test-user.delete');
    Route::post('/payments/{paymentId}/confirm', [PlatformController::class, 'confirmPayment'])->name('payments.confirm');
});

Route::middleware(['auth', 'tenant', 'tenant.active', 'role:entrepreneur,staff'])->group(function () {
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('/subscription', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::middleware('subscription')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::middleware('throttle:20,1')->group(function () {
            Route::get('/exports/sales.csv', [ExportController::class, 'sales'])->middleware('permission:sales.view')->name('exports.sales');
            Route::get('/exports/sales.pdf', [ExportController::class, 'salesPdf'])->middleware('permission:sales.view')->name('exports.sales.pdf');
            Route::get('/exports/customers.csv', [ExportController::class, 'customers'])->middleware('permission:customers.manage')->name('exports.customers');
            Route::get('/exports/tickets.csv', [ExportController::class, 'tickets'])->middleware('permission:vouchers.manage')->name('exports.tickets');
            Route::get('/exports/payments.csv', [ExportController::class, 'payments'])->middleware('permission:sales.view')->name('exports.payments');
        });
        Route::patch('/wifi-zones/{wifiZone}/status', [WifiZoneController::class, 'status'])->middleware('permission:zones.manage')->name('wifi-zones.status');
        Route::resource('wifi-zones', WifiZoneController::class)->except('show')->parameters(['wifi-zones' => 'wifiZone'])->middleware('permission:zones.manage');
        Route::middleware('permission:mikrotiks.manage')->group(function () {
            Route::get('/mikrotiks/assistant', [MikrotikAssistantController::class, 'index'])->name('mikrotiks.assistant');
            Route::post('/mikrotiks/assistant/start', [MikrotikAssistantController::class, 'start'])->name('mikrotiks.assistant.start');
            Route::post('/mikrotiks/assistant/step', [MikrotikAssistantController::class, 'step'])->name('mikrotiks.assistant.step');
            Route::post('/mikrotiks/assistant/test', [MikrotikAssistantController::class, 'test'])->name('mikrotiks.assistant.test');
            Route::post('/mikrotiks/assistant/continue', [MikrotikAssistantController::class, 'advance'])->name('mikrotiks.assistant.continue');
            Route::post('/mikrotiks/assistant/save', [MikrotikAssistantController::class, 'save'])->name('mikrotiks.assistant.save');
            Route::get('/mikrotiks/assistant/{mikrotik}', [MikrotikAssistantController::class, 'show'])->name('mikrotiks.assistant.show');
            Route::post('/mikrotiks/assistant/{mikrotik}/read', [MikrotikAssistantController::class, 'read'])->name('mikrotiks.assistant.read');
            Route::post('/mikrotiks/assistant/{mikrotik}/profile-preview', [MikrotikAssistantController::class, 'previewProfile'])->name('mikrotiks.assistant.profile-preview');
            Route::post('/mikrotiks/assistant/{mikrotik}/profile', [MikrotikAssistantController::class, 'createProfile'])->name('mikrotiks.assistant.profile');
            Route::post('/mikrotiks/assistant/{mikrotik}/retry', [MikrotikAssistantController::class, 'retry'])->name('mikrotiks.assistant.retry');
            Route::post('/mikrotiks/assistant/{mikrotik}/auto-sync', [MikrotikAssistantController::class, 'autoSync'])->name('mikrotiks.assistant.auto');
        });
        Route::post('/mikrotiks/probe', [MikrotikController::class, 'probe'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.probe');
        Route::post('/mikrotiks/{mikrotik}/test', [MikrotikController::class, 'test'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.test');
        Route::post('/mikrotiks/{mikrotik}/sync', [MikrotikController::class, 'sync'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.sync');
        Route::post('/mikrotiks/{mikrotik}/pending', [MikrotikController::class, 'syncPending'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.pending');
        Route::post('/mikrotiks/{mikrotik}/profiles', [MikrotikController::class, 'syncProfiles'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.profiles');
        Route::post('/mikrotiks/{mikrotik}/plan-profile', [MikrotikController::class, 'assignProfile'])->middleware('permission:plans.manage')->name('mikrotiks.plan-profile');
        Route::post('/mikrotiks/{mikrotik}/hotspot', [MikrotikController::class, 'selectHotspot'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.hotspot');
        Route::post('/mikrotiks/{mikrotik}/disconnect', [MikrotikController::class, 'disconnectSession'])->middleware('permission:sessions.disconnect')->name('mikrotiks.disconnect');
        Route::post('/mikrotiks/{mikrotik}/portal', [MikrotikController::class, 'applyPortal'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.portal');
        Route::post('/mikrotiks/{mikrotik}/prepare/read', [MikrotikController::class, 'prepareRead'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.prepare.read');
        Route::post('/mikrotiks/{mikrotik}/prepare/apply', [MikrotikController::class, 'prepareApply'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.prepare.apply');
        Route::post('/mikrotiks/{mikrotik}/prepare/verify', [MikrotikController::class, 'prepareVerify'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.prepare.verify');
        Route::post('/mikrotiks/{mikrotik}/prepare/dns', [MikrotikController::class, 'prepareDns'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.prepare.dns');
        Route::post('/mikrotiks/{mikrotik}/snapshots/{snapshot}/restore', [MikrotikController::class, 'restoreSnapshot'])->middleware('permission:mikrotiks.manage')->name('mikrotiks.snapshots.restore');
        Route::resource('mikrotiks', MikrotikController::class)->middleware('permission:mikrotiks.manage');
        Route::post('/plans/{plan}/duplicate', [PlanController::class, 'duplicate'])->middleware('permission:plans.manage')->name('plans.duplicate');
        Route::patch('/plans/{plan}/status', [PlanController::class, 'status'])->middleware('permission:plans.manage')->name('plans.status');
        Route::resource('plans', PlanController::class)->except('show')->middleware('permission:plans.manage');
        Route::get('/vouchers', [VoucherController::class, 'index'])->middleware('permission:vouchers.manage')->name('vouchers.index');
        Route::get('/vouchers/generate', [VoucherController::class, 'createBatch'])->middleware('permission:vouchers.manage')->name('vouchers.generate');
        Route::post('/vouchers/quick/preview', [VoucherController::class, 'quickPreview'])->middleware('permission:vouchers.manage')->name('vouchers.quick.preview');
        Route::post('/vouchers/quick/plan', [VoucherController::class, 'quickPlan'])->middleware('permission:plans.manage')->name('vouchers.quick.plan');
        Route::post('/vouchers/quick/link', [VoucherController::class, 'quickLink'])->middleware('permission:plans.manage')->name('vouchers.quick.link');
        Route::post('/vouchers/quick', [VoucherController::class, 'quickStore'])->middleware('permission:vouchers.manage')->name('vouchers.quick.store');
        Route::post('/vouchers/quick/express', [VoucherController::class, 'quickExpress'])->middleware('permission:vouchers.manage')->name('vouchers.quick.express');
        Route::post('/vouchers/quick/user', [VoucherController::class, 'quickUser'])->middleware('permission:vouchers.manage')->name('vouchers.quick.user');
        Route::get('/vouchers/quick/username', [VoucherController::class, 'quickUsername'])->middleware('permission:vouchers.manage')->name('vouchers.quick.username');
        Route::post('/vouchers/assist/preview', [VoucherController::class, 'assistPreview'])->middleware('permission:vouchers.manage')->name('vouchers.assist.preview');
        Route::post('/vouchers/assist', [VoucherController::class, 'assistStore'])->middleware('permission:vouchers.manage')->name('vouchers.assist.store');
        Route::get('/vouchers/assist/{voucher}', [VoucherController::class, 'assistShow'])->middleware('permission:vouchers.manage')->name('vouchers.assist.show');
        Route::get('/vouchers/generated', [VoucherController::class, 'generated'])->middleware('permission:vouchers.manage')->name('vouchers.generated');
        Route::post('/vouchers/bulk', [VoucherController::class, 'bulk'])->middleware('permission:vouchers.manage')->name('vouchers.bulk');
        Route::post('/vouchers/print', [VoucherController::class, 'printSheet'])->middleware('permission:vouchers.manage')->name('vouchers.print');
        Route::post('/vouchers/pdf-sheet', [VoucherController::class, 'pdfSheet'])->middleware('permission:vouchers.manage')->name('vouchers.sheet-pdf');
        Route::post('/vouchers', [VoucherController::class, 'store'])->middleware('permission:vouchers.manage')->name('vouchers.store');
        Route::post('/vouchers/{voucher}/sync', [VoucherController::class, 'sync'])->middleware('permission:vouchers.manage')->name('vouchers.sync');
        Route::post('/vouchers/{voucher}/retry-sync', [VoucherController::class, 'sync'])->middleware('permission:vouchers.manage')->name('vouchers.retry');
        Route::get('/vouchers/{voucher}', [VoucherController::class, 'show'])->middleware('permission:vouchers.manage')->name('vouchers.show');
        Route::get('/vouchers/{voucher}/pdf', [VoucherController::class, 'pdf'])->middleware('permission:vouchers.manage')->name('vouchers.pdf');
        Route::patch('/vouchers/{voucher}', [VoucherController::class, 'updateStatus'])->middleware('permission:vouchers.manage')->name('vouchers.status');
        Route::delete('/vouchers/{voucher}', [VoucherController::class, 'destroy'])->middleware('permission:vouchers.manage')->name('vouchers.destroy');
        Route::post('/vouchers/{voucher}/sell', [SaleController::class, 'sellVoucher'])->middleware('permission:sales.confirm')->name('vouchers.sell');
        Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.manage')->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.manage')->name('customers.show');
        Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:sales.view')->name('payments.index');
        Route::get('/reports', [ReportsController::class, 'index'])->middleware('permission:sales.view')->name('reports.index');
        Route::get('/sales/quick', [SaleController::class, 'quick'])->middleware('permission:sales.confirm')->name('sales.quick');
        Route::post('/sales/quick', [SaleController::class, 'storeQuick'])->middleware('permission:sales.confirm')->name('sales.quick.store');
        Route::get('/sales/quick/done', [SaleController::class, 'quickDone'])->middleware('permission:sales.confirm')->name('sales.quick.done');
        Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->middleware('permission:sales.view')->name('sales.show');
        Route::post('/sales/{sale}/confirm', [SaleController::class, 'confirm'])->middleware('permission:sales.confirm')->name('sales.confirm');
        Route::get('/active-users', [ActiveUserController::class, 'index'])->middleware('permission:sessions.view')->name('active-users.index');
        Route::post('/active-users/disconnect', [ActiveUserController::class, 'disconnect'])->middleware('permission:sessions.disconnect')->name('active-users.disconnect');
        Route::get('/sessions', [WifiSessionController::class, 'index'])->middleware('permission:sessions.view')->name('sessions.index');
        Route::get('/statistics', StatisticsController::class)->middleware('permission:statistics.view')->name('statistics');
        Route::get('/business', [SettingController::class, 'edit'])->middleware('permission:settings.manage')->name('business.edit');
        Route::put('/business', [SettingController::class, 'update'])->middleware('permission:settings.manage')->name('business.update');
        Route::get('/settings', [SettingController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
        Route::get('/staff', [StaffController::class, 'index'])->middleware('permission:staff.manage')->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->middleware('permission:staff.manage')->name('staff.store');

        Route::prefix('entrepreneur')->name('entrepreneur.')->group(function () {
            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('/business', [SettingController::class, 'edit'])->middleware('permission:settings.manage')->name('business');
            Route::get('/zones', [WifiZoneController::class, 'index'])->middleware('permission:zones.manage')->name('zones');
            Route::get('/mikrotik', [MikrotikController::class, 'index'])->middleware('permission:mikrotiks.manage')->name('mikrotik');
            Route::get('/profils', [HotspotProfileController::class, 'index'])->middleware('permission:plans.manage')->name('profiles');
            Route::get('/profils/nouveau', [HotspotProfileController::class, 'create'])->middleware('permission:plans.manage')->name('profiles.create');
            Route::post('/profils', [HotspotProfileController::class, 'store'])->middleware('permission:plans.manage')->name('profiles.store');
            Route::get('/profils/{plan}/edit', [HotspotProfileController::class, 'edit'])->middleware('permission:plans.manage')->name('profiles.edit');
            Route::put('/profils/{plan}', [HotspotProfileController::class, 'update'])->middleware('permission:plans.manage')->name('profiles.update');
            Route::get('/utilisateurs', [ActiveUserController::class, 'index'])->middleware('permission:sessions.view')->name('users');
            Route::get('/generer', [VoucherController::class, 'createBatch'])->middleware('permission:vouchers.manage')->name('generate');
            Route::get('/tickets', [VoucherController::class, 'index'])->middleware('permission:vouchers.manage')->name('tickets');
            Route::get('/ventes', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('sales');
            Route::get('/clients', [CustomerController::class, 'index'])->middleware('permission:customers.manage')->name('customers');
            Route::get('/rapports', [ReportsController::class, 'index'])->middleware('permission:sales.view')->name('reports');
            Route::get('/statistiques', StatisticsController::class)->middleware('permission:statistics.view')->name('statistics');
            Route::get('/parametres', [SettingController::class, 'edit'])->middleware('permission:settings.manage')->name('settings');
        });
    });
});
