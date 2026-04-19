<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'bloomorist@gmail.com'],
            [
                'name' => 'Bloomorist Admin',
                'password' => '123',
                'is_super_admin' => 1,
            ]
        );
    }
}
