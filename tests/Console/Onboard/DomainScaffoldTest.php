<?php

namespace Streams\Sdk\Tests\Console\Onboard;

use Illuminate\Support\Facades\Config;
use Streams\Sdk\Console\Onboard\DomainScaffold;
use Streams\Sdk\Console\Onboard\OnboardApplier;
use Streams\Sdk\Console\Onboard\OnboardRecipe;
use Streams\Sdk\Tests\SdkTestCase;

class DomainScaffoldTest extends SdkTestCase
{
    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/streams-sdk-domain-'.uniqid();
        mkdir($this->root, 0777, true);

        Config::set('streams.core.default_source', 'filebase');
        Config::set('streams.core.sources.filebase.formats', [
            'json' => \Filebase\Format\Json::class,
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);

        parent::tearDown();
    }

    public function test_emits_domain_model_repository_lang_and_valid_stream(): void
    {
        $result = (new DomainScaffold)->emit('Todos', $this->root);

        $this->assertSame('Todos', $result['domain']);
        $this->assertSame('todos', $result['handle']);
        $this->assertSame('Todo', $result['entity']);
        $this->assertTrue($result['validation']['valid'], implode('; ', $result['validation']['errors']));

        $this->assertFileExists($this->root.'/app/Domains/Todos/Models/Todo.php');
        $this->assertFileExists($this->root.'/app/Domains/Todos/Repositories/Todos.php');
        $this->assertFileExists($this->root.'/lang/en/domains/todos.php');
        $this->assertFileExists($this->root.'/lang/es/domains/todos.php');
        $this->assertFileExists($this->root.'/lang/pt_BR/domains/todos.php');
        $this->assertFileExists($this->root.'/streams/todos.json');
        $this->assertFileExists($this->root.'/streams.json');

        $stream = json_decode((string) file_get_contents($this->root.'/streams/todos.json'), true);
        $this->assertSame('todos', $stream['id']);
        $this->assertSame('App\\Domains\\Todos\\Models\\Todo', $stream['config']['abstract']);
        $this->assertSame('App\\Domains\\Todos\\Repositories\\Todos', $stream['config']['repository']);
        $this->assertSame('filebase', $stream['config']['source']['type']);
        $this->assertContains('title', array_column($stream['fields'], 'handle'));
        $this->assertContains('completed', array_column($stream['fields'], 'handle'));

        $tree = json_decode((string) file_get_contents($this->root.'/streams.json'), true);
        $this->assertSame(['$ref' => 'streams/todos.json'], $tree['streams']['todos']);
    }

    public function test_skips_existing_files_unless_forced(): void
    {
        $scaffold = new DomainScaffold;
        $first = $scaffold->emit('Todos', $this->root);
        $second = $scaffold->emit('Todos', $this->root, force: false);
        $forced = $scaffold->emit('Todos', $this->root, force: true);

        $this->assertNotEmpty($first['files']);
        $this->assertEmpty($second['files']);
        $this->assertNotEmpty($second['skipped']);
        $this->assertNotEmpty($forced['files']);
    }

    public function test_onboard_applier_applies_domain_step(): void
    {
        $recipe = OnboardRecipe::fromArray([
            'surface' => 'laravel',
            'frontend' => false,
            'panels' => 0,
            'multi_tenancy' => false,
            'api' => false,
            'discover_streams_api' => false,
            'streams_api_base_url' => null,
            'sample_domain' => 'Todos',
            'created_at' => '2026-01-01T00:00:00+00:00',
        ]);

        $results = (new OnboardApplier)->apply($recipe, $this->root, true);
        $domain = collect($results)->firstWhere('id', 'domain-Todos');

        $this->assertNotNull($domain);
        $this->assertSame('applied', $domain['status']);
        $this->assertFileExists($this->root.'/streams/todos.json');
    }

    public function test_onboard_applier_emits_manage_ui_when_panels_requested(): void
    {
        $recipe = OnboardRecipe::fromArray([
            'surface' => 'laravel',
            'frontend' => true,
            'panels' => 1,
            'multi_tenancy' => false,
            'api' => false,
            'discover_streams_api' => false,
            'streams_api_base_url' => null,
            'sample_domain' => 'Todos',
            'created_at' => '2026-01-01T00:00:00+00:00',
        ]);

        $results = (new OnboardApplier)->apply($recipe, $this->root, true);
        $ui = collect($results)->firstWhere('id', 'require-streams-ui');

        $this->assertNotNull($ui);
        $this->assertSame('applied', $ui['status'], $ui['note'] ?? '');

        $panelRoot = $this->root.'/app/Domains/Todos/Panel';
        $written = [];
        if (is_dir($panelRoot)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($panelRoot));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $written[] = substr($file->getPathname(), strlen($this->root) + 1);
                }
            }
        }

        $this->assertNotEmpty($written, 'detail='.json_encode($ui['detail'] ?? null));
        $this->assertTrue(
            collect($written)->contains(fn (string $path) => str_contains($path, '/Pages/')),
            'pages missing: '.implode(', ', $written),
        );
        $this->assertTrue(
            collect($written)->contains(fn (string $path) => str_contains($path, '/Tables/')),
            'tables missing: '.implode(', ', $written),
        );
        $this->assertTrue(
            collect($written)->contains(fn (string $path) => str_contains($path, '/Actions/')),
            'actions missing: '.implode(', ', $written),
        );
    }

    protected function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path.'/'.$item;
            is_dir($full) ? $this->deleteTree($full) : unlink($full);
        }

        rmdir($path);
    }
}
