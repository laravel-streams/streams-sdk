<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Streams\Core\Support\Facades\Streams;
use Streams\Sdk\Tests\SdkTestCase;
use Symfony\Component\Process\Process;

class MakeLivewireTest extends SdkTestCase
{
    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), LivewireServiceProvider::class];
    }

    /**
     * Generate the posts components into a namespace unique
     * to this test, so the classes can be loaded in-process.
     */
    protected function generate(string $type = 'all'): string
    {
        $namespace = 'App\\Livewire\\T' . bin2hex(random_bytes(4));

        config(['livewire.class_namespace' => $namespace]);

        $this->artisan('streams:livewire', ['stream' => 'posts', '--type' => $type])->assertSuccessful();

        return $namespace;
    }

    protected function load(string $namespace, string $class): string
    {
        require_once $this->classFile($namespace, $class);

        return $namespace . '\\' . $class;
    }

    protected function classFile(string $namespace, string $class): string
    {
        return app_path(str_replace('\\', '/', substr($namespace, 4)) . '/' . $class . '.php');
    }

    public function test_it_generates_components_and_prints_routes()
    {
        config(['livewire.class_namespace' => 'App\\Livewire']);

        $this->artisan('streams:livewire', ['stream' => 'posts'])
            ->expectsOutputToContain('app/Livewire/PostsIndex.php')
            ->expectsOutputToContain("Route::get('/posts', \\App\\Livewire\\PostsIndex::class)->name('posts.index');")
            ->expectsOutputToContain("Route::get('/posts/{entry}/edit', \\App\\Livewire\\PostsForm::class)->name('posts.edit');")
            ->expectsOutputToContain("Route::get('/posts/{entry}', \\App\\Livewire\\PostsShow::class)->name('posts.show');")
            ->assertSuccessful();

        foreach (['Index', 'Form', 'Show'] as $type) {
            $class = $this->basePath("app/Livewire/Posts{$type}.php");
            $view = $this->basePath('resources/views/livewire/posts-' . strtolower($type) . '.blade.php');

            $this->assertFileExists($class);
            $this->assertFileExists($view);

            $lint = new Process([PHP_BINARY, '-l', $class]);
            $lint->run();
            $this->assertTrue($lint->isSuccessful(), $lint->getOutput() . $lint->getErrorOutput());

            $contents = file_get_contents($class);
            $this->assertStringContainsString('namespace App\\Livewire;', $contents);
            $this->assertStringNotContainsString('{{ ', $contents);
        }

        $form = file_get_contents($this->basePath('app/Livewire/PostsForm.php'));

        $this->assertStringContainsString("'id' => ['required', 'regex:", $form);
        $this->assertStringContainsString("'title' => ['required', 'max:120'],", $form);
        $this->assertStringContainsString("'status' => ['nullable', 'in:draft,live'],", $form);
        $this->assertStringContainsString("'views' => ['nullable', 'integer'", $form);
        $this->assertStringNotContainsString('secret', $form, 'Protected fields stay out of the form.');
    }

    public function test_it_generates_one_type()
    {
        $namespace = $this->generate('show');

        $this->assertFileExists($this->classFile($namespace, 'PostsShow'));
        $this->assertFileDoesNotExist($this->classFile($namespace, 'PostsIndex'));
        $this->assertFileDoesNotExist($this->classFile($namespace, 'PostsForm'));
    }

    public function test_it_refuses_to_overwrite_unless_forced()
    {
        config(['livewire.class_namespace' => 'App\\Livewire']);

        $this->artisan('streams:livewire', ['stream' => 'posts', '--type' => 'index'])->assertSuccessful();

        file_put_contents($this->basePath('app/Livewire/PostsIndex.php'), 'custom');

        $this->artisan('streams:livewire', ['stream' => 'posts', '--type' => 'index'])
            ->expectsOutputToContain('--force')
            ->assertFailed();

        $this->assertSame('custom', file_get_contents($this->basePath('app/Livewire/PostsIndex.php')));

        $this->artisan('streams:livewire', ['stream' => 'posts', '--type' => 'index', '--force' => true])->assertSuccessful();

        $this->assertStringContainsString('class PostsIndex', file_get_contents($this->basePath('app/Livewire/PostsIndex.php')));
    }

    public function test_it_rejects_unknown_streams_and_types()
    {
        $this->artisan('streams:livewire', ['stream' => 'missing'])
            ->expectsOutputToContain('Stream [missing] not found.')
            ->assertFailed();

        $this->artisan('streams:livewire', ['stream' => 'posts', '--type' => 'table'])
            ->expectsOutputToContain('Unknown type [table]')
            ->assertFailed();
    }

    public function test_the_index_component_lists_sorts_and_deletes_entries()
    {
        $index = $this->load($this->generate('index'), 'PostsIndex');

        Livewire::test($index)
            ->assertOk()
            ->assertSee('Hello World')
            ->assertSee('Second Post')
            ->assertDontSeeHtml('>hidden<')
            ->call('sortBy', 'title')
            ->assertSet('sortField', 'title')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'title')
            ->assertSet('sortDirection', 'desc')
            ->call('sortBy', 'not_a_field')
            ->assertSet('sortField', 'title')
            ->call('delete', 'hello-world')
            ->assertDontSee('Hello World');

        $this->assertNull(Streams::repository('posts')->find('hello-world'));
    }

    public function test_the_form_component_creates_and_updates_entries()
    {
        $form = $this->load($this->generate('form'), 'PostsForm');

        Livewire::test($form)
            ->set('id', 'new-post')
            ->set('title', 'A New Post')
            ->set('status', 'draft')
            ->set('views', 3)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('entry', 'new-post');

        $this->assertSame('A New Post', Streams::repository('posts')->find('new-post')->title);

        Livewire::test($form, ['entry' => 'hello-world'])
            ->assertSet('title', 'Hello World')
            ->assertSet('status', 'live')
            ->set('title', 'Hello Again')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Hello Again', Streams::repository('posts')->find('hello-world')->title);
    }

    public function test_the_form_component_validates()
    {
        $form = $this->load($this->generate('form'), 'PostsForm');

        Livewire::test($form)
            ->set('id', 'bad-post')
            ->set('title', '')
            ->set('status', 'archived')
            ->call('save')
            ->assertHasErrors(['title' => 'required', 'status' => 'in']);

        $this->assertNull(Streams::repository('posts')->find('bad-post'));
    }

    public function test_the_form_redirects_to_the_index_route_when_it_exists()
    {
        $namespace = $this->generate();
        $form = $this->load($namespace, 'PostsForm');
        $index = $this->load($namespace, 'PostsIndex');

        Route::get('/posts', $index)->name('posts.index');
        app('router')->getRoutes()->refreshNameLookups();

        Livewire::test($form, ['entry' => 'hello-world'])
            ->set('title', 'Redirected')
            ->call('save')
            ->assertRedirect('/posts');
    }

    public function test_the_show_component_renders_and_404s()
    {
        $show = $this->load($this->generate('show'), 'PostsShow');

        Livewire::test($show, ['entry' => 'hello-world'])
            ->assertOk()
            ->assertSee('Hello World')
            ->assertSeeHtml('>live<')
            ->assertDontSeeHtml('>hidden<');

        Livewire::test($show, ['entry' => 'nope'])->assertStatus(404);
    }
}
