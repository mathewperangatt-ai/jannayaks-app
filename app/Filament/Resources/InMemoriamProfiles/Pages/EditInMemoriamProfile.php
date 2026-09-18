<?php

namespace App\Filament\Resources\InMemoriamProfiles\Pages;

use App\Filament\Resources\InMemoriamProfiles\InMemoriamProfileResource;
use App\Models\InMemoriamProfile;
use App\Models\User;
use App\Services\InMemoriamUrlService;
use App\Services\StaffAuditLogger;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditInMemoriamProfile extends EditRecord
{
    protected static string $resource = InMemoriamProfileResource::class;

    private ?string $pendingSlug = null;

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
        /** @var InMemoriamProfile $record */
        $record = $this->getRecord();
        if ($record->is_sealed) {
            throw ValidationException::withMessages([
                'status' => 'Sealed memorials cannot be routinely edited.',
            ]);
        }

        if (($data['status'] ?? null) === InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED) {
            unset($data['status']);
        }

        $slug = $data['slug'] ?? null;
        unset($data['slug']);
        $this->pendingSlug = filled($slug) ? (string) $slug : null;

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var InMemoriamProfile $record */
        $record = $this->getRecord();
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return;
        }

        app(StaffAuditLogger::class)->log(
            action: 'in_memoriam.updated',
            subject: $record,
            before: null,
            after: [
                'status' => $record->status,
                'verification_status' => $record->verification_status,
            ],
            actor: $actor,
        );

        if (filled($this->pendingSlug) && (string) $record->slug !== $this->pendingSlug) {
            app(InMemoriamUrlService::class)->assignSlug($record, $this->pendingSlug, $actor);
        }
    }
}
