<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Support;

use Codeception\Lib\Di;
use Codeception\Lib\ModuleContainer;
use Facebook\WebDriver\WebDriverElement;

final class FieldStubWebDriver extends InspectableWebDriver
{
    public function __construct(?WebDriverElement $field = null)
    {
        parent::__construct(new ModuleContainer(new Di(), []), [
            'browser' => 'chrome',
            'url' => 'http://example.com',
            'start' => false,
        ]);
        $this->field = $field ?? new FakeWebElement('input');
    }

    private WebDriverElement $field;

    protected function findField($selector): WebDriverElement
    {
        return $this->field;
    }
}
