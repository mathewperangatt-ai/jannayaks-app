<?php

namespace App\Console\Commands;

use App\Services\RazorpayPaymentService;
use Illuminate\Console\Command;

class ReconcilePaymentCapturesCommand extends Command
{
    protected $signature = 'jannayaks:reconcile-payment-captures';

    protected $description = 'Recover captured Razorpay payments whose webhook retries may have stopped.';

    public function handle(RazorpayPaymentService $razorpay): int
    {
        if (! $razorpay->config()['enabled']) {
            $this->info('Razorpay is disabled. No gateway requests were made.');

            return self::SUCCESS;
        }

        $payments = $razorpay->paymentsDueForCaptureReconciliation();
        if ($payments->isEmpty()) {
            $this->info('No payment attempts are due for capture reconciliation.');

            return self::SUCCESS;
        }

        foreach ($payments as $payment) {
            $outcome = $razorpay->reconcileOpenAttempt($payment);
            $this->line('payment '.$payment->id.': '.$outcome);
        }

        return self::SUCCESS;
    }
}
