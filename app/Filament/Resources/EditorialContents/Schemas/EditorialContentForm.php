<?php

namespace App\Filament\Resources\EditorialContents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EditorialContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('profile_id')
                ->numeric()
                ->required()
                ->helperText('Profile ID this editorial content belongs to.'),
            Select::make('language')
                ->options([
                    'en' => 'English (master)',
                    'ml' => 'Malayalam',
                ])
                ->required()
                ->native(false),
            Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'approved' => 'Approved',
                    'archived' => 'Archived',
                ])
                ->required()
                ->native(false),
            TextInput::make('version_number')
                ->numeric()
                ->default(1)
                ->required(),
            TextInput::make('title')->required()->maxLength(500),
            Textarea::make('summary')->rows(3),
            Textarea::make('body')->rows(12)->columnSpanFull(),
            Textarea::make('source_material')
                ->label('Source material notes (text)')
                ->rows(4)
                ->helperText('Editorial working notes — not a substitute for original Interview/SourceMaterial records.'),
            Toggle::make('ai_generated')
                ->disabled()
                ->dehydrated()
                ->helperText('AI generation is out of scope for P9; leave false.'),
            Textarea::make('review_comment')->rows(3),
        ]);
    }
}
