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
        $packageConfig = $consumerDir . '/vendor/codeception/module-webdriver';
        try {
            ConsumerProjectHelper::installDependencies($consumerDir);
            ConsumerProjectHelper::assertConsumerAutoloadHasNoTestHelpers($consumerDir);
            self::startPhpBuiltInServer($root);
            self::startChromedriver();
            ConsumerProjectHelper::run(
                [PHP_BINARY, $consumerDir . '/vendor/bin/codecept', '-c', $packageConfig, 'build'],
                $consumerDir
            );
            $result = ConsumerProjectHelper::run(
                [
                    PHP_BINARY,
                    $consumerDir . '/vendor/bin/codecept',
                    '-c',
                    $packageConfig,
                    'run',
                    'web',
                    '--env',
                    'chromedriver',
                ],
                $consumerDir
            );
            ConsumerProjectHelper::assertStrictCodeceptOutput($result);
        } finally {
            self::stopProcess(self::$chromedriver);
            self::stopProcess(self::$phpServer);
            self::$chromedriver = null;
            self::$phpServer = null;
            ConsumerProjectHelper::removeConsumer($consumerDir);
            self::restoreAppDatabase($root);
        }
    }

    private static function startPhpBuiltInServer(string $packageRoot): void
    {
        $router = $packageRoot . '/tests/data/app/index.php';
        $cmd = sprintf(
            '%s -S 127.0.0.1:8000 %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($router)
        );
        self::$phpServer = self::startProcess($cmd);
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
        self::$chromedriver = self::startProcess($cmd);
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

    /** @return resource */
    private static function startProcess(string $command)
    {
        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($command, $spec, $pipes);
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
