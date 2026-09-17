<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('application_id')->label('Application'),
                    TextEntry::make('transaction_reference'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('amount'),
                    TextEntry::make('currency'),
                    TextEntry::make('gateway'),
                    TextEntry::make('razorpay_payment_link_id'),
                    TextEntry::make('razorpay_payment_id'),
                    TextEntry::make('invoice_number'),
                    TextEntry::make('tax_invoice_number'),
                    TextEntry::make('credit_note_number'),
                    TextEntry::make('paid_at')->dateTime(),
                    TextEntry::make('refunded_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }
}
