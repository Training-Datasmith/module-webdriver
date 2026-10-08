<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Constraint;

use Codeception\Constraint\WebDriver;
use Codeception\Exception\ElementNotFound;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\FakeWebElement;

final class WebDriverConstraintTest extends TestCase
{
    public function testDisplayedTextMatchesAfterEntityDecode(): void
    {
        $constraint = new WebDriver('Kill & Destroy', '/info');
        $constraint->evaluate([new FakeWebElement('p', 'Kill &amp; Destroy', true)]);
        $this->addToAssertionCount(1);
    }

    public function testHiddenNodeIsIgnored(): void
    {
        $constraint = new WebDriver('hello', '/info');
        $constraint->evaluate([new FakeWebElement('p', 'hello', false), new FakeWebElement('p', 'hello', true)]);
        $this->addToAssertionCount(1);
    }

    public function testZeroNodesThrowElementNotFound(): void
    {
        $constraint = new WebDriver('hello', '/info');
        $this->expectException(ElementNotFound::class);
        $constraint->evaluate([], 'selector');
    }

    public function testFailureListsUpToFourElements(): void
    {
        $constraint = new WebDriver('hello', '/user');
        try {
            $constraint->evaluate(
                [new FakeWebElement('p', 'Bye warcraft'), new FakeWebElement('p', 'Bye world')],
                'selector'
            );
            $this->fail('expected failure');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString("Failed asserting that any element by 'selector' on page /user", $e->getMessage());
            $this->assertStringContainsString('+ <p> Bye world', $e->getMessage());
        }
    }

    public function testFailureSummarizesFiveOrMore(): void
    {
        $constraint = new WebDriver('hello', '/user');
        $nodes = [];
        for ($i = 0; $i < 15; $i++) {
            $nodes[] = new FakeWebElement('p', "item $i");
        }
        try {
            $constraint->evaluate($nodes, 'selector');
            $this->fail('expected failure');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('[total 15 elements]', $e->getMessage());
            $this->assertStringNotContainsString('+ <p> item 0', $e->getMessage());
        }
    }

    public function testOmittedUriOmitsPageClause(): void
    {
        $constraint = new WebDriver('hello');
        try {
            $constraint->evaluate([new FakeWebElement('p', 'Bye world')], 'selector');
            $this->fail('expected failure');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString("Failed asserting that any element by 'selector'", $e->getMessage());
            $this->assertStringNotContainsString('on page', $e->getMessage());
        }
    }
}
