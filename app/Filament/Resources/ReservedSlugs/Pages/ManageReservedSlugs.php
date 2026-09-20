<?php

namespace App\Filament\Resources\ReservedSlugs\Pages;

use App\Filament\Resources\ReservedSlugs\ReservedSlugResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReservedSlugs extends ManageRecords
{
    protected static string $resource = ReservedSlugResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['slug'] = strtolower(trim((string) ($data['slug'] ?? '')));
                    $data['created_by'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
