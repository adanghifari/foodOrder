<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->get('backoffice_is_admin', false) === true) {
            return redirect('/backoffice/dashboard');
        }

        return view('backoffice.auth.login');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
				'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $username = strtolower(trim((string) $request->input('username')));
        $password = (string) $request->input('password');

        // Rate Limiting Key: username + IP address
        $throttleKey = Str::transliterate($username . '|' . $request->ip());

        // Proteksi lockout: batasi maksimal 5 kali percobaan salah
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'status' => 'error',
                'message' => 'Terlalu banyak percobaan login yang gagal. Silakan coba lagi dalam ' . $seconds . ' detik.',
            ], 429);
        }

        $user = User::where('username', $username)->first();

        if (!$user || !Hash::check($password, (string) $user->password)) {
            // Catat kegagalan (kunci selama 60 detik jika melebihi limit)
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'status' => 'error',
                'message' => 'Username atau password tidak valid.',
            ], 401);
        }

        if (strtoupper((string) $user->role) !== 'ADMIN') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun ini bukan akun admin backoffice.',
            ], 403);
        }

        // Bersihkan limiter jika berhasil
        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();
        $request->session()->put('backoffice_is_admin', true);
        $request->session()->put('backoffice_admin_user_id', (string) $user->_id);
        $request->session()->put('backoffice_admin_name', (string) ($user->name ?? 'Administrator'));

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil.',
            'data' => [
                'name' => (string) ($user->name ?? 'Administrator'),
                'role' => (string) $user->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil.',
        ]);
    }
}
