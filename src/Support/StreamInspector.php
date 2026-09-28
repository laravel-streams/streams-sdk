<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Arr;
use Streams\Core\Field\Field;
use Streams\Core\Stream\Stream;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Streams\Core\Support\Facades\Streams;
use Streams\Core\Entry\Contract\EntryInterface;

/**
 * Presents registered streams and entries as plain
 * arrays for agents (MCP tools) and console output.
 */
class StreamInspector
{
    public function find(string $id): Stream
    {
        if (!Streams::exists($id)) {
            throw new \InvalidArgumentException("Stream [{$id}] is not registered. Use list-streams to see registered stream IDs.");
        }

        return Streams::make($id);
    }

    public function summaries(): array
    {
        return Streams::collection()
            ->map(fn (Stream $stream) => $this->summary($stream))
            ->sortBy('id')
            ->values()
            ->all();
    }

    public function summary(Stream $stream): array
    {
        return [
            'id' => $stream->id,
            'name' => $stream->name(),
            'description' => $stream->description,
            'source' => $this->sourceType($stream),
            'fields' => $stream->fields->keys()->all(),
            'extends' => $stream->extends,
        ];
    }

    public function describe(Stream $stream): array
    {
        $file = $this->definitionFile($stream);

        return [
            'id' => $stream->id,
            'name' => $stream->name(),
            'description' => $stream->description,
            'key_name' => $stream->config('key_name', 'id'),
            'source' => array_merge(
                ['type' => $this->sourceType($stream)],
                (array) $stream->config('source', [])
            ),
            'extends' => $stream->extends,
            'definition_file' => $file,
            'fields' => $stream->fields
                ->map(fn (Field $field) => $this->field($field))
                ->values()
                ->all(),
            'routes' => $stream->routes ?: [],
            'definition' => Arr::except($stream->getOriginalPrototypeAttributes(), ['$schema']),
        ];
    }

    public function field(Field $field): array
    {
        return array_filter([
            'handle' => $field->handle,
            'type' => $field->type,
            'name' => $field->name(),
            'description' => $field->description,
            'required' => $field->isRequired(),
            'protected' => (bool) $field->protected,
            'rules' => array_values(array_map(
                fn ($rule) => is_object($rule) ? get_class($rule) : $rule,
                $field->rules()
            )),
            'config' => $field->config ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function entrySchema(Stream $stream): array
    {
        $schema = $stream->schema();

        return array_merge(
            $schema->tag()->toArray(),
            $schema->object()->toArray(),
        );
    }

    public function entry(EntryInterface $entry): array
    {
        $attributes = method_exists($entry, 'toArray')
            ? $entry->toArray()
            : (array) $entry;

        return Arr::except($attributes, ['__prototype', '__created_at', '__updated_at']);
    }

    public function sourceType(Stream $stream): string
    {
        if ($adapter = $stream->config('adapter')) {
            return $adapter;
        }

        return $stream->config('source.type') ?: Config::get('streams.core.default_source', 'filebase');
    }

    /**
     * The app-relative definition file, if the
     * stream was loaded from streams/{id}.json.
     */
    public function definitionFile(Stream $stream): ?string
    {
        $path = 'streams/' . $stream->id . '.json';

        return File::exists(base_path($path)) ? $path : null;
    }
}
