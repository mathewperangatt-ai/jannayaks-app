<?php

namespace App\Filament\Resources\Applications\Tables;

use App\Models\Application;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('package_tier')
                    ->label('Package')
                    ->badge()
                    ->sortable(),
                TextColumn::make('source_method')
                    ->label('Source')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Workflow')
                    ->formatStateUsing(fn (?string $state): string => Application::workflowStatusLabels()[$state] ?? (string) $state)
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->canManageFinance() ?? false),
                TextColumn::make('payment_settled_at')
                    ->label('Settled')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('online_interview_completed_at')
                    ->label('Interview')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Workflow status')
                    ->options(Application::workflowStatusLabels()),
                SelectFilter::make('package_tier')
                    ->options([
                        'emerging' => 'Emerging',
                        'accomplished' => 'Accomplished',
                        'distinguished' => 'Distinguished',
                    ]),
                SelectFilter::make('source_method')
                    ->options([
                        'online_interview' => 'Online Interview',
                        'direct_submission' => 'Direct Submission',
                        'admin_test_demo' => 'Admin Test Demo',
                    ]),
                SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'waived' => 'Waived',
                        'refunded' => 'Refunded',
                        'partially_refunded' => 'Partially refunded',
                    ])
                    ->visible(fn (): bool => auth()->user()?->canManageFinance() ?? false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Application $record): bool => auth()->user()?->can('update', $record) ?? false),
            ]);
    }
}
