<?php

namespace App\Filament\Resources\ReportLineTemplates\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportLineTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('report_type')->label('LAPORAN')->badge(),
                TextColumn::make('line_key')->label('LINE KEY')->searchable(),
                TextColumn::make('label')->label('LABEL')->searchable(),
                TextColumn::make('sort_order')->label('URUTAN')->sortable(),
                IconColumn::make('is_subtotal')->label('SUBTOTAL')->boolean(),
                IconColumn::make('is_active')->label('AKTIF')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
