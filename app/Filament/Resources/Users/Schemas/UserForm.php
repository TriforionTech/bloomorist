<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Admin')
                    ->placeholder('Masukkan nama admin')
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->placeholder('contoh@domain.com')
                    ->required(),
                TextInput::make('password')
                    ->label('Kata Sandi')
                    ->password()
                    ->placeholder('Masukkan kata sandi')
                    ->required(),
                TextInput::make('confirm-password')
                    ->label('Konfirmasi Kata Sandi')
                    ->password()
                    ->placeholder('Ulangi kata sandi')
                    ->required()
                    ->same('password')
                    ->validationMessages([
                        'same' => 'Konfirmasi kata sandi tidak cocok.',
                    ]),
                Select::make('is_super_admin')
                    ->label('Superadmin')
                    ->placeholder('Pilih peran')
                    ->options([
                        0 => 'Tidak',
                        1 => 'Ya',
                    ])
                    ->visible(fn ($record) => Filament::auth()->user()?->is_super_admin &&
                        Filament::auth()->id() !== $record?->id
                    )
                    ->default(0)
                    ->required()
                    ->native(false),
            ]);
    }
}
