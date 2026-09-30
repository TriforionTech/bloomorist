<?php

namespace Database\Seeders;

use App\Models\ReportLineTemplate;
use Illuminate\Database\Seeder;

class ReportLineTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['line_key' => 'sales', 'label' => 'Penjualan', 'account_codes' => ['4101'], 'sign' => 1, 'sort_order' => 10],
            ['line_key' => 'sales_returns', 'label' => 'Retur Penjualan', 'account_codes' => ['4102'], 'sign' => -1, 'sort_order' => 20],
            ['line_key' => 'net_sales', 'label' => 'Penjualan Bersih', 'is_subtotal' => true, 'subtotal_formula' => 'sales + sales_returns', 'sort_order' => 30],
            ['line_key' => 'operating_expenses', 'label' => 'Beban Operasional', 'account_codes' => ['6*'], 'sign' => 1, 'sort_order' => 40],
        ];

        foreach ($templates as $template) {
            ReportLineTemplate::updateOrCreate(
                ['report_type' => 'income_statement', 'line_key' => $template['line_key']],
                $template + ['report_type' => 'income_statement', 'is_active' => true],
            );
        }
    }
}
