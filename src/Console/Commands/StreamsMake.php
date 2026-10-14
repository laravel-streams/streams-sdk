<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Streams\Sdk\Console\Make\BuildStage;
use Streams\Sdk\Console\Make\DefinitionCollector;
use Streams\Sdk\Console\Make\KindCatalog;
use Streams\Sdk\Console\Make\Kinds\InputField;
use Streams\Sdk\Console\Make\Kinds\Kind;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Console\Make\Kinds\KindRegistryException;
use Streams\Sdk\Console\Make\Paths;
use Streams\Sdk\Console\Make\RenameStage;
use Streams\Sdk\Console\Make\TreeEditException;
use Streams\Sdk\Console\Make\TreeEditor;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Support\AppTreeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Make a node in the app JSON tree, and build generated code from that tree.
 *
 * Prompts, flags, and a JSON recipe fill one definition and edit the tree
 * at a dot path (people.actions.add). They do not write PHP. --dry-run and
 * --json with no recipe still plan and apply the build. This is the only
 * build command. There is no streams:build command and no per-component
 * make:* command.
 */
class StreamsMake extends Command
{
    protected $signature = 'streams:make
        {path? : Dot path to make or build, such as people.actions.add.}
        {from? : Source dot path for rename mode.}
        {to? : Destination dot path for rename mode.}
        {--list : Show makeable kinds and the dot paths where they fit.}
        {--tree= : App streams.json. Defaults to config streams.sdk.make.tree.}
        {--output= : Directory generated files are written under. Defaults to config streams.sdk.make.output.}
        {--dry-run : Print the plan or tree edit without writing files.}
        {--json= : JSON recipe file. With no file, print machine-readable JSON.}';

    protected $description = 'Make a node in the app JSON tree, or build generated files from that tree.';

    /**
     * Kind flags are data. Register them once the container is available,
     * and again before bind so a kind added during the process is included.
     */
    public function setLaravel($laravel)
    {
        parent::setLaravel($laravel);

        $this->registerKindOptions();
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->registerKindOptions();

        return parent::run($input, $output);
    }

    public function handle(): int
    {
        if ($this->argument('path') === 'rename') {
            return $this->renameNode();
        }
        if ($this->argument('from') !== null || $this->argument('to') !== null) {
            return $this->failJson('Extra paths are only accepted in rename mode.', 'path');
        }
        if ($this->option('list') || $this->shouldList()) {
            return $this->listKinds();
        }

        if ($this->shouldEdit()) {
            return $this->editTree();
        }

        return $this->buildFromTree();
    }

    protected function renameNode(): int
    {
        $registry = $this->laravel->make(KindRegistry::class);
        try {
            if ($this->option('list') || $this->recipePath() !== null || $this->hasAuthoringFlags()) {
                return $this->failJson('Rename cannot be combined with list, recipes, or authoring flags.');
            }
            $from = $this->argument('from');
            $to = $this->argument('to');
            if (! is_string($from) || ! is_string($to)) {
                return $this->failJson('Rename requires source and destination dot paths.', 'path');
            }
            $file = $this->treeFile();
            $output = $this->locate($this->option('output') ?: (string) config('streams.sdk.make.output', base_path()));
            $payload = (new RenameStage)->apply(
                TreeEditor::forRegistry($registry),
                AppTree::load($file, $registry->treeContainers()),
                $file, $output, $from, $to, (bool) $this->option('dry-run'),
            );
        } catch (\Throwable $e) {
            return $this->failJson($e->getMessage());
        }
        $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * No path, no recipe, and no field flags: show what can be made.
     * --dry-run and --json without a recipe still build, which is the
     * existing plan/apply path.
     */
    protected function shouldList(): bool
    {
        if ($this->pathArgument() !== null || $this->recipePath() !== null || $this->hasAuthoringFlags()) {
            return false;
        }

        return ! $this->option('dry-run') && ! $this->wantsJsonOutput();
    }

    /**
     * Prompts, flags, and --json=file edit the tree and do not build PHP.
     * A path with --dry-run or --json (no file) and no field values still builds.
     */
    protected function shouldEdit(): bool
    {
        if ($this->recipePath() !== null || $this->hasAuthoringFlags()) {
            return true;
        }

        if ($this->pathArgument() === null) {
            return false;
        }

        return ! $this->option('dry-run') && ! $this->wantsJsonOutput();
    }

    protected function buildFromTree(): int
    {
        $registry = $this->laravel->make(KindRegistry::class);

        try {
            $treeFile = $this->locate($this->option('tree') ?: (string) config('streams.sdk.make.tree', base_path('streams.json')));
            $output = $this->locate($this->option('output') ?: (string) config('streams.sdk.make.output', base_path()));
            $tree = AppTree::load($treeFile, $registry->treeContainers());
            $path = $this->argument('path');
            $only = is_string($path) && $path !== '' ? $path : null;

            if ($only !== null && ! $tree->has($only)) {
                $this->error("Unknown app tree node [{$only}].");

                return self::FAILURE;
            }
        } catch (AppTreeException|KindRegistryException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $stage = new BuildStage(Paths::fromConfig());
        $plan = $stage->plan($tree, $only, $output);

        if (! $this->option('dry-run')) {
            $stage->apply($plan, $output);
        }

        if ($this->wantsJsonOutput()) {
            $payload = $plan->toArray();
            $payload['kinds'] = $this->resolvedKinds($registry, $tree, $only);

            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $diff = $plan->unifiedDiff();
            $this->line($diff === '' ? '(no changes)' : $diff);
        } else {
            foreach ($plan->created as $file) {
                $this->line("<info>created:</info> {$file}");
            }

            foreach ($plan->modified as $file) {
                $this->line("<info>modified:</info> {$file}");
            }
        }

        foreach ($plan->skipped as $file) {
            $this->line("<fg=yellow>skipped:</> {$file}");
        }

        if ($plan->orphans !== []) {
            $this->line('still in code, no longer in the config:');
            foreach ($plan->orphans as $orphan) {
                $this->line('  '.$orphan['path'].' '.$orphan['file']);
            }
        }

        foreach ($plan->unapplied as $edit) {
            $this->line("<fg=yellow>unapplied:</> {$edit['path']} {$edit['file']} {$edit['reason']}");
        }

        foreach ($plan->conflicts as $conflict) {
            $this->line("<fg=yellow>conflict:</> {$conflict['path']} {$conflict['file']}");
        }

        $this->line(sprintf(
            'created: %d modified: %d skipped: %d unapplied: %d conflicts: %d',
            count($plan->created),
            count($plan->modified),
            count($plan->skipped),
            count($plan->unapplied),
            count($plan->conflicts),
        ));

        return self::SUCCESS;
    }

    protected function listKinds(): int
    {
        $registry = $this->laravel->make(KindRegistry::class);
        $tree = null;

        try {
            $treeFile = $this->treeFile();

            if (is_file($treeFile)) {
                $tree = AppTree::load($treeFile, $registry->treeContainers());
            }
        } catch (AppTreeException|KindRegistryException) {
            $tree = null;
        }

        $rows = KindCatalog::forRegistry($registry)->entries($tree);

        if ($this->wantsJsonOutput()) {
            $this->output->writeln(json_encode(['kinds' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $this->line($row['kind']);

            foreach ($row['paths'] as $path) {
                $this->line('  '.$path);
            }

            foreach ($row['nodes'] as $node) {
                if (! in_array($node, $row['paths'], true)) {
                    $this->line('  '.$node);
                }
            }

            $this->line('  '.$row['description']);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function editTree(): int
    {
        $registry = $this->laravel->make(KindRegistry::class);

        try {
            $recipe = $this->recipePayload();
            $path = $this->pathArgument() ?? $recipe['path'];

            if ($this->pathArgument() !== null && $recipe['path'] !== null && $this->pathArgument() !== $recipe['path']) {
                return $this->failJson('The recipe path must match the command path.', 'path');
            }

            if ($path === null) {
                return $this->failJson('A dot path is required.', 'path');
            }

            $editor = TreeEditor::forRegistry($registry);
            $type = $editor->typeAt($this->treeFile(), $path);
            $kind = $registry->forType($type);

            if ($kind === null) {
                return $this->failJson("No component kind fits at [{$path}].", 'path');
            }

            foreach (array_keys($recipe['input'] ?? []) as $key) {
                if ($kind->input((string) $key) === null) {
                    return $this->failJson('Unknown input field.', (string) $key);
                }
            }

            $interactive = $this->input->isInteractive() && ! $this->option('no-interaction');
            $definition = (new DefinitionCollector)->collect(
                $path,
                $kind,
                $this->flagValues($kind),
                $recipe['input'],
                $interactive,
                fn (InputField $field, mixed $default): mixed => $this->promptFor($field, $default),
            );

            $errors = $kind->validate($definition->input);

            if ($errors !== []) {
                return $this->failJson('Invalid component definition.', $errors[0]['field'], $errors);
            }

            $written = $editor->apply($this->treeFile(), $path, $definition->input, (bool) $this->option('dry-run'));
        } catch (TreeEditException|AppTreeException|KindRegistryException $e) {
            $field = $e instanceof TreeEditException ? $e->field : null;

            return $this->failJson($e->getMessage(), $field);
        }

        $payload = $definition->toArray();
        $payload['file'] = $written['file'];
        $payload['written'] = $written['written'];

        if ($this->wantsJsonOutput() || $this->recipePath() !== null) {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $verb = $written['written'] ? 'updated' : 'would update';
        $this->line($verb.': '.$path);
        $this->line($written['file']);

        return self::SUCCESS;
    }

    protected function promptFor(InputField $field, mixed $default): mixed
    {
        $label = $field->prompt ?? $field->key;

        if ($field->enum !== []) {
            $choiceDefault = is_string($default) && in_array($default, $field->enum, true) ? $default : null;

            return $this->choice($label, $field->enum, $choiceDefault);
        }

        return $this->ask($label, is_scalar($default) ? (string) $default : null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function flagValues(Kind $kind): array
    {
        $values = [];

        foreach ($kind->flags() as $flag => $key) {
            if (! $this->input->hasParameterOption('--'.$flag)) {
                continue;
            }

            $values[$key] = $this->option($flag);
        }

        return $values;
    }

    protected function hasAuthoringFlags(): bool
    {
        $registry = $this->laravel->make(KindRegistry::class);

        foreach ($registry->all() as $kind) {
            foreach ($kind->flags() as $flag => $key) {
                if ($this->input->hasParameterOption('--'.$flag)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function pathArgument(): ?string
    {
        $path = $this->argument('path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * --json with no value prints JSON. --json=file is a recipe.
     */
    protected function wantsJsonOutput(): bool
    {
        if (! $this->input->hasParameterOption('--json')) {
            return false;
        }

        return ! is_string($this->option('json'));
    }

    protected function recipePath(): ?string
    {
        $value = $this->option('json');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array{path: ?string, input: array<string, mixed>|null}
     */
    protected function recipePayload(): array
    {
        $file = $this->recipePath();

        if ($file === null) {
            return ['path' => null, 'input' => null];
        }

        if (! is_file($file)) {
            throw new TreeEditException("JSON recipe not found [{$file}].");
        }

        $contents = (string) file_get_contents($file);
        $object = json_decode($contents);

        if (! $object instanceof \stdClass) {
            throw new TreeEditException("JSON recipe [{$file}] must be a JSON object.", 'json');
        }

        $data = json_decode($contents, true);

        if (array_key_exists('path', $data) && ! is_string($data['path'])) {
            throw new TreeEditException('The recipe path must be a string.', 'path');
        }

        if (property_exists($object, 'input') && ! $object->input instanceof \stdClass) {
            throw new TreeEditException('The recipe input must be a JSON object.', 'input');
        }

        $path = isset($data['path']) && is_string($data['path']) ? $data['path'] : null;
        $input = isset($data['input']) && is_array($data['input']) ? $data['input'] : $data;

        if ($input === $data) {
            unset($input['path'], $input['kind']);
        }

        return ['path' => $path, 'input' => $input];
    }

    /**
     * @param  list<array{field: string, message: string}>  $errors
     */
    protected function failJson(string $message, ?string $field = null, array $errors = []): int
    {
        $payload = ['message' => $message];

        if ($field !== null) {
            $payload['field'] = $field;
        }

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::FAILURE;
    }

    protected function registerKindOptions(): void
    {
        if (! isset($this->laravel)) {
            return;
        }

        $registry = $this->laravel->make(KindRegistry::class);

        foreach ($registry->all() as $kind) {
            foreach ($kind->inputs as $field) {
                if ($field->flag === null || $this->getDefinition()->hasOption($field->flag)) {
                    continue;
                }

                $this->getDefinition()->addOption(new InputOption(
                    $field->flag,
                    null,
                    InputOption::VALUE_OPTIONAL,
                    $field->prompt ?? $field->description ?? $field->key,
                ));
            }
        }
    }

    protected function treeFile(): string
    {
        return $this->locate($this->option('tree') ?: (string) config('streams.sdk.make.tree', base_path('streams.json')));
    }

    /**
     * Kinds for the nodes in scope. Each entry names the kind and any
     * input-schema errors. This does not register a command.
     *
     * @return array<string, array{kind: string, source: string, package?: string, errors: list<array{field: string, message: string}>}>
     */
    protected function resolvedKinds(KindRegistry $registry, AppTree $tree, ?string $only): array
    {
        $resolved = [];

        foreach ($tree->types() as $path => $type) {
            if ($path === '' || ! $this->pathInScope($path, $only)) {
                continue;
            }

            $kind = $registry->forType($type);

            if ($kind === null) {
                continue;
            }

            $errors = $kind->validate($tree->node($path));
            $entry = [
                'kind' => $kind->name,
                'source' => (string) $kind->source,
                'errors' => $errors,
            ];

            if ($kind->package !== null) {
                $entry = [
                    'kind' => $kind->name,
                    'source' => (string) $kind->source,
                    'package' => $kind->package,
                    'errors' => $errors,
                ];
            }

            $resolved[$path] = $entry;
        }

        ksort($resolved);

        return $resolved;
    }

    protected function pathInScope(string $path, ?string $only): bool
    {
        if ($only === null || $only === '') {
            return true;
        }

        return $path === $only || str_starts_with($path, $only.'.');
    }

    /**
     * Absolute paths are used as given. Relative paths resolve from the app base.
     */
    protected function locate(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
