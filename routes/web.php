<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backoffice\AuthController as BackofficeAuthController;
use App\Http\Controllers\Backoffice\Admin\DashboardController as BackofficeDashboardController;
use App\Http\Controllers\Backoffice\Admin\MenuController as BackofficeMenuController;
use App\Http\Controllers\Backoffice\Admin\OrderController as BackofficeOrderController;
use App\Http\Controllers\Backoffice\Admin\PaymentController as BackofficePaymentController;
use App\Http\Controllers\Backoffice\Admin\TableController as BackofficeTableController;
use App\Http\Controllers\Backoffice\Admin\UserController as BackofficeUserController;
use App\Http\Controllers\Frontliner\Web\MenuController as FrontlinerMenuController;
use App\Http\Controllers\Frontliner\Web\PaymentController as FrontlinerPaymentController;
use App\Http\Controllers\Frontliner\Web\QrScanController as FrontlinerQrScanController;

Route::redirect('/', '/frontliner');

Route::view('/frontliner', 'frontliner.welcome');

Route::prefix('backoffice')->group(function () {
    Route::get('/login', [BackofficeAuthController::class, 'showLogin']);
    Route::post('/login', [BackofficeAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [BackofficeAuthController::class, 'logout'])->middleware('backoffice.admin');

    Route::middleware('backoffice.admin')->group(function () {
        Route::get('/', function () {
            return redirect('/backoffice/dashboard');
        });
        Route::get('/dashboard', [BackofficeDashboardController::class, 'index']);
        Route::get('/daftar_menu', [BackofficeMenuController::class, 'indexPage']);
        Route::post('/daftar_menu', [BackofficeMenuController::class, 'storePage']);
        Route::get('/daftar_menu/{id}', [BackofficeMenuController::class, 'showPage']);
        Route::get('/daftar_menu/{id}/edit', [BackofficeMenuController::class, 'editPage']);
        Route::put('/daftar_menu/{id}', [BackofficeMenuController::class, 'updatePage']);
        Route::get('/add_menu', [BackofficeMenuController::class, 'createPage']);
        Route::get('/daftar_pesanan', [BackofficeOrderController::class, 'indexPage']);
        Route::patch('/daftar_pesanan/{id}/status', [BackofficeOrderController::class, 'updateStatusPage']);
        Route::get('/kelola_meja', [BackofficeTableController::class, 'indexPage']);
        Route::get('/daftar_meja', [BackofficeTableController::class, 'indexPage']);
        Route::patch('/kelola_meja/assign', [BackofficeTableController::class, 'assignPage']);
        Route::patch('/daftar_meja/assign', [BackofficeTableController::class, 'assignPage']);
        Route::get('/pembayaran', [BackofficePaymentController::class, 'indexPage']);
        Route::get('/pengguna', [BackofficeUserController::class, 'indexPage']);
    });
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
Route::post('/frontliner/pembayaran/create', [FrontlinerPaymentController::class, 'createFromCart'])
    ->middleware('throttle:20,1');
Route::get('/frontliner/pembayaran/selesai', [FrontlinerPaymentController::class, 'finishRedirect'])
    ->middleware('throttle:20,1');
Route::get('/frontliner/pembayaran/struk', [FrontlinerPaymentController::class, 'receipt'])
    ->middleware('throttle:30,1');


# buat debugging ngecek tableId di session pake dibawah ini ya ges, 
// Route::get('/debug/table-session', function (Request $request) {
//     return response()->json([
//         'table_id' => $request->session()->get('table_id'),
//         'session_all' => $request->session()->all(),
//     ]);
// });
