<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * The page menu: --menu lists the pages in the tree, picks a page and a
 * component by dot path, and saves or overrides the component's settings
 * into the tree. --menu --json and --no-interaction print the listing
 * without prompting. The save/override write only the tree, never PHP,
 * and a second run with the same answers changes nothing.
 */
class StreamsMakeMenuTest extends SdkTestCase
{
    protected string $fixture;

    /**
     * The component choice list for people.pages.manage, by dot path.
     *
     * @var list<string>
     */
    protected array $componentChoices = [
        'people.actions.add (action)',
        'people.actions.add.actions.more (action)',
        'people.actions.add.actions.more.actions.export (action)',
        'people.actions.add.actions.more.actions.import (action)',
        'people.pages.manage (page)',
        'people.table (table)',
        'people.table.actions.delete (action)',
        'people.table.actions.edit (action)',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = dirname(__DIR__, 3).'/resources/schemas/fixtures/people/streams.json';
    }

    public function test_menu_json_and_no_interaction_list_pages_and_components_by_path(): void
    {
        $code = Artisan::call('streams:make', [
            '--menu' => true,
            '--json' => true,
            '--tree' => $this->fixture,
        ]);
        $output = Artisan::output();
        $decoded = json_decode($output, true);

        $this->assertSame(0, $code, $output);
        $this->assertIsArray($decoded, $output);
        $this->assertArrayHasKey('pages', $decoded);

        $page = $decoded['pages'][0] ?? null;
        $this->assertIsArray($page, $output);
        $this->assertSame('people.pages.manage', $page['path']);

        $paths = array_column($page['components'], 'path');
        $this->assertContains('people.table', $paths);
        $this->assertContains('people.pages.manage', $paths);
        $this->assertContains('people.actions.add', $paths);

        $byPath = array_column($page['components'], 'kind', 'path');
        $this->assertSame('table', $byPath['people.table']);
        $this->assertSame('page', $byPath['people.pages.manage']);
        $this->assertSame('action', $byPath['people.actions.add']);

        $quiet = Artisan::call('streams:make', [
            '--menu' => true,
            '--no-interaction' => true,
            '--tree' => $this->fixture,
        ]);
        $quietOutput = Artisan::output();

        $this->assertSame(0, $quiet, $quietOutput);
        $this->assertSame($decoded, json_decode($quietOutput, true));
    }

    public function test_menu_saves_a_components_settings_with_faked_prompts(): void
    {
        $copy = $this->copyFixture();

        $this->artisan('streams:make', [
            '--menu' => true,
            '--tree' => $copy.'/streams.json',
        ])->expectsChoice('Which page?', 'people.pages.manage', ['people.pages.manage'])
            ->expectsChoice('Which component?', 'people.table (table)', $this->componentChoices)
            ->expectsChoice('Save or override?', 'save', ['save', 'override'])
            ->expectsQuestion('Table handle', 'table')
            ->expectsQuestion('Table name', 'Renamed via the menu')
            ->expectsQuestion('Columns', '{"name":{"handle":"name","label":"Name"}}')
            ->expectsQuestion('Row actions', '')
            ->assertExitCode(0);

        $node = AppTree::load($copy.'/streams.json')->node('people.table');
        $this->assertSame('Renamed via the menu', $node['name']);
        $this->assertSame([], $this->phpFiles($copy));

        File::deleteDirectory($copy);
    }

    public function test_menu_override_writes_the_settings_and_a_second_run_changes_nothing(): void
    {
        $copy = $this->copyFixture();

        $this->runMenu($copy, 'override', 'Managed people');

        $node = AppTree::load($copy.'/streams.json')->node('people.pages.manage');
        $this->assertSame('Managed people', $node['title']);
        $afterFirst = File::get($copy.'/streams/people.pages.manage.json');

        $this->runMenu($copy, 'override', 'Managed people');

        $this->assertSame($afterFirst, File::get($copy.'/streams/people.pages.manage.json'));
        $this->assertSame([], $this->phpFiles($copy));

        File::deleteDirectory($copy);
    }

    /**
     * Run the interactive menu from the copy in one faked-prompt pass:
     * pick the manage page, override the page component, answer the page
     * fields with the given title.
     */
    protected function runMenu(string $copy, string $action, string $title): void
    {
        $this->artisan('streams:make', [
            '--menu' => true,
            '--tree' => $copy.'/streams.json',
        ])->expectsChoice('Which page?', 'people.pages.manage', ['people.pages.manage'])
            ->expectsChoice('Which component?', 'people.pages.manage (page)', $this->componentChoices)
            ->expectsChoice('Save or override?', $action, ['save', 'override'])
            ->expectsQuestion('Page handle', 'manage')
            ->expectsQuestion('Page title', $title)
            ->expectsQuestion('Panel section', 'people')
            ->expectsQuestion('Table path', 'table')
            ->assertExitCode(0);
    }

    public function test_menu_save_writes_the_same_tree_as_flags_and_a_json_recipe(): void
    {
        $input = [
            'handle' => 'add',
            'label' => 'Menu label',
            'type' => 'modal',
            'form' => 'people.actions.add.form',
        ];

        $prompted = $this->copyFixture();
        $this->artisan('streams:make', [
            '--menu' => true,
            '--tree' => $prompted.'/streams.json',
        ])->expectsChoice('Which page?', 'people.pages.manage', ['people.pages.manage'])
            ->expectsChoice('Which component?', 'people.actions.add (action)', $this->componentChoices)
            ->expectsChoice('Save or override?', 'save', ['save', 'override'])
            ->expectsQuestion('Action handle', 'add')
            ->expectsQuestion('Action label', 'Menu label')
            ->expectsChoice('Action type', 'modal', ['button', 'link', 'modal', 'dropdown'])
            ->expectsQuestion('Link target', '')
            ->expectsQuestion('Modal form', 'people.actions.add.form')
            ->assertExitCode(0);

        $flagged = $this->copyFixture();
        $flagCode = Artisan::call('streams:make', [
            'path' => 'people.actions.add',
            '--tree' => $flagged.'/streams.json',
            '--no-interaction' => true,
            '--handle' => 'add',
            '--label' => 'Menu label',
            '--type' => 'modal',
            '--form' => 'people.actions.add.form',
        ]);
        $this->assertSame(0, $flagCode, Artisan::output());

        $recipeFile = $flagged.'/recipe.json';
        File::put($recipeFile, json_encode([
            'path' => 'people.actions.add',
            'input' => $input,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $fromRecipe = $this->copyFixture();
        $recipeCode = Artisan::call('streams:make', [
            'path' => 'people.actions.add',
            '--tree' => $fromRecipe.'/streams.json',
            '--no-interaction' => true,
            '--json' => $recipeFile,
        ]);
        $this->assertSame(0, $recipeCode, Artisan::output());

        $menuNode = AppTree::load($prompted.'/streams.json')->node('people.actions.add');
        $flagNode = AppTree::load($flagged.'/streams.json')->node('people.actions.add');
        $recipeNode = AppTree::load($fromRecipe.'/streams.json')->node('people.actions.add');

        $this->assertSame('Menu label', $menuNode['label']);
        $this->assertSame($menuNode, $flagNode);
        $this->assertSame($menuNode, $recipeNode);
        $this->assertSame(
            File::get($prompted.'/streams/people.actions.add.json'),
            File::get($flagged.'/streams/people.actions.add.json'),
        );
        $this->assertSame(
            File::get($prompted.'/streams/people.actions.add.json'),
            File::get($fromRecipe.'/streams/people.actions.add.json'),
        );
        $this->assertSame([], $this->phpFiles($prompted));

        File::deleteDirectory($prompted);
        File::deleteDirectory($flagged);
        File::deleteDirectory($fromRecipe);
    }

    public function test_menu_refuses_a_path_recipe_or_authoring_flags(): void
    {
        foreach ([
            ['path' => 'people.table'],
            ['--json' => 'recipe.json'],
            ['--handle' => 'table'],
        ] as $extra) {
            $code = Artisan::call('streams:make', [
                '--menu' => true,
                '--tree' => $this->fixture,
            ] + $extra);
            $output = Artisan::output();
            $decoded = json_decode($output, true);

            $this->assertNotSame(0, $code, $output);
            $this->assertIsArray($decoded, $output);
            $this->assertSame('menu', $decoded['field'], $output);
        }
    }

    protected function copyFixture(): string
    {
        $dest = sys_get_temp_dir().'/streams-sdk-menu-'.uniqid();

        File::copyDirectory(dirname($this->fixture), $dest);

        return $dest;
    }

    /**
     * @return list<string>
     */
    protected function phpFiles(string $root): array
    {
        $files = [];

        foreach (File::allFiles($root) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getRelativePathname();
            }
        }

        sort($files);

        return $files;
    }
}