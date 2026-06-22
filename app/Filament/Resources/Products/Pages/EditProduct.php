<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\InvoiceItem;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),

            // Soft delete: set is_active = false
            Action::make('delete')
                ->label('Delete')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading(function () {
                    $record = $this->getRecord();
                    $invoiceCount = InvoiceItem::where('product_id', $record->id)
                        ->distinct('invoice_id')
                        ->count('invoice_id');
                    return $invoiceCount > 0
                        ? "⚠️ Hapus Produk (Terdapat di {$invoiceCount} Invoice)"
                        : 'Hapus Produk';
                })
                ->modalDescription(function () {
                    $record = $this->getRecord();
                    $invoiceCount = InvoiceItem::where('product_id', $record->id)
                        ->distinct('invoice_id')
                        ->count('invoice_id');
                    if ($invoiceCount > 0) {
                        return "Produk \"{$record->nama}\" (SKU: {$record->sku}) tercatat pada {$invoiceCount} invoice. Produk akan dinonaktifkan namun data transaksi historis tetap aman. Lanjutkan?";
                    }
                    return 'Produk akan dinonaktifkan dan tidak tampil di daftar. Data transaksi historis tetap aman. Lanjutkan?';
                })
                ->modalSubmitActionLabel('Ya, Hapus')
                ->modalCancelActionLabel('Batal')
                ->action(function () {
                    $record = $this->getRecord();
                    $record->update(['is_active' => false]);

                    Notification::make()
                        ->success()
                        ->title('Produk Dihapus')
                        ->body("Produk \"{$record->nama}\" telah dinonaktifkan.")
                        ->send();

                    return redirect(ProductResource::getUrl('index'));
                })
                ->visible(fn () => Filament::auth()->user()?->is_super_admin),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
