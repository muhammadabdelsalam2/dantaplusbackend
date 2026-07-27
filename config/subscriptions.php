<?php

return [
    'plan_prices' => [
        'basic' => (float) env('DANTAPLUS_PLAN_BASIC_PRICE', 99),
        'standard' => (float) env('DANTAPLUS_PLAN_STANDARD_PRICE', 149),
        'premium' => (float) env('DANTAPLUS_PLAN_PREMIUM_PRICE', 249),
    ],
];
