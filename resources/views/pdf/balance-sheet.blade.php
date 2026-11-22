<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Neraca Keuangan - {{ $company }}</title>
    @include('pdf.partials.financial-report-style')
</head>
<body>
    @php
        $formatAmount = static fn (float|int $amount): string => $amount < 0 ? '(Rp ' . number_format(abs($amount), 0, ',', '.') . ')' : 'Rp ' . number_format($amount, 0, ',', '.');
        $currentAssets = collect($data['aset_groups']['Aktiva Lancar'] ?? [])->sum('saldo');
        $fixedAssets = collect($data['aset_groups']['Aktiva Tetap'] ?? [])->sum('saldo') + collect($data['aset_groups']['Aktiva Tetap (Kontra)'] ?? [])->sum('saldo');
    @endphp
    <div class="report-header">
        <div class="company-name">{{ $company }}</div>
        <div class="report-title">NERACA (BALANCE SHEET)</div>
        <div class="report-meta">Per tanggal: {{ $data['as_of']->format('d M Y') }}</div>
        <div class="generated-at">Dicetak: {{ $generated }}</div>
    </div>
    <div class="status {{ $data['is_balanced'] ? 'status-ok' : 'status-error' }}">
        {{ $data['is_balanced'] ? 'Neraca Seimbang' : 'Neraca Tidak Seimbang: Rp ' . number_format(abs($data['total_aset'] - $data['total_kewajiban_ekuitas']), 0, ',', '.') }}
    </div>
    <table class="report-table">
        <thead><tr><th>Keterangan</th><th class="amount">Jumlah</th></tr></thead>
        <tbody>
            <tr class="section-row"><td colspan="2">AKTIVA</td></tr>
            <tr class="subsection-row"><td colspan="2">Aktiva Lancar</td></tr>
            @foreach(['1101' => 'Kas', '1102' => 'Bank', '1103' => 'Piutang Dagang', '1107' => 'Piutang Ongkir', '1110' => 'Uang Muka Pembelian Petani', '1109' => 'Piutang Investasi', '1111' => 'Gaji Bayar di Muka', '1104' => 'Persediaan Bunga (Akhir)', '1108' => 'Perlengkapan'] as $code => $label)
                <tr><td class="detail-label">{{ $label }}</td><td class="amount">{{ $formatAmount($data['balances_by_code'][$code] ?? 0) }}</td></tr>
            @endforeach
            <tr class="total-row"><td class="detail-label">Total Aktiva Lancar</td><td class="amount">{{ $formatAmount($currentAssets) }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>
            <tr class="subsection-row"><td colspan="2">Aktiva Tetap</td></tr>
            <tr><td class="detail-label">Peralatan</td><td class="amount">{{ $formatAmount($data['balances_by_code']['1105'] ?? 0) }}</td></tr>
            <tr><td class="detail-label negative">Akumulasi Penyusutan Peralatan</td><td class="amount negative">{{ $formatAmount(-abs($data['balances_by_code']['1106'] ?? 0)) }}</td></tr>
            <tr><td class="detail-label">Uang Muka Pembelian Tanah</td><td class="amount">{{ $formatAmount($data['balances_by_code']['1112'] ?? 0) }}</td></tr>
            <tr class="total-row"><td class="detail-label">Total Aktiva Tetap</td><td class="amount">{{ $formatAmount($fixedAssets) }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>
            <tr class="highlight-row"><td>TOTAL AKTIVA</td><td class="amount">{{ $formatAmount($data['total_aset']) }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>
            <tr class="section-row"><td colspan="2">KEWAJIBAN</td></tr>
            @foreach(['2101' => 'Hutang Dagang', '2102' => 'Uang Muka Penjualan'] as $code => $label)
                <tr><td class="detail-label">{{ $label }}</td><td class="amount">{{ $formatAmount($data['balances_by_code'][$code] ?? 0) }}</td></tr>
            @endforeach
            <tr class="total-row"><td class="detail-label">Total Kewajiban</td><td class="amount">{{ $formatAmount($data['total_kewajiban']) }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>
            <tr class="section-row"><td colspan="2">MODAL</td></tr>
            <tr><td class="detail-label">Modal Pemilik (Awal)</td><td class="amount">{{ $formatAmount($data['balances_by_code']['3101'] ?? 0) }}</td></tr>
            <tr><td class="detail-label {{ $data['laba_ditahan'] < 0 ? 'negative' : '' }}">Laba Bersih Periode Berjalan</td><td class="amount {{ $data['laba_ditahan'] < 0 ? 'negative' : '' }}">{{ $formatAmount($data['laba_ditahan']) }}</td></tr>
            <tr><td class="detail-label negative">Prive</td><td class="amount negative">{{ $formatAmount(-abs($data['balances_by_code']['3102'] ?? 0)) }}</td></tr>
            <tr class="total-row"><td class="detail-label">Total Modal (Akhir)</td><td class="amount">{{ $formatAmount($data['total_ekuitas']) }}</td></tr>
            <tr class="spacer"><td colspan="2"></td></tr>
            <tr class="highlight-row"><td>TOTAL KEWAJIBAN + MODAL</td><td class="amount">{{ $formatAmount($data['total_kewajiban_ekuitas']) }}</td></tr>
        </tbody>
    </table>
    <div class="report-footer">{{ $company }} - Laporan dihasilkan secara otomatis - {{ $generated }}</div>
</body>
</html>
