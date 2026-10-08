<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Module;

use Codeception\Exception\ElementNotFound;
use Codeception\Exception\MalformedLocatorException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\FakeWebElement;
use Tests\PhpUnit\Support\FieldStubWebDriver;
use Tests\PhpUnit\Support\ModuleFactory;
use Tests\PhpUnit\Support\WebDriverMocks;

final class LocatorsKeysAndInputTest extends TestCase
{
    public function testStrictLocators(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $module->_findElements(['id' => 'user']);
        $this->assertSame('id', $mocks->calls[0][1][0]);
        $this->assertSame('user', $mocks->calls[0][1][1]);
    }

    public function testUnknownStrictLocator(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $this->expectException(MalformedLocatorException::class);
        $module->_findElements(['nope' => 'x']);
    }

    public function testFindClickableQuotesApostrophe(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $page = $mocks->driver;
        $mocks->driver->method('findElements')->willReturnCallback(function (WebDriverBy $by) use (&$mocks) {
            $mocks->calls[] = ['findElements', [$by->getMechanism(), $by->getValue()]];
            return [];
        });
        $module->exposedFindClickable($page, "it's");
        $xpath = $mocks->calls[0][1][1];
        $this->assertStringContainsString("\"it's\"", $xpath);
    }

    public function testTypeSplitsCharacters(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $pressed = [];
        $mocks->keyboard->method('pressKey')->willReturnCallback(function ($key) use (&$pressed) {
            $pressed[] = $key;
        });
        $start = microtime(true);
        $module->type('aé');
        $this->assertSame(['a', 'é'], $pressed);
        $this->assertLessThan(0.5, microtime(true) - $start);
    }

    public function testConvertKeyModifierMapsCtrl(): void
    {
        $module = ModuleFactory::create(['start' => false]);
        $ref = new \ReflectionMethod($module, 'convertKeyModifier');
        $ref->setAccessible(true);
        $this->assertSame([WebDriverKeys::CONTROL, 'a'], $ref->invoke($module, ['ctrl', 'a']));
        $this->assertSame([WebDriverKeys::META, 'z'], $ref->invoke($module, ['meta', 'z']));
    }

    public function testSelectOptionPartialXpathIsEscaped(): void
    {
        $ref = new \ReflectionMethod(\Codeception\Module\WebDriver::class, 'xPathLiteral');
        $ref->setAccessible(true);
        $literal = $ref->invoke(null, "a'b\"c");
        $this->assertSame("concat('a', \"'\", 'b\"c')", $literal);
        $xpath = './/option [contains (., ' . $literal . ')]';
        $this->assertStringContainsString("concat('a', \"'\", 'b\"c')", $xpath);
    }

    public function testAttachFileMissing(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = new FieldStubWebDriver();
        $module->webDriver = $mocks->driver;
        $this->expectException(InvalidArgumentException::class);
        $module->attachFile(['id' => 'file'], 'no-such-file.txt');
    }
}
