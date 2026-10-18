@props([
    'steps' => [],
])

<div class="fi-headroom-run-steps">
    <table>
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">{{ __('filament-headroom::pages.detail.steps.time') }}</th>
                <th scope="col">{{ __('filament-headroom::pages.requests.columns.model') }}</th>
                <th scope="col">{{ __('filament-headroom::pages.requests.columns.outcome') }}</th>
                <th scope="col" class="fi-headroom-number">{{ __('filament-headroom::pages.detail.steps.estimated_input') }}</th>
                <th scope="col" class="fi-headroom-number">{{ __('filament-headroom::pages.detail.steps.growth') }}</th>
                <th scope="col" class="fi-headroom-number">{{ __('filament-headroom::pages.requests.columns.context') }}</th>
                <th scope="col">{{ __('filament-headroom::pages.requests.columns.cost') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($steps as $step)
                <tr @if ($step['current']) aria-current="true" @endif>
                    <td class="fi-headroom-number">
                        @if ($step['current'])
                            {{ $step['number'] }}
                        @else
                            <a href="{{ $step['url'] }}">{{ $step['number'] }}</a>
                        @endif
                    </td>
                    <td class="fi-headroom-tabular">{{ $step['counted_at'] ?? '-' }}</td>
                    <td>{{ $step['model'] ?? '-' }}</td>
                    <td>
                        <x-filament::badge :color="$step['outcome_color']" size="sm">{{ $step['outcome'] }}</x-filament::badge>
                    </td>
                    <td class="fi-headroom-number">{{ $step['estimated'] ?? '-' }}</td>
                    <td class="fi-headroom-number fi-headroom-muted">{{ $step['growth'] ?? '-' }}</td>
                    <td class="fi-headroom-number">{{ $step['percent'] ?? '-' }}</td>
                    <td><x-filament-headroom::cost :cost="$step['cost']" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
