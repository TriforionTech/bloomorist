<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Arus Kas ?" {{ $company }}</title>
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

        .section-title.operating { background-color: #2563eb; }
        .section-title.investing { background-color: #059669; }
        .section-title.financing { background-color: #d97706; }

        /* Table */
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table td {
            padding: 8px 16px;
            border-bottom: 1px solid #f0f0f0;
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

        /* Summary */
        .summary-box {
            margin-top: 30px;
            border: 2px solid #e5e7eb;
            padding: 16px 20px;
        }
        
        .summary-row {
            display: table;
            width: 100%;
            padding: 8px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-row.total {
            border-top: 2px solid #1a1a2e;
            border-bottom: none;
            padding-top: 12px;
            font-weight: 700;
            font-size: 12pt;
        }

        .summary-row .label {
            display: table-cell;
            color: #374151;
            font-weight: 600;
        }

        .summary-row .value {
            display: table-cell;
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 600;
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
            <div class="report-title">Laporan Arus Kas (Cash Flow)</div>
            <div class="report-period">
                Periode: {{ $data['period']->label }}
            </div>
            <div class="generated-at">Dicetak: {{ $generated }}</div>
        </div>

        <div class="section">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 60px;">Helper</th>
                        <th style="text-align: left;">Keterangan</th>
                        <th style="text-align: right;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- OPERATING -->
                    <tr>
                        <td></td>
                        <td style="font-weight: 700; padding-top: 15px;">ARUS KAS DARI AKTIVITAS OPERASI</td>
                        <td></td>
                    </tr>
                    @foreach($data['operating'] as $item)
                        <tr>
                            <td class="account-code">{{ $item['key'] }}</td>
                            <td class="account-name">{{ $item['label'] }}</td>
                            <td class="amount">{{ $item['amount'] < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($item['amount']), 0, ',', '.') }}{{ $item['amount'] < 0 ? ')' : '' }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal-row">
                        <td></td>
                        <td>Kas Bersih dari Aktivitas Operasi</td>
                        <td class="amount">{{ $data['operating_total'] < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($data['operating_total']), 0, ',', '.') }}{{ $data['operating_total'] < 0 ? ')' : '' }}</td>
                    </tr>
                    <tr><td colspan="3" style="border:none; padding:10px;"></td></tr>

                    <!-- INVESTING -->
                    <tr>
                        <td></td>
                        <td style="font-weight: 700; padding-top: 15px;">ARUS KAS DARI AKTIVITAS INVESTASI</td>
                        <td></td>
                    </tr>
                    @foreach($data['investing'] as $item)
                        <tr>
                            <td class="account-code">{{ $item['key'] }}</td>
                            <td class="account-name">{{ $item['label'] }}</td>
                            <td class="amount">{{ $item['amount'] < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($item['amount']), 0, ',', '.') }}{{ $item['amount'] < 0 ? ')' : '' }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal-row">
                        <td></td>
                        <td>Kas Bersih dari Aktivitas Investasi</td>
                        <td class="amount">{{ $data['investing_total'] < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($data['investing_total']), 0, ',', '.') }}{{ $data['investing_total'] < 0 ? ')' : '' }}</td>
                    </tr>
                    <tr><td colspan="3" style="border:none; padding:10px;"></td></tr>

                    <!-- FINANCING -->
                    <tr>
                        <td></td>
                        <td style="font-weight: 700; padding-top: 15px;">ARUS KAS DARI AKTIVITAS PENDANAAN</td>
                        <td></td>
                    </tr>
                    @foreach($data['financing'] as $item)
                        <tr>
                            <td class="account-code">{{ $item['key'] }}</td>
                            <td class="account-name">{{ $item['label'] }}</td>
                            <td class="amount">{{ $item['amount'] < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($item['amount']), 0, ',', '.') }}{{ $item['amount'] < 0 ? ')' : '' }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal-row">
                        <td></td>
                        <td>Kas Bersih dari Aktivitas Pendanaan</td>
                        <td class="amount">{{ $data['financing_total'] < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($data['financing_total']), 0, ',', '.') }}{{ $data['financing_total'] < 0 ? ')' : '' }}</td>
                    </tr>
                    <tr><td colspan="3" style="border:none; padding:10px;"></td></tr>

                    <!-- SUMMARY -->
                    <tr class="subtotal-row">
                        <td></td>
                        <td>KENAIKAN (PENURUNAN) KAS & BANK BERSIH</td>
                        <td class="amount">{{ $data['net_change'] < 0 ? '(Rp ' : 'Rp ' }}{{ number_format(abs($data['net_change']), 0, ',', '.') }}{{ $data['net_change'] < 0 ? ')' : '' }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="account-name" style="padding-left: 20px;">Saldo Kas & Bank Awal Periode</td>
                        <td class="amount">Rp {{ number_format($data['opening_cash'], 0, ',', '.') }}</td>
                    </tr>
                    <tr style="font-weight: 700;">
                        <td></td>
                        <td class="account-name" style="padding-left: 20px;">Saldo Kas & Bank Akhir Periode</td>
                        <td class="amount">Rp {{ number_format($data['ending_cash'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="report-footer">
            {{ $company }} &bull; Laporan dihasilkan secara otomatis &bull; {{ $generated }}
        </div>

    </div>
</body>
</html>
