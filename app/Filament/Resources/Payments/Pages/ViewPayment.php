<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Services\RazorpayPaymentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkRazorpayNow')
                ->label('Check Razorpay now')
                ->requiresConfirmation()
                ->modalHeading('Check this payment with Razorpay')
                ->modalDescription('Reads the payment link and does not cancel, replace, or refund it.')
                ->visible(function (): bool {
                    $record = $this->getRecord();

                    return $record instanceof Payment
                        && app(RazorpayPaymentService::class)->canStaffReconcileAttempt($record)
                        && (auth()->user()?->canManageFinance() ?? false);
                })
                ->action(function (RazorpayPaymentService $razorpay): void {
                    $record = $this->getRecord();
                    if (! $record instanceof Payment) {
                        return;
                    }

                    $outcome = $razorpay->reconcileOpenAttempt($record, true);
                    $this->record = $record->fresh() ?? $record;

                    $notification = Notification::make()->title('Razorpay check finished');
                    if (in_array($outcome, [RazorpayPaymentService::OUTCOME_SETTLED, RazorpayPaymentService::OUTCOME_ALREADY_SETTLED, RazorpayPaymentService::OUTCOME_CLOSED], true)) {
                        $notification->success()->body('The payment record was updated from the gateway result.');
                    } elseif ($outcome === RazorpayPaymentService::OUTCOME_SKIPPED) {
                        $notification->warning()->body('A Razorpay check was just recorded. Wait before checking again.');
                    } elseif ($outcome === RazorpayPaymentService::OUTCOME_DISABLED) {
                        $notification->warning()->body('Razorpay is disabled. No gateway request was made.');
                    } else {
                        $notification->warning()->body('The attempt was not settled. The reconciliation note on this payment has the result.');
                    }
                    $notification->send();
                }),
        ];
    }
}
