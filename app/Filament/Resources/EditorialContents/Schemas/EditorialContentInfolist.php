<?php

namespace App\Filament\Resources\EditorialContents\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EditorialContentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id'),
            TextEntry::make('profile_id'),
            TextEntry::make('language')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('version_number'),
            TextEntry::make('title'),
            TextEntry::make('summary'),
            TextEntry::make('body')->columnSpanFull(),
            TextEntry::make('source_material')->columnSpanFull(),
            IconEntry::make('ai_generated')->boolean(),
            TextEntry::make('createdBy.email')->label('Created by'),
            TextEntry::make('reviewedBy.email')->label('Reviewed by'),
            TextEntry::make('review_comment'),
        ]);
    }
}
