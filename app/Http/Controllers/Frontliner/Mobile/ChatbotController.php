<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Domains\Chatbot\Services\ChatbotService;
use App\Domains\Chatbot\Services\GeminiNluService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly ChatbotService $chatbotService,
        private readonly GeminiNluService $geminiNluService
    )
    {
    }

    public function message(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string|max:500',
            'action' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $payload = $validator->validated();

        $response = $this->chatbotService->handleMessage(
            $user,
            (string) ($payload['message'] ?? ''),
            (string) ($payload['action'] ?? '')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Chatbot response generated',
            'data' => $response,
        ]);
    }

    public function messageDebug(Request $request)
    {
        if (app()->environment('production')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string|max:500',
            'action' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $message = (string) ($payload['message'] ?? '');
        $action = (string) ($payload['action'] ?? '');

        $ruleBased = app(\App\Domains\Chatbot\Services\ChatbotIntentService::class)->detect($message, $action);
        $aiFallback = app(\App\Domains\Chatbot\Services\GeminiNluService::class)->detectIntent($message);
        $response = $this->chatbotService->handleMessage($request->user(), $message, $action);

        return response()->json([
            'status' => 'success',
            'message' => 'Chatbot debug generated',
            'data' => [
                'rule_based' => $ruleBased,
                'gemini' => $aiFallback,
                'resolved_response' => $response,
            ],
        ]);
    }

    public function health(Request $request)
    {
        if (app()->environment('production')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 404);
        }

        $configured = trim((string) config('services.gemini.api_key')) !== '';
        $model = trim((string) config('services.gemini.model', ''));
        $runInference = $request->boolean('run_inference', false);

        $result = null;
        if ($configured && $runInference) {
            $probeMessage = trim((string) $request->query('probe', 'yang pedes murah ada ga?'));
            $startedAt = microtime(true);
            $ai = $this->geminiNluService->detectIntent($probeMessage);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $result = [
                'ok' => is_array($ai),
                'latency_ms' => $latencyMs,
                'probe' => $probeMessage,
                'response' => $ai,
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Chatbot health checked',
            'data' => [
                'gemini_configured' => $configured,
                'gemini_model' => $model,
                'run_inference' => $runInference,
                'inference_result' => $result,
            ],
        ]);
    }
}
