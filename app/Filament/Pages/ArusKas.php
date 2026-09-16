<?php

namespace App\Filament\Pages;

use App\Models\AccountingPeriod;
use App\Services\CashFlowService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use UnitEnum;

class ArusKas extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $title = 'Cash Flow';
    protected static ?string $navigationLabel = 'Cash Flow';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|UnitEnum|null $navigationGroup = 'Accounting & Finances';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'filament.pages.arus-kas';

    public ?int $selectedPeriodId = null;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->is_super_admin ?? false;
    }

    public function mount(): void
    {
        $this->selectedPeriodId = AccountingPeriod::query()->orderByDesc('start_date')->value('id');
    }

    public function filterSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filter Arus Kas')
                ->schema([
                    Select::make('selectedPeriodId')
                        ->label('Periode Akuntansi')
                        ->options(AccountingPeriod::query()->orderByDesc('start_date')->pluck('label', 'id'))
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->required(),
                ]),
        ]);
    }

    public function getCashFlowData(): ?array
    {
        return $this->selectedPeriodId
            ? app(CashFlowService::class)->getForPeriod($this->selectedPeriodId)
            : null;
    }
}
