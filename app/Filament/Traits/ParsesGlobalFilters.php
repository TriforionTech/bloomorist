<?php

namespace App\Filament\Traits;

use App\Services\DashboardPeriod;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;

trait ParsesGlobalFilters
{
    /**
     * Periode aktif dari filter global dashboard (`$this->filters` dari InteractsWithPageFilters).
     */
    protected function dashboardPeriod(): DashboardPeriod
    {
        return DashboardPeriod::fromFilters($this->filters ?? null);
    }

    /**
     * Parse the global dashboard filters into a startDate and endDate.
     * Dipertahankan untuk kompatibilitas; kini didelegasikan ke DashboardPeriod.
     *
     * @return array{0: Carbon|null, 1: Carbon|null, 2: string}
     */
    protected function parseFilterDates(): array
    {
        $period = $this->dashboardPeriod();

        return [
            $period->start ? Carbon::instance($period->start) : null,
            $period->end ? Carbon::instance($period->end) : null,
            $period->preset,
        ];
    }

    /**
     * Susun teks, warna, dan ikon perbandingan KPI terhadap periode sebelumnya.
     *
     * Mengembalikan null bila periode tidak punya pembanding bermakna (Kustom / Semua Waktu).
     *
     * @return array{text: string, color: string, icon: string|null}|null
     */
    protected function comparisonDescriptor(
        DashboardPeriod $period,
        float|int $current,
        float|int|null $previous,
        bool $higherIsBetter = true,
    ): ?array {
        if (! $period->hasComparison()) {
            return null;
        }

        if ($previous === null || (float) $previous == 0.0) {
            return ['text' => 'Belum ada data pembanding', 'color' => 'gray', 'icon' => null];
        }

        $percent = (($current - $previous) / abs($previous)) * 100;
        $rounded = round($percent, 1);

        if ($rounded == 0.0) {
            return ['text' => "Tidak berubah vs {$period->compareLabel}", 'color' => 'gray', 'icon' => 'heroicon-m-minus'];
        }

        $isUp = $rounded > 0;
        $isGood = $isUp === $higherIsBetter;
        $formatted = rtrim(rtrim(number_format(abs($rounded), 1, ',', '.'), '0'), ',');

        return [
            'text' => ($isUp ? '▲ ' : '▼ ')."{$formatted}% vs {$period->compareLabel}",
            'color' => $isGood ? 'success' : 'danger',
            'icon' => null,
        ];
    }

    /**
     * Nilai Stat dengan angka utama proporsional dan keterangan deskripsi yang jelas dibaca.
     */
    protected function statValue(string $value, ?string $caption = null): HtmlString
    {
        $html = '<span class="block text-xl xl:text-2xl font-bold tracking-tight text-gray-950 dark:text-white">'.e($value).'</span>';

        if ($caption !== null) {
            $html .= '<span class="block text-sm font-medium text-gray-600 dark:text-gray-300 mt-0.5">'.e($caption).'</span>';
        }

        return new HtmlString($html);
    }

    protected function formatRupiah(float|int $amount): string
    {
        return ($amount < 0 ? '-' : '').'Rp '.number_format(abs($amount), 0, ',', '.');
    }
}
