<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['nama_barang' => 'Amaranthus', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Ammi Majus', 'harga_beli_barang' => 5000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Andong Merah', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 20000],
            ['nama_barang' => 'Anggrek Bulan', 'harga_beli_barang' => 100000, 'harga_jual_barang' => 150000],
            ['nama_barang' => 'Anthurium Holland', 'harga_beli_barang' => 60000, 'harga_jual_barang' => 85000],
            ['nama_barang' => 'Anthurium Lokal', 'harga_beli_barang' => 7000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Aster', 'harga_beli_barang' => 14000, 'harga_jual_barang' => 20000],
            ['nama_barang' => 'Aster Mini', 'harga_beli_barang' => 20000, 'harga_jual_barang' => 25000],
            ['nama_barang' => 'Asparagus Bintang', 'harga_beli_barang' => 35000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Astromelia', 'harga_beli_barang' => 7000, 'harga_jual_barang' => 25000],
            ['nama_barang' => 'Baby Breath', 'harga_beli_barang' => 30000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Baby Breath Holland', 'harga_beli_barang' => 25000, 'harga_jual_barang' => 40000],
            ['nama_barang' => 'Bulrush', 'harga_beli_barang' => 150000, 'harga_jual_barang' => 200000],
            ['nama_barang' => 'Calla Lilly', 'harga_beli_barang' => 25000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Callistephus', 'harga_beli_barang' => 25000, 'harga_jual_barang' => 35000],
            ['nama_barang' => 'Casablanca Lilly', 'harga_beli_barang' => 175000, 'harga_jual_barang' => 250000],
            ['nama_barang' => 'Carnation Holland', 'harga_beli_barang' => 60000, 'harga_jual_barang' => 75000],
            ['nama_barang' => 'Carnation Lokal', 'harga_beli_barang' => 20000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Caspea Holland', 'harga_beli_barang' => 220000, 'harga_jual_barang' => 350000],
            ['nama_barang' => 'Cemara Balon', 'harga_beli_barang' => 7000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Cemara Wangi', 'harga_beli_barang' => 8000, 'harga_jual_barang' => 12000],
            ['nama_barang' => 'Chamomile', 'harga_beli_barang' => 300000, 'harga_jual_barang' => 350000],
            ['nama_barang' => 'Charming Piano', 'harga_beli_barang' => 30000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Craspedia Billy Balls', 'harga_beli_barang' => 300000, 'harga_jual_barang' => 350000],
            ['nama_barang' => 'Daun Marble', 'harga_beli_barang' => 13000, 'harga_jual_barang' => 35000],
            ['nama_barang' => 'Dolcetto', 'harga_beli_barang' => 30000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Eden', 'harga_beli_barang' => 30000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Ekor Tupai', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 20000],
            ['nama_barang' => 'Eucalyptus Baby Blue', 'harga_beli_barang' => 50000, 'harga_jual_barang' => 150000],
            ['nama_barang' => 'Eucalyptus Parvi', 'harga_beli_barang' => 40000, 'harga_jual_barang' => 75000],
            ['nama_barang' => 'Florida Beauty', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Garbera', 'harga_beli_barang' => 18000, 'harga_jual_barang' => 30000],
            ['nama_barang' => 'Garbera Spider', 'harga_beli_barang' => 35000, 'harga_jual_barang' => 45000],
            ['nama_barang' => 'Gompie', 'harga_beli_barang' => 16000, 'harga_jual_barang' => 35000],
            ['nama_barang' => 'Heliconia', 'harga_beli_barang' => 50000, 'harga_jual_barang' => 75000],
            ['nama_barang' => 'Hortensia Holland', 'harga_beli_barang' => 50000, 'harga_jual_barang' => 85000],
            ['nama_barang' => 'Hortensia Lokal', 'harga_beli_barang' => 25000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Ivy', 'harga_beli_barang' => 7000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Kadaka', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Karpus', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Kemuning', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 20000],
            ['nama_barang' => 'Krisan', 'harga_beli_barang' => 15000, 'harga_jual_barang' => 25000],
            ['nama_barang' => 'Krisan Aiko', 'harga_beli_barang' => 150000, 'harga_jual_barang' => 175000],
            ['nama_barang' => 'Krisan Fiji', 'harga_beli_barang' => 15000, 'harga_jual_barang' => 20000],
            ['nama_barang' => 'Krisdoren', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Leather Leaf', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Lisianthus', 'harga_beli_barang' => 150000, 'harga_jual_barang' => 175000],
            ['nama_barang' => 'Marigold', 'harga_beli_barang' => 3000, 'harga_jual_barang' => 8000],
            ['nama_barang' => 'Matahari Lokal', 'harga_beli_barang' => 15000, 'harga_jual_barang' => 20000],
            ['nama_barang' => 'Matahari Semi Import', 'harga_beli_barang' => 15000, 'harga_jual_barang' => 30000],
            ['nama_barang' => 'Mawar PD', 'harga_beli_barang' => 25000, 'harga_jual_barang' => 60000],
            ['nama_barang' => 'Mawar PJ', 'harga_beli_barang' => 25000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Mawar PJ Pilihan', 'harga_beli_barang' => 50000, 'harga_jual_barang' => 87500],
            ['nama_barang' => 'Memusa', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Monstera', 'harga_beli_barang' => 30000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Pakis Sisir', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Palm', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 25000],
            ['nama_barang' => 'Papyrus', 'harga_beli_barang' => 20000, 'harga_jual_barang' => 30000],
            ['nama_barang' => 'Peony Mum', 'harga_beli_barang' => 175000, 'harga_jual_barang' => 250000],
            ['nama_barang' => 'Philodendron', 'harga_beli_barang' => 3000, 'harga_jual_barang' => 10000],
            ['nama_barang' => 'Pikok', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Pom-Pom', 'harga_beli_barang' => 15000, 'harga_jual_barang' => 25000],
            ['nama_barang' => 'Pussy Willow', 'harga_beli_barang' => 250000, 'harga_jual_barang' => 300000],
            ['nama_barang' => 'Pytos', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Ranunculus', 'harga_beli_barang' => 400000, 'harga_jual_barang' => 600000],
            ['nama_barang' => 'Ruskus', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Sedap Malam', 'harga_beli_barang' => 20000, 'harga_jual_barang' => 35000],
            ['nama_barang' => 'Sikat Botol', 'harga_beli_barang' => 7000, 'harga_jual_barang' => 20000],
            ['nama_barang' => 'Snapdragon', 'harga_beli_barang' => 30000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Song Of India', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 20000],
            ['nama_barang' => 'Thalspi', 'harga_beli_barang' => 250000, 'harga_jual_barang' => 300000],
            ['nama_barang' => 'Toffe', 'harga_beli_barang' => 25000, 'harga_jual_barang' => 50000],
            ['nama_barang' => 'Tricolor', 'harga_beli_barang' => 15000, 'harga_jual_barang' => 25000],
            ['nama_barang' => 'Tulip Holland', 'harga_beli_barang' => 400000, 'harga_jual_barang' => 450000],
            ['nama_barang' => 'Xanado', 'harga_beli_barang' => 10000, 'harga_jual_barang' => 15000],
            ['nama_barang' => 'Zaitun', 'harga_beli_barang' => 15000, 'harga_jual_barang' => 20000],
        ];

        $products = array_map(fn ($p) => array_merge($p, [
            'created_at' => now(),
            'updated_at' => now(),
        ]), $products);

        Product::insert($products);
    }
}
