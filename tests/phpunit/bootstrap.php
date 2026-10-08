<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

date_default_timezone_set('UTC');

$root = dirname(__DIR__, 2);
chdir($root);

\Codeception\Configuration::config($root . '/codeception.yml');

$outputDir = \Codeception\Configuration::outputDir();
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}
$debugDir = $outputDir . 'debug';
if (!is_dir($debugDir)) {
    mkdir($debugDir, 0777, true);
}
@chmod($outputDir, 0777);
@chmod($debugDir, 0777);
