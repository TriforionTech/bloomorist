<?php

namespace App\Filament\Traits;

use Closure;
use Throwable;

/**
 * State kosong/error untuk ChartWidget dashboard.
 *
 * Widget memanggil `guardChartData()` di dalam getData(); view membaca
 * `hasChartError()` dan `hasChartData()` untuk memilih tampilan.
 */
trait HandlesChartState
{
    protected bool $chartLoadFailed = false;

    protected bool $chartHasData = false;

    public function hasChartError(): bool
    {
        $this->getCachedData();

        return $this->chartLoadFailed;
    }

    public function hasChartData(): bool
    {
        $this->getCachedData();

        return $this->chartHasData;
    }

    public function getEmptyStateMessage(): string
    {
        return 'Belum ada data pada periode ini';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-chart-pie';
    }

    /**
     * @param  Closure(): array{hasData: bool, chart: array<string, mixed>}  $callback
     * @return array<string, mixed>
     */
    protected function guardChartData(Closure $callback): array
    {
        try {
            $result = $callback();
            $this->chartHasData = $result['hasData'];
            $this->chartLoadFailed = false;

            return $result['chart'];
        } catch (Throwable $exception) {
            report($exception);

            $this->chartLoadFailed = true;
            $this->chartHasData = false;

            return ['datasets' => [], 'labels' => []];
        }
    }
}
