<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialGenerationService;
use App\Services\PostPublicationUpdateService;
use App\Support\EditorialTestBypass;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EditApplication extends EditRecord
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
                        ->success()
                        ->persistent()->send();
                }),
            Action::make('releaseCustomerPreview')
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
                }),
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Application $record */
        $actor = auth()->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        try {
            return app(ApplicationWorkflowService::class)->updateStaffFields($record, $data, $actor);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'status' => $e->getMessage(),
            ]);
        }
    }

    private function prePublicationEditorialActionsOpen(): bool
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
