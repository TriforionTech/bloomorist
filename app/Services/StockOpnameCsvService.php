<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockOpnameCsvService
{
    /**
     * Stream a dynamically generated CSV template populated with all active products.
     */
    public function generateTemplateCsvStream(): StreamedResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('kategori')
            ->orderBy('nama')
            ->get();

        $fileName = 'template_stock_opname_'.date('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for seamless Microsoft Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'product_id',
                'sku',
                'nama_produk',
                'kategori',
                'stok_sistem',
                'harga_beli',
                'stok_fisik',
                'catatan',
            ]);

            // Populate rows with current active products
            foreach ($products as $product) {
                fputcsv($handle, [
                    $product->id,
                    $product->sku,
                    $product->nama,
                    $product->kategori,
                    $product->stok,
                    $product->harga_beli,
                    '', // Empty for user physical count input
                    '', // Empty for optional note
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Parse, validate, and import physical stock count from a CSV file.
     *
     * @param  array{reference_number: string, date: string, notes?: ?string, post_immediately?: bool}  $meta
     * @return array{stock_opname: StockOpname, total_items: int, posted: bool}
     */
    public function importCsv(string $filePath, array $meta): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('File CSV tidak ditemukan atau tidak dapat dibaca.');
        }

        $date = Carbon::parse($meta['date'] ?? now());

        // Validate Accounting Period
        $period = AccountingPeriod::where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        if ($period && $period->isClosed()) {
            throw new RuntimeException("Periode akuntansi '{$period->label}' untuk tanggal {$date->format('d/m/Y')} sudah ditutup (Closed). Tidak dapat mencatat Stock Opname.");
        }

        $content = file_get_contents($filePath);
        // Strip BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            throw new RuntimeException('File CSV kosong.');
        }

        // Auto-detect delimiter (, or ;)
        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $headerRow = str_getcsv(array_shift($lines), $delimiter);
        $headerMap = [];
        foreach ($headerRow as $colIndex => $colName) {
            $normalized = strtolower(trim((string) $colName));
            $headerMap[$normalized] = $colIndex;
        }

        // Validate required headers
        $hasProductId = isset($headerMap['product_id']) || isset($headerMap['id']);
        $hasSku = isset($headerMap['sku']);
        $hasPhysicalQty = isset($headerMap['stok_fisik']) || isset($headerMap['physical_qty']);

        if (! $hasProductId || ! $hasSku || ! $hasPhysicalQty) {
            throw new RuntimeException("Format kolom CSV tidak valid. Wajib memiliki kolom 'product_id', 'sku', dan 'stok_fisik'.");
        }

        $idCol = $headerMap['product_id'] ?? $headerMap['id'];
        $skuCol = $headerMap['sku'];
        $physicalCol = $headerMap['stok_fisik'] ?? $headerMap['physical_qty'];
        $notesCol = $headerMap['catatan'] ?? ($headerMap['notes'] ?? null);

        $validItems = [];
        $seenProductIds = [];

        foreach ($lines as $index => $line) {
            $rowNumber = $index + 2; // Line 1 is header
            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }

            $cells = str_getcsv($trimmedLine, $delimiter);
            $productIdRaw = trim((string) ($cells[$idCol] ?? ''));
            $skuRaw = trim((string) ($cells[$skuCol] ?? ''));
            $physicalQtyRaw = trim((string) ($cells[$physicalCol] ?? ''));
            $noteRaw = $notesCol !== null ? trim((string) ($cells[$notesCol] ?? '')) : null;

            // If physical stock is left blank, skip product (allow partial opname)
            if ($physicalQtyRaw === '') {
                continue;
            }

            // Validate numeric physical quantity
            if (! is_numeric($physicalQtyRaw) || (float) $physicalQtyRaw < 0) {
                throw new RuntimeException("Baris {$rowNumber}: Stok fisik '{$physicalQtyRaw}' tidak valid. Harus berupa angka bulat >= 0.");
            }

            $physicalQty = (int) $physicalQtyRaw;

            // Validate Product ID
            if (! is_numeric($productIdRaw)) {
                throw new RuntimeException("Baris {$rowNumber}: ID Produk '{$productIdRaw}' tidak valid.");
            }

            $productId = (int) $productIdRaw;
            $product = Product::find($productId);

            if (! $product) {
                throw new RuntimeException("Baris {$rowNumber}: Produk dengan ID '{$productId}' tidak ditemukan di sistem.");
            }

            // Strict Integrity Check: Verify that SKU matches the Database Product
            if (strcasecmp(trim($product->sku), $skuRaw) !== 0) {
                throw new RuntimeException("Baris {$rowNumber}: Ketidakcocokan data terdeteksi! SKU '{$skuRaw}' tidak cocok dengan produk '{$product->nama}' (SKU sistem: {$product->sku}). Mohon gunakan template asli tanpa mengubah ID/SKU.");
            }

            // Duplicate row check
            if (isset($seenProductIds[$productId])) {
                throw new RuntimeException("Baris {$rowNumber}: Produk '{$product->nama}' (SKU: {$product->sku}) muncul lebih dari 1 kali di dalam file CSV.");
            }

            $seenProductIds[$productId] = true;

            $systemQty = (int) $product->stok;
            $unitCost = (float) $product->harga_beli;
            $variance = $physicalQty - $systemQty;
            $totalValue = $variance * $unitCost;

            $validItems[] = [
                'product_id' => $product->id,
                'system_qty' => $systemQty,
                'physical_qty' => $physicalQty,
                'variance' => $variance,
                'unit_cost' => $unitCost,
                'total_value' => $totalValue,
                'note' => $noteRaw,
            ];
        }

        if (empty($validItems)) {
            throw new RuntimeException("Tidak ada produk dengan kolom 'stok_fisik' yang diisi. Mohon isi angka hasil hitung fisik pada kolom 'stok_fisik'.");
        }

        $postImmediately = ! empty($meta['post_immediately']);

        return DB::transaction(function () use ($meta, $date, $validItems, $postImmediately) {
            $stockOpname = StockOpname::create([
                'reference_number' => $meta['reference_number'],
                'date' => $date->toDateString(),
                'status' => 'draft',
                'notes' => $meta['notes'] ?? null,
            ]);

            foreach ($validItems as $item) {
                StockOpnameItem::create([
                    'stock_opname_id' => $stockOpname->id,
                    'product_id' => $item['product_id'],
                    'system_qty' => $item['system_qty'],
                    'physical_qty' => $item['physical_qty'],
                    'variance' => $item['variance'],
                    'unit_cost' => $item['unit_cost'],
                    'total_value' => $item['total_value'],
                ]);
            }

            if ($postImmediately) {
                app(AccountingService::class)->createStockOpnameJournal($stockOpname);
            }

            return [
                'stock_opname' => $stockOpname->fresh(['items']),
                'total_items' => count($validItems),
                'posted' => $postImmediately,
            ];
        });
    }
}
