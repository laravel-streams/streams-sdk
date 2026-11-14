<?php

namespace Streams\Sdk\Support;

use Streams\Core\Field\Field;
use Streams\Core\Stream\Stream;
use Streams\Core\Support\Facades\Streams;
use Throwable;

/**
 * Entry writes that always go through the stream's own validation. Protected
 * fields are never returned, and unknown fields are refused rather than
 * silently dropped — the two places where a well-meaning agent does quiet
 * damage to someone's data.
 */
class EntryWriter
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws WriteFailure
     */
    public function create(string $streamId, array $attributes): array
    {
        $stream = $this->resolve($streamId);

        $attributes = $this->filterFields($stream, $attributes, true);

        $this->fillDefaults($stream, $attributes);

        $key = $this->key($stream, $attributes);

        if ($key !== null && $stream->repository()->find($key)) {
            throw new WriteFailure(
                'An entry with '.$stream->config('key_name', 'id').' ['.$key.'] already exists in ['.$stream->id.']. Use update-entry.',
            );
        }

        $this->validate($stream, $attributes, null);

        try {
            $entry = $stream->create($attributes);
        } catch (Throwable $e) {
            throw new WriteFailure('The ['.$stream->id.'] entry could not be saved: '.$e->getMessage());
        }

        return [
            'created' => true,
            'stream' => (string) $stream->id,
            'key' => $key ?? (string) $this->key($stream, StreamFacts::attributes($stream, $entry)),
            'entry' => StreamFacts::attributes($stream, $entry),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws WriteFailure
     */
    public function update(string $streamId, string|int $key, array $attributes): array
    {
        $stream = $this->resolve($streamId);

        $entry = $stream->repository()->find($key);

        if (! $entry) {
            throw new WriteFailure('No ['.$stream->id.'] entry with '.$stream->config('key_name', 'id').' ['.$key.'].');
        }

        $attributes = $this->filterFields($stream, $attributes, false);

        $merged = array_merge(StreamFacts::attributes($stream, $entry), $attributes);

        $this->validate($stream, $merged, $key);

        foreach ($attributes as $handle => $value) {
            $entry->{$handle} = $value;
        }

        try {
            $entry->save();
        } catch (Throwable $e) {
            throw new WriteFailure('The ['.$stream->id.'] entry could not be saved: '.$e->getMessage());
        }

        return [
            'updated' => true,
            'stream' => (string) $stream->id,
            'key' => (string) $key,
            'changed' => array_keys($attributes),
            'entry' => StreamFacts::attributes($stream, $entry),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws WriteFailure
     */
    public function delete(string $streamId, string|int $key): array
    {
        $stream = $this->resolve($streamId);

        $entry = $stream->repository()->find($key);

        if (! $entry) {
            throw new WriteFailure('No ['.$stream->id.'] entry with '.$stream->config('key_name', 'id').' ['.$key.'].');
        }

        $deleted = StreamFacts::attributes($stream, $entry);

        try {
            $entry->delete();
        } catch (Throwable $e) {
            throw new WriteFailure('The ['.$stream->id.'] entry could not be deleted: '.$e->getMessage());
        }

        return [
            'deleted' => true,
            'stream' => (string) $stream->id,
            'key' => (string) $key,
            'was' => $deleted,
        ];
    }

    /**
     * Read a stream's entries with the same criteria the app uses.
     *
     * Long text values are shortened on purpose: an agent reading a list needs
     * to recognise rows, not spend its context on bodies. `read-entry` returns
     * the whole thing, and every row keeps its key so it can ask for one.
     *
     * @param  array<int, array{field: string, operator?: string, value?: mixed}>  $where
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    public function list(
        string $streamId,
        array $where = [],
        ?string $orderBy = null,
        string $direction = 'asc',
        int $limit = 25,
        int $offset = 0,
        array $fields = [],
        int $truncate = 300,
    ): array {
        $stream = $this->resolve($streamId);

        $known = StreamFacts::fields($stream, false);
        $keyName = (string) $stream->config('key_name', 'id');

        $projection = [];

        foreach ($fields as $handle) {
            $handle = (string) $handle;

            if (! array_key_exists($handle, $known)) {
                throw new WriteFailure('['.$handle.'] is not a field of ['.$stream->id.']. Known fields: '.implode(', ', array_keys($known)).'.');
            }

            $projection[] = $handle;
        }

        if ($projection !== [] && ! in_array($keyName, $projection, true)) {
            array_unshift($projection, $keyName);
        }

        $criteria = $stream->entries();

        foreach ($where as $clause) {
            $field = (string) ($clause['field'] ?? '');

            if ($field === '') {
                throw new WriteFailure('Every where clause needs a "field".');
            }

            if (! array_key_exists($field, $known)) {
                throw new WriteFailure('['.$field.'] is not a field of ['.$stream->id.']. Known fields: '.implode(', ', array_keys($known)).'.');
            }

            $criteria->where($field, (string) ($clause['operator'] ?? '='), $clause['value'] ?? null);
        }

        if ($orderBy !== null) {
            $criteria->orderBy($orderBy, $direction === 'desc' ? 'desc' : 'asc');
        }

        $criteria->limit(max(1, min($limit, 100)), max(0, $offset));

        try {
            $entries = $criteria->get();
        } catch (Throwable $e) {
            throw new WriteFailure('The ['.$stream->id.'] query failed: '.$e->getMessage());
        }

        $truncated = [];

        $rows = $entries
            ->map(function ($entry) use ($stream, $projection, $truncate, &$truncated) {
                $attributes = StreamFacts::attributes($stream, $entry);

                if ($projection !== []) {
                    $attributes = array_intersect_key($attributes, array_flip($projection));
                }

                $row = [];

                foreach ($attributes as $handle => $value) {
                    if (is_string($value) && mb_strlen($value) > $truncate) {
                        $truncated[(string) $handle] = true;
                        $value = rtrim(mb_substr($value, 0, $truncate)).'…';
                    }

                    $row[$handle] = $value;
                }

                return $row;
            })
            ->values()
            ->all();

        $payload = [
            'stream' => (string) $stream->id,
            'returned' => count($rows),
            'total' => StreamFacts::count($stream),
            'offset' => $offset,
            'limit' => $limit,
            'entries' => $rows,
        ];

        if ($projection !== []) {
            $payload['fields'] = $projection;
        }

        if ($truncated !== []) {
            $payload['shortened_fields'] = array_keys($truncated);
            $payload['note'] = 'Long values were shortened for reading. Call read-entry with the key for the full value, or pass fewer fields.';
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws WriteFailure
     */
    public function read(string $streamId, string|int $key): array
    {
        $stream = $this->resolve($streamId);

        $entry = $stream->repository()->find($key);

        if (! $entry) {
            throw new WriteFailure('No ['.$stream->id.'] entry with '.$stream->config('key_name', 'id').' ['.$key.'].');
        }

        return [
            'stream' => (string) $stream->id,
            'key' => (string) $key,
            'entry' => StreamFacts::attributes($stream, $entry),
        ];
    }

    /**
     * A field with a `config.default` fills itself in, exactly as
     * `Criteria::fillDefaults()` does in core — so a generated `id` (uuid,
     * `default: true`) never has to be passed in, and never trips its own
     * `required` rule before the value exists.
     */
    protected function fillDefaults(Stream $stream, array &$attributes): void
    {
        foreach ($stream->fields as $field) {
            $default = $field->config('default');

            if (! $default || ! $field->handle) {
                continue;
            }

            if (array_key_exists((string) $field->handle, $attributes)) {
                continue;
            }

            $attributes[(string) $field->handle] = $field->default($default);
        }
    }

    /**
     * @return array{0: bool, 1: array<int, string>} [passed, messages]
     */
    public function check(Stream $stream, array $attributes, string|int|null $key = null): array
    {
        $validator = $stream->validator($attributes, $key);

        return [$validator->passes(), $validator->messages()->all()];
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws WriteFailure
     */
    protected function validate(Stream $stream, array $attributes, string|int|null $key): void
    {
        [$passed, $messages] = $this->check($stream, $attributes, $key);

        if (! $passed) {
            throw new WriteFailure(
                'The ['.$stream->id.'] entry failed validation. Nothing was written.',
                $messages,
            );
        }
    }

    /**
     * Refuse unknown handles instead of dropping them, and refuse protected
     * handles instead of letting an agent overwrite a secret.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws WriteFailure
     */
    protected function filterFields(Stream $stream, array $attributes, bool $fresh): array
    {
        $fields = StreamFacts::fields($stream, false);

        $unknown = array_diff(array_keys($attributes), array_keys($fields));

        if ($unknown !== []) {
            throw new WriteFailure(
                '['.implode(', ', $unknown).'] '.(count($unknown) === 1 ? 'is' : 'are').' not a field of ['.$stream->id.']. Known fields: '.implode(', ', array_keys($fields)).'.',
            );
        }

        $protected = [];

        foreach ($stream->fields as $field) {
            if ($field instanceof Field && $field->protected && array_key_exists((string) $field->handle, $attributes)) {
                $protected[] = (string) $field->handle;
            }
        }

        if ($protected !== []) {
            throw new WriteFailure('['.implode(', ', $protected).'] '.(count($protected) === 1 ? 'is' : 'are').' protected on ['.$stream->id.'] and cannot be set here.');
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function key(Stream $stream, array $attributes): ?string
    {
        $keyName = (string) $stream->config('key_name', 'id');

        $value = $attributes[$keyName] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    protected function resolve(string $streamId): Stream
    {
        if (! Streams::exists($streamId)) {
            throw new WriteFailure(
                'Stream ['.$streamId.'] is not registered. Run list-streams to see what exists, or make-stream to create one.',
            );
        }

        return Streams::make($streamId);
    }
}
