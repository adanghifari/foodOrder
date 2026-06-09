<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Domains\Chatbot\Services\ChatbotService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatbotSimulationController extends Controller
{
    public function __construct(
        private readonly ChatbotService $chatbotService
    ) {
    }

    public function indexPage()
    {
        return view('backoffice.chatbot.simulation');
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

        $adminUserId = $request->session()->get('backoffice_admin_user_id');
        $user = User::find($adminUserId);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin user not found in session.',
            ], 401);
        }

        $payload = $validator->validated();

        $response = $this->chatbotService->handleMessage(
            $user,
            (string) ($payload['message'] ?? ''),
            (string) ($payload['action'] ?? ''),
            'admin_simulation'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Chatbot response generated',
            'data' => $response,
        ]);
    }
}
