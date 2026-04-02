<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\CartController as CustomerCartController;
use App\Http\Controllers\Mobile\MenuController as MobileCustomerMenuController;
use App\Http\Controllers\Mobile\OrderController as CustomerOrderController;
use App\Http\Controllers\Web\TableController as WebCustomerTableController;
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
    Route::get('/', [MobileCustomerMenuController::class, 'list']);
    Route::get('/search', [MobileCustomerMenuController::class, 'search']);
    Route::get('/filter', [MobileCustomerMenuController::class, 'filter']);
    
    Route::middleware(['auth:api', 'role:ADMIN'])->group(function () {
        Route::post('/', [AdminMenuController::class, 'create']);
        Route::put('/{id}', [AdminMenuController::class, 'update']);
        Route::delete('/{id}', [AdminMenuController::class, 'remove']);
        Route::post('/upload-image/{id}', [AdminMenuController::class, 'uploadImage']);
        Route::delete('/{id}/image', [AdminMenuController::class, 'deleteImage']);
        Route::get('/count', [AdminMenuController::class, 'count']);
    });
});

Route::group(['prefix' => 'v1/cart', 'middleware' => ['auth:api', 'role:CUSTOMER']], function () {
    Route::post('/', [CustomerCartController::class, 'add']);
    Route::get('/', [CustomerCartController::class, 'get']);
    Route::delete('/', [CustomerCartController::class, 'remove']);
    Route::post('/checkout', [CustomerCartController::class, 'checkout'])->middleware('web');
});

Route::post('v1/table-session/clear', [WebCustomerTableController::class, 'clearTableSession'])
    ->middleware('web');

Route::get('v1/tables/{tableId}/availability', [WebCustomerTableController::class, 'checkTableAvailability'])
    ->whereNumber('tableId');

Route::group(['prefix' => 'v1/orders', 'middleware' => 'auth:api'], function () {
    // Admin routes
    Route::group(['middleware' => 'role:ADMIN'], function () {
        Route::get('/', [AdminOrderController::class, 'list']);
        Route::patch('/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::get('/count', [AdminOrderController::class, 'count']);
    });
    
    // Customer routes (Some might overlap, specifically creating directly)
    Route::group(['middleware' => 'role:CUSTOMER'], function () {
        Route::post('/', [CustomerOrderController::class, 'create']);
        Route::get('/me', [CustomerOrderController::class, 'myOrders']);
    });
});

Route::group(['prefix' => 'v1/payments'], function () {
    Route::get('/', [PaymentController::class, 'list']);
});

Route::group(['prefix' => 'v1/overview'], function () {
    Route::get('/', [OverviewController::class, 'get']);
});
