<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Domains\Payment\Services\PaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function list()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Payment list retrieved',
            'data' => $this->paymentService->listPayments(),
        ]);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $result = $this->paymentService->createTransaction((string) $request->input('order_id'));

        return response()->json([
            'status' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], (int) ($result['status'] ?? 500));
    }
}
