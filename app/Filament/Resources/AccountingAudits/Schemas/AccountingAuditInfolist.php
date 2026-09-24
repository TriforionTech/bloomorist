<?php

namespace App\Filament\Resources\AccountingAudits\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountingAuditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Log Audit')
                    ->icon('heroicon-o-shield-check')
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Waktu Kejadian')
                            ->dateTime('d M Y H:i:s'),

                        TextEntry::make('user.name')
                            ->label('Pelaku (User)')
                            ->placeholder('System')
                            ->weight('bold'),

                        TextEntry::make('action')
                            ->label('Jenis Aksi')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default => 'info',
                            })
                            ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                        TextEntry::make('display_no_bukti')
                            ->label('No. Bukti Jurnal')
                            ->weight('bold')
                            ->color('primary')
                            ->copyable(),

                        TextEntry::make('display_keterangan')
                            ->label('Keterangan Jurnal')
                            ->columnSpan([
                                'default' => 1,
                                'md' => 2,
                            ]),
                    ]),

                Section::make('Rincian Perubahan Nilai')
                    ->icon('heroicon-o-code-bracket')
                    ->description('Perbandingan nilai sebelum (old values) dan sesudah (new values)')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            TextEntry::make('old_values')
                                ->label('Nilai Sebelum (Old Values)')
                                ->html()
                                ->formatStateUsing(function ($state) {
                                    if (empty($state)) {
                                        return '<span class="text-gray-400 italic text-sm">Tidak ada (Data Baru Dibuat)</span>';
                                    }
                                    $json = e(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                                    return "<pre class=\"bg-gray-100 dark:bg-gray-900 p-3 rounded-lg text-xs font-mono overflow-x-auto text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-800 max-h-96\">{$json}</pre>";
                                }),

                            TextEntry::make('new_values')
                                ->label('Nilai Sesudah (New Values)')
                                ->html()
                                ->formatStateUsing(function ($state) {
                                    if (empty($state)) {
                                        return '<span class="text-gray-400 italic text-sm">Tidak ada (Data Dihapus)</span>';
                                    }
                                    $json = e(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                                    return "<pre class=\"bg-gray-100 dark:bg-gray-900 p-3 rounded-lg text-xs font-mono overflow-x-auto text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-800 max-h-96\">{$json}</pre>";
                                }),
                        ]),
                    ]),
            ]);
    }
}
