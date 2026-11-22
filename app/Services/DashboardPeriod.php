<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Representasi periode global dashboard beserta periode pembandingnya.
 *
 * Semua rentang bersifat inklusif pada level tanggal (start 00:00 s/d end 23:59).
 * `start`/`end` bernilai null hanya untuk preset "Semua Waktu".
 */
final class DashboardPeriod
{
    public const DEFAULT_PRESET = 'this_month';

    public function __construct(
        public readonly string $preset,
        public readonly ?CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
        public readonly ?CarbonImmutable $compareStart = null,
        public readonly ?CarbonImmutable $compareEnd = null,
        public readonly ?string $compareLabel = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function presetOptions(): array
    {
        return [
            'today' => 'Hari Ini',
            'yesterday' => 'Kemarin',
            'this_month' => 'Bulan Ini',
            'previous_month' => 'Bulan Lalu',
            'ytd' => 'Tahun Ini',
            'previous_year' => 'Tahun Lalu',
            'all' => 'Semua Waktu',
            'custom' => 'Kustom',
        ];
    }

    /**
     * Bangun periode dari state filter dashboard (`filter_preset`, `startDate`, `endDate`).
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function fromFilters(?array $filters, ?CarbonInterface $now = null): self
    {
        $today = CarbonImmutable::instance($now ?? now())->startOfDay();

        $preset = (string) ($filters['filter_preset'] ?? self::DEFAULT_PRESET);

        if (! array_key_exists($preset, self::presetOptions())) {
            $preset = self::DEFAULT_PRESET;
        }

        return match ($preset) {
            'today' => new self(
                $preset,
                $today,
                $today->endOfDay(),
                $today->subDay(),
                $today->subDay()->endOfDay(),
                'kemarin',
            ),
            'yesterday' => new self(
                $preset,
                $today->subDay(),
                $today->subDay()->endOfDay(),
                $today->subDays(2),
                $today->subDays(2)->endOfDay(),
                '2 hari sebelumnya',
            ),
            'previous_month' => self::previousMonth($today),
            'ytd' => self::yearToDate($today),
            'previous_year' => new self(
                $preset,
                $today->subYearNoOverflow()->startOfYear(),
                $today->subYearNoOverflow()->endOfYear(),
                $today->subYearsNoOverflow(2)->startOfYear(),
                $today->subYearsNoOverflow(2)->endOfYear(),
                'tahun sebelumnya',
            ),
            'all' => new self($preset, null, null),
            'custom' => self::custom($filters ?? [], $today),
            default => self::thisMonth($today),
        };
    }

    public function isAllTime(): bool
    {
        return $this->start === null && $this->end === null;
    }

    public function hasComparison(): bool
    {
        return $this->compareStart !== null && $this->compareEnd !== null;
    }

    /**
     * Periode pembanding sebagai objek periode tersendiri (tanpa pembanding lanjutan).
     */
    public function comparison(): ?self
    {
        if (! $this->hasComparison()) {
            return null;
        }

        return new self('comparison', $this->compareStart, $this->compareEnd);
    }

    public function presetLabel(): string
    {
        return self::presetOptions()[$this->preset] ?? 'Bulan Ini';
    }

    public function isSingleDay(): bool
    {
        return $this->start !== null && $this->end !== null && $this->start->isSameDay($this->end);
    }

    /**
     * Rentang tanggal yang mudah dibaca, mis. "1 – 31 Okt 2026".
     */
    public function rangeLabel(): string
    {
        return self::formatRange($this->start, $this->end);
    }

    public function compareRangeLabel(): ?string
    {
        if (! $this->hasComparison()) {
            return null;
        }

        return self::formatRange($this->compareStart, $this->compareEnd);
    }

    public static function formatRange(?CarbonImmutable $start, ?CarbonImmutable $end): string
    {
        if ($start === null || $end === null) {
            return 'Seluruh riwayat transaksi';
        }

        $start = $start->locale('id');
        $end = $end->locale('id');

        if ($start->isSameDay($end)) {
            return $end->translatedFormat('j M Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->translatedFormat('j').' – '.$end->translatedFormat('j M Y');
        }

        if ($start->isSameYear($end)) {
            return $start->translatedFormat('j M').' – '.$end->translatedFormat('j M Y');
        }

        return $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y');
    }

    private static function thisMonth(CarbonImmutable $today): self
    {
        $previousStart = $today->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $previousStart->addDays($today->day - 1)->min($previousStart->endOfMonth())->endOfDay();

        return new self(
            'this_month',
            $today->startOfMonth(),
            $today->endOfMonth(),
            $previousStart,
            $previousEnd,
            'bulan lalu',
        );
    }

    private static function previousMonth(CarbonImmutable $today): self
    {
        $start = $today->subMonthNoOverflow()->startOfMonth();
        $before = $start->subMonthNoOverflow()->startOfMonth();

        return new self(
            'previous_month',
            $start,
            $start->endOfMonth(),
            $before,
            $before->endOfMonth(),
            'bulan sebelumnya',
        );
    }

    private static function yearToDate(CarbonImmutable $today): self
    {
        $previousStart = $today->subYearNoOverflow()->startOfYear();
        $previousEnd = $today->subYearNoOverflow()->endOfDay();

        return new self(
            'ytd',
            $today->startOfYear(),
            $today->endOfDay(),
            $previousStart,
            $previousEnd,
            'tahun lalu',
        );
    }

    /**
     * Kustom: tanpa pembanding. Tanggal tunggal dianggap rentang satu hari,
     * tanggal terbalik ditukar, dan tanpa tanggal kembali ke bulan berjalan.
     *
     * @param  array<string, mixed>  $filters
     */
    private static function custom(array $filters, CarbonImmutable $today): self
    {
        $start = self::parseDate($filters['startDate'] ?? null);
        $end = self::parseDate($filters['endDate'] ?? null);

        if ($start === null && $end === null) {
            return new self('custom', $today->startOfMonth(), $today->endOfMonth());
        }

        $start ??= $end;
        $end ??= $start;

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return new self('custom', $start->startOfDay(), $end->endOfDay());
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
