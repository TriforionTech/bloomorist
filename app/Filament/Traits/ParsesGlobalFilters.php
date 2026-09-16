<?php

namespace App\Filament\Traits;

use Carbon\Carbon;

trait ParsesGlobalFilters
{
    /**
     * Parse the global dashboard filters into a startDate and endDate.
     * Uses $this->filters provided by InteractsWithPageFilters.
     */
    protected function parseFilterDates(): array
    {
        $preset = $this->filters['filter_preset'] ?? 'this_month';
        
        $startDate = null;
        $endDate = null;

        if ($preset === 'custom') {
            $startDate = !empty($this->filters['startDate']) ? Carbon::parse($this->filters['startDate'])->startOfDay() : null;
            $endDate = !empty($this->filters['endDate']) ? Carbon::parse($this->filters['endDate'])->endOfDay() : null;
        } else {
            match ($preset) {
                'yesterday' => [$startDate, $endDate] = [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
                'last_7' => [$startDate, $endDate] = [now()->subDays(6)->startOfDay(), now()->endOfDay()],
                'this_month' => [$startDate, $endDate] = [now()->startOfMonth(), now()->endOfMonth()],
                'previous_month' => [$startDate, $endDate] = [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                'last_30' => [$startDate, $endDate] = [now()->subDays(29)->startOfDay(), now()->endOfDay()],
                'last_90' => [$startDate, $endDate] = [now()->subDays(89)->startOfDay(), now()->endOfDay()],
                'last_180' => [$startDate, $endDate] = [now()->subDays(179)->startOfDay(), now()->endOfDay()],
                'ytd' => [$startDate, $endDate] = [now()->startOfYear(), now()->endOfDay()],
                'last_365' => [$startDate, $endDate] = [now()->subDays(364)->startOfDay(), now()->endOfDay()],
                'all' => [$startDate, $endDate] = [null, null],
                default => [$startDate, $endDate] = [now()->startOfMonth(), now()->endOfMonth()],
            };
        }

        return [$startDate, $endDate, $preset];
    }
}
