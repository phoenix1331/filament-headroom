<?php

declare(strict_types=1);

return [
    'overview' => [
        'title' => 'AI usage',
        'navigation_label' => 'Overview',
    ],
    'requests' => [
        'title' => 'Requests',
        'navigation_label' => 'Requests',
        'empty' => 'No requests in this range',
        'counted' => ':method count',
        'runs' => [
            'title' => 'Agent runs',
            'empty' => 'No agent runs in this range',
            'group_by_run' => 'Group by run',
            'show_requests' => 'Show requests',
            'problems' => '{1} :count warned, blocked or failed|[2,*] :count warned, blocked or failed',
            'columns' => [
                'started' => 'Started',
                'steps' => 'Steps',
                'peak_context' => 'Peak context',
            ],
        ],
        'filters' => [
            'provider' => 'Provider',
            'count_method' => 'Count method',
        ],
        'columns' => [
            'when' => 'When',
            'outcome' => 'Outcome',
            'agent' => 'Agent',
            'model' => 'Model',
            'input_tokens' => 'Input',
            'output_tokens' => 'Output',
            'context' => 'Context',
            'cost' => 'Cost',
        ],
    ],
    'models' => [
        'title' => 'Models',
        'navigation_label' => 'Models',
    ],
];
