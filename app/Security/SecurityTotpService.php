<?php

namespace App\Security;

use App\Models\User;
use Illuminate\Support\Str;
use RobThree\Auth\TwoFactorAuth;

/**
 * TOTP two-factor authentication for Jannayaks staff (Wave 3).
 *
 * Mandatory for the Jannayaks Security Administrator (security_admin):
 * enrollment is enforced before panel access, and every login requires a
 * fresh TOTP or recovery code until the browser session ends. Recovery codes
 * are single-use and hashed like passwords; TOTP secrets are encrypted at
 * rest via the User model casts.
 */
class SecurityTotpService
{
    private const ISSUER = 'Jannayaks';

    private const SESSION_KEY = 'security.totp_verified_at';

    public function enroll(User $user): array
    {
        $secret = (new TwoFactorAuth(self::ISSUER, 6, 30))->createSecret();

        $codes = collect(range(1, 8))
            ->map(fn (): string => strtoupper(Str::random(10)))
            ->all();

        $user->forceFill([
            'totp_secret' => $secret,
            'totp_recovery_codes' => array_map(fn (string $code): string => bcrypt($code), $codes),
            'totp_enabled_at' => null, // Enabled only after a code is confirmed.
        ])->save();

        return [
            'secret' => $secret,
            // otpauth URI (v1.8 exposes getQRText(label, secret) rather than
            // getProvisioningUri); authenticator apps accept the setup key
            // directly, which is what the enrollment page presents.
            'uri' => (new TwoFactorAuth(self::ISSUER, 6, 30))->getQRText(self::ISSUER.' Security Admin: '.$user->email, $secret),
            'recovery_codes' => $codes, // Shown exactly once.
        ];
    }

    public function confirmEnrollment(User $user, string $code): bool
    {
        $secret = (string) $user->totp_secret;
        if ($secret === '') {
            return false;
        }

        if (! $this->verifyCode($secret, $code)) {
            return false;
        }

        $user->forceFill(['totp_enabled_at' => now()])->save();
        $this->markSessionVerified();

        return true;
    }

    /**
     * Login challenge: TOTP code or a single-use recovery code.
     */
    public function verifyChallenge(User $user, string $code): bool
    {
        if ($user->totp_enabled_at === null) {
            return false;
        }

        $trimmed = trim($code);

        // TOTP code first (window ±1 step for clock drift).
        $secret = (string) $user->totp_secret;
        if ($secret !== '' && $this->verifyCode($secret, $trimmed)) {
            $this->markSessionVerified();

            return true;
        }

        // Recovery codes: single use, hashed, order-independent.
        $codes = $user->totp_recovery_codes ?? [];
        foreach ($codes as $index => $hashed) {
            if (is_string($hashed) && str_starts_with($hashed, '$2') && password_verify($trimmed, $hashed)) {
                unset($codes[$index]);
                $user->forceFill(['totp_recovery_codes' => array_values($codes)])->save();
                $this->markSessionVerified();

                return true;
            }
        }

        return false;
    }

    public function isSessionVerified(): bool
    {
        $verifiedAt = session(self::SESSION_KEY);

        return is_numeric($verifiedAt) && (int) $verifiedAt > 0;
    }

    public function markSessionVerified(): void
    {
        // Session-scoped: expires when the browser session ends, and the
        // login flow clears it at every new login.
        session([self::SESSION_KEY => now()->timestamp]);
    }

    public function clearSessionVerification(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function needsEnrollment(User $user): bool
    {
        return $user->isSecurityAdmin()
            && ($user->totp_enabled_at === null || (string) $user->totp_secret === '');
    }

    private function verifyCode(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $tfa = new TwoFactorAuth(self::ISSUER, 6, 30);

        try {
            return (bool) $tfa->verifyCode($secret, $code, 1);
        } catch (\Throwable) {
            return false;
        }
    }
}
