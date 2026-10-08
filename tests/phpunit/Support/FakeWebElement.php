<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Support;

use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;

/**
 * Minimal element double for constraint and module tests (same pattern as TestedWebElement).
 */
class FakeWebElement extends RemoteWebElement
{
    /** @param list<FakeWebElement> $children */
    public function __construct(
        private string $tagName = 'p',
        private string $text = '',
        private bool $displayed = true,
        private array $attributes = [],
        private bool $selected = false,
        private array $children = [],
    ) {
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function isDisplayed(): bool
    {
        return $this->displayed;
    }

    public function getAttribute($attribute_name)
    {
        return $this->attributes[$attribute_name] ?? null;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function click(): void
    {
    }

    public function clear(): void
    {
    }

    public function sendKeys($value): void
    {
    }

    public function getLocation(): object
    {
        return new class {
            public function getX(): int
            {
                return 10;
            }

            public function getY(): int
            {
                return 20;
            }
        };
    }

    public function getCoordinates(): object
    {
        return new class {
            public function getAuxiliary(): object
            {
                return new class {
                };
            }
        };
    }

    /**
     * @return WebDriverElement[]
     */
    public function findElements(WebDriverBy $by): array
    {
        return $this->children;
    }

    public function findElement(WebDriverBy $by): WebDriverElement
    {
        if ($this->children === []) {
            throw new \Facebook\WebDriver\Exception\NoSuchElementException('missing');
        }
        return $this->children[0];
    }

    public function submit(): void
    {
    }

    public function setFileDetector(\Facebook\WebDriver\Remote\FileDetector $detector): void
    {
    }
}
