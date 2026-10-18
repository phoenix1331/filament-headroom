@props([
    'cost',
])

@php
    /** @var \Phoenix1331\FilamentHeadroom\Support\Cost $cost */
    $label = $cost->basis->getLabel();
@endphp

<span {{ $attributes->class(['fi-headroom-cost inline-flex flex-wrap items-center gap-x-2 gap-y-1']) }}>
    <span @class(['fi-headroom-cost-amount', 'text-gray-500 dark:text-gray-400' => $cost->isEmpty()])>{{ $cost->amount() }}</span>

    @if ($label !== null && ! $cost->isEmpty())
        <x-filament::badge
            :color="$cost->basis->getColor()"
            :tooltip="$cost->basis->getTooltip()"
            size="sm"
        >
            {{ $label }}
        </x-filament::badge>
    @endif
</span>
