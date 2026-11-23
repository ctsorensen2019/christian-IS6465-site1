<?php
// Router for PHP's built-in server:  php -S 0.0.0.0:8000 php/router.php   (run from the lab7 folder)
//
// Allow-list: only /, /index.php, /css/* and the page files directly inside /php/ are
// reachable. Everything else (includes/, services/, repositories/, config/, database/,
// tests/, docs/, README.md, *.db, *.sql) answers 404, so internal code and the database
// can never be fetched or executed by URL. A production server needs a dedicated public
// document root instead of this router.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if ($path === '/') {
    $path = '/index.php';
}
$allowed = $path === '/index.php'
    || preg_match('~^/css/[A-Za-z0-9._-]+\.css$~', $path)
    || preg_match('~^/php/(?!router\.php$)[A-Za-z0-9._-]+\.php$~', $path);
$base = realpath(dirname(__DIR__));
$full = $allowed ? realpath($base . $path) : false;
if ($full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($full)) {
    http_response_code(404);
    exit('Not found');
}
return false;
