<?php

namespace Streams\Sdk\Console\Make;

use Streams\Sdk\Console\Make\Kinds\InputField;
use Streams\Sdk\Console\Make\Kinds\Kind;
use Streams\Sdk\Support\AppTree;

/**
 * Fills one MakeDefinition from defaults, a JSON recipe, flags, and prompts.
 *
 * Explicit recipe and flag values win over defaults. A prompt runs only for
 * a field that neither the recipe nor a flag supplied. Empty answers are
 * omitted so the three modes write the same keys.
 */
class DefinitionCollector
{
    /**
     * @param  array<string, mixed>  $flags  JSON key => value
     * @param  array<string, mixed>|null  $recipe
     * @param  callable(InputField, mixed): mixed  $prompt
     */
    public function collect(
        string $path,
        Kind $kind,
        array $flags,
        ?array $recipe,
        bool $interactive,
        callable $prompt,
    ): MakeDefinition {
        $values = [];

        foreach ($kind->inputs as $field) {
            $default = $this->defaultFor($path, $field);

            if ($default !== null && $default !== '') {
                $values[$field->key] = $default;
            }
        }

        if ($recipe !== null) {
            foreach ($kind->inputs as $field) {
                if (! array_key_exists($field->key, $recipe)) {
                    continue;
                }

                $this->put($values, $field, $recipe[$field->key]);
            }
        }

        foreach ($flags as $key => $value) {
            $field = $kind->input($key);

            if ($field === null) {
                continue;
            }

            $this->put($values, $field, $value);
        }

        if ($interactive) {
            foreach ($kind->inputs as $field) {
                if ($field->prompt === null) {
                    continue;
                }

                $fromRecipe = $recipe !== null && array_key_exists($field->key, $recipe);
                $fromFlag = array_key_exists($field->key, $flags);

                if ($fromRecipe || $fromFlag) {
                    continue;
                }

                $answered = $prompt($field, $values[$field->key] ?? $field->default);
                $this->put($values, $field, $answered);
            }
        }

        $ordered = [];

        foreach ($kind->keys() as $key) {
            if (array_key_exists($key, $values)) {
                $ordered[$key] = $values[$key];
            }
        }

        return new MakeDefinition($path, $kind, $ordered);
    }

    /**
     * The value a prompt offers when the user hits enter, and the value a
     * flag uses when it is omitted. Handle defaults to the path's last segment.
     */
    public function defaultFor(string $path, InputField $field): mixed
    {
        if ($field->default !== null) {
            return $field->default;
        }

        if ($field->key !== 'handle' || $path === '') {
            return null;
        }

        $segment = str_contains($path, '.') ? substr($path, (int) strrpos($path, '.') + 1) : $path;

        if (preg_match(AppTree::SEGMENT_PATTERN, $segment) !== 1) {
            return null;
        }

        return $segment;
    }

    public function cast(InputField $field, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $types = explode('|', $field->type);

        if (in_array('boolean', $types, true) && in_array(strtolower($value), ['true', 'false', '1', '0'], true)) {
            return in_array(strtolower($value), ['true', '1'], true);
        }

        if (in_array('integer', $types, true) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        $structured = in_array('array', $types, true) || in_array('object', $types, true);
        $trimmed = ltrim($value);

        if ($structured && $trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function put(array &$values, InputField $field, mixed $value): void
    {
        $cast = $this->cast($field, $value);

        if ($cast === null || $cast === '') {
            unset($values[$field->key]);

            return;
        }

        $values[$field->key] = $cast;
    }
}
