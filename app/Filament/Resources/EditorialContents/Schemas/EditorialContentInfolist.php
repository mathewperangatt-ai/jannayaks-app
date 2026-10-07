<?php

namespace App\Filament\Resources\EditorialContents\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditorialContentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            /*
             * Content-first: the editorial text is primary; AI generation and
             * QA metadata are supporting information below it.
             */
            Section::make('Editorial content')
                ->schema([
                    TextEntry::make('language')->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('version_number')->label('Version'),
                    TextEntry::make('title'),
                    TextEntry::make('summary')
                        ->columnSpanFull(),
                    TextEntry::make('body')
                        ->columnSpanFull()
                        ->helperText('Stored as plain text. Filament escapes on display — not raw HTML.'),
                    TextEntry::make('source_material')
                        ->label('Editorial notes / draft marker')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('AI generation & editorial QA')
                ->description('Supporting metadata — where this version came from and what still needs human review. The editorial content above is primary.')
                ->schema([
                    IconEntry::make('ai_generated')->boolean()->label('AI draft'),
                    TextEntry::make('generation_run_id')->label('AI run ID')->placeholder('—'),
                    TextEntry::make('generationRun.provider')->label('AI provider')->placeholder('—'),
                    TextEntry::make('generationRun.model')->label('AI model')->placeholder('—'),
                    TextEntry::make('generationRun.status')->label('AI run status')->badge()->placeholder('—'),
                    TextEntry::make('review_flags')
                        ->label('Editorial review flags')
                        ->badge()
                        ->placeholder('—')
                        ->helperText('Internal workflow only — a flag means human review is required, never that content is forbidden. Not shown publicly.')
                        ->state(fn ($record): array => $record?->review_flags ?? []),
                    TextEntry::make('claimTraces_count')
                        ->label('Internal claim→source QA traces')
                        ->helperText('Editorial QA only — not verification, not a public badge.')
                        ->state(fn ($record): int => $record?->claimTraces()->count() ?? 0),
                    TextEntry::make('claimTraces_mapped_count')
                        ->label('Traces mapped to source rows')
                        ->helperText('Count of traces where a questionnaire/source row was found. Still not independent fact verification.')
                        ->state(fn ($record): int => $record?->claimTraces()->where('mapped_to_source', true)->count() ?? 0),
                ])
                ->columns(2),
            Section::make('Record')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('profile_id'),
                    TextEntry::make('source_editorial_content_id')
                        ->label('English master ID')
                        ->placeholder('— (this may be the English master)'),
                    TextEntry::make('createdBy.email')->label('Created by'),
                    TextEntry::make('reviewedBy.email')->label('Reviewed by'),
                    TextEntry::make('review_comment'),
                ])
                ->columns(2)
                ->collapsed(),
        ]);
    }
}
