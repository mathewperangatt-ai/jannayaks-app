<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id'),
            TextEntry::make('name'),
            TextEntry::make('email'),
            TextEntry::make('role')->badge(),
            TextEntry::make('account_status')->badge(),
            TextEntry::make('email_verified_at')->dateTime(),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }
}
