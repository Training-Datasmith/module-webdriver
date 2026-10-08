<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Module;

use Codeception\Test\Descriptor;
use Codeception\Test\Metadata;
use Codeception\Test\TestCaseWrapper;
use InvalidArgumentException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\PhpUnit\Support\ModuleFactory;
use Tests\PhpUnit\Support\WebDriverMocks;

final class ArtifactsTest extends TestCase
{
    public function testSaveScreenshotNullDriver(): void
    {
        $module = ModuleFactory::create(['start' => false]);
        $path = codecept_output_dir() . 'null-shot.png';
        if (file_exists($path)) {
            unlink($path);
        }
        $module->_saveScreenshot($path);
        $this->assertFileDoesNotExist($path);
    }

    public function testMakeHtmlSnapshotWritesSource(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->pageSource = 'SOURCE-MARKER';
        $path = codecept_output_dir() . 'debug/fixed.html';
        try {
            $module->makeHtmlSnapshot('fixed');
            $this->assertFileExists($path);
            $this->assertSame('SOURCE-MARKER', file_get_contents($path));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testFailedRejectsNonSelfDescribing(): void
    {
        $module = ModuleFactory::create(['start' => false]);
        $test = $this->getMockBuilder(\Codeception\TestInterface::class)->getMock();
        $this->expectException(InvalidArgumentException::class);
        $module->_failed($test, new AssertionFailedError());
    }

    public function testFailedStoresReports(): void
    {
        $mocks = new WebDriverMocks($this);
        $module = ModuleFactory::create(['start' => false]);
        $mocks->attachTo($module);
        $mocks->pageSource = 'SOURCE-MARKER';
        $test = new TestCaseWrapper($this);
        $module->_failed($test, new AssertionFailedError());
        $signature = Descriptor::getTestSignatureUnique($test);
        $base = preg_replace('#[^a-zA-Z0-9\x80-\xff]#', '.', $signature);
        $png = codecept_output_dir() . mb_strcut($base, 0, 245, 'utf-8') . '.fail.png';
        $html = codecept_output_dir() . mb_strcut($base, 0, 244, 'utf-8') . '.fail.html';
        $this->assertFileExists($html);
        $this->assertSame('SOURCE-MARKER', file_get_contents($html));
        $reports = $test->getMetadata()->getReports();
        $this->assertSame($png, $reports['png'] ?? null);
        @unlink($png);
        @unlink($html);
    }
}
