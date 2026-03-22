<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Require auth for specific routes
        // Wait, middleware in controller is deprecated in Laravel 11.
        // We will assign middleware in routes/api.php
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users',
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

        $user = User::create([
            'username' => $request->username,
            'name'     => $request->name,
            'no_telp'  => $request->no_telp,
            'password' => Hash::make($request->password),
            'role'     => 'CUSTOMER',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Customer registered',
            'data' => [
                'id' => $user->_id,
                'username' => $user->username,
                'name' => $user->name,
                'no_telp' => $user->no_telp,
                'role' => $user->role,
            ]
        ], 201);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['username', 'password']);

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid username or password'
            ], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = auth('api')->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Current user',
            'data' => [
                'id' => $user->_id,
                'username' => $user->username,
                'name' => $user->name,
                'no_telp' => $user->no_telp,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        $user = auth('api')->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->_id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'no_telp' => $user->no_telp,
                    'role' => $user->role,
                ]
            ]
        ]);
    }

    /**
     * Show the login form for the web interface.
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle login for the web interface.
     */
    public function webLogin(Request $request)
    {
        $credentials = $request->only('username', 'password');

        if (! $token = auth('api')->attempt($credentials)) {
            return back()->with('error', 'Username atau password salah')->withInput($request->only('username'));
        }

        $user = auth('api')->user();

        // Redirect based on role
        if ($user->role === 'ADMIN') {
            return redirect('/admin/dashboard')->withCookie(cookie('token', $token, 60 * 24));
        }

        return redirect('/menu/allmenu')->withCookie(cookie('token', $token, 60 * 24));
    }

    /**
     * Show the registration form for the web interface.
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle registration for the web interface.
     */
    public function webRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users',
            'name'     => 'required|string|max:255',
            'no_telp'  => 'required|string|max:20',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        User::create([
            'username' => $request->username,
            'name'     => $request->name,
            'no_telp'  => $request->no_telp,
            'password' => Hash::make($request->password),
            'role'     => 'CUSTOMER',
        ]);

        return redirect('/login')->with('success', 'Registrasi berhasil! Silakan login.');
    }
}
