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
                Periode: {{ $data['start_date']->format('d M Y') }} — {{ $data['end_date']->format('d M Y') }}
            </div>
            <div class="generated-at">Dicetak: {{ $generated }}</div>
        </div>

        <!-- PENDAPATAN -->
        <div class="section">
            <div class="section-title pendapatan">Pendapatan</div>
            <table class="report-table">
                @foreach($data['pendapatan'] as $item)
                    <tr>
                        <td class="account-code">{{ $item['kode_akun'] }}</td>
                        <td class="account-name">{{ $item['nama_akun'] }}</td>
                        <td class="amount">Rp {{ number_format($item['saldo'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row pendapatan">
                    <td colspan="2">Total Pendapatan</td>
                    <td class="amount">Rp {{ number_format($data['total_pendapatan'], 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <!-- BEBAN -->
        <div class="section">
            <div class="section-title beban">Beban</div>
            <table class="report-table">
                @foreach($data['beban'] as $item)
                    <tr>
                        <td class="account-code">{{ $item['kode_akun'] }}</td>
                        <td class="account-name">{{ $item['nama_akun'] }}</td>
                        <td class="amount">Rp {{ number_format($item['saldo'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row beban">
                    <td colspan="2">Total Beban</td>
                    <td class="amount">Rp {{ number_format($data['total_beban'], 0, ',', '.') }}</td>
                </tr>
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
