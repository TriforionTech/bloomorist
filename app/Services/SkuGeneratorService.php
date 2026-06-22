<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class SkuGeneratorService
{
    /**
     * Generate SKU otomatis dengan format: [KATEGORI]-[KODE]-[COUNTER]
     * Contoh: FLW-AMR-001, PKG-BOX-001, OTH-RIB-001
     *
     * @param string $kategori  Kategori produk (bunga, packaging, others)
     * @param string $nama      Nama produk
     * @return string           SKU yang di-generate
     */
    public static function generate(string $kategori, string $nama): string
    {
        return DB::transaction(function () use ($kategori, $nama) {
            // Segmen 1: Kategori prefix
            $seg1 = match ($kategori) {
                'bunga'     => 'FLW',
                'packaging' => 'PKG',
                default     => 'OTH',
            };

            // Segmen 2: 3 huruf pertama dari nama (bersihkan non-alpha)
            $cleaned = preg_replace('/[^a-zA-Z]/', '', $nama);
            $seg2 = strtoupper(substr($cleaned, 0, 3));

            // Fallback jika nama terlalu pendek
            if (strlen($seg2) < 3) {
                $seg2 = str_pad($seg2, 3, 'X');
            }

            $prefix = "{$seg1}-{$seg2}-";

            // Segmen 3: Counter dengan lockForUpdate() untuk race condition prevention
            // WAJIB membaca seluruh data (is_active true maupun false)
            $lastProduct = Product::withoutGlobalScopes()
                ->where('sku', 'LIKE', "{$prefix}%")
                ->lockForUpdate()
                ->orderBy('sku', 'desc')
                ->first();

            if ($lastProduct) {
                // Ambil 3 digit terakhir dan increment
                $lastCounter = (int) substr($lastProduct->sku, -3);
                $newCounter = $lastCounter + 1;
            } else {
                $newCounter = 1;
            }

            return $prefix . str_pad($newCounter, 3, '0', STR_PAD_LEFT);
        });
    }
}
