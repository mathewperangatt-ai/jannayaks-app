<?php

namespace App\Filament\Resources\EditorialContents\Pages;

use App\Filament\Resources\EditorialContents\EditorialContentResource;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialContentVersioningService;
use App\Services\StaffAuditLogger;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEditorialContent extends EditRecord
{
    protected static string $resource = EditorialContentResource::class;

    /** @var array<string, mixed> */
    private array $auditBefore = [];

    private bool $createdSuccessorVersion = false;

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
            'body' => $record->body,
            'summary' => $record->summary,
            'language' => $record->language,
            'version_number' => $record->version_number,
        ];
        $this->createdSuccessorVersion = false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        /** @var EditorialContent $record */
        $versioning = app(EditorialContentVersioningService::class);
        $result = $versioning->applyEditorUpdate($record, $data, $actor);

        if ($result->getKey() !== $record->getKey()) {
            $this->createdSuccessorVersion = true;
            $this->record = $result;

            return $result;
        }

        return $result;
    }

    protected function afterSave(): void
    {
        /** @var EditorialContent $record */
        $record = $this->record;

        if ($this->createdSuccessorVersion) {
            // Versioning service already audited + invalidated prior preview/approval bindings.
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]));

            return;
        }

        app(StaffAuditLogger::class)->log(
            action: 'editorial_content.updated',
            subject: $record,
            before: [
                'status' => $this->auditBefore['status'] ?? null,
                'title' => $this->auditBefore['title'] ?? null,
                'language' => $this->auditBefore['language'] ?? null,
                'version_number' => $this->auditBefore['version_number'] ?? null,
            ],
            after: [
                'status' => $record->status,
                'title' => $record->title,
                'language' => $record->language,
                'version_number' => $record->version_number,
            ],
        );

        $contentChanged = ($this->auditBefore['title'] ?? null) !== $record->title
            || ($this->auditBefore['body'] ?? null) !== $record->body
            || ($this->auditBefore['summary'] ?? null) !== $record->summary
            || ($this->auditBefore['status'] ?? null) !== $record->status;

        if ($contentChanged) {
            $actor = auth()->user() instanceof User ? auth()->user() : null;
            app(CustomerEditorialWorkflowService::class)
                ->invalidateApprovalForEditorialContent(
                    $record,
                    $actor,
                    'Editorial content updated after customer preview/approval.',
                );
        }
    }
}
