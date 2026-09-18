<?php

namespace App\Filament\Resources\InMemoriamProfiles\Pages;

use App\Filament\Resources\InMemoriamProfiles\InMemoriamProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInMemoriamProfiles extends ListRecords
{
    protected static string $resource = InMemoriamProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
