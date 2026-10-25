<?php
// Development server only. Unlike the verbose Laravel router, do not log QR URLs.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$public=realpath(__DIR__.'/../public');
$uri=rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file=realpath($public.$uri);
if ($file && str_starts_with($file,$public.DIRECTORY_SEPARATOR) && is_file($file)) return false;
require $public.'/index.php';
