<?php

namespace App\Filament\Resources\InMemoriamProfiles\Schemas;

use App\Models\InMemoriamProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InMemoriamProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Deceased person')
                ->columns(2)
                ->schema([
                    TextInput::make('deceased_full_name')->required()->maxLength(255),
                    TextInput::make('deceased_display_name')->maxLength(255),
                    TextInput::make('profession')->maxLength(5000),
                    TextInput::make('bio_headline')->maxLength(255),
                    DatePicker::make('deceased_date_of_birth'),
                    DatePicker::make('deceased_date_of_death'),
                    TextInput::make('deceased_place_of_birth')->maxLength(255),
                    TextInput::make('deceased_place_of_death')->maxLength(255),
                    TextInput::make('slug')
                        ->label('Public URL slug')
                        ->helperText('Published at /in-memoriam/{slug}')
                        ->maxLength(64),
                ]),
            Section::make('Commissioner / contact (staff only)')
                ->columns(2)
                ->schema([
                    TextInput::make('commissioner_contact_name')->required()->maxLength(255),
                    TextInput::make('commissioner_contact_mobile')->required()->maxLength(32),
                    TextInput::make('commissioner_contact_email')->email()->maxLength(255),
                    TextInput::make('commissioner_relation')->maxLength(128),
                    Toggle::make('commissioner_display_consent')
                        ->label('Show commissioner name on public page'),
                ]),
            Section::make('Death verification outcome')
                ->description('Offline verification only. Do not store certificate contents or scans here.')
                ->columns(2)
                ->schema([
                    Select::make('verification_status')
                        ->options([
                            InMemoriamProfile::VERIFICATION_UNVERIFIED => 'Unverified',
                            InMemoriamProfile::VERIFICATION_VERIFIED => 'Verified',
                            InMemoriamProfile::VERIFICATION_COULD_NOT_VERIFY => 'Could not verify',
                            InMemoriamProfile::VERIFICATION_WAIVED => 'Waived',
                        ])
                        ->required()
                        ->native(false),
                    Select::make('verification_method')
                        ->options([
                            'death_certificate_inspection' => 'Death certificate inspection',
                            'official_records' => 'Official records',
                            'both' => 'Both',
                            'other' => 'Other',
                        ])
                        ->native(false),
                    Textarea::make('verification_notes')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('Brief operational note only (e.g. “certificate examined; DoD matches”).'),
                ]),
            Section::make('Workflow')
                ->schema([
                    Select::make('status')
                        ->options([
                            InMemoriamProfile::STATUS_DRAFT => 'Draft',
                            InMemoriamProfile::STATUS_PAYMENT_PENDING => 'Payment pending',
                            InMemoriamProfile::STATUS_SUBMITTED => 'Submitted',
                            InMemoriamProfile::STATUS_UNDER_EDITORIAL_REVIEW => 'Under editorial review',
                            InMemoriamProfile::STATUS_REJECTED => 'Rejected',
                        ])
                        ->required()
                        ->native(false)
                        ->helperText('Publication uses the Admin Publish action (status becomes published_archived).'),
                ]),
        ]);
    }
}
