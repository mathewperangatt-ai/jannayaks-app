<?php

namespace App\Filament\Resources\EditorialContents\Pages;

use App\Filament\Resources\EditorialContents\EditorialContentResource;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\EditorialContentVersioningService;
use App\Services\StaffAuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateEditorialContent extends CreateRecord
{
    protected static string $resource = EditorialContentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        if ($actor instanceof User) {
            $data['created_by_id'] = $actor->id;
        }
        $data['ai_generated'] = false;

        try {
            return app(EditorialContentVersioningService::class)->attributesForNewDraft($data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'status' => $e->getMessage(),
            ]);
        }
    }

    protected function afterCreate(): void
    {
        /** @var EditorialContent $record */
        $record = $this->record;
        app(StaffAuditLogger::class)->log(
            action: 'editorial_content.created',
            subject: $record,
            after: [
                'profile_id' => $record->profile_id,
                'language' => $record->language,
                'status' => $record->status,
                'version_number' => $record->version_number,
            ],
        );
    }
}
