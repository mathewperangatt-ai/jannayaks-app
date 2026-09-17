<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Models\Application;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Applicant')
                ->schema([
                    TextInput::make('full_name')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('preferred_display_name')
                        ->disabled()
                        ->dehydrated(false),
                    Placeholder::make('account_email')
                        ->label('Account email')
                        ->content(fn (?Application $record): string => (string) ($record?->user?->email ?? '—'))
                        ->visible(fn (?Application $record): bool => self::canSeeAccountEmail($record)),
                    TextInput::make('preferred_contact_email')
                        ->label('Preferred contact email')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?Application $record): bool => self::canSeePrivateContact($record)),
                    TextInput::make('preferred_contact_mobile')
                        ->label('Preferred contact mobile')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?Application $record): bool => self::canSeePrivateContact($record)),
                ])
                ->columns(2),
            Section::make('Package & source')
                ->schema([
                    TextInput::make('package_tier')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('source_method')
                        ->disabled()
                        ->dehydrated(false),
                    Toggle::make('distinguished_interview_addon')
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(2),
            Section::make('Workflow')
                ->schema([
                    Select::make('status')
                        ->label('Workflow status')
                        ->options(fn (?Application $record): array => self::statusOptions($record))
                        ->required()
                        ->disabled(fn (): bool => ! (self::actor()?->canManageEditorial() ?? false)),
                    TextInput::make('payment_status')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (): bool => self::actor()?->canManageFinance() ?? false),
                ])
                ->columns(2),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(?Application $record): array
    {
        $actor = self::actor();
        if ($actor?->isAdmin()) {
            return Application::workflowStatusLabels();
        }

        $options = [];
        foreach (Application::editorialHandoffStatuses() as $status) {
            $options[$status] = Application::workflowStatusLabels()[$status];
        }

        if ($record?->status && ! isset($options[$record->status])) {
            $options[$record->status] = Application::workflowStatusLabels()[$record->status]
                ?? $record->status;
        }

        return $options;
    }

    private static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function canSeeAccountEmail(?Application $record): bool
    {
        $actor = self::actor();
        if (! $actor instanceof User || ! $record instanceof Application) {
            return false;
        }

        return $actor->can('viewAccountEmail', $record);
    }

    private static function canSeePrivateContact(?Application $record): bool
    {
        $actor = self::actor();
        if (! $actor instanceof User || ! $record instanceof Application) {
            return false;
        }

        return $actor->can('viewContactDetails', $record);
    }
}
