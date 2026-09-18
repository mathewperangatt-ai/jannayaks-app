<?php

namespace App\Filament\Resources\InMemoriamProfiles\RelationManagers;

use App\Models\InMemoriamEditorialContent;
use App\Models\InMemoriamProfile;
use App\Models\User;
use App\Services\InMemoriamEditorialService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MemorialEditorialRelationManager extends RelationManager
{
    protected static string $relationship = 'editorialContents';

    protected static ?string $title = 'Memorial narrative';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('language')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('version_number')->label('Ver.'),
                IconColumn::make('ai_generated')->boolean()->label('AI'),
                TextColumn::make('title')->limit(40),
                TextColumn::make('updated_at')->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                Action::make('saveEn')
                    ->label('Save English')
                    ->visible(fn (): bool => auth()->user()?->can('manageEditorial', $this->getOwnerRecord()) ?? false)
                    ->form($this->formFor('en'))
                    ->action(fn (array $data) => $this->save('en', $data)),
                Action::make('saveMl')
                    ->label('Save Malayalam')
                    ->visible(fn (): bool => auth()->user()?->can('manageEditorial', $this->getOwnerRecord()) ?? false)
                    ->form($this->formFor('ml'))
                    ->action(fn (array $data) => $this->save('ml', $data)),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * @return array<int, mixed>
     */
    private function formFor(string $language): array
    {
        /** @var InMemoriamProfile $owner */
        $owner = $this->getOwnerRecord();
        $existing = $owner->editorialContents()
            ->where('language', $language)
            ->where('status', '!=', InMemoriamEditorialContent::STATUS_ARCHIVED)
            ->orderByDesc('version_number')
            ->first();

        return [
            TextInput::make('title')->default($existing?->title)->required()->maxLength(500),
            Textarea::make('summary')->default($existing?->summary)->rows(3),
            Textarea::make('body')->default($existing?->body)->rows(14)->required(),
            Textarea::make('source_material')->label('Family-supplied notes')->default($existing?->source_material)->rows(3),
            Select::make('status')
                ->options([
                    InMemoriamEditorialContent::STATUS_DRAFT => 'Draft',
                    InMemoriamEditorialContent::STATUS_APPROVED => 'Approved',
                ])
                ->default($existing?->status ?? InMemoriamEditorialContent::STATUS_APPROVED)
                ->required()
                ->native(false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(string $language, array $data): void
    {
        $actor = auth()->user();
        /** @var InMemoriamProfile $owner */
        $owner = $this->getOwnerRecord();
        if (! $actor instanceof User) {
            return;
        }

        try {
            $content = app(InMemoriamEditorialService::class)->upsertHumanContent($owner, $actor, $language, $data);
            if ($content->ai_generated) {
                Notification::make()->title('Unexpected AI flag')->danger()->send();

                return;
            }
            Notification::make()->title('Narrative saved')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Save failed')->body(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canViewApplicationQueue();
    }
}
