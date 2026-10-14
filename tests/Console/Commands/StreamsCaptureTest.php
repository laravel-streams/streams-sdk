<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Tests\SdkTestCase;
use Streams\Sdk\Tests\Support\SchemaValidator;

/**
 * streams:capture reads the people reference builders into a streams.json:
 * literal builder settings become data, closures/callbacks become opaque
 * nodes with file + line, and streams:make never rewrites an opaque node.
 */
class StreamsCaptureTest extends SdkTestCase
{
    protected string $schemaDir = __DIR__.'/../../../resources/schemas';

    protected string $root;

    protected string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/streams-sdk-capture-'.bin2hex(random_bytes(8));
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
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_capturing_the_people_fixtures_produces_a_streams_json_that_validates(): void
    {
        $exit = Artisan::call('streams:capture');
        $this->assertSame(0, $exit, Artisan::output());

        $treeFile = $this->root.'/streams.json';
        $this->assertFileExists($treeFile);

        $data = json_decode((string) File::get($treeFile));
        $this->assertIsObject($data);

        $validator = new SchemaValidator($this->schemaDir);
        $validator->assertValid($data, 'https://streams.dev/schema/app.schema.json', 'streams.json');

        $tree = AppTree::load($treeFile);
        $this->assertSame('stream', $tree->type('people'));
        $this->assertSame('table', $tree->type('people.table'));
        $this->assertSame('panel', $tree->type('people.panel'));
        $this->assertSame('page', $tree->type('people.pages.list_people'));
        $this->assertSame('action', $tree->type('people.actions.add_person_modal'));
    }

    public function test_closures_and_callbacks_become_opaque_nodes_with_file_and_line(): void
    {
        Artisan::call('streams:capture');
        $tree = AppTree::load($this->root.'/streams.json');

        $opaque = $tree->node('people.table')['opaque'] ?? [];
        $this->assertNotEmpty($opaque);

        foreach ($opaque as $entry) {
            $this->assertArrayHasKey('handle', $entry);
            $this->assertArrayHasKey('file', $entry);
            $this->assertArrayHasKey('line', $entry);
            $this->assertIsInt($entry['line']);
            $this->assertSame('app/Domains/People/Panel/Tables/PeopleTable.php', $entry['file']);
        }

        // The status column's color() and title() closures are opaque nodes.
        $handles = array_column($opaque, 'handle');
        $this->assertContains('columns.status.color', $handles);
        $this->assertContains('columns.status.title', $handles);
        $this->assertContains('query', $handles);

        $color = $this->findOpaque($opaque, 'columns.status.color');
        $this->assertNotNull($color);
        $this->assertSame(45, $color['line']);

        // Literal builder settings are still captured as data.
        $settings = $tree->node('people.table')['settings'] ?? [];
        $this->assertTrue($settings['columns.status.sortable'] ?? false);
        $this->assertSame(['first_name', 'last_name'], $settings['columns.first_name.searchable'] ?? null);

        // The action's permission callback is opaque too.
        $actionOpaque = $tree->node('people.actions.add_person_modal')['opaque'] ?? [];
        $actionHandles = array_column($actionOpaque, 'handle');
        $this->assertContains('authorize', $actionHandles);
        $this->assertContains('action', $actionHandles);
    }

    public function test_capture_then_build_produces_zero_changes_because_opaque_nodes_are_never_rewritten(): void
    {
        Artisan::call('streams:capture');

        $exit = Artisan::call('streams:make', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($report);

        foreach (['created', 'modified', 'skipped', 'unapplied', 'conflicts'] as $key) {
            $this->assertSame([], $report[$key]);
        }
        $this->assertSame('', $report['diff']);
        File::delete($this->output.'/streams.lock');
        $this->assertSame([], File::allFiles($this->output));

        // Once the owned builder files already exist, build still never
        // rewrites an opaque node: every file stays byte-for-byte intact.
        foreach (File::allFiles(__DIR__.'/../../Fixtures/References/v1/app') as $file) {
            $relative = $file->getRelativePathname();
            File::ensureDirectoryExists(dirname($this->output.'/'.$relative));
            File::copy($file->getPathname(), $this->output.'/'.$relative);
        }

        $before = $this->snapshot();
        $second = $this->make();

        foreach (['created', 'modified', 'skipped', 'unapplied', 'conflicts'] as $key) {
            $this->assertSame([], $second[$key]);
        }
        $this->assertSame('', $second['diff']);
        $this->assertSame($before, $this->snapshot());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function make(array $arguments = []): array
    {
        $exit = Artisan::call('streams:make', array_merge(['--json' => true], $arguments));
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($report);

        return $report;
    }

    /**
     * @return array<string, string>
     */
    protected function snapshot(): array
    {
        $files = [];
        foreach (File::allFiles($this->output) as $file) {
            if ($file->getRelativePathname() === 'streams.lock') {
                continue;
            }
            $files[$file->getRelativePathname()] = $file->getContents();
        }
        ksort($files);

        return $files;
    }

    /**
     * @param  list<array{handle: string, file: string, line: int}>  $opaque
     * @return array{handle: string, file: string, line: int}|null
     */
    protected function findOpaque(array $opaque, string $handle): ?array
    {
        foreach ($opaque as $entry) {
            if ($entry['handle'] === $handle) {
                return $entry;
            }
        }

        return null;
    }
}
