<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Stock Movement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th {
            background-color: #f4f4f4;
            padding: 8px;
            text-align: left;
        }
        td {
            padding: 8px;
        }
        .badge {
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            color: #fff;
            text-transform: uppercase;
        }
        .bg-in { background-color: #10b981; }
        .bg-out { background-color: #ef4444; }
        .bg-sale { background-color: #f59e0b; }
        .bg-return { background-color: #3b82f6; }
        .bg-opname { background-color: #6b7280; }
    </style>
</head>
<body>

    <h2>Laporan Pergerakan Stok (Stock Movement)</h2>
    <p>Tanggal Cetak : {{ \Carbon\Carbon::now()->format('d M Y H:i:s') }}</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Nama Barang</th>
                <th>Tipe</th>
                <th>Qty</th>
                <th>Referensi</th>
                <th>Catatan</th>
                <th>Admin</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $index => $record)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $record->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $record->product->nama ?? '-' }}</td>
                    <td>
                        <span class="badge bg-{{ strtolower($record->type) }}">
                            {{ strtoupper($record->type) }}
                        </span>
                    </td>
                    <td>{{ number_format($record->quantity, 0, ',', '.') }}</td>
                    <td>{{ $record->reference_id ?? '-' }}</td>
                    <td>{{ $record->notes ?? '-' }}</td>
                    <td>{{ $record->user->name ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
