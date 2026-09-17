<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialGenerationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
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
                        ->success()
                        ->send();
                }),
            Action::make('releaseCustomerPreview')
                ->label('Release for customer preview')
                ->requiresConfirmation()
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

        return app(ApplicationWorkflowService::class)->updateStaffFields($record, $data, $actor);
    }
}
