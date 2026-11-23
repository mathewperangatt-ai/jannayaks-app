<?php

namespace App\Filament\Resources\PhotoReplacementRequests\Pages;

use App\Filament\Resources\PhotoReplacementRequests\PhotoReplacementRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePhotoReplacementRequests extends ManageRecords
{
    protected static string $resource = PhotoReplacementRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
