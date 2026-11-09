<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Module;

use Codeception\Lib\Di;
use Codeception\Lib\ModuleContainer;
use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\InspectableWebDriver;

/**
 * @group browser
 */
final class BrowserIntegrationTest extends TestCase
{
    private static ?int $appPort = null;

    private static ?int $driverPort = null;

    /** @var resource|null */
    private static $phpServer = null;

    /** @var resource|null */
    private static $chromedriver = null;

    private static ?InspectableWebDriver $module = null;

    public static function setUpBeforeClass(): void
    {
        self::$appPort = self::freePort();
        self::$driverPort = self::freePort();
        $router = dirname(__DIR__, 2) . '/data/app/index.php';
        $cmd = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            self::$appPort,
            escapeshellarg($router)
        );
        self::$phpServer = self::startProcess($cmd);
        self::waitForPort(self::$appPort);

        $chrome = self::resolveChromeBinary();
        $driver = self::resolveChromedriver();
        $cmdDriver = sprintf(
            '%s --port=%d --allowed-ips=127.0.0.1 --allowed-origins=* --url-base=/wd/hub 2>/dev/null',
            escapeshellarg($driver),
            self::$driverPort
        );
        self::$chromedriver = self::startProcess($cmdDriver);
        self::waitForPort(self::$driverPort);

        $container = new ModuleContainer(new Di(), []);
        self::$module = new InspectableWebDriver($container, [
            'browser' => 'chrome',
            'url' => 'http://127.0.0.1:' . self::$appPort,
            'host' => '127.0.0.1',
            'port' => (string) self::$driverPort,
            'path' => '/wd/hub',
            'wait' => 0,
            'restart' => false,
            'window_size' => '1200x768',
            'capabilities' => [
                'goog:chromeOptions' => [
                    'args' => ['--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage'],
                    'binary' => $chrome,
                ],
            ],
        ]);
        self::$module->_initialize();
        self::$module->_initializeSession();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$module !== null) {
            self::$module->_closeSession();
            self::$module = null;
        }
        self::stopProcess(self::$chromedriver);
        self::stopProcess(self::$phpServer);
    }

    protected function setUp(): void
    {
        if (self::$module === null) {
            $this->fail('Browser harness was not initialized');
        }
    }

    public function testInfoPageVisibleText(): void
    {
        self::$module->amOnPage('/info');
        self::$module->see('Information');
        self::$module->see('Kill & Destroy');
        self::$module->dontSee('Invisible text');
        self::$module->seeInSource('Invisible text');
    }

    public function testClickLinkNavigatesToInfo(): void
    {
        self::$module->amOnPage('/');
        self::$module->click('More info');
        self::$module->seeCurrentUrlEquals('/info');
        self::$module->see('Information');
    }

    public function testExecuteJsReadsDom(): void
    {
        self::$module->amOnPage('/info');
        $title = self::$module->executeJS('return document.querySelector("h1").textContent');
        $this->assertIsString($title);
        $this->assertStringContainsString('Information', $title);
    }

    private static function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            throw new \RuntimeException('Failed to bind free port: ' . $errstr);
        }
        $name = stream_socket_get_name($server, false);
        fclose($server);
        if (!is_string($name)) {
            throw new \RuntimeException('Failed to read bound socket name');
        }
        $port = (int) substr($name, strrpos($name, ':') + 1);
        if ($port <= 0) {
            throw new \RuntimeException('Failed to parse free port from ' . $name);
        }

        return $port;
    }

    private static function waitForPort(int $port): void
    {
        $deadline = microtime(true) + 15;
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

    private static function resolveChromeBinary(): string
    {
        $fromEnv = getenv('WEBDRIVER_TEST_CHROME_BINARY');
        if (is_string($fromEnv) && $fromEnv !== '' && is_executable($fromEnv)) {
            return $fromEnv;
        }
        foreach (['/usr/bin/google-chrome', '/usr/local/bin/google-chrome'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        throw new \RuntimeException('Chrome binary not found; set WEBDRIVER_TEST_CHROME_BINARY');
    }

    private static function resolveChromedriver(): string
    {
        $fromEnv = getenv('WEBDRIVER_TEST_CHROMEDRIVER_BINARY');
        if (is_string($fromEnv) && $fromEnv !== '' && is_executable($fromEnv)) {
            return $fromEnv;
        }
        foreach (['/usr/bin/chromedriver', '/usr/local/bin/chromedriver'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        throw new \RuntimeException('Chromedriver not found; set WEBDRIVER_TEST_CHROMEDRIVER_BINARY');
    }
}
