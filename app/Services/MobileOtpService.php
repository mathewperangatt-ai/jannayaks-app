<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Models\User;
use App\Support\IndiaMobile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MobileOtpService
{
    public const TEST_CACHE_PREFIX = 'otp:test:';

    public function request(string $rawMobile, ?string $ip = null): string
    {
        try {
            $mobile = IndiaMobile::normalize($rawMobile);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['mobile' => $e->getMessage()]);
        }

        $mobileKey = 'otp-request:mobile:'.$mobile;
        $ipKey = 'otp-request:ip:'.($ip ?: 'unknown');

        if (RateLimiter::tooManyAttempts($mobileKey, 3)) {
            throw ValidationException::withMessages([
                'mobile' => 'Too many OTP requests for this number. Please wait and try again.',
            ]);
        }
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            throw ValidationException::withMessages([
                'mobile' => 'Too many OTP requests. Please wait and try again.',
            ]);
        }

        RateLimiter::hit($mobileKey, 600);
        RateLimiter::hit($ipKey, 600);

        $code = (string) random_int(100000, 999999);

        OtpVerification::query()->create([
            'mobile' => $mobile,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('jannayaks.otp.expiry_minutes', 10)),
            'request_ip' => $ip,
        ]);

        $this->dispatchCode($mobile, $code);

        if (app()->environment('testing') || (bool) config('jannayaks.otp.expose_test_code', false)) {
            Cache::put(self::TEST_CACHE_PREFIX.$mobile, $code, now()->addMinutes(15));
        }

        return $mobile;
    }

    public function verify(string $rawMobile, string $code, ?string $ip = null): User
    {
        try {
            $mobile = IndiaMobile::normalize($rawMobile);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['mobile' => $e->getMessage()]);
        }

        $verifyMobileKey = 'otp-verify:mobile:'.$mobile;
        $verifyIpKey = 'otp-verify:ip:'.($ip ?: 'unknown');

        if (RateLimiter::tooManyAttempts($verifyMobileKey, 8)) {
            throw ValidationException::withMessages([
                'otp' => 'Too many verification attempts. Please request a new OTP.',
            ]);
        }
        if (RateLimiter::tooManyAttempts($verifyIpKey, 20)) {
            throw ValidationException::withMessages([
                'otp' => 'Too many verification attempts from this network. Please wait and try again.',
            ]);
        }

        RateLimiter::hit($verifyMobileKey, 600);
        RateLimiter::hit($verifyIpKey, 600);

        $otp = OtpVerification::query()
            ->where('mobile', $mobile)
            ->whereNull('used_at')
            ->orderByDesc('id')
            ->first();

        if (! $otp instanceof OtpVerification || $otp->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => 'OTP expired or not found. Please request a new code.',
            ]);
        }

        if (! Hash::check($code, $otp->otp_hash)) {
            $otp->increment('attempt_count');
            throw ValidationException::withMessages([
                'otp' => 'Invalid OTP. Please try again.',
            ]);
        }

        $otp->forceFill([
            'used_at' => now(),
            'attempt_count' => $otp->attempt_count + 1,
        ])->save();

        RateLimiter::clear($verifyMobileKey);

        $user = User::query()->where('mobile', $mobile)->first();
        if (! $user instanceof User) {
            $user = User::query()->create([
                'name' => 'Member '.substr($mobile, -4),
                'mobile' => $mobile,
                'mobile_verified_at' => now(),
                'role' => User::ROLE_MEMBER,
                'account_status' => 'active',
                'password' => null,
            ]);
        } else {
            if ($user->mobile_verified_at === null) {
                $user->mobile_verified_at = now();
            }
            $user->save();
        }

        $otp->user_id = $user->id;
        $otp->save();

        Cache::forget(self::TEST_CACHE_PREFIX.$mobile);

        return $user;
    }

    public function testCodeFor(string $normalizedMobile): ?string
    {
        $value = Cache::get(self::TEST_CACHE_PREFIX.$normalizedMobile);

        return is_string($value) ? $value : null;
    }

    private function dispatchCode(string $mobile, string $code): void
    {
        // P7: log/test only — no SMS provider is implemented.
        Log::info('Jannayaks mobile OTP dispatched (non-SMS P7 driver).', [
            'mobile_suffix' => substr($mobile, -4),
            'channel' => config('jannayaks.otp.channel', 'log'),
            'sms_enabled' => (bool) config('jannayaks.otp.sms_enabled', false),
        ]);

        if ((bool) config('jannayaks.otp.log_plaintext_in_non_production', true) && ! app()->environment('production')) {
            Log::debug('Jannayaks OTP metadata (non-production only; code not logged).', [
                'mobile_suffix' => substr($mobile, -4),
                'otp_length' => strlen($code),
            ]);
        }
    }
}
