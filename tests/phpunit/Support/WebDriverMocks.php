<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Support;

use Facebook\WebDriver\Cookie;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverKeyboard;
use Facebook\WebDriver\WebDriverMouse;
use Facebook\WebDriver\WebDriverNavigation;
use Facebook\WebDriver\WebDriverOptions;
use Facebook\WebDriver\WebDriverTargetLocator;
use Facebook\WebDriver\WebDriverTimeouts;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverWindow;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class WebDriverMocks
{
    /** @var list<array{0: string, 1: mixed}> */
    public array $calls = [];

    public MockObject&RemoteWebDriver $driver;

    public MockObject&WebDriverOptions $options;

    public MockObject&WebDriverTimeouts $timeouts;

    public MockObject&WebDriverKeyboard $keyboard;

    public MockObject&WebDriverMouse $mouse;

    public MockObject&WebDriverNavigation $navigation;

    public MockObject&WebDriverTargetLocator $targetLocator;

    public MockObject&WebDriverWindow $window;

    /** @var list<Cookie> */
    public array $cookies = [];

    public string $currentUrl = 'http://example.com/';

    public string $pageSource = '<html><body></body></html>';

    public string $title = '';

    /** @var list<string> */
    public array $windowHandles = ['win-a'];

    public string $windowHandle = 'win-a';

    public function __construct(private TestCase $testCase)
    {
        $this->options = $this->mock(WebDriverOptions::class, true);
        $this->timeouts = $this->mock(WebDriverTimeouts::class, true);
        $this->keyboard = $this->mock(WebDriverKeyboard::class, true);
        $this->mouse = $this->mock(WebDriverMouse::class, true);
        $this->navigation = $this->mock(WebDriverNavigation::class, true);
        $this->targetLocator = $this->mock(WebDriverTargetLocator::class, true);
        $this->window = $this->mock(WebDriverWindow::class, true);
        $this->driver = $this->mock(RemoteWebDriver::class, true);

        $caps = DesiredCapabilities::chrome();

        $this->driver->method('manage')->willReturnCallback(function () {
            $this->calls[] = ['manage', null];
            return $this->options;
        });
        $this->options->method('timeouts')->willReturn($this->timeouts);
        $this->options->method('window')->willReturn($this->window);
        $this->options->method('getCookies')->willReturnCallback(fn () => $this->cookies);
        $this->options->method('addCookie')->willReturnCallback(function ($params) {
            $this->calls[] = ['addCookie', $params];
        });
        $this->options->method('deleteCookieNamed')->willReturnCallback(function ($name) {
            $this->calls[] = ['deleteCookieNamed', $name];
        });
        $this->options->method('deleteAllCookies')->willReturnCallback(function () {
            $this->calls[] = ['deleteAllCookies', null];
        });
        $this->options->method('getAvailableLogTypes')->willReturn([]);
        $this->options->method('getLog')->willReturn([]);

        $this->driver->method('getCurrentURL')->willReturnCallback(fn () => $this->currentUrl);
        $this->driver->method('getPageSource')->willReturnCallback(fn () => $this->pageSource);
        $this->driver->method('getTitle')->willReturnCallback(fn () => $this->title);
        $this->driver->method('get')->willReturnCallback(function ($url) {
            $this->calls[] = ['get', $url];
        });
        $this->driver->method('getKeyboard')->willReturn($this->keyboard);
        $this->driver->method('getMouse')->willReturn($this->mouse);
        $this->driver->method('navigate')->willReturn($this->navigation);
        $this->driver->method('switchTo')->willReturn($this->targetLocator);
        $this->driver->method('getWindowHandle')->willReturnCallback(fn () => $this->windowHandle);
        $this->driver->method('getWindowHandles')->willReturnCallback(fn () => $this->windowHandles);
        $this->driver->method('getCapabilities')->willReturn($caps);
        $this->driver->method('quit')->willReturnCallback(function () {
            $this->calls[] = ['quit', null];
        });
        $this->driver->method('close')->willReturnCallback(function () {
            $this->calls[] = ['close', null];
        });
        $this->driver->method('executeScript')->willReturnCallback(function ($script, $args = []) {
            $this->calls[] = ['executeScript', [$script, $args]];
            return null;
        });
        $this->driver->method('executeAsyncScript')->willReturnCallback(function ($script, $args = []) {
            $this->calls[] = ['executeAsyncScript', [$script, $args]];
            return null;
        });
        $this->driver->method('takeScreenshot')->willReturnCallback(function ($path) {
            $this->calls[] = ['takeScreenshot', $path];
            return true;
        });
        $this->driver->method('findElements')->willReturnCallback(function (WebDriverBy $by) {
            $this->calls[] = ['findElements', [$by->getMechanism(), $by->getValue()]];
            if ($by->getMechanism() === 'css selector' && $by->getValue() === 'body') {
                return [new FakeWebElement('body', '', true)];
            }
            return [];
        });
        $this->driver->method('wait')->willReturnCallback(function ($timeout) {
            $this->calls[] = ['wait', $timeout];
            $wait = $this->mock(WebDriverWait::class, true);
            $wait->method('until')->willReturnCallback(function ($condition, $message = '') {
                $this->calls[] = ['until', [$message, $condition]];
                if (is_callable($condition)) {
                    return $condition($this->driver);
                }
                if (is_object($condition) && method_exists($condition, '__invoke')) {
                    return $condition($this->driver);
                }
                return true;
            });
            return $wait;
        });
    }

    public function attachTo(InspectableWebDriver $module): void
    {
        $module->webDriver = $this->driver;
        $ref = new \ReflectionMethod(InspectableWebDriver::class, 'setBaseElement');
        $ref->setAccessible(true);
        $ref->invoke($module, null);
    }

    public function withBodyText(string $text): void
    {
        $body = new FakeWebElement('body', $text, true);
        $this->driver->method('findElements')->willReturnCallback(function (WebDriverBy $by) use ($body) {
            $this->calls[] = ['findElements', [$by->getMechanism(), $by->getValue()]];
            if ($by->getMechanism() === 'css selector' && $by->getValue() === 'body') {
                return [$body];
            }
            return [];
        });
    }

    /**
     * @param array<string, list<WebDriverElement>> $map mechanism:value => elements
     */
    public function mapFindElements(array $map): void
    {
        $this->driver->method('findElements')->willReturnCallback(function (WebDriverBy $by) use ($map) {
            $key = $by->getMechanism() . ':' . $by->getValue();
            $this->calls[] = ['findElements', [$by->getMechanism(), $by->getValue()]];
            return $map[$key] ?? [];
        });
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T&MockObject
     */
    private function mock(string $class, bool $disableConstructor = false): MockObject
    {
        $builder = $this->testCase->getMockBuilder($class);
        if ($disableConstructor) {
            $builder->disableOriginalConstructor();
        }
        return $builder->getMock();
    }
}
