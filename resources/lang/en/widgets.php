<?php

declare(strict_types=1);

return [
    'no_data' => 'No data',

    'stats' => [
        'spend' => 'Spend',
        'spend_trend_up' => ':percent more than the previous :days days',
        'spend_trend_down' => ':percent less than the previous :days days',
        'spend_trend_flat' => 'Same as the previous :days days',
        'spend_trend_new' => 'No spend in the previous :days days',
        'requests' => 'Requests',
        'requests_with_usage' => ':count with usage reported',
        'context' => 'Average context used',
        'context_peak' => 'Peak :percent, from pre-flight counts',
        'blocked' => 'Blocked',
        'blocked_description' => 'Did not fit the context window, never sent',
        'warned' => 'Warned',
        'warned_description' => 'Crossed a soft limit, still sent',
    ],
];
