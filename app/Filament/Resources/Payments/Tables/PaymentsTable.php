<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Models\Payment;
use App\Services\InvoiceDeliveryService;
use App\Services\InvoicePdfService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('application_id')->label('App #')->sortable(),
                // 15.9: search by customer name on the linked application.
                TextColumn::make('application.full_name')->label('Customer')->searchable()->toggleable(),
                TextColumn::make('transaction_reference')->searchable()->wrap(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('amount')->money('INR')->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('gateway'),
                // 15.9: search by invoice / receipt number.
                TextColumn::make('invoice_number')->searchable()->toggleable(),
                TextColumn::make('tax_invoice_number')->searchable()->toggleable(),
                TextColumn::make('credit_note_number')->searchable()->toggleable(),
                TextColumn::make('refund_amount')->label('Refunded')->money('INR')->toggleable(),
                TextColumn::make('invoice_emailed_at')->label('Docs emailed')->dateTime()->toggleable(),
                TextColumn::make('paid_at')->dateTime()->sortable(),
                TextColumn::make('error_code')->label('Reconciliation')->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'initiated' => 'Initiated',
                        'paid' => 'Paid',
                        'captured' => 'Captured',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'expired' => 'Expired',
                        'refunded' => 'Refunded',
                        'partially_refunded' => 'Partially refunded',
                    ]),
                // 15.9: date filtering.
                Filter::make('paid_at')
                    ->form([
                        DatePicker::make('paid_from')->label('Paid from'),
                        DatePicker::make('paid_until')->label('Paid until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['paid_from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('paid_at', '>=', $date))
                            ->when($data['paid_until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('paid_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                // 15.9: PDF downloads straight from the register.
                Action::make('downloadReceiptPdf')
                    ->label('Receipt PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->visible(fn (Payment $record): bool => $record->isPaidOrBetter() && $record->invoice_number !== null)
                    ->url(fn (Payment $record): string => route('admin.payments.document.pdf', ['payment' => $record, 'kind' => 'receipt']))
                    ->openUrlInNewTab(),
                Action::make('downloadTaxInvoicePdf')
                    ->label('Tax invoice PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->visible(fn (Payment $record): bool => $record->isPaidOrBetter() && $record->tax_invoice_number !== null)
                    ->url(fn (Payment $record): string => route('admin.payments.document.pdf', ['payment' => $record, 'kind' => 'tax-invoice']))
                    ->openUrlInNewTab(),
                // 15.9: document-email resend with the registered address.
                Action::make('resendDocuments')
                    ->label('Resend documents')
                    ->icon('heroicon-m-envelope')
                    ->requiresConfirmation()
                    ->modalHeading('Resend settlement documents by email')
                    ->modalDescription('Re-sends the receipt and any issued tax invoice / credit note to the registered email. Document numbers are not changed.')
                    ->visible(fn (Payment $record): bool => $record->isPaidOrBetter() || $record->isRefunded())
                    ->action(function (Payment $record, InvoiceDeliveryService $delivery): void {
                        $sent = $delivery->sendSettlementDocuments($record, force: true);
                        Notification::make()
                            ->title($sent ? 'Documents emailed' : 'Email not sent')
                            ->body($sent
                                ? 'The settlement documents were delivered to the registered email address.'
                                : 'No registered billing email is available or document delivery is disabled. The issued documents remain unchanged.')
                            ->status($sent ? 'success' : 'warning')
                            ->persistent()
                            ->send();
                    }),
            ]);
    }
}
