<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\SkuGeneratorService;

class ProductObserver
{
    /**
     * Handle the Product "creating" event.
     * Auto-generate SKU jika belum diisi.
     */
    public function creating(Product $product): void
    {
        if (empty($product->sku)) {
            $product->sku = SkuGeneratorService::generate(
                $product->kategori ?? 'others',
                $product->nama ?? 'Unknown'
            );
        }
    }
}
