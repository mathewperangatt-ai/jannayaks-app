<?php

namespace App\Filament\Resources\CustomerApprovals\Pages;

use App\Filament\Resources\CustomerApprovals\CustomerApprovalResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerApproval extends ViewRecord
{
    protected static string $resource = CustomerApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
