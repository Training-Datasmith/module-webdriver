<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Support;

use Codeception\Module\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverSearchContext;

class InspectableWebDriver extends WebDriver
{
    public function exposedWdHost(): ?string
    {
        return $this->wdHost;
    }

    public function exposedCapabilities(): mixed
    {
        return $this->capabilities;
    }

    public function exposedConnectionTimeoutMs(): mixed
    {
        return $this->connectionTimeoutInMs;
    }

    public function exposedRequestTimeoutMs(): mixed
    {
        return $this->requestTimeoutInMs;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function exposedSessionSnapshots(): array
    {
        return $this->sessionSnapshots;
    }

    public function exposedGetProxy(): ?array
    {
        return $this->getProxy();
    }

    public function exposedGetLocator($selector): WebDriverBy
    {
        return $this->getLocator($selector);
    }

    /**
     * @param string|array|WebDriverBy $selector
     * @return WebDriverElement[]
     */
    public function exposedMatch(WebDriverSearchContext $page, $selector, bool $throwMalformed = true): array
    {
        return $this->match($page, $selector, $throwMalformed);
    }

    public function exposedFindClickable(WebDriverSearchContext $page, $link): ?WebDriverElement
    {
        return $this->_findClickable($page, $link);
    }

    public function exposedFormatLogEntries(array $logEntries): string
    {
        return $this->formatLogEntries($logEntries);
    }

    public function exposedIsJSError(string $level, string $message): bool
    {
        return $this->isJSError($level, $message);
    }

    public function exposedCookieDomainMatches(array $cookie): bool
    {
        $ref = new \ReflectionMethod(WebDriver::class, 'cookieDomainMatchesConfigUrl');
        $ref->setAccessible(true);
        return $ref->invoke($this, $cookie);
    }
}
