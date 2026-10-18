<?php

declare(strict_types=1);

return [
    'estimated' => [
        'label' => 'Estimate',
        'tooltip' => "No usage was reported, so this is Headroom's pre-flight estimate (input plus the model's maximum output), not a billed cost.",
    ],
    'includes_estimates' => [
        'label' => 'Includes estimates',
        'tooltip' => 'Some requests never reported usage and are counted at their pre-flight estimate, so this total is not all billed cost.',
    ],
    'approximate' => [
        'label' => 'Approximate',
        'tooltip' => 'Cache tokens were reported but could not be priced exactly, so this cost is approximate.',
    ],
    'none' => 'No cost recorded',
    'with_amount' => ':amount (:basis)',
];
