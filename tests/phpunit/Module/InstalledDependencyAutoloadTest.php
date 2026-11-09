<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Module;

use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\ConsumerProjectHelper;

final class InstalledDependencyAutoloadTest extends TestCase
{
    public function testConsumerAutoloadDoesNotExposePackageDevClasses(): void
    {
        $consumerDir = ConsumerProjectHelper::createConsumerProject();
        try {
            ConsumerProjectHelper::installDependencies($consumerDir);
            ConsumerProjectHelper::assertConsumerAutoloadHasNoTestHelpers($consumerDir);
            $this->addToAssertionCount(1);
        } finally {
            ConsumerProjectHelper::removeConsumer($consumerDir);
        }
    }

    public function testConsumerUnitSuiteAsInstalledDependency(): void
    {
        $consumerDir = ConsumerProjectHelper::createConsumerProject();
        try {
            ConsumerProjectHelper::installDependencies($consumerDir);
            ConsumerProjectHelper::assertConsumerAutoloadHasNoTestHelpers($consumerDir);
            $config = $consumerDir . '/vendor/codeception/module-webdriver';
            $result = ConsumerProjectHelper::runCodecept($consumerDir, $config, ['run', 'unit']);
            ConsumerProjectHelper::assertStrictCodeceptOutput($result);
            $this->addToAssertionCount(1);
        } finally {
            ConsumerProjectHelper::removeConsumer($consumerDir);
        }
    }

    public function testStandaloneRepoRootUnitSuite(): void
    {
        $result = ConsumerProjectHelper::runStandaloneCodeceptUnit();
        ConsumerProjectHelper::assertStrictCodeceptOutput($result);
        $this->addToAssertionCount(1);
    }
}
