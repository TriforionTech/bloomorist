<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neraca Keuangan — {{ $company }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #1a1a2e;
            background: #fff;
            line-height: 1.5;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        /* Header */
        .report-header {
            border-bottom: 3px solid #1a1a2e;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 22pt;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .report-title {
            font-size: 14pt;
            font-weight: 600;
            color: #4a4a68;
            margin-top: 6px;
        }

        .report-period {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 4px;
        }

        .generated-at {
            font-size: 8pt;
            color: #9ca3af;
            float: right;
            margin-top: -40px;
        }

        /* Section */
        .section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 10pt;
            font-weight: 700;
            color: #fff;
            padding: 8px 16px;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .section-title.aset { background-color: #0284c7; }
        .section-title.kewajiban { background-color: #ea580c; }
        .section-title.ekuitas { background-color: #7c3aed; }

        /* Table */
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table td {
            padding: 8px 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .report-table .account-code {
            width: 80px;
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            color: #6b7280;
        }

        .report-table .account-name {
            color: #374151;
        }

        .report-table .amount {
            text-align: right;
            width: 160px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        /* Special row */
        .retained-row td {
            background-color: #fef3c7;
            font-weight: 600;
            font-style: italic;
        }

        /* Subtotal Row */
        .subtotal-row td {
            border-top: 2px solid #1a1a2e;
            border-bottom: 2px solid #1a1a2e;
            font-weight: 700;
            padding: 10px 16px;
        }

        .subtotal-row.aset td { color: #0284c7; }
        .subtotal-row.kewajiban td { color: #ea580c; }
        .subtotal-row.ekuitas td { color: #7c3aed; }

        /* Balance Box */
        .balance-box {
            margin-top: 30px;
            padding: 16px 20px;
            border: 3px solid #1a1a2e;
            display: table;
            width: 100%;
        }

        .balance-box .label {
            display: table-cell;
            font-size: 11pt;
            font-weight: 700;
            color: #1a1a2e;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .balance-box .value {
            display: table-cell;
            text-align: right;
            font-size: 12pt;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .balance-indicator {
            margin-top: 12px;
            padding: 10px 16px;
            text-align: center;
            font-weight: 700;
            font-size: 10pt;
            letter-spacing: 1px;
        }

        .balance-indicator.balanced {
            background-color: #d1fae5;
            color: #065f46;
            border: 2px solid #059669;
        }

        .balance-indicator.unbalanced {
            background-color: #fee2e2;
            color: #991b1b;
            border: 2px solid #dc2626;
        }

        /* Footer */
        .report-footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            font-size: 8pt;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- Header -->
        <div class="report-header">
            <div class="company-name">{{ $company }}</div>
            <div class="report-title">Neraca Keuangan (Balance Sheet)</div>
            <div class="report-period">
                Per Tanggal: {{ $data['as_of']->format('d M Y') }}
            </div>
            <div class="generated-at">Dicetak: {{ $generated }}</div>
        </div>

        <!-- ASET -->
        <div class="section">
            <table class="report-table">
                <tr class="subtotal-row aset"><td colspan="2">AKTIVA</td><td class="amount"></td></tr>
                
                <tr style="background-color: #f9fafb;"><td colspan="2" style="font-weight: 700;">Aktiva Lancar</td><td class="amount"></td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Kas</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1101'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Bank</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1102'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Piutang Dagang</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1103'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Piutang Ongkir</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1107'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Uang Muka Pembelian Petani</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1110'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Piutang Investasi</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1109'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Gaji Bayar di Muka</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1111'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Persediaan Bunga (Akhir)</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1104'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Perlengkapan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1108'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Total Aktiva Lancar</td><td class="amount">Rp {{ number_format(collect($data['aset_groups']['Aktiva Lancar'] ?? [])->sum('saldo'), 0, ',', '.') }}</td></tr>
                <tr><td colspan="3" style="border:none; padding:5px;"></td></tr>

                <tr style="background-color: #f9fafb;"><td colspan="2" style="font-weight: 700;">Aktiva Tetap</td><td class="amount"></td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Peralatan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1105'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name" style="color: #dc2626;">Akumulasi Penyusutan Peralatan</td><td class="amount" style="color: #dc2626;">(Rp {{ number_format(abs($data['balances_by_code']['1106'] ?? 0), 0, ',', '.') }})</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Uang Muka Pembelian Tanah</td><td class="amount">Rp {{ number_format($data['balances_by_code']['1112'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Total Aktiva Tetap</td><td class="amount">
                    @php
                        $totalAktivaTetap = collect($data['aset_groups']['Aktiva Tetap'] ?? [])->sum('saldo') + collect($data['aset_groups']['Aktiva Tetap (Kontra)'] ?? [])->sum('saldo');
                    @endphp
                    {{ $totalAktivaTetap < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($totalAktivaTetap), 0, ',', '.') }}{{ $totalAktivaTetap < 0 ? ')' : '' }}
                </td></tr>
            </table>
        </div>

        <!-- KEWAJIBAN -->
        <div class="section">
            <table class="report-table">
                <tr class="subtotal-row kewajiban"><td colspan="2">KEWAJIBAN</td><td class="amount"></td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Hutang Dagang</td><td class="amount">Rp {{ number_format($data['balances_by_code']['2101'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Uang Muka Penjualan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['2102'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Total Kewajiban</td><td class="amount">Rp {{ number_format($data['total_kewajiban'], 0, ',', '.') }}</td></tr>
            </table>
        </div>

        <!-- EKUITAS -->
        <div class="section">
            <table class="report-table">
                <tr class="subtotal-row ekuitas"><td colspan="2">MODAL</td><td class="amount"></td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Modal Pemilik (Awal)</td><td class="amount">Rp {{ number_format($data['balances_by_code']['3101'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name {{ $data['laba_ditahan'] < 0 ? 'loss' : '' }}">Laba Bersih Periode Berjalan</td><td class="amount {{ $data['laba_ditahan'] < 0 ? 'loss' : '' }}">{{ $data['laba_ditahan'] < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($data['laba_ditahan']), 0, ',', '.') }}{{ $data['laba_ditahan'] < 0 ? ')' : '' }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name" style="color: #dc2626;">Prive</td><td class="amount" style="color: #dc2626;">(Rp {{ number_format(abs($data['balances_by_code']['3102'] ?? 0), 0, ',', '.') }})</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Total Modal (Akhir)</td><td class="amount">Rp {{ number_format($data['total_ekuitas'], 0, ',', '.') }}</td></tr>
            </table>
        </div>

        <!-- BALANCE SUMMARY -->
        <div class="balance-box">
            <span class="label">Total Aset</span>
            <span class="value">Rp {{ number_format($data['total_aset'], 0, ',', '.') }}</span>
        </div>
        <div class="balance-box" style="margin-top: 0; border-top: none;">
            <span class="label">Total Kewajiban + Ekuitas</span>
            <span class="value">Rp {{ number_format($data['total_kewajiban_ekuitas'], 0, ',', '.') }}</span>
        </div>

        <div class="balance-indicator {{ $data['is_balanced'] ? 'balanced' : 'unbalanced' }}">
            {{ $data['is_balanced'] ? '✓ NERACA SEIMBANG (BALANCED)' : '✗ NERACA TIDAK SEIMBANG' }}
        </div>

        <!-- Footer -->
        <div class="report-footer">
            {{ $company }} &bull; Laporan dihasilkan secara otomatis &bull; {{ $generated }}
        </div>

    </div>
</body>
</html>
