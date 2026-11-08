<?php

namespace App\Filament\Resources\Applications\Pages\Concerns;

use App\Filament\Resources\EditorialContents\EditorialContentResource;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialGenerationService;
use App\Services\PostPublicationUpdateService;
use App\Support\EditorialTestBypass;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use InvalidArgumentException;

/**
 * Shared Filament header actions for pre-publication AI draft generation and
 * customer-preview release. Used by EditApplication and ViewApplication so
 * visibility and wiring stay aligned. Maintenance / publish actions stay
 * outside this trait.
 */
trait HasPrePublicationEditorialActions
{
    protected function generateAiEditorialHeaderAction(): Action
    {
        return Action::make('generateAiEditorial')
            ->label('Generate AI editorial draft')
            ->requiresConfirmation()
            ->modalHeading('Generate English + Malayalam AI draft')
            ->modalDescription('Creates new draft editorial versions from interview/source material. Approved content is not overwritten. AI cannot publish.')
            ->visible(fn (): bool => $this->prePublicationEditorialActionsOpen())
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
                        ->persistent()->send();

                    return;
                }

                if ($run->isFailed()) {
                    Notification::make()
                        ->title('AI generation failed')
                        ->body($run->error_message ?: 'The draft was not marked complete.')
                        ->danger()
                        ->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title('AI editorial drafts created')
                    ->body('English master and Malayalam adaptation stored as draft versions for human review.')
                    ->success()
                    ->persistent()->send();
            });
    }

    protected function releaseCustomerPreviewHeaderAction(): Action
    {
        return Action::make('releaseCustomerPreview')
            ->label('Release for customer preview')
            ->requiresConfirmation()
            ->modalHeading('Release profile for customer preview')
            ->modalDescription(function (): string {
                $record = $this->getRecord();

                return app(EditorialTestBypass::class)->releaseModalDescription(
                    $record instanceof Application ? $record : null,
                );
            })
            ->visible(fn (): bool => $this->prePublicationEditorialActionsOpen())
            ->action(function (): void {
                $actor = auth()->user();
                if (! $actor instanceof User) {
                    abort(403);
                }

                try {
                    /** @var Application $application */
                    $application = $this->getRecord();
                    app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $actor);
                    Notification::make()->title('Customer preview released')->success()->persistent()->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Cannot release preview')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    protected function editEnglishDraftHeaderAction(): Action
    {
        return Action::make('editEnglishDraft')
            ->label('Edit English draft')
            ->color('gray')
            ->url(function (): ?string {
                $draft = $this->englishMasterAwaitingApproval();

                return $draft instanceof EditorialContent
                    ? EditorialContentResource::getUrl('edit', ['record' => $draft])
                    : null;
            })
            ->visible(fn (): bool => $this->prePublicationEditorialActionsOpen()
                && $this->englishMasterAwaitingApproval() instanceof EditorialContent);
    }

    protected function approveEnglishMasterHeaderAction(): Action
    {
        return Action::make('approveEnglishMaster')
            ->label('Approve English')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve the English editorial master')
            ->modalDescription(function (): string {
                $draft = $this->englishMasterAwaitingApproval();
                $version = $draft instanceof EditorialContent ? ' (version '.$draft->version_number.')' : '';

                return 'Marks the current English draft'.$version.' as editorially Approved and records you as the reviewer. '
                    .'It does not edit the text, release the customer preview, record customer approval, approve Malayalam, or publish.';
            })
            ->modalSubmitActionLabel('Approve English')
            ->visible(fn (): bool => $this->prePublicationEditorialActionsOpen()
                && $this->actorMayApproveEnglish()
                && $this->englishMasterAwaitingApproval() instanceof EditorialContent)
            ->action(function (): void {
                $actor = auth()->user();
                if (! $actor instanceof User || ! $actor->isAdmin() || ! $actor->isActiveAccount()) {
                    abort(403);
                }

                try {
                    /** @var Application $application */
                    $application = $this->getRecord();
                    $approved = app(ApplicationWorkflowService::class)->approveEnglishMaster($application, $actor);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Cannot approve English')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title('English approved')
                    ->body('English version '.$approved->version_number.' is Approved. Nothing was published. Use Release for customer preview next.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    protected function englishMasterAwaitingApproval(): ?EditorialContent
    {
        $record = $this->getRecord();

        return $record instanceof Application
            ? app(ApplicationWorkflowService::class)->englishMasterAwaitingApproval($record)
            : null;
    }

    protected function actorMayApproveEnglish(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->isAdmin() && $actor->isActiveAccount();
    }

    protected function prePublicationEditorialActionsOpen(): bool
    {
        $record = $this->getRecord();
        if (! $record instanceof Application) {
            return false;
        }
        if (! (auth()->user()?->canManageEditorial() ?? false)) {
            return false;
        }
        if ($record->status === Application::STATUS_PUBLISHED) {
            return false;
        }

        return app(PostPublicationUpdateService::class)->openRequestFor($record) === null;
    }
}
