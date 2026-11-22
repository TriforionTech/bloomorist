<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal metrik dashboard agar seluruh widget memakai definisi yang sama.
 *
 * Definisi:
 * - Pendapatan      : SUM(grand_total) invoice berstatus `paid` berdasarkan issued_date
 *                     (identik dengan jurnal penjualan 4101 yang diposting saat invoice lunas).
 * - Total Transaksi : seluruh invoice pada periode (semua status), sama seperti basis % Invoice Paid.
 * - AOV             : Pendapatan / jumlah invoice paid.
 * - Pengeluaran     : SUM(nominal) Expense berstatus `posted` berdasarkan tanggal pengeluaran.
 * - Laba            : dari AccountingService::getIncomeStatement(), hanya jika rentang = Periode Akuntansi.
 */
class DashboardMetricsService
{
    public const EXCLUDED_PRODUCT_NAMES = ['Box', 'Wrapping'];

    public const LOW_STOCK_THRESHOLD = 10;

    /**
     * Batasi query pada rentang tanggal periode (end eksklusif +1 hari agar aman untuk kolom date/datetime).
     */
    public function applyDateRange(
        EloquentBuilder|QueryBuilder $query,
        ?CarbonImmutable $start,
        ?CarbonImmutable $end,
        string $column = 'issued_date',
    ): EloquentBuilder|QueryBuilder {
        if ($start !== null) {
            $query->where($column, '>=', $start->startOfDay()->toDateString());
        }

        if ($end !== null) {
            $query->where($column, '<', $end->startOfDay()->addDay()->toDateString());
        }

        return $query;
    }

    public function applyPeriod(
        EloquentBuilder|QueryBuilder $query,
        DashboardPeriod $period,
        string $column = 'issued_date',
    ): EloquentBuilder|QueryBuilder {
        return $this->applyDateRange($query, $period->start, $period->end, $column);
    }

    /**
     * @return array{total_count: int, paid_count: int, revenue: float}
     */
    public function invoiceSummary(DashboardPeriod $period): array
    {
        $row = $this->applyPeriod(Invoice::query(), $period)
            ->selectRaw("COUNT(*) AS total_count, COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count, COALESCE(SUM(CASE WHEN status = 'paid' THEN grand_total ELSE 0 END), 0) AS revenue")
            ->first();

        return [
            'total_count' => (int) ($row->total_count ?? 0),
            'paid_count' => (int) ($row->paid_count ?? 0),
            'revenue' => (float) ($row->revenue ?? 0),
        ];
    }

    public function soldQuantity(DashboardPeriod $period): int
    {
        $query = InvoiceItem::query()
            ->join('bl_invoices_t', 'bl_invoice_items_t.invoice_id', '=', 'bl_invoices_t.id')
            ->where('bl_invoices_t.status', 'paid')
            ->whereNotIn('bl_invoice_items_t.snapshot_name', self::EXCLUDED_PRODUCT_NAMES);

        $this->applyPeriod($query, $period, 'bl_invoices_t.issued_date');

        return (int) $query->sum('bl_invoice_items_t.quantity');
    }

    public function expenseTotal(DashboardPeriod $period): float
    {
        $query = Expense::query()->where('status', 'posted');

        $this->applyPeriod($query, $period, 'created_at');

        return (float) $query->sum('nominal');
    }

    /**
     * Laba kotor & laba bersih mengikuti Laporan Laba Rugi.
     *
     * Hanya tersedia bila rentang persis sama dengan satu Periode Akuntansi yang sudah
     * memiliki nilai persediaan akhir (HPP periodik butuh persediaan awal/akhir).
     * Selain itu mengembalikan null agar tidak menampilkan angka yang menyesatkan.
     *
     * @return array{gross_profit: float, net_profit: float}|null
     */
    public function profit(DashboardPeriod $period): ?array
    {
        if ($period->start === null || $period->end === null) {
            return null;
        }

        $accountingPeriod = AccountingPeriod::query()
            ->whereDate('start_date', $period->start->toDateString())
            ->whereDate('end_date', $period->end->toDateString())
            ->first();

        if (! $accountingPeriod || $accountingPeriod->closing_inventory_value === null) {
            return null;
        }

        $hasPeriodicAccounts = ChartOfAccount::query()
            ->whereIn('kode_akun', ['4101', '4102', '5101', '5102', '5103', '1104'])
            ->exists();

        if (! $hasPeriodicAccounts) {
            return null;
        }

        $income = app(AccountingService::class)->getIncomeStatement(
            Carbon::parse($period->start->toDateString())->startOfDay(),
            Carbon::parse($period->end->toDateString())->endOfDay(),
        );

        return [
            'gross_profit' => (float) $income['laba_kotor'],
            'net_profit' => (float) $income['laba_rugi'],
        ];
    }

    /**
     * Produk terlaris berdasarkan qty dari invoice lunas (Box/Wrapping dikecualikan).
     *
     * @return Collection<int, array{name: string, qty: int}>
     */
    public function topProducts(DashboardPeriod $period, int $limit = 10): Collection
    {
        $query = InvoiceItem::query()
            ->selectRaw('bl_invoice_items_t.snapshot_name AS name, SUM(bl_invoice_items_t.quantity) AS qty')
            ->join('bl_invoices_t', 'bl_invoice_items_t.invoice_id', '=', 'bl_invoices_t.id')
            ->where('bl_invoices_t.status', 'paid')
            ->whereNotIn('bl_invoice_items_t.snapshot_name', self::EXCLUDED_PRODUCT_NAMES);

        $this->applyPeriod($query, $period, 'bl_invoices_t.issued_date');

        return $query
            ->groupBy('bl_invoice_items_t.snapshot_name')
            ->havingRaw('SUM(bl_invoice_items_t.quantity) > 0')
            ->orderByDesc('qty')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => ['name' => (string) $row->name, 'qty' => (int) $row->qty]);
    }

    /**
     * Pelanggan dengan total belanja tertinggi (invoice lunas). Invoice tanpa customer_id
     * dikelompokkan berdasarkan customer_name yang tersimpan pada invoice.
     *
     * @return Collection<int, array{name: string, total: float, invoices: int}>
     */
    public function topCustomers(DashboardPeriod $period, int $limit = 10): Collection
    {
        $query = Invoice::query()
            ->leftJoin('bl_customers_t', 'bl_invoices_t.customer_id', '=', 'bl_customers_t.id')
            ->where('bl_invoices_t.status', 'paid')
            ->selectRaw('bl_invoices_t.customer_id AS customer_id, bl_invoices_t.customer_name AS invoice_customer_name, bl_customers_t.nama AS customer_nama, COUNT(*) AS invoices, SUM(bl_invoices_t.grand_total) AS total')
            ->groupBy('bl_invoices_t.customer_id', 'bl_invoices_t.customer_name', 'bl_customers_t.nama');

        $this->applyPeriod($query, $period, 'bl_invoices_t.issued_date');

        return $query->get()
            ->groupBy(fn ($row): string => $row->customer_id !== null
                ? 'id:'.$row->customer_id
                : 'name:'.mb_strtolower(trim((string) $row->invoice_customer_name)))
            ->map(fn (Collection $rows): array => [
                'name' => (string) ($rows->first()->customer_nama ?: $rows->first()->invoice_customer_name ?: 'Tanpa Nama'),
                'total' => (float) $rows->sum('total'),
                'invoices' => (int) $rows->sum('invoices'),
            ])
            ->sortByDesc('total')
            ->take($limit)
            ->values();
    }

    /**
     * Seri penjualan (invoice lunas) dengan interval otomatis.
     *
     * Interval: jam (1 hari), harian (<= 62 hari), bulanan (<= 24 bulan), selain itu tahunan.
     * Rentang dipotong sampai hari ini sehingga tidak ada ruang kosong untuk tanggal mendatang.
     *
     * @return array{labels: array<int, string>, data: array<int, float>, interval: string, total: float}
     */
    public function salesSeries(DashboardPeriod $period): array
    {
        $today = CarbonImmutable::today();
        $start = $period->start;
        $end = $period->end === null ? $today : $period->end->startOfDay()->min($today);

        if ($start === null) {
            $earliest = Invoice::query()->where('status', 'paid')->whereNotNull('issued_date')->min('issued_date');

            if ($earliest === null) {
                return $this->emptySeries('month');
            }

            $start = CarbonImmutable::parse($earliest);
        }

        $start = $start->startOfDay();

        if ($start->gt($end)) {
            return $this->emptySeries('day');
        }

        if ($start->isSameDay($end)) {
            return $this->hourlySeries($start);
        }

        $paid = $this->applyDateRange(Invoice::query()->where('status', 'paid'), $start, $end)
            ->selectRaw('issued_date AS issued, SUM(grand_total) AS total')
            ->groupBy('issued_date')
            ->get();

        $perDay = [];
        foreach ($paid as $row) {
            $key = CarbonImmutable::parse($row->issued)->toDateString();
            $perDay[$key] = ($perDay[$key] ?? 0) + (float) $row->total;
        }

        $days = (int) $start->diffInDays($end) + 1;

        if ($days <= 62) {
            return $this->dailySeries($start, $end, $perDay);
        }

        $months = (int) $start->startOfMonth()->diffInMonths($end->startOfMonth()) + 1;

        return $months <= 24
            ? $this->monthlySeries($start, $end, $perDay)
            : $this->yearlySeries($start, $end, $perDay);
    }

    /**
     * Kondisi operasional saat ini. Tidak dipengaruhi filter periode.
     *
     * @return array{pending_count: int, pending_value: float, low_stock_count: int, negative_stock_count: int}
     */
    public function currentState(): array
    {
        $pending = Invoice::query()
            ->where('status', 'pending')
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(grand_total), 0) AS pending_value')
            ->first();

        $stock = Product::query()
            ->where('is_active', true)
            ->where('stok', '<', self::LOW_STOCK_THRESHOLD)
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN stok < 0 THEN 1 ELSE 0 END), 0) AS negative')
            ->first();

        return [
            'pending_count' => (int) ($pending->total ?? 0),
            'pending_value' => (float) ($pending->pending_value ?? 0),
            'low_stock_count' => (int) ($stock->total ?? 0),
            'negative_stock_count' => (int) ($stock->negative ?? 0),
        ];
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, float>, interval: string, total: float}
     */
    private function emptySeries(string $interval): array
    {
        return ['labels' => [], 'data' => [], 'interval' => $interval, 'total' => 0.0];
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, float>, interval: string, total: float}
     */
    private function hourlySeries(CarbonImmutable $day): array
    {
        $rows = Invoice::query()
            ->where('status', 'paid')
            ->where('issued_date', '>=', $day->toDateString())
            ->where('issued_date', '<', $day->addDay()->toDateString())
            ->get(['created_at', 'grand_total']);

        $perHour = [];
        foreach ($rows as $row) {
            $hour = (int) ($row->created_at?->hour ?? 0);
            $perHour[$hour] = ($perHour[$hour] ?? 0) + (float) $row->grand_total;
        }

        if ($perHour === []) {
            return $this->emptySeries('hour');
        }

        $from = min(8, min(array_keys($perHour)));
        $to = max(20, max(array_keys($perHour)));

        $labels = [];
        $data = [];
        for ($hour = $from; $hour <= $to; $hour++) {
            $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';
            $data[] = $perHour[$hour] ?? 0.0;
        }

        return ['labels' => $labels, 'data' => $data, 'interval' => 'hour', 'total' => array_sum($data)];
    }

    /**
     * @param  array<string, float>  $perDay
     * @return array{labels: array<int, string>, data: array<int, float>, interval: string, total: float}
     */
    private function dailySeries(CarbonImmutable $start, CarbonImmutable $end, array $perDay): array
    {
        $labels = [];
        $data = [];

        for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDay()) {
            $labels[] = $cursor->locale('id')->translatedFormat('j M');
            $data[] = $perDay[$cursor->toDateString()] ?? 0.0;
        }

        return ['labels' => $labels, 'data' => $data, 'interval' => 'day', 'total' => array_sum($data)];
    }

    /**
     * @param  array<string, float>  $perDay
     * @return array{labels: array<int, string>, data: array<int, float>, interval: string, total: float}
     */
    private function monthlySeries(CarbonImmutable $start, CarbonImmutable $end, array $perDay): array
    {
        $perMonth = [];
        foreach ($perDay as $date => $total) {
            $key = substr($date, 0, 7);
            $perMonth[$key] = ($perMonth[$key] ?? 0) + $total;
        }

        $labels = [];
        $data = [];
        $crossesYears = ! $start->isSameYear($end);

        for ($cursor = $start->startOfMonth(); $cursor->lte($end); $cursor = $cursor->addMonthNoOverflow()) {
            $labels[] = $cursor->locale('id')->translatedFormat($crossesYears ? 'M Y' : 'M');
            $data[] = $perMonth[$cursor->format('Y-m')] ?? 0.0;
        }

        return ['labels' => $labels, 'data' => $data, 'interval' => 'month', 'total' => array_sum($data)];
    }

    /**
     * @param  array<string, float>  $perDay
     * @return array{labels: array<int, string>, data: array<int, float>, interval: string, total: float}
     */
    private function yearlySeries(CarbonImmutable $start, CarbonImmutable $end, array $perDay): array
    {
        $perYear = [];
        foreach ($perDay as $date => $total) {
            $key = substr($date, 0, 4);
            $perYear[$key] = ($perYear[$key] ?? 0) + $total;
        }

        $labels = [];
        $data = [];

        for ($year = $start->year; $year <= $end->year; $year++) {
            $labels[] = (string) $year;
            $data[] = $perYear[(string) $year] ?? 0.0;
        }

        return ['labels' => $labels, 'data' => $data, 'interval' => 'year', 'total' => array_sum($data)];
    }
}
