<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Streams\Core\Field\Field;
use Streams\Core\Stream\Stream;
use Streams\Core\Support\Facades\Streams;

/**
 * Generate Livewire 3 index, form, and show components for a stream.
 *
 * The components talk to the stream through streams/core (repository and
 * criteria), so they work with any source adapter. Nothing is registered
 * for you: the command prints the routes to add to routes/web.php.
 */
class MakeLivewire extends Command
{
    public const TYPES = ['index', 'form', 'show'];

    protected $signature = 'streams:livewire
        {stream : The stream ID}
        {--type=all : Component to generate: index, form, show, or all}
        {--force : Overwrite existing files}';

    protected $description = 'Generate Livewire index, form, and show components for a stream.';

    public function handle()
    {
        $id = (string) $this->argument('stream');

        if (! Streams::exists($id)) {
            $this->error("Stream [{$id}] not found.");

            return self::FAILURE;
        }

        $stream = Streams::make($id);

        $type = strtolower((string) $this->option('type'));
        $types = $type === 'all' ? self::TYPES : [$type];

        if (array_diff($types, self::TYPES)) {
            $this->error("Unknown type [{$type}]. Use index, form, show, or all.");

            return self::FAILURE;
        }

        $files = [];

        foreach ($types as $type) {
            $files[$type] = [
                'class' => $this->classPath($this->className($stream, $type)),
                'view' => $this->viewPath($this->viewName($stream, $type)),
            ];
        }

        if (! $this->option('force')) {
            foreach ($files as $paths) {
                foreach ($paths as $path) {
                    if (File::exists($path)) {
                        $this->error("[{$this->relative($path)}] already exists. Pass --force to overwrite.");

                        return self::FAILURE;
                    }
                }
            }
        }

        foreach ($files as $type => $paths) {
            File::ensureDirectoryExists(dirname($paths['class']));
            File::ensureDirectoryExists(dirname($paths['view']));

            File::put($paths['class'], $this->render("{$type}.class.stub", $this->classReplacements($stream, $type)));
            File::put($paths['view'], $this->render("{$type}.view.stub", $this->viewReplacements($stream)));

            $this->info("Created {$this->relative($paths['class'])}");
            $this->info("Created {$this->relative($paths['view'])}");
        }

        $this->newLine();
        $this->line('Add these routes to routes/web.php:');
        $this->newLine();

        foreach ($types as $type) {
            foreach ($this->routes($stream, $type) as $route) {
                $this->line($route);
            }
        }

        $this->newLine();
        $this->line('Full-page components render inside the Livewire layout (config livewire.layout).');

        return self::SUCCESS;
    }

    public function namespace(): string
    {
        return trim(Config::get('livewire.class_namespace', 'App\\Livewire'), '\\');
    }

    public function className(Stream $stream, string $type): string
    {
        return Str::studly($stream->id) . Str::studly($type);
    }

    public function viewName(Stream $stream, string $type): string
    {
        return 'livewire.' . Str::kebab($this->className($stream, $type));
    }

    protected function classPath(string $class): string
    {
        $namespace = $this->namespace();

        $relative = str_starts_with($namespace . '\\', 'App\\')
            ? substr($namespace, 4)
            : 'Livewire';

        return app_path(trim(str_replace('\\', '/', $relative) . '/' . $class . '.php', '/'));
    }

    protected function viewPath(string $view): string
    {
        return resource_path('views/' . str_replace('.', '/', $view) . '.blade.php');
    }

    protected function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }

    public function routes(Stream $stream, string $type): array
    {
        $uri = Str::kebab(Str::camel($stream->id));
        $class = '\\' . $this->namespace() . '\\' . $this->className($stream, $type);

        return match ($type) {
            'index' => ["Route::get('/{$uri}', {$class}::class)->name('{$stream->id}.index');"],
            'form' => [
                "Route::get('/{$uri}/create', {$class}::class)->name('{$stream->id}.create');",
                "Route::get('/{$uri}/{entry}/edit', {$class}::class)->name('{$stream->id}.edit');",
            ],
            'show' => ["Route::get('/{$uri}/{entry}', {$class}::class)->name('{$stream->id}.show');"],
        };
    }

    protected function render(string $stub, array $replacements): string
    {
        $contents = File::get(__DIR__ . '/stubs/livewire/' . $stub);

        return str_replace(array_keys($replacements), array_values($replacements), $contents);
    }

    protected function classReplacements(Stream $stream, string $type): array
    {
        $fields = $this->formFields($stream);

        return [
            '{{ namespace }}' => $this->namespace(),
            '{{ class }}' => $this->className($stream, $type),
            '{{ stream }}' => $stream->id,
            '{{ title }}' => addcslashes($this->title($stream), "'\\"),
            '{{ view }}' => $this->viewName($stream, $type),
            '{{ keyName }}' => $this->keyName($stream),
            '{{ sortField }}' => $this->keyName($stream),
            '{{ properties }}' => $this->properties($fields),
            '{{ rules }}' => $this->rules($stream, $fields),
            '{{ attributes }}' => $fields->map(fn (Field $field) => var_export($field->handle, true))->implode(', '),
        ];
    }

    protected function viewReplacements(Stream $stream): array
    {
        $columns = $this->displayFields($stream)->take(6);

        return [
            '{{ stream }}' => $stream->id,
            '{{ title }}' => e($this->title($stream)),
            '{{ keyName }}' => $this->keyName($stream),
            '{{ columnCount }}' => (string) ($columns->count() + 1),
            '{{ tableHeaders }}' => $columns->map(fn (Field $field) => $this->tableHeader($field))->implode("\n"),
            '{{ tableCells }}' => $columns->map(fn (Field $field) => $this->tableCell($field))->implode("\n"),
            '{{ formFields }}' => $this->formFields($stream)->map(fn (Field $field) => $this->formField($stream, $field))->implode("\n\n"),
            '{{ showFields }}' => $this->displayFields($stream)->map(fn (Field $field) => $this->showField($field))->implode("\n"),
        ];
    }

    protected function title(Stream $stream): string
    {
        return $stream->name ?: Str::title(str_replace('_', ' ', $stream->id));
    }

    protected function keyName(Stream $stream): string
    {
        return $stream->config('key_name', 'id');
    }

    protected function label(Field $field): string
    {
        return e($field->name ?: Str::title(str_replace('_', ' ', $field->handle)));
    }

    /**
     * Fields shown in the index table and on the show page.
     * Protected fields are never rendered.
     */
    protected function displayFields(Stream $stream)
    {
        return $stream->fields->filter(fn (Field $field) => ! $field->protected)->values();
    }

    /**
     * Fields bound to the form. Protected fields and a generated
     * key (integer or uuid) are left out.
     */
    public function formFields(Stream $stream)
    {
        $key = $this->keyName($stream);

        return $stream->fields
            ->filter(fn (Field $field) => ! $field->protected)
            ->filter(fn (Field $field) => ! ($field->handle === $key && in_array($field->type, ['integer', 'uuid'])))
            ->values();
    }

    protected function properties($fields): string
    {
        return $fields->map(function (Field $field) {

            $default = match ($field->type) {
                'integer', 'number', 'decimal' => 'null',
                'boolean' => 'false',
                'array', 'multiselect' => '[]',
                'date', 'datetime', 'time', 'image', 'file' => 'null',
                default => "''",
            };

            return "    public \${$field->handle} = {$default};";
        })->implode("\n");
    }

    /**
     * Laravel rules for each form field: the field's own rules from the
     * stream definition, plus type rules. Stream-aware rules (unique,
     * exists) need the repository presence verifier and are left out.
     */
    protected function rules(Stream $stream, $fields): string
    {
        return $fields->map(function (Field $field) {

            $defined = array_values(array_filter(
                $field->rules(),
                fn ($rule) => is_string($rule) && ! in_array(explode(':', $rule)[0], ['required', 'nullable', 'unique', 'exists'], true)
            ));

            $rules = [$field->isRequired() ? 'required' : 'nullable'];

            $rules = array_merge($rules, match ($field->type) {
                'email' => ['email'],
                'url' => ['url'],
                'integer' => ['integer'],
                'number', 'decimal' => ['numeric'],
                'boolean' => ['boolean'],
                'date', 'datetime' => ['date'],
                'array', 'multiselect' => ['array'],
                'select' => $field->config('options')
                    ? ['in:' . implode(',', array_keys((array) $field->config('options')))]
                    : [],
                default => [],
            }, $defined);

            if (! in_array($field->type, ['image', 'file', 'array', 'multiselect', 'boolean'])) {
                foreach (['max', 'min'] as $name) {
                    if (($value = $field->config($name)) && ! $this->containsRule($rules, $name)) {
                        $rules[] = "{$name}:{$value}";
                    }
                }
            }

            $rules = array_values(array_unique($rules));

            $rules = implode(', ', array_map(fn ($rule) => var_export($rule, true), $rules));

            return '            ' . var_export($field->handle, true) . " => [{$rules}],";
        })->implode("\n");
    }

    protected function containsRule(array $rules, string $name): bool
    {
        foreach ($rules as $rule) {
            if (explode(':', $rule)[0] === $name) {
                return true;
            }
        }

        return false;
    }

    protected function tableHeader(Field $field): string
    {
        $handle = $field->handle;
        $label = $this->label($field);

        return <<<BLADE
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <button type="button" wire:click="sortBy('{$handle}')" class="inline-flex items-center gap-1">
                                {$label}
                                @if (\$sortField === '{$handle}')
                                    <span aria-hidden="true">{{ \$sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
BLADE;
    }

    protected function tableCell(Field $field): string
    {
        return '                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">' . $this->valueMarkup($field, 50) . '</td>';
    }

    protected function showField(Field $field): string
    {
        $label = $this->label($field);
        $value = $this->valueMarkup($field);

        return <<<BLADE
                <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-gray-500">{$label}</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{$value}</dd>
                </div>
BLADE;
    }

    /**
     * Blade markup that renders one attribute safely whatever its runtime shape.
     */
    protected function valueMarkup(Field $field, ?int $limit = null): string
    {
        $value = "\$entry->{$field->handle}";

        return match ($field->type) {
            'boolean' => "{{ {$value} ? 'Yes' : 'No' }}",
            'image' => "@if ({$value})<img src=\"{{ \\Illuminate\\Support\\Facades\\Storage::url({$value}) }}\" alt=\"\" class=\"h-10 w-10 rounded object-cover\">@endif",
            'date' => "{{ {$value} instanceof \\DateTimeInterface ? {$value}->format('M j, Y') : {$value} }}",
            'datetime' => "{{ {$value} instanceof \\DateTimeInterface ? {$value}->format('M j, Y g:i A') : {$value} }}",
            default => $limit
                ? "{{ is_scalar({$value}) || is_null({$value}) ? \\Illuminate\\Support\\Str::limit((string) {$value}, {$limit}) : json_encode({$value}) }}"
                : "{{ is_scalar({$value}) || is_null({$value}) ? {$value} : json_encode({$value}) }}",
        };
    }

    protected function formField(Stream $stream, Field $field): string
    {
        $handle = $field->handle;
        $label = $this->label($field);
        $required = $field->required ? ' required' : '';
        $readonly = $handle === $this->keyName($stream) ? ' @if ($entry) readonly @endif' : '';
        $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm';
        $error = "@error('{$handle}') <p class=\"mt-1 text-sm text-red-600\">{{ \$message }}</p> @enderror";

        $control = match ($field->type) {
            'boolean' => "<input wire:model=\"{$handle}\" type=\"checkbox\" id=\"{$handle}\" class=\"h-4 w-4 rounded border-gray-300\">",
            'select' => $this->selectControl($field, $input, $required),
            'image', 'file' => "<input wire:model=\"{$handle}\" type=\"file\" id=\"{$handle}\"" . ($field->type === 'image' ? ' accept="image/*"' : '') . " class=\"mt-1 block w-full text-sm\">",
            'array', 'multiselect', 'object' => "<p class=\"mt-1 text-sm text-gray-500\">Edit {$label} in code; it is not a scalar value.</p>",
            default => in_array(data_get($field->input, 'type'), ['markdown', 'textarea', 'editor', 'wysiwyg'], true)
                ? "<textarea wire:model=\"{$handle}\" id=\"{$handle}\" rows=\"6\" class=\"{$input}\"{$required}></textarea>"
                : "<input wire:model=\"{$handle}\" type=\"{$this->inputType($field)}\" id=\"{$handle}\" class=\"{$input}\"{$required}{$readonly}>",
        };

        return <<<BLADE
                <div>
                    <label for="{$handle}" class="block text-sm font-medium text-gray-700">{$label}</label>
                    {$control}
                    {$error}
                </div>
BLADE;
    }

    protected function selectControl(Field $field, string $class, string $required): string
    {
        $options = collect((array) $field->config('options', []))
            ->map(fn ($label, $value) => '<option value="' . e($value) . '">' . e($label) . '</option>')
            ->implode('');

        return "<select wire:model=\"{$field->handle}\" id=\"{$field->handle}\" class=\"{$class}\"{$required}><option value=\"\">Choose…</option>{$options}</select>";
    }

    protected function inputType(Field $field): string
    {
        return match ($field->type) {
            'email' => 'email',
            'url' => 'url',
            'integer', 'number', 'decimal' => 'number',
            'date' => 'date',
            'datetime' => 'datetime-local',
            'time' => 'time',
            'color' => 'color',
            default => 'text',
        };
    }
}
