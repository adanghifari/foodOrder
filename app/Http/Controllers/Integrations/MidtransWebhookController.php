<?php

namespace App\Http\Controllers\Integrations;

use App\Domains\Payment\Services\PaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function handle(Request $request)
    {
        $result = $this->paymentService->processWebhook($request->all());

        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], (int) ($result['status'] ?? 500));
    }

    public function landing(Request $request)
    {
        return redirect('/kedai/pembayaran/selesai?' . http_build_query($request->query()));
    }
}
