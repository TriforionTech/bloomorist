<?php

namespace App\Filament\Pages;

use App\Models\ChartOfAccount;
use App\Services\AccountingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
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

    protected static ?string $title = 'Ledger';
    protected static ?string $navigationLabel = 'Ledger';
    protected static ?string $pluralLabel = 'Ledgers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
    protected static string|UnitEnum|null $navigationGroup = 'Accounting & Finances';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.buku-besar';

    public ?int $selectedCoaId = null;
    public ?string $startDate = null;
    public ?string $endDate = null;

    /**
     * Only superadmin can access this page.
     */
    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->is_super_admin ?? false;
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

                    DatePicker::make('startDate')
                        ->label('Dari Tanggal')
                        ->native(false)
                        ->live(),

                    DatePicker::make('endDate')
                        ->label('Sampai Tanggal')
                        ->native(false)
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

        $start = $this->startDate ? Carbon::parse($this->startDate) : null;
        $end   = $this->endDate ? Carbon::parse($this->endDate) : null;

        return $service->getLedgerEntries($this->selectedCoaId, $start, $end);
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
