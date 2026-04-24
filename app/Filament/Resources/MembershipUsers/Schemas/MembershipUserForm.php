<?php

namespace App\Filament\Resources\MembershipUsers\Schemas;

use App\Models\Membership;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

use function Laravel\Prompts\textarea;

class MembershipUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Name')
                    ->placeholder('Enter member name')
                    ->required(),
                TextInput::make('alias')
                    ->label('Alias')
                    ->placeholder('Enter member alias (optional)'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->placeholder('example@domain.com')
                    ->required(),
                Textarea::make('alamat')
                    ->label('Address')
                    ->placeholder('Enter member address')
                    ->required(),
                TextInput::make('kota')
                    ->label('City')
                    ->placeholder('Enter member city')
                    ->required(),
                TextInput::make('provinsi')
                    ->label('Province')
                    ->placeholder('Enter member province'),
                TextInput::make('negara')
                    ->label('Country')
                    ->default('Indonesia')
                    ->required(),
                TextInput::make('nomor_hp')
                    ->label('Phone Number')
                    ->placeholder('e.g., +628xxxxxxxxxx')
                    ->required()
                    ->tel()
                    ->rule('regex:/^[0-9+\-\s]+$/'),
                Select::make('membership_id')
                    ->label('Membership Category')
                    ->required()
                    ->searchable()
                    ->options(
                        Membership::pluck('nama', 'id')
                    ),
            ]);
    }
}
