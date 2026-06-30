<x-filament-panels::page>

    {{-- Filter Form --}}
    {{ $this->filterSchema }}

    {{-- Ledger Table --}}
    @if($this->selectedCoaId)
        @php
            $entries = $this->getLedgerData();
            $coa = $this->getSelectedCoa();
        @endphp

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Buku Besar: {{ $coa->kode_akun }} — {{ $coa->nama_akun }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Kategori: {{ $coa->kategori }} | Saldo Normal: {{ $coa->saldo_normal }}
                </p>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Tanggal</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">No. Bukti</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Kode Akun</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Keterangan</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Debit</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Kredit</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($entries as $row)
                            <tr class="{{ $row['is_opening'] ? 'bg-amber-50 dark:bg-amber-900/20 font-semibold' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    {{ $row['tanggal'] }}
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    @if($row['is_opening'])
                                        <span class="text-amber-600 dark:text-amber-400">—</span>
                                    @else
                                        <span class="font-medium text-primary-600 dark:text-primary-400">{{ $row['no_bukti'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    {{ $row['kode_coa'] }}
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    @if($row['is_opening'])
                                        <span class="text-amber-600 dark:text-amber-400 font-semibold">{{ $row['keterangan'] }}</span>
                                    @else
                                        {{ $row['keterangan'] }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    @if($row['debit'] > 0)
                                        Rp {{ number_format($row['debit'], 0, ',', '.') }}
                                    @elseif(!$row['is_opening'])
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    @if($row['kredit'] > 0)
                                        Rp {{ number_format($row['kredit'], 0, ',', '.') }}
                                    @elseif(!$row['is_opening'])
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold whitespace-nowrap {{ $row['saldo'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                    Rp {{ number_format(abs($row['saldo']), 0, ',', '.') }}{{ $row['saldo'] < 0 ? ' (-)' : '' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                    Tidak ada transaksi untuk akun ini pada periode yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-12 text-center">
            <x-heroicon-o-calculator class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Pilih Akun</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Pilih akun dari filter di atas untuk melihat buku besar.
            </p>
        </div>
    @endif

</x-filament-panels::page>
