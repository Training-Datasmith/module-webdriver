<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Support;

final class ConsumerProjectHelper
{
    private const PACKAGE_EXCLUDE = [
        'vendor',
        '.git',
        '.phpunit.cache',
        'tests/output',
        '.ci-php-runtime',
        '.ci-browsers',
    ];

    public static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public static function createConsumerProject(): string
    {
        $stagingRoot = self::createPackageStagingCopy();
        $dir = sys_get_temp_dir() . '/module-webdriver-consumer-' . bin2hex(random_bytes(8));
        if (!mkdir($dir) && !is_dir($dir)) {
            self::deleteDirectory($stagingRoot);
            throw new \RuntimeException('Failed to create consumer directory: ' . $dir);
        }

        $composer = [
            'name' => 'example/module-webdriver-consumer',
            'require' => [
                'codeception/module-webdriver' => '@dev',
            ],
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => $stagingRoot,
                    'options' => ['symlink' => false],
                ],
            ],
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
            'config' => [
                'classmap-authoritative' => true,
                'allow-plugins' => [
                    'codeception/c3' => true,
                ],
            ],
        ];

        $written = file_put_contents(
            $dir . '/composer.json',
            json_encode($composer, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        if ($written === false) {
            self::deleteDirectory($stagingRoot);
            throw new \RuntimeException('Failed to write consumer composer.json');
        }

        return $dir;
    }

    public static function installDependencies(string $consumerDir): void
    {
        if (!extension_loaded('zip')) {
            throw new \RuntimeException('ext-zip is required for consumer composer tests');
        }
        $result = self::run(
            array_merge(self::composerCommand(), ['update', '--no-interaction', '--no-progress']),
            $consumerDir
        );
        if ($result['exitCode'] !== 0) {
            throw new \RuntimeException('composer update failed: ' . $result['output']);
        }
        if (is_dir($consumerDir . '/vendor/codeception/module-webdriver/vendor')) {
            throw new \RuntimeException('Nested vendor in package must not exist');
        }
        self::restoreComposerIgnoredPackageDevFiles($consumerDir);
    }

    /**
     * Composer export-ignore omits tests and *.yml from path installs; copy them from the path source for consumer suites.
     */
    private static function restoreComposerIgnoredPackageDevFiles(string $consumerDir): void
    {
        /** @var array<string, mixed> $composer */
        $composer = json_decode(
            (string) file_get_contents($consumerDir . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $staging = null;
        foreach ($composer['repositories'] ?? [] as $repository) {
            if (($repository['type'] ?? '') === 'path' && isset($repository['url']) && is_string($repository['url'])) {
                $staging = $repository['url'];
                break;
            }
        }
        if ($staging === null || !is_dir($staging)) {
            throw new \RuntimeException('Path repository staging directory is missing');
        }
        $installed = $consumerDir . '/vendor/codeception/module-webdriver';
        if (!is_dir($installed)) {
            throw new \RuntimeException('Installed package directory is missing');
        }
        $pairs = [
            $staging . '/codeception.yml' => $installed . '/codeception.yml',
            $staging . '/tests' => $installed . '/tests',
        ];
        foreach ($pairs as $from => $to) {
            if (!is_file($from) && !is_dir($from)) {
                throw new \RuntimeException('Missing package dev path: ' . $from);
            }
            if (is_dir($from)) {
                self::mirrorDirectory($from, $to, $from);
            } elseif (!copy($from, $to)) {
                throw new \RuntimeException('Failed to copy: ' . $from);
            }
        }
    }

    /**
     * @return array{classmap: array<string, string>, psr4: array<string, list<string>>}
     */
    public static function consumerAutoloadMaps(string $consumerDir): array
    {
        $classmapFile = $consumerDir . '/vendor/composer/autoload_classmap.php';
        $psr4File = $consumerDir . '/vendor/composer/autoload_psr4.php';
        if (!is_file($classmapFile) || !is_file($psr4File)) {
            throw new \RuntimeException('Consumer autoload files missing');
        }

        /** @var array<string, string> $classmap */
        $classmap = require $classmapFile;
        /** @var array<string, list<string>> $psr4 */
        $psr4 = require $psr4File;

        return ['classmap' => $classmap, 'psr4' => $psr4];
    }

    /**
     * @param list<string> $codeceptArgs
     * @return array{exitCode: int, output: string}
     */
    public static function runCodecept(string $consumerDir, string $packageConfig, array $codeceptArgs): array
    {
        $buildResult = self::run(
            [PHP_BINARY, self::codeceptBinary($consumerDir), '-c', $packageConfig, 'build'],
            $consumerDir
        );
        if ($buildResult['exitCode'] !== 0) {
            throw new \RuntimeException('codecept build failed: ' . $buildResult['output']);
        }

        $runCmd = array_merge(
            [PHP_BINARY, self::codeceptBinary($consumerDir), '-c', $packageConfig],
            $codeceptArgs
        );

        return self::run($runCmd, $consumerDir);
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    public static function runStandaloneCodeceptUnit(): array
    {
        $root = self::packageRoot();
        $buildResult = self::run([PHP_BINARY, self::codeceptBinary($root), 'build'], $root);
        if ($buildResult['exitCode'] !== 0) {
            throw new \RuntimeException('codecept build failed: ' . $buildResult['output']);
        }

        return self::run(
            [PHP_BINARY, self::codeceptBinary($root), 'run', 'unit'],
            $root
        );
    }

  /**
   * @param array{exitCode: int, output: string} $result
   */
    public static function assertStrictCodeceptOutput(array $result): void
    {
        if ($result['exitCode'] !== 0) {
            throw new \RuntimeException(
                "codecept failed with exit {$result['exitCode']}:\n" . $result['output']
            );
        }
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $result['output']);
        if (!is_string($plain)) {
            throw new \RuntimeException('Failed to normalize codecept output');
        }
        foreach (explode("\n", $plain) as $line) {
            if (preg_match('/^(Warning|Deprecated):/', $line) === 1) {
                throw new \RuntimeException('codecept output must not contain warnings/deprecations: ' . $line);
            }
        }
        if (preg_match('/^OK \(/m', $plain) !== 1) {
            throw new \RuntimeException("codecept output missing OK line:\n" . $result['output']);
        }
    }

    public static function removeConsumer(string $consumerDir): void
    {
        if (!str_starts_with($consumerDir, sys_get_temp_dir())) {
            return;
        }
        $composerJson = $consumerDir . '/composer.json';
        if (is_file($composerJson)) {
            $decoded = json_decode((string) file_get_contents($composerJson), true);
            if (is_array($decoded)) {
                foreach ($decoded['repositories'] ?? [] as $repository) {
                    if (!is_array($repository)) {
                        continue;
                    }
                    $url = $repository['url'] ?? '';
                    if (
                        is_string($url)
                        && str_starts_with($url, sys_get_temp_dir())
                        && str_contains($url, 'module-webdriver-staging-')
                    ) {
                        self::deleteDirectory($url);
                    }
                }
            }
        }
        self::deleteDirectory($consumerDir);
    }

    /**
     * @return list<string>
     */
    public static function composerCommand(): array
    {
        $composerBin = getenv('GATE_COMPOSER_BIN');
        if (is_string($composerBin) && $composerBin !== '' && is_executable($composerBin)) {
            return [PHP_BINARY, $composerBin];
        }

        $which = trim((string) shell_exec('command -v composer 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return [PHP_BINARY, $which];
        }

        throw new \RuntimeException(
            'Composer binary not found; set GATE_COMPOSER_BIN or install composer on the runner'
        );
    }

    private static function createPackageStagingCopy(): string
    {
        $root = self::packageRoot();
        $staging = sys_get_temp_dir() . '/module-webdriver-staging-' . bin2hex(random_bytes(8));
        if (!mkdir($staging) && !is_dir($staging)) {
            throw new \RuntimeException('Failed to create staging directory: ' . $staging);
        }
        self::mirrorDirectory($root, $staging, $root);
        return $staging;
    }

    private static function mirrorDirectory(string $source, string $destination, string $packageRoot): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($packageRoot) + 1);
            if ($relative === false || $relative === '') {
                continue;
            }
            foreach (self::PACKAGE_EXCLUDE as $exclude) {
                if ($relative === $exclude || str_starts_with($relative, $exclude . '/')) {
                    continue 2;
                }
            }
            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
                    throw new \RuntimeException('Failed to create directory: ' . $target);
                }
            } else {
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                    throw new \RuntimeException('Failed to create directory: ' . $parent);
                }
                if (!copy($item->getPathname(), $target)) {
                    throw new \RuntimeException('Failed to copy: ' . $item->getPathname());
                }
            }
        }
    }

    private static function codeceptBinary(string $projectWithVendor): string
    {
        return $projectWithVendor . '/vendor/bin/codecept';
    }

    /**
     * @param list<string> $command
     * @return array{exitCode: int, output: string}
     */
    /**
     * @return array<string, string>
     */
    public static function phpProcessEnvironment(): array
    {
        $path = getenv('PATH');
        if (!is_string($path) || $path === '') {
            $path = '/usr/bin:/bin';
        }
        $home = sys_get_temp_dir() . '/module-webdriver-gate-home';
        if (!is_dir($home) && !mkdir($home, 0777, true) && !is_dir($home)) {
            throw new \RuntimeException('Failed to create gate HOME directory: ' . $home);
        }
        $env = [
            'LD_LIBRARY_PATH' => '/opt/php/lib/php-gate-deps',
            'TZ' => 'UTC',
            'PHP_INI_SCAN_DIR' => '/opt/php/etc/conf.d',
            'PATH' => '/opt/php/bin:' . $path,
            'HOME' => $home,
            'COMPOSER_HOME' => $home . '/composer',
        ];
        $composer = getenv('GATE_COMPOSER_BIN');
        if (is_string($composer) && $composer !== '') {
            $env['GATE_COMPOSER_BIN'] = $composer;
        }

        return $env;
    }

    public static function run(array $command, string $cwd, array $env = []): array
    {
        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $mergedEnv = array_merge(self::phpProcessEnvironment(), $env);
        $proc = proc_open(
            $command,
            $spec,
            $pipes,
            $cwd,
            $mergedEnv
        );
        if (!is_resource($proc)) {
            throw new \RuntimeException('Failed to start: ' . implode(' ', $command));
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        return [
            'exitCode' => $exitCode,
            'output' => (string) $stdout . (string) $stderr,
        ];
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
