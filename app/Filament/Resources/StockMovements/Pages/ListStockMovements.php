<?php

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Livewire\Attributes\On;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    // Export state
    public string $exportPeriod = 'this_month';
    public ?string $exportFrom = null;
    public ?string $exportUntil = null;

    protected function getHeaderActions(): array
    {
        return [];
    }

    private function buildExportQuery(): array
    {
        $query = \App\Models\StockMovement::with(['product', 'user']);

        switch ($this->exportPeriod) {
            case 'today':
                $query->whereDate('created_at', \Carbon\Carbon::today());
                $dateString = 'today_' . \Carbon\Carbon::today()->format('dMY');
                break;
            case 'this_week':
                $query->whereBetween('created_at', [
                    \Carbon\Carbon::now()->startOfWeek(),
                    \Carbon\Carbon::now()->endOfWeek(),
                ]);
                $dateString = 'week_' . \Carbon\Carbon::now()->startOfWeek()->format('dM') . '-' . \Carbon\Carbon::now()->endOfWeek()->format('dM') . '_' . \Carbon\Carbon::now()->format('Y');
                break;
            case 'this_month':
                $query->whereBetween('created_at', [
                    \Carbon\Carbon::now()->startOfMonth(),
                    \Carbon\Carbon::now()->endOfMonth(),
                ]);
                $dateString = \Carbon\Carbon::now()->format('M_Y');
                break;
            case 'last_month':
                $query->whereBetween('created_at', [
                    \Carbon\Carbon::now()->subMonth()->startOfMonth(),
                    \Carbon\Carbon::now()->subMonth()->endOfMonth(),
                ]);
                $dateString = \Carbon\Carbon::now()->subMonth()->format('M_Y');
                break;
            case 'custom':
                $from = \Carbon\Carbon::parse($this->exportFrom)->startOfDay();
                $until = \Carbon\Carbon::parse($this->exportUntil)->endOfDay();
                $query->whereBetween('created_at', [$from, $until]);
                $dateString = $from->format('dM') . '-' . $until->format('dM') . '_' . $from->format('Y');
                break;
            default:
                $dateString = \Carbon\Carbon::now()->format('dMY');
        }

        return [$query->orderBy('created_at', 'desc')->get(), $dateString];
    }

    public function exportPdf(): mixed
    {
        [$records, $dateString] = $this->buildExportQuery();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.stock-movements', ['records' => $records]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "stock-movements-{$dateString}.pdf",
            ['Content-Type' => 'application/pdf',]
        );
    }

    public function exportCsv(): mixed
    {
        [$records, $dateString] = $this->buildExportQuery();

        $csvData = "Tanggal,Nama Barang,Tipe,Jumlah,Referensi,Catatan,Admin\n";

        foreach ($records as $record) {
            $csvData .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $record->created_at->format('Y-m-d H:i:s'),
                $record->product->nama ?? '',
                $record->type,
                $record->quantity,
                $record->reference_id,
                str_replace('"', '""', $record->notes ?? ''),
                $record->user->name ?? ''
            );
        }

        return response()->streamDownload(
            fn () => print($csvData),
            "stock-movements-{$dateString}.csv",
            ['Content-Type' => 'text/csv']
        );
    }
}
