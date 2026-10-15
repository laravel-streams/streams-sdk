<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Console\Make\Paths;
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

        // A computed make() name (a translation call) is opaque under the
        // item's `name`, never mislabeled as a bare constructor node.
        $panelOpaque = $tree->node('people.panel')['opaque'] ?? [];
        $panelHandles = array_column($panelOpaque, 'handle');
        $this->assertContains('navigation.items.0.name', $panelHandles);
        $this->assertNotContains('navigation.items.0.NavigationItem', $panelHandles);
    }

    public function test_literal_settings_including_null_and_negative_numbers_are_captured(): void
    {
        Artisan::call('streams:capture');
        $tree = AppTree::load($this->root.'/streams.json');

        // Literal null, negative and integer-array settings stay data.
        $table = $tree->node('people.table');
        $this->assertArrayHasKey('pollingInterval', $table['settings']);
        $this->assertNull($table['settings']['pollingInterval']);
        $this->assertSame(-1, $table['settings']['defaultSort'] ?? null);
        $this->assertSame([5, 10, 25], $table['settings']['paginationPageOptions'] ?? null);

        // A literal null property default is data too.
        $page = $tree->node('people.pages.list_people');
        $this->assertArrayHasKey('navigationBadge', $page['settings']);
        $this->assertNull($page['settings']['navigationBadge']);

        // make() names an item; it is not a literal setting.
        $this->assertArrayNotHasKey('columns.status.make', $table['settings']);
        $panel = $tree->node('people.panel');
        $this->assertArrayNotHasKey('navigation.make', $panel['settings']);

        // Assignment scaffolding never leaks an empty-handle opaque node.
        foreach (['people.table', 'people.panel', 'people.pages.list_people', 'people.actions.add_person_modal'] as $path) {
            $node = $tree->node($path);
            $this->assertNotEmpty($node['opaque'] ?? []);
            $this->assertNotContains('', array_column($node['opaque'], 'handle'));
        }
    }

    /**
     * Spread arrays merge items at runtime and mixed builder arrays depend on
     * computed siblings: capture keeps literal items as data and reports the
     * computed parts as opaque nodes, never the reverse.
     */
    public function test_spread_arrays_and_literal_items_inside_mixed_arrays_are_captured_honestly(): void
    {
        File::put($this->root.'/app/Domains/People/Panel/Actions/EdgeCases.php', <<<'PHP'
            <?php

            namespace App\Domains\People\Panel\Actions;

            class EdgeCases extends \Streams\Ui\Builders\Actions\Action
            {
                protected static array $columns = ['first_name', 'last_name'];

                protected static array $withComputed = [
                    'en' => 'English',
                    'fr' => __('domains.people.french'),
                ];

                protected function setUp(): void
                {
                    $this
                        ->defaults([5, 10, 25])
                        ->options([
                            'en' => 'English',
                            'fr' => __('domains.people.french'),
                        ])
                        ->labels(['a', 'b', ...[3, 4]]);
                }
            }
            PHP);

        $exit = Artisan::call('streams:capture');
        $this->assertSame(0, $exit, Artisan::output());

        $tree = AppTree::load($this->root.'/streams.json');
        $node = $tree->node('people.actions.edge_cases');
        $settings = $node['settings'];
        $handles = array_column($node['opaque'], 'handle');

        // Literal arrays are data: a property default and a single argument.
        $this->assertSame(['first_name', 'last_name'], $settings['columns'] ?? null);
        $this->assertSame([5, 10, 25], $settings['defaults'] ?? null);

        // A literal item inside a mixed builder array stays data...
        $this->assertSame('English', $settings['options.en'] ?? null);

        // ...while its computed sibling is opaque, never literal data.
        $this->assertContains('options.fr', $handles);

        // A spread merges items at runtime: the surrounding literal items stay
        // data and the spread itself is opaque, never a flattened literal.
        $this->assertArrayNotHasKey('labels', $settings);
        $this->assertSame('a', $settings['labels.0'] ?? null);
        $this->assertSame('b', $settings['labels.1'] ?? null);
        $this->assertContains('labels.2', $handles);

        // A mixed property default depends on a computed sibling: the whole
        // property is opaque, not split into literal + opaque fragments.
        $this->assertArrayNotHasKey('withComputed', $settings);
        $this->assertContains('withComputed', $handles);

        // The tree still validates against the app schema.
        $validator = new SchemaValidator($this->schemaDir);
        $validator->assertValid(json_decode((string) File::get($this->root.'/streams.json')), 'https://streams.dev/schema/app.schema.json', 'streams.json');
    }

    public function test_unreadable_builder_files_are_reported_not_silently_dropped(): void
    {
        File::put($this->root.'/app/Domains/People/Panel/Tables/BrokenTable.php', "<?php\n\nthis is not valid php (((\n");
        File::put($this->root.'/app/Domains/People/Panel/Tables/NoClassTable.php', "<?php\n\n\$shared = 1;\n");

        $exit = Artisan::call('streams:capture');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('BrokenTable.php', $output);
        $this->assertStringContainsString('could not be parsed', $output);
        $this->assertStringContainsString('NoClassTable.php', $output);
        $this->assertStringContainsString('no builder class found', $output);

        // The healthy builders are still captured and the tree still validates.
        $treeFile = $this->root.'/streams.json';
        $this->assertFileExists($treeFile);
        $data = json_decode((string) File::get($treeFile));
        $validator = new SchemaValidator($this->schemaDir);
        $validator->assertValid($data, 'https://streams.dev/schema/app.schema.json', 'streams.json');
        $tree = AppTree::load($treeFile);
        $this->assertSame('table', $tree->type('people.table'));
        $this->assertSame('people_table', $tree->node('people.table')['handle'] ?? null);
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

    public function test_litmus_capture_rebuild_clean_and_report_opaque_share(): void
    {
        Artisan::call('streams:capture');

        // Snapshot the captured tree before any build touches the output dir.
        $tree = AppTree::load($this->root.'/streams.json');

        // Every code-bearing node is either rebuilt from JSON data or is a
        // deliberately opaque node (captured closure/callback, never rewritten).
        $total = 0;
        $opaque = [];
        foreach (Paths::FILE_TYPES as $type) {
            foreach ($tree->types() as $path => $nodeType) {
                if ($nodeType !== $type) {
                    continue;
                }
                $total++;
                if ($this->isOpaqueSubtree($tree, $path)) {
                    $opaque[$path] = $this->opaqueEntries($tree, $path);
                }
            }
        }

        $this->assertGreaterThan(0, $total);
        $this->assertGreaterThan(0, count($opaque));

        // Rebuild into a clean directory from the snapshot: streams:make
        // regenerates every non-opaque code-bearing node and never writes an
        // opaque one.
        $report = $this->make();
        $this->assertSame([], $report['modified']);
        $this->assertSame([], $report['conflicts']);
        $this->assertSame([], $report['unapplied']);

        // The people reference slice is entirely opaque, so the rebuild from
        // the snapshot creates nothing: every code-bearing node is either
        // rebuilt from JSON data or deliberately opaque.
        $rebuilt = $total - count($opaque);
        $this->assertSame($rebuilt, count($report['created']));

        // Opaque share: the fraction of code-bearing nodes the toolkit cannot
        // rebuild from JSON yet (number + list).
        $share = count($opaque) / $total;
        $this->assertGreaterThan(0.0, $share);
        $this->assertLessThanOrEqual(1.0, $share);

        $lines = [];
        foreach ($opaque as $path => $entries) {
            foreach ($entries as $entry) {
                $lines[] = sprintf(
                    '- [ ] `%s` — `%s` (%s:%d) — toolkit gap candidate',
                    $entry['handle'],
                    $path,
                    $entry['file'],
                    $entry['line'],
                );
            }
        }

        $reportMarkdown = implode("\n", [
            '# Task 139 — Litmus: capture → rebuild → opaque share',
            '',
            sprintf('Code-bearing nodes: **%d**', $total),
            sprintf('Opaque nodes (not rebuildable from JSON): **%d**', count($opaque)),
            sprintf('Opaque share: **%.1f%%** (%d/%d)', $share * 100, count($opaque), $total),
            '',
            'Every code-bearing node is either rebuilt from the captured JSON',
            'snapshot or is a deliberately opaque node (a captured closure,',
            'permission callback or computed option) that `streams:make` never',
            'rewrites. The opaque nodes below are toolkit gap candidates:',
            '',
            ...$lines,
            '',
        ]);

        $evidenceDir = $this->root.'/evidence';
        File::ensureDirectoryExists($evidenceDir);
        File::put($evidenceDir.'/litmus-report.md', $reportMarkdown);
    }

    /**
     * Whether a code-bearing node is owned by people: its own node or any
     * descendant carries a non-empty `opaque` list. Mirrors
     * `BuildStage::isOpaqueSubtree` so the litmus reports the same verdict.
     */
    protected function isOpaqueSubtree(AppTree $tree, string $path): bool
    {
        foreach ($tree->types() as $nodePath => $type) {
            if ($nodePath !== $path && ! str_starts_with($nodePath, $path.'.')) {
                continue;
            }

            $node = $tree->node($nodePath);

            if (isset($node['opaque']) && is_array($node['opaque']) && $node['opaque'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{handle: string, file: string, line: int}>
     */
    protected function opaqueEntries(AppTree $tree, string $path): array
    {
        $entries = [];
        foreach ($tree->types() as $nodePath => $type) {
            if ($nodePath !== $path && ! str_starts_with($nodePath, $path.'.')) {
                continue;
            }

            $node = $tree->node($nodePath);
            $opaque = $node['opaque'] ?? [];

            if (is_array($opaque) && $opaque !== []) {
                foreach ($opaque as $entry) {
                    $entries[] = [
                        'handle' => $entry['handle'] ?? $nodePath,
                        'file' => $entry['file'] ?? '',
                        'line' => $entry['line'] ?? 0,
                    ];
                }
            }
        }

        return $entries;
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
