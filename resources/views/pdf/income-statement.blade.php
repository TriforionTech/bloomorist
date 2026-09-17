<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Laba Rugi - {{ $company }}</title>
    @include('pdf.partials.financial-report-style')
</head>
<body>
    <div class="report-header">
        <div class="company-name">{{ $company }}</div>
        <div class="report-title">Laporan Laba Rugi (Periodik - Perusahaan Dagang)</div>
        <div class="report-meta">Periode: {{ $periodLabel ?? ($data['start_date']->format('d M Y') . ' - ' . $data['end_date']->format('d M Y')) }}</div>
        <div class="generated-at">Dicetak: {{ $generated }}</div>
    </div>

    <table class="report-table">
        <thead><tr><th>Keterangan</th><th class="amount">Jumlah</th></tr></thead>
        <tbody>
            <tr class="section-row"><td colspan="2">PENDAPATAN</td></tr>
            <tr><td class="detail-label">Penjualan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['4101'] ?? 0, 0, ',', '.') }}</td></tr>
            <tr><td class="detail-label">Retur Penjualan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['4102'] ?? 0, 0, ',', '.') }}</td></tr>
            <tr class="total-row"><td class="detail-label">Penjualan Bersih</td><td class="amount">Rp {{ number_format($data['penjualan_bersih'], 0, ',', '.') }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>

            <tr class="section-row"><td colspan="2">HARGA POKOK PENJUALAN (HPP)</td></tr>
            <tr><td class="detail-label">Pembelian Bunga</td><td class="amount">Rp {{ number_format($data['balances_by_code']['5101'] ?? 0, 0, ',', '.') }}</td></tr>
            <tr><td class="detail-label">Retur Pembelian</td><td class="amount">Rp {{ number_format($data['balances_by_code']['5102'] ?? 0, 0, ',', '.') }}</td></tr>
            <tr class="total-row"><td class="detail-label">Pembelian Bersih</td><td class="amount">Rp {{ number_format($data['pembelian_bersih'], 0, ',', '.') }}</td></tr>
            <tr><td class="detail-label">Beban Angkut Pembelian</td><td class="amount">Rp {{ number_format($data['beban_angkut_pembelian'], 0, ',', '.') }}</td></tr>
            <tr><td class="detail-label">Persediaan Awal</td><td class="amount">Rp {{ number_format($data['persediaan_awal'], 0, ',', '.') }}</td></tr>
            <tr class="total-row"><td class="detail-label">Barang Tersedia Dijual</td><td class="amount">Rp {{ number_format($data['barang_tersedia_dijual'], 0, ',', '.') }}</td></tr>
            <tr><td class="detail-label negative">Persediaan Bunga Akhir (hasil stock opname)</td><td class="amount negative">(Rp {{ number_format($data['persediaan_akhir'] ?? 0, 0, ',', '.') }})</td></tr>
            <tr class="total-row"><td class="detail-label">Harga Pokok Penjualan (HPP)</td><td class="amount">Rp {{ number_format($data['hpp'], 0, ',', '.') }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>

            <tr class="highlight-row"><td>LABA KOTOR</td><td class="amount">Rp {{ number_format($data['laba_kotor'], 0, ',', '.') }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>

            <tr class="section-row"><td colspan="2">BEBAN OPERASIONAL</td></tr>
            @foreach(['6101' => 'Beban Gaji', '6102' => 'Beban Sewa', '6103' => 'Beban Utilitas', '6104' => 'Beban Angkut Penjualan', '6105' => 'Beban Penyusutan', '6106' => 'Beban Kerugian Bunga Rusak/Layu', '6107' => 'Beban Perlengkapan', '6109' => 'Beban Akomodasi', '6108' => 'Beban Lain-Lain', '6110' => 'Beban Pesangon'] as $code => $label)
                <tr><td class="detail-label">{{ $label }}</td><td class="amount">Rp {{ number_format($data['balances_by_code'][$code] ?? 0, 0, ',', '.') }}</td></tr>
            @endforeach
            <tr class="total-row"><td class="detail-label">Total Beban Operasional</td><td class="amount">Rp {{ number_format($data['beban_operasional'], 0, ',', '.') }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>

            <tr class="highlight-row"><td>LABA USAHA</td><td class="amount">Rp {{ number_format($data['laba_usaha'], 0, ',', '.') }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>

            <tr class="section-row"><td colspan="2">PENDAPATAN DI LUAR USAHA</td></tr>
            <tr><td class="detail-label">Pendapatan Bunga Piutang</td><td class="amount">Rp {{ number_format($data['balances_by_code']['4103'] ?? 0, 0, ',', '.') }}</td></tr>
            <tr><td class="detail-label">Pendapatan Lain-Lain</td><td class="amount">Rp {{ number_format($data['balances_by_code']['4104'] ?? 0, 0, ',', '.') }}</td></tr>
            <tr class="total-row"><td class="detail-label">Total Pendapatan Di Luar Usaha</td><td class="amount">Rp {{ number_format($data['pendapatan_luar_usaha'], 0, ',', '.') }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>

            <tr class="result-row"><td>{{ $data['laba_rugi'] >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}</td><td class="amount">{{ $data['laba_rugi'] < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($data['laba_rugi']), 0, ',', '.') }}{{ $data['laba_rugi'] < 0 ? ')' : '' }}</td></tr>
        </tbody>
    </table>

    <div class="report-footer">{{ $company }} - Laporan dihasilkan secara otomatis - {{ $generated }}</div>
</body>
</html>
