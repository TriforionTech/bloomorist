<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Membership;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Name')
                    ->placeholder('Enter member name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) {
                        $set('nama', Str::title($state));
                    })
                    ->dehydrateStateUsing(fn ($state) => Str::title($state)),
                TextInput::make('alias')
                    ->label('Alias')
                    ->placeholder('Enter member alias (optional)'),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->placeholder('example@domain.com'),
                Textarea::make('alamat')
                    ->label('Address')
                    ->placeholder('Enter member address')
                    ->required(),
                TextInput::make('kota')
                    ->label('City')
                    ->placeholder('Enter member city'),
                TextInput::make('provinsi')
                    ->label('Province')
                    ->placeholder('Enter member province'),
                TextInput::make('negara')
                    ->label('Country')
                    ->default('Indonesia'),
                TextInput::make('nomor_hp')
                    ->label('Phone Number')
                    ->placeholder('e.g., 08xxxxxxxxxx')
                    ->tel()
                    ->rule('regex:/^[0-9+\-\s]+$/'),
                Select::make('membership_id')
                    ->label('Membership Category')
                    ->searchable()
                    ->options(
                        Membership::pluck('nama', 'id')
                    ),
            ]);
    }
}
