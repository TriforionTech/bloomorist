<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Menggunakan sistem grid 12 kolom agar ukuran tiap widget bisa diatur lebih detail.
     */
    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg' => 12,
        ];
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'Pantau ringkasan performa penjualan dan inventaris toko Anda.';
    }
}
