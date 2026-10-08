<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Module;

use Codeception\Exception\ElementNotFound;
use Facebook\WebDriver\WebDriverBy;
use Codeception\Test\Metadata;
use Codeception\Test\TestCaseWrapper;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\FakeWebElement;
use Tests\PhpUnit\Support\ModuleFactory;
use Tests\PhpUnit\Support\WebDriverMocks;

final class AssertionsAndElementsTest extends TestCase
{
    public function testSeeInPageSourceFindsRawMarker(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->pageSource = '<div>Kill &amp; Destroy</div>';
        $mocks->currentUrl = 'http://example.com/page';
        $module->seeInPageSource('Kill &amp; Destroy');
    }

    public function testSeeMissingTextFails(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->mapFindElements(['css selector:body' => [new FakeWebElement('body', 'Kill &amp; Destroy', true)]]);
        $mocks->currentUrl = 'http://example.com/page';
        $this->expectException(AssertionFailedError::class);
        $module->see('Nope');
    }

    public function testJsErrorDetection(): void
    {
        $module = ModuleFactory::create(['browser' => 'chrome', 'start' => false]);
        $this->assertTrue($module->exposedIsJSError('SEVERE', 'boom'));
        $this->assertFalse($module->exposedIsJSError('INFO', 'ignore-me'));
        $this->assertFalse($module->exposedIsJSError('SEVERE', 'ERR_PROXY_CONNECTION_FAILED'));
    }

    public function testGrabTextFromRegexFallback(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->pageSource = '<h1>Information</h1>';
        $this->assertSame('Information', $module->grabTextFrom('#<h1>([^<]+)</h1>#'));
    }

    public function testGrabTextFromRegexMissingThrows(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->pageSource = '<h1>Information</h1>';
        $this->expectException(ElementNotFound::class);
        $module->grabTextFrom('#missing#');
    }

}
