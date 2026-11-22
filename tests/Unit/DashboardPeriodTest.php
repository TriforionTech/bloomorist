<?php

namespace Tests\Unit;

use App\Services\DashboardPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DashboardPeriodTest extends TestCase
{
    private function period(string $preset, string $now = '2026-10-25 10:00:00', array $extra = []): DashboardPeriod
    {
        return DashboardPeriod::fromFilters(
            ['filter_preset' => $preset, ...$extra],
            CarbonImmutable::parse($now),
        );
    }

    public function test_default_preset_is_this_month_with_aligned_previous_month_comparison(): void
    {
        $period = DashboardPeriod::fromFilters(null, CarbonImmutable::parse('2026-10-25 10:00:00'));

        $this->assertSame('this_month', $period->preset);
        $this->assertSame('2026-10-01', $period->start->toDateString());
        $this->assertSame('2026-10-31', $period->end->toDateString());
        $this->assertSame('2026-09-01', $period->compareStart->toDateString());
        $this->assertSame('2026-09-25', $period->compareEnd->toDateString());
        $this->assertSame('bulan lalu', $period->compareLabel);
    }

    public function test_this_month_comparison_does_not_overflow_on_short_previous_month(): void
    {
        $period = $this->period('this_month', '2026-03-31 10:00:00');

        $this->assertSame('2026-02-01', $period->compareStart->toDateString());
        $this->assertSame('2026-02-28', $period->compareEnd->toDateString());
    }

    public function test_previous_month_is_full_calendar_month_not_last_30_days(): void
    {
        $period = $this->period('previous_month', '2026-03-31 10:00:00');

        $this->assertSame('2026-02-01', $period->start->toDateString());
        $this->assertSame('2026-02-28', $period->end->toDateString());
        $this->assertSame('2026-01-01', $period->compareStart->toDateString());
        $this->assertSame('2026-01-31', $period->compareEnd->toDateString());
    }

    public function test_previous_month_crosses_year_boundary(): void
    {
        $period = $this->period('previous_month', '2026-01-15 10:00:00');

        $this->assertSame('2025-12-01', $period->start->toDateString());
        $this->assertSame('2025-12-31', $period->end->toDateString());
    }

    public function test_previous_year_is_full_calendar_year(): void
    {
        $period = $this->period('previous_year');

        $this->assertSame('2025-01-01', $period->start->toDateString());
        $this->assertSame('2025-12-31', $period->end->toDateString());
        $this->assertSame('2024-01-01', $period->compareStart->toDateString());
        $this->assertSame('2024-12-31', $period->compareEnd->toDateString());
    }

    public function test_year_to_date_compares_with_same_span_previous_year(): void
    {
        $period = $this->period('ytd');

        $this->assertSame('2026-01-01', $period->start->toDateString());
        $this->assertSame('2026-10-25', $period->end->toDateString());
        $this->assertSame('2025-01-01', $period->compareStart->toDateString());
        $this->assertSame('2025-10-25', $period->compareEnd->toDateString());
    }

    public function test_today_compares_with_yesterday_and_yesterday_with_two_days_before(): void
    {
        $today = $this->period('today');
        $yesterday = $this->period('yesterday');

        $this->assertSame('2026-10-25', $today->start->toDateString());
        $this->assertSame('2026-10-24', $today->compareStart->toDateString());
        $this->assertSame('2026-10-24', $yesterday->start->toDateString());
        $this->assertSame('2026-10-23', $yesterday->compareStart->toDateString());
    }

    public function test_all_time_has_no_bounds_and_no_comparison(): void
    {
        $period = $this->period('all');

        $this->assertTrue($period->isAllTime());
        $this->assertFalse($period->hasComparison());
        $this->assertNull($period->comparison());
    }

    public function test_custom_has_no_comparison(): void
    {
        $period = $this->period('custom', extra: ['startDate' => '2026-09-01', 'endDate' => '2026-09-10']);

        $this->assertFalse($period->hasComparison());
        $this->assertSame('2026-09-01', $period->start->toDateString());
        $this->assertSame('2026-09-10', $period->end->toDateString());
    }

    public function test_custom_swaps_inverted_dates_and_handles_partial_input(): void
    {
        $inverted = $this->period('custom', extra: ['startDate' => '2026-09-30', 'endDate' => '2026-09-01']);
        $this->assertSame('2026-09-01', $inverted->start->toDateString());
        $this->assertSame('2026-09-30', $inverted->end->toDateString());

        $partial = $this->period('custom', extra: ['startDate' => null, 'endDate' => '2026-09-30']);
        $this->assertTrue($partial->isSingleDay());
        $this->assertSame('2026-09-30', $partial->start->toDateString());

        $empty = $this->period('custom');
        $this->assertSame('2026-10-01', $empty->start->toDateString());
        $this->assertSame('2026-10-31', $empty->end->toDateString());
    }

    public function test_unknown_preset_falls_back_to_this_month(): void
    {
        $this->assertSame('this_month', $this->period('last_999_days')->preset);
    }

    public function test_range_label_is_readable(): void
    {
        $this->assertSame('1 – 31 Okt 2026', $this->period('this_month')->rangeLabel());
        $this->assertSame('Seluruh riwayat transaksi', $this->period('all')->rangeLabel());
        $this->assertSame('25 Okt 2026', $this->period('today')->rangeLabel());
    }
}
