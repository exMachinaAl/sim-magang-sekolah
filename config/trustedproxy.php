<?php

$validProxy = static function (string $value): bool {
    if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
        return true;
    }
    if (! str_contains($value, '/')) {
        return false;
    }
    [$network, $prefix] = array_pad(explode('/', $value, 2), 2, null);
    if (filter_var($network, FILTER_VALIDATE_IP) === false || ! ctype_digit((string) $prefix)) {
        return false;
    }
    $maximum = str_contains($network, ':') ? 128 : 32;

    return (int) $prefix >= 0 && (int) $prefix <= $maximum;
};

$configured = array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', '')))));

return [
    // Explicit proxy IPs only. Never trust every client or forwarded host headers.
    'proxies' => array_values(array_filter($configured, $validProxy)),
    'invalid' => array_values(array_filter($configured, static fn (string $value): bool => ! $validProxy($value))),
];
