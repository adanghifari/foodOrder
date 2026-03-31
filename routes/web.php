<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MenuController;

Route::get('/menu/hidangan', [MenuController::class, 'hidangan']);
Route::get('/menu/cemilan', [MenuController::class, 'cemilan']);
Route::get('/menu/minuman', [MenuController::class, 'minuman']);
Route::get('/keranjang', function () {
    return view('keranjang');
});
