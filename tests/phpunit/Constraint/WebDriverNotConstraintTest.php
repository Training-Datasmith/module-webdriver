<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Constraint;

use Codeception\Constraint\WebDriverNot;
use Codeception\Util\Locator;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\FakeWebElement;

final class WebDriverNotConstraintTest extends TestCase
{
    public function testPassesWhenNoDisplayedNodeContainsNeedle(): void
    {
        $constraint = new WebDriverNot('warcraft', '/user');
        $constraint->evaluate([new FakeWebElement('p', 'Hello world'), new FakeWebElement('p', 'Bye world')]);
        $this->addToAssertionCount(1);
    }

    public function testHiddenMatchDoesNotFail(): void
    {
        $constraint = new WebDriverNot('warcraft', '/user');
        $constraint->evaluate([new FakeWebElement('p', 'Bye warcraft', false)]);
        $this->addToAssertionCount(1);
    }

    public function testEmptyNodeListPasses(): void
    {
        $constraint = new WebDriverNot('warcraft', '/user');
        $constraint->evaluate([]);
        $this->addToAssertionCount(1);
    }

    public function testFailureNamesOnlyNodesThatContainNeedle(): void
    {
        $constraint = new WebDriverNot('warcraft', '/user');
        try {
            $constraint->evaluate([new FakeWebElement('p', 'Bye warcraft'), new FakeWebElement('p', 'Bye world')], 'selector');
            $this->fail('expected failure');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString("There was 'selector' element on page /user", $e->getMessage());
            $this->assertStringContainsString('+ <p> Bye warcraft', $e->getMessage());
            $this->assertStringNotContainsString('+ <p> Bye world', $e->getMessage());
        }
    }

    public function testEmptyNeedleFailsWhenAnyNodeExists(): void
    {
        $constraint = new WebDriverNot('', '/user');
        $this->expectException(AssertionFailedError::class);
        $constraint->evaluate([new FakeWebElement('p', 'Bye warcraft')]);
    }
}
