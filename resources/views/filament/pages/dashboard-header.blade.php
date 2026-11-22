<header class="fi-header flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div class="flex flex-col gap-2 min-w-0">
        <h1 class="fi-header-heading">{{ $heading }}</h1>

        <div id="dashboard-active-period" class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-base text-gray-700 dark:text-gray-200">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3 py-1 text-sm font-semibold text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30">
                <x-filament::icon icon="heroicon-m-calendar-days" class="h-4 w-4" />
                Periode aktif: {{ $period->presetLabel() }}
            </span>
            <span class="font-semibold">{{ $period->rangeLabel() }}</span>
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            Data historis mengikuti periode ini · Diperbarui {{ $updatedAt }}
            @if ($period->hasComparison())
                · Pembanding: {{ $period->compareRangeLabel() }} ({{ $period->compareLabel }})
            @endif
        </p>
    </div>

    <div id="dashboard-period-filter" class="w-full shrink-0 lg:w-auto lg:ml-auto">
        <div
            class="{{ $period->preset === 'custom' ? 'w-full lg:w-[36rem]' : 'w-full sm:w-64 lg:w-72' }}"
            style="min-width: {{ $period->preset === 'custom' ? 'min(100%, 34rem)' : '15rem' }};"
        >
            {{ $filtersForm }}
        </div>
    </div>

    <style>
        #dashboard-period-filter .fi-select-input-value-label {
            white-space: nowrap;
        }
        #dashboard-period-filter .fi-dropdown-panel {
            min-width: 100% !important;
            width: max-content !important;
        }
    </style>
</header>