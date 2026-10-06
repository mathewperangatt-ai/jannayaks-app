<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InterviewAnswersRelationManager extends RelationManager
{
    protected static string $relationship = 'interviewAnswers';

    protected static ?string $title = 'Interview answers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question_id')->label('Question')->sortable(),
                TextColumn::make('original_answer')->label('Answer')->wrap()->limit(120),
                TextColumn::make('answered_at')->dateTime()->sortable(),
            ])
            ->defaultSort('question_id')
            ->emptyStateHeading(fn (): string => $this->getOwnerRecord()->source_method === 'online_interview'
                ? 'Interview not submitted yet'
                : 'No Online Interview exists')
            ->emptyStateDescription(fn (): string => $this->getOwnerRecord()->source_method === 'online_interview'
                ? 'The customer\'s answers will appear here once the Online Interview is submitted for editorial processing.'
                : 'This application has no Online Interview record (direct submission or admin test/demo lane). Supplied files appear under Source materials.')
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->canAccessSourceMaterial() ?? false;
    }
}
