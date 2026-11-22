<?php

namespace Tests\Feature;

use App\Filament\Widgets\StatsOverview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_overview_renders_successfully(): void
    {
        $user = User::create([
            'name' => 'Admin Dashboard',
            'email' => 'admin_dashboard@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Livewire::test(StatsOverview::class)
            ->assertStatus(200)
            ->assertSee('Total Transaksi')
            ->assertSee('Pendapatan')
            ->assertSee('Pengeluaran');

        Livewire::test(\App\Filament\Widgets\CurrentStatusOverview::class)
            ->assertStatus(200)
            ->assertSee('Transaksi Pending')
            ->assertSee('Stok Menipis');
    }

    public function test_sales_chart_renders_successfully(): void
    {
        $user = User::create([
            'name' => 'Admin Dashboard',
            'email' => 'admin_dashboard@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Filament\Widgets\SalesChart::class)
            ->assertStatus(200)
            ->assertSee('Grafik Penjualan');
    }

    public function test_sales_chart_handles_partial_custom_date_filter_without_error(): void
    {
        $user = User::create([
            'name' => 'Admin Dashboard 2',
            'email' => 'admin_dashboard2@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        // Pass pageFilters in initial mount parameters
        Livewire::test(\App\Filament\Widgets\SalesChart::class, [
            'pageFilters' => [
                'filter_preset' => 'custom',
                'startDate' => null,
                'endDate' => '2026-09-30',
            ],
        ])->assertStatus(200);
    }

    public function test_sales_chart_handles_inverted_custom_date_filter_without_error(): void
    {
        $user = User::create([
            'name' => 'Admin Dashboard 3',
            'email' => 'admin_dashboard3@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Filament\Widgets\SalesChart::class, [
            'pageFilters' => [
                'filter_preset' => 'custom',
                'startDate' => '2026-09-30',
                'endDate' => '2026-09-01',
            ],
        ])->assertStatus(200);
    }

    public function test_dashboard_sibling_widgets_render_successfully(): void
    {
        $user = User::create([
            'name' => 'Admin Dashboard 4',
            'email' => 'admin_dashboard4@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Filament\Widgets\SalesInsights::class)->assertStatus(200);
        Livewire::test(\App\Filament\Widgets\TopProducts::class)->assertStatus(200);
        Livewire::test(\App\Filament\Widgets\TopCustomers::class)->assertStatus(200);
        Livewire::test(\App\Filament\Widgets\LowStockProducts::class)->assertStatus(200);
    }

    public function test_dashboard_sibling_widgets_handle_partial_date_filter(): void
    {
        $user = User::create([
            'name' => 'Admin Dashboard 5',
            'email' => 'admin_dashboard5@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $filters = [
            'filter_preset' => 'custom',
            'startDate' => null,
            'endDate' => '2026-09-30',
        ];

        Livewire::test(\App\Filament\Widgets\SalesInsights::class, ['pageFilters' => $filters])->assertStatus(200);
        Livewire::test(\App\Filament\Widgets\TopProducts::class, ['pageFilters' => $filters])->assertStatus(200);
        Livewire::test(\App\Filament\Widgets\TopCustomers::class, ['pageFilters' => $filters])->assertStatus(200);
    }

    public function test_dashboard_page_renders_with_header_filter(): void
    {
        $user = User::create([
            'name' => 'Admin Dashboard 6',
            'email' => 'admin_dashboard6@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Filament\Pages\Dashboard::class)
            ->assertStatus(200)
            ->assertSee('Periode aktif')
            ->set('filters.filter_preset', 'today')
            ->assertSee('Periode aktif: Hari Ini');
    }

    public function test_low_stock_products_has_pagination_and_header_action(): void
    {
        $user = User::create([
            'name' => 'Admin Dashboard 7',
            'email' => 'admin_dashboard7@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Filament\Widgets\LowStockProducts::class)
            ->assertStatus(200)
            ->assertTableHeaderActionsExistInOrder(['view_all']);
    }
}
