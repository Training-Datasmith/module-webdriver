<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Module;

use Codeception\Exception\ConnectionException;
use Codeception\Exception\ModuleConfigException;
use Codeception\Lib\Interfaces\ConflictsWithModule;
use Codeception\Lib\Interfaces\ElementLocator;
use Codeception\Lib\Interfaces\MultiSession;
use Codeception\Lib\Interfaces\PageSourceSaver;
use Codeception\Lib\Interfaces\Remote;
use Codeception\Lib\Interfaces\RequiresPackage;
use Codeception\Lib\Interfaces\ScreenshotSaver;
use Codeception\Lib\Interfaces\SessionSnapshot;
use Codeception\Lib\Interfaces\Web;
use Codeception\Module\WebDriver;
use Codeception\Test\Metadata;
use Codeception\Test\TestCaseWrapper;
use Facebook\WebDriver\Exception\Internal\UnexpectedResponseException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverDimension;
use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\InspectableWebDriver;
use Tests\PhpUnit\Support\ModuleFactory;
use Tests\PhpUnit\Support\WebDriverMocks;

final class ConfigAndSessionTest extends TestCase
{
    public function testRequiredFieldsRejectMissingUrl(): void
    {
        $this->expectException(ModuleConfigException::class);
        $di = new \Codeception\Lib\Di();
        $container = new \Codeception\Lib\ModuleContainer($di, []);
        new InspectableWebDriver($container, ['browser' => 'chrome']);
    }

    public function testConflictsWithWebInterface(): void
    {
        $module = ModuleFactory::create();
        $this->assertSame(Web::class, $module->_conflicts());
    }

    public function testRequiresRemoteWebDriverClass(): void
    {
        $module = ModuleFactory::create();
        $this->assertArrayHasKey(RemoteWebDriver::class, $module->_requires());
    }

    public function testImplementsModuleInterfaces(): void
    {
        $interfaces = [
            Web::class,
            Remote::class,
            MultiSession::class,
            SessionSnapshot::class,
            ScreenshotSaver::class,
            PageSourceSaver::class,
            ElementLocator::class,
            ConflictsWithModule::class,
            RequiresPackage::class,
        ];
        foreach ($interfaces as $interface) {
            $this->assertInstanceOf($interface, ModuleFactory::create());
        }
    }

    public function testDefaultHostAndBrowserCapability(): void
    {
        $module = ModuleFactory::create();
        $module->_initialize();
        $this->assertSame('http://127.0.0.1:4444/wd/hub', $module->exposedWdHost());
        $this->assertSame('chrome', $module->exposedCapabilities()['browserName']);
    }

    public function testCustomEndpoint(): void
    {
        $module = ModuleFactory::create([
            'protocol' => 'https',
            'host' => 'example.test',
            'port' => '9515',
            'path' => '',
        ]);
        $module->_initialize();
        $this->assertSame('https://example.test:9515', $module->exposedWdHost());
    }

    public function testTimeoutsAreScaledFromSeconds(): void
    {
        $module = ModuleFactory::create(['connection_timeout' => 2, 'request_timeout' => 3]);
        $module->_initialize();
        $this->assertSame(2000, $module->exposedConnectionTimeoutMs());
        $this->assertSame(3000, $module->exposedRequestTimeoutMs());
    }

    public function testManualProxyCapabilities(): void
    {
        $module = ModuleFactory::create([
            'http_proxy' => '127.0.0.1',
            'http_proxy_port' => '8888',
            'ssl_proxy' => '127.0.0.1',
            'ssl_proxy_port' => '8889',
        ]);
        $module->_initialize();
        $proxy = $module->exposedGetProxy();
        $this->assertSame('manual', $proxy['proxyType']);
        $this->assertSame('127.0.0.1:8888', $proxy['httpProxy']);
        $this->assertSame('127.0.0.1:8889', $proxy['sslProxy']);
    }

    public function testProxyOmittedWhenUnset(): void
    {
        $module = ModuleFactory::create();
        $module->_initialize();
        $this->assertArrayNotHasKey('proxy', $module->exposedCapabilities());
    }

    public function testCapabilitiesClosureReplacesMap(): void
    {
        $module = ModuleFactory::create();
        $module->_initialize();
        $module->_capabilities(fn ($c) => array_merge($c, ['name' => 'login']));
        $this->assertSame('login', $module->exposedCapabilities()['name']);
    }

    public function testFirefoxProfileMissingFile(): void
    {
        $module = ModuleFactory::create(['capabilities' => ['firefox_profile' => '/no/such/profile.zip']]);
        $this->expectException(ModuleConfigException::class);
        $module->_initialize();
    }

    public function testFirefoxProfileLoadsBytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ffprof');
        file_put_contents($path, 'profile-bytes');
        $module = ModuleFactory::create(['capabilities' => ['firefox_profile' => $path]]);
        $module->_initialize();
        $this->assertSame('profile-bytes', $module->exposedCapabilities()['firefox_profile']);
        unlink($path);
    }

    public function testWindowSizeString(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['window_size' => '800x600', 'start' => false]);
        $module->_initialize();
        $mocks->attachTo($module);
        $mocks->window->expects($this->once())->method('setSize')->with($this->callback(
            fn (WebDriverDimension $d) => $d->getWidth() === 800 && $d->getHeight() === 600
        ));
        $ref = new \ReflectionMethod(WebDriver::class, 'initialWindowSize');
        $ref->setAccessible(true);
        $ref->invoke($module);
    }

    public function testWindowSizeMaximize(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['window_size' => 'maximize', 'start' => false]);
        $module->_initialize();
        $mocks->attachTo($module);
        $mocks->window->expects($this->once())->method('maximize');
        $mocks->window->expects($this->never())->method('setSize');
        $ref = new \ReflectionMethod(WebDriver::class, 'initialWindowSize');
        $ref->setAccessible(true);
        $ref->invoke($module);
    }

    public function testBeforeRecordsBrowserMetadata(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $test = new TestCaseWrapper($this);
        $module->_before($test);
        $this->assertSame('chrome', $test->getMetadata()->getCurrent()['browser']);
    }

    public function testAfterRestartQuitsSessions(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['restart' => true, 'start' => false]);
        $mocks->attachTo($module);
        $sessions = new \ReflectionProperty(WebDriver::class, 'sessions');
        $sessions->setAccessible(true);
        $sessions->setValue($module, [$mocks->driver]);
        $module->_after($this->createStub(\Codeception\TestInterface::class));
        $this->assertNull($module->webDriver);
        $this->assertContains(['quit', null], $mocks->calls);
    }

    public function testAfterClearsCookies(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['restart' => false, 'clear_cookies' => true, 'start' => false]);
        $mocks->attachTo($module);
        $module->_after($this->createStub(\Codeception\TestInterface::class));
        $this->assertContains(['deleteAllCookies', null], $mocks->calls);
    }

    public function testCloseSessionNoopWhenEmpty(): void
    {
        $module = ModuleFactory::create();
        $module->_closeSession();
        $this->addToAssertionCount(1);
    }

    public function testBackupAndLoadSession(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $backup = $module->_backupSession();
        $other = $mocks->driver;
        $module->_loadSession($other);
        $this->assertSame($other, $module->webDriver);
        $this->assertSame($mocks->driver, $backup);
    }
}
