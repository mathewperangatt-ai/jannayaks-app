<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\RefundService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordRefund')
                ->label('Record refund credit note')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Record a credit note')
                ->modalDescription('This records a credit note in Jannayaks. It does not send a refund to the payment gateway. After dashboard delivery, use this only for an exceptional case such as those listed in the refund policy.')
                ->visible(fn (): bool => (auth()->user()?->canManageFinance() ?? false)
                    && $this->getRecord() instanceof Payment
                    && $this->getRecord()->isPaidOrBetter()
                    && ! $this->getRecord()->isRefunded())
                ->form([
                    Textarea::make('note')
                        ->label('Reason')
                        ->required()
                        ->minLength(8)
                        ->maxLength(500),
                    Toggle::make('exceptional_after_delivery')
                        ->label('This is an exceptional refund after dashboard delivery')
                        ->helperText('Leave off for the routine full refund before the completed profile is delivered. Turning this on does not call the gateway.'),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User || ! $actor->canManageFinance()) {
                        abort(403);
                    }

                    /** @var Payment $payment */
                    $payment = $this->getRecord();
                    $refund = app(RefundService::class)->initiateRefund(
                        $payment,
                        (string) ($data['note'] ?? ''),
                        null,
                        null,
                        (bool) ($data['exceptional_after_delivery'] ?? false),
                    );

                    if (! $refund instanceof Payment) {
                        Notification::make()
                            ->title('Refund was not recorded')
                            ->body('The routine refund window may already be closed. Turn on the exceptional option only when that is the intended case.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Credit note recorded')
                        ->body('No gateway refund was issued. Finance must initiate that separately in the payment gateway.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
