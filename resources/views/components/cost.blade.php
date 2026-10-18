@props([
    'cost',
])

@php
    /** @var \Phoenix1331\FilamentHeadroom\Support\Cost $cost */
    $label = $cost->basis->getLabel();
@endphp

<span {{ $attributes->class(['fi-headroom-cost']) }}>
    <span @class(['fi-headroom-cost-amount', 'fi-headroom-muted' => $cost->isEmpty()])>{{ $cost->amount() }}</span>

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
