<?php

namespace App\Filament\Resources\StaffActionLogs\Pages;

use App\Filament\Resources\StaffActionLogs\StaffActionLogResource;
use Filament\Resources\Pages\ListRecords;

class ListStaffActionLogs extends ListRecords
{
    protected static string $resource = StaffActionLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
