<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

date_default_timezone_set('UTC');

$root = dirname(__DIR__, 2);
chdir($root);

\Codeception\Configuration::config($root . '/codeception.yml');
