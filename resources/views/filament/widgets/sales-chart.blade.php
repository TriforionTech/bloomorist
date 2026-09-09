@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
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

        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
            class="overflow-x-auto w-full pb-2"
        >
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
                            'min-w-[700px] lg:min-w-0'
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
    </x-filament::section>
</x-filament-widgets::widget>
