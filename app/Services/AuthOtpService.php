<?php

namespace App\Services;

use App\Models\AuthOtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthOtpService
{
    public const PURPOSE_REGISTER = 'register';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public function issue(string $email, string $purpose): string
    {
        $normalizedEmail = strtolower(trim($email));
        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        AuthOtpCode::query()
            ->where('email', $normalizedEmail)
            ->where('purpose', $purpose)
            ->delete();

        AuthOtpCode::query()->create([
            'email' => $normalizedEmail,
            'purpose' => $purpose,
            'code' => Hash::make($plainCode),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        if (config('app.debug')) {
            Log::info('Auth OTP issued', [
                'email' => $normalizedEmail,
                'purpose' => $purpose,
                'otp' => $plainCode,
            ]);
        }

        return $plainCode;
    }

    public function verify(string $email, string $purpose, string $code): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $record = AuthOtpCode::query()
            ->where('email', $normalizedEmail)
            ->where('purpose', $purpose)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $record || ! Hash::check(trim($code), $record->code)) {
            return false;
        }

        $record->delete();

        return true;
    }
}
