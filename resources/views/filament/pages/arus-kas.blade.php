<x-filament-panels::page>
    {{ $this->filterSchema }}

    @php $data = $this->getCashFlowData(); @endphp
    @if($data)
        <div class="mb-4 rounded-lg px-4 py-4 text-sm font-medium {{ $data['is_reconciled'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }}">
            {{ $data['is_reconciled'] ? '✅ Saldo akhir Arus Kas cocok dengan saldo Kas/Bank' : '❌ Arus Kas tidak cocok dengan saldo Kas/Bank' }}
        </div>

        @foreach(['operating' => 'Aktivitas Operasi', 'investing' => 'Aktivitas Investasi', 'financing' => 'Aktivitas Pendanaan'] as $section => $label)
            <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-4 font-bold dark:border-gray-700 dark:bg-gray-800 sm:px-6">{{ $label }}</div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($data[$section] as $line)
                        <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:justify-between sm:px-6">
                            <span>{{ $line['label'] }}</span>
                            <span class="font-semibold">Rp {{ number_format($line['amount'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                    <div class="flex flex-col gap-1 bg-gray-50 px-4 py-3 font-bold sm:flex-row sm:justify-between sm:px-6 dark:bg-gray-800">
                        <span>Total {{ $label }}</span>
                        <span>Rp {{ number_format($data["{$section}_total"], 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6">
            <div class="flex flex-col gap-1 py-2 sm:flex-row sm:justify-between"><span>Saldo Kas Awal</span><strong>Rp {{ number_format($data['opening_cash'], 0, ',', '.') }}</strong></div>
            <div class="flex flex-col gap-1 py-2 sm:flex-row sm:justify-between"><span>Kenaikan (Penurunan) Kas Bersih</span><strong>Rp {{ number_format($data['net_change'], 0, ',', '.') }}</strong></div>
            <div class="flex flex-col gap-1 border-t pt-3 text-lg font-bold sm:flex-row sm:justify-between"><span>Saldo Kas Akhir</span><strong>Rp {{ number_format($data['ending_cash'], 0, ',', '.') }}</strong></div>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500 dark:border-gray-600">Buat periode akuntansi terlebih dahulu.</div>
    @endif
</x-filament-panels::page>
