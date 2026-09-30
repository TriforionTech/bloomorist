<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CoaSyncSeeder::class,
            MembershipSeeder::class,
            ReportLineTemplateSeeder::class,
            ProductSeeder::class,
        ]);

        // Pastikan ada 1 periode akuntansi berstatus open untuk bulan berjalan
        $now = now();
        \App\Models\AccountingPeriod::firstOrCreate(
            [
                'start_date' => $now->copy()->startOfMonth()->toDateString(),
                'end_date' => $now->copy()->endOfMonth()->toDateString(),
            ],
            [
                'label' => $now->format('F Y'),
                'status' => 'open',
                'opening_cash_balance' => 0,
            ]
        );
    }
}
