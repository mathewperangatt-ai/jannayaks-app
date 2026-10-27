<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\RazorpayPaymentService;
use App\Services\RefundService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;

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

                    $notification = Notification::make()->title('Razorpay check finished')->persistent();
                    if (in_array($outcome, [RazorpayPaymentService::OUTCOME_SETTLED, RazorpayPaymentService::OUTCOME_ALREADY_SETTLED, RazorpayPaymentService::OUTCOME_CLOSED], true)) {
                        $notification->success()->body('The payment record was updated from the gateway result.');
                    } elseif ($outcome === RazorpayPaymentService::OUTCOME_SKIPPED) {
                        $notification->warning()->body('A Razorpay check was just recorded. Wait before checking again.');
                    } elseif ($outcome === RazorpayPaymentService::OUTCOME_DISABLED) {
                        $notification->warning()->body('Razorpay is disabled. No gateway request was made.');
                    } else {
                        $notification->warning()->body('The attempt was not settled. The reconciliation note on this payment has the result.');
                    }
                    $notification->persistent()->send();
                }),
            Action::make('recordLocalRefund')
                ->label('Record local refund')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Record a local refund')
                ->modalDescription(function (): string {
                    $record = $this->getRecord();
                    if (! $record instanceof Payment) {
                        return 'This does not call Razorpay.';
                    }
                    $summary = app(RefundService::class)->refundSummary($record);
                    $amount = $summary['refund_amount_formatted'] ?? 'not calculated';
                    $eligibility = $summary['eligible'] ? 'eligible' : 'not eligible';

                    return 'Calculated refund: '.$amount.' ('.$summary['basis_percent'].'%, '.$eligibility.'). '
                        .'Confirming records the refund and a credit note locally. Razorpay is not called. '
                        .'A live gateway refund stays off until GST, Razorpay production, and the business constitution are decided.';
                })
                ->visible(function (): bool {
                    $record = $this->getRecord();

                    return (auth()->user()?->canManageFinance() ?? false)
                        && $record instanceof Payment
                        && $record->isPaidOrBetter()
                        && ! $record->isRefunded();
                })
                ->form([
                    Textarea::make('reason')->label('Reason')->required(),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    $record = $this->getRecord();
                    if (! $actor instanceof User || ! $record instanceof Payment) {
                        abort(403);
                    }

                    try {
                        $recorded = app(RefundService::class)->recordAdministrativeRefund($record, $actor, (string) $data['reason']);
                        $this->record = $recorded;
                        Notification::make()
                            ->title('Local refund recorded')
                            ->body('Disposition: recorded locally. No Razorpay refund was sent.')
                            ->success()
                            ->persistent()
                            ->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Refund was not recorded')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
