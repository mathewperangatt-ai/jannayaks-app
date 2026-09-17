<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Models\Application;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Applicant')
                ->schema([
                    TextEntry::make('id')->label('Application ID'),
                    TextEntry::make('full_name'),
                    TextEntry::make('preferred_display_name'),
                    TextEntry::make('user.email')
                        ->label('Account email')
                        ->visible(fn (?Application $record): bool => self::canSeeAccountEmail($record)),
                    TextEntry::make('preferred_contact_email')
                        ->label('Preferred contact email')
                        ->visible(fn (?Application $record): bool => self::canSeePrivateContact($record)),
                    TextEntry::make('preferred_contact_mobile')
                        ->label('Preferred contact mobile')
                        ->visible(fn (?Application $record): bool => self::canSeePrivateContact($record)),
                ])
                ->columns(2),
            Section::make('Workflow')
                ->schema([
                    TextEntry::make('status')
                        ->formatStateUsing(fn (?string $state): string => Application::workflowStatusLabels()[$state] ?? (string) $state),
                    TextEntry::make('package_tier'),
                    TextEntry::make('source_method'),
                    TextEntry::make('payment_status')
                        ->visible(fn (): bool => self::actor()?->canManageFinance() ?? false),
                    TextEntry::make('payment_settled_at')->dateTime(),
                    TextEntry::make('online_interview_completed_at')->dateTime(),
                    TextEntry::make('direct_submission_received_at')->dateTime(),
                ])
                ->columns(2),
        ]);
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
