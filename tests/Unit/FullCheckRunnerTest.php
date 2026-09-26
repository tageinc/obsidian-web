<?php

namespace Tests\Unit;

use Obsidian\Scripts\FullCheckRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../scripts/check-all.php';

class FullCheckRunnerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/obsidian-check-all-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/public/build', 0700, true);
        mkdir($this->root.'/tests/browser', 0700, true);
        file_put_contents($this->root.'/tests/browser/workflow.mjs', 'fixture');
        file_put_contents($this->root.'/playwright.config.mjs', 'fixture');
        file_put_contents($this->root.'/.dockerignore', implode(PHP_EOL, [
            '[Dd][Ee][Vv][Oo][Pp][Ss][Oo][Bb][Ss][Ii][Dd][Ii][Aa][Nn].[Tt][Xx][Tt]',
            '**/[Dd][Ee][Vv][Oo][Pp][Ss][Oo][Bb][Ss][Ii][Dd][Ii][Aa][Nn].[Tt][Xx][Tt]',
        ]));
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function test_application_checks_run_in_the_mandatory_order(): void
    {
        $commands = [];
        $runner = new FullCheckRunner($this->root, function (array $command) use (&$commands): int {
            $commands[] = $command;

            return 0;
        });

        $runner->run('application');

        $this->assertCount(7, $commands);
        $this->assertSame('check', $commands[0][1]);
        $this->assertSame(['run', 'check'], array_slice($commands[1], 1));
        $this->assertStringEndsWith('scripts/verify-frontend-assets.php', str_replace('\\', '/', $commands[2][1]));
        $this->assertSame('tests/scripts/scheduler-cron.test.sh', $commands[3][1]);
        $this->assertSame(['build', '--tag', 'obsidian-web:ci', '.'], array_slice($commands[4], 1));
        $this->assertSame(['run', 'test:browser'], array_slice($commands[5], 1));
        $this->assertSame('prettier', $commands[6][1]);
    }

    public function test_manifest_is_unavailable_during_backend_check_and_restored_after_failure(): void
    {
        $manifest = $this->root.'/public/build/manifest.json';
        file_put_contents($manifest, 'original manifest');
        $runner = new FullCheckRunner($this->root, function (array $command) use ($manifest): int {
            if (($command[1] ?? '') === 'check') {
                $this->assertFileDoesNotExist($manifest);

                return 7;
            }

            return 0;
        });

        try {
            $runner->run('application');
            $this->fail('The failing command should stop verification.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('exit code 7', $error->getMessage());
        }

        $this->assertSame('original manifest', file_get_contents($manifest));
        $this->assertFileDoesNotExist($this->root.'/public/build/.check-all-manifest.backup');
    }

    public function test_existing_manifest_backup_is_never_overwritten(): void
    {
        $manifest = $this->root.'/public/build/manifest.json';
        $backup = $this->root.'/public/build/.check-all-manifest.backup';
        file_put_contents($manifest, 'original manifest');
        file_put_contents($backup, 'existing backup');
        $executed = false;
        $runner = new FullCheckRunner($this->root, function () use (&$executed): int {
            $executed = true;

            return 0;
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('backup already exists');

        try {
            $runner->run('application');
        } finally {
            $this->assertFalse($executed);
            $this->assertSame('original manifest', file_get_contents($manifest));
            $this->assertSame('existing backup', file_get_contents($backup));
        }
    }

    public function test_docker_build_requires_both_restricted_file_exclusions(): void
    {
        file_put_contents(
            $this->root.'/.dockerignore',
            '[Dd][Ee][Vv][Oo][Pp][Ss][Oo][Bb][Ss][Ii][Dd][Ii][Aa][Nn].[Tt][Xx][Tt]'.PHP_EOL
        );
        $runner = new FullCheckRunner($this->root, static fn (): int => 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('restricted-file exclusion');
        $runner->run('application');
    }

    public function test_docker_build_rejects_a_later_restricted_file_negation(): void
    {
        file_put_contents($this->root.'/.dockerignore', file_get_contents($this->root.'/.dockerignore').PHP_EOL.'!nested/DevOpsObsidian.txt'.PHP_EOL);
        $runner = new FullCheckRunner($this->root, static fn (): int => 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('later negation');
        $runner->run('application');
    }
}
