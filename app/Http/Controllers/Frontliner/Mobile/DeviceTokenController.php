<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|min:20|max:4096',
            'platform' => 'required|string|in:android,ios',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $token = trim((string) $request->input('token'));
        $platform = strtolower(trim((string) $request->input('platform')));
        $userId = (string) $user->getAuthIdentifier();

        DeviceToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $userId,
                'platform' => $platform,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Device token registered',
            'data' => null,
        ]);
    }
}

