<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\Concerns\HasPrePublicationEditorialActions;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EditApplication extends EditRecord
{
    use HasPrePublicationEditorialActions;

    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->generateAiEditorialHeaderAction(),
            $this->releaseCustomerPreviewHeaderAction(),
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
}
