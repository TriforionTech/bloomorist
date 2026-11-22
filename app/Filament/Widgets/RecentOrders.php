<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\GenerateInvoice;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Traits\ParsesGlobalFilters;
use App\Models\Invoice;
use App\Services\DashboardMetricsService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Maksimal 10 transaksi terbaru pada periode aktif (semua status).
 */
class RecentOrders extends TableWidget
{
    use InteractsWithPageFilters;
    use ParsesGlobalFilters;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = [
        'default' => 1,
        'lg' => 12,
    ];

    public function table(Table $table): Table
    {
        $period = $this->dashboardPeriod();

        return $table
            ->heading('Pesanan Terbaru')
            ->description("10 transaksi terbaru · {$period->presetLabel()} ({$period->rangeLabel()})")
            ->query(fn (): Builder => $this->recentOrdersQuery())
            ->paginated(false)
            ->headerActions([
                Action::make('view_all')
                    ->label('Lihat Semua')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->size('sm')
                    ->url(fn (): string => InvoiceResource::getUrl('index')),
            ])
            ->recordUrl(fn (Invoice $record): string => GenerateInvoice::getUrl().'?invoice='.$record->id.($record->isEditable() ? '' : '&view=1'))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->getStateUsing(fn (Invoice $record): string => $record->customer?->nama ?? $record->customer_name ?? '-'),

                TextColumn::make('issued_date')
                    ->label('Tanggal')
                    ->date('d M Y'),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => $this->formatRupiah((float) ($state ?? 0)))
                    ->weight('bold')
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('Belum ada transaksi pada periode ini')
            ->emptyStateDescription('Transaksi terbaru akan muncul di sini setelah invoice dibuat.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    private function recentOrdersQuery(): Builder
    {
        $query = Invoice::query()->with('customer');

        app(DashboardMetricsService::class)->applyPeriod($query, $this->dashboardPeriod());

        return $query
            ->orderByDesc('issued_date')
            ->orderByDesc('id')
            ->limit(10);
    }
}
