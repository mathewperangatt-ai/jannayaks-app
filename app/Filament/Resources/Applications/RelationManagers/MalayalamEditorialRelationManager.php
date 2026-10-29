<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\EditorialContentVersioningService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Application-scoped Malayalam editorial workspace with visible Edit / Save /
 * Approve controls. Publication uses ApplicationWorkflowService::publishMalayalam().
 */
class MalayalamEditorialRelationManager extends RelationManager
{
    protected static string $relationship = 'malayalamEditorialContents';

    protected static ?string $title = 'Malayalam editorial';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Edit and save Malayalam here. English is never changed. Draft → Approve Malayalam → Approve and publish Malayalam. The live published version is marked Published.')
            ->modifyQueryUsing(function (Builder $query): Builder {
                /** @var Application $application */
                $application = $this->getOwnerRecord();
                $profileId = (int) ($application->profile_id ?? 0);

                return $query
                    ->where('language', EditorialContent::LANGUAGE_ML)
                    ->where('profile_id', $profileId > 0 ? $profileId : -1);
            })
            ->columns([
                TextColumn::make('version_number')
                    ->label('Ver')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        EditorialContent::STATUS_DRAFT => 'Draft',
                        EditorialContent::STATUS_APPROVED => 'Approved',
                        EditorialContent::STATUS_ARCHIVED => 'Archived',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        EditorialContent::STATUS_APPROVED => 'success',
                        EditorialContent::STATUS_ARCHIVED => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('publication_state')
                    ->label('Publication')
                    ->badge()
                    ->state(fn (EditorialContent $record): string => $this->isCurrentlyPublished($record) ? 'Published' : 'Not published')
                    ->color(fn (string $state): string => $state === 'Published' ? 'success' : 'gray'),
                TextColumn::make('english_pairing')
                    ->label('EN pairing')
                    ->badge()
                    ->state(fn (EditorialContent $record): string => $this->pairingLabel($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Paired with published EN' => 'success',
                        'Paired (other EN)' => 'info',
                        default => 'warning',
                    }),
                IconColumn::make('ai_generated')
                    ->label('AI')
                    ->boolean(),
                TextColumn::make('title')
                    ->label('Title')
                    ->limit(48)
                    ->wrap(),
                TextColumn::make('summary')
                    ->label('Summary')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
            ])
            ->defaultSort('version_number', 'desc')
            ->emptyStateHeading('No Malayalam editorial versions yet')
            ->emptyStateDescription('Generate an AI editorial draft from this application first. Malayalam versions will appear here for editing.')
            ->headerActions([
                Action::make('editLatestMalayalam')
                    ->label('Edit Malayalam')
                    ->button()
                    ->color('primary')
                    ->visible(fn (): bool => $this->canEditMalayalam() && $this->latestEditableMalayalam() !== null)
                    ->modalHeading('Edit Malayalam biography')
                    ->modalDescription('Correct the Malayalam title, summary, and body. Saving does not publish and does not change English.')
                    ->modalSubmitActionLabel('Save Malayalam Draft')
                    ->modalCancelActionLabel('Cancel')
                    ->modalWidth('5xl')
                    ->fillForm(function (): array {
                        $record = $this->latestEditableMalayalam();

                        return $record ? $this->formStateFrom($record) : [];
                    })
                    ->form($this->malayalamEditorFields())
                    ->action(function (array $data): void {
                        $record = $this->resolveBoundEditableMalayalam(
                            (int) ($data['editorial_content_id'] ?? 0),
                        );
                        if (! $record instanceof EditorialContent) {
                            return;
                        }
                        $this->saveMalayalamEdit($record, $data);
                    }),
                Action::make('approveAndPublishMalayalam')
                    ->label('Approve and publish Malayalam')
                    ->button()
                    ->color('success')
                    ->visible(fn (): bool => $this->canPublishMalayalam() && $this->publishableMalayalamOptions() !== [])
                    ->modalHeading('Approve and publish Malayalam')
                    ->modalDescription('Publishes the selected Malayalam version onto the already-published English profile. English is not changed. Pairing and publication safeguards stay in force.')
                    ->modalSubmitActionLabel('Approve and publish Malayalam')
                    ->modalCancelActionLabel('Cancel')
                    ->form([
                        \Filament\Forms\Components\Select::make('editorial_content_id')
                            ->label('Malayalam version to publish')
                            ->required()
                            ->options(fn (): array => $this->publishableMalayalamOptions())
                            ->default(fn (): ?int => $this->preferredPublishableMalayalamId())
                            ->helperText('Defaults to the latest draft or approved version paired with the currently published English biography.'),
                    ])
                    ->action(function (array $data): void {
                        $this->publishMalayalamVersion((int) ($data['editorial_content_id'] ?? 0));
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->button()
                    ->color('primary')
                    ->visible(fn (EditorialContent $record): bool => $this->canEditMalayalam()
                        && $record->language === EditorialContent::LANGUAGE_ML
                        && $record->status !== EditorialContent::STATUS_ARCHIVED)
                    ->modalHeading(fn (EditorialContent $record): string => 'Edit Malayalam · version '.$record->version_number)
                    ->modalDescription('Correct the Malayalam title, summary, and body. Save creates or updates a draft. Locked or published text gets a new draft successor; the live pointer does not move.')
                    ->modalSubmitActionLabel('Save Malayalam Draft')
                    ->modalCancelActionLabel('Cancel')
                    ->modalWidth('5xl')
                    ->fillForm(fn (EditorialContent $record): array => $this->formStateFrom($record))
                    ->form($this->malayalamEditorFields())
                    ->action(function (EditorialContent $record, array $data): void {
                        $this->saveMalayalamEdit($record, $data);
                    }),
                Action::make('approve')
                    ->label('Approve Malayalam')
                    ->button()
                    ->color('warning')
                    ->visible(fn (EditorialContent $record): bool => $this->canApproveMalayalam()
                        && $record->language === EditorialContent::LANGUAGE_ML
                        && $record->status === EditorialContent::STATUS_DRAFT
                        && trim((string) $record->body) !== '')
                    ->requiresConfirmation()
                    ->modalHeading('Approve this Malayalam version?')
                    ->modalDescription('Marks this Malayalam version as Approved. It does not publish it and does not change English. After approval, use Approve and publish Malayalam.')
                    ->modalSubmitActionLabel('Approve Malayalam')
                    ->modalCancelActionLabel('Cancel')
                    ->action(function (EditorialContent $record): void {
                        $this->approveMalayalamVersion($record);
                    }),
            ])
            ->toolbarActions([]);
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    private function malayalamEditorFields(): array
    {
        return [
            Hidden::make('editorial_content_id')
                ->required()
                ->dehydrated(),
            TextInput::make('language_display')
                ->label('Language')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('profile_display')
                ->label('Profile')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('pairing_display')
                ->label('English master')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('status_display')
                ->label('Current status')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('title')
                ->label('Malayalam title')
                ->required()
                ->maxLength(500),
            Textarea::make('summary')
                ->label('Malayalam summary')
                ->rows(3)
                ->maxLength(2000),
            Textarea::make('body')
                ->label('Malayalam biography')
                ->rows(16)
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function formStateFrom(EditorialContent $record): array
    {
        return [
            'editorial_content_id' => (int) $record->id,
            'language_display' => 'Malayalam (cannot be changed)',
            'profile_display' => 'Profile #'.$record->profile_id.' (cannot be changed)',
            'pairing_display' => $record->source_editorial_content_id
                ? 'EN version #'.$record->source_editorial_content_id.' — '.$this->pairingLabel($record)
                : 'No English master linked',
            'status_display' => match ($record->status) {
                EditorialContent::STATUS_DRAFT => 'Draft',
                EditorialContent::STATUS_APPROVED => 'Approved',
                EditorialContent::STATUS_ARCHIVED => 'Archived',
                default => (string) $record->status,
            }.($this->isCurrentlyPublished($record) ? ' · Published' : ' · Not published'),
            'title' => (string) $record->title,
            'summary' => (string) $record->summary,
            'body' => (string) $record->body,
        ];
    }

    /**
     * Resolve the Malayalam version bound when the header Edit form opened.
     * Never silently retargets a newer "latest" version.
     */
    private function resolveBoundEditableMalayalam(int $editorialContentId): ?EditorialContent
    {
        if ($editorialContentId <= 0) {
            Notification::make()
                ->title('Cannot save Malayalam')
                ->body('This edit form is not bound to a Malayalam version. Close it and open Edit Malayalam again.')
                ->danger()
                ->persistent()
                ->send();

            return null;
        }

        /** @var Application $application */
        $application = $this->getOwnerRecord();
        $record = EditorialContent::query()->find($editorialContentId);

        if (! $record instanceof EditorialContent
            || (int) $record->profile_id !== (int) $application->profile_id
            || $record->language !== EditorialContent::LANGUAGE_ML) {
            Notification::make()
                ->title('Cannot save Malayalam')
                ->body('The version opened for editing does not belong to this application as Malayalam. Nothing was saved.')
                ->danger()
                ->persistent()
                ->send();

            return null;
        }

        if ($record->status === EditorialContent::STATUS_ARCHIVED) {
            Notification::make()
                ->title('Cannot save Malayalam')
                ->body('Version '.$record->version_number.' is no longer eligible for editing (archived). Nothing was saved to another version.')
                ->danger()
                ->persistent()
                ->send();

            return null;
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveMalayalamEdit(EditorialContent $record, array $data): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        /** @var Application $application */
        $application = $this->getOwnerRecord();

        if ((int) $record->profile_id !== (int) $application->profile_id
            || $record->language !== EditorialContent::LANGUAGE_ML) {
            Notification::make()
                ->title('Cannot save Malayalam')
                ->body('That version does not belong to this application profile as Malayalam.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $payload = [
            'title' => (string) ($data['title'] ?? $record->title),
            'summary' => (string) ($data['summary'] ?? $record->summary),
            'body' => (string) ($data['body'] ?? $record->body),
            // Saving from this workspace keeps approval separate: always persist
            // narrative as a draft unless the row was already approved and the
            // narrative is unchanged (versioning handles locked rows).
            'status' => $this->statusAfterNarrativeSave($record),
            'source_material' => $record->source_material,
            'review_comment' => $record->review_comment,
        ];

        $publishedPointerBefore = $application->published_malayalam_editorial_content_id;
        $englishPointerBefore = $application->published_english_editorial_content_id;
        $englishSnapshot = $this->englishSnapshot($application);

        try {
            $result = app(EditorialContentVersioningService::class)
                ->applyEditorUpdate($record, $payload, $actor);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'body' => $e->getMessage(),
            ]);
        }

        $application->refresh();
        $this->assertEnglishUnchanged($application, $englishSnapshot, $englishPointerBefore, $publishedPointerBefore);

        if ($result->getKey() !== $record->getKey()) {
            Notification::make()
                ->title('Malayalam draft saved as new version')
                ->body('Version '.$result->version_number.' was created. The previous version and the live publication pointer were left unchanged. English was not modified. Next: Approve Malayalam, then Approve and publish Malayalam.')
                ->success()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Malayalam draft saved')
            ->body('Title, summary, and body were updated. English was not changed. Publication pointers were not moved.')
            ->success()
            ->persistent()
            ->send();
    }

    private function statusAfterNarrativeSave(EditorialContent $record): string
    {
        // Keep approved status only when versioning will create a successor
        // (immutable narrative). Direct draft edits stay drafts.
        if ($record->status === EditorialContent::STATUS_APPROVED) {
            return EditorialContent::STATUS_APPROVED;
        }

        return EditorialContent::STATUS_DRAFT;
    }

    private function approveMalayalamVersion(EditorialContent $record): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! $actor->isAdmin()) {
            abort(403);
        }

        /** @var Application $application */
        $application = $this->getOwnerRecord();
        if ((int) $record->profile_id !== (int) $application->profile_id
            || $record->language !== EditorialContent::LANGUAGE_ML) {
            Notification::make()->title('Cannot approve Malayalam')->danger()->send();

            return;
        }

        $publishedPointerBefore = $application->published_malayalam_editorial_content_id;
        $englishPointerBefore = $application->published_english_editorial_content_id;
        $englishSnapshot = $this->englishSnapshot($application);

        try {
            app(EditorialContentVersioningService::class)->applyEditorUpdate($record, [
                'title' => $record->title,
                'summary' => $record->summary,
                'body' => $record->body,
                'status' => EditorialContent::STATUS_APPROVED,
                'source_material' => $record->source_material,
                'review_comment' => $record->review_comment,
            ], $actor);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Cannot approve Malayalam')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        $application->refresh();
        $this->assertEnglishUnchanged($application, $englishSnapshot, $englishPointerBefore, $publishedPointerBefore);

        Notification::make()
            ->title('Malayalam approved')
            ->body('This version is Approved but not yet published. Use Approve and publish Malayalam next. English was not changed.')
            ->success()
            ->persistent()
            ->send();
    }

    private function publishMalayalamVersion(int $editorialContentId): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! $actor->isAdmin()) {
            abort(403);
        }

        /** @var Application $application */
        $application = $this->getOwnerRecord();
        $version = EditorialContent::query()->find($editorialContentId);
        if (! $version instanceof EditorialContent) {
            Notification::make()->title('Choose a Malayalam version')->warning()->send();

            return;
        }

        $englishPointerBefore = $application->published_english_editorial_content_id;
        $englishSnapshot = $this->englishSnapshot($application);

        try {
            app(ApplicationWorkflowService::class)->publishMalayalam($application, $actor, $version);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Cannot publish Malayalam')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        $application->refresh();
        if ((int) $application->published_english_editorial_content_id !== (int) $englishPointerBefore) {
            Notification::make()
                ->title('Unexpected English publication change')
                ->danger()
                ->persistent()
                ->send();

            return;
        }
        $this->assertEnglishContentUnchanged($englishSnapshot);

        Notification::make()
            ->title('Malayalam version published')
            ->body('Version '.$version->version_number.' is now the published Malayalam biography. The English profile was not changed.')
            ->success()
            ->persistent()
            ->send();
    }

    /**
     * @return array{id: ?int, title: ?string, summary: ?string, body: ?string}
     */
    private function englishSnapshot(Application $application): array
    {
        $english = $application->published_english_editorial_content_id
            ? EditorialContent::query()->find($application->published_english_editorial_content_id)
            : null;

        return [
            'id' => $english?->id,
            'title' => $english?->title,
            'summary' => $english?->summary,
            'body' => $english?->body,
        ];
    }

    /**
     * @param  array{id: ?int, title: ?string, summary: ?string, body: ?string}  $englishSnapshot
     */
    private function assertEnglishUnchanged(
        Application $application,
        array $englishSnapshot,
        mixed $englishPointerBefore,
        mixed $publishedPointerBefore,
    ): void {
        if ((int) $application->published_english_editorial_content_id !== (int) $englishPointerBefore
            || (int) $application->published_malayalam_editorial_content_id !== (int) $publishedPointerBefore) {
            Notification::make()
                ->title('Unexpected publication pointer change')
                ->body('Saving or approving Malayalam must not move publication pointers.')
                ->danger()
                ->persistent()
                ->send();
        }

        $this->assertEnglishContentUnchanged($englishSnapshot);
    }

    /**
     * @param  array{id: ?int, title: ?string, summary: ?string, body: ?string}  $englishSnapshot
     */
    private function assertEnglishContentUnchanged(array $englishSnapshot): void
    {
        if ($englishSnapshot['id'] === null) {
            return;
        }

        $english = EditorialContent::query()->find($englishSnapshot['id']);
        if (! $english instanceof EditorialContent) {
            return;
        }

        if ($english->title !== $englishSnapshot['title']
            || $english->summary !== $englishSnapshot['summary']
            || $english->body !== $englishSnapshot['body']) {
            Notification::make()
                ->title('English content changed unexpectedly')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    private function latestEditableMalayalam(): ?EditorialContent
    {
        /** @var Application $application */
        $application = $this->getOwnerRecord();
        if (! filled($application->profile_id)) {
            return null;
        }

        return EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->whereIn('status', [EditorialContent::STATUS_DRAFT, EditorialContent::STATUS_APPROVED])
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function publishableMalayalamOptions(): array
    {
        return $this->publishableMalayalamVersions()
            ->mapWithKeys(fn (EditorialContent $content): array => [
                $content->id => 'Version '.$content->version_number.' ('.$content->status.')'
                    .($this->isCurrentlyPublished($content) ? ' · currently published' : ''),
            ])
            ->all();
    }

    private function preferredPublishableMalayalamId(): ?int
    {
        $versions = $this->publishableMalayalamVersions();
        if ($versions->isEmpty()) {
            return null;
        }

        // Prefer the newest non-published draft/approved pair so a corrected
        // successor is selected ahead of the already-live version.
        $preferred = $versions->first(fn (EditorialContent $content): bool => ! $this->isCurrentlyPublished($content));

        return (int) ($preferred ?? $versions->first())->id;
    }

    /**
     * @return Collection<int, EditorialContent>
     */
    private function publishableMalayalamVersions(): Collection
    {
        /** @var Application $application */
        $application = $this->getOwnerRecord();
        if ($application->status !== Application::STATUS_PUBLISHED
            || $application->published_english_editorial_content_id === null
            || $application->profile_id === null) {
            return collect();
        }

        return EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->where('source_editorial_content_id', $application->published_english_editorial_content_id)
            ->whereIn('status', [EditorialContent::STATUS_DRAFT, EditorialContent::STATUS_APPROVED])
            ->orderByDesc('version_number')
            ->get();
    }

    private function isCurrentlyPublished(EditorialContent $record): bool
    {
        /** @var Application $application */
        $application = $this->getOwnerRecord();

        return $application->published_malayalam_editorial_content_id !== null
            && (int) $application->published_malayalam_editorial_content_id === (int) $record->id;
    }

    private function pairingLabel(EditorialContent $record): string
    {
        if ($record->source_editorial_content_id === null) {
            return 'Unpaired';
        }

        /** @var Application $application */
        $application = $this->getOwnerRecord();
        $publishedEnglishId = $application->published_english_editorial_content_id;

        if ($publishedEnglishId !== null
            && (int) $record->source_editorial_content_id === (int) $publishedEnglishId) {
            return 'Paired with published EN';
        }

        return 'Paired (other EN)';
    }

    private function canEditMalayalam(): bool
    {
        return auth()->user()?->canManageEditorial() ?? false;
    }

    private function canApproveMalayalam(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin() && $user->isActiveAccount();
    }

    private function canPublishMalayalam(): bool
    {
        return $this->canApproveMalayalam();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->canManageEditorial()
            && filled($ownerRecord->profile_id);
    }
}
