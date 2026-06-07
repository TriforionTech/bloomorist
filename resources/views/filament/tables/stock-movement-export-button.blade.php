<x-filament::dropdown placement="bottom-start" shift width="sm">
    <x-slot name="trigger">
        <x-filament::button
            color="gray"
            icon="heroicon-o-arrow-down-tray"
            outlined
        >
            Export
        </x-filament::button>
    </x-slot>

    <div style="padding: 1rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <h4 style="font-size: 1rem; font-weight: 600; color: inherit;">
                Export Options
            </h4>
        </div>

        <div style="display: grid; gap: 1rem;">
            {{-- Period Select --}}
            <div x-data="{ period: @entangle('exportPeriod') }">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">
                    Period
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="exportPeriod" x-model="period">
                        <option value="today">Today</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="custom">Custom Range</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            {{-- Custom date pickers --}}
            <div x-data="{ period: @entangle('exportPeriod') }" x-show="period === 'custom'" x-cloak>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">
                            From Date
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="date" wire:model.live="exportFrom" />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">
                            Until Date
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="date" wire:model.live="exportUntil" />
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </div>

            {{-- Export buttons --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem;">
                <x-filament::button
                    color="danger"
                    icon="heroicon-o-document-arrow-down"
                    wire:click="exportPdf"
                    x-on:click="close()"
                    size="sm"
                >
                    Export PDF
                </x-filament::button>
                <x-filament::button
                    color="success"
                    icon="heroicon-o-document-text"
                    wire:click="exportCsv"
                    x-on:click="close()"
                    size="sm"
                >
                    Export CSV
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament::dropdown>
