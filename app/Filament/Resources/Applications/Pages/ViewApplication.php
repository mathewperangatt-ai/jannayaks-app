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
                ->visible(fn (): bool => (auth()->user()?->canManageEditorial() ?? false)
                    && $this->getRecord()?->status !== Application::STATUS_PUBLISHED
                    && ! $this->hasOpenMaintenanceRequest())
                ->action(function (): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        abort(403);
                    }

                    /** @var Application $application */
                    $application = $this->getRecord();

                    try {
                        $run = app(EditorialGenerationService::class)->generateForApplication($application, $actor);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Cannot start AI generation')
                            ->body($e->getMessage())
                            ->warning()
                            ->send();

                        return;
                    }

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
                ->visible(fn (): bool => (auth()->user()?->canManageEditorial() ?? false)
                    && $this->getRecord()?->status !== Application::STATUS_PUBLISHED
                    && ! $this->hasOpenMaintenanceRequest())
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
                ->visible(fn (): bool => (auth()->user()?->isAdmin() ?? false)
                    && ! $this->hasOpenMaintenanceRequest())
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
            Action::make('savePreparedVersions')
                ->label('Save prepared EN/ML versions')
                ->color('gray')
                ->visible(fn (): bool => (auth()->user()?->canManageEditorial() ?? false)
                    && ($this->maintenanceRequest()?->isAwaitingPreparation() === true))
                ->form([
                    \Filament\Schemas\Components\Section::make('English — prepared version')
                        ->description('Applied to the cycle\'s current proposed English version. Locked versions get an immutable successor.')
                        ->schema([
                            \Filament\Forms\Components\TextInput::make('en_title')->label('Title')->maxLength(255),
                            \Filament\Forms\Components\Textarea::make('en_summary')->label('Summary')->rows(3)->maxLength(2000),
                            \Filament\Forms\Components\Textarea::make('en_body')->label('Body')->rows(8)->maxLength(20000),
                        ])
                        ->columns(1),
                    \Filament\Schemas\Components\Section::make('Malayalam — prepared version (paired to this English master)')
                        ->description('Applied to the cycle\'s current proposed Malayalam version. Its EN↔ML pairing is established now, at creation — never later.')
                        ->schema([
                            \Filament\Forms\Components\TextInput::make('ml_title')->label('Title (Malayalam)')->maxLength(255),
                            \Filament\Forms\Components\Textarea::make('ml_summary')->label('Summary (Malayalam)')->rows(3)->maxLength(2000),
                            \Filament\Forms\Components\Textarea::make('ml_body')->label('Body (Malayalam)')->rows(8)->maxLength(20000),
                        ])
                        ->columns(1),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        abort(403);
                    }

                    /** @var Application $application */
                    $application = $this->getRecord();

                    $enData = array_filter([
                        'title' => $data['en_title'] ?? null,
                        'summary' => $data['en_summary'] ?? null,
                        'body' => $data['en_body'] ?? null,
                    ], fn ($v): bool => filled($v));

                    $mlData = array_filter([
                        'title' => $data['ml_title'] ?? null,
                        'summary' => $data['ml_summary'] ?? null,
                        'body' => $data['ml_body'] ?? null,
                    ], fn ($v): bool => filled($v));

                    if ($enData === [] && $mlData === []) {
                        Notification::make()->title('Nothing to save')->body('Enter the prepared English and/or Malayalam text.')->warning()->send();

                        return;
                    }

                    try {
                        $request = app(\App\Services\PostPublicationUpdateService::class)->prepareVersions(
                            $application,
                            $actor,
                            $enData === [] ? null : $enData,
                            $mlData === [] ? null : $mlData,
                        );
                        Notification::make()
                            ->title('Prepared versions saved')
                            ->body('EN/ML pairing is established. Review and approve the draft versions (Editorial contents), then release for customer approval.')
                            ->success()
                            ->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Cannot save prepared versions')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('generateMaintenanceDraft')
                ->label('Prepare AI maintenance draft')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Prepare AI draft for this maintenance request')
                ->modalDescription('Uses the existing AI generation pipeline with the current published profile and the customer\'s update request as source material. Drafts are drafts — editorial review follows. AI cannot publish.')
                ->visible(fn (): bool => (auth()->user()?->canManageEditorial() ?? false)
                    && $this->maintenanceRequest()?->isAwaitingPreparation() === true)
                ->action(function (): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        abort(403);
                    }

                    /** @var Application $application */
                    $application = $this->getRecord();

                    try {
                        $request = app(\App\Services\PostPublicationUpdateService::class)->prepareViaAiDraft($application, $actor);
                        Notification::make()
                            ->title('AI maintenance draft prepared')
                            ->body('Draft EN/ML versions are linked to the maintenance request and await editorial review. AI output is never released to the customer directly.')
                            ->success()
                            ->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Cannot prepare AI draft')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('releaseMaintenancePreview')
                ->label('Release for customer approval')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Release the prepared version for customer approval')
                ->modalDescription('Resolves the latest approved EN/ML successors prepared for this maintenance request, shows them to the customer, and records their approval or corrections. The live public profile does not change.')
                ->visible(fn (): bool => (auth()->user()?->canManageEditorial() ?? false)
                    && ($this->maintenanceRequest()?->isAwaitingPreparation() === true))
                ->action(function (): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        abort(403);
                    }

                    /** @var Application $application */
                    $application = $this->getRecord();

                    try {
                        app(\App\Services\PostPublicationUpdateService::class)->releaseMaintenancePreview($application, $actor);
                        Notification::make()->title('Maintenance preview released')->body('The customer can now approve the updated profile or request minor corrections.')->success()->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Cannot release maintenance preview')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('publishMaintenanceUpdate')
                ->label('Publish approved update')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish the customer-approved maintenance update')
                ->modalDescription('Admin only. Publishes the customer-approved EN/ML versions as the new public profile. Previous published versions remain as immutable history. Audited.')
                ->visible(fn (): bool => (auth()->user()?->isAdmin() ?? false)
                    && ($this->maintenanceRequest()?->status === \App\Models\EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED))
                ->action(function (): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        abort(403);
                    }

                    /** @var Application $application */
                    $application = $this->getRecord();

                    try {
                        app(\App\Services\PostPublicationUpdateService::class)->completePublication($application, $actor);
                        Notification::make()->title('Maintenance update published')->body('The approved EN/ML versions are now the published profile. The maintenance request is completed.')->success()->send();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Cannot publish maintenance update')->body($e->getMessage())->danger()->send();
                    }
                }),
            EditAction::make()
                ->visible(fn (): bool => static::getResource()::canEdit($this->getRecord())),
        ];
    }

    private function maintenanceRequest(): ?\App\Models\EditorialRevisionRequest
    {
        return app(\App\Services\PostPublicationUpdateService::class)->openRequestFor($this->getRecord());
    }

    private function hasOpenMaintenanceRequest(): bool
    {
        return $this->maintenanceRequest() !== null;
    }
}
