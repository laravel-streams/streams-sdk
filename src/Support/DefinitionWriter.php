<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Facades\File;
use Streams\Core\Support\Facades\Streams;
use Throwable;

/**
 * Writes a stream definition the same way for every caller: check the id, run
 * the same validation `streams:validate` runs, write pretty JSON with the
 * `$schema` pointer that gives editors autocomplete, then register it so the
 * running app agrees with the file.
 *
 * This is deliberately the only place a definition file is written, so an agent
 * and a person cannot end up with different guarantees.
 */
class DefinitionWriter
{
    public const SCHEMA = 'https://streams.dev/schema/streams.schema.json';

    /**
     * Where the definition schema ships inside this package.
     */
    public static function schemaPath(): string
    {
        return dirname(__DIR__, 2).'/resources/schemas/streams.schema.json';
    }

    public function __construct(protected StreamSourceValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     *
     * @throws WriteFailure
     */
    public function write(string $id, array $definition, bool $force = false): array
    {
        $this->guardId($id);

        $path = StreamFacts::definitionsDirectory().'/'.$id.'.json';

        if (! $force && (is_file($path) || Streams::exists($id))) {
            throw new WriteFailure(
                'Stream ['.$id.'] already exists'.(is_file($path) ? ' at '.StreamFacts::relative($path) : '').
                '. Pass force to replace it, or edit the file and run streams:validate.',
            );
        }

        $definition = $this->normalize($id, $definition);

        $result = $this->validator->validate($definition);

        if (! $result['valid']) {
            throw new WriteFailure(
                'The ['.$id.'] definition is not valid and was not written.',
                $result['errors'],
            );
        }

        File::ensureDirectoryExists(dirname($path));

        file_put_contents(
            $path,
            json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
        );

        $registered = false;
        $notes = [];

        // Replace a live registration before loading the new file, or Core keeps
        // the old instance for this request.
        try {
            $registered = Streams::load($path) !== null;
        } catch (Throwable $e) {
            $notes[] = 'Written, but not registered in this process: '.$e->getMessage().' Restart to load it.';
        }

        return [
            'written' => true,
            'id' => $id,
            'path' => StreamFacts::relative($path),
            'registered' => $registered,
            'valid' => true,
            'warnings' => $result['warnings'],
            'source_type' => $result['source_type'],
            'notes' => $notes,
            'next' => [
                'php artisan streams:schema --include='.$id,
                'php artisan entries:list '.$id,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function normalize(string $id, array $definition): array
    {
        $definition = array_merge([
            '$schema' => static::SCHEMA,
            'name' => \Illuminate\Support\Str::title(str_replace(['-', '_'], ' ', $id)),
            'description' => '',
            'config' => ['source' => ['format' => 'json']],
            'fields' => [],
        ], $definition);

        // The file name is the id; a matching "id" removes a whole class of
        // "my stream registered as something else" bugs.
        $definition['id'] = $id;

        unset($definition['handle']);

        if ($definition['description'] === '') {
            unset($definition['description']);
        }

        return $definition;
    }

    /**
     * @throws WriteFailure
     */
    protected function guardId(string $id): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
            throw new WriteFailure(
                'Stream id ['.$id.'] must be snake_case: a lowercase letter, then lowercase letters, numbers, and underscores.',
            );
        }
    }
}
