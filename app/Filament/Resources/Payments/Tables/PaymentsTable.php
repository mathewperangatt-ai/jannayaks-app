<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('application_id')->label('App #')->sortable(),
                TextColumn::make('transaction_reference')->searchable()->wrap(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('amount')->money('INR')->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('gateway'),
                TextColumn::make('invoice_number')->toggleable(),
                TextColumn::make('tax_invoice_number')->toggleable(),
                TextColumn::make('paid_at')->dateTime()->sortable(),
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
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
