<?php

namespace App\Http\Controllers\Frontliner\Mobile;

use App\Http\Controllers\Controller;
use App\Domains\Auth\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

		$credentials = $request->only(['username', 'password']);
		$token = $this->authService->attemptLogin($credentials);

		if (! $token) {
			return response()->json([
				'status' => 'error',
				'message' => 'Invalid username or password'
			], 401);
		}

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
}
