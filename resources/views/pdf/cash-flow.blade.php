<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Arus Kas - {{ $company }}</title>
    @include('pdf.partials.financial-report-style')
</head>
<body>
    @php
        $formatAmount = static fn (float|int $amount): string => $amount < 0 ? '(Rp ' . number_format(abs($amount), 0, ',', '.') . ')' : 'Rp ' . number_format($amount, 0, ',', '.');
        $sections = [
            'operating' => ['title' => 'ARUS KAS DARI AKTIVITAS OPERASI', 'total' => 'Kas Bersih dari Aktivitas Operasi'],
            'investing' => ['title' => 'ARUS KAS DARI AKTIVITAS INVESTASI', 'total' => 'Kas Bersih dari Aktivitas Investasi'],
            'financing' => ['title' => 'ARUS KAS DARI AKTIVITAS PENDANAAN', 'total' => 'Kas Bersih dari Aktivitas Pendanaan'],
        ];
    @endphp
    <div class="report-header">
        <div class="company-name">{{ $company }}</div>
        <div class="report-title">Arus Kas</div>
        <div class="report-meta">Periode: {{ $data['period']->label }}</div>
        <div class="generated-at">Dicetak: {{ $generated }}</div>
    </div>
    <div class="status {{ $data['is_reconciled'] ? 'status-ok' : 'status-error' }}">
        {{ $data['is_reconciled'] ? 'Kas Terekonsiliasi Seimbang' : 'Selisih Kas: Rp ' . number_format(abs($data['difference']), 0, ',', '.') . ' (cek transaksi kas/bank yang tidak terpetakan)' }}
    </div>
    <table class="report-table">
        <thead><tr><th class="helper">Helper</th><th>Keterangan</th><th class="amount">Jumlah</th></tr></thead>
        <tbody>
            @foreach($sections as $key => $section)
                <tr class="section-row"><td colspan="3">{{ $section['title'] }}</td></tr>
                @foreach($data[$key] as $item)
                    <tr><td class="helper">{{ $item['key'] }}</td><td>{{ $item['label'] }}</td><td class="amount {{ $item['amount'] < 0 ? 'negative' : '' }}">{{ $formatAmount($item['amount']) }}</td></tr>
                @endforeach
                <tr class="total-row"><td></td><td>{{ $section['total'] }}</td><td class="amount {{ $data["{$key}_total"] < 0 ? 'negative' : '' }}">{{ $formatAmount($data["{$key}_total"]) }}</td></tr>
                <tr class="spacer"><td colspan="3"></td></tr>
            @endforeach
            <tr class="highlight-row"><td></td><td>KENAIKAN (PENURUNAN) KAS &amp; BANK BERSIH</td><td class="amount {{ $data['net_change'] < 0 ? 'negative' : '' }}">{{ $formatAmount($data['net_change']) }}</td></tr>
            <tr><td></td><td class="detail-label">Saldo Kas &amp; Bank Awal Periode</td><td class="amount">{{ $formatAmount($data['opening_cash']) }}</td></tr>
            <tr class="result-row"><td></td><td>Saldo Kas &amp; Bank Akhir Periode</td><td class="amount">{{ $formatAmount($data['ending_cash']) }}</td></tr>
        </tbody>
    </table>
    <div class="report-footer">{{ $company }} - Laporan dihasilkan secara otomatis - {{ $generated }}</div>
</body>
</html>
