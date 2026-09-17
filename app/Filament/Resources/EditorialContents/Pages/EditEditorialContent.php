<?php

namespace App\Filament\Resources\EditorialContents\Pages;

use App\Filament\Resources\EditorialContents\EditorialContentResource;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\StaffAuditLogger;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEditorialContent extends EditRecord
{
    protected static string $resource = EditorialContentResource::class;

    /** @var array<string, mixed> */
    private array $auditBefore = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = auth()->user();
        if ($actor instanceof User && (($data['status'] ?? null) === 'approved')) {
            $data['reviewed_by_id'] = $actor->id;
        }
        $data['ai_generated'] = (bool) ($this->record->ai_generated ?? false);

        return $data;
    }

    protected function beforeSave(): void
    {
        /** @var EditorialContent $record */
        $record = $this->record;
        $this->auditBefore = [
            'status' => $record->status,
            'title' => $record->title,
            'language' => $record->language,
            'version_number' => $record->version_number,
        ];
    }

    protected function afterSave(): void
    {
        /** @var EditorialContent $record */
        $record = $this->record;
        app(StaffAuditLogger::class)->log(
            action: 'editorial_content.updated',
            subject: $record,
            before: $this->auditBefore,
            after: [
                'status' => $record->status,
                'title' => $record->title,
                'language' => $record->language,
                'version_number' => $record->version_number,
            ],
        );
    }
}
