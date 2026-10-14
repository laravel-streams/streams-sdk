<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * Every copy-pasteable example command from docs/commands.md runs green.
 * Each example is invoked exactly as documented (arguments and options
 * only), against the Reference v1 people fixtures for streams:capture and
 * the schema fixture for streams:make build, rename, --fix, and --json.
 * If you change a command's signature or output, update docs/commands.md
 * and this file together.
 */
class DocExamplesTest extends SdkTestCase
{
    protected string $root;

    protected string $output;

    protected string $makeFixture;

    protected ?string $treeCopy = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/streams-sdk-doc-examples-'.bin2hex(random_bytes(8));
        $this->output = $this->root.'/build';

        File::copyDirectory(__DIR__.'/../../Fixtures/References/v1/app', $this->root.'/app');
        File::copyDirectory(__DIR__.'/../../Fixtures/References/v1/streams', $this->root.'/streams');
        File::ensureDirectoryExists($this->output);

        config([
            'streams.sdk.capture.root' => $this->root,
            'streams.sdk.capture.tree' => $this->root.'/streams.json',
            'streams.sdk.make.tree' => $this->root.'/streams.json',
            'streams.sdk.make.output' => $this->output,
        ]);

        $this->makeFixture = dirname(__DIR__, 3).'/resources/schemas/fixtures/people/streams.json';
    }

    protected function tearDown(): void
    {
        if ($this->treeCopy !== null && is_dir($this->treeCopy)) {
            File::deleteDirectory($this->treeCopy);
        }

        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_every_streams_capture_example_runs_green(): void
    {
        // php artisan streams:capture
        $this->assertSame(0, Artisan::call('streams:capture'), Artisan::output());
        $this->assertFileExists($this->root.'/streams.json');

        // php artisan streams:capture --dry-run
        $this->assertSame(0, Artisan::call('streams:capture', ['--dry-run' => true]), Artisan::output());
        $this->assertStringContainsString('"people"', Artisan::output());

        // php artisan streams:capture --json
        $this->assertSame(0, Artisan::call('streams:capture', ['--json' => true]), Artisan::output());
        $this->assertStringContainsString('"people"', Artisan::output());

        // php artisan streams:capture --root=... --tree=...
        $this->assertSame(0, Artisan::call('streams:capture', [
            '--root' => $this->root,
            '--tree' => $this->root.'/streams-other.json',
        ]), Artisan::output());
        $this->assertFileExists($this->root.'/streams-other.json');
    }

    public function test_every_streams_make_build_example_runs_green(): void
    {
        // php artisan streams:make --list
        $this->assertSame(0, Artisan::call('streams:make', [
            '--list' => true,
            '--tree' => $this->makeFixture,
            '--output' => $this->output,
        ]), Artisan::output());

        // --list --json
        $this->assertSame(0, Artisan::call('streams:make', [
            '--list' => true,
            '--json' => true,
            '--tree' => $this->makeFixture,
            '--output' => $this->output,
        ]), Artisan::output());

        // php artisan streams:make --json
        $this->assertSame(0, Artisan::call('streams:make', [
            '--json' => true,
            '--tree' => $this->makeFixture,
            '--output' => $this->output,
        ]), Artisan::output());

        // php artisan streams:make --dry-run
        $this->assertSame(0, Artisan::call('streams:make', [
            '--dry-run' => true,
            '--tree' => $this->makeFixture,
            '--output' => $this->output,
        ]), Artisan::output());

        // php artisan streams:make people.table --dry-run --json
        $this->assertSame(0, Artisan::call('streams:make', [
            'path' => 'people.table',
            '--dry-run' => true,
            '--json' => true,
            '--tree' => $this->makeFixture,
            '--output' => $this->output,
        ]), Artisan::output());
    }

    public function test_every_streams_make_rename_example_runs_green(): void
    {
        // A rename needs the source node built and locked first.
        $copy = $this->copyFixture();
        $this->runMake(['--tree' => $copy.'/streams.json']);

        // php artisan streams:make rename people.actions.add people.actions.create
        $this->assertSame(0, Artisan::call('streams:make', [
            'path' => 'rename', 'from' => 'people.actions.add', 'to' => 'people.actions.create',
            '--tree' => $copy.'/streams.json', '--output' => $this->output,
        ]), Artisan::output());

        // rename back (the pair above already swapped the tree), then dry-run.
        Artisan::call('streams:make', [
            'path' => 'rename', 'from' => 'people.actions.create', 'to' => 'people.actions.add',
            '--tree' => $copy.'/streams.json', '--output' => $this->output,
        ]);

        $this->assertSame(0, Artisan::call('streams:make', [
            'path' => 'rename', 'from' => 'people.actions.add', 'to' => 'people.actions.create',
            '--dry-run' => true,
            '--tree' => $copy.'/streams.json', '--output' => $this->output,
        ]), Artisan::output());
    }

    public function test_every_streams_make_fix_example_runs_green(): void
    {
        // Build once, then hand-edit a generated file so --fix has work.
        $this->runMake(['--tree' => $this->makeFixture]);
        $relative = 'app/Domains/People/Panel/Actions/PeopleActionsAdd.php';
        $absolute = $this->output.'/'.$relative;
        $original = File::get($absolute);
        File::put($absolute, str_replace("return 'Add person';", "return 'Added by hand';", $original));

        // php artisan streams:make --fix --dry-run
        $this->assertSame(0, Artisan::call('streams:make', [
            '--fix' => true,
            '--dry-run' => true,
            '--tree' => $this->makeFixture,
            '--output' => $this->output,
        ]), Artisan::output());
        $this->assertStringContainsString('would fix: people.actions.add', Artisan::output());

        // php artisan streams:make --fix
        $this->assertSame(0, Artisan::call('streams:make', [
            '--fix' => true,
            '--tree' => $this->makeFixture,
            '--output' => $this->output,
        ]), Artisan::output());
        $this->assertStringContainsString('fixed: people.actions.add', Artisan::output());
        $this->assertStringContainsString("return 'Add person';", File::get($absolute));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function runMake(array $arguments): void
    {
        $this->assertSame(0, Artisan::call('streams:make', $arguments + ['--output' => $this->output]), Artisan::output());
    }

    protected function copyFixture(): string
    {
        $dest = sys_get_temp_dir().'/streams-sdk-doc-tree-'.uniqid();

        File::copyDirectory(dirname($this->makeFixture), $dest);

        $this->treeCopy = $dest;

        return $dest;
    }
}