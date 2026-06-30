<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Seed standard Indonesian Chart of Accounts.
     */
    public function run(): void
    {
        $accounts = [
            // Aset
            ['kode_akun' => '1010', 'nama_akun' => 'Kas & Bank',            'kategori' => 'Aset',       'saldo_normal' => 'Debit'],
            ['kode_akun' => '1020', 'nama_akun' => 'Piutang Usaha',         'kategori' => 'Aset',       'saldo_normal' => 'Debit'],
            ['kode_akun' => '1030', 'nama_akun' => 'Persediaan Barang',     'kategori' => 'Aset',       'saldo_normal' => 'Debit'],
            ['kode_akun' => '1040', 'nama_akun' => 'Perlengkapan',          'kategori' => 'Aset',       'saldo_normal' => 'Debit'],

            // Kewajiban
            ['kode_akun' => '2010', 'nama_akun' => 'Hutang Usaha',          'kategori' => 'Kewajiban',  'saldo_normal' => 'Kredit'],
            ['kode_akun' => '2020', 'nama_akun' => 'Hutang Gaji',           'kategori' => 'Kewajiban',  'saldo_normal' => 'Kredit'],

            // Ekuitas
            ['kode_akun' => '3010', 'nama_akun' => 'Modal Pemilik',         'kategori' => 'Ekuitas',    'saldo_normal' => 'Kredit'],
            ['kode_akun' => '3020', 'nama_akun' => 'Prive (Penarikan Dana)', 'kategori' => 'Ekuitas',    'saldo_normal' => 'Debit'],

            // Pendapatan
            ['kode_akun' => '4010', 'nama_akun' => 'Pendapatan Penjualan',  'kategori' => 'Pendapatan', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '4020', 'nama_akun' => 'Pendapatan Jasa',       'kategori' => 'Pendapatan', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '4030', 'nama_akun' => 'Pendapatan Lain-lain',  'kategori' => 'Pendapatan', 'saldo_normal' => 'Kredit'],

            // Beban
            ['kode_akun' => '5010', 'nama_akun' => 'Beban Operasional',     'kategori' => 'Beban',      'saldo_normal' => 'Debit'],
            ['kode_akun' => '5020', 'nama_akun' => 'Beban Gaji',            'kategori' => 'Beban',      'saldo_normal' => 'Debit'],
            ['kode_akun' => '5030', 'nama_akun' => 'Beban Sewa',            'kategori' => 'Beban',      'saldo_normal' => 'Debit'],
            ['kode_akun' => '5040', 'nama_akun' => 'Beban Utilitas',        'kategori' => 'Beban',      'saldo_normal' => 'Debit'],
            ['kode_akun' => '5050', 'nama_akun' => 'Beban Lain-lain',       'kategori' => 'Beban',      'saldo_normal' => 'Debit'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['kode_akun' => $account['kode_akun']],
                $account
            );
        }
    }
}
