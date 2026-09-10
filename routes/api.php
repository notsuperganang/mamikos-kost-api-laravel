<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityInquiryController;
use App\Http\Controllers\Api\V1\KostController;
use App\Http\Controllers\Api\V1\Owner\KostController as OwnerKostController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('login');
        Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum')->name('me');
    });

    Route::get('kosts', [KostController::class, 'index'])->name('kosts.index');
    Route::get('kosts/{kost}', [KostController::class, 'show'])->name('kosts.show');

    Route::post('kosts/{kost}/availability-inquiries', [AvailabilityInquiryController::class, 'store'])
        ->middleware(['auth:sanctum', 'role:regular,premium'])
        ->name('kosts.availability-inquiries.store');

    Route::prefix('owner')->name('owner.')->middleware(['auth:sanctum', 'role:owner'])->group(function (): void {
        Route::apiResource('kosts', OwnerKostController::class)->except(['show']);
    });
});
