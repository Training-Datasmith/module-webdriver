<?php

declare(strict_types=1);

$I = new WebGuy($scenario);
$I->wantTo('call friend with steps to ask expert work');
$I->amOnPage('/info');
$john = $I->haveFriend('john', '\Step\RootWatcher');
$john->does(function (\Step\RootWatcher $I) {
    $I->seeInRootPage('Welcome to test app!');
});
$I->seeInCurrentUrl('/info');
