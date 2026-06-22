<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function registerCustomer(array $input): User
    {
        $username = strtolower(trim((string) ($input['username'] ?? '')));

        return User::create([
            'username' => $username,
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'name' => $input['name'],
            'no_telp' => $input['no_telp'],
            'password' => Hash::make($input['password']),
            'role' => 'CUSTOMER',
        ]);
    }

    public function attemptLogin(array $credentials): ?string
    {
        $loginInput = strtolower(trim((string) ($credentials['username'] ?? '')));
        $password = $credentials['password'] ?? '';

        // Jika input berupa email, gunakan email sebagai kunci pencarian
        if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            $authCredentials = [
                'email' => $loginInput,
                'password' => $password,
            ];
        } else {
            $authCredentials = [
                'username' => $loginInput,
                'password' => $password,
            ];
        }

        $token = auth('api')->attempt($authCredentials);

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
        $avatarUrl = $user->avatar_url;
        if ($avatarUrl && !str_starts_with($avatarUrl, 'http://') && !str_starts_with($avatarUrl, 'https://')) {
            $avatarUrl = url($avatarUrl);
        }
        return [
            'id' => $user->_id,
            'username' => $user->username,
            'email' => $user->email,
            'name' => $user->name,
            'no_telp' => $user->no_telp,
            'role' => $user->role,
            'avatar_url' => $avatarUrl,
        ];
    }
}
