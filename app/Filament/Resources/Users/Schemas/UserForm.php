<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->rule(Password::defaults())
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),
            Select::make('role')
                ->options([
                    User::ROLE_MEMBER => 'Member',
                    User::ROLE_ADMIN => 'Admin',
                    User::ROLE_EDITOR => 'Editor',
                    User::ROLE_SUPPORT => 'Support',
                ])
                ->required()
                ->native(false),
            Select::make('account_status')
                ->options([
                    'active' => 'Active',
                    'suspended' => 'Suspended',
                ])
                ->required()
                ->native(false),
        ]);
    }
}
