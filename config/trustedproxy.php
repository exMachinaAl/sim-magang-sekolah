<?php

return [
    // Explicit proxy IPs only. Never trust every client or forwarded host headers.
    'proxies' => array_values(array_filter(
        array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))),
        fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false,
    )),
];
