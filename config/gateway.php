<?php

$split = static fn (?string $value): array => array_values(array_filter(
    array_map('trim', explode(',', (string) $value)),
    static fn (string $item): bool => $item !== '',
));

$validHostname = static function (string $pattern): bool {
    $host = strtolower(rtrim(trim($pattern), '.'));
    if (str_starts_with($host, '*.')) {
        $host = substr($host, 2);
    }
    if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return true;
    }

    return strlen($host) <= 253
        && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host) === 1;
};

$configuredHosts = $split(env('GATEWAY_TRUSTED_HOSTS'));
$appHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);
if (is_string($appHost) && $appHost !== '') {
    $configuredHosts[] = $appHost;
}
if (in_array((string) env('APP_ENV', 'production'), ['local', 'testing'], true)) {
    array_push($configuredHosts, 'localhost', '127.0.0.1', '::1');
}
$configuredHosts = array_values(array_unique(array_map('strtolower', $configuredHosts)));
$hosts = array_values(array_filter($configuredHosts, $validHostname));

$configuredPrefixes = array_values(array_unique(array_map(
    static fn (string $prefix): string => $prefix === '/' ? '' : '/'.trim($prefix, '/'),
    $split(env('GATEWAY_ALLOWED_PREFIXES', '/,/simmag')),
)));
$validPrefix = static fn (string $prefix): bool => $prefix === ''
    || preg_match('#^/[A-Za-z0-9._~-]+(?:/[A-Za-z0-9._~-]+)*$#', $prefix) === 1;
$prefixes = array_values(array_filter($configuredPrefixes, $validPrefix));

return [
    // Exact hosts or wildcard suffixes such as *.smkn1cerme.test. These are
    // runtime settings so a new tunnel hostname never requires a source edit.
    'trusted_hosts' => $hosts,
    'invalid_hosts' => array_values(array_filter($configuredHosts, static fn (string $host): bool => ! $validHostname($host))),
    // '/' represents root/subdomain mode. Prefixes are supplied by the trusted
    // gateway through X-Forwarded-Prefix after it strips the public prefix.
    'allowed_prefixes' => $prefixes,
    'invalid_prefixes' => array_values(array_filter($configuredPrefixes, static fn (string $prefix): bool => ! $validPrefix($prefix))),
];
