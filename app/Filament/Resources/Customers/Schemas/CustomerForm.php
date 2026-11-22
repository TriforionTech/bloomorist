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
                    ->label('Nama Pelanggan')
                    ->placeholder('Masukkan nama pelanggan')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) {
                        $set('nama', Str::title($state));
                    })
                    ->dehydrateStateUsing(fn ($state) => Str::title($state)),
                Select::make('tipe_customer')
                    ->label('Tipe Pelanggan')
                    ->options([
                        'toko' => 'Toko (Default)',
                        'vendor' => 'Vendor',
                        'dekor' => 'Dekorator',
                    ])
                    ->default('toko')
                    ->required(),
                TextInput::make('alias')
                    ->label('Alias')
                    ->placeholder('Masukkan alias pelanggan (opsional)'),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->placeholder('contoh@domain.com')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at')
                    )
                    ->validationMessages([
                        'unique' => 'Email ini sudah digunakan oleh customer aktif lain.',
                    ]),
                Textarea::make('alamat')
                    ->label('Alamat')
                    ->placeholder('Masukkan alamat pelanggan')
                    ->required(),
                TextInput::make('kota')
                    ->label('Kota')
                    ->placeholder('Masukkan kota pelanggan'),
                TextInput::make('provinsi')
                    ->label('Provinsi')
                    ->placeholder('Masukkan provinsi pelanggan'),
                TextInput::make('negara')
                    ->label('Negara')
                    ->default('Indonesia'),
                TextInput::make('nomor_hp')
                    ->label('Nomor Telepon / HP')
                    ->placeholder('Contoh: 08xxxxxxxxxx')
                    ->tel()
                    ->rule('regex:/^[0-9+\-\s]+$/'),
                Select::make('membership_id')
                    ->label('Kategori Membership')
                    ->searchable()
                    ->options(
                        Membership::pluck('nama', 'id')
                    ),
            ]);
    }
}
