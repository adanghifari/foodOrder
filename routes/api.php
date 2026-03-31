<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\OverviewController;

Route::group(['prefix' => 'v1/auth'], function ($router) {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');
});

Route::group(['prefix' => 'v1/menus'], function ($router) {
    Route::get('/', [MenuController::class, 'list']);
    Route::get('/search', [MenuController::class, 'search']);
    Route::get('/filter', [MenuController::class, 'filter']);
    
    Route::middleware(['auth:api', 'role:ADMIN'])->group(function () {
        Route::post('/', [MenuController::class, 'create']);
        Route::put('/{id}', [MenuController::class, 'update']);
        Route::delete('/{id}', [MenuController::class, 'remove']);
        Route::post('/upload-image/{id}', [MenuController::class, 'uploadImage']);
        Route::delete('/{id}/image', [MenuController::class, 'deleteImage']);
        Route::get('/count', [MenuController::class, 'count']);
    });
});

Route::group(['prefix' => 'v1/cart', 'middleware' => ['auth:api', 'role:CUSTOMER']], function () {
    Route::post('/', [CartController::class, 'add']);
    Route::get('/', [CartController::class, 'get']);
    Route::delete('/', [CartController::class, 'remove']);
    Route::post('/checkout', [CartController::class, 'checkout']);
});

Route::group(['prefix' => 'v1/orders', 'middleware' => 'auth:api'], function () {
    // Admin routes
    Route::group(['middleware' => 'role:ADMIN'], function () {
        Route::get('/', [OrderController::class, 'list']);
        Route::patch('/{id}/status', [OrderController::class, 'updateStatus']);
        Route::get('/count', [OrderController::class, 'count']);
    });
    
    // Customer routes (Some might overlap, specifically creating directly)
    Route::group(['middleware' => 'role:CUSTOMER'], function () {
        Route::post('/', [OrderController::class, 'create']);
        Route::get('/me', [OrderController::class, 'myOrders']);
    });
});

Route::group(['prefix' => 'v1/payments'], function () {
    Route::get('/', [PaymentController::class, 'list']);
});

Route::group(['prefix' => 'v1/overview'], function () {
    Route::get('/', [OverviewController::class, 'get']);
});
