<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Module;

use Codeception\Coverage\Subscriber\LocalServer;
use Facebook\WebDriver\Cookie;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\ModuleFactory;
use Tests\PhpUnit\Support\WebDriverMocks;

final class CookiesAndSnapshotsTest extends TestCase
{
    public function testGrabCookieFiltersByDomain(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->cookies = [
            Cookie::createFromArray(['name' => 'a', 'value' => 'one', 'domain' => 'example.com']),
            Cookie::createFromArray(['name' => 'a', 'value' => 'two', 'domain' => 'other.test']),
        ];
        $this->assertSame('one', $module->grabCookie('a', ['domain' => 'example.com']));
        $this->assertNull($module->grabCookie('missing'));
    }

    public function testSetCookieCopiesExpiresAndKeepsExplicitFlags(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $module->setCookie('tok', 'v', ['expires' => 1893456000, 'secure' => true, 'httpOnly' => true, 'path' => '/app'], false);
        $added = null;
        foreach ($mocks->calls as $call) {
            if ($call[0] === 'addCookie') {
                $added = $call[1];
            }
        }
        $this->assertIsArray($added);
        $this->assertSame(1893456000, $added['expiry']);
        $this->assertTrue($added['secure']);
        $this->assertTrue($added['httpOnly']);
        $this->assertSame('/app', $added['path']);
    }

    public function testSnapshotKeepsMatchingDomainsOnly(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['url' => 'http://example.com', 'start' => false]);
        $mocks->attachTo($module);
        $mocks->cookies = [
            Cookie::createFromArray(['name' => 'session', 'value' => '1', 'domain' => 'example.com']),
            Cookie::createFromArray(['name' => 'dotted', 'value' => '1', 'domain' => '.example.com']),
            Cookie::createFromArray(['name' => 'empty-domain', 'value' => '1']),
            Cookie::createFromArray(['name' => 'foreign', 'value' => '1', 'domain' => 'other.test']),
            Cookie::createFromArray(['name' => 'too-specific', 'value' => '1', 'domain' => 'www.example.com']),
        ];
        $module->saveSessionSnapshot('s');
        $names = array_map(fn ($c) => $c['name'], $module->exposedSessionSnapshots()['s']);
        $this->assertContains('session', $names);
        $this->assertContains('dotted', $names);
        $this->assertContains('empty-domain', $names);
        $this->assertNotContains('foreign', $names);
        $this->assertNotContains('too-specific', $names);
    }

    public function testSnapshotMatchesSubdomainIgnoringCase(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['url' => 'http://Sub.Example.com', 'start' => false]);
        $mocks->attachTo($module);
        $mocks->cookies = [Cookie::createFromArray(['name' => 'x', 'value' => '1', 'domain' => 'example.com'])];
        $module->saveSessionSnapshot('s');
        $this->assertNotEmpty($module->exposedSessionSnapshots()['s']);
    }

    public function testSnapshotSkipsCoverageCookies(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['url' => 'http://example.com', 'start' => false]);
        $mocks->attachTo($module);
        $mocks->cookies = [
            Cookie::createFromArray(['name' => LocalServer::COVERAGE_COOKIE, 'value' => 'x', 'domain' => 'example.com']),
            Cookie::createFromArray(['name' => 'normal', 'value' => '1', 'domain' => 'example.com']),
        ];
        $module->saveSessionSnapshot('s');
        $names = array_map(fn ($c) => $c['name'], $module->exposedSessionSnapshots()['s']);
        $this->assertNotContains(LocalServer::COVERAGE_COOKIE, $names);
        $this->assertContains('normal', $names);
    }

    public function testLoadMissingSnapshotReturnsFalse(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $this->assertFalse($module->loadSessionSnapshot('absent'));
    }
}
