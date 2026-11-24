<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StaffAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Provision and administer the Jannayaks Security Administrator account.
 *
 * This command is the ONLY path that can create, reset, disable or re-enable
 * the security_admin account. It is deliberately OUTSIDE the Filament UI and
 * requires BOTH:
 *   1. Shell access to the Railway service (the ordinary admin UI cannot
 *      reach it — satisfying the separation requirement that the existing
 *      administrator cannot change, disable, delete or re-permission this
 *      account), and
 *   2. The emergency recovery key held in the environment
 *      (JANNAYAKS_SECURITY_RECOVERY_KEY) — server access alone is not enough.
 *
 * Every invocation is audit-logged. Secrets (password, TOTP secret, recovery
 * codes) are printed exactly once to the operator terminal and never stored
 * in plaintext.
 *
 * Identity email comes exclusively from
 * JANNAYAKS_PUBLISHED_PROFILE_ADMIN_EMAIL (no hardcoded address anywhere).
 */
class ProvisionSecurityAdminCommand extends Command
{
    protected $signature = 'jannayaks:security-admin
                            {--action=provision : provision | reset-password | reset-totp | disable | enable | status}';

    protected $description = 'Provision or administer the Jannayaks Security Administrator (requires the emergency recovery key).';

    public function handle(StaffAuditLogger $audit): int
    {
        $email = trim((string) config('jannayaks.security.integrity.published_profile_admin.email', ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('JANNAYAKS_PUBLISHED_PROFILE_ADMIN_EMAIL is not configured or is not a valid address.');

            return self::FAILURE;
        }

        $recoveryKey = (string) config('jannayaks.security.integrity.recovery_key', env('JANNAYAKS_SECURITY_RECOVERY_KEY', ''));
        if ($recoveryKey === '' || mb_strlen($recoveryKey) < 32) {
            $this->error('JANNAYAKS_SECURITY_RECOVERY_KEY must be set (32+ chars) before this command can run.');

            return self::FAILURE;
        }

        $this->line('Emergency recovery authorisation required.');
        $provided = (string) $this->secret('Enter JANNAYAKS_SECURITY_RECOVERY_KEY');
        if (! hash_equals($recoveryKey, $provided)) {
            $this->error('Recovery key mismatch — nothing was changed.');

            return self::FAILURE;
        }

        $action = (string) $this->option('action');

        try {
            $account = match ($action) {
                'provision' => $this->provision($email, $audit),
                'reset-password' => $this->resetPassword($email, $audit),
                'reset-totp' => $this->resetTotp($email, $audit),
                'disable' => $this->setAccountStatus($email, 'disabled_by_admin', $audit),
                'enable' => $this->setAccountStatus($email, 'active', $audit),
                'status' => $this->showStatus($email),
                default => throw new InvalidArgumentException('Unknown --action: '.$action),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function accountQuery(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    private function provision(string $email, StaffAuditLogger $audit): User
    {
        $existing = $this->accountQuery($email);
        if ($existing !== null) {
            $this->warn('Account already exists (role: '.$existing->role.'). Use --action=reset-password / reset-totp / status.');

            return $existing;
        }

        $password = Str::password(24, symbols: true);
        $account = User::query()->create([
            'name' => 'Jannayaks Security Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_SECURITY_ADMIN,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $audit->log(
            action: 'security.security_admin_provisioned',
            subject: $account,
            after: ['email_domain' => substr(strrchr($email, '@'), 0), 'role' => User::ROLE_SECURITY_ADMIN],
            actor: null,
        );

        $this->info('Security Administrator provisioned.');
        $this->line('Login email : '.$email);
        $this->line('One-time password (store in your password manager NOW — printed only here):');
        $this->line('  '.$password);

        return $account;
    }

    private function resetPassword(string $email, StaffAuditLogger $audit): User
    {
        $account = $this->requireAccount($email);
        $password = Str::password(24, symbols: true);
        $account->forceFill(['password' => Hash::make($password)])->save();

        // A password reset invalidates active sessions and outstanding TOTP state.
        DB::table('sessions')->where('user_id', $account->id)->delete();

        $audit->log(
            action: 'security.security_admin_password_reset',
            subject: $account,
            actor: null,
        );

        $this->info('Password rotated; all sessions terminated.');
        $this->line('One-time password (store NOW — printed only here):');
        $this->line('  '.$password);

        return $account;
    }

    private function resetTotp(string $email, StaffAuditLogger $audit): User
    {
        $account = $this->requireAccount($email);
        $tfa = new \RobThree\Auth\TwoFactorAuth('Jannayaks');
        $secret = $tfa->createSecret();
        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(10)))->all();

        $account->forceFill([
            'totp_secret' => $secret,
            'totp_enabled_at' => null, // Re-enrollment required before panel access.
            'totp_recovery_codes' => $codes,
        ])->save();

        $audit->log(
            action: 'security.security_admin_totp_reset',
            subject: $account,
            actor: null,
        );

        $this->info('TOTP secret rotated. Enrollment is required before panel access.');
        $this->line('TOTP secret (enter in your authenticator NOW — printed only here):');
        $this->line('  '.$secret);
        $this->line('Setup key (issuer: Jannayaks / account: '.$email.'). Enrollment page will accept a fresh code.');
        $this->line('Recovery codes (store NOW — printed only here):');
        foreach ($codes as $code) {
            $this->line('  '.$code);
        }

        return $account;
    }

    private function setAccountStatus(string $email, string $status, StaffAuditLogger $audit): User
    {
        $account = $this->requireAccount($email);
        $account->forceFill(['account_status' => $status])->save();
        if ($status !== 'active') {
            DB::table('sessions')->where('user_id', $account->id)->delete();
        }

        $audit->log(
            action: 'security.security_admin_status_changed',
            subject: $account,
            before: ['account_status' => $account->getOriginal('account_status')],
            after: ['account_status' => $status],
            actor: null,
        );

        $this->info('Account status: '.$status);

        return $account;
    }

    private function showStatus(string $email): User
    {
        $account = $this->requireAccount($email);
        $this->line(json_encode([
            'role' => $account->role,
            'account_status' => $account->account_status,
            'totp_enabled' => $account->totp_enabled_at !== null,
            'recovery_codes_remaining' => is_array($account->totp_recovery_codes) ? count($account->totp_recovery_codes) : 0,
        ], JSON_PRETTY_PRINT));

        return $account;
    }

    private function requireAccount(string $email): User
    {
        $account = $this->accountQuery($email);
        if ($account === null) {
            throw new InvalidArgumentException('No account exists for the configured Security Administrator email. Run --action=provision first.');
        }
        if ($account->role !== User::ROLE_SECURITY_ADMIN) {
            throw new InvalidArgumentException('The configured account no longer holds the security_admin role — refusing to act.');
        }

        return $account;
    }
}
