<?php

namespace App\Filament\Resources\Applications\Tables;

use App\Models\Application;
use App\Models\EditorialRevisionRequest;
use App\Models\MediaItem;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Support\TierLabels;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('profile.reference_code')
                    ->label('Ref')
                    ->copyable()
                    ->copyMessage('Reference number copied')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'profile',
                        fn (Builder $query) => $query->where('reference_code', 'like', "%{$search}%"),
                    ))
                    ->sortable(),
                TextColumn::make('profile.slug')
                    ->label('Public slug')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'profile',
                        fn (Builder $query) => $query->where('slug', 'like', "%{$search}%"),
                    )),
                TextColumn::make('package_tier')
                    ->label('Package')
                    ->badge()
                    ->formatStateUsing(function (?string $state, $record): string {
                        return $record instanceof Application
                            ? TierLabels::staffLabel($record)
                            : TierLabels::label($state);
                    })
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
                    ->options(Application::workflowStatusLabels())
                    ->multiple(),
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
                self::maintenanceStageFilter(),
                self::maintenanceBillingFilter(),
                self::photoQueueFilter(),
                self::prepublicationRevisionQueueFilter(),
                self::customerPreviewQueueFilter(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Application $record): bool => auth()->user()?->can('update', $record) ?? false),
            ]);
    }

    /**
     * Maintenance-stage filter over the OPEN post-publication request only —
     * completed historical cycles never make an application match.
     */
    private static function maintenanceStageFilter(): SelectFilter
    {
        return SelectFilter::make('maintenance_stage')
            ->label('Maintenance stage')
            ->indicator('Maintenance')
            ->options([
                'open' => 'All open maintenance',
                'prep' => 'Needs editorial work',
                'awaiting_customer' => 'Awaiting customer',
                'ready' => 'Ready to publish',
                'correction' => 'Customer correction pending',
            ])
            ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                'open' => $query->whereHas('editorialRevisionRequests', fn (Builder $q) => self::openMaintenance($q)),
                'prep' => $query->whereHas('editorialRevisionRequests', fn (Builder $q) => self::openMaintenance($q)->where(function (Builder $qq): void {
                    $qq->where('status', EditorialRevisionRequest::STATUS_SUBMITTED)
                        ->orWhere(fn (Builder $w) => $w
                            ->where('status', EditorialRevisionRequest::STATUS_IN_PROGRESS)
                            ->whereNull('customer_correction_text'));
                })),
                'awaiting_customer' => $query->whereHas('editorialRevisionRequests', fn (Builder $q) => self::openMaintenance($q)
                    ->where('status', EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW)),
                'ready' => $query->whereHas('editorialRevisionRequests', fn (Builder $q) => self::openMaintenance($q)
                    ->where('status', EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED)),
                'correction' => $query->whereHas('editorialRevisionRequests', fn (Builder $q) => self::openMaintenance($q)
                    ->where('status', EditorialRevisionRequest::STATUS_IN_PROGRESS)
                    ->whereNotNull('customer_correction_text')),
                default => $query,
            });
    }

    /**
     * Complimentary/paid filter over the OPEN post-publication request —
     * the snapshot classification of the current cycle, not of history.
     */
    private static function maintenanceBillingFilter(): SelectFilter
    {
        return SelectFilter::make('maintenance_billing')
            ->label('Maintenance billing')
            ->indicator('Maintenance billing')
            ->options([
                'complimentary' => 'Complimentary update',
                'paid' => 'Paid update request',
            ])
            ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                ? $query->whereHas('editorialRevisionRequests', fn (Builder $q) => self::openMaintenance($q)
                    ->where('billing_classification', $data['value']))
                : $query);
    }

    /**
     * Photograph-queue filter: applications with photos awaiting approval.
     */
    private static function photoQueueFilter(): SelectFilter
    {
        return SelectFilter::make('photo_queue')
            ->label('Photograph queue')
            ->indicator('Photos pending')
            ->options([
                'pending' => 'Photographs awaiting approval',
            ])
            ->query(fn (Builder $query, array $data): Builder => ($data['value'] ?? null) === 'pending'
                ? $query->whereHas('profile.media', fn (Builder $q) => $q
                    ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                    ->where('review_status', MediaItem::REVIEW_PENDING))
                : $query);
    }

    /**
     * Pre-publication revision queue: applications with an open customer
     * revision/factual-correction request. Matches PendingAttentionWidget's
     * revision category (submitted/in_progress, not published_update),
     * regardless of the application's current workflow status.
     */
    private static function prepublicationRevisionQueueFilter(): SelectFilter
    {
        return SelectFilter::make('prepublication_revision_queue')
            ->label('Pre-publication revision queue')
            ->indicator('Pre-pub revisions')
            ->options([
                'open' => 'Open pre-publication revision requests',
            ])
            ->query(fn (Builder $query, array $data): Builder => ($data['value'] ?? null) === 'open'
                ? $query->whereHas('editorialRevisionRequests', fn (Builder $q) => self::openPrepublicationRevision($q))
                : $query);
    }

    /**
     * Customer-preview queue: applications whose preview has been released and
     * which are awaiting customer review. Matches PendingAttentionWidget's
     * customer category (editorial_approved + customer_preview_released_at set).
     * Distinct from the generic workflow status filter.
     */
    private static function customerPreviewQueueFilter(): SelectFilter
    {
        return SelectFilter::make('customer_preview_queue')
            ->label('Customer preview queue')
            ->indicator('Customer review')
            ->options([
                'awaiting_review' => 'Awaiting customer review (preview released)',
            ])
            ->query(fn (Builder $query, array $data): Builder => ($data['value'] ?? null) === 'awaiting_review'
                ? $query
                    ->where('status', Application::STATUS_EDITORIAL_APPROVED)
                    ->whereNotNull('customer_preview_released_at')
                : $query);
    }

    /** @param Builder<EditorialRevisionRequest> $query */
    private static function openPrepublicationRevision(Builder $query): Builder
    {
        return $query
            ->where('request_type', '!=', EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)
            ->whereIn('status', [
                EditorialRevisionRequest::STATUS_SUBMITTED,
                EditorialRevisionRequest::STATUS_IN_PROGRESS,
            ]);
    }

    /** @param Builder<EditorialRevisionRequest> $query */
    private static function openMaintenance(Builder $query): Builder
    {
        return $query
            ->where('request_type', EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)
            ->whereIn('status', [
                EditorialRevisionRequest::STATUS_SUBMITTED,
                EditorialRevisionRequest::STATUS_IN_PROGRESS,
                EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW,
                EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED,
            ]);
    }
}
