<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/menu', [\App\Http\Controllers\Web\MenuController::class, 'semua']);
Route::get('/menu/semua', function () {
    return redirect('/menu');
});
Route::get('/menu/makanan-utama', [\App\Http\Controllers\Web\MenuController::class, 'makananUtama']);
Route::get('/menu/cemilan', [\App\Http\Controllers\Web\MenuController::class, 'cemilan']);
Route::get('/menu/minuman', [\App\Http\Controllers\Web\MenuController::class, 'minuman']);
Route::get('/menu/{tableId}', [\App\Http\Controllers\Web\QrScanController::class, 'accessFromMenuRoute'])
    ->whereNumber('tableId');
Route::get('/scan', [\App\Http\Controllers\Web\QrScanController::class, 'accessFromQueryParam']);
Route::get('/keranjang', function (Request $request) {
    return view('keranjang', [
        'tableNumber' => $request->session()->get('table_id'),
    ]);
});


# buat debugging ngecek tableId di session pake dibawah ini ya ges, 
// Route::get('/debug/table-session', function (Request $request) {
//     return response()->json([
//         'table_id' => $request->session()->get('table_id'),
//         'session_all' => $request->session()->all(),
//     ]);
// });
