<?php
if (PHP_SAPI === 'cli-server') {
    $__requestPath = urldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $__requestedFile = __DIR__ . $__requestPath;
    if ($__requestPath !== '/' && is_file($__requestedFile)) {
        return false;
    }
    unset($__requestPath, $__requestedFile);
}

require __DIR__ . '/config.php';

Router::dispatch();