@php
    $icon = $getIcon();
    $label = $getLabel();
    $value = $getValue();
    $description = $getDescription();
    $color = $getColor() ?? 'gray';
@endphp

<div
    {{
        $getExtraAttributeBag()->only(['class', 'style', 'id'])
            ->class([
                'fi-wi-stats-overview-stat flex flex-col rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10',
            ])
    }}
>
    <div class="flex justify-between items-start mb-2">
        <div>
            @if ($getExtraAttributeBag()->has('month-nav'))
                {!! $getExtraAttributeBag()->get('month-nav') !!}
            @endif
        </div>

        @if ($icon)
            <div
                class="stat-icon-wrapper p-1.5 rounded-lg bg-gray-50 dark:bg-gray-800"
                style="--stat-icon-color: var(--{{ $color }}-600); --stat-icon-color-dark: var(--{{ $color }}-400); color: var(--{{ $color }}-600);"
            >
                <x-filament::icon
                    :icon="$icon"
                    class="w-4 h-4 stat-icon"
                    style="color: var(--stat-icon-color, var(--{{ $color }}-600));"
                />
            </div>
        @endif
    </div>

    <div class="flex flex-col gap-y-0.5">
        <div class="text-[15px] font-bold tracking-tight text-gray-950 dark:text-white">
            {{ $value }}
        </div>

        <div class="text-[13px] font-medium text-gray-500 dark:text-gray-400">
            {{ $label }}
        </div>

        @if ($description)
            <div class="text-[11px] leading-tight text-gray-400 dark:text-gray-500 mt-0.5">
                {{ $description }}
            </div>
        @endif
    </div>
</div>
