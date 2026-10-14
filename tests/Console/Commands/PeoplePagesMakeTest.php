<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Tests\SdkTestCase;
use Streams\Sdk\Tests\Support\GoldenFiles;

class PeoplePagesMakeTest extends SdkTestCase
{
    protected string $fixture;

    protected string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = __DIR__.'/../../Fixtures/PeopleMake/streams.json';
        $this->output = sys_get_temp_dir().'/streams-sdk-people-pages-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->output);

        config([
            'streams.sdk.make.tree' => $this->fixture,
            'streams.sdk.make.output' => $this->output,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->output);

        parent::tearDown();
    }

    public function test_people_fixture_resolves_the_manage_table_and_modal_form(): void
    {
        $tree = AppTree::load($this->fixture);

        $this->assertSame('page', $tree->type('people.pages.manage'));
        $this->assertSame('table', $tree->type('people.table'));
        $this->assertSame('modal', $tree->node('people.actions.add')['type']);
        $this->assertSame('form', $tree->type('people.actions.add.form'));
        $this->assertSame([], File::allFiles($this->output));
    }

    protected function make(array $arguments = []): array
    {
        $exit = Artisan::call('streams:make', array_merge(['--json' => true], $arguments));
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($report);

        return $report;
    }

    /** @dataProvider inputModes */
    public function test_command_input_modes_match_the_people_golden_files(array $arguments): void
    {
        GoldenFiles::assertGenerated(function (string $app) use ($arguments): void {
            $report = $this->make(array_merge($arguments, ['--output' => $app]));
            $this->assertSame([], $report['unapplied']);
            $this->assertSame([], $report['conflicts']);
            $this->assertCount(4, $report['created']);
            // Ownership metadata is tested separately; the golden captures app code.
            File::delete($app.'/streams.lock');
        }, __DIR__.'/../../Fixtures/Golden/people-make');
    }

    public static function inputModes(): array
    {
        return [
            'whole tree via configured defaults' => [[]],
            'stream path via explicit tree' => [['path' => 'people', '--tree' => __DIR__.'/../../Fixtures/PeopleMake/streams.json']],
            'manage component and dependencies' => [['path' => 'people.pages.manage']],
        ];
    }

    public function test_add_action_is_attached_to_an_existing_manage_page_with_a_minimal_ast_diff(): void
    {
        $relative = 'app/Domains/People/Panel/Pages/PeoplePagesManage.php';
        $original = File::get(__DIR__.'/../../Fixtures/PeopleMake/ExistingManage.php');
        File::ensureDirectoryExists(dirname($this->output.'/'.$relative));
        File::put($this->output.'/'.$relative, $original);

        $dry = $this->make(['path' => 'people.actions.add', '--dry-run' => true]);
        $this->assertSame($original, File::get($this->output.'/'.$relative));
        $real = $this->make(['path' => 'people.actions.add']);
        $this->assertSame($dry, $real);
        $this->assertSame([$relative], $real['modified']);
        $this->assertSame([], $real['unapplied']);
        $this->assertCount(2, $real['created']);

        $expected = str_replace(
            ['        PeopleTable::register($this);', '                            PeopleActionsMenu::make(),'],
            ["        PeopleTable::register(\$this);\n        AddPersonModal::register(\$this);", "                            PeopleActionsMenu::make(),\n                            AddPersonModal::resolve(),"],
            $original,
        );
        $this->assertSame($expected, File::get($this->output.'/'.$relative));
        $second = $this->make(['path' => 'people.actions.add']);
        $this->assertSame([], $second['created']);
        $this->assertSame([], $second['modified']);
        $this->assertSame('', $second['diff']);
        $this->assertSame($expected, File::get($this->output.'/'.$relative));
    }

    public function test_second_run_preserves_every_byte_and_dry_run_matches_the_real_change_list(): void
    {
        $dry = $this->make(['--dry-run' => true]);
        $this->assertSame([], File::allFiles($this->output));
        $real = $this->make();
        $this->assertSame($dry, $real);

        $before = $this->snapshot();
        $second = $this->make();
        foreach (['created', 'modified', 'skipped', 'unapplied', 'conflicts'] as $key) {
            $this->assertSame([], $second[$key]);
        }
        $this->assertSame('', $second['diff']);
        $this->assertSame($before, $this->snapshot());
    }

    /** @dataProvider unappliedPages */
    public function test_unapplied_page_edits_are_reported_and_no_existing_file_is_deleted(string $source, string $reason): void
    {
        $this->make(['path' => 'people.table']);
        $relative = 'app/Domains/People/Panel/Pages/PeoplePagesManage.php';
        File::ensureDirectoryExists(dirname($this->output.'/'.$relative));
        File::put($this->output.'/'.$relative, $source);
        File::put($this->output.'/keep.txt', 'keep this unrelated app file');
        $before = $this->snapshot();

        $dry = $this->make(['path' => 'people.actions.add', '--dry-run' => true]);
        $this->assertSame($before, $this->snapshot());
        $real = $this->make(['path' => 'people.actions.add']);
        $this->assertSame($dry, $real);
        $this->assertCount(1, $real['unapplied']);
        $this->assertSame('people.pages.manage', $real['unapplied'][0]['path']);
        $this->assertSame($relative, $real['unapplied'][0]['file']);
        $this->assertStringContainsString($reason, $real['unapplied'][0]['reason']);
        $this->assertNotContains($relative, $real['modified']);
        foreach ($before as $file => $contents) {
            if ($file !== 'streams.lock') {
                $this->assertSame($contents, File::get($this->output.'/'.$file));
            }
        }
    }

    public static function unappliedPages(): array
    {
        $fixture = file_get_contents(__DIR__.'/../../Fixtures/PeopleMake/ExistingManage.php');

        return [
            'missing component method' => [str_replace('function getComponents()', 'function customComponents()', $fixture), 'getComponents() are required'],
            'unparseable page' => ['<?php class PeoplePagesManage { invalid syntax', 'Syntax error'],
            'empty commented array' => [str_replace(["                            ImportPeopleModal::resolve(),\n", "                            PeopleActionsMenu::make(),\n"], '', $fixture), 'comments that cannot be preserved'],
            'ambiguous heading actions' => [str_replace('                    PeopleTable::resolve(),', "                    Heading::make('Another heading')->actions([]),\n                    PeopleTable::resolve(),", $fixture), 'expected one Heading actions array'],
        ];
    }

    public function test_table_dot_path_builds_only_the_table(): void
    {
        $report = $this->make(['path' => 'people.table']);
        $relative = 'app/Domains/People/Panel/Tables/PeopleTable.php';

        $this->assertSame([$relative], $report['created']);
        $this->assertSame(File::get(__DIR__.'/../../Fixtures/Golden/people-make/'.$relative), File::get($this->output.'/'.$relative));
        $this->assertFileDoesNotExist($this->output.'/app/Domains/People/Panel/Pages/PeoplePagesManage.php');
    }

    public function test_hand_edits_to_an_attached_page_are_preserved_and_reported_as_conflicts(): void
    {
        $relative = 'app/Domains/People/Panel/Pages/PeoplePagesManage.php';
        File::ensureDirectoryExists(dirname($this->output.'/'.$relative));
        File::put($this->output.'/'.$relative, File::get(__DIR__.'/../../Fixtures/PeopleMake/ExistingManage.php'));
        $this->make(['path' => 'people.actions.add']);
        $edited = str_replace('Manage people', 'Our custom title', File::get($this->output.'/'.$relative));
        File::put($this->output.'/'.$relative, $edited);
        $before = $this->snapshot();
        $dry = $this->make(['path' => 'people.actions.add', '--dry-run' => true]);
        $real = $this->make(['path' => 'people.actions.add']);

        $this->assertSame($dry, $real);
        $this->assertCount(1, $real['conflicts']);
        $this->assertSame($relative, $real['conflicts'][0]['file']);
        $this->assertSame([], $real['modified']);
        $this->assertSame($before, $this->snapshot());
    }

    protected function snapshot(): array
    {
        $files = [];
        foreach (File::allFiles($this->output) as $file) {
            $files[$file->getRelativePathname()] = $file->getContents();
        }
        ksort($files);

        return $files;
    }
}
