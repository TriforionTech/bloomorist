<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ProductExcelService
{
    /**
     * Ekspor seluruh produk atau hasil query ke file Excel (.xlsx) tanpa kolom stok.
     */
    public function exportProductsExcel(?Builder $query = null): BinaryFileResponse
    {
        $products = ($query ?? Product::query())
            ->orderBy('kategori')
            ->orderBy('nama')
            ->get();

        $fileName = 'data_produk_'.date('Y-m-d_His').'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_prod_');

        $this->buildXlsxFile($tempPath, $products, false);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Unduh template kosong Excel (.xlsx) untuk import/input produk baru atau update massal.
     */
    public function generateTemplateExcel(): BinaryFileResponse
    {
        $fileName = 'template_import_produk_'.date('Y-m-d_His').'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_prod_tpl_');

        $this->buildXlsxFile($tempPath, collect(), true);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Import dan validasi data produk dari file Excel (.xlsx) atau CSV.
     *
     * @return array{total_rows: int, created_count: int, updated_count: int, errors: array<string>}
     */
    public function importExcel(string $filePath): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('File Excel tidak ditemukan atau tidak dapat dibaca.');
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            $rows = $this->readCsvRows($filePath);
        } else {
            $rows = $this->readXlsxRows($filePath);
        }

        return $this->processRows($rows);
    }

    /**
     * Bangun file OpenXML .xlsx natively dengan ZipArchive.
     */
    protected function buildXlsxFile(string $outputPath, $products, bool $isTemplate = false): void
    {
        $zip = new ZipArchive;
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat file Excel (.xlsx).');
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
    <sheet name="Produk" sheetId="1" r:id="rId1"/>
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
            'A1' => 'sku',
            'B1' => 'nama',
            'C1' => 'kategori',
            'D1' => 'harga_beli',
            'E1' => 'harga_jual',
            'F1' => 'harga_vendor',
            'G1' => 'harga_dekor',
            'H1' => 'status',
        ];

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <cols>
    <col min="1" max="1" width="18" customWidth="1"/>
    <col min="2" max="2" width="36" customWidth="1"/>
    <col min="3" max="3" width="16" customWidth="1"/>
    <col min="4" max="4" width="18" customWidth="1"/>
    <col min="5" max="5" width="20" customWidth="1"/>
    <col min="6" max="6" width="18" customWidth="1"/>
    <col min="7" max="7" width="18" customWidth="1"/>
    <col min="8" max="8" width="14" customWidth="1"/>
  </cols>
  <sheetData>
    <row r="1">';

        foreach ($headers as $cellRef => $title) {
            $sheetXml .= '<c r="'.$cellRef.'" t="inlineStr" s="1"><is><t>'.htmlspecialchars($title, ENT_XML1).'</t></is></c>';
        }
        $sheetXml .= '</row>';

        $rowNum = 2;

        if ($isTemplate && $products->isEmpty()) {
            // Contoh baris sampel untuk panduan pengisian
            $sampleRows = [
                [
                    'sku' => 'FLW-ROS-RED',
                    'nama' => 'Mawar Merah Semi Holland',
                    'kategori' => 'bunga',
                    'harga_beli' => 15000,
                    'harga_jual' => 25000,
                    'harga_vendor' => 22000,
                    'harga_dekor' => 20000,
                    'status' => 'aktif',
                ],
                [
                    'sku' => 'PKG-WRAP-KRAFT',
                    'nama' => 'Kertas Wrapping Kraft Brown',
                    'kategori' => 'packaging',
                    'harga_beli' => 3000,
                    'harga_jual' => 7000,
                    'harga_vendor' => 6000,
                    'harga_dekor' => 5500,
                    'status' => 'aktif',
                ],
                [
                    'sku' => 'OTH-CARD-CUSTOM',
                    'nama' => 'Greeting Card Custom Bloomorist',
                    'kategori' => 'others',
                    'harga_beli' => 2000,
                    'harga_jual' => 5000,
                    'harga_vendor' => 4000,
                    'harga_dekor' => 3500,
                    'status' => 'aktif',
                ],
            ];

            foreach ($sampleRows as $item) {
                $sheetXml .= '<row r="'.$rowNum.'">';
                $sheetXml .= '<c r="A'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($item['sku'], ENT_XML1).'</t></is></c>';
                $sheetXml .= '<c r="B'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($item['nama'], ENT_XML1).'</t></is></c>';
                $sheetXml .= '<c r="C'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($item['kategori'], ENT_XML1).'</t></is></c>';
                $sheetXml .= '<c r="D'.$rowNum.'"><v>'.(float) $item['harga_beli'].'</v></c>';
                $sheetXml .= '<c r="E'.$rowNum.'"><v>'.(float) $item['harga_jual'].'</v></c>';
                $sheetXml .= '<c r="F'.$rowNum.'"><v>'.(float) $item['harga_vendor'].'</v></c>';
                $sheetXml .= '<c r="G'.$rowNum.'"><v>'.(float) $item['harga_dekor'].'</v></c>';
                $sheetXml .= '<c r="H'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($item['status'], ENT_XML1).'</t></is></c>';
                $sheetXml .= '</row>';
                $rowNum++;
            }
        } else {
            foreach ($products as $p) {
                $statusText = $p->is_active ? 'aktif' : 'nonaktif';
                $sheetXml .= '<row r="'.$rowNum.'">';
                $sheetXml .= '<c r="A'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($p->sku ?? '', ENT_XML1).'</t></is></c>';
                $sheetXml .= '<c r="B'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($p->nama ?? '', ENT_XML1).'</t></is></c>';
                $sheetXml .= '<c r="C'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($p->kategori ?? '', ENT_XML1).'</t></is></c>';
                $sheetXml .= '<c r="D'.$rowNum.'"><v>'.(float) $p->harga_beli.'</v></c>';
                $sheetXml .= '<c r="E'.$rowNum.'"><v>'.(float) $p->harga_jual.'</v></c>';
                $sheetXml .= '<c r="F'.$rowNum.'"><v>'.(float) $p->harga_vendor.'</v></c>';
                $sheetXml .= '<c r="G'.$rowNum.'"><v>'.(float) $p->harga_dekor.'</v></c>';
                $sheetXml .= '<c r="H'.$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($statusText, ENT_XML1).'</t></is></c>';
                $sheetXml .= '</row>';
                $rowNum++;
            }
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
     * Baca dan parse baris-baris dari file Excel (.xlsx).
     *
     * @return array<int, array<string, string>>
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

        return $this->mapRowsWithHeader($rawRows);
    }

    /**
     * Baca dan parse baris dari file CSV sebagai opsi cadangan.
     *
     * @return array<int, array<string, string>>
     */
    protected function readCsvRows(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException('Gagal membaca file CSV.');
        }

        // Hapus BOM UTF-8 jika ada
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        if (empty($lines)) {
            throw new RuntimeException('File CSV kosong.');
        }

        // Deteksi delimiter (; atau , atau tab)
        $firstLine = $lines[0];
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $rawRows = [];
        $rowNum = 1;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, $delimiter);
            $rowData = [];
            foreach ($cols as $idx => $val) {
                $colLetter = chr(65 + $idx);
                $rowData[$colLetter] = trim($val);
            }
            $rawRows[$rowNum] = $rowData;
            $rowNum++;
        }

        return $this->mapRowsWithHeader($rawRows);
    }

    /**
     * Petakan kolom data berdasarkan baris header.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mapRowsWithHeader(array $rawRows): array
    {
        $headerRow = array_shift($rawRows);
        $headerMap = [];

        foreach ($headerRow as $col => $name) {
            $normalized = strtolower(trim((string) $name));
            $normalized = str_replace([' ', '-', '/'], '_', $normalized);
            $headerMap[$normalized] = $col;
        }

        // Alias normalisasi header
        $skuCol = $headerMap['sku'] ?? ($headerMap['kode_sku'] ?? ($headerMap['kode'] ?? null));
        $namaCol = $headerMap['nama'] ?? ($headerMap['nama_produk'] ?? ($headerMap['product_name'] ?? ($headerMap['name'] ?? null)));
        $kategoriCol = $headerMap['kategori'] ?? ($headerMap['category'] ?? null);
        $hargaBeliCol = $headerMap['harga_beli'] ?? ($headerMap['beli'] ?? ($headerMap['modal'] ?? ($headerMap['cost'] ?? null)));
        $hargaJualCol = $headerMap['harga_jual'] ?? ($headerMap['harga_toko'] ?? ($headerMap['toko'] ?? ($headerMap['jual'] ?? ($headerMap['price'] ?? null))));
        $hargaVendorCol = $headerMap['harga_vendor'] ?? ($headerMap['vendor'] ?? null);
        $hargaDekorCol = $headerMap['harga_dekor'] ?? ($headerMap['dekor'] ?? ($headerMap['decor'] ?? null));
        $statusCol = $headerMap['status'] ?? ($headerMap['is_active'] ?? ($headerMap['aktif'] ?? null));

        if (! $namaCol || ! $kategoriCol || ! $hargaJualCol) {
            throw new RuntimeException("Header kolom tidak lengkap. Wajib memiliki kolom 'nama', 'kategori', dan 'harga_jual'.");
        }

        $mappedRows = [];
        foreach ($rawRows as $rowNum => $row) {
            $sku = $skuCol ? trim((string) ($row[$skuCol] ?? '')) : '';
            $nama = trim((string) ($row[$namaCol] ?? ''));
            $kategori = trim((string) ($row[$kategoriCol] ?? ''));
            $hargaBeli = $hargaBeliCol ? trim((string) ($row[$hargaBeliCol] ?? '')) : '';
            $hargaJual = trim((string) ($row[$hargaJualCol] ?? ''));
            $hargaVendor = $hargaVendorCol ? trim((string) ($row[$hargaVendorCol] ?? '')) : '';
            $hargaDekor = $hargaDekorCol ? trim((string) ($row[$hargaDekorCol] ?? '')) : '';
            $status = $statusCol ? trim((string) ($row[$statusCol] ?? '')) : '';

            // Abaikan jika baris benar-benar kosong
            if ($sku === '' && $nama === '' && $kategori === '' && $hargaJual === '') {
                continue;
            }

            $mappedRows[] = [
                'row_number' => $rowNum,
                'sku' => $sku,
                'nama' => $nama,
                'kategori' => $kategori,
                'harga_beli' => $hargaBeli,
                'harga_jual' => $hargaJual,
                'harga_vendor' => $hargaVendor,
                'harga_dekor' => $hargaDekor,
                'status' => $status,
            ];
        }

        return $mappedRows;
    }

    /**
     * Validasi data baris demi baris dan lakukan Upsert ke database.
     *
     * @return array{total_rows: int, created_count: int, updated_count: int, errors: array<string>}
     */
    protected function processRows(array $rows): array
    {
        if (empty($rows)) {
            throw new RuntimeException('Tidak ada data produk yang ditemukan untuk diimpor.');
        }

        $errors = [];
        $validData = [];
        $validCategories = ['bunga', 'packaging', 'others'];

        foreach ($rows as $item) {
            $rowNum = $item['row_number'];
            $sku = $item['sku'];
            $nama = $item['nama'];
            $kategoriRaw = strtolower($item['kategori']);

            // 1. Validasi Nama
            if ($nama === '') {
                $errors[] = "Baris {$rowNum}: Nama produk tidak boleh kosong.";

                continue;
            }

            // 2. Normalisasi dan Validasi Kategori
            $kategori = match ($kategoriRaw) {
                'bunga', 'flower', 'flowers', 'bunga_segar' => 'bunga',
                'packaging', 'kemasan', 'box', 'wrap', 'wrapping' => 'packaging',
                'others', 'lainnya', 'lain_lain', 'aksesoris', 'other' => 'others',
                default => null,
            };

            if (! $kategori || ! in_array($kategori, $validCategories)) {
                $errors[] = "Baris {$rowNum} ('{$nama}'): Kategori '{$item['kategori']}' tidak valid. Pilihan yang valid: bunga, packaging, others.";

                continue;
            }

            // 3. Validasi & Parsing Harga
            $hargaBeli = $this->parseNumber($item['harga_beli'], 0);
            $hargaJual = $this->parseNumber($item['harga_jual'], null);
            $hargaVendor = $this->parseNumber($item['harga_vendor'], 0);
            $hargaDekor = $this->parseNumber($item['harga_dekor'], 0);

            if ($hargaJual === null || $hargaJual < 0) {
                $errors[] = "Baris {$rowNum} ('{$nama}'): Harga jual (toko) harus berupa angka positif.";

                continue;
            }

            if ($hargaBeli < 0 || $hargaVendor < 0 || $hargaDekor < 0) {
                $errors[] = "Baris {$rowNum} ('{$nama}'): Harga beli, harga vendor, atau harga dekor tidak boleh bernilai negatif.";

                continue;
            }

            // 4. Validasi Status
            $statusRaw = strtolower(trim($item['status']));
            $isActive = true;
            if ($statusRaw !== '') {
                if (in_array($statusRaw, ['nonaktif', 'inactive', '0', 'false', 'tidak', 'off'])) {
                    $isActive = false;
                }
            }

            $validData[] = [
                'row_number' => $rowNum,
                'sku' => $sku !== '' ? $sku : null,
                'nama' => $nama,
                'kategori' => $kategori,
                'harga_beli' => $hargaBeli,
                'harga_jual' => $hargaJual,
                'harga_vendor' => $hargaVendor,
                'harga_dekor' => $hargaDekor,
                'is_active' => $isActive,
            ];
        }

        // Jika ada kesalahan validasi, batalkan import dan laporkan seluruh baris bermasalah
        if (! empty($errors)) {
            $errorSummary = implode("\n", array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $errorSummary .= "\n...dan ".(count($errors) - 10).' baris lainnya memiliki format salah.';
            }

            throw new RuntimeException("Validasi gagal pada file Excel:\n".$errorSummary);
        }

        // Lakukan Upsert di dalam Database Transaction
        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($validData, &$createdCount, &$updatedCount) {
            foreach ($validData as $row) {
                $product = null;

                // Cari berdasarkan SKU jika ada
                if ($row['sku']) {
                    $product = Product::where('sku', $row['sku'])->first();
                }

                // Jika tidak ditemukan via SKU tapi nama persis sama
                if (! $product) {
                    $product = Product::where('nama', $row['nama'])->first();
                }

                if ($product) {
                    // UPDATE PRODUK:
                    // Catatan penting: Nilai kolom 'stok' fisik TIDAK diubah sama sekali!
                    $product->update([
                        'nama' => $row['nama'],
                        'kategori' => $row['kategori'],
                        'harga_beli' => $row['harga_beli'],
                        'harga_jual' => $row['harga_jual'],
                        'harga_vendor' => $row['harga_vendor'],
                        'harga_dekor' => $row['harga_dekor'],
                        'is_active' => $row['is_active'],
                    ]);
                    $updatedCount++;
                } else {
                    // PRODUK BARU:
                    // Set stok awal 0 (penyesuaian stok dilakukan via Stock Opname/Movement)
                    Product::create([
                        'sku' => $row['sku'], // Jika null, ProductObserver otomatis men-generate SKU
                        'nama' => $row['nama'],
                        'kategori' => $row['kategori'],
                        'harga_beli' => $row['harga_beli'],
                        'harga_jual' => $row['harga_jual'],
                        'harga_vendor' => $row['harga_vendor'],
                        'harga_dekor' => $row['harga_dekor'],
                        'stok' => 0,
                        'is_active' => $row['is_active'],
                    ]);
                    $createdCount++;
                }
            }
        });

        return [
            'total_rows' => count($validData),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'errors' => [],
        ];
    }

    /**
     * Konversi format string angka (misal "Rp 50.000", "50,000", "50000.00") menjadi angka float.
     */
    protected function parseNumber(mixed $value, ?float $default = 0): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = trim((string) $value);
        $cleaned = preg_replace('/[^\d.,]/', '', $cleaned);

        if ($cleaned === '') {
            return $default;
        }

        // Tangani pemisah ribuan titik/koma (e.g. 50.000 atau 50,000.00)
        if (str_contains($cleaned, '.') && str_contains($cleaned, ',')) {
            if (strrpos($cleaned, ',') > strrpos($cleaned, '.')) {
                // Format Eropa/Indonesia: 50.000,00
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            } else {
                // Format US: 50,000.00
                $cleaned = str_replace(',', '', $cleaned);
            }
        } elseif (str_contains($cleaned, '.')) {
            // Jika ada lebih dari satu titik, atau titik 3 digit di belakang (ribuan): 50.000
            $parts = explode('.', $cleaned);
            if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                $cleaned = str_replace('.', '', $cleaned);
            }
        } elseif (str_contains($cleaned, ',')) {
            // Jika koma diikuti 3 digit (ribuan): 50,000
            $parts = explode(',', $cleaned);
            if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                $cleaned = str_replace(',', '', $cleaned);
            } else {
                $cleaned = str_replace(',', '.', $cleaned);
            }
        }

        return is_numeric($cleaned) ? (float) $cleaned : $default;
    }
}
