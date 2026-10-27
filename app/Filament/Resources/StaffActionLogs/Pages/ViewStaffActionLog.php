<?php

namespace App\Filament\Resources\StaffActionLogs\Pages;

use App\Filament\Resources\StaffActionLogs\StaffActionLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStaffActionLog extends ViewRecord
{
    protected static string $resource = StaffActionLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
