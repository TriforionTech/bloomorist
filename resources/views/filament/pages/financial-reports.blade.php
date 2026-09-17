<x-filament-panels::page>

    <x-filament::tabs label="Laporan keuangan">
        <x-filament::tabs.item
            :active="$activeTab === 'laba-rugi'"
            wire:click="$set('activeTab', 'laba-rugi')"
            icon="heroicon-o-arrow-trending-up"
        >
            Laba Rugi
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'neraca'"
            wire:click="$set('activeTab', 'neraca')"
            icon="heroicon-o-scale"
        >
            Neraca
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'arus-kas'"
            wire:click="$set('activeTab', 'arus-kas')"
            icon="heroicon-o-banknotes"
        >
            Arus Kas
        </x-filament::tabs.item>
    </x-filament::tabs>

    {{-- ================================================================ --}}
    {{-- TAB 1: LABA RUGI (Income Statement) --}}
    {{-- ================================================================ --}}
    @if($activeTab === 'laba-rugi')
        {{-- Filters --}}
        {{ $this->incomeFilterSchema }}

        @php $income = $this->getIncomeStatementData(); @endphp

        {{-- Export Buttons --}}
        <div class="flex flex-wrap gap-3 mt-4 mb-4">
            <x-filament::button color="danger" icon="heroicon-o-document-arrow-down" wire:click="downloadIncomeStatementPdf" size="sm">
                Download PDF
            </x-filament::button>
            <x-filament::button color="success" icon="heroicon-o-table-cells" wire:click="downloadIncomeStatementCsv" size="sm">
                Download CSV
            </x-filament::button>
        </div>

        <x-filament::section
            heading="Laporan Laba Rugi (Periodik - Perusahaan Dagang)"
            description="Periode: {{ $income['start_date']->format('d M Y') }} — {{ $income['end_date']->format('d M Y') }}"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="border-b">
                        <tr>
                            <th class="py-2 px-4 font-semibold text-gray-900 dark:text-white">Keterangan</th>
                            <th class="py-2 px-4 text-right font-semibold text-gray-900 dark:text-white">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- PENDAPATAN --}}
                        <tr class="bg-gray-50 dark:bg-gray-800"><td colspan="2" class="py-2 px-4 font-bold">PENDAPATAN</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Penjualan</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['4101'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Retur Penjualan</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['4102'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-8">Penjualan Bersih</td><td class="py-2 px-4 text-right">{{ number_format($income['penjualan_bersih'], 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- HARGA POKOK PENJUALAN --}}
                        <tr class="bg-gray-50 dark:bg-gray-800"><td colspan="2" class="py-2 px-4 font-bold">HARGA POKOK PENJUALAN (HPP)</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Pembelian Bunga</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['5101'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Retur Pembelian</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['5102'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-8">Pembelian Bersih</td><td class="py-2 px-4 text-right">{{ number_format($income['pembelian_bersih'], 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Angkut Pembelian</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['5103'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Persediaan Awal</td><td class="py-2 px-4 text-right">{{ number_format($income['persediaan_awal'], 0, ',', '.') }}</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-8">Barang Tersedia Dijual</td><td class="py-2 px-4 text-right">{{ number_format($income['barang_tersedia_dijual'], 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8 text-danger-600">Persediaan Bunga Akhir (hasil stock opname)</td><td class="py-2 px-4 text-right text-danger-600">({{ number_format($income['persediaan_akhir'] ?? 0, 0, ',', '.') }})</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-8">Harga Pokok Penjualan (HPP)</td><td class="py-2 px-4 text-right">{{ number_format($income['hpp'], 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- LABA KOTOR --}}
                        <tr class="bg-primary-50 dark:bg-primary-900/20 text-lg"><td class="py-3 px-4 font-bold text-primary-600 dark:text-primary-400">LABA KOTOR</td><td class="py-3 px-4 text-right font-bold text-primary-600 dark:text-primary-400">{{ number_format($income['laba_kotor'], 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- BEBAN OPERASIONAL --}}
                        <tr class="bg-gray-50 dark:bg-gray-800"><td colspan="2" class="py-2 px-4 font-bold">BEBAN OPERASIONAL</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Gaji</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6101'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Sewa</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6102'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Utilitas</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6103'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Angkut Penjualan</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6104'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Penyusutan</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6105'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Kerugian Bunga Rusak/Layu</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6106'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Perlengkapan</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6107'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Akomodasi</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6109'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Lain-Lain</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6108'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Beban Pesangon</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['6110'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-8">Total Beban Operasional</td><td class="py-2 px-4 text-right">{{ number_format($income['beban_operasional'], 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- LABA USAHA --}}
                        <tr class="bg-primary-50 dark:bg-primary-900/20 text-lg"><td class="py-3 px-4 font-bold text-primary-600 dark:text-primary-400">LABA USAHA</td><td class="py-3 px-4 text-right font-bold text-primary-600 dark:text-primary-400">{{ number_format($income['laba_usaha'], 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- PENDAPATAN DI LUAR USAHA --}}
                        <tr class="bg-gray-50 dark:bg-gray-800"><td colspan="2" class="py-2 px-4 font-bold">PENDAPATAN DI LUAR USAHA</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Pendapatan Bunga Piutang</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['4103'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-8">Pendapatan Lain-Lain</td><td class="py-2 px-4 text-right">{{ number_format($income['balances_by_code']['4104'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-8">Total Pendapatan Di Luar Usaha</td><td class="py-2 px-4 text-right">{{ number_format($income['pendapatan_luar_usaha'], 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- LABA BERSIH --}}
                        <tr class="bg-success-50 dark:bg-success-900/20 text-xl">
                            <td class="py-4 px-4 font-bold text-success-600 dark:text-success-400">{{ $income['laba_rugi'] >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}</td>
                            <td class="py-4 px-4 text-right font-bold text-success-600 dark:text-success-400">{{ number_format($income['laba_rugi'], 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- ================================================================ --}}
    {{-- TAB 2: NERACA (Balance Sheet) --}}
    {{-- ================================================================ --}}
    @if($activeTab === 'neraca')
        {{-- Filters --}}
        {{ $this->balanceFilterSchema }}

        @php $balance = $this->getBalanceSheetData(); @endphp

        {{-- Export Buttons --}}
        <div class="flex flex-wrap gap-3 mt-4 mb-4">
            <x-filament::button color="danger" icon="heroicon-o-document-arrow-down" wire:click="downloadBalanceSheetPdf" size="sm">
                Download PDF
            </x-filament::button>
            <x-filament::button color="success" icon="heroicon-o-table-cells" wire:click="downloadBalanceSheetCsv" size="sm">
                Download CSV
            </x-filament::button>
        </div>

        <x-filament::section
            heading="NERACA (BALANCE SHEET)"
            description="Per tanggal: {{ $balance['as_of']->format('d M Y') }}"
        >
            <div class="mb-4">
                @if($balance['is_balanced'])
                    <x-filament::badge color="success">Neraca Seimbang</x-filament::badge>
                @else
                    <x-filament::badge color="danger">
                        Tidak Seimbang: Rp {{ number_format(abs($balance['total_aset'] - $balance['total_kewajiban_ekuitas']), 0, ',', '.') }}
                    </x-filament::badge>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="border-b">
                        <tr>
                            <th class="py-2 px-4 font-semibold text-gray-900 dark:text-white">Keterangan</th>
                            <th class="py-2 px-4 text-right font-semibold text-gray-900 dark:text-white">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- AKTIVA --}}
                        <tr class="bg-gray-50 dark:bg-gray-800"><td colspan="2" class="py-2 px-4 font-bold text-lg">AKTIVA</td></tr>
                        
                        {{-- Aktiva Lancar --}}
                        <tr class="bg-gray-50 dark:bg-gray-800/50"><td colspan="2" class="py-2 px-4 pl-8 font-bold">Aktiva Lancar</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Kas</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1101'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Bank</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1102'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Piutang Dagang</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1103'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Piutang Ongkir</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1107'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Uang Muka Pembelian Petani</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1110'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Piutang Investasi</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1109'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Gaji Bayar di Muka</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1111'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Persediaan Bunga (Akhir)</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1104'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Perlengkapan</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1108'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-12">Total Aktiva Lancar</td><td class="py-2 px-4 text-right">{{ number_format(collect($balance['aset_groups']['Aktiva Lancar'] ?? [])->sum('saldo'), 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- Aktiva Tetap --}}
                        <tr class="bg-gray-50 dark:bg-gray-800/50"><td colspan="2" class="py-2 px-4 pl-8 font-bold">Aktiva Tetap</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Peralatan</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1105'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12 text-danger-600">Akumulasi Penyusutan Peralatan</td><td class="py-2 px-4 text-right text-danger-600">({{ number_format(abs($balance['balances_by_code']['1106'] ?? 0), 0, ',', '.') }})</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Uang Muka Pembelian Tanah</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['1112'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-12">Total Aktiva Tetap</td><td class="py-2 px-4 text-right">
                            @php
                                $totalAktivaTetap = collect($balance['aset_groups']['Aktiva Tetap'] ?? [])->sum('saldo') + collect($balance['aset_groups']['Aktiva Tetap (Kontra)'] ?? [])->sum('saldo');
                            @endphp
                            {{ $totalAktivaTetap < 0 ? '(' : '' }}{{ number_format(abs($totalAktivaTetap), 0, ',', '.') }}{{ $totalAktivaTetap < 0 ? ')' : '' }}
                        </td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- TOTAL AKTIVA --}}
                        <tr class="bg-primary-50 dark:bg-primary-900/20 text-lg"><td class="py-3 px-4 font-bold text-primary-600 dark:text-primary-400">TOTAL AKTIVA</td><td class="py-3 px-4 text-right font-bold text-primary-600 dark:text-primary-400">{{ number_format($balance['total_aset'], 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-4 border-none"></td></tr>

                        {{-- KEWAJIBAN --}}
                        <tr class="bg-gray-50 dark:bg-gray-800"><td colspan="2" class="py-2 px-4 font-bold text-lg">KEWAJIBAN</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Hutang Dagang</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['2101'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Uang Muka Penjualan</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['2102'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-12">Total Kewajiban</td><td class="py-2 px-4 text-right">{{ number_format($balance['total_kewajiban'], 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- MODAL --}}
                        <tr class="bg-gray-50 dark:bg-gray-800"><td colspan="2" class="py-2 px-4 font-bold text-lg">MODAL</td></tr>
                        <tr><td class="py-2 px-4 pl-12">Modal Pemilik (Awal)</td><td class="py-2 px-4 text-right">{{ number_format($balance['balances_by_code']['3101'] ?? 0, 0, ',', '.') }}</td></tr>
                        <tr>
                            <td class="py-2 px-4 pl-12 {{ $balance['laba_ditahan'] < 0 ? 'text-danger-600' : '' }}">Laba Bersih Periode Berjalan</td>
                            <td class="py-2 px-4 text-right {{ $balance['laba_ditahan'] < 0 ? 'text-danger-600' : '' }}">
                                {{ $balance['laba_ditahan'] < 0 ? '(' : '' }}{{ number_format(abs($balance['laba_ditahan']), 0, ',', '.') }}{{ $balance['laba_ditahan'] < 0 ? ')' : '' }}
                            </td>
                        </tr>
                        <tr><td class="py-2 px-4 pl-12 text-danger-600">Prive</td><td class="py-2 px-4 text-right text-danger-600">({{ number_format(abs($balance['balances_by_code']['3102'] ?? 0), 0, ',', '.') }})</td></tr>
                        <tr class="font-bold"><td class="py-2 px-4 pl-12">Total Modal (Akhir)</td><td class="py-2 px-4 text-right">{{ number_format($balance['total_ekuitas'], 0, ',', '.') }}</td></tr>
                        <tr><td colspan="2" class="py-2"></td></tr>

                        {{-- TOTAL KEWAJIBAN + MODAL --}}
                        <tr class="bg-primary-50 dark:bg-primary-900/20 text-lg"><td class="py-3 px-4 font-bold text-primary-600 dark:text-primary-400">TOTAL KEWAJIBAN + MODAL</td><td class="py-3 px-4 text-right font-bold text-primary-600 dark:text-primary-400">{{ number_format($balance['total_kewajiban_ekuitas'], 0, ',', '.') }}</td></tr>

                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if($activeTab === 'arus-kas')
        {{ $this->cashFlowFilterSchema }}

        @php $cashFlow = $this->getCashFlowData(); @endphp

        @if($cashFlow)
            {{-- Export Buttons --}}
            <div class="flex flex-wrap gap-3 mt-4 mb-4">
                <x-filament::button color="danger" icon="heroicon-o-document-arrow-down" wire:click="downloadCashFlowPdf" size="sm">
                    Download PDF
                </x-filament::button>
                <x-filament::button color="success" icon="heroicon-o-table-cells" wire:click="downloadCashFlowCsv" size="sm">
                    Download CSV
                </x-filament::button>
            </div>

            <x-filament::section
                heading="Arus Kas"
                description="Periode: {{ $cashFlow['period']->label }}"
            >
                <div class="mb-6">
                <div class="mb-4">
                    @if($cashFlow['is_reconciled'])
                        <x-filament::badge color="success">Kas Terekonsiliasi Seimbang</x-filament::badge>
                    @else
                        <x-filament::badge color="danger">
                            Selisih Kas: Rp {{ number_format(abs($cashFlow['difference']), 0, ',', '.') }}
                            (Cek transaksi kas/bank yang tidak terpetakan)
                        </x-filament::badge>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="border-b">
                            <tr>
                                <th class="py-2 px-4 font-semibold text-gray-900 dark:text-white w-24">Helper</th>
                                <th class="py-2 px-4 font-semibold text-gray-900 dark:text-white">Keterangan</th>
                                <th class="py-2 px-4 text-right font-semibold text-gray-900 dark:text-white">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            {{-- OPERATING --}}
                            <tr>
                                <td></td>
                                <td class="py-3 px-4 font-bold text-lg text-gray-900 dark:text-white">ARUS KAS DARI AKTIVITAS OPERASI</td>
                                <td></td>
                            </tr>
                            @foreach($cashFlow['operating'] as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="py-2 px-4 font-mono text-xs text-gray-500">{{ $item['key'] }}</td>
                                    <td class="py-2 px-4">{{ $item['label'] }}</td>
                                    <td class="py-2 px-4 text-right font-medium {{ $item['amount'] < 0 ? 'text-danger-600' : '' }}">
                                        {{ $item['amount'] < 0 ? '(' : '' }}{{ number_format(abs($item['amount']), 0, ',', '.') }}{{ $item['amount'] < 0 ? ')' : '' }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 dark:bg-gray-800/50 font-bold">
                                <td></td>
                                <td class="py-3 px-4">Kas Bersih dari Aktivitas Operasi</td>
                                <td class="py-3 px-4 text-right {{ $cashFlow['operating_total'] < 0 ? 'text-danger-600' : '' }}">
                                    {{ $cashFlow['operating_total'] < 0 ? '(' : '' }}{{ number_format(abs($cashFlow['operating_total']), 0, ',', '.') }}{{ $cashFlow['operating_total'] < 0 ? ')' : '' }}
                                </td>
                            </tr>
                            <tr><td colspan="3" class="py-2 border-none"></td></tr>

                            {{-- INVESTING --}}
                            <tr>
                                <td></td>
                                <td class="py-3 px-4 font-bold text-lg text-gray-900 dark:text-white">ARUS KAS DARI AKTIVITAS INVESTASI</td>
                                <td></td>
                            </tr>
                            @foreach($cashFlow['investing'] as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="py-2 px-4 font-mono text-xs text-gray-500">{{ $item['key'] }}</td>
                                    <td class="py-2 px-4">{{ $item['label'] }}</td>
                                    <td class="py-2 px-4 text-right font-medium {{ $item['amount'] < 0 ? 'text-danger-600' : '' }}">
                                        {{ $item['amount'] < 0 ? '(' : '' }}{{ number_format(abs($item['amount']), 0, ',', '.') }}{{ $item['amount'] < 0 ? ')' : '' }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 dark:bg-gray-800/50 font-bold">
                                <td></td>
                                <td class="py-3 px-4">Kas Bersih dari Aktivitas Investasi</td>
                                <td class="py-3 px-4 text-right {{ $cashFlow['investing_total'] < 0 ? 'text-danger-600' : '' }}">
                                    {{ $cashFlow['investing_total'] < 0 ? '(' : '' }}{{ number_format(abs($cashFlow['investing_total']), 0, ',', '.') }}{{ $cashFlow['investing_total'] < 0 ? ')' : '' }}
                                </td>
                            </tr>
                            <tr><td colspan="3" class="py-2 border-none"></td></tr>

                            {{-- FINANCING --}}
                            <tr>
                                <td></td>
                                <td class="py-3 px-4 font-bold text-lg text-gray-900 dark:text-white">ARUS KAS DARI AKTIVITAS PENDANAAN</td>
                                <td></td>
                            </tr>
                            @foreach($cashFlow['financing'] as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="py-2 px-4 font-mono text-xs text-gray-500">{{ $item['key'] }}</td>
                                    <td class="py-2 px-4">{{ $item['label'] }}</td>
                                    <td class="py-2 px-4 text-right font-medium {{ $item['amount'] < 0 ? 'text-danger-600' : '' }}">
                                        {{ $item['amount'] < 0 ? '(' : '' }}{{ number_format(abs($item['amount']), 0, ',', '.') }}{{ $item['amount'] < 0 ? ')' : '' }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 dark:bg-gray-800/50 font-bold">
                                <td></td>
                                <td class="py-3 px-4">Kas Bersih dari Aktivitas Pendanaan</td>
                                <td class="py-3 px-4 text-right {{ $cashFlow['financing_total'] < 0 ? 'text-danger-600' : '' }}">
                                    {{ $cashFlow['financing_total'] < 0 ? '(' : '' }}{{ number_format(abs($cashFlow['financing_total']), 0, ',', '.') }}{{ $cashFlow['financing_total'] < 0 ? ')' : '' }}
                                </td>
                            </tr>
                            <tr><td colspan="3" class="py-2 border-none"></td></tr>

                            {{-- SUMMARY --}}
                            <tr class="bg-primary-50 dark:bg-primary-900/20 text-lg">
                                <td></td>
                                <td class="py-3 px-4 font-bold text-primary-600 dark:text-primary-400">KENAIKAN (PENURUNAN) KAS & BANK BERSIH</td>
                                <td class="py-3 px-4 text-right font-bold {{ $cashFlow['net_change'] < 0 ? 'text-danger-600' : 'text-primary-600 dark:text-primary-400' }}">
                                    {{ $cashFlow['net_change'] < 0 ? '(' : '' }}{{ number_format(abs($cashFlow['net_change']), 0, ',', '.') }}{{ $cashFlow['net_change'] < 0 ? ')' : '' }}
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td class="py-2 px-4 pl-8">Saldo Kas & Bank Awal Periode</td>
                                <td class="py-2 px-4 text-right font-medium">{{ number_format($cashFlow['opening_cash'], 0, ',', '.') }}</td>
                            </tr>
                            <tr class="font-bold text-lg border-t-2 border-gray-900 dark:border-white">
                                <td></td>
                                <td class="py-3 px-4 pl-8">Saldo Kas & Bank Akhir Periode</td>
                                <td class="py-3 px-4 text-right">{{ number_format($cashFlow['ending_cash'], 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="text-sm text-gray-500">Buat periode akuntansi terlebih dahulu.</p>
            </x-filament::section>
        @endif
    @endif

    <x-filament-actions::modals />

</x-filament-panels::page>
