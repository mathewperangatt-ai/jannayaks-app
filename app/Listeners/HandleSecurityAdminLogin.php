<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\StaffAuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Wave 3 security scaffolding for the Security Administrator:
 *  - every login is audit-logged,
 *  - the TOTP session verification is cleared so each new login requires a
 *    fresh challenge,
 *  - a new-sign-in notification is emailed to the account itself.
 */
class HandleSecurityAdminLogin implements ShouldQueue
{
    public function __construct(private StaffAuditLogger $audit) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->isSecurityAdmin()) {
            return;
        }

        app(\App\Security\SecurityTotpService::class)->clearSessionVerification();

        $this->audit->log(
            action: 'security.security_admin_login',
            subject: $user,
            after: [
                'guard' => $event->guard,
                'remember' => (bool) $event->remember,
            ],
            actor: $user,
        );

        $recipient = trim((string) $user->email);
        if ($recipient !== '') {
            Mail::raw(
                'A new sign-in to the Jannayaks Security Administrator account occurred at '
                .now()->timezone('Asia/Kolkata')->format('Y-m-d H:i:s').' IST. '
                .'If this was not you, run the emergency recovery procedure immediately (jannayaks:security-admin).',
                function ($message) use ($recipient): void {
                    $message->to($recipient)
                        ->subject('[JANNAYAKS SECURITY] New sign-in to the Security Administrator account');
                },
            );
        }
    }
}
