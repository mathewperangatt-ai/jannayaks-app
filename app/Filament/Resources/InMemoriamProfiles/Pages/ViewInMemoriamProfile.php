<?php

namespace App\Filament\Resources\InMemoriamProfiles\Pages;

use App\Filament\Resources\InMemoriamProfiles\InMemoriamProfileResource;
use App\Models\InMemoriamProfile;
use App\Models\User;
use App\Services\InMemoriamLifecycleService;
use App\Services\InMemoriamUrlService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewInMemoriamProfile extends ViewRecord
{
    protected static string $resource = InMemoriamProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordOfflinePayment')
                ->label('Record offline package payment')
                ->visible(fn (): bool => (auth()->user()?->can('recordOfflinePayment', $this->getRecord()) ?? false)
                    && ! $this->getRecord()->isPackagePaid())
                ->requiresConfirmation()
                ->modalDescription('Records ₹25,000 + GST from PricingAmounts::forInMemoriam5yr() as a manual/offline settlement. No Razorpay checkout.')
                ->action(function (): void {
                    $actor = auth()->user();
                    /** @var InMemoriamProfile $record */
                    $record = $this->getRecord();
                    if (! $actor instanceof User) {
                        abort(403);
                    }
                    try {
                        app(InMemoriamLifecycleService::class)->recordOfflinePackagePaid($record, $actor);
                        Notification::make()->title('Offline payment recorded')->success()->send();
                        $this->refreshFormData(['commission_paid_at', 'status', 'commission_amount']);
                    } catch (ValidationException $e) {
                        Notification::make()->title('Could not record payment')->body(collect($e->errors())->flatten()->first())->danger()->send();
                    }
                }),
            Action::make('publishMemorial')
                ->label('Publish & seal')
                ->color('success')
                ->visible(fn (): bool => (auth()->user()?->can('publish', $this->getRecord()) ?? false)
                    && ! $this->getRecord()->is_sealed)
                ->form([
                    TextInput::make('slug')
                        ->label('Public URL slug')
                        ->default(fn (): ?string => $this->getRecord()->slug)
                        ->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    /** @var InMemoriamProfile $record */
                    $record = $this->getRecord();
                    if (! $actor instanceof User) {
                        abort(403);
                    }
                    try {
                        if (filled($data['slug'] ?? null) && (string) $data['slug'] !== (string) $record->slug) {
                            app(InMemoriamUrlService::class)->assignSlug($record, (string) $data['slug'], $actor);
                            $record->refresh();
                        }
                        app(InMemoriamLifecycleService::class)->publish($record, $actor);
                        Notification::make()->title('Memorial published and sealed')->success()->send();
                        $this->refreshFormData([
                            'status', 'is_sealed', 'published_at', 'hosting_starts_on', 'hosting_ends_on', 'slug',
                        ]);
                    } catch (ValidationException $e) {
                        Notification::make()->title('Cannot publish')->body(collect($e->errors())->flatten()->first())->danger()->send();
                    }
                }),
            Action::make('exceptionalCorrection')
                ->label('Admin exceptional correction')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('exceptionalCorrection', $this->getRecord()) ?? false)
                ->form([
                    TextInput::make('deceased_display_name')->default(fn () => $this->getRecord()->deceased_display_name),
                    TextInput::make('deceased_full_name')->default(fn () => $this->getRecord()->deceased_full_name),
                    TextInput::make('profession')->default(fn () => $this->getRecord()->profession),
                    TextInput::make('bio_headline')->default(fn () => $this->getRecord()->bio_headline),
                    Textarea::make('notes')->label('Correction notes')->required()->rows(3),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    /** @var InMemoriamProfile $record */
                    $record = $this->getRecord();
                    if (! $actor instanceof User) {
                        abort(403);
                    }
                    try {
                        $notes = (string) ($data['notes'] ?? '');
                        unset($data['notes']);
                        app(InMemoriamLifecycleService::class)->applyExceptionalCorrection($record, $actor, $data, $notes);
                        Notification::make()->title('Correction applied')->success()->send();
                        $this->refreshFormData([
                            'deceased_display_name', 'deceased_full_name', 'profession', 'bio_headline', 'admin_correction_notes',
                        ]);
                    } catch (ValidationException $e) {
                        Notification::make()->title('Correction failed')->body(collect($e->errors())->flatten()->first())->danger()->send();
                    }
                }),
            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false),
        ];
    }
}
