<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Streams\Core\Stream\Stream;
use Streams\Core\Support\Facades\Streams;

/**
 * Writes validated stream definitions to streams/{id}.json
 * and registers them in the running application.
 */
class StreamGenerator
{
    public function __construct(protected StreamDefinitionValidator $validator)
    {
    }

    /**
     * @return array{path: string, definition: array, warnings: array<int, string>, stream: Stream}
     */
    public function make(string $id, array $attributes = [], bool $overwrite = false): array
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
            throw ValidationException::withMessages([
                'id' => "The stream ID [{$id}] must be snake_case: lowercase letters, numbers, and underscores, starting with a letter.",
            ]);
        }

        $relative = "streams/{$id}.json";
        $path = base_path($relative);

        if (File::exists($path) && !$overwrite) {
            throw ValidationException::withMessages([
                'id' => "[{$relative}] already exists.",
            ]);
        }

        if (!File::exists($path) && Streams::exists($id)) {
            throw ValidationException::withMessages([
                'id' => "A stream with ID [{$id}] is already registered by the application or an addon.",
            ]);
        }

        $definition = $this->definition($id, $attributes);

        $result = $this->validator->validate($definition, $id);

        if (!$result['valid']) {
            throw ValidationException::withMessages(['definition' => $result['errors']]);
        }

        File::ensureDirectoryExists(dirname($path));

        File::put($path, json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return [
            'path' => $relative,
            'definition' => $definition,
            'warnings' => $result['warnings'],
            'stream' => Streams::load($path),
        ];
    }

    public function definition(string $id, array $attributes = []): array
    {
        $definition = [
            '$schema' => StreamDefinitionValidator::SCHEMA_ID,
            'name' => Arr::get($attributes, 'name') ?: Str::title(str_replace(['-', '_'], ' ', $id)),
            'description' => Arr::get($attributes, 'description') ?: '',
            'config' => Arr::get($attributes, 'config') ?: [
                'source' => [
                    'format' => 'json',
                ],
            ],
            'fields' => Arr::get($attributes, 'fields') ?: [
                [
                    'handle' => 'id',
                    'type' => 'uuid',
                    'config' => [
                        'default' => true,
                    ],
                ],
            ],
        ];

        foreach (['routes', 'rules', 'extends', 'ui'] as $key) {
            if ($value = Arr::get($attributes, $key)) {
                $definition[$key] = $value;
            }
        }

        return $definition;
    }
}
