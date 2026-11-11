<?php

namespace Streams\Sdk\Console\Onboard;

use Illuminate\Support\Str;

/**
 * Emits the API provider, public Blade/Alpine page, panel registration,
 * and optional env-admin login. Seeded files are skipped unless $force.
 */
final class SurfaceEmitter
{
    /**
     * @param  list<string>  $streams
     * @return array{files: list<string>, skipped: list<string>}
     */
    public function emit(string $base, bool $frontend, bool $api, bool $panel, string $auth, array $streams, bool $force = false): array
    {
        $base = rtrim($base, '/');
        $files = [];
        $skipped = [];

        if ($api) {
            $allow = $streams === [] ? 'todos,categories,tags' : implode(',', $streams);
            $this->write($base.'/app/Providers/StreamsServiceProvider.php', $this->provider($panel, $auth), $force, $files, $skipped);
            $this->ensureProviderListed($base, $force, $files, $skipped);
            $this->ensureEnv($base, $allow, $auth, $files, $skipped);
        }

        if ($frontend) {
            $this->write($base.'/app/Http/Controllers/TodosPageController.php', $this->controller(), $force, $files, $skipped);
            $this->write($base.'/resources/views/todos.blade.php', $this->blade(), $force, $files, $skipped);
            $this->write($base.'/resources/js/todos.js', $this->alpine(), $force, $files, $skipped);
            $this->pointHomeRoute($base, $files, $skipped);
            $this->ensureVite($base, $files, $skipped);
        }

        if ($panel) {
            $this->write($base.'/app/Livewire/PanelPage.php', $this->panelPage(), $force, $files, $skipped);
        }

        if ($panel && $auth === 'env-admin') {
            $this->write($base.'/app/Http/Controllers/AdminLoginController.php', $this->loginController(), $force, $files, $skipped);
            $this->write($base.'/app/Http/Middleware/EnsureEnvAdmin.php', $this->middleware(), $force, $files, $skipped);
            $this->write($base.'/resources/views/admin/login.blade.php', $this->loginBlade(), $force, $files, $skipped);
            $this->ensureAdminRoutes($base, $files, $skipped);
            $this->seedEnvState($base, $files, $skipped);
        }

        return ['files' => $files, 'skipped' => $skipped];
    }

    /**
     * @param  list<string>  $files
     * @param  list<string>  $skipped
     */
    protected function write(string $path, string $contents, bool $force, array &$files, array &$skipped): void
    {
        if (is_file($path) && ! $force) {
            $skipped[] = $path;

            return;
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $contents);
        $files[] = $path;
    }

    protected function panelMiddleware(string $auth): string
    {
        if ($auth === 'env-admin') {
            return "'web', \\App\\Http\\Middleware\\EnsureEnvAdmin::class";
        }

        return "'web'";
    }

    protected function provider(bool $panel, string $auth): string
    {
        $pages = <<<'PHP'
        $pages = [];
        foreach (glob(app_path('Domains/*/Panel/Pages/*.php')) ?: [] as $file) {
            $class = 'App\\Domains\\'.basename(dirname($file, 3)).'\\Panel\\Pages\\'.basename($file, '.php');
            if (class_exists($class)) {
                $pages[] = $class;
            }
        }
PHP;

        $panelCode = $panel ? <<<PHP
        if (class_exists(\\Streams\\Ui\\Support\\Facades\\UI::class)) {
{$pages}
            \\Streams\\Ui\\Support\\Facades\\UI::panel(
                \\Streams\\Ui\\Builders\\Panels\\Panel::make('admin')->default()->path('admin')->middleware([{$this->panelMiddleware($auth)}])->pages(\$pages)
            );
        }
PHP
        : '';

        $authNote = $auth === 'env-admin' ? "\n        // Panel login is the env-admin middleware on /admin." : '';

        return <<<PHP
<?php

namespace App\\Providers;

use Illuminate\\Support\\ServiceProvider;
use Streams\\Api\\Support\\Facades\\API;

class StreamsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('streams.api.enabled')) {
            API::routeEntries();
        }
{$panelCode}{$authNote}
    }
}

PHP;
    }

    /**
     * @param  list<string>  $files
     * @param  list<string>  $skipped
     */
    protected function ensureProviderListed(string $base, bool $force, array &$files, array &$skipped): void
    {
        $path = $base.'/bootstrap/providers.php';
        if (! is_file($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        if (str_contains($contents, 'StreamsServiceProvider')) {
            $skipped[] = $path;

            return;
        }

        if (str_contains($contents, 'AppServiceProvider::class,')) {
            $contents = str_replace(
                'AppServiceProvider::class,',
                "AppServiceProvider::class,\n    App\\Providers\\StreamsServiceProvider::class,",
                $contents
            );
        } else {
            $contents = str_replace(
                "return [\n",
                "return [\n    App\\Providers\\StreamsServiceProvider::class,\n",
                $contents
            );
        }
        file_put_contents($path, $contents);
        $files[] = $path;
    }

    /**
     * @param  list<string>  $files
     * @param  list<string>  $skipped
     */
    protected function ensureEnv(string $base, string $allow, string $auth, array &$files, array &$skipped): void
    {
        $path = $base.'/.env.example';
        if (! is_file($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        $extra = "\nSTREAMS_API_ENABLED=true\nSTREAMS_API_MIDDLEWARE=web\nSTREAMS_API_STREAMS={$allow}\n";
        if ($auth === 'env-admin') {
            $extra .= "ADMIN_EMAIL=admin@todos.test\nADMIN_PASSWORD=\n";
        }

        if (! str_contains($contents, 'STREAMS_API_ENABLED')) {
            file_put_contents($path, rtrim($contents).$extra);
            $files[] = $path;
        } else {
            $skipped[] = $path;
        }

        $env = $base.'/.env';
        if (is_file($env) && ! str_contains((string) file_get_contents($env), 'STREAMS_API_ENABLED')) {
            file_put_contents($env, rtrim((string) file_get_contents($env)).$extra);
            $files[] = $env;
        }
    }

    protected function controller(): string
    {
        return <<<'PHP'
<?php

namespace App\Http\Controllers;

class TodosPageController extends Controller
{
    public function __invoke()
    {
        return view('todos');
    }
}

PHP;
    }

    protected function blade(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Todos</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    @vite(['resources/js/todos.js'])
</head>
<body class="font-sans antialiased">
    <main class="max-w-xl mx-auto p-6" x-data="todosPage()" x-init="load()">
        <h1 class="text-2xl font-semibold">Todos</h1>
        <form class="mt-4 flex gap-2" @submit.prevent="create()">
            <input class="border rounded px-3 py-2 flex-1" x-model="title" placeholder="What needs doing?" required>
            <button class="border rounded px-3 py-2" type="submit">Add</button>
        </form>
        <ul class="mt-6 space-y-2">
            <template x-for="todo in todos" :key="todo.id">
                <li class="flex items-center gap-2">
                    <input type="checkbox" :checked="todo.completed" @change="toggle(todo)">
                    <span :class="todo.completed ? 'line-through' : ''" x-text="todo.title"></span>
                </li>
            </template>
        </ul>
        <p class="mt-6 text-sm"><a href="/admin">Panel</a></p>
    </main>
</body>
</html>
HTML;
    }

    protected function alpine(): string
    {
        return <<<'JS'
import { Client, CsrfMiddleware } from '@laravel-streams/api-client';

const client = new Client({
    baseURL: '/api/',
    middlewares: [new CsrfMiddleware()],
});

window.todosPage = function todosPage() {
    return {
        todos: [],
        title: '',
        async load() {
            const response = await client.entries.get('todos');
            const body = response.data || response;
            this.todos = body.data || body;
        },
        async create() {
            await client.entries.post('todos', { title: this.title, completed: false });
            this.title = '';
            await this.load();
        },
        async toggle(todo) {
            await client.entries.patch('todos', todo.id, { completed: ! todo.completed });
            await this.load();
        },
    };
};

JS;
    }

    /**
     * @param  list<string>  $files
     * @param  list<string>  $skipped
     */
    /**
     * @param  list<string>  $files
     * @param  list<string>  $skipped
     */
    protected function ensureVite(string $base, array &$files, array &$skipped): void
    {
        $path = $base.'/vite.config.js';
        if (! is_file($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        if (str_contains($contents, 'resources/js/todos.js')) {
            $skipped[] = $path;

            return;
        }

        $contents = str_replace(
            "input: ['resources/css/app.css', 'resources/js/app.js'],",
            "input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/todos.js'],",
            $contents
        );
        file_put_contents($path, $contents);
        $files[] = $path;

        $package = $base.'/package.json';
        if (is_file($package)) {
            $json = json_decode((string) file_get_contents($package), true);
            if (is_array($json)) {
                $json['dependencies']['@laravel-streams/api-client'] = 'file:../_packages/api-client';
                file_put_contents($package, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
                $files[] = $package;
            }
        }
    }

    protected function pointHomeRoute(string $base, array &$files, array &$skipped): void
    {
        $path = $base.'/routes/web.php';
        if (! is_file($path)) {
            return;
        }

        $contents = (string) file_get_contents($path);
        if (str_contains($contents, 'TodosPageController')) {
            $skipped[] = $path;

            return;
        }

        $contents = str_replace(
            "Route::get('/', function () {\n    return view('welcome');\n});",
            "Route::get('/', \\App\\Http\\Controllers\\TodosPageController::class);",
            $contents
        );
        file_put_contents($path, $contents);
        $files[] = $path;
    }

    protected function panelPage(): string
    {
        return <<<'PHP'
<?php

namespace App\Livewire;

use Streams\Ui\Livewire\Pages\PanelPage as StreamsPanelPage;

/**
 * Seeded base so generated manage pages can extend App\Livewire\PanelPage.
 * The real layout lives in streams/ui.
 */
abstract class PanelPage extends StreamsPanelPage
{
}

PHP;
    }

    protected function loginController(): string
    {
        return <<<'PHP'
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminLoginController extends Controller
{
    public function show()
    {
        return view('admin.login');
    }

    public function store(Request $request)
    {
        $state = $this->state();
        $email = $state['ADMIN_EMAIL'] ?? '';
        $password = $state['ADMIN_PASSWORD'] ?? '';

        if ($email === '' || $password === '' || ! hash_equals($email, (string) $request->input('email')) || ! Hash::check((string) $request->input('password'), $password)) {
            return back()->withErrors(['email' => 'Those credentials do not match.'])->onlyInput('email');
        }

        $request->session()->put('env_admin', true);

        return redirect('/admin');
    }

    /**
     * @return array<string, string>
     */
    protected function state(): array
    {
        $path = base_path('.env.state');
        if (! is_file($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value);
        }

        return $values;
    }
}

PHP;
    }

    protected function middleware(): string
    {
        return <<<'PHP'
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEnvAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('admin/login') || $request->is('admin/login/*')) {
            return $next($request);
        }

        if (! $request->session()->get('env_admin')) {
            return redirect('/admin/login');
        }

        return $next($request);
    }
}

PHP;
    }

    protected function loginBlade(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin login</title>
</head>
<body>
    <form method="post" action="/admin/login">
        @csrf
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Sign in</button>
        @if ($errors->any())
            <p>{{ $errors->first() }}</p>
        @endif
    </form>
</body>
</html>
HTML;
    }

    /**
     * @param  list<string>  $files
     * @param  list<string>  $skipped
     */
    protected function ensureAdminRoutes(string $base, array &$files, array &$skipped): void
    {
        $path = $base.'/routes/web.php';
        $contents = (string) file_get_contents($path);
        if (str_contains($contents, 'AdminLoginController')) {
            $skipped[] = $path;

            return;
        }

        $contents .= <<<'PHP'

Route::get('/admin/login', [\App\Http\Controllers\AdminLoginController::class, 'show']);
Route::post('/admin/login', [\App\Http\Controllers\AdminLoginController::class, 'store']);

PHP;
        file_put_contents($path, $contents);
        $files[] = $path;
    }

    /**
     * @param  list<string>  $files
     * @param  list<string>  $skipped
     */
    protected function seedEnvState(string $base, array &$files, array &$skipped): void
    {
        $path = $base.'/.env.state';
        if (is_file($path)) {
            $skipped[] = $path;

            return;
        }

        $hash = password_hash('password', PASSWORD_BCRYPT);
        file_put_contents($path, "ADMIN_EMAIL=admin@todos.test\nADMIN_PASSWORD={$hash}\n");
        $files[] = $path;
    }
}
