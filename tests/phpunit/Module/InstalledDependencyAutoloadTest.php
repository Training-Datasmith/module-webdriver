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
            $maps = ConsumerProjectHelper::consumerAutoloadMaps($consumerDir);
            $this->assertArrayNotHasKey('Tests\\Unit\\Codeception\\Constraints\\TestedWebElement', $maps['classmap']);
            $this->assertArrayNotHasKey('data', $maps['classmap']);
            $this->assertArrayNotHasKey('Tests\\Web\\', $maps['psr4']);
            $this->assertDirectoryDoesNotExist($consumerDir . '/vendor/codeception/module-webdriver/vendor');
        } finally {
            ConsumerProjectHelper::removeConsumer($consumerDir);
        }
    }

    public function testConsumerUnitSuiteAsInstalledDependency(): void
    {
        $consumerDir = ConsumerProjectHelper::createConsumerProject();
        try {
            ConsumerProjectHelper::installDependencies($consumerDir);
            ConsumerProjectHelper::consumerAutoloadMaps($consumerDir);
            $config = $consumerDir . '/vendor/codeception/module-webdriver/codeception.yml';
            $result = ConsumerProjectHelper::runCodecept($consumerDir, $config, ['run', 'unit']);
            ConsumerProjectHelper::assertStrictCodeceptOutput($result);
            $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $result['output']);
            $this->assertIsString($plain);
            $this->assertMatchesRegularExpression('/^OK \(/m', $plain);
        } finally {
            ConsumerProjectHelper::removeConsumer($consumerDir);
        }
    }

    public function testStandaloneRepoRootUnitSuite(): void
    {
        $result = ConsumerProjectHelper::runStandaloneCodeceptUnit();
        ConsumerProjectHelper::assertStrictCodeceptOutput($result);
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $result['output']);
        $this->assertIsString($plain);
        $this->assertMatchesRegularExpression('/^OK \(/m', $plain);
    }
}
