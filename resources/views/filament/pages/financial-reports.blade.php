<x-filament-panels::page>

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="-mb-px flex space-x-8">
            <button
                wire:click="$set('activeTab', 'laba-rugi')"
                class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'laba-rugi' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
            >
                <x-heroicon-o-arrow-trending-up class="inline-block w-5 h-5 mr-1 -mt-0.5" />
                Laba Rugi
            </button>
            <button
                wire:click="$set('activeTab', 'neraca')"
                class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'neraca' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
            >
                <x-heroicon-o-scale class="inline-block w-5 h-5 mr-1 -mt-0.5" />
                Neraca
            </button>
        </nav>
    </div>

    {{-- ================================================================ --}}
    {{-- TAB 1: LABA RUGI (Income Statement) --}}
    {{-- ================================================================ --}}
    @if($activeTab === 'laba-rugi')
        {{-- Filters --}}
        {{ $this->incomeFilterSchema }}

        @php $income = $this->getIncomeStatementData(); @endphp

        {{-- Export Buttons --}}
        <div class="flex gap-3 mb-4">
            <x-filament::button color="danger" icon="heroicon-o-document-arrow-down" wire:click="downloadIncomeStatementPdf" size="sm">
                Download PDF
            </x-filament::button>
            <x-filament::button color="success" icon="heroicon-o-table-cells" wire:click="downloadIncomeStatementCsv" size="sm">
                Download CSV
            </x-filament::button>
        </div>

        {{-- Period Label --}}
        <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Periode: {{ $income['start_date']->format('d M Y') }} — {{ $income['end_date']->format('d M Y') }}
        </div>

        {{-- Income Statement Card --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            {{-- PENDAPATAN Section --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-emerald-50 dark:bg-emerald-900/20">
                <h3 class="text-base font-bold text-emerald-800 dark:text-emerald-300 uppercase tracking-wide">Pendapatan</h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($income['pendapatan'] as $item)
                    <div class="px-6 py-3 flex justify-between items-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <div>
                            <span class="text-xs font-mono text-gray-400 mr-2">{{ $item['kode_akun'] }}</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['nama_akun'] }}</span>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            Rp {{ number_format($item['saldo'], 0, ',', '.') }}
                        </span>
                    </div>
                @endforeach
                <div class="px-6 py-3 flex justify-between items-center bg-emerald-50 dark:bg-emerald-900/20">
                    <span class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Total Pendapatan</span>
                    <span class="text-sm font-bold text-emerald-800 dark:text-emerald-300">
                        Rp {{ number_format($income['total_pendapatan'], 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- BEBAN Section --}}
            <div class="px-6 py-4 border-b border-t border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/20">
                <h3 class="text-base font-bold text-red-800 dark:text-red-300 uppercase tracking-wide">Beban</h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($income['beban'] as $item)
                    <div class="px-6 py-3 flex justify-between items-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <div>
                            <span class="text-xs font-mono text-gray-400 mr-2">{{ $item['kode_akun'] }}</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['nama_akun'] }}</span>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            Rp {{ number_format($item['saldo'], 0, ',', '.') }}
                        </span>
                    </div>
                @endforeach
                <div class="px-6 py-3 flex justify-between items-center bg-red-50 dark:bg-red-900/20">
                    <span class="text-sm font-bold text-red-800 dark:text-red-300">Total Beban</span>
                    <span class="text-sm font-bold text-red-800 dark:text-red-300">
                        Rp {{ number_format($income['total_beban'], 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- LABA / RUGI BERSIH --}}
            <div class="px-6 py-5 border-t-2 {{ $income['laba_rugi'] >= 0 ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/30' : 'border-red-500 bg-red-50 dark:bg-red-900/30' }}">
                <div class="flex justify-between items-center">
                    <span class="text-lg font-bold {{ $income['laba_rugi'] >= 0 ? 'text-emerald-800 dark:text-emerald-300' : 'text-red-800 dark:text-red-300' }}">
                        {{ $income['laba_rugi'] >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}
                    </span>
                    <span class="text-lg font-bold {{ $income['laba_rugi'] >= 0 ? 'text-emerald-800 dark:text-emerald-300' : 'text-red-800 dark:text-red-300' }}">
                        Rp {{ number_format(abs($income['laba_rugi']), 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
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

        {{-- Period Label --}}
        <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Per Tanggal: {{ $balance['as_of']->format('d M Y') }}
        </div>

        {{-- Balance Indicator --}}
        <div class="mb-4 px-4 py-4 rounded-lg text-sm font-medium {{ $balance['is_balanced'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }}">
            @if($balance['is_balanced'])
                ✅ Neraca Seimbang (Balance)
            @else
                ❌ Neraca Tidak Seimbang — Selisih: Rp {{ number_format(abs($balance['total_aset'] - $balance['total_kewajiban_ekuitas']), 0, ',', '.') }}
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- LEFT SIDE: ASET --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-sky-50 dark:bg-sky-900/20">
                    <h3 class="text-base font-bold text-sky-800 dark:text-sky-300 uppercase tracking-wide">Aset</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($balance['aset'] as $item)
                        <div class="px-6 py-3 flex justify-between items-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <div>
                                <span class="text-xs font-mono text-gray-400 mr-2">{{ $item['kode_akun'] }}</span>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['nama_akun'] }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                Rp {{ number_format($item['saldo'], 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>
                <div class="px-6 py-4 border-t-2 border-sky-500 bg-sky-50 dark:bg-sky-900/30">
                    <div class="flex justify-between items-center">
                        <span class="text-base font-bold text-sky-800 dark:text-sky-300">TOTAL ASET</span>
                        <span class="text-base font-bold text-sky-800 dark:text-sky-300">
                            Rp {{ number_format($balance['total_aset'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- RIGHT SIDE: KEWAJIBAN + EKUITAS --}}
            <div class="space-y-6">
                {{-- KEWAJIBAN --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-orange-50 dark:bg-orange-900/20">
                        <h3 class="text-base font-bold text-orange-800 dark:text-orange-300 uppercase tracking-wide">Kewajiban</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($balance['kewajiban'] as $item)
                            <div class="px-6 py-3 flex justify-between items-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <div>
                                    <span class="text-xs font-mono text-gray-400 mr-2">{{ $item['kode_akun'] }}</span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['nama_akun'] }}</span>
                                </div>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Rp {{ number_format($item['saldo'], 0, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-orange-50 dark:bg-orange-900/20">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-bold text-orange-800 dark:text-orange-300">Total Kewajiban</span>
                            <span class="text-sm font-bold text-orange-800 dark:text-orange-300">
                                Rp {{ number_format($balance['total_kewajiban'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- EKUITAS --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-violet-50 dark:bg-violet-900/20">
                        <h3 class="text-base font-bold text-violet-800 dark:text-violet-300 uppercase tracking-wide">Ekuitas</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($balance['ekuitas'] as $item)
                            <div class="px-6 py-3 flex justify-between items-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <div>
                                    <span class="text-xs font-mono text-gray-400 mr-2">{{ $item['kode_akun'] }}</span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['nama_akun'] }}</span>
                                </div>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    Rp {{ number_format($item['saldo'], 0, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                        {{-- LABA DITAHAN (Retained Earnings) --}}
                        <div class="px-6 py-3 flex justify-between items-center bg-amber-50 dark:bg-amber-900/20">
                            <div>
                                <span class="text-xs font-mono text-amber-600 dark:text-amber-400 mr-2">—</span>
                                <span class="text-sm font-semibold text-amber-700 dark:text-amber-300">Laba Ditahan / Retained Earnings</span>
                            </div>
                            <span class="text-sm font-bold {{ $balance['laba_ditahan'] >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                                Rp {{ number_format(abs($balance['laba_ditahan']), 0, ',', '.') }}{{ $balance['laba_ditahan'] < 0 ? ' (-)' : '' }}
                            </span>
                        </div>
                    </div>
                    <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-violet-50 dark:bg-violet-900/20">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-bold text-violet-800 dark:text-violet-300">Total Ekuitas</span>
                            <span class="text-sm font-bold text-violet-800 dark:text-violet-300">
                                Rp {{ number_format($balance['total_ekuitas'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- TOTAL KEWAJIBAN + EKUITAS --}}
                <div class="rounded-xl border-2 {{ $balance['is_balanced'] ? 'border-emerald-500' : 'border-red-500' }} bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 {{ $balance['is_balanced'] ? 'bg-emerald-50 dark:bg-emerald-900/30' : 'bg-red-50 dark:bg-red-900/30' }}">
                        <div class="flex justify-between items-center">
                            <span class="text-base font-bold {{ $balance['is_balanced'] ? 'text-emerald-800 dark:text-emerald-300' : 'text-red-800 dark:text-red-300' }}">
                                TOTAL KEWAJIBAN + EKUITAS
                            </span>
                            <span class="text-base font-bold {{ $balance['is_balanced'] ? 'text-emerald-800 dark:text-emerald-300' : 'text-red-800 dark:text-red-300' }}">
                                Rp {{ number_format($balance['total_kewajiban_ekuitas'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />

</x-filament-panels::page>
