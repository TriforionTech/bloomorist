<?php

namespace App\Filament\Pages;

use App\Models\AccountingPeriod;
use App\Services\TrialBalanceService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use UnitEnum;

class NeracaSaldo extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $title = 'Neraca Saldo';
    protected static ?string $navigationLabel = 'Neraca Saldo';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';
    protected static string|UnitEnum|null $navigationGroup = 'Accounting & Finances';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.pages.neraca-saldo';

    public ?int $selectedPeriodId = null;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->is_super_admin ?? false;
    }

    public function mount(): void
    {
        $this->selectedPeriodId = AccountingPeriod::query()
            ->orderByDesc('start_date')
            ->value('id');
    }

    public function filterSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filter Neraca Saldo')
                ->schema([
                    Select::make('selectedPeriodId')
                        ->label('Periode Akuntansi')
                        ->options(
                            AccountingPeriod::query()
                                ->orderByDesc('start_date')
                                ->pluck('label', 'id')
                        )
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->required(),
                ]),
        ]);
    }

    public function getTrialBalanceData(): ?array
    {
        if (!$this->selectedPeriodId) {
            return null;
        }

        return app(TrialBalanceService::class)->getForPeriod($this->selectedPeriodId);
    }

    public function getSelectedPeriod(): ?AccountingPeriod
    {
        return $this->selectedPeriodId
            ? AccountingPeriod::find($this->selectedPeriodId)
            : null;
    }
}
