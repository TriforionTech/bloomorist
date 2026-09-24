<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class StockOpnameExcelService
{
    /**
     * Generate an Excel (.xlsx) template pre-filled with all current active products.
     * Excel (.xlsx) opens seamlessly on all spreadsheet apps (Excel, WPS, Google Sheets)
     * and allows the user to save immediately (Ctrl+S) without any annoying CSV warnings.
     */
    public function generateTemplateExcel(): BinaryFileResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('kategori')
            ->orderBy('nama')
            ->get();

        $fileName = 'template_stock_opname_'.date('Y-m-d_His').'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_so_');

        $this->buildXlsxFile($tempPath, $products);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Backward-compatible alias for template generation.
     */
    public function generateTemplateXlsxStream(): BinaryFileResponse
    {
        return $this->generateTemplateExcel();
    }

    /**
     * Parse, validate, and import physical stock count from an Excel (.xlsx) file.
     *
     * @param  array{reference_number: string, date: string, notes?: ?string, post_immediately?: bool}  $meta
     * @return array{stock_opname: StockOpname, total_items: int, posted: bool}
     */
    public function importExcel(string $filePath, array $meta): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('File Excel tidak ditemukan atau tidak dapat dibaca.');
        }

        $rows = $this->readXlsxRows($filePath);

        return $this->processParsedRows($rows, $meta);
    }

    /**
     * Backward-compatible alias for importExcel.
     */
    public function importFile(string $filePath, array $meta): array
    {
        // If file is CSV, handle via CSV fallback, otherwise Excel
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            $rows = $this->readCsvRows($filePath);

            return $this->processParsedRows($rows, $meta);
        }

        return $this->importExcel($filePath, $meta);
    }

    /**
     * Backward-compatible alias.
     */
    public function importCsv(string $filePath, array $meta): array
    {
        return $this->importFile($filePath, $meta);
    }

    /**
     * Construct an OpenXML .xlsx spreadsheet zip archive natively.
     */
    protected function buildXlsxFile(string $outputPath, $products): void
    {
        $zip = new ZipArchive;
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat file template Excel (.xlsx).');
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Stock Opname" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><name val="Calibri"/><sz val="11"/></font>
    <font><b/><name val="Calibri"/><sz val="11"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
  </cellXfs>
</styleSheet>';

        $headers = [
            'A1' => 'product_id',
            'B1' => 'sku',
            'C1' => 'nama_produk',
            'D1' => 'kategori',
            'E1' => 'stok_sistem',
            'F1' => 'harga_beli',
            'G1' => 'stok_fisik',
            'H1' => 'catatan',
        ];

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <cols>
    <col min="1" max="1" width="12" customWidth="1"/>
    <col min="2" max="2" width="18" customWidth="1"/>
    <col min="3" max="3" width="34" customWidth="1"/>
    <col min="4" max="4" width="18" customWidth="1"/>
    <col min="5" max="5" width="14" customWidth="1"/>
    <col min="6" max="6" width="16" customWidth="1"/>
    <col min="7" max="7" width="18" customWidth="1"/>
    <col min="8" max="8" width="28" customWidth="1"/>
  </cols>
  <sheetData>
    <row r="1">';

        foreach ($headers as $cellRef => $title) {
            $sheetXml .= '<c r="'.$cellRef.'" t="inlineStr" s="1"><is><t>'.htmlspecialchars($title, ENT_XML1).'</t></is></c>';
        }
        $sheetXml .= '</row>';

        $rowNum = 2;
        foreach ($products as $p) {
            $sheetXml .= '<row r="'.$rowNum.'">';
            $sheetXml .= '<c r="A'.$rowNum.'"><v>'.(int) $p->id.'</v></c>';
            $sheetXml .= '<c r="B'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($p->sku ?? '', ENT_XML1).'</t></is></c>';
            $sheetXml .= '<c r="C'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($p->nama ?? '', ENT_XML1).'</t></is></c>';
            $sheetXml .= '<c r="D'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($p->kategori ?? '', ENT_XML1).'</t></is></c>';
            $sheetXml .= '<c r="E'.$rowNum.'"><v>'.(int) $p->stok.'</v></c>';
            $sheetXml .= '<c r="F'.$rowNum.'"><v>'.(float) $p->harga_beli.'</v></c>';
            $sheetXml .= '<c r="G'.$rowNum.'"></c>'; // Empty for user input
            $sheetXml .= '<c r="H'.$rowNum.'"></c>'; // Empty for optional note
            $sheetXml .= '</row>';
            $rowNum++;
        }

        $sheetXml .= '</sheetData></worksheet>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();
    }

    /**
     * Read and parse rows from an Excel (.xlsx) file.
     *
     * @return array<int, array{row_number: int, product_id: string, sku: string, physical_qty: string, note: ?string}>
     */
    protected function readXlsxRows(string $filePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('File yang diunggah bukan file Excel (.xlsx) yang valid atau file rusak.');
        }

        $sharedStrings = [];
        $xmlStrings = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlStrings !== false) {
            $doc = simplexml_load_string($xmlStrings);
            if ($doc) {
                foreach ($doc->si as $si) {
                    $t = $si->xpath('.//*[local-name()="t"]');
                    $sharedStrings[] = implode('', array_map('strval', $t ?: []));
                }
            }
        }

        $sheetPath = 'xl/worksheets/sheet1.xml';
        if ($zip->locateName($sheetPath) === false) {
            $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($relsXml !== false) {
                $rels = simplexml_load_string($relsXml);
                if ($rels) {
                    foreach ($rels->Relationship as $rel) {
                        $target = (string) $rel['Target'];
                        if (str_contains($target, 'sheet')) {
                            $sheetPath = 'xl/'.ltrim($target, '/');
                            break;
                        }
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('Lembar kerja Excel (worksheet) tidak ditemukan di dalam file.');
        }

        $doc = simplexml_load_string($sheetXml);
        $zip->close();

        if (! $doc || ! isset($doc->sheetData)) {
            throw new RuntimeException('Lembar kerja Excel kosong atau tidak valid.');
        }

        $rawRows = [];
        foreach ($doc->sheetData->row as $row) {
            $rowNum = (int) $row['r'];
            $rowData = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
                $col = $m[1] ?? 'A';
                $t = (string) ($cell['t'] ?? '');
                $raw = (string) ($cell->v ?? '');

                if ($t === 's') {
                    $val = $sharedStrings[(int) $raw] ?? '';
                } elseif ($t === 'inlineStr') {
                    $val = (string) ($cell->is->t ?? '');
                } elseif ($t === 'str') {
                    $val = $raw;
                } else {
                    $val = $raw;
                }
                $rowData[$col] = $val;
            }
            $rawRows[$rowNum] = $rowData;
        }

        ksort($rawRows);
        if (empty($rawRows)) {
            throw new RuntimeException('File Excel tidak memiliki baris data.');
        }

        $headerRow = array_shift($rawRows);
        $headerMap = [];
        foreach ($headerRow as $col => $name) {
            $normalized = strtolower(trim((string) $name));
            $headerMap[$normalized] = $col;
        }

        $hasProductId = isset($headerMap['product_id']) || isset($headerMap['id']);
        $hasSku = isset($headerMap['sku']);
        $hasPhysicalQty = isset($headerMap['stok_fisik']) || isset($headerMap['physical_qty']);

        if (! $hasProductId || ! $hasSku || ! $hasPhysicalQty) {
            throw new RuntimeException("Format kolom Excel tidak valid. Wajib memiliki kolom 'product_id', 'sku', dan 'stok_fisik'.");
        }

        $idCol = $headerMap['product_id'] ?? $headerMap['id'];
        $skuCol = $headerMap['sku'];
        $physicalCol = $headerMap['stok_fisik'] ?? $headerMap['physical_qty'];
        $notesCol = $headerMap['catatan'] ?? ($headerMap['notes'] ?? null);

        $parsed = [];
        foreach ($rawRows as $rowNum => $row) {
            $productIdRaw = trim((string) ($row[$idCol] ?? ''));
            $skuRaw = trim((string) ($row[$skuCol] ?? ''));
            $physicalQtyRaw = isset($row[$physicalCol]) ? trim((string) $row[$physicalCol]) : '';
            $noteRaw = $notesCol !== null ? trim((string) ($row[$notesCol] ?? '')) : null;

            if ($productIdRaw === '' && $skuRaw === '') {
                continue;
            }

            $parsed[] = [
                'row_number' => $rowNum,
                'product_id' => $productIdRaw,
                'sku' => $skuRaw,
                'physical_qty' => $physicalQtyRaw,
                'note' => $noteRaw,
            ];
        }

        return $parsed;
    }

    /**
     * Read and parse rows from a CSV fallback file.
     *
     * @return array<int, array{row_number: int, product_id: string, sku: string, physical_qty: string, note: ?string}>
     */
    protected function readCsvRows(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            throw new RuntimeException('File kosong.');
        }

        $firstLine = $lines[0];
        $delimiter = ',';
        if (preg_match('/^sep=(.)/i', $firstLine, $sepMatch)) {
            $delimiter = $sepMatch[1];
            array_shift($lines);
            if (empty($lines)) {
                throw new RuntimeException('File kosong.');
            }
        } else {
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        }

        $headerRow = str_getcsv(array_shift($lines), $delimiter);
        $headerMap = [];
        foreach ($headerRow as $colIndex => $colName) {
            $normalized = strtolower(trim((string) $colName));
            $headerMap[$normalized] = $colIndex;
        }

        $hasProductId = isset($headerMap['product_id']) || isset($headerMap['id']);
        $hasSku = isset($headerMap['sku']);
        $hasPhysicalQty = isset($headerMap['stok_fisik']) || isset($headerMap['physical_qty']);

        if (! $hasProductId || ! $hasSku || ! $hasPhysicalQty) {
            throw new RuntimeException("Format kolom tidak valid. Wajib memiliki kolom 'product_id', 'sku', dan 'stok_fisik'.");
        }

        $idCol = $headerMap['product_id'] ?? $headerMap['id'];
        $skuCol = $headerMap['sku'];
        $physicalCol = $headerMap['stok_fisik'] ?? $headerMap['physical_qty'];
        $notesCol = $headerMap['catatan'] ?? ($headerMap['notes'] ?? null);

        $parsed = [];
        foreach ($lines as $index => $line) {
            $rowNumber = $index + 2;
            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }

            $cells = str_getcsv($trimmedLine, $delimiter);
            $productIdRaw = trim((string) ($cells[$idCol] ?? ''));
            $skuRaw = trim((string) ($cells[$skuCol] ?? ''));
            $physicalQtyRaw = isset($cells[$physicalCol]) ? trim((string) $cells[$physicalCol]) : '';
            $noteRaw = $notesCol !== null ? trim((string) ($cells[$notesCol] ?? '')) : null;

            if ($productIdRaw === '' && $skuRaw === '') {
                continue;
            }

            $parsed[] = [
                'row_number' => $rowNumber,
                'product_id' => $productIdRaw,
                'sku' => $skuRaw,
                'physical_qty' => $physicalQtyRaw,
                'note' => $noteRaw,
            ];
        }

        return $parsed;
    }

    /**
     * Process parsed rows through strict integrity validation and database creation.
     */
    protected function processParsedRows(array $rows, array $meta): array
    {
        $date = Carbon::parse($meta['date'] ?? now());

        $period = AccountingPeriod::where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        if ($period && $period->isClosed()) {
            throw new RuntimeException("Periode akuntansi '{$period->label}' untuk tanggal {$date->format('d/m/Y')} sudah ditutup (Closed). Tidak dapat mencatat Stock Opname.");
        }

        $validItems = [];
        $seenProductIds = [];

        foreach ($rows as $item) {
            $rowNumber = $item['row_number'];
            $productIdRaw = $item['product_id'];
            $skuRaw = $item['sku'];
            $physicalQtyRaw = $item['physical_qty'];
            $noteRaw = $item['note'];

            // Skip products where physical count is left blank
            if ($physicalQtyRaw === '') {
                continue;
            }

            // Validate non-negative integer numeric
            if (! is_numeric($physicalQtyRaw) || (float) $physicalQtyRaw < 0 || floor((float) $physicalQtyRaw) != (float) $physicalQtyRaw) {
                throw new RuntimeException("Baris {$rowNumber}: Stok fisik '{$physicalQtyRaw}' tidak valid. Harus berupa angka bulat >= 0.");
            }

            $physicalQty = (int) $physicalQtyRaw;

            if (! is_numeric($productIdRaw)) {
                throw new RuntimeException("Baris {$rowNumber}: ID Produk '{$productIdRaw}' tidak valid.");
            }

            $productId = (int) $productIdRaw;
            $product = Product::find($productId);

            if (! $product) {
                throw new RuntimeException("Baris {$rowNumber}: Produk dengan ID '{$productId}' tidak ditemukan di sistem.");
            }

            // Strict Integrity Check: Verify that SKU matches DB product
            if (strcasecmp(trim($product->sku), $skuRaw) !== 0) {
                throw new RuntimeException("Baris {$rowNumber}: Ketidakcocokan data terdeteksi! SKU '{$skuRaw}' tidak cocok dengan produk '{$product->nama}' (SKU sistem: {$product->sku}). Mohon gunakan template asli tanpa mengubah ID/SKU.");
            }

            // Duplicate check
            if (isset($seenProductIds[$productId])) {
                throw new RuntimeException("Baris {$rowNumber}: Produk '{$product->nama}' (SKU: {$product->sku}) muncul lebih dari 1 kali di dalam file Excel.");
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
                    'notes' => $item['note'],
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
