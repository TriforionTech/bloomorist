<x-filament-panels::page>
    {{ $this->filterSchema }}

    @php
        $data = $this->getTrialBalanceData();
        $period = $this->getSelectedPeriod();
    @endphp

    @if($data && $period)
        <div class="mb-4">
            @if($data['is_balanced'])
                <x-filament::badge color="success" size="lg">✓ Neraca Saldo Seimbang</x-filament::badge>
            @else
                <x-filament::badge color="danger" size="lg">
                    ✗ Tidak Seimbang: Selisih Rp {{ number_format(abs($data['total_debit'] - $data['total_credit']), 0, ',', '.') }}
                </x-filament::badge>
            @endif
        </div>

        <x-filament::section
            heading="Neraca Saldo"
            description="Periode: {{ $period->label }} ({{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }})"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left font-semibold text-gray-900 dark:border-gray-700 dark:text-white">
                            <th class="py-3 pr-4">Kode Akun</th>
                            <th class="py-3 px-4">Nama Akun</th>
                            <th class="py-3 pl-4 text-right">Debit</th>
                            <th class="py-3 pl-4 text-right">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($data['rows'] as $row)
                            <tr>
                                <td class="py-3 pr-4 font-mono text-xs text-gray-500">{{ $row['account']->kode_akun }}</td>
                                <td class="py-3 px-4">{{ $row['account']->nama_akun }}</td>
                                <td class="py-3 pl-4 text-right font-medium whitespace-nowrap">{{ $row['debit'] ? 'Rp ' . number_format($row['debit'], 0, ',', '.') : '-' }}</td>
                                <td class="py-3 pl-4 text-right font-medium whitespace-nowrap">{{ $row['credit'] ? 'Rp ' . number_format($row['credit'], 0, ',', '.') : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 dark:border-gray-600">
                        <tr class="font-bold">
                            <td colspan="2" class="py-4 pr-4 text-right text-gray-900 dark:text-white">TOTAL</td>
                            <td class="py-4 pl-4 text-right text-gray-900 dark:text-white whitespace-nowrap">Rp {{ number_format($data['total_debit'], 0, ',', '.') }}</td>
                            <td class="py-4 pl-4 text-right text-gray-900 dark:text-white whitespace-nowrap">Rp {{ number_format($data['total_credit'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500 dark:border-gray-600">
            Pilih atau buat periode akuntansi terlebih dahulu untuk melihat Neraca Saldo.
        </div>
    @endif
</x-filament-panels::page>
