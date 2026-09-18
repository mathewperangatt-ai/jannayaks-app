<?php

namespace App\Filament\Resources\InMemoriamProfiles\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InMemoriamProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Memorial')
                ->columns(2)
                ->schema([
                    TextEntry::make('deceased_full_name'),
                    TextEntry::make('deceased_display_name'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('slug'),
                    TextEntry::make('profession')->columnSpanFull(),
                    IconEntry::make('is_sealed')->boolean(),
                    TextEntry::make('published_at')->dateTime(),
                    TextEntry::make('hosting_starts_on')->date(),
                    TextEntry::make('hosting_ends_on')->date(),
                    TextEntry::make('commission_paid_at')->dateTime(),
                    TextEntry::make('commission_amount'),
                    TextEntry::make('commission_gst_amount'),
                ]),
            Section::make('Commissioner')
                ->columns(2)
                ->visible(fn (): bool => (auth()->user()?->canManageEditorial() ?? false)
                    || (auth()->user()?->isAdmin() ?? false))
                ->schema([
                    TextEntry::make('commissioner_contact_name'),
                    TextEntry::make('commissioner_contact_mobile'),
                    TextEntry::make('commissioner_contact_email'),
                    TextEntry::make('commissioner_relation'),
                    IconEntry::make('commissioner_display_consent')->boolean(),
                ]),
            Section::make('Verification')
                ->columns(2)
                ->schema([
                    TextEntry::make('verification_status')->badge(),
                    TextEntry::make('verification_method'),
                    TextEntry::make('verified_at')->dateTime(),
                    TextEntry::make('verification_notes')->columnSpanFull(),
                ]),
            Section::make('Admin correction')
                ->schema([
                    TextEntry::make('last_admin_corrected_at')->dateTime(),
                    TextEntry::make('admin_correction_notes')->columnSpanFull(),
                ]),
        ]);
    }
}
