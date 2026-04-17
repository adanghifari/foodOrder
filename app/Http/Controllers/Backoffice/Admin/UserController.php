<?php

namespace App\Http\Controllers\Backoffice\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function indexPage()
    {
        $users = User::orderBy('_id', 'desc')
            ->get()
            ->map(function (User $user) {
                $role = strtoupper((string) ($user->role ?? 'CUSTOMER'));
                $name = (string) ($user->name ?? '-');
                $profilePhoto = (string) ($user->profile_photo_url ?? $user->avatar_url ?? '');

                if ($profilePhoto === '') {
                    $profilePhoto = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=FCB861&color=6A2B09&bold=true';
                }

                return [
                    'id' => (string) $user->_id,
                    'name' => $name,
                    'username' => (string) ($user->username ?? '-'),
                    'email' => (string) (($user->email ?? null) ?: ($user->username ?? '-')),
                    'phone' => (string) ($user->no_telp ?? '-'),
                    'role' => $role,
                    'photoUrl' => $profilePhoto,
                    'createdAt' => optional($user->created_at)?->toDateTimeString(),
                ];
            })
            ->values();

        $summary = [
            'total' => $users->count(),
            'admin' => $users->where('role', 'ADMIN')->count(),
            'customer' => $users->where('role', 'CUSTOMER')->count(),
        ];

        return view('backoffice.user.index', [
            'users' => $users,
            'summary' => $summary,
        ]);
    }

    public function deletePage(string $id): RedirectResponse
    {
        $user = User::find($id);

        if (!$user) {
            return redirect('/backoffice/pengguna')->with('error', 'Akun tidak ditemukan.');
        }

        $role = strtoupper((string) ($user->role ?? 'CUSTOMER'));
        if ($role === 'ADMIN') {
            return redirect('/backoffice/pengguna')->with('error', 'Akun admin tidak dapat dihapus.');
        }

        $displayName = trim((string) ($user->name ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($user->username ?? 'pengguna');
        }

        $user->delete();

        return redirect('/backoffice/pengguna')->with('success', 'Akun ' . $displayName . ' berhasil dihapus.');
    }
}
