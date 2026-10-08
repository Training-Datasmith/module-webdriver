<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Module;

use Codeception\Exception\ModuleException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\ModuleFactory;
use Tests\PhpUnit\Support\WebDriverMocks;

final class NavigationUrlTest extends TestCase
{
    public function testAmOnPageJoinsPath(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['url' => 'http://example.com', 'start' => false]);
        $mocks->attachTo($module);
        $module->amOnPage('/info');
        $this->assertSame(['get', 'http://example.com/info'], $mocks->calls[0]);

        $mocks2 = new WebDriverMocks($this);
        $module2 = ModuleFactory::create(['url' => 'http://example.com/app/', 'start' => false]);
        $mocks2->attachTo($module2);
        $module2->amOnPage('info');
        $this->assertSame(['get', 'http://example.com/app/info'], $mocks2->calls[0]);
    }

    public function testAmOnPageStripsBaseQuery(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['url' => 'http://example.com/app?x=1', 'start' => false]);
        $mocks->attachTo($module);
        $module->amOnPage('/info');
        $this->assertSame(['get', 'http://example.com/app/info'], $mocks->calls[0]);
    }

    public function testAmOnUrlSetsHostAndNavigates(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $module->amOnUrl('http://shop.example.com:8080/item/2');
        $this->assertSame(['get', 'http://shop.example.com:8080/item/2'], $mocks->calls[0]);
        $this->assertSame('http://shop.example.com:8080', $module->_getUrl());
    }

    public function testAmOnSubdomainInsertsLabel(): void
    {
        $module = ModuleFactory::create(['url' => 'http://example.com', 'start' => false]);
        $module->amOnSubdomain('app');
        $this->assertSame('http://app.example.com', $module->_getUrl());

        $module2 = ModuleFactory::create(['url' => 'https://www.example.com/path', 'start' => false]);
        $module2->amOnSubdomain('api');
        $this->assertSame('https://api.example.com/path', $module2->_getUrl());
    }

    public function testCurrentUriRejectsBlankPages(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->currentUrl = 'about:blank';
        $this->expectException(ModuleException::class);
        $module->_getCurrentUri();
    }

    public function testCurrentUriReturnsPathQueryFragment(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->currentUrl = 'http://example.com/user/15?x=1#y';
        $this->assertSame('/user/15?x=1#y', $module->_getCurrentUri());
    }

    public function testUrlAssertionsUseRelativeUri(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->currentUrl = 'http://example.com/user/15';
        $module->seeCurrentUrlEquals('/user/15');
        $this->expectException(AssertionFailedError::class);
        $module->seeCurrentUrlEquals('http://example.com/user/15');
    }

    public function testGrabFromCurrentUrl(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->currentUrl = 'http://example.com/user/15';
        $this->assertSame('/user/15', $module->grabFromCurrentUrl());
        $this->assertSame('15', $module->grabFromCurrentUrl('~/user/(\d+)~'));
    }
}
