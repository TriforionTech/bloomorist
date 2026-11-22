<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GenerateInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Widgets\CurrentStatusOverview;
use App\Filament\Widgets\RecentOrders;
use App\Filament\Widgets\SalesChart;
use App\Filament\Widgets\SalesInsights;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopCustomers;
use App\Filament\Widgets\TopProducts;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\DashboardPeriod;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-25 10:30:00'));

        $this->actingAs(User::create([
            'name' => 'Admin Dashboard',
            'email' => 'dashboard_revision@example.com',
            'password' => bcrypt('password'),
        ]));
    }

    private function makeInvoice(string $status, string $issuedDate, float $total, string $customer = 'Budi'): Invoice
    {
        static $sequence = 0;
        $sequence++;

        return Invoice::withoutEvents(fn (): Invoice => Invoice::create([
            'invoice_number' => 'INV-TEST-'.$sequence,
            'customer_type' => 'non_member',
            'customer_name' => $customer,
            'subtotal' => $total,
            'grand_total' => $total,
            'issued_date' => $issuedDate,
            'status' => $status,
        ]));
    }

    private function makeItem(Invoice $invoice, string $name, int $quantity): InvoiceItem
    {
        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => null,
            'snapshot_sku' => 'SKU-'.strtoupper(substr(md5($name), 0, 6)),
            'snapshot_name' => $name,
            'quantity' => $quantity,
            'unit_price' => 1000,
            'normal_price' => 1000,
            'discount_price' => 1000,
        ]);
    }

    public function test_metrics_follow_selected_period_and_ignore_other_months(): void
    {
        $this->makeInvoice('paid', '2026-10-10', 1000000);
        $this->makeInvoice('pending', '2026-10-12', 500000);
        $this->makeInvoice('paid', '2026-09-10', 700000);
        $this->makeInvoice('paid', '2025-10-10', 900000);

        $metrics = app(DashboardMetricsService::class);
        $now = CarbonImmutable::parse('2026-10-25');

        $thisMonth = $metrics->invoiceSummary(DashboardPeriod::fromFilters(['filter_preset' => 'this_month'], $now));
        $this->assertSame(2, $thisMonth['total_count']);
        $this->assertSame(1, $thisMonth['paid_count']);
        $this->assertEquals(1000000, $thisMonth['revenue']);

        $lastMonth = $metrics->invoiceSummary(DashboardPeriod::fromFilters(['filter_preset' => 'previous_month'], $now));
        $this->assertEquals(700000, $lastMonth['revenue']);

        $lastYear = $metrics->invoiceSummary(DashboardPeriod::fromFilters(['filter_preset' => 'previous_year'], $now));
        $this->assertEquals(900000, $lastYear['revenue']);

        $all = $metrics->invoiceSummary(DashboardPeriod::fromFilters(['filter_preset' => 'all'], $now));
        $this->assertSame(4, $all['total_count']);
        $this->assertEquals(2600000, $all['revenue']);
    }

    public function test_last_day_of_range_is_inclusive(): void
    {
        $this->makeInvoice('paid', '2026-10-31', 250000);

        $summary = app(DashboardMetricsService::class)->invoiceSummary(
            DashboardPeriod::fromFilters(['filter_preset' => 'custom', 'startDate' => '2026-10-31', 'endDate' => '2026-10-31'], CarbonImmutable::parse('2026-10-25'))
        );

        $this->assertEquals(250000, $summary['revenue']);
    }

    public function test_stats_overview_shows_kpis_with_green_increase_against_previous_month(): void
    {
        $this->makeInvoice('paid', '2026-10-10', 1200000);
        $this->makeInvoice('paid', '2026-09-10', 1000000);

        Livewire::test(StatsOverview::class)
            ->assertStatus(200)
            ->assertSee('Total Transaksi')
            ->assertSee('Pendapatan')
            ->assertSee('Pengeluaran')
            ->assertSee('Laba Kotor')
            ->assertSee('Laba Bersih')
            ->assertSee('Rp 1.200.000')
            ->assertSee('▲ 20% vs bulan lalu')
            ->assertDontSee('Total Transaksi Hari Ini');
    }

    public function test_decrease_in_revenue_is_flagged_and_missing_baseline_is_explained(): void
    {
        $this->makeInvoice('paid', '2026-10-10', 800000);
        $this->makeInvoice('paid', '2026-09-10', 1000000);

        Livewire::test(StatsOverview::class)->assertSee('▼ 20% vs bulan lalu');

        Livewire::test(StatsOverview::class, ['pageFilters' => ['filter_preset' => 'previous_year']])
            ->assertSee('Belum ada data pembanding');
    }

    public function test_custom_and_all_time_periods_hide_comparison(): void
    {
        $this->makeInvoice('paid', '2026-10-10', 800000);
        $this->makeInvoice('paid', '2026-09-10', 1000000);

        Livewire::test(StatsOverview::class, ['pageFilters' => ['filter_preset' => 'custom', 'startDate' => '2026-09-01', 'endDate' => '2026-10-31']])
            ->assertDontSee('vs ')
            ->assertDontSee('Belum ada data pembanding');

        Livewire::test(StatsOverview::class, ['pageFilters' => ['filter_preset' => 'all']])
            ->assertDontSee('vs ')
            ->assertDontSee('Belum ada data pembanding');
    }

    public function test_expense_total_counts_only_posted_expenses_in_period(): void
    {
        $this->seed(DatabaseSeeder::class);
        $base = [
            'coa_id' => ChartOfAccount::where('kode_akun', '6101')->value('id'),
            'coa_kredit_id' => ChartOfAccount::where('kode_akun', '1101')->value('id'),
        ];

        Expense::insert([
            ['keterangan' => 'Listrik', 'nominal' => 300000, 'status' => 'posted', 'created_at' => '2026-10-05 09:00:00', 'updated_at' => '2026-10-05 09:00:00'] + $base,
            ['keterangan' => 'Draft', 'nominal' => 111000, 'status' => 'draft', 'created_at' => '2026-10-06 09:00:00', 'updated_at' => '2026-10-06 09:00:00'] + $base,
            ['keterangan' => 'Void', 'nominal' => 222000, 'status' => 'void', 'created_at' => '2026-10-07 09:00:00', 'updated_at' => '2026-10-07 09:00:00'] + $base,
            ['keterangan' => 'Bulan lalu', 'nominal' => 999000, 'status' => 'posted', 'created_at' => '2026-09-30 23:59:59', 'updated_at' => '2026-09-30 23:59:59'] + $base,
        ]);

        $total = app(DashboardMetricsService::class)->expenseTotal(
            DashboardPeriod::fromFilters(['filter_preset' => 'this_month'], CarbonImmutable::parse('2026-10-25'))
        );

        $this->assertEquals(300000, $total);
    }

    public function test_profit_is_unavailable_without_matching_accounting_period(): void
    {
        $this->makeInvoice('paid', '2026-10-10', 1000000);

        $this->assertNull(app(DashboardMetricsService::class)->profit(
            DashboardPeriod::fromFilters(['filter_preset' => 'this_month'], CarbonImmutable::parse('2026-10-25'))
        ));

        Livewire::test(StatsOverview::class)->assertSee('Belum tersedia');
    }

    public function test_current_status_widget_ignores_period_filter(): void
    {
        $this->makeInvoice('pending', '2024-02-01', 450000);
        Product::create(['sku' => 'LOW-1', 'nama' => 'Mawar', 'harga_beli' => 1, 'harga_jual' => 2, 'stok' => -2, 'is_active' => true]);
        Product::create(['sku' => 'OK-1', 'nama' => 'Tulip', 'harga_beli' => 1, 'harga_jual' => 2, 'stok' => 50, 'is_active' => true]);

        $state = app(DashboardMetricsService::class)->currentState();
        $this->assertSame(1, $state['pending_count']);
        $this->assertSame(1, $state['low_stock_count']);
        $this->assertSame(1, $state['negative_stock_count']);

        Livewire::test(CurrentStatusOverview::class, ['pageFilters' => ['filter_preset' => 'yesterday']])
            ->assertSee('Transaksi Pending')
            ->assertSee('Stok Menipis')
            ->assertSee('Kondisi realtime · Diperbarui 10.30')
            ->assertSee('1 stok negatif');
    }

    public function test_top_products_use_paid_invoices_only_and_exclude_box_and_wrapping(): void
    {
        $paid = $this->makeInvoice('paid', '2026-10-10', 100000);
        $pending = $this->makeInvoice('pending', '2026-10-11', 100000);

        $this->makeItem($paid, 'Mawar Merah', 5);
        $this->makeItem($paid, 'Box', 9);
        $this->makeItem($paid, 'Wrapping', 9);
        $this->makeItem($pending, 'Tulip', 20);

        $products = app(DashboardMetricsService::class)->topProducts(
            DashboardPeriod::fromFilters(['filter_preset' => 'this_month'], CarbonImmutable::parse('2026-10-25'))
        );

        $this->assertSame([['name' => 'Mawar Merah', 'qty' => 5]], $products->all());

        Livewire::test(TopProducts::class)->assertStatus(200)->assertSee('Top 10 Produk Terlaris');
    }

    public function test_top_products_pie_shows_empty_state_when_no_sales(): void
    {
        Livewire::test(TopProducts::class)->assertSee('Belum ada produk terjual pada periode ini');
    }

    public function test_top_customers_are_ranked_by_total_spend(): void
    {
        $this->makeInvoice('paid', '2026-10-10', 300000, 'Andi');
        $this->makeInvoice('paid', '2026-10-11', 200000, 'Andi');
        $this->makeInvoice('paid', '2026-10-12', 400000, 'Budi');
        $this->makeInvoice('pending', '2026-10-12', 900000, 'Citra');

        $customers = app(DashboardMetricsService::class)->topCustomers(
            DashboardPeriod::fromFilters(['filter_preset' => 'this_month'], CarbonImmutable::parse('2026-10-25'))
        );

        $this->assertSame(['Andi', 'Budi'], $customers->pluck('name')->all());
        $this->assertEquals(500000, $customers->first()['total']);
        $this->assertSame(2, $customers->first()['invoices']);

        Livewire::test(TopCustomers::class)->assertStatus(200)->assertSee('Pelanggan Teratas');
        Livewire::test(TopCustomers::class, ['pageFilters' => ['filter_preset' => 'previous_year']])
            ->assertSee('Belum ada belanja pelanggan pada periode ini');
    }

    public function test_sales_chart_series_uses_proper_interval_and_trims_future_days(): void
    {
        $this->makeInvoice('paid', '2026-10-02', 100000);
        $this->makeInvoice('paid', '2026-10-02', 50000);

        $metrics = app(DashboardMetricsService::class);
        $now = CarbonImmutable::parse('2026-10-25');

        $month = $metrics->salesSeries(DashboardPeriod::fromFilters(['filter_preset' => 'this_month'], $now));
        $this->assertSame('day', $month['interval']);
        $this->assertCount(25, $month['labels']);
        $this->assertEquals(150000, $month['total']);

        $year = $metrics->salesSeries(DashboardPeriod::fromFilters(['filter_preset' => 'ytd'], $now));
        $this->assertSame('month', $year['interval']);
        $this->assertCount(10, $year['labels']);

        $today = $metrics->salesSeries(DashboardPeriod::fromFilters(['filter_preset' => 'today'], $now));
        $this->assertSame('hour', $today['interval']);
        $this->assertEquals(0, $today['total']);
    }

    public function test_sales_chart_renders_empty_state_and_chart_with_data(): void
    {
        Livewire::test(SalesChart::class)
            ->assertStatus(200)
            ->assertSee('Grafik Penjualan')
            ->assertSee('Belum ada transaksi pada periode ini')
            ->assertDontSee('data-chart-type', false);

        $this->makeInvoice('paid', '2026-10-10', 100000);

        Livewire::test(SalesChart::class)
            ->assertDontSee('Belum ada transaksi pada periode ini')
            ->assertSee('data-chart-type', false);
    }

    public function test_sales_chart_handles_partial_and_inverted_custom_filters(): void
    {
        Livewire::test(SalesChart::class, ['pageFilters' => ['filter_preset' => 'custom', 'startDate' => null, 'endDate' => '2026-09-30']])->assertStatus(200);
        Livewire::test(SalesChart::class, ['pageFilters' => ['filter_preset' => 'custom', 'startDate' => '2026-09-30', 'endDate' => '2026-09-01']])->assertStatus(200);
    }

    public function test_sales_insights_invoice_paid_thresholds_and_metrics(): void
    {
        $this->assertSame('success', SalesInsights::paidRateColor(80));
        $this->assertSame('warning', SalesInsights::paidRateColor(79.9));
        $this->assertSame('warning', SalesInsights::paidRateColor(50));
        $this->assertSame('danger', SalesInsights::paidRateColor(49.9));

        $paid = $this->makeInvoice('paid', '2026-10-10', 600000);
        $this->makeInvoice('paid', '2026-10-11', 400000);
        $this->makeInvoice('pending', '2026-10-12', 100000);
        $this->makeItem($paid, 'Mawar Merah', 7);

        Livewire::test(SalesInsights::class)
            ->assertStatus(200)
            ->assertSee('AOV')
            ->assertSee('Rp 500.000')
            ->assertSee('7 pcs')
            ->assertSee('Mawar Merah')
            ->assertSee('66,7%');
    }

    public function test_recent_orders_are_limited_to_ten_within_period(): void
    {
        $inPeriod = collect(range(1, 12))->map(fn (int $day): Invoice => $this->makeInvoice('paid', sprintf('2026-10-%02d', $day), 10000 * $day));
        $outside = $this->makeInvoice('paid', '2026-08-01', 5000);

        $component = Livewire::test(RecentOrders::class)
            ->assertStatus(200)
            ->assertCanSeeTableRecords($inPeriod->slice(2)->all())
            ->assertCanNotSeeTableRecords($inPeriod->take(2)->all())
            ->assertCanNotSeeTableRecords([$outside])
            ->assertTableHeaderActionsExistInOrder(['view_all']);

        $this->assertCount(10, $component->instance()->getTableRecords());

        Livewire::test(RecentOrders::class, ['pageFilters' => ['filter_preset' => 'previous_year']])
            ->assertCountTableRecords(0)
            ->assertSee('Belum ada transaksi pada periode ini');
    }

    public function test_dashboard_renders_header_filter_with_active_period(): void
    {
        Livewire::test(Dashboard::class)
            ->assertStatus(200)
            ->assertSee('Periode aktif: Bulan Ini')
            ->assertSee('1 – 31 Okt 2026')
            ->assertSee('Diperbarui 10.30')
            ->set('filters.filter_preset', 'previous_month')
            ->assertSee('Periode aktif: Bulan Lalu')
            ->assertSee('1 – 30 Sep 2026')
            ->set('filters.filter_preset', 'custom')
            ->assertSee('Periode aktif: Kustom');
    }

    public function test_invoice_history_has_new_invoice_button_pointing_to_generate_invoice_page(): void
    {
        Livewire::test(ListInvoices::class)
            ->assertSuccessful()
            ->assertActionExists('newInvoice')
            ->assertActionHasUrl('newInvoice', GenerateInvoice::getUrl());
    }
}
