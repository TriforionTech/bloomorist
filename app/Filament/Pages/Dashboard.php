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
        return 12;
    }
}
