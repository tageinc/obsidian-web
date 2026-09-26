<?php

namespace Obsidian\Scripts;

use Closure;
use RuntimeException;
use Throwable;

final class FullCheckRunner
{
    private const RESTRICTED_ROOT_PATTERN = '[Dd][Ee][Vv][Oo][Pp][Ss][Oo][Bb][Ss][Ii][Dd][Ii][Aa][Nn].[Tt][Xx][Tt]';
    private const RESTRICTED_RECURSIVE_PATTERN = '**/'.self::RESTRICTED_ROOT_PATTERN;

    private string $root;
    private Closure $execute;
    private array $resolvedTools = [];

    public function __construct(string $root, ?callable $execute = null)
    {
        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new RuntimeException('Repository root does not exist: '.$root);
        }

        $this->root = rtrim($resolvedRoot, DIRECTORY_SEPARATOR);
        $this->execute = $execute === null
            ? Closure::fromCallable([$this, 'executeCommand'])
            : Closure::fromCallable($execute);
    }

    public function run(string $mode = 'all', bool $plan = false): void
    {
        if (!in_array($mode, ['all', 'application', 'redis'], true)) {
            throw new RuntimeException('Unknown check mode: '.$mode);
        }

        if ($mode === 'all' && !$plan) {
            $this->assertRedisContext();
        }

        if ($mode !== 'redis') {
            $this->runApplicationChecks($plan);
        }

        if ($mode !== 'application') {
            $this->runRedisChecks($plan, $mode === 'all');
        }

        if ($plan) {
            fwrite(STDOUT, PHP_EOL.'Plan only; no checks were executed.'.PHP_EOL);

            return;
        }

        if ($mode === 'application') {
            fwrite(STDOUT, PHP_EOL.'Application checks passed. Full verification also requires the redis mode against a disposable Redis service.'.PHP_EOL);

            return;
        }

        fwrite(STDOUT, PHP_EOL.($mode === 'redis' ? 'Redis workload checks passed.' : 'All mandatory checks passed.').PHP_EOL);
    }

    private function runApplicationChecks(bool $plan): void
    {
        $this->runBackendChecks($plan);
        $this->runStep('Frontend lint, tests, and production build', [$this->tool('npm'), 'run', 'check'], $plan);
        $this->runStep('Compiled frontend asset verification', [PHP_BINARY, 'scripts/verify-frontend-assets.php'], $plan);
        $this->runStep('Scheduler installer regression', [$this->tool('bash'), 'tests/scripts/scheduler-cron.test.sh'], $plan);

        $this->assertRestrictedFileExcludedFromDocker();
        $this->runStep('Production Docker image build', [$this->tool('docker'), 'build', '--tag', 'obsidian-web:ci', '.'], $plan);
        $this->runStep('Desktop and mobile browser regressions', [$this->tool('npm'), 'run', 'test:browser'], $plan);

        $browserTests = glob($this->root.'/tests/browser/*.mjs');
        if ($browserTests === false || $browserTests === []) {
            throw new RuntimeException('No browser harness files matched tests/browser/*.mjs.');
        }
        sort($browserTests, SORT_STRING);
        $browserTests = array_map(fn (string $path): string => $this->relativePath($path), $browserTests);
        $this->runStep(
            'Browser harness formatting',
            array_merge([$this->tool('npx'), 'prettier', '--check', 'playwright.config.mjs'], $browserTests),
            $plan
        );
    }

    private function runBackendChecks(bool $plan): void
    {
        $command = [$this->tool('composer'), 'check'];
        if ($plan) {
            fwrite(STDOUT, PHP_EOL.'==> Backend syntax and regression checks'.PHP_EOL);
            fwrite(STDOUT, '    public/build/manifest.json will be moved to a guarded backup and restored in finally.'.PHP_EOL);
            fwrite(STDOUT, '    '.$this->formatCommand($command).PHP_EOL);

            return;
        }

        $this->withHiddenManifest(function () use ($command): void {
            $this->runStep('Backend syntax and regression checks', $command, false);
        });
    }

    private function runRedisChecks(bool $plan, bool $contextAlreadyChecked): void
    {
        if (!$plan && !$contextAlreadyChecked) {
            $this->assertRedisContext();
        }

        $this->runStep(
            'Disposable Redis workload integration',
            [PHP_BINARY, 'scripts/test-redis-workloads.php'],
            $plan
        );
    }

    private function runStep(string $label, array $command, bool $plan): void
    {
        fwrite(STDOUT, PHP_EOL.'==> '.$label.PHP_EOL);
        fwrite(STDOUT, '    '.$this->formatCommand($command).PHP_EOL);
        if ($plan) {
            return;
        }

        $exitCode = ($this->execute)($command, $this->root);
        if ($exitCode !== 0) {
            throw new RuntimeException($label.' failed with exit code '.$exitCode.'.');
        }
    }

    private function withHiddenManifest(callable $checks): void
    {
        $buildDirectory = $this->root.'/public/build';
        $manifest = $buildDirectory.'/manifest.json';
        $backup = $buildDirectory.'/.check-all-manifest.backup';
        $moved = false;
        $failure = null;

        if (is_dir($buildDirectory)) {
            $resolvedBuild = realpath($buildDirectory);
            if ($resolvedBuild === false || !$this->isWithinRoot($resolvedBuild)) {
                throw new RuntimeException('Refusing to move a manifest from outside the repository.');
            }
        }

        if (file_exists($backup) || is_link($backup)) {
            throw new RuntimeException('Manifest backup already exists; inspect and restore it before rerunning: '.$backup);
        }

        if (is_link($manifest)) {
            throw new RuntimeException('Refusing to move a symbolic-link frontend manifest.');
        }
        if (file_exists($manifest) && !is_file($manifest)) {
            throw new RuntimeException('Frontend manifest path is not a regular file: '.$manifest);
        }

        if (is_file($manifest)) {
            if (!rename($manifest, $backup)) {
                throw new RuntimeException('Unable to move the frontend manifest to its guarded backup.');
            }
            $moved = true;
        }

        try {
            $checks();
        } catch (Throwable $error) {
            $failure = $error;
        } finally {
            if ($moved) {
                if (file_exists($manifest) || is_link($manifest)) {
                    throw new RuntimeException(
                        'A new frontend manifest appeared during backend checks. The original remains at '.$backup.' and was not overwritten.',
                        0,
                        $failure
                    );
                }
                if (!rename($backup, $manifest)) {
                    throw new RuntimeException('Unable to restore the frontend manifest from '.$backup.'.', 0, $failure);
                }
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    private function assertRestrictedFileExcludedFromDocker(): void
    {
        $path = $this->root.'/.dockerignore';
        $contents = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : false;
        if ($contents === false) {
            throw new RuntimeException('Docker build refused because .dockerignore is missing.');
        }

        $patterns = array_map('trim', $contents);
        $lastRequiredPosition = -1;
        foreach ([self::RESTRICTED_ROOT_PATTERN, self::RESTRICTED_RECURSIVE_PATTERN] as $required) {
            $position = array_search($required, $patterns, true);
            if ($position === false) {
                throw new RuntimeException('Docker build refused because .dockerignore lacks the restricted-file exclusion: '.$required);
            }
            $lastRequiredPosition = max($lastRequiredPosition, $position);
        }
        foreach ($patterns as $position => $pattern) {
            if ($position > $lastRequiredPosition && str_starts_with($pattern, '!')) {
                throw new RuntimeException('Docker build refused because .dockerignore has a later negation after the restricted-file exclusions.');
            }
        }
    }

    private function assertRedisContext(): void
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('REDIS_WORKLOADS_INTEGRATION') !== '1') {
            throw new RuntimeException('Redis checks require APP_ENV=testing and REDIS_WORKLOADS_INTEGRATION=1 for an explicitly provisioned disposable service.');
        }
        if (!extension_loaded('redis')) {
            throw new RuntimeException('Redis checks require the phpredis extension.');
        }
    }

    private function executeCommand(array $command, string $workingDirectory): int
    {
        $process = proc_open(
            $command,
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            $workingDirectory,
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start command: '.$this->formatCommand($command));
        }

        return proc_close($process);
    }

    private function tool(string $name): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return $name;
        }

        if (isset($this->resolvedTools[$name])) {
            return $this->resolvedTools[$name];
        }

        $fileName = match ($name) {
            'composer' => 'composer.bat',
            'npm', 'npx' => $name.'.cmd',
            'docker', 'bash' => $name.'.exe',
            default => $name,
        };
        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
            $candidate = rtrim(trim($directory, '"'), '\\/').DIRECTORY_SEPARATOR.$fileName;
            if (is_file($candidate)) {
                return $this->resolvedTools[$name] = $candidate;
            }
        }

        return $this->resolvedTools[$name] = $fileName;
    }

    private function relativePath(string $path): string
    {
        $normalizedRoot = str_replace('\\', '/', $this->root);
        $normalizedPath = str_replace('\\', '/', $path);
        $prefix = $normalizedRoot.'/';
        $comparisonPath = PHP_OS_FAMILY === 'Windows' ? strtolower($normalizedPath) : $normalizedPath;
        $comparisonPrefix = PHP_OS_FAMILY === 'Windows' ? strtolower($prefix) : $prefix;
        if (!str_starts_with($comparisonPath, $comparisonPrefix)) {
            throw new RuntimeException('Path is outside the repository: '.$path);
        }

        return substr($normalizedPath, strlen($prefix));
    }

    private function isWithinRoot(string $path): bool
    {
        $normalizedRoot = str_replace('\\', '/', $this->root);
        $normalizedPath = str_replace('\\', '/', rtrim($path, DIRECTORY_SEPARATOR));
        if (PHP_OS_FAMILY === 'Windows') {
            $normalizedRoot = strtolower($normalizedRoot);
            $normalizedPath = strtolower($normalizedPath);
        }

        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot.'/');
    }

    private function formatCommand(array $command): string
    {
        return implode(' ', array_map(static function (string $argument): string {
            if ($argument !== '' && preg_match('/^[a-zA-Z0-9_\.\/:=@+-]+$/', $argument)) {
                return $argument;
            }

            return '"'.str_replace('"', '\\"', $argument).'"';
        }, $command));
    }
}

function usage(): string
{
    return <<<'TEXT'
Usage: php scripts/check-all.php [all|application|redis] [--plan]

  all          Run every mandatory check. Requires an already-provisioned,
               disposable Redis service, APP_ENV=testing,
               REDIS_WORKLOADS_INTEGRATION=1, and phpredis.
  application  Run backend, frontend, asset, scheduler, Docker, browser, and
               browser-format checks. The Redis service check remains required.
  redis        Run only the opt-in Redis integration check for a separate CI job.
  --plan       Print the ordered commands without executing them.

This runner builds and tests locally; it never deploys the application.
TEXT;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $arguments = array_slice($argv, 1);
    if (in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
        fwrite(STDOUT, usage().PHP_EOL);
        exit(0);
    }

    $plan = false;
    $mode = 'all';
    $modeSpecified = false;
    foreach ($arguments as $argument) {
        if ($argument === '--plan') {
            $plan = true;
        } elseif (in_array($argument, ['all', 'application', 'redis'], true) && !$modeSpecified) {
            $mode = $argument;
            $modeSpecified = true;
        } else {
            fwrite(STDERR, 'Invalid argument: '.$argument.PHP_EOL.PHP_EOL.usage().PHP_EOL);
            exit(2);
        }
    }

    try {
        (new FullCheckRunner(__DIR__.'/..'))->run($mode, $plan);
    } catch (Throwable $error) {
        fwrite(STDERR, PHP_EOL.'Verification failed: '.$error->getMessage().PHP_EOL);
        exit(1);
    }
}
