<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Laba Rugi — {{ $company }}</title>
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

        .section-title.pendapatan { background-color: #059669; }
        .section-title.beban { background-color: #dc2626; }

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

        /* Subtotal Row */
        .subtotal-row td {
            border-top: 2px solid #1a1a2e;
            border-bottom: 2px solid #1a1a2e;
            font-weight: 700;
            padding: 10px 16px;
        }

        .subtotal-row.pendapatan td { color: #059669; }
        .subtotal-row.beban td { color: #dc2626; }

        /* Net Result */
        .net-result {
            margin-top: 30px;
            padding: 16px 20px;
            border: 3px solid #1a1a2e;
            display: table;
            width: 100%;
        }

        .net-result .label {
            display: table-cell;
            font-size: 12pt;
            font-weight: 700;
            color: #1a1a2e;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .net-result .value {
            display: table-cell;
            text-align: right;
            font-size: 14pt;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .net-result .value.profit { color: #059669; }
        .net-result .value.loss { color: #dc2626; }

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
            <div class="report-title">Laporan Laba Rugi (Income Statement)</div>
            <div class="report-period">
                Periode: {{ $periodLabel ?? ($data['start_date']->format('d M Y') . ' — ' . $data['end_date']->format('d M Y')) }}
            </div>
            <div class="generated-at">Dicetak: {{ $generated }}</div>
        </div>

        <div class="section">
            <table class="report-table">
                <!-- PENDAPATAN -->
                <tr class="subtotal-row pendapatan"><td colspan="2">PENDAPATAN</td><td class="amount"></td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Penjualan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['4101'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Retur Penjualan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['4102'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Penjualan Bersih</td><td class="amount">Rp {{ number_format($data['penjualan_bersih'], 0, ',', '.') }}</td></tr>
                <tr><td colspan="3" style="border:none; padding:10px;"></td></tr>

                <!-- HPP -->
                <tr class="subtotal-row"><td colspan="2">HARGA POKOK PENJUALAN (HPP)</td><td class="amount"></td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Pembelian Bunga</td><td class="amount">Rp {{ number_format($data['balances_by_code']['5101'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Retur Pembelian</td><td class="amount">Rp {{ number_format($data['balances_by_code']['5102'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Pembelian Bersih</td><td class="amount">Rp {{ number_format($data['pembelian_bersih'], 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Angkut Pembelian</td><td class="amount">Rp {{ number_format($data['balances_by_code']['5103'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Persediaan Awal</td><td class="amount">Rp {{ number_format($data['persediaan_awal'], 0, ',', '.') }}</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Barang Tersedia Dijual</td><td class="amount">Rp {{ number_format($data['barang_tersedia_dijual'], 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name" style="color: #dc2626;">Persediaan Bunga Akhir (hasil stock opname)</td><td class="amount" style="color: #dc2626;">(Rp {{ number_format($data['persediaan_akhir'] ?? 0, 0, ',', '.') }})</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Harga Pokok Penjualan (HPP)</td><td class="amount">Rp {{ number_format($data['hpp'], 0, ',', '.') }}</td></tr>
                <tr><td colspan="3" style="border:none; padding:10px;"></td></tr>

                <!-- LABA KOTOR -->
                <tr class="subtotal-row" style="background-color: #f0fdf4;"><td colspan="2" style="color: #166534;">LABA KOTOR</td><td class="amount" style="color: #166534;">Rp {{ number_format($data['laba_kotor'], 0, ',', '.') }}</td></tr>
                <tr><td colspan="3" style="border:none; padding:10px;"></td></tr>

                <!-- BEBAN OPERASIONAL -->
                <tr class="subtotal-row beban"><td colspan="2">BEBAN OPERASIONAL</td><td class="amount"></td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Gaji</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6101'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Sewa</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6102'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Utilitas</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6103'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Angkut Penjualan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6104'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Penyusutan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6105'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Kerugian Bunga Rusak/Layu</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6106'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Perlengkapan</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6107'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Akomodasi</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6109'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Lain-Lain</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6108'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Beban Pesangon</td><td class="amount">Rp {{ number_format($data['balances_by_code']['6110'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Total Beban Operasional</td><td class="amount">Rp {{ number_format($data['beban_operasional'], 0, ',', '.') }}</td></tr>
                <tr><td colspan="3" style="border:none; padding:10px;"></td></tr>

                <!-- LABA USAHA -->
                <tr class="subtotal-row" style="background-color: #f0fdf4;"><td colspan="2" style="color: #166534;">LABA USAHA</td><td class="amount" style="color: #166534;">Rp {{ number_format($data['laba_usaha'], 0, ',', '.') }}</td></tr>
                <tr><td colspan="3" style="border:none; padding:10px;"></td></tr>

                <!-- PENDAPATAN DI LUAR USAHA -->
                <tr class="subtotal-row"><td colspan="2">PENDAPATAN DI LUAR USAHA</td><td class="amount"></td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Pendapatan Bunga Piutang</td><td class="amount">Rp {{ number_format($data['balances_by_code']['4103'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Pendapatan Lain-Lain</td><td class="amount">Rp {{ number_format($data['balances_by_code']['4104'] ?? 0, 0, ',', '.') }}</td></tr>
                <tr style="font-weight: 700;"><td class="account-code" style="padding-left: 20px;"></td><td class="account-name">Total Pendapatan Di Luar Usaha</td><td class="amount">Rp {{ number_format($data['pendapatan_luar_usaha'], 0, ',', '.') }}</td></tr>
            </table>
        </div>

        <!-- NET RESULT -->
        <div class="net-result">
            <span class="label">{{ $data['laba_rugi'] >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}</span>
            <span class="value {{ $data['laba_rugi'] >= 0 ? 'profit' : 'loss' }}">
                Rp {{ number_format(abs($data['laba_rugi']), 0, ',', '.') }}
            </span>
        </div>

        <!-- Footer -->
        <div class="report-footer">
            {{ $company }} &bull; Laporan dihasilkan secara otomatis &bull; {{ $generated }}
        </div>

    </div>
</body>
</html>
