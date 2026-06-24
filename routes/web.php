<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backoffice\AuthController as BackofficeAuthController;
use App\Http\Controllers\Backoffice\Admin\DashboardController as BackofficeDashboardController;
use App\Http\Controllers\Backoffice\Admin\OverviewController as BackofficeOverviewController;
use App\Http\Controllers\Backoffice\Admin\MenuController as BackofficeMenuController;
use App\Http\Controllers\Backoffice\Admin\OrderController as BackofficeOrderController;
use App\Http\Controllers\Backoffice\Admin\PaymentController as BackofficePaymentController;
use App\Http\Controllers\Backoffice\Admin\ChatbotAnalyticsController as BackofficeChatbotAnalyticsController;
use App\Http\Controllers\Backoffice\Admin\ChatbotSimulationController as BackofficeChatbotSimulationController;
use App\Http\Controllers\Backoffice\Admin\BookingController as BackofficeBookingController;
use App\Http\Controllers\Backoffice\Admin\UserController as BackofficeUserController;
use App\Http\Controllers\Backoffice\Admin\TableController as BackofficeTableController;
use App\Http\Controllers\Frontliner\Web\MenuController as FrontlinerMenuController;
use App\Http\Controllers\Frontliner\Web\PaymentController as FrontlinerPaymentController;
use App\Http\Controllers\Frontliner\Web\QrScanController as FrontlinerQrScanController;


Route::redirect('/', '/kedai');

Route::redirect('/frontliner', '/kedai');
Route::view('/kedai', 'frontliner.welcome');

Route::prefix('backoffice')->group(function () {
    Route::view('/', 'backoffice.welcome');
    Route::get('/login', [BackofficeAuthController::class, 'showLogin']);
    Route::post('/login', [BackofficeAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [BackofficeAuthController::class, 'logout'])->middleware('backoffice.admin');

    Route::middleware('backoffice.admin')->group(function () {
        Route::get('/dashboard', [BackofficeDashboardController::class, 'index']);
        Route::get('/overview', [BackofficeOverviewController::class, 'indexPage']);
        Route::get('/overview/export-pdf', [BackofficeOverviewController::class, 'exportPdf']);
        Route::get('/daftar_menu', [BackofficeMenuController::class, 'indexPage']);
        Route::post('/daftar_menu', [BackofficeMenuController::class, 'storePage']);
        Route::get('/daftar_menu/{id}', [BackofficeMenuController::class, 'showPage']);
        Route::get('/daftar_menu/{id}/edit', [BackofficeMenuController::class, 'editPage']);
        Route::put('/daftar_menu/{id}', [BackofficeMenuController::class, 'updatePage']);
        Route::delete('/daftar_menu/{id}', [BackofficeMenuController::class, 'deletePage']);
        Route::get('/add_menu', [BackofficeMenuController::class, 'createPage']);
        Route::get('/daftar_pesanan', [BackofficeOrderController::class, 'indexPage']);
        Route::patch('/daftar_pesanan/{id}/status', [BackofficeOrderController::class, 'updateStatusPage']);
        Route::get('/booking', [BackofficeBookingController::class, 'indexPage']);
        Route::patch('/booking/{id}/status', [BackofficeBookingController::class, 'updateStatusPage']);
        Route::get('/pembayaran', [BackofficePaymentController::class, 'indexPage']);
        Route::get('/chatbot-analytics', [BackofficeChatbotAnalyticsController::class, 'indexPage']);
        Route::get('/chatbot-simulation', [BackofficeChatbotSimulationController::class, 'indexPage']);
        Route::post('/chatbot-simulation/message', [BackofficeChatbotSimulationController::class, 'message']);
        Route::delete('/pembayaran/{id}', [BackofficePaymentController::class, 'delete']);
        Route::get('/pengguna', [BackofficeUserController::class, 'indexPage']);
        Route::delete('/pengguna/{id}', [BackofficeUserController::class, 'deletePage']);
        Route::get('/kelola_meja', [BackofficeTableController::class, 'indexPage']);
        Route::get('/daftar_meja', [BackofficeTableController::class, 'indexPage']);
        Route::patch('/kelola_meja/assign', [BackofficeTableController::class, 'assignPage']);
        Route::patch('/daftar_meja/assign', [BackofficeTableController::class, 'assignPage']);
        Route::patch('/kelola_meja/{tableId}/clear', [BackofficeTableController::class, 'clearPage'])->whereNumber('tableId');
        Route::patch('/daftar_meja/{tableId}/clear', [BackofficeTableController::class, 'clearPage'])->whereNumber('tableId');
    });
});

Route::get('/menu', [FrontlinerMenuController::class, 'semua']);
Route::get('/menu/semua', function () {
    return redirect('/menu');
});
Route::get('/menu/makanan-utama', [FrontlinerMenuController::class, 'makananUtama']);
Route::get('/menu/cemilan', [FrontlinerMenuController::class, 'cemilan']);
Route::get('/menu/minuman', [FrontlinerMenuController::class, 'minuman']);
Route::get('/menu/take_away', [FrontlinerQrScanController::class, 'accessTakeAwayRoute']);
Route::get('/menu/{tableId}', [FrontlinerQrScanController::class, 'accessFromMenuRoute'])
    ->whereNumber('tableId');
Route::get('/scan', [FrontlinerQrScanController::class, 'accessFromQueryParam']);
Route::view('/kedai/scan', 'frontliner.scan');
Route::get('/keranjang', function (Request $request) {
    $orderType = strtoupper((string) $request->session()->get('order_type', ''));
    $isTakeAway = $orderType === 'TAKE_AWAY';
    $tableNumber = $request->session()->get('table_id');

    return view('frontliner.keranjang', [
        'tableNumber' => $tableNumber,
        'orderType' => $isTakeAway ? 'take_away' : 'dine_in',
        'tableLabel' => $isTakeAway
            ? 'Pesan & ambil'
            : ($tableNumber ? (string) $tableNumber : '-'),
    ]);
});
Route::post('/kedai/pembayaran/create', [FrontlinerPaymentController::class, 'createFromCart'])
    ->middleware('throttle:20,1');
Route::get('/kedai/pembayaran/{id}/pilih-metode', [FrontlinerPaymentController::class, 'resumePendingPayment'])
    ->middleware('throttle:20,1');
Route::post('/kedai/pembayaran/{id}/batalkan', [FrontlinerPaymentController::class, 'cancelPendingPayment'])
    ->middleware('throttle:20,1');
Route::get('/kedai/pembayaran/selesai', [FrontlinerPaymentController::class, 'finishRedirect'])
    ->middleware('throttle:20,1');
Route::get('/kedai/pembayaran/struk', [FrontlinerPaymentController::class, 'receipt'])
    ->middleware('throttle:30,1');
Route::get('/kedai/pembayaran/struk/email-link/{id}', [FrontlinerPaymentController::class, 'receiptFromEmailLink'])
    ->name('frontliner.receipt.email-link')
    ->middleware(['signed', 'throttle:30,1']);
Route::get('/kedai/pembayaran/struk/download', [FrontlinerPaymentController::class, 'downloadReceiptPdf'])
    ->middleware('throttle:20,1');

Route::get('/test-mail', function (Request $request) {
    if ((string) $request->query('key', '') !== (string) env('MAIL_TEST_KEY', '')) {
        return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        Log::info('TEST MAIL START');

        Mail::raw('hello', function ($msg) {
            $msg->to('emailkamu@gmail.com')
                ->subject('Railway Test');
        });

        Log::info('TEST MAIL SUCCESS');
        return response()->json(['ok' => true, 'message' => 'Mail sent']);
    } catch (\Throwable $e) {
        Log::error('TEST MAIL ERROR', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
})->middleware('throttle:5,1');

Route::get('/test-mail-debug', function (Request $request) {
    return [
        'query' => $request->query('key'),
        'env' => env('MAIL_TEST_KEY'),
    ];
});

Route::get('/debug-midtrans', function () {
    $serverKey = (string) config('services.midtrans.server_key');
    $clientKey = (string) config('services.midtrans.client_key');
    $merchantId = (string) config('services.midtrans.merchant_id');
    $callbackUrl = (string) config('services.midtrans.callback_url');
    $isProd = config('services.midtrans.is_production');

    return response()->json([
        'server_key' => [
            'configured' => $serverKey !== '',
            'length' => strlen($serverKey),
            'prefix' => substr($serverKey, 0, 11),
            'suffix' => substr($serverKey, -4),
        ],
        'client_key' => [
            'configured' => $clientKey !== '',
            'length' => strlen($clientKey),
            'prefix' => substr($clientKey, 0, 11),
            'suffix' => substr($clientKey, -4),
        ],
        'merchant_id' => [
            'configured' => $merchantId !== '',
            'value' => $merchantId,
        ],
        'callback_url' => [
            'configured' => $callbackUrl !== '',
            'value' => $callbackUrl,
        ],
        'is_production' => $isProd,
        'app_url' => config('app.url'),
    ]);
});



# buat debugging ngecek tableId di session pake dibawah ini ya ges, 
// Route::get('/debug/table-session', function (Request $request) {
//     return response()->json([
//         'table_id' => $request->session()->get('table_id'),
//         'session_all' => $request->session()->all(),
//     ]);
// });
