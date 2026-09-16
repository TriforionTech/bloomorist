<?php

namespace App\Filament\Resources\ReportLineTemplates\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReportLineTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('report_type')
                ->label('Jenis Laporan')
                ->options([
                    'income_statement' => 'Laba Rugi',
                    'balance_sheet' => 'Neraca',
                    'cash_flow' => 'Arus Kas',
                ])
                ->required()
                ->native(false),
            TextInput::make('line_key')->label('Line Key')->required()->maxLength(100),
            TextInput::make('label')->label('Label')->required()->maxLength(255),
            Textarea::make('account_codes')
                ->label('Kode Akun / Pattern (JSON)')
                ->helperText('Contoh: ["4101"] atau ["6*"]')
                ->formatStateUsing(fn ($state) => $state ? json_encode($state) : '[]')
                ->dehydrateStateUsing(fn ($state) => json_decode($state ?: '[]', true, 512, JSON_THROW_ON_ERROR))
                ->required(fn ($get) => !$get('is_subtotal')),
            TextInput::make('sign')->label('Sign')->numeric()->integer()->default(1)->required(),
            TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->default(0)->required(),
            Toggle::make('is_subtotal')->label('Subtotal')->live(),
            TextInput::make('subtotal_formula')
                ->label('Formula Subtotal')
                ->placeholder('sales + sales_returns')
                ->visible(fn ($get) => $get('is_subtotal'))
                ->required(fn ($get) => $get('is_subtotal')),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }
}
