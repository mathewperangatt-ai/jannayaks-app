<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\RazorpayPaymentService;
use Illuminate\Console\Command;

/**
 * TEMPORARY — pre-Razorpay-launch workflow testing. Delete this file when
 * online payments go live.
 *
 * Settles the active payment attempt for an ALLOWLISTED test account through
 * the same primitives the Razorpay webhook uses (markPaidOrCaptured +
 * ApplicationPaymentStateService::afterSettled), so the application reaches
 * the genuine post-payment state. The row is marked simulated
 * (method='test_simulated', audit note on the application); no Razorpay
 * transaction/link IDs are invented and no invoice documents are assigned, so
 * a simulated payment can never pass as a real one. There is no HTTP surface:
 * only an operator with shell access can run it.
 */
class SimulateTestPayment extends Command
{
    protected $signature = 'payments:simulate-test-payment
        {user : Email or username of an allowlisted test account}';

    protected $description = 'TEMPORARY (pre-launch): settle a payment for an allowlisted test account to exercise the post-payment workflow.';

    /**
     * Test accounts only — this is a fixed fixture list, not a configuration
     * surface, so nothing can widen it without a code change.
     */
    private const ALLOWLIST = [
        'ravison05@gmail.com',
        'terry.perangat@gmail.com',
        'sreejas.tvm@gmail.com',
        'mathewin',
    ];

    public function handle(ApplicationPaymentStateService $stateService): int
    {
        // Self-disabling: the moment the gateway is enabled this command
        // refuses to run, so it can never create a real payment link.
        if ((bool) config('services.razorpay.enabled')) {
            $this->error('Refusing to simulate: Razorpay is enabled. This command is only for the pre-launch phase.');

            return self::FAILURE;
        }

        $identifier = strtolower(trim((string) $this->argument('user')));
        if (! in_array($identifier, self::ALLOWLIST, true)) {
            $this->error('"'.$identifier.'" is not an allowlisted test account.');

            return self::FAILURE;
        }

        $user = User::query()->where(function ($q) use ($identifier) {
            $q->whereRaw('LOWER(email) = ?', [$identifier])->orWhere('username', $identifier);
        })->first();
        if ($user === null) {
            $this->error('No user found for "'.$identifier.'".');

            return self::FAILURE;
        }

        /** @var Application|null $application */
        $application = Application::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Application::STATUS_PAYMENT_PENDING, Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW])
            ->orderByDesc('id')
            ->first();
        if ($application === null) {
            $this->error($user->email.' has no payment_pending application to settle.');

            return self::FAILURE;
        }

        $alreadySettled = Payment::query()
            ->where('application_id', $application->id)
            ->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_CAPTURED, Payment::STATUS_SUCCESS, Payment::STATUS_REFUNDED])
            ->exists();
        if ($alreadySettled) {
            $this->warn('Application #'.$application->id.' already has a settled payment. Nothing to do.');

            return self::SUCCESS;
        }

        // Builds (or reuses) the active payment attempt. With the gateway
        // disabled this marks the row initiated WITHOUT contacting Razorpay
        // and WITHOUT any gateway/link identifiers.
        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($application);

        if ($payment->isPaidOrBetter()) {
            $this->warn('Application #'.$application->id.' already has a settled payment (payment #'.$payment->id.'). Nothing to do.');

            return self::SUCCESS;
        }

        $payment->markPaidOrCaptured(
            targetStatus: Payment::STATUS_PAID,
            gatewayPaymentId: null,
            gatewayEventId: null,
            eventType: Payment::EVENT_PAYMENT_CAPTURED,
            paidAt: now(),
        );
        $payment->forceFill(['method' => 'test_simulated'])->save();

        $stateService->afterSettled($payment->fresh() ?? $payment);

        $marker = 'SIMULATED TEST PAYMENT — pre-launch workflow testing only; not a real transaction.';
        $application->forceFill([
            'admin_demo_audit_note' => trim(((string) $application->admin_demo_audit_note !== '' ? $application->admin_demo_audit_note.' | ' : '').$marker),
        ])->save();
        $application->refresh();

        $this->info('Simulated payment settled for '.$user->email.'.');
        $this->line('  payment #'.$payment->id.' status='.$payment->status.' method='.$payment->method.' (no gateway IDs, no invoice assigned)');
        $this->line('  application #'.$application->id.' status='.$application->status.' payment_status='.$application->payment_status);
        $this->line('  Online Interview is now unlocked for this application.');

        return self::SUCCESS;
    }
}
