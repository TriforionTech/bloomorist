<?php

namespace App\Filament\Resources\MembershipUsers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MembershipUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // TextColumn::make('id')
                //     ->label('NO.')
                //     ->sortable(),
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('nama')
                    ->label('NAME')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('alias')
                    ->label('ALIAS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('EMAIL')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('alamat')
                    ->label('ADDRESS')
                    ->searchable(),
                TextColumn::make('kota')
                    ->label('CITY')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provinsi')
                    ->label('REGION')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('negara')
                    ->label('COUNTRY')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nomor_hp')
                    ->label('PHONE')
                    ->searchable(),
                TextColumn::make('membership_id')
                    ->label('MEMBERSHIP')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'SILVER',
                        2 => 'GOLD',
                        3 => 'PLATINUM',
                        default => '-',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'gray',
                        2 => 'warning',
                        3 => 'info',
                        default => 'secondary',
                    })
                    // TAMBAHKAN ICON DI SINI
                    ->icon(fn (int $state): string => match ($state) {
                        1 => 'heroicon-m-shield-check',
                        2 => 'heroicon-m-star',
                        3 => 'heroicon-m-trophy',
                        default => 'heroicon-m-minus',
                    }),
                TextColumn::make('created_at')
                    ->label('CREATED AT')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('UPDATED AT')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('membership_id')
                    ->label('Membership Category')
                    ->options([
                        1 => 'SILVER',
                        2 => 'GOLD',
                        3 => 'PLATINUM',
                    ])
                    ->searchable(),
            ])
            ->recordActionsColumnLabel('ACTIONS')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])            
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
