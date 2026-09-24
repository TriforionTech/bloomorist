<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\ProductExcelService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    public string $priceTier = 'toko';

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->tooltip('Unduh data produk & tier harga saat ini ke file Excel (.xlsx)')
                ->action(function (ProductExcelService $service) {
                    $query = Product::query()->where('is_active', true);
                    if (! empty($this->activeTab) && in_array($this->activeTab, ['bunga', 'packaging', 'others'])) {
                        $query->where('kategori', $this->activeTab);
                    }

                    return $service->exportProductsExcel($query);
                }),

            Action::make('downloadTemplate')
                ->label('Template Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->tooltip('Unduh template Excel kosong (.xlsx) dengan baris contoh untuk import produk')
                ->action(fn (ProductExcelService $service) => $service->generateTemplateExcel()),

            Action::make('importExcel')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn () => Filament::auth()->user()?->is_super_admin)
                ->modalHeading('Import Data Produk & Tier Harga (Excel)')
                ->modalDescription('Perbarui tier harga (Toko, Vendor, Dekor, Beli) dan katalog secara massal. Stok fisik produk tidak akan diubah (gunakan menu Stock Opname untuk penyesuaian stok).')
                ->modalWidth('xl')
                ->schema([
                    FileUpload::make('file')
                        ->label('File Excel Produk (.xlsx / .csv)')
                        ->disk('local')
                        ->directory('temp-product-import')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'application/zip',
                            'text/csv',
                            'text/plain',
                        ])
                        ->required()
                        ->helperText('Pastikan format kolom: sku, nama, kategori, harga_beli, harga_jual, harga_vendor, harga_dekor, status.'),
                ])
                ->action(function (array $data, ProductExcelService $service) {
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
                        $result = $service->importExcel($fullPath);

                        Notification::make()
                            ->title('Import Produk Berhasil')
                            ->body("Sebanyak {$result['total_rows']} produk diproses: {$result['updated_count']} diperbarui, {$result['created_count']} baru ditambahkan.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal Mengimpor File Produk')
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
                ->label('New Product')
                ->visible(fn () => Filament::auth()->user()?->is_super_admin),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-squares-2x2')
                ->badge(fn () => Product::where('is_active', true)->count()),

            'bunga' => Tab::make('Flowers')
                ->icon('heroicon-o-sparkles')
                ->badge(fn () => Product::where('is_active', true)->where('kategori', 'bunga')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kategori', 'bunga')),

            'packaging' => Tab::make('Packaging')
                ->icon('heroicon-o-archive-box')
                ->badge(fn () => Product::where('is_active', true)->where('kategori', 'packaging')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kategori', 'packaging')),

            'others' => Tab::make('Others')
                ->icon('heroicon-o-tag')
                ->badge(fn () => Product::where('is_active', true)->where('kategori', 'others')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kategori', 'others')),
        ];
    }
}
