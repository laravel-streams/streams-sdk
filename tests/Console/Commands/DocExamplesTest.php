<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Tests\SdkTestCase;

class DocExamplesTest extends SdkTestCase
{
    protected string $root;

    protected string $output;

    protected string $workingDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workingDirectory = getcwd();
        $this->root = sys_get_temp_dir().'/streams-sdk-doc-examples-'.bin2hex(random_bytes(8));
        $this->output = $this->root.'/build';

        File::copyDirectory(__DIR__.'/../../Fixtures/References/v1/app', $this->root.'/app');
        File::copyDirectory(dirname(__DIR__, 3).'/resources/schemas/fixtures/people', $this->root);
        File::ensureDirectoryExists($this->output);
        $this->app->setBasePath($this->root);
        chdir($this->root);
        config([
            'streams.sdk.capture.root' => $this->root,
            'streams.sdk.capture.tree' => $this->root.'/streams.json',
            'streams.sdk.make.tree' => $this->root.'/streams.json',
            'streams.sdk.make.output' => $this->output,
        ]);

        // Use the documented recipe bytes, so documentation drift fails here.
        preg_match_all('/```json\R(.*?)\R```/s', self::documentation(), $blocks);

        foreach ($blocks[1] as $block) {
            $recipe = json_decode($block, true, flags: JSON_THROW_ON_ERROR);

            if (($recipe['path'] ?? null) === 'people.actions.add' && isset($recipe['input'])) {
                File::put($this->root.'/add.json', $block);
            }
        }
    }

    protected function tearDown(): void
    {
        chdir($this->workingDirectory);
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * Execute concrete make/capture examples directly from the reference.
     * Signature notation with {arguments} is not a shell example.
     *
     * @dataProvider documentedCommands
     */
    public function test_documented_command_runs_green(string $command): void
    {
        $rename = str_contains($command, 'streams:make rename ');
        $fix = str_contains($command, '--fix');

        if ($rename || $fix) {
            $code = Artisan::call('streams:make --json');
            $this->assertSame(0, $code, Artisan::output());
        }

        $relative = str_contains($command, 'streams:make people.table ')
            ? 'app/Domains/People/Panel/Tables/PeopleTable.php'
            : 'app/Domains/People/Panel/Actions/PeopleActionsAdd.php';
        $conflicted = $this->output.'/'.$relative;

        if ($fix) {
            File::append($conflicted, "\n// Local edit that regeneration will discard.\n");
        }

        $before = $this->snapshot();
        $code = Artisan::call($command);
        $output = Artisan::output();
        $this->assertSame(0, $code, $command."\n".$output);

        if (str_contains($command, '--dry-run') || $command === 'streams:capture --json') {
            $this->assertSame($before, $this->snapshot(), 'Preview wrote files: '.$command);
        }

        if (str_contains($command, '--json') && ! str_contains($command, '--json=')) {
            $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            $this->assertIsArray($payload);

            if ($fix) {
                $this->assertCount(1, $payload['report']['fixed']);
                $this->assertSame([], $payload['report']['failed']);
            } elseif (str_contains($command, '--menu')) {
                $this->assertNotEmpty($payload['pages']);
                $this->assertSame('people.pages.manage', $payload['pages'][0]['path']);
                $this->assertNotEmpty($payload['pages'][0]['components']);
            } elseif (str_contains($command, '--list')) {
                $this->assertNotEmpty($payload['kinds']);
                $this->assertContains('action', array_column($payload['kinds'], 'kind'));
            } elseif (isset($payload['report'])) {
                foreach (['created', 'modified', 'skipped', 'unapplied', 'conflicts', 'diff', 'kinds'] as $key) {
                    $this->assertArrayHasKey($key, $payload);
                }
            }
        }

        if ($fix) {
            if (str_contains($command, '--dry-run')) {
                $this->assertStringContainsString('would fix:', $output);
            } else {
                $this->assertStringNotContainsString('// Local edit', File::get($conflicted));
            }
        }

        if ($rename && ! str_contains($command, '--dry-run')) {
            $lock = File::get($this->output.'/streams.lock');
            $this->assertStringContainsString('people.actions.create', $lock);
            $this->assertStringNotContainsString('people.actions.add', $lock);
        }

        if ($command === 'streams:make --json') {
            $this->assertFileExists($this->output.'/streams.lock');
            $this->assertNotSame($before, $this->snapshot(), 'JSON build must apply its plan.');
        }

        if (str_contains($command, '--json=add.json') && ! str_contains($command, '--dry-run')) {
            $tree = File::get($this->root.'/streams/people.actions.add.json');
            $label = str_contains($command, '--label=') ? 'Create person' : 'Add person';
            $this->assertStringContainsString($label, $tree);
            $this->assertFileDoesNotExist($this->output.'/streams.lock');
        }

        if ($command === 'streams:capture') {
            $this->assertStringContainsString('captured:', $output);
            $this->assertStringContainsString('"people"', File::get($this->root.'/streams.json'));
        }

        if (str_contains($command, '--tree=streams-captured.json')) {
            $this->assertFileExists($this->root.'/streams-captured.json');
        }
    }

    /** @return array<string, array{string}> */
    public static function documentedCommands(): array
    {
        preg_match_all('/```bash\R(.*?)\R```/s', self::documentation(), $blocks);
        $commands = [];

        foreach ($blocks[1] as $block) {
            foreach (explode("\n", $block) as $line) {
                if (preg_match('/^php artisan (streams:(?:make|capture)(?: .*)?)$/', trim($line), $match)
                    && ! str_contains($line, '{')) {
                    $commands[$match[1]] = [$match[1]];
                }
            }
        }

        if ($commands === []) {
            throw new \RuntimeException('No make/capture documentation examples found.');
        }

        return $commands;
    }

    protected static function documentation(): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/docs/commands.md');
    }

    /** @return array<string, string> */
    protected function snapshot(): array
    {
        $files = [];

        foreach (File::allFiles($this->root) as $file) {
            $files[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }

        ksort($files);

        return $files;
    }
}
