<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\JournalItem;
use App\Models\Expense;

class CoaSyncSeeder extends Seeder
{
    public function run(): void
    {
        $coas = [
            ['kode_akun' => '1101', 'nama_akun' => 'Kas', 'kategori' => 'Aktiva Lancar', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1102', 'nama_akun' => 'Bank', 'kategori' => 'Aktiva Lancar', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1103', 'nama_akun' => 'Piutang Dagang', 'kategori' => 'Aktiva Lancar', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1104', 'nama_akun' => 'Persediaan Bunga', 'kategori' => 'Aktiva Lancar', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1105', 'nama_akun' => 'Peralatan', 'kategori' => 'Aktiva Tetap', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1106', 'nama_akun' => 'Akumulasi Penyusutan Peralatan', 'kategori' => 'Aktiva Tetap (Kontra)', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '1107', 'nama_akun' => 'Piutang Ongkir', 'kategori' => 'Aktiva Lancar', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1108', 'nama_akun' => 'Perlengkapan', 'kategori' => 'Aktiva Lancar', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1109', 'nama_akun' => 'Piutang Investasi', 'kategori' => 'Aktiva Lancar', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1110', 'nama_akun' => 'Uang Muka Pembelian Petani', 'kategori' => 'Aktiva Lancar', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1111', 'nama_akun' => 'Gaji Bayar di Muka', 'kategori' => 'Aktiva Lancar', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '1112', 'nama_akun' => 'Uang Muka Pembelian Tanah', 'kategori' => 'Aktiva Tetap', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '2101', 'nama_akun' => 'Hutang Dagang', 'kategori' => 'Kewajiban Lancar', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '2102', 'nama_akun' => 'Uang Muka Penjualan', 'kategori' => 'Kewajiban Lancar', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '3101', 'nama_akun' => 'Modal Pemilik', 'kategori' => 'Modal', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '3102', 'nama_akun' => 'Prive', 'kategori' => 'Modal (Kontra)', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '4101', 'nama_akun' => 'Penjualan', 'kategori' => 'Pendapatan', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '4102', 'nama_akun' => 'Retur Penjualan', 'kategori' => 'Pendapatan (Kontra)', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '4103', 'nama_akun' => 'Pendapatan Bunga', 'kategori' => 'Pendapatan Lain-Lain', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '4104', 'nama_akun' => 'Pendapatan Lain-Lain', 'kategori' => 'Pendapatan Lain-Lain', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '5101', 'nama_akun' => 'Pembelian Bunga', 'kategori' => 'Beban Pokok Penjualan', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '5102', 'nama_akun' => 'Retur Pembelian', 'kategori' => 'Beban Pokok Penjualan (Kontra)', 'saldo_normal' => 'Kredit'],
            ['kode_akun' => '5103', 'nama_akun' => 'Beban Angkut Pembelian', 'kategori' => 'Beban Pokok Penjualan', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '5104', 'nama_akun' => 'HPP', 'kategori' => 'Beban Pokok Penjualan', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6101', 'nama_akun' => 'Beban Gaji', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6102', 'nama_akun' => 'Beban Sewa', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6103', 'nama_akun' => 'Beban Utilitas', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6104', 'nama_akun' => 'Beban Angkut Penjualan', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6105', 'nama_akun' => 'Beban Penyusutan', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6106', 'nama_akun' => 'Beban Kerugian Bunga Rusak/Layu', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6107', 'nama_akun' => 'Beban Perlengkapan', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6108', 'nama_akun' => 'Beban Lain-Lain', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6109', 'nama_akun' => 'Beban Akomodasi', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
            ['kode_akun' => '6110', 'nama_akun' => 'Beban Pesangon', 'kategori' => 'Beban Operasional', 'saldo_normal' => 'Debit'],
        ];

        // Ensure we don't truncate if there are foreign key constraints from active journals,
        // but typically in dev we can disable fk checks.
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('bl_coa_t')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = now();
        foreach ($coas as &$coa) {
            $coa['created_at'] = $now;
            $coa['updated_at'] = $now;
        }

        DB::table('bl_coa_t')->insert($coas);
    }
}
