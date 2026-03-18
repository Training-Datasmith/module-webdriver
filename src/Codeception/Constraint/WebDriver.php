<?php

declare(strict_types=1);

namespace Codeception\Constraint;

use Codeception\Exception\ElementNotFound;
use Codeception\Lib\Console\Message;
use Codeception\Util\Locator;

use function count;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;

use function htmlspecialchars_decode;

use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\Comparator\ComparisonFailure;

class WebDriver extends Page
{
    /**
     * @param WebDriverElement[] $nodes
     */
    protected function matches($nodes): bool
    {
        if (count($nodes) === 0) {
            return false;
        }
        if ($this->string === '') {
            return true;
        }

        foreach ($nodes as $node) {
            if (!$node->isDisplayed()) {
                continue;
            }
            if (parent::matches(htmlspecialchars_decode((string) $node->getText(), ENT_QUOTES | ENT_SUBSTITUTE))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param WebDriverElement[] $nodes
     * @param string|array|WebDriverBy $selector
     */
    protected function fail($nodes, $selector, ?ComparisonFailure $comparisonFailure = null): never
    {
        if (count($nodes) === 0) {
            throw new ElementNotFound($selector, 'Element located either by name, CSS or XPath');
        }

        $output = 'Failed asserting that any element by ' . Locator::humanReadableString($selector);
        $output .= ' ' . $this->uriMessage('on page');

        if (count($nodes) < 5) {
            $output .= "\nElements: ";
            $output .= $this->nodesList($nodes);
        } else {
            $message = new Message('[total %s elements]');
            $output .= $message->with(count($nodes));
        }
        $output .= "\ncontains text '" . $this->string . "'";

        throw new ExpectationFailedException(
            $output,
            $comparisonFailure
        );
    }

    /**
     * @param WebDriverElement[] $nodes
     */
    protected function failureDescription($nodes): string
    {
        $desc = '';
        foreach ($nodes as $node) {
            $desc .= parent::failureDescription($node->getText());
        }
        return $desc;
    }

    /**
     * @param WebDriverElement[] $nodes
     */
    protected function nodesList(array $nodes, ?string $contains = null): string
    {
        $output = '';
        foreach ($nodes as $node) {
            if ($contains && !str_contains((string) $node->getText(), $contains)) {
                continue;
            }
            $message = new Message("\n+ <%s> %s");
            $output .= $message->with($node->getTagName(), $node->getText());
        }
        return $output;
    }
}
