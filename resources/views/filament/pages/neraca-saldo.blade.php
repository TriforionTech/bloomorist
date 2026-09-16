<x-filament-panels::page>
    {{ $this->filterSchema }}

    @php
        $data = $this->getTrialBalanceData();
        $period = $this->getSelectedPeriod();
    @endphp

    @if($data && $period)
        <div class="mb-4 rounded-lg px-4 py-4 text-sm font-medium {{ $data['is_balanced'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }}">
            @if($data['is_balanced'])
                ✅ Neraca Saldo Seimbang
            @else
                ❌ Neraca Saldo Tidak Seimbang — Selisih: Rp {{ number_format(abs($data['total_debit'] - $data['total_credit']), 0, ',', '.') }}
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-700 dark:bg-gray-800 sm:px-6">
                <h3 class="font-semibold text-gray-900 dark:text-white">Neraca Saldo — {{ $period->label }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[640px] w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <th class="px-4 py-3 text-left">Kode Akun</th>
                            <th class="px-4 py-3 text-left">Nama Akun</th>
                            <th class="px-4 py-3 text-right">Debit</th>
                            <th class="px-4 py-3 text-right">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($data['rows'] as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 font-mono">{{ $row['account']->kode_akun }}</td>
                                <td class="px-4 py-3">{{ $row['account']->nama_akun }}</td>
                                <td class="px-4 py-3 text-right">{{ $row['debit'] ? 'Rp ' . number_format($row['debit'], 0, ',', '.') : '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ $row['credit'] ? 'Rp ' . number_format($row['credit'], 0, ',', '.') : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 bg-gray-50 font-bold dark:border-gray-600 dark:bg-gray-800">
                            <td colspan="2" class="px-4 py-3 text-right">TOTAL</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format($data['total_debit'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format($data['total_credit'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500 dark:border-gray-600">
            Buat periode akuntansi terlebih dahulu untuk melihat Neraca Saldo.
        </div>
    @endif
</x-filament-panels::page>
