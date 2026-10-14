<?php

namespace Streams\Sdk\Tests\Support;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GoldenFilesTest extends TestCase
{
    private function golden(): string
    {
        return dirname(__DIR__).'/Fixtures/Golden/helper-v1';
    }

    private function generate(string $directory): void
    {
        mkdir($directory.'/nested');
        file_put_contents($directory.'/nested/people.json', "{\"handle\":\"people\"}\n");
        file_put_contents($directory.'/.empty', '');
    }

    public function test_matches_nested_and_hidden_files_and_removes_temporary_directory(): void
    {
        $output = null;
        GoldenFiles::assertGenerated(function (string $directory) use (&$output): void {
            $output = $directory;
            $this->generate($directory);
        }, $this->golden().'/');

        $this->assertDirectoryDoesNotExist($output);
    }

    public function test_mismatch_reports_unified_diff_and_cleans_up(): void
    {
        $output = null;

        try {
            GoldenFiles::assertGenerated(function (string $directory) use (&$output): void {
                $output = $directory;
                $this->generate($directory);
                file_put_contents($directory.'/nested/people.json', "{\"handle\":\"wrong\"}\n");
            }, $this->golden());
        } catch (AssertionFailedError $error) {
            $this->assertStringContainsString("--- golden/nested/people.json\n+++ actual/nested/people.json\n@@", $error->getMessage());
            $this->assertStringContainsString('-{"handle":"people"}', $error->getMessage());
            $this->assertStringContainsString('+{"handle":"wrong"}', $error->getMessage());
            $this->assertDirectoryDoesNotExist($output);

            return;
        }

        $this->fail('A changed output file must fail.');
    }

    public function test_missing_empty_file_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Missing output file: .empty');
        GoldenFiles::assertGenerated(function (string $directory): void {
            $this->generate($directory);
            unlink($directory.'/.empty');
        }, $this->golden());
    }

    public function test_unexpected_empty_file_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Unexpected output file: extra');
        GoldenFiles::assertGenerated(function (string $directory): void {
            $this->generate($directory);
            file_put_contents($directory.'/extra', '');
        }, $this->golden());
    }

    public function test_generator_exception_propagates_and_cleans_up(): void
    {
        $output = null;

        try {
            GoldenFiles::assertGenerated(function (string $directory) use (&$output): void {
                $output = $directory;
                $this->generate($directory);
                throw new RuntimeException('Generator failed');
            }, $this->golden());
        } catch (RuntimeException $error) {
            $this->assertSame('Generator failed', $error->getMessage());
            $this->assertDirectoryDoesNotExist($output);

            return;
        }

        $this->fail('The generator exception must propagate.');
    }

    public function test_each_run_gets_an_empty_unique_directory(): void
    {
        $outputs = [];

        for ($run = 0; $run < 2; $run++) {
            GoldenFiles::assertGenerated(function (string $directory) use (&$outputs): void {
                $outputs[] = $directory;
                $this->assertSame([], array_values(array_diff(scandir($directory), ['.', '..'])));
                $this->generate($directory);
            }, $this->golden());
        }

        $this->assertNotSame($outputs[0], $outputs[1]);
    }

    public function test_missing_nonempty_file_reports_deletion_diff(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('-{"handle":"people"}');
        GoldenFiles::assertGenerated(function (string $directory): void {
            $this->generate($directory);
            unlink($directory.'/nested/people.json');
        }, $this->golden());
    }

    public function test_symbolic_link_is_rejected_without_deleting_its_target(): void
    {
        $output = null;
        $target = $this->golden().'/nested/people.json';
        $original = file_get_contents($target);

        try {
            GoldenFiles::assertGenerated(function (string $directory) use (&$output, $target): void {
                $output = $directory;
                $this->generate($directory);
                symlink($target, $directory.'/linked.json');
            }, $this->golden());
        } catch (AssertionFailedError $error) {
            $this->assertStringContainsString('do not support symbolic links', $error->getMessage());
            $this->assertDirectoryDoesNotExist($output);
            $this->assertSame($original, file_get_contents($target));

            return;
        }

        $this->fail('Symbolic links must fail.');
    }
}
