@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $type = $this->getType();
    $hasError = $this->hasChartError();
    $isEmpty = ! $hasError && ! $this->hasChartData();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :heading="$heading" :description="$description">
        @if ($hasError)
            <div class="dashboard-chart-error flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-danger-300 bg-danger-50/50 px-4 py-10 text-center dark:border-danger-500/40 dark:bg-danger-500/5">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-8 w-8 text-danger-500" />
                <p class="text-sm font-semibold text-danger-700 dark:text-danger-400">Grafik gagal dimuat</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Terjadi kesalahan saat mengambil data. Muat ulang halaman atau ubah periode.</p>
            </div>
        @elseif ($isEmpty)
            <div class="dashboard-chart-empty flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center dark:border-white/15">
                <x-filament::icon :icon="$this->getEmptyStateIcon()" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $this->getEmptyStateMessage() }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Ubah periode untuk melihat data lainnya.</p>
            </div>
        @else
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
                        ])
                        ->style([
                            'max-height: ' . $maxHeight => filled($maxHeight),
                        ])
                }}
            >
                <canvas x-ref="canvas"></canvas>

                <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
