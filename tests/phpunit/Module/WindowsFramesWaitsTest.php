<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Module;

use Codeception\Exception\ElementNotFound;
use Codeception\Exception\ModuleException;
use Codeception\Exception\TestRuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\PhpUnit\Support\FakeWebElement;
use Tests\PhpUnit\Support\ModuleFactory;
use Tests\PhpUnit\Support\WebDriverMocks;

final class WindowsFramesWaitsTest extends TestCase
{
    public function testWaitGuard(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $this->expectException(TestRuntimeException::class);
        $module->wait(1000);
    }

    public function testCloseLastTabThrows(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->windowHandles = ['only'];
        $mocks->windowHandle = 'only';
        $this->expectException(ModuleException::class);
        $module->closeTab();
    }

    public function testPhantomRejectsTabs(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['browser' => 'phantomjs', 'start' => false]);
        $mocks->attachTo($module);
        $this->expectException(ModuleException::class);
        $module->switchToNextTab();
    }
}
