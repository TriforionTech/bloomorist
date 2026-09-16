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
        <div class="flex flex-wrap gap-2">
            <x-filament::button color="danger" icon="heroicon-o-document-arrow-down" wire:click="downloadIncomeStatementPdf" size="sm">
                Download PDF
            </x-filament::button>
            <x-filament::button color="success" icon="heroicon-o-table-cells" wire:click="downloadIncomeStatementCsv" size="sm">
                Download CSV
            </x-filament::button>
        </div>

        <x-filament::section
            heading="Laba Rugi"
            description="Periode: {{ $income['start_date']->format('d M Y') }} — {{ $income['end_date']->format('d M Y') }}"
        >
            <div class="space-y-8">
                <x-filament::section heading="Pendapatan" compact>
                    <x-slot name="afterHeader">
                        <span class="text-sm font-semibold">
                            Rp {{ number_format($income['total_pendapatan'], 0, ',', '.') }}
                        </span>
                    </x-slot>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($income['pendapatan'] as $item)
                    <tr>
                        <td class="py-3 pr-4 font-mono text-xs text-gray-500">{{ $item['kode_akun'] }}</td>
                        <td class="py-3">{{ $item['nama_akun'] }}</td>
                        <td class="py-3 text-right font-medium whitespace-nowrap">Rp {{ number_format($item['saldo'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

            @if($income['persediaan_akhir'] !== null)
                <x-filament::section heading="Perhitungan HPP (Periodik)" compact>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach([
                        'Persediaan Awal' => $income['persediaan_awal'],
                        'Pembelian Bersih' => $income['pembelian_bersih'],
                        'Beban Angkut Pembelian' => $income['beban_angkut_pembelian'],
                        'Barang Tersedia Dijual' => $income['barang_tersedia_dijual'],
                        'Persediaan Akhir' => -$income['persediaan_akhir'],
                        'HPP' => $income['hpp'],
                    ] as $label => $amount)
                        <tr class="{{ $label === 'HPP' ? 'font-semibold' : '' }}">
                            <td class="py-3">{{ $label }}</td>
                            <td class="py-3 text-right whitespace-nowrap">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-slot name="footer">
                        <div class="flex justify-between font-semibold">
                            <span>Laba Kotor</span>
                            <span>Rp {{ number_format($income['laba_kotor'], 0, ',', '.') }}</span>
                        </div>
                    </x-slot>
                </x-filament::section>
            @endif

                <x-filament::section heading="Beban" compact>
                    <x-slot name="afterHeader">
                        <span class="text-sm font-semibold">
                            Rp {{ number_format($income['total_beban'], 0, ',', '.') }}
                        </span>
                    </x-slot>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($income['beban'] as $item)
                    <tr>
                        <td class="py-3 pr-4 font-mono text-xs text-gray-500">{{ $item['kode_akun'] }}</td>
                        <td class="py-3">{{ $item['nama_akun'] }}</td>
                        <td class="py-3 text-right font-medium whitespace-nowrap">Rp {{ number_format($item['saldo'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex justify-between text-lg font-semibold">
                        <span>{{ $income['laba_rugi'] >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}</span>
                        <span>Rp {{ number_format(abs($income['laba_rugi']), 0, ',', '.') }}</span>
                    </div>
                </x-filament::section>
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
        <div class="flex gap-3 mb-4">
            <x-filament::button color="danger" icon="heroicon-o-document-arrow-down" wire:click="downloadBalanceSheetPdf" size="sm">
                Download PDF
            </x-filament::button>
            <x-filament::button color="success" icon="heroicon-o-table-cells" wire:click="downloadBalanceSheetCsv" size="sm">
                Download CSV
            </x-filament::button>
        </div>

        <x-filament::section
            heading="Neraca"
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

            @foreach([
                'Aktiva' => [$balance['aset_groups'], $balance['total_aset']],
                'Kewajiban' => [$balance['kewajiban_groups'], $balance['total_kewajiban']],
                'Modal' => [$balance['ekuitas_groups'], $balance['total_ekuitas']],
            ] as $heading => [$groups, $total])
                <x-filament::section :heading="$heading" compact class="mb-6">
                    @foreach($groups as $category => $items)
                        <div class="mb-6 last:mb-0">
                            <h4 class="mb-2 text-sm font-semibold">{{ $category }}</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($items as $item)
                                            <tr>
                                                <td class="py-3 pr-4 font-mono text-xs text-gray-500">{{ $item['kode_akun'] }}</td>
                                                <td class="py-3">{{ $item['nama_akun'] }}</td>
                                                <td class="py-3 text-right font-medium whitespace-nowrap">Rp {{ number_format($item['saldo'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                        @if($heading === 'Modal' && $category === 'Modal')
                                            <tr>
                                                <td class="py-3 pr-4 font-mono text-xs text-gray-500">—</td>
                                                <td class="py-3">Laba Ditahan</td>
                                                <td class="py-3 text-right font-medium whitespace-nowrap">Rp {{ number_format(abs($balance['laba_ditahan']), 0, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2 flex justify-between border-t pt-2 text-sm font-medium">
                                <span>Total {{ $category }}</span>
                                <span>Rp {{ number_format(collect($items)->sum('saldo') + (($heading === 'Modal' && $category === 'Modal') ? $balance['laba_ditahan'] : 0), 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                    <x-slot name="footer">
                        <div class="flex justify-between font-semibold">
                            <span>Total {{ $heading }}</span>
                            <span>Rp {{ number_format($total, 0, ',', '.') }}</span>
                        </div>
                    </x-slot>
                </x-filament::section>
            @endforeach

            <x-filament::section>
                <div class="flex justify-between text-lg font-semibold">
                    <span>Total Kewajiban + Ekuitas</span>
                    <span>Rp {{ number_format($balance['total_kewajiban_ekuitas'], 0, ',', '.') }}</span>
                </div>
            </x-filament::section>
        </x-filament::section>
    @endif

    @if($activeTab === 'arus-kas')
        {{ $this->cashFlowFilterSchema }}

        @php $cashFlow = $this->getCashFlowData(); @endphp

        @if($cashFlow)
            <x-filament::section
                heading="Arus Kas"
                description="Periode: {{ $cashFlow['period']->label }}"
            >
                <div class="mb-6">
                    @if($cashFlow['is_reconciled'])
                        <x-filament::badge color="success">Saldo kas terrekonsiliasi</x-filament::badge>
                    @else
                        <x-filament::badge color="danger">Saldo kas tidak terrekonsiliasi</x-filament::badge>
                    @endif
                </div>

                @foreach(['operating' => 'Aktivitas Operasi', 'investing' => 'Aktivitas Investasi', 'financing' => 'Aktivitas Pendanaan'] as $section => $label)
                    <x-filament::section :heading="$label" compact class="mb-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($cashFlow[$section] as $line)
                                        <tr>
                                            <td class="py-3">{{ $line['label'] }}</td>
                                            <td class="py-3 text-right font-medium whitespace-nowrap">Rp {{ number_format($line['amount'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <x-slot name="footer">
                            <div class="flex justify-between font-semibold">
                                <span>Total {{ $label }}</span>
                                <span>Rp {{ number_format($cashFlow["{$section}_total"], 0, ',', '.') }}</span>
                            </div>
                        </x-slot>
                    </x-filament::section>
                @endforeach

                <x-filament::section heading="Ringkasan" compact>
                    <dl class="divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <div class="flex justify-between py-3">
                            <dt>Saldo Kas Awal</dt>
                            <dd class="font-medium">Rp {{ number_format($cashFlow['opening_cash'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt>Kenaikan (Penurunan) Kas Bersih</dt>
                            <dd class="font-medium">Rp {{ number_format($cashFlow['net_change'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between py-3 text-base font-semibold">
                            <dt>Saldo Kas Akhir</dt>
                            <dd>Rp {{ number_format($cashFlow['ending_cash'], 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </x-filament::section>
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="text-sm text-gray-500">Buat periode akuntansi terlebih dahulu.</p>
            </x-filament::section>
        @endif
    @endif

    <x-filament-actions::modals />

</x-filament-panels::page>
