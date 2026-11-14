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
            // App-owned after first emit: never overwrite these, even with --force.
            $this->writeOnce($base.'/resources/views/todos.blade.php', $this->blade(), $files, $skipped);
            $this->writeOnce($base.'/resources/js/todos.js', $this->alpine(), $files, $skipped);
            $this->pointHomeRoute($base, $files, $skipped);
            $this->ensureVite($base, $files, $skipped);
        }

        if ($panel) {
            $this->write($base.'/app/Livewire/PanelPage.php', $this->panelPage(), $force, $files, $skipped);
        }

        if ($panel && $auth === 'env-admin') {
            $this->write($base.'/app/Livewire/BasicPage.php', $this->basicPage(), $force, $files, $skipped);
            $this->write($base.'/app/Domains/Admin/Panel/Forms/LoginForm.php', $this->loginForm(), $force, $files, $skipped);
            $this->write($base.'/app/Domains/Admin/Panel/Pages/Login.php', $this->loginPage(), $force, $files, $skipped);
            $this->write($base.'/app/Http/Middleware/EnsureEnvAdmin.php', $this->middleware(), $force, $files, $skipped);
            $this->write($base.'/resources/views/layouts/admin-login.blade.php', $this->loginLayout(), $force, $files, $skipped);
            $this->write($base.'/resources/views/livewire/admin/login.blade.php', $this->loginLivewireView(), $force, $files, $skipped);
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

    /**
     * Seed a file only when it does not exist yet (ignores --force).
     *
     * @param  list<string>  $files
     * @param  list<string>  $skipped
     */
    protected function writeOnce(string $path, string $contents, array &$files, array &$skipped): void
    {
        if (is_file($path)) {
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
            if ($class === \\App\\Domains\\Admin\\Panel\\Pages\\Login::class) {
                continue;
            }
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
{{-- Public todos page is app-owned after first onboard emit. Customize this file in your application. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Todos</title>
    @vite(['resources/css/app.css', 'resources/js/todos.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <div class="mx-auto max-w-lg px-4 py-14" x-data="todosPage" x-init="load()">
        <h1 class="text-2xl font-semibold text-slate-900">Todos</h1>
        <p class="mt-2 text-sm text-slate-600">Replace resources/views/todos.blade.php with your public UI.</p>
    </div>
</body>
</html>
HTML;
    }

    protected function alpine(): string
    {
        return <<<'JS'
// Public todos Alpine entry is app-owned after first onboard emit. Customize resources/js/todos.js.
import Alpine from 'alpinejs';
import { Client, CsrfMiddleware } from '@laravel-streams/api-client';

const client = new Client({
    baseURL: '/api/',
    middlewares: [new CsrfMiddleware()],
});

Alpine.data('todosPage', () => ({
    todos: [],
    async load() {
        const response = await client.entries.get('todos');
        const payload = response?.data ?? response;
        this.todos = Array.isArray(payload?.data) ? payload.data : [];
    },
}));

window.Alpine = Alpine;
Alpine.start();

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
                $json['dependencies']['alpinejs'] = '^3.14.0';
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

    protected function basicPage(): string
    {
        return <<<'PHP'
<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Contracts\View\View;
use Streams\Ui\Livewire\Forms\InteractsWithForms;
use Streams\Ui\Builders\Concerns\HasComponents;

/**
 * Lightweight Livewire host for non-panel pages (e.g. login) without table/action chrome.
 */
abstract class BasicPage extends Component
{
    use HasComponents;
    use InteractsWithForms;

    public ?array $data = [];

    protected static string $view;

    protected static string $layout = 'layouts.admin-login';

    public function render(): View
    {
        return view(static::$view)->layout(static::$layout);
    }
}

PHP;
    }

    protected function loginForm(): string
    {
        return <<<'PHP'
<?php

namespace App\Domains\Admin\Panel\Forms;

use Streams\Ui\Builders\Forms\Form;
use Illuminate\Support\Facades\Hash;
use Streams\Ui\Builders\Actions\Action;
use Streams\Ui\Builders\Inputs\TextInput;

class LoginForm extends Form
{
    protected function setUp(): void
    {
        $name = $this->getName();

        $this
            ->components([
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->autofocus(),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required(),
                Action::make('submit')
                    ->label('Sign in')
                    ->tag('button')
                    ->loadingIndicator()
                    ->mergeHtmlAttributes([
                        'type' => 'submit',
                        'class' => 'w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 sm:w-auto',
                    ]),
            ])
            ->mergeHtmlAttributes([
                'class' => 'mt-6 flex flex-col gap-4',
                'wire:submit.prevent' => "handleForm('{$name}', 'attempt')",
            ]);
    }

    /**
     * @return array{email: string, password: string}
     */
    public static function defaultState(): array
    {
        return [
            'email' => '',
            'password' => '',
        ];
    }

    /**
     * Authenticate the operator. Subclasses override this method to swap guards
     * without redefining email/password fields or submit wiring.
     */
    public function attempt(array $payload = []): mixed
    {
        $livewire = $this->getLivewire();
        $validData = $this->getRawState();

        $livewire->resetErrorBag('email');

        $state = static::envState();
        $email = $state['ADMIN_EMAIL'] ?? '';
        $password = $state['ADMIN_PASSWORD'] ?? '';

        if (
            $email === ''
            || $password === ''
            || ! hash_equals($email, (string) ($validData['email'] ?? ''))
            || ! Hash::check((string) ($validData['password'] ?? ''), $password)
        ) {
            $livewire->addError('email', 'Those credentials do not match.');

            return null;
        }

        session(['env_admin' => true]);

        $livewire->redirect('/admin');

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected static function envState(): array
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

    protected function loginPage(): string
    {
        return <<<'PHP'
<?php

namespace App\Domains\Admin\Panel\Pages;

use App\Livewire\BasicPage;
use App\Domains\Admin\Panel\Forms\LoginForm;

class Login extends BasicPage
{
    protected static string $view = 'livewire.admin.login';

    public function boot(): void
    {
        LoginForm::register($this);
    }

    public function mount(): void
    {
        $this->getForm('login-form')->fill(LoginForm::defaultState());
    }

    public function getComponents(): array
    {
        return [
            $this->getForm('login-form'),
        ];
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

    protected function loginLayout(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin</title>
    {!! \Streams\Core\Support\Facades\Assets::collection('styles')->tags() !!}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-lg flex-col px-4 py-10 sm:py-14">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            {!! $slot !!}
        </div>

        <p class="mt-6 text-center text-sm text-slate-500">
            <a class="font-medium text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900 hover:decoration-slate-500" href="/">Back to todos</a>
        </p>
    </main>
    @livewireScriptConfig
    {!! \Streams\Core\Support\Facades\Assets::collection('scripts')->tags() !!}
    @livewireScripts
</body>
</html>
HTML;
    }

    protected function loginLivewireView(): string
    {
        return <<<'HTML'
<div>
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Admin</h1>
    <p class="mt-1 text-sm text-slate-600">Panel sign-in for the Todos demo.</p>

    @error('email')
        <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ $message }}
        </p>
    @enderror

    @foreach ($this->getComponents() as $component)
        {!! $component->render() !!}
    @endforeach
</div>
HTML;
    }

    /**
     * @param  list<string>  $files
     * @param  list<string>  $skipped
     */
    protected function ensureAdminRoutes(string $base, array &$files, array &$skipped): void
    {
        $path = $base.'/routes/web.php';

        // pointHomeRoute() guards this file and this one did not, so an app
        // without routes/web.php - a Laravel 11+ slim skeleton, a package test
        // app, or an API-only install - fataled the whole apply step instead of
        // reporting the route wiring as skipped.
        if (! is_file($path)) {
            $skipped[] = $path;

            return;
        }

        $contents = (string) file_get_contents($path);
        if (str_contains($contents, 'Domains\\Admin\\Panel\\Pages\\Login')) {
            $skipped[] = $path;

            return;
        }

        $contents .= <<<'PHP'

Route::get('/admin/login', \App\Domains\Admin\Panel\Pages\Login::class);

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

        $hash = password_hash('Password123123', PASSWORD_BCRYPT);
        file_put_contents($path, "ADMIN_EMAIL=admin@todos.test\nADMIN_PASSWORD={$hash}\n");
        $files[] = $path;
    }
}
