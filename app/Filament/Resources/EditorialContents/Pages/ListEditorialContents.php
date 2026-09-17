<?php

namespace App\Filament\Resources\EditorialContents\Pages;

use App\Filament\Resources\EditorialContents\EditorialContentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEditorialContents extends ListRecords
{
    protected static string $resource = EditorialContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
