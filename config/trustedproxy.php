<?php

return [
    // Only configure verified proxy IPs/CIDRs; never trust all clients.
    'proxies' => array_values(array_filter(array_map(trim(...), explode(',', env('TRUSTED_PROXIES', ''))))),
];
