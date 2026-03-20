<?php

declare (strict_types=1);
namespace Codeception\Constraint;

use Codeception\Util\Locator;
use Facebook\Web_Driver\Web_Driver_By;
use Facebook\Web_Driver\Web_Driver_Element;
use function is_string;
use Php_Unit\Framework\Expectation_Failed_Exception;
use Sebastian_Bergmann\Comparator\Comparison_Failure;
class Web_Driver_Not extends Web_Driver
{
    protected function matches($nodes): bool
    {
        return !parent::matches($nodes);
    }
    /**
     * @param WebDriverElement[] $nodes
     * @param string|array|WebDriverBy $selector
     */
    protected function fail($nodes, $selector, ?Comparison_Failure $comparison_failure = null): never
    {
        if (!is_string($selector) || !str_contains($selector, "'")) {
            $selector = Locator::human_readable_string($selector);
        }
        if (!$this->string) {
            throw new Expectation_Failed_Exception("Element {$selector} was found", $comparison_failure);
        }
        $output = "There was {$selector} element";
        $output .= ' ' . $this->uri_message('on page');
        $output .= $this->nodes_list($nodes, $this->string);
        $output .= "\ncontaining '{$this->string}'";
        throw new Expectation_Failed_Exception($output, $comparison_failure);
    }
    public function to_string(): string
    {
        if ($this->string) {
            return 'that contains text "' . $this->string . '"';
        }
        return '';
    }
}