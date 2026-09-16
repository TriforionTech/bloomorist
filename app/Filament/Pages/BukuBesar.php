<?php

namespace App\Filament\Pages;

use App\Models\ChartOfAccount;
use App\Models\AccountingPeriod;
use App\Services\AccountingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use UnitEnum;

class BukuBesar extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $title = 'Buku Besar';
    protected static ?string $navigationLabel = 'Buku Besar';
    protected static ?string $pluralLabel = 'Buku Besar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
    protected static string|UnitEnum|null $navigationGroup = 'Accounting & Finances';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.buku-besar';

    public ?int $selectedCoaId = null;
    public ?int $selectedPeriodId = null;

    /**
     * Only superadmin can access this page.
     */
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
            Section::make('Filter Buku Besar')
                ->schema([
                    Select::make('selectedCoaId')
                        ->label('Pilih Akun')
                        ->options(
                            ChartOfAccount::orderBy('kode_akun')
                                ->get()
                                ->mapWithKeys(fn ($coa) => [
                                    $coa->id => "{$coa->kode_akun} — {$coa->nama_akun}",
                                ])
                        )
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->live(),

                    Select::make('selectedPeriodId')
                        ->label('Periode Akuntansi')
                        ->options(
                            AccountingPeriod::query()
                                ->orderByDesc('start_date')
                                ->pluck('label', 'id')
                        )
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->live(),
                ])
                ->columns(3),
        ]);
    }

    /**
     * Get ledger entries for the selected account.
     */
    public function getLedgerData(): Collection
    {
        if (!$this->selectedCoaId) {
            return collect();
        }

        $service = app(AccountingService::class);

        $period = AccountingPeriod::find($this->selectedPeriodId);
        if (!$period) {
            return collect();
        }

        return $service->getLedgerEntries(
            $this->selectedCoaId,
            Carbon::parse($period->start_date)->startOfDay(),
            Carbon::parse($period->end_date)->endOfDay(),
        );
    }

    public function getSelectedPeriod(): ?AccountingPeriod
    {
        return $this->selectedPeriodId
            ? AccountingPeriod::find($this->selectedPeriodId)
            : null;
    }

    /**
     * Get the selected COA info.
     */
    public function getSelectedCoa(): ?ChartOfAccount
    {
        if (!$this->selectedCoaId) {
            return null;
        }

        return ChartOfAccount::find($this->selectedCoaId);
    }
}
