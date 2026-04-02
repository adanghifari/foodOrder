<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function registerCustomer(array $input): User
    {
        return User::create([
            'username' => $input['username'],
            'name' => $input['name'],
            'no_telp' => $input['no_telp'],
            'password' => Hash::make($input['password']),
            'role' => 'CUSTOMER',
        ]);
    }

    public function attemptLogin(array $credentials): ?string
    {
        $token = auth('api')->attempt($credentials);

        return $token ?: null;
    }

    public function currentUser()
    {
        return auth('api')->user();
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    public function refreshToken(): string
    {
        return auth('api')->refresh();
    }

    public function userPayload($user): array
    {
        return [
            'id' => $user->_id,
            'username' => $user->username,
            'name' => $user->name,
            'no_telp' => $user->no_telp,
            'role' => $user->role,
        ];
    }
}
