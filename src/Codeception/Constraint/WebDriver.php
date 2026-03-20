<?php

declare (strict_types=1);
namespace Codeception\Constraint;

use Codeception\Exception\Element_Not_Found;
use Codeception\Lib\Console\Message;
use Codeception\Util\Locator;
use function count;
use Facebook\Web_Driver\Web_Driver_By;
use Facebook\Web_Driver\Web_Driver_Element;
use function htmlspecialchars_decode;
use Php_Unit\Framework\Expectation_Failed_Exception;
use Sebastian_Bergmann\Comparator\Comparison_Failure;
class Web_Driver extends Page
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
            if (!$node->is_displayed()) {
                continue;
            }
            if (parent::matches(htmlspecialchars_decode((string) $node->get_text(), ENT_QUOTES | ENT_SUBSTITUTE))) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param WebDriverElement[] $nodes
     * @param string|array|WebDriverBy $selector
     */
    protected function fail($nodes, $selector, ?Comparison_Failure $comparison_failure = null): never
    {
        if (count($nodes) === 0) {
            throw new Element_Not_Found($selector, 'Element located either by name, CSS or XPath');
        }
        $output = 'Failed asserting that any element by ' . Locator::human_readable_string($selector);
        $output .= ' ' . $this->uri_message('on page');
        if (count($nodes) < 5) {
            $output .= "\nElements: ";
            $output .= $this->nodes_list($nodes);
        } else {
            $message = new Message('[total %s elements]');
            $output .= $message->with(count($nodes));
        }
        $output .= "\ncontains text '" . $this->string . "'";
        throw new Expectation_Failed_Exception($output, $comparison_failure);
    }
    /**
     * @param WebDriverElement[] $nodes
     */
    protected function failure_description($nodes): string
    {
        $desc = '';
        foreach ($nodes as $node) {
            $desc .= parent::failure_description($node->get_text());
        }
        return $desc;
    }
    /**
     * @param WebDriverElement[] $nodes
     */
    protected function nodes_list(array $nodes, ?string $contains = null): string
    {
        $output = '';
        foreach ($nodes as $node) {
            if ($contains && !str_contains((string) $node->get_text(), $contains)) {
                continue;
            }
            $message = new Message("\n+ <%s> %s");
            $output .= $message->with($node->get_tag_name(), $node->get_text());
        }
        return $output;
    }
}