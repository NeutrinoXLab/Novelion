<?php

return [
    'enabled' => in_array(env('STRIPE_RECONCILIATION_ENABLED', false), [true, 'true'], true),
    'checkout_hosts' => ['checkout.stripe.com'],
    'batch' => 50,
    'read_budget' => 100, // Enumeration allowance per run; effective minimum 4.
    // Added once per run, clamped to [4, 2014]. After finalization starts,
    // insufficient reads/time mean manual_review + cleared cursor, never scan_incomplete.
    // No fixed budget relationship suffices for every history; current-run proof stays mandatory.
    'validation_read_budget' => 1014,
    'runtime_seconds' => 180,
    'lease_seconds' => 240,
    'page_size' => 100,
    'scan_max_pages' => 1000,
    'scan_max_seconds' => 86400,
    'pending_seconds' => 300,
    'stable_seconds' => 604800,
    'manual_seconds' => 86400,
    'async_manual_after_seconds' => 86400,
    'alert_after_failures' => 8,
    'backoff_seconds' => [300, 900, 3600, 21600, 86400],
    'connect_timeout' => 3,
    'request_timeout' => 10,
];
