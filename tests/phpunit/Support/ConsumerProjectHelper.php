<?php

declare(strict_types=1);

namespace Tests\PhpUnit\Support;

final class ConsumerProjectHelper
{
    public static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public static function createConsumerProject(): string
    {
        $root = self::packageRoot();
        $dir = sys_get_temp_dir() . '/module-webdriver-consumer-' . bin2hex(random_bytes(8));
        if (!mkdir($dir) && !is_dir($dir)) {
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
                    'url' => $root,
                    'options' => ['symlink' => true],
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
            throw new \RuntimeException('Failed to write consumer composer.json');
        }

        return $dir;
    }

    public static function installDependencies(string $consumerDir): void
    {
        if (extension_loaded('zip')) {
            self::run(
                array_merge(self::composerCommand(), ['update', '--no-interaction', '--no-progress']),
                $consumerDir
            );
        } else {
            self::installDependenciesWithDocker($consumerDir);
        }
        $installedPath = $consumerDir . '/vendor/codeception/module-webdriver';
        $nestedVendor = $installedPath . '/vendor';
        if (is_dir($nestedVendor)) {
            $packageRoot = realpath(self::packageRoot());
            $resolvedInstall = realpath($installedPath);
            $isDevSymlink = is_link($installedPath)
                && $packageRoot !== false
                && $resolvedInstall === $packageRoot;
            $rootVendor = $packageRoot !== false ? realpath($packageRoot . '/vendor') : false;
            $nestedResolved = realpath($nestedVendor);
            if ($isDevSymlink && $rootVendor !== false && $nestedResolved === $rootVendor) {
                // Path-repository dev install may expose the checkout vendor tree via symlink.
            } else {
                self::deleteDirectory($nestedVendor);
                if (is_dir($nestedVendor)) {
                    throw new \RuntimeException('Nested vendor in package must not exist');
                }
            }
        }
    }

    private static function installDependenciesWithDocker(string $consumerDir): void
    {
        $packageRoot = self::packageRoot();
        $phpImage = 'php@sha256:76e563191d1ade120313a8736df24154d21da5155c0756f147c0b01bd19d9087';
        $escapedConsumer = escapeshellarg($consumerDir);
        $escapedRoot = escapeshellarg($packageRoot);
        $docker = trim((string) shell_exec('command -v docker 2>/dev/null'));
        if ($docker === '') {
            $sudo = trim((string) shell_exec('command -v sudo 2>/dev/null'));
            if ($sudo !== '') {
                $docker = 'sudo docker';
            } else {
                throw new \RuntimeException('docker binary not found');
            }
        }
        $command = sprintf(
            '%s run --rm -v %s:/workspace -v %s:%s -w %s %s bash -lc %s',
            $docker,
            $escapedRoot,
            $escapedConsumer,
            $escapedConsumer,
            $escapedConsumer,
            $phpImage,
            escapeshellarg(
                'set -e; export DEBIAN_FRONTEND=noninteractive; apt-get update -qq; '
                . 'apt-get install -y -qq git unzip libzip-dev; docker-php-ext-install zip >/dev/null; '
                . 'curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; '
                . 'git config --global --add safe.directory /workspace; '
                . 'composer update --no-interaction --no-progress'
            )
        );
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException(
                "Docker composer update failed with exit {$exitCode}:\n" . implode("\n", $output)
            );
        }
    }

    public static function assertConsumerAutoloadHasNoTestHelpers(string $consumerDir): void
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

        if (isset($classmap['Tests\\Unit\\Codeception\\Constraints\\TestedWebElement'])) {
            throw new \RuntimeException('Consumer autoload must not map TestedWebElement');
        }
        if (isset($classmap['data'])) {
            throw new \RuntimeException('Consumer autoload must not map data');
        }
        if (isset($psr4['Tests\\Web\\'])) {
            throw new \RuntimeException('Consumer autoload must not map Tests\\Web\\');
        }
    }

    /**
   * @param list<string> $codeceptArgs
   * @return array{exitCode: int, output: string}
   */
    public static function runCodecept(string $consumerDir, string $packageConfig, array $codeceptArgs): array
    {
        $cmd = array_merge(
            [PHP_BINARY, self::codeceptBinary($consumerDir), '-c', $packageConfig, 'build'],
        );
        $buildResult = self::run($cmd, $consumerDir);
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
     * @param list<string> $args
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
        $hasOk = false;
        foreach (explode("\n", $plain) as $line) {
            if (preg_match('/^(Warning|Deprecated):/', $line) === 1) {
                throw new \RuntimeException('codecept output must not contain warnings/deprecations: ' . $line);
            }
            if (preg_match('/^OK \(/', $line) === 1) {
                $hasOk = true;
            }
        }
        if (!$hasOk) {
            throw new \RuntimeException("codecept output missing OK line:\n" . $result['output']);
        }
    }

    public static function removeConsumer(string $consumerDir): void
    {
        if (!str_starts_with($consumerDir, sys_get_temp_dir())) {
            return;
        }
        self::deleteDirectory($consumerDir);
    }

    /**
     * @return list<string>
     */
    public static function composerCommand(): array
    {
        $which = trim((string) shell_exec('command -v composer 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return [PHP_BINARY, $which];
        }

        $phar = sys_get_temp_dir() . '/codeception-module-webdriver-composer.phar';
        if (!is_file($phar)) {
            $installer = file_get_contents('https://getcomposer.org/installer');
            if ($installer === false) {
                throw new \RuntimeException('Failed to download Composer installer');
            }
            $expectedSig = trim((string) file_get_contents('https://composer.github.io/installer.sig'));
            if ($expectedSig === '') {
                throw new \RuntimeException('Failed to download Composer installer signature');
            }
            $tmpInstaller = sys_get_temp_dir() . '/composer-setup-' . bin2hex(random_bytes(4)) . '.php';
            if (file_put_contents($tmpInstaller, $installer) === false) {
                throw new \RuntimeException('Failed to write Composer installer');
            }
            if (hash_file('sha384', $tmpInstaller) !== $expectedSig) {
                unlink($tmpInstaller);
                throw new \RuntimeException('Composer installer signature mismatch');
            }
            $result = self::run(
                [PHP_BINARY, $tmpInstaller, '--install-dir=' . dirname($phar), '--filename=' . basename($phar)],
                sys_get_temp_dir()
            );
            unlink($tmpInstaller);
            if ($result['exitCode'] !== 0 || !is_file($phar)) {
                throw new \RuntimeException('Composer phar install failed: ' . $result['output']);
            }
        }

        return [PHP_BINARY, $phar];
    }

    private static function codeceptBinary(string $projectWithVendor): string
    {
        return $projectWithVendor . '/vendor/bin/codecept';
    }

    /**
     * @param list<string> $command
     * @return array{exitCode: int, output: string}
     */
    public static function run(array $command, string $cwd, array $env = []): array
    {
        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open(
            $command,
            $spec,
            $pipes,
            $cwd,
            $env !== [] ? $env : null
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
