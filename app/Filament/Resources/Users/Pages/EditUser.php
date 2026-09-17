<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\StaffAuditLogger;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<string, mixed> */
    private array $auditBefore = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        /** @var User $record */
        $record = $this->record;
        $this->auditBefore = [
            'email' => $record->email,
            'role' => $record->role,
            'account_status' => $record->account_status,
            'name' => $record->name,
        ];
    }

    protected function afterSave(): void
    {
        /** @var User $record */
        $record = $this->record;
        app(StaffAuditLogger::class)->log(
            action: 'user.updated',
            subject: $record,
            before: $this->auditBefore,
            after: [
                'email' => $record->email,
                'role' => $record->role,
                'account_status' => $record->account_status,
                'name' => $record->name,
            ],
        );
    }
}
