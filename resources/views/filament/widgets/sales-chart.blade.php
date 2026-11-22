@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $hasError = method_exists($this, 'hasChartError') && $this->hasChartError();
    $isEmpty = ! $hasError && method_exists($this, 'hasChartData') && ! $this->hasChartData();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section
        :description="$description"
        :collapsible="$isCollapsible"
    >
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full gap-2">
                <span class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    {{ $heading }}
                </span>
                
                @if ($filters || method_exists($this, 'getFiltersSchema') || $filter === 'custom')
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if ($filters)
                            <x-filament::input.wrapper
                                inline-prefix
                                wire:target="filter"
                                class="fi-wi-chart-filter"
                            >
                                <x-filament::input.select
                                    inline-prefix
                                    wire:model.live="filter"
                                >
                                    @foreach ($filters as $value => $label)
                                        <option value="{{ $value }}">
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        @endif

                        @if (method_exists($this, 'getFiltersSchema'))
                            <x-filament::dropdown
                                placement="bottom-end"
                                shift
                                width="xs"
                                class="fi-wi-chart-filter"
                            >
                                <x-slot name="trigger">
                                    {{ $this->getFiltersTriggerAction() }}
                                </x-slot>

                                <div class="fi-wi-chart-filter-content">
                                    {{ $this->getFiltersSchema() }}
                                </div>
                            </x-filament::dropdown>
                        @endif
                    </div>
                @endif
            </div>
        </x-slot>

        <style>
            .fi-wi-sales-chart-scroll::-webkit-scrollbar {
                height: 4px;
            }
            .fi-wi-sales-chart-scroll::-webkit-scrollbar-track {
                background: transparent;
            }
            .fi-wi-sales-chart-scroll::-webkit-scrollbar-thumb {
                background: rgba(156, 163, 175, 0.4);
                border-radius: 9999px;
            }
            .dark .fi-wi-sales-chart-scroll::-webkit-scrollbar-thumb {
                background: rgba(107, 114, 128, 0.4);
            }
            @media (max-width: 1023px) {
                .fi-wi-sales-chart-scroll {
                    overflow-x: auto !important;
                    -webkit-overflow-scrolling: touch;
                    overscroll-behavior-x: contain;
                    scrollbar-width: thin;
                }
                .fi-wi-sales-chart-inner {
                    min-width: 650px !important;
                    width: 100% !important;
                }
                .fi-wi-sales-chart-inner canvas {
                    touch-action: pan-x pan-y !important;
                }
            }
            @media (min-width: 1024px) {
                .fi-wi-sales-chart-inner {
                    min-width: 100% !important;
                    width: 100% !important;
                }
            }
        </style>

        @if ($hasError)
            <div id="sales-chart-error" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-danger-300 bg-danger-50/50 px-4 py-10 text-center dark:border-danger-500/40 dark:bg-danger-500/5">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-8 w-8 text-danger-500" />
                <p class="text-sm font-semibold text-danger-700 dark:text-danger-400">Grafik gagal dimuat</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Terjadi kesalahan saat mengambil data. Muat ulang halaman atau ubah periode.</p>
            </div>
        @elseif ($isEmpty)
            <div id="sales-chart-empty" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center dark:border-white/15">
                <x-filament::icon icon="heroicon-o-chart-bar" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Belum ada transaksi pada periode ini</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Ubah periode untuk melihat data penjualan lainnya.</p>
            </div>
        @else
        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
            class="fi-wi-sales-chart-scroll w-full pb-2"
        >
            <div class="flex items-center justify-end gap-1 mb-1 text-xs text-gray-400 dark:text-gray-500 lg:hidden">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                </svg>
                <span>Arahkan kursor untuk melihat detail</span>
            </div>

            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                data-chart-type="{{ $type }}"
                x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                {{
                    (new ComponentAttributeBag)
                        ->color(ChartWidgetComponent::class, $color)
                        ->class([
                            'fi-wi-chart-canvas-ctn',
                            'fi-wi-chart-canvas-ctn-no-aspect-ratio' => filled($maxHeight = $this->getMaxHeight()),
                            'fi-wi-sales-chart-inner',
                        ])
                        ->style([
                            'max-height: ' . $maxHeight => filled($maxHeight),
                        ])
                }}
            >
                <canvas x-ref="canvas"></canvas>

                <span
                    x-ref="backgroundColorElement"
                    class="fi-wi-chart-bg-color"
                ></span>

                <span
                    x-ref="borderColorElement"
                    class="fi-wi-chart-border-color"
                ></span>

                <span
                    x-ref="gridColorElement"
                    class="fi-wi-chart-grid-color"
                ></span>

                <span
                    x-ref="textColorElement"
                    class="fi-wi-chart-text-color"
                ></span>
            </div>
        </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
