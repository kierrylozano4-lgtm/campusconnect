<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.v1.login');

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('logout');

        Route::get('/user', [AuthController::class, 'user'])
            ->name('user');

        Route::middleware('abilities:services:read')->group(function (): void {
            Route::get('/services', [ServiceController::class, 'index'])
                ->name('services.index');
            Route::get('/services/{service}', [ServiceController::class, 'show'])
                ->name('services.show');
        });

        Route::middleware('abilities:services:write')->group(function (): void {
            Route::post('/services', [ServiceController::class, 'store'])
                ->name('services.store');
            Route::put('/services/{service}', [ServiceController::class, 'update'])
                ->name('services.update');
            Route::patch('/services/{service}', [ServiceController::class, 'update'])
                ->name('services.patch');
            Route::delete('/services/{service}', [ServiceController::class, 'destroy'])
                ->name('services.destroy');
        });
    });
