<?php

namespace Streams\Sdk\Support;

use Illuminate\Support\Arr;
use Streams\Core\Stream\Stream;
use Illuminate\Validation\ValidationException;
use Streams\Core\Entry\Contract\EntryInterface;

/**
 * Validated entry reads and writes shared by
 * the MCP entry tools.
 */
class EntryManager
{
    public function find(Stream $stream, string|int $key): EntryInterface
    {
        if (!$entry = $stream->repository()->find($key)) {
            throw new \InvalidArgumentException("Entry [{$key}] was not found in stream [{$stream->id}].");
        }

        return $entry;
    }

    public function list(Stream $stream, array $options = []): array
    {
        $criteria = $stream->entries();

        foreach (Arr::get($options, 'where', []) as $constraint) {

            $field = Arr::get($constraint, 'field');

            if (!$field) {
                throw new \InvalidArgumentException('Each "where" constraint needs a "field".');
            }

            $criteria->where(
                $field,
                Arr::get($constraint, 'operator', '='),
                Arr::get($constraint, 'value')
            );
        }

        if ($orderBy = Arr::get($options, 'order_by')) {
            $criteria->orderBy($orderBy, Arr::get($options, 'direction', 'asc'));
        }

        $perPage = max(1, min((int) Arr::get($options, 'limit', 15), 100));
        $page = max(1, (int) Arr::get($options, 'page', 1));

        $paginator = $criteria->paginate([
            'per_page' => $perPage,
            'page' => $page,
        ]);

        return [
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'entries' => collect($paginator->items())->all(),
        ];
    }

    public function create(Stream $stream, array $attributes): EntryInterface
    {
        $keyName = $stream->config('key_name', 'id');

        $key = Arr::get($attributes, $keyName);

        if ($key !== null && $stream->repository()->find($key)) {
            throw ValidationException::withMessages([
                $keyName => "An entry with {$keyName} [{$key}] already exists in stream [{$stream->id}]. Use update-entry instead.",
            ]);
        }

        // Build an instance first so field defaults
        // (such as generated UUIDs) are validated.
        $preview = $stream->repository()->newInstance($attributes);

        $this->validate($stream, $this->attributes($preview));

        return $stream->repository()->create($this->attributes($preview));
    }

    public function update(Stream $stream, string|int $key, array $attributes): EntryInterface
    {
        $keyName = $stream->config('key_name', 'id');

        $entry = $this->find($stream, $key);

        if (array_key_exists($keyName, $attributes) && (string) $attributes[$keyName] !== (string) $key) {
            throw ValidationException::withMessages([
                $keyName => "Changing the entry key is not supported. Create a new entry and delete [{$key}] instead.",
            ]);
        }

        $merged = array_merge($this->attributes($entry), $attributes);

        $this->validate($stream, $merged, $key);

        foreach ($attributes as $attribute => $value) {
            $entry->{$attribute} = $value;
        }

        $entry->save();

        return $entry;
    }

    public function delete(Stream $stream, string|int $key): bool
    {
        return $stream->repository()->delete($this->find($stream, $key));
    }

    protected function validate(Stream $stream, array $attributes, string|int|null $key = null): void
    {
        $validator = $stream->validator($attributes, $key);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    protected function attributes(EntryInterface $entry): array
    {
        return Arr::except($entry->getAttributes(), ['__prototype', '__created_at', '__updated_at']);
    }
}
