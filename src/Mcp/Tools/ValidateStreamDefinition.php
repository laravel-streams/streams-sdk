<?php

namespace Streams\Sdk\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Streams\Sdk\Mcp\Tool;
use Streams\Sdk\Support\StreamFacts;
use Streams\Sdk\Support\StreamSourceValidator;

/**
 * The pre-flight check. Same source rules as `streams:validate`, plus the
 * structural mistakes that make a definition register wrongly, and it can run
 * on a draft that has not been written to disk yet.
 */
#[Name('validate-stream-definition')]
#[Title('Validate a stream definition')]
#[Description('Checks a stream definition against the installed Core. Pass {"definition": {...}} to check a draft before writing it, {"stream": "id"} to check a registered stream\'s file, {"path": "streams/x.json"} to check one file, or nothing to check every file in streams/. Reports errors (source type not an adapter, format not registered, missing model class, "validation" or "config.pagination" which Core ignores, duplicate or non-snake_case field handles) and warnings. Always run this after editing a definition and before make-stream. Nothing here writes a file.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ValidateStreamDefinition extends Tool
{
    /**
     * Resolved lazily so the class stays `new`-able (the MCP test helpers and
     * any caller outside the container construct tools with no arguments).
     */
    public function __construct(protected ?StreamSourceValidator $validator = null)
    {
    }

    protected function validator(): StreamSourceValidator
    {
        return $this->validator ??= app(StreamSourceValidator::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'definition' => ['nullable', 'array'],
            'stream' => ['nullable', 'string'],
            'path' => ['nullable', 'string'],
        ]);

        $sources = array_filter([
            'definition' => isset($validated['definition']),
            'stream' => isset($validated['stream']),
            'path' => isset($validated['path']),
        ]);

        if (count($sources) > 1) {
            return Response::error('Pass one of definition, stream, or path — not '.implode(' and ', array_keys($sources)).'.');
        }

        if ($sources === []) {
            return $this->all();
        }

        if (isset($sources['definition'])) {
            return $this->report('draft', (array) $validated['definition'], null);
        }

        if (isset($sources['stream'])) {
            $stream = $this->stream((string) $validated['stream']);

            if ($stream === null) {
                return $this->unknownStream((string) $validated['stream']);
            }

            $definition = StreamFacts::originalDefinition($stream);

            if ($definition === null) {
                return $this->data([
                    'target' => (string) $stream->id,
                    'valid' => true,
                    'errors' => [],
                    'warnings' => ['No definition file on disk: this stream was registered in PHP (an addon default or Streams::register at runtime), so there is nothing to validate.'],
                    'note' => 'Runtime-registered streams cannot be regenerated from a file.',
                ]);
            }

            return $this->report((string) $stream->id, $definition, StreamFacts::definitionPath($stream));
        }

        $path = (string) $validated['path'];
        $absolute = $this->resolvePath($path);

        if ($absolute === null) {
            return Response::error('Cannot read ['.$path.']. Pass a path relative to the app root, such as "streams/people.json".');
        }

        $definition = json_decode((string) file_get_contents($absolute), true);

        if (! is_array($definition)) {
            return $this->data([
                'target' => StreamFacts::relative($absolute),
                'valid' => false,
                'errors' => ['The file is not readable JSON. Fix the syntax; comments are not allowed in .json.'],
                'warnings' => [],
            ]);
        }

        return $this->report(StreamFacts::relative($absolute), $definition, StreamFacts::relative($absolute));
    }

    /**
     * Every file in streams/, the way CI should run it.
     */
    protected function all(): Response|ResponseFactory
    {
        $files = glob(StreamFacts::definitionsDirectory().'/*.json') ?: [];

        if ($files === []) {
            return $this->data([
                'checked' => 0,
                'valid' => true,
                'errors' => [],
                'warnings' => [],
                'note' => 'No stream definition files in '.config('streams.sdk.definitions.path', 'streams').'/. Create one with make-stream, or run `php artisan streams:onboard`.',
            ]);
        }

        $results = [];
        $failed = [];

        foreach ($files as $file) {
            $definition = json_decode((string) file_get_contents($file), true);
            $target = StreamFacts::relative($file);

            if (! is_array($definition)) {
                $failed[] = $target.': not readable JSON.';

                continue;
            }

            $report = $this->check($definition);
            $results[] = array_merge(['target' => $target], $report);

            foreach ($report['errors'] as $error) {
                $failed[] = $target.': '.$error;
            }
        }

        return $this->data([
            'checked' => count($results),
            'valid' => $failed === [],
            'failing' => count($failed),
            'errors' => $failed,
            'results' => $results,
            'next' => $failed === []
                ? 'All definitions match the installed Core.'
                : 'Fix each file, then run this tool again. make-stream refuses an invalid definition.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function check(array $definition): array
    {
        $report = $this->validator()->validate($definition);

        $report['errors'] = array_merge($report['errors'], $this->structural($definition));

        return [
            'valid' => $report['errors'] === [],
            'errors' => array_values(array_unique($report['errors'])),
            'warnings' => $report['warnings'],
            'source_type' => $report['source_type'],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  string|null  $file
     * @return Response|ResponseFactory
     */
    protected function report(string $target, array $definition, ?string $file): Response|ResponseFactory
    {
        return $this->data(array_merge(
            ['target' => $target, 'file' => $file],
            $this->check($definition),
            ['next' => 'php artisan streams:schema to regenerate entry types once it is valid.'],
        ));
    }

    /**
     * The mistakes that let a file look right while the stream registers
     * wrong: a name where a handle belongs, a reused handle, an empty model.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    protected function structural(array $definition): array
    {
        $errors = [];

        if (! isset($definition['name'])) {
            $errors[] = '/name: a definition needs a human name (it is what panels and menus show).';
        }

        $fields = $definition['fields'] ?? null;

        if ($fields === null || $fields === []) {
            $errors[] = '/fields: a stream with no fields cannot hold data. Add at least one field.';

            return $errors;
        }

        if (! is_array($fields)) {
            $errors[] = '/fields: "fields" must be a list of field objects.';

            return $errors;
        }

        $seen = [];

        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                $errors[] = '/fields/'.$index.': each field must be an object with a "handle" and a "type".';

                continue;
            }

            $handle = $field['handle'] ?? ($field['field'] ?? null);

            if (($field['field'] ?? null) !== null && ($field['handle'] ?? null) === null) {
                $errors[] = '/fields/'.$index.': "field" is not a field property. Use "handle" for the key and "name" for the label.';
            }

            if (! is_string($handle) || $handle === '') {
                $errors[] = '/fields/'.$index.': a field needs a "handle" (the attribute name entries use).';

                continue;
            }

            if (! preg_match('/^[a-z][a-z0-9_]*$/', $handle)) {
                $errors[] = '/fields/'.$index.': handle ['.$handle.'] must be snake_case.';
            }

            if (in_array($handle, $seen, true)) {
                $errors[] = '/fields/'.$index.': handle ['.$handle.'] is repeated; entries cannot hold the same attribute twice.';
            }

            $seen[] = $handle;

            if (! isset($field['type'])) {
                $errors[] = '/fields/'.$index.' ['.$handle.']: a field needs a "type" (it decides casting, rules, and the entry schema). Known types: '.implode(', ', array_keys((array) config('streams.core.field_types', []))).'.';
            }
        }

        return $errors;
    }

    protected function resolvePath(string $path): ?string
    {
        $absolute = str_starts_with($path, '/') ? $path : base_path($path);

        $real = realpath($absolute);

        if ($real === false || ! is_file($real) || ! str_ends_with($real, '.json')) {
            return null;
        }

        // Stay inside the app: an agent should not be reading the disk at large.
        return str_starts_with($real, realpath(base_path()) ?: $real) ? $real : null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'definition' => $schema->object()->description('A draft definition to check, exactly as it would go in streams/{id}.json. Nothing is written.'),
            'stream' => $schema->string()->description('Check a registered stream\'s definition file.'),
            'path' => $schema->string()->description('Check one file by path relative to the app root, e.g. "streams/people.json".'),
        ];
    }
}
