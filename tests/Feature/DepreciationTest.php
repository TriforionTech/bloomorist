<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\GeneralJournal;
use App\Services\DepreciationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepreciationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_posting_is_a_catch_up_entry(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'Agustus 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
        ]);
        $assetAccount = $this->account('1105', 'Peralatan', 'Aset', 'Debit');
        $expenseAccount = $this->account('6105', 'Beban Penyusutan', 'Beban', 'Debit');
        $accumulatedAccount = $this->account('1106', 'Akumulasi Penyusutan', 'Aset', 'Kredit');

        $asset = FixedAsset::create([
            'name' => 'Peralatan Gudang',
            'purchase_date' => '2025-08-15',
            'acquisition_cost' => 4800,
            'useful_life_months' => 12,
            'asset_account_id' => $assetAccount->id,
            'expense_account_id' => $expenseAccount->id,
            'accum_dep_account_id' => $accumulatedAccount->id,
            'is_active' => true,
        ]);

        $journal = app(DepreciationService::class)->postMonthlyDepreciation($period);

        $this->assertSame('DEPRECIATION', $journal->source_type);
        $this->assertEquals(4800, $journal->items()->sum('debit'));
        $this->assertEquals(4800, $journal->items()->sum('kredit'));
        $this->assertEquals(4800, FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->value('accumulated_depreciation'));
    }

    public function test_depreciation_cannot_be_posted_twice_for_same_period(): void
    {
        $period = AccountingPeriod::create([
            'label' => 'Agustus 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
        ]);
        $assetAccount = $this->account('1105', 'Peralatan', 'Aset', 'Debit');
        $expenseAccount = $this->account('6105', 'Beban Penyusutan', 'Beban', 'Debit');
        $accumulatedAccount = $this->account('1106', 'Akumulasi Penyusutan', 'Aset', 'Kredit');
        FixedAsset::create([
            'name' => 'Peralatan',
            'purchase_date' => '2026-08-01',
            'acquisition_cost' => 1200,
            'useful_life_months' => 12,
            'asset_account_id' => $assetAccount->id,
            'expense_account_id' => $expenseAccount->id,
            'accum_dep_account_id' => $accumulatedAccount->id,
            'is_active' => true,
        ]);

        $service = app(DepreciationService::class);
        $service->postMonthlyDepreciation($period);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah diposting');
        $service->postMonthlyDepreciation($period);
    }

    private function account(string $code, string $name, string $category, string $normal): ChartOfAccount
    {
        return ChartOfAccount::create([
            'kode_akun' => $code,
            'nama_akun' => $name,
            'kategori' => $category,
            'saldo_normal' => $normal,
        ]);
    }
}
