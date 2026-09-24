<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Services\StockOpnameExcelService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListStockOpnames extends ListRecords
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Download Template Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->tooltip('Unduh template Excel (.xlsx) resmi yang telah otomatis terisi seluruh produk aktif terbaru')
                ->action(fn (StockOpnameExcelService $service) => $service->generateTemplateExcel()),

            Action::make('importExcel')
                ->label('Import Hasil Opname (Excel)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->modalHeading('Import Hasil Stock Opname (Excel)')
                ->modalDescription('Unggah file Excel (.xlsx) yang telah Anda lengkapi kolom stok fisiknya. Sistem akan mencocokkan integritas SKU & ID produk serta menghitung varians secara otomatis.')
                ->modalWidth('xl')
                ->schema([
                    DatePicker::make('date')
                        ->label('Tanggal Opname')
                        ->default(now())
                        ->required()
                        ->native(false),

                    TextInput::make('reference_number')
                        ->label('Nomor Referensi')
                        ->default(fn () => 'SO-'.date('Y-m-d').'-'.strtoupper(Str::random(4)))
                        ->required(),

                    TextInput::make('notes')
                        ->label('Catatan Opname (Opsional)')
                        ->placeholder('Contoh: Opname Rutin Akhir Bulan Gudang Utama'),

                    FileUpload::make('file')
                        ->label('File Excel Stock Opname (.xlsx)')
                        ->disk('local')
                        ->directory('temp-stock-opname')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'application/zip',
                        ])
                        ->required()
                        ->helperText('Unggah file template Excel (.xlsx) yang telah Anda isi kolom stok_fisik-nya.'),

                    Toggle::make('post_immediately')
                        ->label('Langsung Posting ke Akuntansi')
                        ->helperText('Jika aktif, stok produk langsung disesuaikan dan jurnal penyesuaian otomatis dibuat. Jika nonaktif, akan disimpan sebagai Draft.')
                        ->default(false),
                ])
                ->action(function (array $data, StockOpnameExcelService $service) {
                    $fileData = $data['file'] ?? null;
                    if (is_array($fileData)) {
                        $fileData = reset($fileData);
                    }

                    $fullPath = null;
                    $relativePathToDelete = null;

                    if ($fileData instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $fullPath = $fileData->getRealPath();
                    } elseif (is_string($fileData)) {
                        if (file_exists($fileData)) {
                            $fullPath = $fileData;
                        } elseif (Storage::disk('local')->exists($fileData)) {
                            $fullPath = Storage::disk('local')->path($fileData);
                            $relativePathToDelete = $fileData;
                        }
                    }

                    if (! $fullPath || ! file_exists($fullPath)) {
                        Notification::make()
                            ->title('File Excel Tidak Ditemukan')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $result = $service->importExcel($fullPath, [
                            'date' => $data['date'],
                            'reference_number' => $data['reference_number'],
                            'notes' => $data['notes'] ?? null,
                            'post_immediately' => $data['post_immediately'] ?? false,
                        ]);

                        Notification::make()
                            ->title('Stock Opname Berhasil Diimpor')
                            ->body("Sebanyak {$result['total_items']} item produk berhasil dicatat. Status: ".($result['posted'] ? 'Posted' : 'Draft'))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal Mengimpor File Excel Stock Opname')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    } finally {
                        if ($relativePathToDelete && Storage::disk('local')->exists($relativePathToDelete)) {
                            Storage::disk('local')->delete($relativePathToDelete);
                        }
                    }
                }),

            CreateAction::make()
                ->label('Input Manual (1 per 1)'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\StockOpnames\Widgets\StockOpnameOverview::class,
        ];
    }
}
