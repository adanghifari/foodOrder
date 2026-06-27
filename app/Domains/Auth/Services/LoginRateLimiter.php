<?php

namespace App\Domains\Auth\Services;

use App\Models\LoginAttempt;
use Carbon\Carbon;

class LoginRateLimiter
{
    /**
     * Cek apakah user sedang terkunci (lockout).
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $attempt = LoginAttempt::where('key', $key)->first();
        if (!$attempt) {
            return false;
        }

        // Jika lockout_expires_at ada dan masih di masa depan, berarti terkunci
        if ($attempt->lockout_expires_at && Carbon::now()->lessThan($attempt->lockout_expires_at)) {
            return true;
        }

        // Jika lockout sudah lewat, reset attempts
        if ($attempt->lockout_expires_at && Carbon::now()->greaterThanOrEqualTo($attempt->lockout_expires_at)) {
            $attempt->update([
                'attempts' => 0,
                'lockout_expires_at' => null,
            ]);
            return false;
        }

        // Cek jika waktu percobaan terakhir sudah lewat dari 1 menit (decay time)
        if ($attempt->last_attempt_at && Carbon::now()->diffInSeconds($attempt->last_attempt_at) > 60) {
            $attempt->update([
                'attempts' => 0,
            ]);
            return false;
        }

        return $attempt->attempts >= $maxAttempts;
    }

    /**
     * Catat percobaan login yang salah.
     */
    public function hit(string $key, int $decaySeconds = 60): void
    {
        $attempt = LoginAttempt::where('key', $key)->first();
        $now = Carbon::now();

        if (!$attempt) {
            LoginAttempt::create([
                'key' => $key,
                'attempts' => 1,
                'lockout_expires_at' => null,
                'last_attempt_at' => $now,
            ]);
            return;
        }

        // Jika lockout sedang aktif, abaikan hit tambahan
        if ($attempt->lockout_expires_at && $now->lessThan($attempt->lockout_expires_at)) {
            return;
        }

        // Jika attempt terakhir sudah lewat dari 60 detik, reset hit dari 1 kembali
        if ($attempt->last_attempt_at && $now->diffInSeconds($attempt->last_attempt_at) > 60) {
            $newAttempts = 1;
        } else {
            $newAttempts = $attempt->attempts + 1;
        }

        $lockoutExpires = null;
        if ($newAttempts >= 5) {
            $lockoutExpires = $now->copy()->addSeconds($decaySeconds);
        }

        $attempt->update([
            'attempts' => $newAttempts,
            'lockout_expires_at' => $lockoutExpires,
            'last_attempt_at' => $now,
        ]);
    }

    /**
     * Dapatkan sisa waktu kunci dalam detik.
     */
    public function availableIn(string $key): int
    {
        $attempt = LoginAttempt::where('key', $key)->first();
        if (!$attempt || !$attempt->lockout_expires_at) {
            return 0;
        }

        $diff = Carbon::now()->diffInSeconds($attempt->lockout_expires_at, false);
        return $diff > 0 ? (int) $diff : 0;
    }

    /**
     * Bersihkan riwayat kegagalan (dipanggil saat login berhasil).
     */
    public function clear(string $key): void
    {
        LoginAttempt::where('key', $key)->delete();
    }
}
