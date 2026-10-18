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

    'spend_over_time' => [
        'heading' => 'Spend over time',
        'by_model' => 'By model',
        'by_provider' => 'By provider',
        'other' => 'Other',
        'empty' => 'No spend in this range.',
        'includes_estimates' => 'Includes estimates for requests that never reported usage.',
        'other_currencies' => 'Showing :currency only; spend in :others is not converted or included.',
    ],

    'tokens_by_agent' => [
        'heading' => 'Tokens by agent',
        'input' => 'Input',
        'output' => 'Output',
        'other' => 'Other agents',
        'no_agent' => 'No agent',
        'unreported' => '{1} Reported usage only: :count request never reported usage and is not included.|[2,*] Reported usage only: :count requests never reported usage and are not included.',
    ],

    'context_headroom' => [
        'heading' => 'Context headroom',
        'requests' => 'Requests',
        'bucket' => ':from-:to%',
        'over' => ':percent%+',
        'empty' => 'No counted requests in this range.',
        'near_limit' => '{0} Share of the context window used, from pre-flight counts; over 100% did not fit. No requests reached :percent%.|{1} Share of the context window used, from pre-flight counts; over 100% did not fit. :count request reached :percent% or more.|[2,*] Share of the context window used, from pre-flight counts; over 100% did not fit. :count requests reached :percent% or more.',
    ],
];
