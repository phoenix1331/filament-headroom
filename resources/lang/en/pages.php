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
