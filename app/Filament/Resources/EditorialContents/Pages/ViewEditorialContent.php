<?php

namespace App\Filament\Resources\EditorialContents\Pages;

use App\Filament\Resources\EditorialContents\EditorialContentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEditorialContent extends ViewRecord
{
    protected static string $resource = EditorialContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
