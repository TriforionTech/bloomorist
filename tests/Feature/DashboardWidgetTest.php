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
            ->assertSee('Total Order Hari Ini')
            ->assertSee('Pendapatan Hari Ini')
            ->assertSee('Order Pending')
            ->assertSee('Stok Menipis');
    }
}
