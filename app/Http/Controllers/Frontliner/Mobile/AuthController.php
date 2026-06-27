<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Http\Controllers\Controller;
use App\Domains\Auth\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\PasswordResetOtp;
use Illuminate\Support\Facades\RateLimiter;


class AuthController extends Controller
{
	public function __construct(private readonly AuthService $authService)
	{
	}

	public function register(Request $request)
	{
		$request->merge([
			'username' => strtolower(trim((string) $request->input('username'))),
		]);

		$validator = Validator::make($request->all(), [
			'username' => 'required|string|max:255|unique:users,username',
			'email'    => 'required|string|email|max:255|unique:users,email',
			'name'     => 'required|string|max:255',
			'no_telp'  => 'required|string|max:20',
			'password' => 'required|string|min:6',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$user = $this->authService->registerCustomer($validator->validated());

		return response()->json([
			'status' => 'success',
			'message' => 'Customer registered',
			'data' => $this->authService->userPayload($user)
		], 201);
	}

	public function login(Request $request)
	{
		$request->merge([
			'username' => strtolower(trim((string) $request->input('username'))),
		]);

		$validator = Validator::make($request->all(), [
			'username' => 'required|string',
			'password' => 'required|string|min:6',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		// Rate Limiting Key: username + IP address
		$throttleKey = Str::transliterate(strtolower($request->input('username')) . '|' . $request->ip());

		$limiter = app(\App\Domains\Auth\Services\LoginRateLimiter::class);

		// Proteksi lockout: batasi maksimal 5 kali percobaan salah
		if ($limiter->tooManyAttempts($throttleKey, 5)) {
			$seconds = $limiter->availableIn($throttleKey);

			return response()->json([
				'status' => 'error',
				'message' => 'Terlalu banyak percobaan login yang gagal. Silakan coba lagi dalam ' . $seconds . ' detik.'
			], 429);
		}

		$credentials = $request->only(['username', 'password']);
		$token = $this->authService->attemptLogin($credentials);

		if (! $token) {
			// Tambahkan hit kegagalan (kunci selama 60 detik jika mencapai limit)
			$limiter->hit($throttleKey, 60);

			return response()->json([
				'status' => 'error',
				'message' => 'Invalid username or password'
			], 401);
		}

		// Bersihkan limiter jika login berhasil
		$limiter->clear($throttleKey);

		return $this->respondWithToken($token);
	}

	public function me()
	{
		$user = $this->authService->currentUser();

		return response()->json([
			'status' => 'success',
			'message' => 'Current user',
			'data' => $this->authService->userPayload($user)
		]);
	}

	public function logout()
	{
		$this->authService->logout();

		return response()->json([
			'status' => 'success',
			'message' => 'Successfully logged out',
			'data' => null,
		]);
	}

	public function refresh()
	{
		return $this->respondWithToken($this->authService->refreshToken());
	}

	protected function respondWithToken($token)
	{
		$user = $this->authService->currentUser();

		return response()->json([
			'status' => 'success',
			'message' => 'Login successful',
			'data' => [
				'token' => $token,
				'user' => $this->authService->userPayload($user)
			]
		]);
	}

	public function updateProfile(Request $request)
	{
		$user = $this->authService->currentUser();
		if (!$user) {
			return response()->json([
				'status' => 'error',
				'message' => 'Unauthorized'
			], 401);
		}

		$request->merge([
			'username' => strtolower(trim((string) $request->input('username'))),
		]);

		$validator = Validator::make($request->all(), [
			'username' => 'required|string|max:255|unique:users,username,' . $user->id . ',_id',
			'email'    => 'required|string|email|max:255|unique:users,email,' . $user->id . ',_id',
			'name'     => 'required|string|max:255',
			'no_telp'  => 'required|string|max:20',
			'avatar_url' => 'nullable|string|max:2048',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		$validated = $validator->validated();
		$user->update([
			'username' => $validated['username'],
			'email' => $validated['email'],
			'name' => $validated['name'],
			'no_telp' => $validated['no_telp'],
		]);

		if (array_key_exists('avatar_url', $validated)) {
			$avatarUrl = $validated['avatar_url'];
			if ($avatarUrl) {
				$parsed = parse_url($avatarUrl);
				if (isset($parsed['path'])) {
					$storagePos = strpos($parsed['path'], '/storage/');
					if ($storagePos !== false) {
						$avatarUrl = substr($parsed['path'], $storagePos);
					}
				}
			}
			$user->update(['avatar_url' => $avatarUrl]);
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Profile updated successfully',
			'data' => $this->authService->userPayload($user)
		]);
	}

	public function changePassword(Request $request)
	{
		$user = $this->authService->currentUser();
		if (!$user) {
			return response()->json([
				'status' => 'error',
				'message' => 'Unauthorized'
			], 401);
		}

		$validator = Validator::make($request->all(), [
			'current_password' => 'required|string|min:6',
			'new_password'     => 'required|string|min:6|different:current_password',
			'new_password_confirmation' => 'required|string|same:new_password',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		if (!Hash::check($request->input('current_password'), $user->password)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Password sekarang yang Anda masukkan salah.'
			], 400);
		}

		$user->update([
			'password' => Hash::make($request->input('new_password')),
		]);

		return response()->json([
			'status' => 'success',
			'message' => 'Password berhasil diubah.'
		]);
	}

	public function uploadAvatar(Request $request)
	{
		$user = $this->authService->currentUser();
		if (!$user) {
			return response()->json([
				'status' => 'error',
				'message' => 'Unauthorized'
			], 401);
		}

		$validator = Validator::make($request->all(), [
			'avatar' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validation error',
				'data' => $validator->errors()
			], 422);
		}

		if ($request->hasFile('avatar')) {
			$file = $request->file('avatar');

			// Delete old avatar if exists
			if ($user->avatar_url) {
				if ($this->isCloudinaryUrl($user->avatar_url)) {
					try {
						$this->deleteFromCloudinary($user->avatar_url);
					} catch (\Exception $e) {
						\Illuminate\Support\Facades\Log::warning('Gagal menghapus avatar lama dari Cloudinary: ' . $e->getMessage());
					}
				} else {
					$oldPath = $user->avatar_url;
					$storagePos = strpos($oldPath, '/storage/');
					if ($storagePos !== false) {
						$oldPath = substr($oldPath, $storagePos);
					}
					if (str_starts_with($oldPath, '/storage/avatars/')) {
						$relativePath = ltrim(str_replace('/storage/', '', $oldPath), '/');
						\Illuminate\Support\Facades\Storage::disk('public')->delete($relativePath);
					}
				}
			}

			// Upload new avatar file
			try {
				if ($this->isCloudinaryConfigured()) {
					$avatarUrl = $this->uploadToCloudinary($file);
				} else {
					$path = $file->store('avatars', 'public');
					$avatarUrl = '/storage/' . $path;
				}

				// Update user
				$user->update(['avatar_url' => $avatarUrl]);

				return response()->json([
					'status' => 'success',
					'message' => 'Avatar uploaded successfully',
					'data' => $this->authService->userPayload($user)
				]);
			} catch (\Exception $e) {
				\Illuminate\Support\Facades\Log::error('Upload avatar gagal: ' . $e->getMessage());
				return response()->json([
					'status' => 'error',
					'message' => 'Gagal mengunggah foto profil: ' . $e->getMessage()
				], 500);
			}
		}

		return response()->json([
			'status' => 'error',
			'message' => 'Avatar upload failed'
		], 500);
	}

	private function isCloudinaryConfigured(): bool
	{
		return !empty(config('services.cloudinary.cloud_name'))
			&& !empty(config('services.cloudinary.api_key'))
			&& !empty(config('services.cloudinary.api_secret'));
	}

	private function uploadToCloudinary(\Illuminate\Http\UploadedFile $image): string
	{
		$cloudName = (string) config('services.cloudinary.cloud_name');
		$apiKey = (string) config('services.cloudinary.api_key');
		$apiSecret = (string) config('services.cloudinary.api_secret');
		$folder = (string) config('services.cloudinary.folder', 'kedaiklik/avatars');
		
		// Ensure folder target includes avatars directory
		$folder = str_replace('menu', 'avatars', $folder);
		if (!str_contains($folder, 'avatars')) {
			$folder = 'kedaiklik/avatars';
		}
		$timestamp = time();

		$paramsToSign = [
			'folder' => $folder,
			'timestamp' => $timestamp,
		];

		$signature = $this->generateCloudinarySignature($paramsToSign, $apiSecret);

		$response = \Illuminate\Support\Facades\Http::timeout(30)
			->asMultipart()
			->attach('file', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
			->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
				'api_key' => $apiKey,
				'timestamp' => $timestamp,
				'folder' => $folder,
				'signature' => $signature,
			]);

		if (!$response->successful()) {
			throw new \RuntimeException('Upload gambar ke Cloudinary gagal: ' . $response->body());
		}

		$secureUrl = (string) $response->json('secure_url', '');
		if ($secureUrl === '') {
			throw new \RuntimeException('Upload gambar ke Cloudinary gagal: secure_url kosong.');
		}

		return $secureUrl;
	}

	private function isCloudinaryUrl(string $imageUrl): bool
	{
		return str_contains($imageUrl, 'res.cloudinary.com');
	}

	private function deleteFromCloudinary(string $imageUrl): void
	{
		if (!$this->isCloudinaryConfigured()) {
			return;
		}

		$publicId = $this->extractCloudinaryPublicId($imageUrl);
		if ($publicId === null) {
			return;
		}

		$cloudName = (string) config('services.cloudinary.cloud_name');
		$apiKey = (string) config('services.cloudinary.api_key');
		$apiSecret = (string) config('services.cloudinary.api_secret');
		$timestamp = time();

		$paramsToSign = [
			'public_id' => $publicId,
			'timestamp' => $timestamp,
		];

		$signature = $this->generateCloudinarySignature($paramsToSign, $apiSecret);

		$response = \Illuminate\Support\Facades\Http::timeout(20)
			->asForm()
			->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
				'api_key' => $apiKey,
				'timestamp' => $timestamp,
				'public_id' => $publicId,
				'signature' => $signature,
			]);

		if (!$response->successful()) {
			\Illuminate\Support\Facades\Log::warning('Cloudinary avatar image destroy failed', [
				'public_id' => $publicId,
				'status' => $response->status(),
				'body' => $response->body(),
			]);
		}
	}

	private function extractCloudinaryPublicId(string $imageUrl): ?string
	{
		$path = parse_url($imageUrl, PHP_URL_PATH);
		if (!is_string($path) || $path === '') {
			return null;
		}

		$marker = '/image/upload/';
		$pos = strpos($path, $marker);
		if ($pos === false) {
			return null;
		}

		$resource = substr($path, $pos + strlen($marker));
		if ($resource === '') {
			return null;
		}

		$parts = explode('/', $resource);
		if (!empty($parts) && preg_match('/^v\d+$/', $parts[0])) {
			array_shift($parts);
		}

		$publicPath = implode('/', $parts);
		if ($publicPath === '') {
			return null;
		}

		$publicPath = rawurldecode($publicPath);
		return preg_replace('/\.[^.]+$/', '', $publicPath) ?: null;
	}

	private function generateCloudinarySignature(array $params, string $apiSecret): string
	{
		ksort($params);

		$parts = [];
		foreach ($params as $key => $value) {
			$parts[] = $key . '=' . $value;
		}

		return sha1(implode('&', $parts) . $apiSecret);
	}

	public function forgotPassword(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => 'required|string|email|max:255',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Format email tidak valid.',
				'data' => $validator->errors()
			], 422);
		}

		$email = strtolower(trim((string) $request->input('email')));

		// Cek apakah user dengan email tersebut terdaftar
		$user = User::where('email', $email)->first();
		if (!$user) {
			return response()->json([
				'status' => 'error',
				'message' => 'Email tidak terdaftar.'
			], 404);
		}

		// Cek apakah role-nya customer
		if (strtoupper($user->role ?? '') !== 'CUSTOMER') {
			return response()->json([
				'status' => 'error',
				'message' => 'Hanya akun Customer yang dapat mereset password melalui aplikasi mobile.'
			], 403);
		}

		// Generate 6 digit OTP acak
		$otp = (string) rand(100000, 999999);

		// Simpan atau perbarui OTP di DB
		PasswordResetOtp::updateOrCreate(
			['email' => $email],
			[
				'otp' => $otp,
				'token' => null, // Reset token sebelumnya
				'expired_at' => now()->addMinutes(15),
				'is_verified' => false,
			]
		);

		// Kirim email
		try {
			$this->sendOtpEmailViaSendgrid($email, $otp, $user->username);
		} catch (\Exception $e) {
			\Illuminate\Support\Facades\Log::error('Gagal mengirim email OTP: ' . $e->getMessage());
			return response()->json([
				'status' => 'error',
				'message' => 'Gagal mengirimkan email OTP. Silakan coba beberapa saat lagi.'
			], 500);
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Kode OTP berhasil dikirim ke email Anda.'
		]);
	}

	public function verifyOtp(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => 'required|string|email|max:255',
			'otp'   => 'required|string|min:6|max:6',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validasi gagal.',
				'data' => $validator->errors()
			], 422);
		}

		$email = strtolower(trim((string) $request->input('email')));
		$otp = trim((string) $request->input('otp'));

		$resetRecord = PasswordResetOtp::where('email', $email)->first();

		if (!$resetRecord || $resetRecord->otp !== $otp) {
			return response()->json([
				'status' => 'error',
				'message' => 'Kode OTP salah.'
			], 400);
		}

		if (now()->greaterThan($resetRecord->expired_at)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Kode OTP sudah kedaluwarsa.'
			], 400);
		}

		// Generate secure token untuk proses reset
		$token = Str::random(60);

		$resetRecord->update([
			'is_verified' => true,
			'token' => $token,
			'expired_at' => now()->addMinutes(15), // token berlaku selama 15 menit
		]);

		return response()->json([
			'status' => 'success',
			'message' => 'Kode OTP berhasil diverifikasi.',
			'data' => [
				'token' => $token
			]
		]);
	}

	public function resetPassword(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email'    => 'required|string|email|max:255',
			'token'    => 'required|string',
			'password' => 'required|string|min:6|confirmed',
		]);

		if ($validator->fails()) {
			return response()->json([
				'status' => 'error',
				'message' => 'Validasi gagal.',
				'data' => $validator->errors()
			], 422);
		}

		$email = strtolower(trim((string) $request->input('email')));
		$token = $request->input('token');

		$resetRecord = PasswordResetOtp::where('email', $email)->first();

		if (!$resetRecord || !$resetRecord->is_verified || $resetRecord->token !== $token) {
			return response()->json([
				'status' => 'error',
				'message' => 'Token reset tidak valid atau sesi verifikasi telah kedaluwarsa.'
			], 400);
		}

		if (now()->greaterThan($resetRecord->expired_at)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Sesi reset password telah kedaluwarsa.'
			], 400);
		}

		// Update user password
		$user = User::where('email', $email)->first();
		if (!$user) {
			return response()->json([
				'status' => 'error',
				'message' => 'User tidak ditemukan.'
			], 404);
		}

		$user->update([
			'password' => Hash::make($request->input('password')),
		]);

		// Hapus record OTP
		$resetRecord->delete();

		return response()->json([
			'status' => 'success',
			'message' => 'Password Anda berhasil diperbarui.'
		]);
	}

	private function sendOtpEmailViaSendgrid(string $email, string $otp, string $username): void
	{
		$sendgridApiKey = trim((string) config('services.sendgrid.api_key', ''));
		if ($sendgridApiKey === '') {
			throw new \RuntimeException('SendGrid API key is not configured');
		}

		$fromEmail = trim((string) config('mail.from.address', ''));
		if ($fromEmail === '') {
			throw new \RuntimeException('MAIL_FROM_ADDRESS is not configured');
		}

		$fromName = trim((string) config('mail.from.name', 'KedaiKlik'));
		$htmlContent = view('emails.forgot-password', [
			'otp' => $otp,
			'username' => $username,
		])->render();

		$payload = [
			'from' => [
				'email' => $fromEmail,
				'name' => $fromName,
			],
			'personalizations' => [[
				'to' => [[
					'email' => $email,
				]],
				'subject' => 'Kode Verifikasi OTP Lupa Password KedaiKlik',
			]],
			'content' => [[
				'type' => 'text/html',
				'value' => $htmlContent,
			]],
		];

		$response = Http::withToken($sendgridApiKey)
			->acceptJson()
			->timeout(20)
			->connectTimeout(8)
			->post('https://api.sendgrid.com/v3/mail/send', $payload);

		if (!$response->successful()) {
			throw new \RuntimeException(
				'SendGrid API failed: HTTP ' . $response->status() . ' ' . $response->body()
			);
		}
	}
}
