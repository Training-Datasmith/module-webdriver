<?php

declare(strict_types=1);

namespace WebGuy\Steps;

class RootWatcher extends \WebGuy
{
    public function seeInRootPage($selector)
    {
        $I = $this;
        $I->amOnPage('/');
        $I->see($selector);
    }
}
