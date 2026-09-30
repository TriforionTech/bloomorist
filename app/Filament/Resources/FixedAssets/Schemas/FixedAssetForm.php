<?php

namespace App\Filament\Resources\FixedAssets\Schemas;

use App\Models\ChartOfAccount;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FixedAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        $accountOptions = fn () => ChartOfAccount::query()
            ->orderBy('kode_akun')
            ->get()
            ->mapWithKeys(fn (ChartOfAccount $account) => [
                $account->id => "{$account->kode_akun} — {$account->nama_akun}",
            ]);

        return $schema->components([
            Section::make('Informasi Aset')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Aset')
                        ->required()
                        ->maxLength(255),
                    DatePicker::make('purchase_date')
                        ->label('Tanggal Pembelian')
                        ->required()
                        ->native(false),
                    TextInput::make('acquisition_cost')
                        ->label('Harga Perolehan')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->prefix('Rp'),
                    TextInput::make('useful_life_months')
                        ->label('Masa Manfaat (Bulan)')
                        ->required()
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->default(48),
                ])
                ->columns(2),
            Section::make('Akun Akuntansi')
                ->schema([
                    Select::make('asset_account_id')
                        ->label('Akun Aset')
                        ->options($accountOptions)
                        ->required()
                        ->searchable()
                        ->native(false),
                    Select::make('expense_account_id')
                        ->label('Akun Beban Penyusutan')
                        ->options($accountOptions)
                        ->required()
                        ->searchable()
                        ->native(false),
                    Select::make('accum_dep_account_id')
                        ->label('Akun Akumulasi Penyusutan')
                        ->options($accountOptions)
                        ->required()
                        ->searchable()
                        ->native(false),
                    Toggle::make('is_active')
                        ->label('Aset Aktif')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }
}
