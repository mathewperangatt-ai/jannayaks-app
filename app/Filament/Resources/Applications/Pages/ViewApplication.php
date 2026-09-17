<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialGenerationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateAiEditorial')
                ->label('Generate AI editorial draft')
                ->requiresConfirmation()
                ->modalHeading('Generate English + Malayalam AI draft')
                ->modalDescription('Creates new draft editorial versions from interview/source material. Approved content is not overwritten. AI cannot publish.')
                ->visible(fn (): bool => auth()->user()?->canManageEditorial() ?? false)
                ->action(function (): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        abort(403);
                    }

                    /** @var Application $application */
                    $application = $this->getRecord();
                    $run = app(EditorialGenerationService::class)->generateForApplication($application, $actor);

                    if ($run->isFailed()) {
                        Notification::make()
                            ->title('AI generation failed')
                            ->body($run->error_message ?: 'The draft was not marked complete.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('AI editorial drafts created')
                        ->body('English master and Malayalam adaptation stored as draft versions for human review.')
                        ->success()
                        ->send();
                }),
            Action::make('releaseCustomerPreview')
                ->label('Release for customer preview')
                ->requiresConfirmation()
                ->modalHeading('Release profile for customer preview')
                ->modalDescription('Requires an approved English editorial master. The member can then review, request included revisions, or approve. Does not publish.')
                ->visible(fn (): bool => auth()->user()?->canManageEditorial() ?? false)
                ->action(function (): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        abort(403);
                    }

                    try {
                        /** @var Application $application */
                        $application = $this->getRecord();
                        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $actor);
                        Notification::make()->title('Customer preview released')->success()->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Cannot release preview')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('publishApplication')
                ->label('Publish')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish this profile')
                ->modalDescription('Admin only. Normal path requires customer approval. Exceptional offline/admin publication is audited.')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->form([
                    Toggle::make('require_customer_approval')
                        ->label('Require customer approval (normal path)')
                        ->default(true)
                        ->helperText('Turn off only for authorised offline/admin exceptional publication.'),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        abort(403);
                    }

                    try {
                        /** @var Application $application */
                        $application = $this->getRecord();
                        app(ApplicationWorkflowService::class)->publish(
                            $application,
                            $actor,
                            (bool) ($data['require_customer_approval'] ?? true),
                        );
                        Notification::make()->title('Profile published')->success()->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Cannot publish')->body($e->getMessage())->danger()->send();
                    }
                }),
            EditAction::make()
                ->visible(fn (): bool => static::getResource()::canEdit($this->getRecord())),
        ];
    }
}
