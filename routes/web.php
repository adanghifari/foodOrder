<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontliner\Web\MenuController as FrontlinerMenuController;
use App\Http\Controllers\Frontliner\Web\QrScanController as FrontlinerQrScanController;

Route::redirect('/', '/frontliner');

Route::view('/frontliner', 'frontliner.welcome');

Route::prefix('backoffice')->group(function () {
    Route::view('/', 'backoffice.welcome');
    Route::view('/login', 'backoffice.auth.login');
    Route::view('/dashboard', 'backoffice.dashboard.index');
    Route::view('/daftar_menu', 'backoffice.menu.index');
    Route::view('/add_menu', 'backoffice.menu.create');
    Route::view('/daftar_pesanan', 'backoffice.order.index');
});

Route::get('/menu', [FrontlinerMenuController::class, 'semua']);
Route::get('/menu/semua', function () {
    return redirect('/menu');
});
Route::get('/menu/makanan-utama', [FrontlinerMenuController::class, 'makananUtama']);
Route::get('/menu/cemilan', [FrontlinerMenuController::class, 'cemilan']);
Route::get('/menu/minuman', [FrontlinerMenuController::class, 'minuman']);
Route::get('/menu/{tableId}', [FrontlinerQrScanController::class, 'accessFromMenuRoute'])
    ->whereNumber('tableId');
Route::get('/scan', [FrontlinerQrScanController::class, 'accessFromQueryParam']);
Route::get('/keranjang', function (Request $request) {
    return view('frontliner.keranjang', [
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
