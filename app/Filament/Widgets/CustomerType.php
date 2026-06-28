<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerType extends BaseWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return request()->routeIs('filament.admin.resources.customers.index');
    }

    protected function getStats(): array
    {
        $memberCount = Customer::whereNotNull('membership_id')->count();
        $nonMemberCount = Customer::whereNull('membership_id')->count();
        $totalCount = $memberCount + $nonMemberCount;

        return [
            Stat::make('Total Customer', $totalCount)
                ->icon('heroicon-o-users')
                ->description('Semua customer terdaftar')
                ->color('primary'),

            Stat::make('Member', $memberCount)
                ->icon('heroicon-o-star')
                ->description('Customer dengan membership')
                ->color('success'),

            Stat::make('Non-Member', $nonMemberCount)
                ->icon('heroicon-o-user')
                ->description('Customer reguler')
                ->color('warning'),
        ];
    }
}
