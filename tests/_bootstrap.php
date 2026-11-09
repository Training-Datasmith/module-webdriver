<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__);

require_once $packageRoot . '/tests/data/app/data.php';
require_once $packageRoot . '/tests/unit/Codeception/Constraints/TestedWebElement.php';

spl_autoload_register(static function (string $class) use ($packageRoot): void {
    $prefix = 'Tests\\Web\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $packageRoot . '/tests/web/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
