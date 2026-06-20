<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Http\Controllers\Controller;
use App\Domains\Auth\Services\AuthService;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(Request $request)
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users,username',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'name'     => 'required|string|max:255',
            'no_telp'  => 'required|string|max:20',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'data' => $validator->errors()], 422);
        }
        $user = $this->authService->registerCustomer($validator->validated());
        return response()->json(['status' => 'success', 'message' => 'Akun berhasil didaftarkan', 'data' => $this->authService->userPayload($user)], 201);
    }

    public function login(Request $request)
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'data' => $validator->errors()], 422);
        }
        $token = $this->authService->attemptLogin($request->only(['username', 'password']));
        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Username atau password salah'], 401);
        }
        return $this->respondWithToken($token);
    }

    public function me()
    {
        $user = $this->authService->currentUser();
        return response()->json(['status' => 'success', 'message' => 'Data pengguna saat ini', 'data' => $this->authService->userPayload($user)]);
    }

    public function logout()
    {
        $this->authService->logout();
        return response()->json(['status' => 'success', 'message' => 'Berhasil keluar', 'data' => null]);
    }

    public function refresh()
    {
        return $this->respondWithToken($this->authService->refreshToken());
    }

    protected function respondWithToken($token)
    {
        $user = $this->authService->currentUser();
        return response()->json(['status' => 'success', 'message' => 'Login berhasil', 'data' => ['token' => $token, 'user' => $this->authService->userPayload($user)]]);
    }

    public function updateProfile(Request $request)
    {
        $user = $this->authService->currentUser();
        if (!$user) return response()->json(['status' => 'error', 'message' => 'Tidak terautentikasi'], 401);
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $validator = Validator::make($request->all(), [
            'username'   => 'required|string|max:255|unique:users,username,' . $user->id . ',_id',
            'name'       => 'required|string|max:255',
            'no_telp'    => 'required|string|max:20',
            'avatar_url' => 'nullable|string|max:2048',
        ]);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'data' => $validator->errors()], 422);
        $validated = $validator->validated();
        $user->update(['username' => $validated['username'], 'name' => $validated['name'], 'no_telp' => $validated['no_telp']]);
        if (array_key_exists('avatar_url', $validated)) $user->update(['avatar_url' => $validated['avatar_url']]);
        return response()->json(['status' => 'success', 'message' => 'Profil berhasil diperbarui', 'data' => $this->authService->userPayload($user)]);
    }

    public function changePassword(Request $request)
    {
        $user = $this->authService->currentUser();
        if (!$user) return response()->json(['status' => 'error', 'message' => 'Tidak terautentikasi'], 401);
        $validator = Validator::make($request->all(), [
            'current_password'          => 'required|string|min:6',
            'new_password'              => 'required|string|min:6|different:current_password',
            'new_password_confirmation' => 'required|string|same:new_password',
        ]);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'data' => $validator->errors()], 422);
        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Password saat ini salah.'], 400);
        }
        $user->update(['password' => Hash::make($request->input('new_password'))]);
        return response()->json(['status' => 'success', 'message' => 'Password berhasil diubah.']);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|string|email|max:255']);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'data' => $validator->errors()], 422);

        $email = strtolower(trim((string) $request->input('email')));
        $user  = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['status' => 'success', 'message' => 'Jika email terdaftar, kode OTP akan dikirimkan ke email kamu.']);
        }

        PasswordResetToken::where('email', $email)->whereNull('used_at')->delete();

        $otp       = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(15);

        PasswordResetToken::create(['email' => $email, 'token' => $otp, 'expires_at' => $expiresAt]);

        try {
            $this->sendOtpViaResend(email: $email, userName: $user->name, otp: $otp, expiredAt: $expiresAt->setTimezone('Asia/Jakarta')->format('d M Y, H.i') . ' WIB');
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email OTP', ['email' => $email, 'error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Gagal mengirimkan email. Silakan coba lagi.'], 500);
        }

        return response()->json(['status' => 'success', 'message' => 'Kode OTP telah dikirimkan ke email kamu. Berlaku selama 15 menit.']);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|string|email|max:255', 'otp' => 'required|string|size:6']);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'data' => $validator->errors()], 422);

        $email  = strtolower(trim((string) $request->input('email')));
        $otp    = trim((string) $request->input('otp'));
        $record = PasswordResetToken::where('email', $email)->where('token', $otp)->whereNull('used_at')->latest()->first();

        if (!$record || $record->isExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Kode OTP tidak valid atau sudah kedaluwarsa.'], 400);
        }

        $resetToken = Str::random(64);
        $record->update(['token' => $resetToken, 'expires_at' => now()->addMinutes(10)]);

        return response()->json(['status' => 'success', 'message' => 'OTP valid. Silakan lanjutkan untuk membuat password baru.', 'data' => ['reset_token' => $resetToken]]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'                 => 'required|string|email|max:255',
            'reset_token'           => 'required|string',
            'password'              => 'required|string|min:6',
            'password_confirmation' => 'required|string|same:password',
        ]);
        if ($validator->fails()) return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'data' => $validator->errors()], 422);

        $email      = strtolower(trim((string) $request->input('email')));
        $resetToken = trim((string) $request->input('reset_token'));
        $record     = PasswordResetToken::where('email', $email)->where('token', $resetToken)->whereNull('used_at')->latest()->first();

        if (!$record || $record->isExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Token reset tidak valid atau sudah kedaluwarsa.'], 400);
        }

        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['status' => 'error', 'message' => 'Pengguna tidak ditemukan.'], 404);

        $user->update(['password' => Hash::make($request->input('password'))]);
        $record->update(['used_at' => now()]);

        return response()->json(['status' => 'success', 'message' => 'Password berhasil direset. Silakan login dengan password baru kamu.']);
    }

    private function sendOtpViaResend(string $email, string $userName, string $otp, string $expiredAt): void
    {
        $resendApiKey = trim((string) config('services.resend.api_key', ''));
        if ($resendApiKey === '') throw new \RuntimeException('Resend API key belum dikonfigurasi');

        $fromEmail = trim((string) config('mail.from.address', 'onboarding@resend.dev'));
        $fromName  = trim((string) config('mail.from.name', 'KedaiKlik'));

        $html = view('emails.password-reset', ['userName' => $userName, 'resetToken' => $otp, 'expiredAt' => $expiredAt])->render();

        $response = Http::withToken($resendApiKey)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.resend.com/emails', [
                'from'    => "$fromName <$fromEmail>",
                'to'      => [$email],
                'subject' => 'Reset Password - KedaiKlik',
                'html'    => $html,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Resend API gagal: HTTP ' . $response->status() . ' ' . $response->body());
        }
    }
}
