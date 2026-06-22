<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * 2-column grid agar widget Row 3 & 4 bisa side-by-side.
     * Row 1 & 2 pakai columnSpan='full', Row 3 & 4 pakai columnSpan=1.
     */
    public function getColumns(): int | array
    {
        return 2;
    }
}
