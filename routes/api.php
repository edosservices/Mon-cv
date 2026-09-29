<?php

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [ApiController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'tenant', 'tenant.active', 'subscription', 'api.feature'])->group(function () {
        Route::get('/dashboard', [ApiController::class, 'dashboard']);
        Route::get('/wifi-zones', [ApiController::class, 'zones']);
        Route::post('/wifi-zones', [ApiController::class, 'storeZone']);
        Route::get('/mikrotiks', [ApiController::class, 'mikrotiks']);
        Route::post('/mikrotiks', [ApiController::class, 'storeMikrotik']);
        Route::post('/mikrotiks/test', [ApiController::class, 'testMikrotik']);
        Route::get('/mikrotiks/{mikrotik}/active-users', [ApiController::class, 'activeUsers']);
        Route::post('/mikrotiks/{mikrotik}/disconnect-user', [ApiController::class, 'disconnect']);
        Route::get('/vouchers', [ApiController::class, 'vouchers']);
        Route::post('/vouchers', [ApiController::class, 'storeVoucher']);
        Route::post('/vouchers/bulk', [ApiController::class, 'bulkVouchers']);
        Route::get('/plans', [ApiController::class, 'plans']);
        Route::post('/plans', [ApiController::class, 'storePlan']);
        Route::get('/sales', [ApiController::class, 'sales']);
        Route::get('/statistics', [ApiController::class, 'statistics']);
    });
});
