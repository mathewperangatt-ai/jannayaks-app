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
            TextEntry::make('source_editorial_content_id')
                ->label('English master ID')
                ->placeholder('— (this may be the English master)'),
            TextEntry::make('generation_run_id')->label('AI run')->placeholder('—'),
            TextEntry::make('generationRun.status')
                ->label('AI run status')
                ->badge()
                ->placeholder('—'),
            TextEntry::make('title'),
            TextEntry::make('summary'),
            TextEntry::make('body')
                ->columnSpanFull()
                ->helperText('Stored as plain text. Filament escapes on display — not raw HTML.'),
            TextEntry::make('source_material')
                ->label('Editorial notes / draft marker')
                ->columnSpanFull(),
            IconEntry::make('ai_generated')->boolean()->label('AI draft'),
            TextEntry::make('claimTraces_count')
                ->label('Internal claim→source QA traces')
                ->helperText('Editorial QA only — not verification, not a public badge.')
                ->state(fn ($record): int => $record?->claimTraces()->count() ?? 0),
            TextEntry::make('claimTraces_mapped_count')
                ->label('Traces mapped to source rows')
                ->helperText('Count of traces where a questionnaire/source row was found. Still not independent fact verification.')
                ->state(fn ($record): int => $record?->claimTraces()->where('mapped_to_source', true)->count() ?? 0),
            TextEntry::make('createdBy.email')->label('Created by'),
            TextEntry::make('reviewedBy.email')->label('Reviewed by'),
            TextEntry::make('review_comment'),
        ]);
    }
}
