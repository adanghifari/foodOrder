<?php

use Illuminate\Support\Facades\Route;

Route::get('/menu/hidangan', [\App\Http\Controllers\Web\Customer\MenuController::class, 'hidangan']);
Route::get('/menu/cemilan', [\App\Http\Controllers\Web\Customer\MenuController::class, 'cemilan']);
Route::get('/menu/minuman', [\App\Http\Controllers\Web\Customer\MenuController::class, 'minuman']);
Route::get('/menu/{tableId}', [\App\Http\Controllers\Web\Customer\QrScanController::class, 'accessFromMenuRoute'])
    ->whereNumber('tableId');
Route::get('/scan', [\App\Http\Controllers\Web\Customer\QrScanController::class, 'accessFromQueryParam']);
Route::get('/keranjang', function () {
    return view('keranjang');
});
