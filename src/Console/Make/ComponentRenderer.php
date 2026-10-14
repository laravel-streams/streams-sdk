<?php

namespace Streams\Sdk\Console\Make;

use Illuminate\Support\Str;
use Streams\Sdk\Support\AppTree;

/** The people manage slice uses the same builder shape as the pinned references. */
class ComponentRenderer
{
    public function __construct(protected Paths $paths, protected ?StreamLock $lock = null) {}

    public function render(AppTree $tree, string $path, string $type, string $namespace, string $class, string $fallback): string
    {
        $node = $tree->node($path);
        $stream = explode('.', $path)[0];
        $body = null;
        $imports = [];
        $extends = '';

        if ($type === 'table') {
            $imports = ['Streams\\Ui\\Builders\\Tables\\Columns\\TextColumn', 'Streams\\Ui\\Builders\\Tables\\Table'];
            $extends = 'Table';
            $columns = [];
            foreach ($node['columns'] ?? [] as $handle => $column) {
                $handle = $column['handle'] ?? $handle;
                $label = $column['label'] ?? Str::headline($handle);
                $columns[] = '                TextColumn::make('.var_export($handle, true).')'."\n".
                    '                    ->label('.var_export($label, true).'),';
            }
            $columns = implode("\n", $columns);
            $query = var_export($stream, true);
            $body = <<<PHP
    protected function setUp(): void
    {
        \$this
            ->columns([
{$columns}
            ])
            ->query(entries({$query}));
    }
PHP;
        } elseif ($type === 'page' && isset($node['table']) && is_string($node['table'])) {
            $tablePath = str_contains($node['table'], '.') ? $node['table'] : $stream.'.'.$node['table'];
            if (! $tree->has($tablePath) || $tree->type($tablePath) !== 'table') {
                return $fallback;
            }
            $table = $this->classForPath($tree, $tablePath);
            $imports = [
                $this->qualified($tree, $tablePath, 'table'),
                'App\\Livewire\\PanelPage',
                'Streams\\Ui\\Builders\\Containers\\Container',
                'Streams\\Ui\\Builders\\Headings\\Heading',
                'Streams\\Ui\\Livewire\\Tables\\InteractsWithTables',
            ];
            $extends = 'PanelPage';
            $actions = '';
            $register = '';
            $trait = '';
            $actionPath = $tree->has($stream.'.actions.add') ? $stream.'.actions.add' : null;
            if ($actionPath === null) {
                foreach ($tree->types() as $candidate => $candidateType) {
                    if ($candidateType === 'action' && str_starts_with($candidate, $stream.'.actions.') && ($tree->node($candidate)['type'] ?? null) === 'modal') {
                        $actionPath = $candidate;
                        break;
                    }
                }
            }
            if ($actionPath !== null) {
                $action = $this->classForPath($tree, $actionPath);
                $imports[] = $this->qualified($tree, $actionPath, 'action');
                $imports[] = 'Streams\\Ui\\Livewire\\Actions\\InteractsWithActions';
                $trait = "    use InteractsWithActions;\n";
                $register = "        {$action}::register(\$this);\n";
                $actions = "                            {$action}::resolve(),\n";
            }
            $title = var_export($node['title'] ?? $stream, true);
            $body = <<<PHP
{$trait}    use InteractsWithTables;

    public function boot(): void
    {
{$register}        {$table}::register(\$this);
    }

    public function getComponents(): array
    {
        return [
            Container::make('group')
                ->components([
                    Heading::make({$title})
                        ->actions([
{$actions}                        ]),
                    {$table}::resolve(),
                ]),
        ];
    }
PHP;
        } elseif ($type === 'action' && ($node['type'] ?? null) === 'modal' && $tree->has($path.'.form')) {
            $form = $this->classForPath($tree, $path.'.form');
            $imports = [$this->qualified($tree, $path.'.form', 'form'), 'Streams\\Ui\\Builders\\Actions\\MountableAction'];
            $extends = 'MountableAction';
            $label = var_export($node['label'] ?? $path, true);
            $body = <<<PHP
    protected function setUp(): void
    {
        \$name = \$this->getName();
        {$form}::register(\$this->getLivewire());

        \$this
            ->label({$label})
            ->modalWidth('xl')
            ->modalHeading({$label})
            ->modalComponents(fn (): array => [{$form}::resolve()])
            ->mergeHtmlAttributes([
                'wire:click' => 'mountAction(\''.\$name.'\')',
            ]);
    }
PHP;
        } elseif ($type === 'form') {
            $imports = ['Streams\\Ui\\Builders\\Forms\\Form'];
            $extends = 'Form';
            $fields = [];
            foreach ($node['sections'] ?? [] as $section => $data) {
                $sectionPath = $path.'.sections.'.$section;
                $data = $tree->has($sectionPath) ? $tree->node($sectionPath) : $data;
                foreach ($data['fields'] ?? [] as $field) {
                    $fields[] = '                '.var_export($field['handle'], true).',';
                }
            }
            $fields = implode("\n", $fields);
            $streamName = var_export($stream, true);
            $body = <<<PHP
    protected function setUp(): void
    {
        \$this
            ->stream({$streamName})
            ->fields([
{$fields}
            ]);
    }
PHP;
        }

        if ($body === null) {
            return $fallback;
        }

        // Retain the node metadata methods and ownership tag from the build stage.
        $imports[] = 'Streams\\Sdk\\Attributes\\Generated';
        sort($imports);
        $uses = implode("\n", array_map(fn (string $import): string => 'use '.$import.';', $imports));
        $source = str_replace('use Streams\\Sdk\\Attributes\\Generated;', $uses, $fallback);
        $source = str_replace('class '.$class."\n{", 'class '.$class.' extends '.$extends."\n{\n".$body."\n", $source);
        $source = str_replace('public function label(): string', 'public function generatedLabel(): string', $source);

        return $source;
    }

    public function qualified(AppTree $tree, string $path, string $type): string
    {
        $class = $this->classForPath($tree, $path);
        $relative = $this->lockedFile($path) ?? $this->paths->resolve($type, Str::studly(explode('.', $path)[0]), $class);

        if ($relative === null) {
            throw new \RuntimeException('No output path for ['.$path.'].');
        }

        return $this->paths->namespaceFor($relative).'\\'.$class;
    }

    protected function classForPath(AppTree $tree, string $path): string
    {
        $file = $this->lockedFile($path);

        return $file === null ? $tree->classForPath($path) : pathinfo($file, PATHINFO_FILENAME);
    }

    protected function lockedFile(string $path): ?string
    {
        $files = array_keys($this->lock?->entries[$path] ?? []);

        return count($files) === 1 && StreamLock::validFile($files[0]) ? $files[0] : null;
    }
}
