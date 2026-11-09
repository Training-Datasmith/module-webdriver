<?php

declare(strict_types=1);

namespace Tests\Consumer;

use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\ConsumerProjectHelper;

final class ConsumerWebSuiteTest extends TestCase
{
    /** @var resource|null */
    private static $phpServer = null;

    /** @var resource|null */
    private static $chromedriver = null;

    public function testConsumerWebChromedriverEnv(): void
    {
        $root = ConsumerProjectHelper::packageRoot();
        $consumerDir = ConsumerProjectHelper::createConsumerProject();
        $installedPackage = $consumerDir . '/vendor/codeception/module-webdriver';
        $packageConfig = $installedPackage . '/codeception.yml';
        try {
            ConsumerProjectHelper::installDependencies($consumerDir);
            $maps = ConsumerProjectHelper::consumerAutoloadMaps($consumerDir);
            if (
                isset($maps['classmap']['Tests\\Unit\\Codeception\\Constraints\\TestedWebElement'])
                || isset($maps['classmap']['data'])
                || isset($maps['psr4']['Tests\\Web\\'])
            ) {
                $this->fail('Consumer autoload must not expose package dev helpers');
            }
            self::ensureAppDatabaseDirectory($installedPackage);
            self::startPhpBuiltInServer($installedPackage);
            self::startChromedriver();
            $buildResult = ConsumerProjectHelper::run(
                [PHP_BINARY, $consumerDir . '/vendor/bin/codecept', '-c', $packageConfig, 'build'],
                $consumerDir
            );
            if ($buildResult['exitCode'] !== 0) {
                throw new \RuntimeException('codecept build failed: ' . $buildResult['output']);
            }
            $result = ConsumerProjectHelper::run(
                [
                    PHP_BINARY,
                    $consumerDir . '/vendor/bin/codecept',
                    '-c',
                    $packageConfig,
                    'run',
                    'web',
                    '--grep',
                    'Friend',
                    '--env',
                    'chromedriver',
                ],
                $consumerDir
            );
            ConsumerProjectHelper::assertStrictCodeceptOutput($result);
            $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $result['output']);
            $this->assertIsString($plain);
            $this->assertMatchesRegularExpression('/^OK \(/m', $plain);
        } finally {
            self::stopProcess(self::$chromedriver);
            self::stopProcess(self::$phpServer);
            self::$chromedriver = null;
            self::$phpServer = null;
            ConsumerProjectHelper::removeConsumer($consumerDir);
            self::restoreAppDatabase($root);
        }
    }

    private static function ensureAppDatabaseDirectory(string $packageRoot): void
    {
        $db = $packageRoot . '/tests/data/app/db';
        if (is_file($db)) {
            unlink($db);
        }
        if (!is_dir($db) && !mkdir($db, 0777, true) && !is_dir($db)) {
            throw new \RuntimeException('Failed to create app database directory: ' . $db);
        }
    }

    private static function startPhpBuiltInServer(string $packageRoot): void
    {
        $docroot = $packageRoot . '/tests/data/app';
        $cmd = sprintf(
            '%s -S 127.0.0.1:8000 -t %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($docroot)
        );
        self::$phpServer = self::startProcess($cmd, ConsumerProjectHelper::phpProcessEnvironment());
        self::waitForPort(8000);
    }

    private static function startChromedriver(): void
    {
        $driver = getenv('WEBDRIVER_TEST_CHROMEDRIVER_BINARY');
        if (!is_string($driver) || $driver === '' || !is_executable($driver)) {
            $driver = '/usr/bin/chromedriver';
        }
        if (!is_executable($driver)) {
            throw new \RuntimeException('Chromedriver not found; set WEBDRIVER_TEST_CHROMEDRIVER_BINARY');
        }
        $cmd = sprintf(
            '%s --port=9515 --allowed-ips=127.0.0.1 --allowed-origins=* --url-base=/wd/hub 2>/dev/null',
            escapeshellarg($driver)
        );
        self::$chromedriver = self::startProcess($cmd, ConsumerProjectHelper::phpProcessEnvironment());
        self::waitForPort(9515);
    }

    private static function waitForPort(int $port): void
    {
        $deadline = microtime(true) + 30;
        while (microtime(true) < $deadline) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($fp !== false) {
                fclose($fp);
                return;
            }
        }
        throw new \RuntimeException("Port {$port} did not open");
    }

    /**
     * @param array<string, string> $env
     * @return resource
     */
    private static function startProcess(string $command, array $env = [])
    {
        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($command, $spec, $pipes, null, $env !== [] ? $env : null);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Failed to start: ' . $command);
        }
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return $proc;
    }

    /** @param resource|null $proc */
    private static function stopProcess($proc): void
    {
        if (is_resource($proc)) {
            proc_terminate($proc);
            proc_close($proc);
        }
    }

    private static function restoreAppDatabase(string $packageRoot): void
    {
        if (!is_executable('/usr/bin/git')) {
            return;
        }
        $cmd = ['git', '-C', $packageRoot, 'checkout', '--', 'tests/data/app/db'];
        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $spec, $pipes, $packageRoot);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }
    }
}
