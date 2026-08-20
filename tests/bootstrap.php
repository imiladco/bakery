<?php

declare(strict_types=1);

// Domain tests are pure PHP and must never require a WordPress bootstrap
// (see Architecture V3/V4 test-layering decision). This autoloader only
// wires up the plugin's own namespace for the test run.

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';

if (is_file($vendorAutoload)) {
    require $vendorAutoload;

    return;
}

spl_autoload_register(static function (string $class): void {
    $map = [
        'WHW\\Tests\\' => __DIR__ . '/',
        'WHW\\' => __DIR__ . '/../includes/',
    ];

    foreach ($map as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});
