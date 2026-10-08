<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Support;

use Codeception\Lib\Di;
use Codeception\Lib\ModuleContainer;
use Codeception\Module\WebDriver;

final class ModuleFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config = [], bool $inspectable = true): WebDriver|InspectableWebDriver
    {
        $defaults = [
            'browser' => 'chrome',
            'url' => 'http://example.com',
            'start' => false,
        ];
        $di = new Di();
        $container = new ModuleContainer($di, []);
        $class = $inspectable ? InspectableWebDriver::class : WebDriver::class;
        return new $class($container, array_merge($defaults, $config));
    }
}
