<?php

namespace App\Filament\Resources\CustomerApprovals\Pages;

use App\Filament\Resources\CustomerApprovals\CustomerApprovalResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerApprovals extends ListRecords
{
    protected static string $resource = CustomerApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
