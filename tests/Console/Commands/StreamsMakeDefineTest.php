<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Console\Make\Kinds\BuiltInKinds;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * Prompts, flags, and a JSON recipe edit the same JSON tree.
 * That edit does not write PHP. --list prints dot paths.
 * --no-interaction names a missing field and does not prompt.
 */
class StreamsMakeDefineTest extends SdkTestCase
{
    protected string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = dirname(__DIR__, 3).'/resources/schemas/fixtures/people/streams.json';
    }

    public function test_prompts_flags_and_a_json_recipe_write_the_same_tree(): void
    {
        $input = [
            'handle' => 'add',
            'label' => 'Add person',
            'type' => 'modal',
            'form' => 'people.actions.add.form',
        ];

        $prompted = $this->copyFixture();
        $this->artisan('streams:make', [
            'path' => 'people.actions.add',
            '--tree' => $prompted.'/streams.json',
        ])->expectsQuestion('Action handle', 'add')
            ->expectsQuestion('Action label', 'Add person')
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
            '--label' => 'Add person',
            '--type' => 'modal',
            '--form' => 'people.actions.add.form',
        ]);

        $this->assertSame(0, $flagCode, Artisan::output());

        $recipeFile = $flagged.'/recipe.json';
        file_put_contents($recipeFile, json_encode([
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

        $promptTree = AppTree::load($prompted.'/streams.json')->nodes();
        $flagTree = AppTree::load($flagged.'/streams.json')->nodes();
        $recipeTree = AppTree::load($fromRecipe.'/streams.json')->nodes();

        $this->assertSame($input['handle'], $promptTree['people.actions.add']['handle']);
        $this->assertSame($input['label'], $promptTree['people.actions.add']['label']);
        $this->assertSame($input['type'], $promptTree['people.actions.add']['type']);
        $this->assertSame($input['form'], $promptTree['people.actions.add']['form']);
        $this->assertSame($promptTree, $flagTree);
        $this->assertSame($promptTree, $recipeTree);

        File::deleteDirectory($prompted);
        File::deleteDirectory($flagged);
        File::deleteDirectory($fromRecipe);
    }

    public function test_list_prints_kinds_and_dot_paths_and_json_is_machine_readable(): void
    {
        $bare = Artisan::call('streams:make', [
            '--tree' => $this->fixture,
        ]);
        $bareText = Artisan::output();

        $this->assertSame(0, $bare, $bareText);
        $this->assertStringContainsString('people.actions.add', $bareText);
        $this->assertStringContainsString('people.pages.manage', $bareText);

        $code = Artisan::call('streams:make', [
            '--list' => true,
            '--tree' => $this->fixture,
        ]);
        $text = Artisan::output();

        $this->assertSame(0, $code, $text);
        $this->assertStringContainsString('action', $text);
        $this->assertStringContainsString('people.actions.add', $text);
        $this->assertStringContainsString('people.actions.{handle}', $text);
        $this->assertStringContainsString('people.pages.manage', $text);
        $this->assertStringContainsString('page', $text);
        $this->assertStringContainsString('table', $text);

        $jsonCode = Artisan::call('streams:make', [
            '--list' => true,
            '--json' => true,
            '--tree' => $this->fixture,
        ]);
        $encoded = Artisan::output();
        $decoded = json_decode($encoded, true);

        $this->assertSame(0, $jsonCode, $encoded);
        $this->assertIsArray($decoded, $encoded);
        $this->assertArrayHasKey('kinds', $decoded);

        $action = null;

        foreach ($decoded['kinds'] as $kind) {
            if ($kind['kind'] === 'action') {
                $action = $kind;
            }
        }

        $this->assertNotNull($action);
        $this->assertContains('people.actions.add', $action['nodes']);
        $this->assertContains('people.actions.{handle}', $action['paths']);
        $this->assertSame('label', $action['inputs'][1]['field']);
        $this->assertSame('label', $action['inputs'][1]['flag']);
    }

    public function test_no_interaction_with_a_missing_required_value_exits_with_a_json_field_error(): void
    {
        $copy = $this->copyFixture();
        $before = file_get_contents($copy.'/streams/people.actions.add.json');

        $code = Artisan::call('streams:make', [
            'path' => 'people.actions.add',
            '--tree' => $copy.'/streams.json',
            '--no-interaction' => true,
        ]);
        $output = Artisan::output();
        $decoded = json_decode($output, true);

        $this->assertNotSame(0, $code);
        $this->assertIsArray($decoded, $output);
        $this->assertSame('label', $decoded['field']);
        $this->assertSame('label', $decoded['errors'][0]['field']);
        $this->assertStringContainsString('label', $decoded['errors'][0]['message']);
        $this->assertSame($before, file_get_contents($copy.'/streams/people.actions.add.json'));

        File::deleteDirectory($copy);
    }

    public function test_tree_editing_does_not_change_php_files(): void
    {
        $copy = $this->copyFixture();
        $output = $copy.'/generated';
        File::ensureDirectoryExists($output.'/app/Domains/People/Panel/Actions');
        $php = $output.'/app/Domains/People/Panel/Actions/PeopleActionsAdd.php';
        File::put($php, "<?php\n// sentinel\n");
        $before = File::get($php);

        $code = Artisan::call('streams:make', [
            'path' => 'people.actions.add',
            '--tree' => $copy.'/streams.json',
            '--output' => $output,
            '--no-interaction' => true,
            '--handle' => 'add',
            '--label' => 'Add person',
            '--type' => 'modal',
            '--form' => 'people.actions.add.form',
        ]);

        $this->assertSame(0, $code, Artisan::output());
        $this->assertSame($before, File::get($php));
        $this->assertFileDoesNotExist($output.'/streams.lock');
        $this->assertSame(['app/Domains/People/Panel/Actions/PeopleActionsAdd.php'], $this->phpFiles($output));

        $node = AppTree::load($copy.'/streams.json')->node('people.actions.add');
        $this->assertSame('Add person', $node['label']);
        $this->assertSame('modal', $node['type']);

        File::deleteDirectory($copy);
    }

    public function test_every_prompt_has_a_flag(): void
    {
        foreach (BuiltInKinds::all() as $kind) {
            foreach ($kind->inputs as $field) {
                if ($field->prompt === null) {
                    continue;
                }

                $this->assertNotNull($field->flag, $kind->name.'.'.$field->key);
                $this->assertNotSame('', $field->flag, $kind->name.'.'.$field->key);
            }
        }
    }

    protected function copyFixture(): string
    {
        $dest = sys_get_temp_dir().'/streams-sdk-define-'.uniqid();

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
