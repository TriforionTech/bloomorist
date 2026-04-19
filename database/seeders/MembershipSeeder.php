<?php

namespace Database\Seeders;

use App\Models\Membership;
use Illuminate\Database\Seeder;

class MembershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Membership::insert([
            [
                'nama' => 'Silver',
                'besaran_diskon_persen' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Gold',
                'besaran_diskon_persen' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Platinum',
                'besaran_diskon_persen' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
