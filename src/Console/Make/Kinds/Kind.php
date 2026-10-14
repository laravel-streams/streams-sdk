<?php

namespace Streams\Sdk\Console\Make\Kinds;

/**
 * A component kind streams:make can produce.
 *
 * The input schema drives prompts, flags, and JSON keys. Kinds are registry
 * data. Registering a kind does not register a command.
 */
class Kind
{
    /**
     * @param  list<InputField>  $inputs
     * @param  list<ChainLink>  $chain
     * @param  array<string, array{field: string, equals: string}>  $requiredWhen
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputs,
        public readonly array $chain = [],
        public readonly array $requiredWhen = [],
        public readonly ?Placement $placement = null,
        public readonly ?string $source = null,
        public readonly ?string $package = null,
        public readonly ?string $nodeType = null,
    ) {}

    /**
     * @param  list<InputField>  $inputs
     * @param  list<ChainLink>  $chain
     * @param  array<string, array{field: string, equals: string}>  $requiredWhen
     */
    public static function define(
        string $name,
        string $description,
        array $inputs,
        array $chain = [],
        array $requiredWhen = [],
        ?string $nodeType = null,
    ): self {
        return new self($name, $description, $inputs, $chain, $requiredWhen, null, null, null, $nodeType);
    }

    public function fromSource(string $source, ?string $package = null): self
    {
        return new self(
            $this->name,
            $this->description,
            $this->inputs,
            $this->chain,
            $this->requiredWhen,
            $this->placement,
            $source,
            $package,
            $this->nodeType,
        );
    }

    /**
     * Attach this kind under an existing tree parent so streams:make can
     * address it by dot path.
     */
    public function placedOn(string $parent, string $key, bool $map, ?string $mapOf = null): self
    {
        return new self(
            $this->name,
            $this->description,
            $this->inputs,
            $this->chain,
            $this->requiredWhen,
            new Placement($parent, $key, $map, $mapOf),
            $this->source,
            $this->package,
            $this->nodeType,
        );
    }

    /**
     * The app-tree node type this kind validates.
     */
    public function resolvedType(): string
    {
        return $this->nodeType ?? $this->name;
    }

    public function input(string $key): ?InputField
    {
        foreach ($this->inputs as $input) {
            if ($input->key === $key) {
                return $input;
            }
        }

        return null;
    }

    /**
     * JSON keys in schema order.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(fn (InputField $input): string => $input->key, $this->inputs);
    }

    /**
     * Prompts keyed by JSON key.
     *
     * @return array<string, string>
     */
    public function prompts(): array
    {
        $prompts = [];

        foreach ($this->inputs as $input) {
            if ($input->prompt !== null) {
                $prompts[$input->key] = $input->prompt;
            }
        }

        return $prompts;
    }

    /**
     * Artisan flag name => JSON key.
     *
     * @return array<string, string>
     */
    public function flags(): array
    {
        $flags = [];

        foreach ($this->inputs as $input) {
            if ($input->flag !== null) {
                $flags[$input->flag] = $input->key;
            }
        }

        return $flags;
    }

    /**
     * Validate an input payload.
     *
     * Each error names its field. An empty list means the input is valid.
     *
     * @param  array<string, mixed>  $input
     * @return list<array{field: string, message: string}>
     */
    public function validate(array $input): array
    {
        $errors = [];

        foreach ($this->inputs as $field) {
            $value = $input[$field->key] ?? null;
            $present = $this->present($value);

            if (! $present) {
                if ($field->required) {
                    $errors[] = [
                        'field' => $field->key,
                        'message' => "The {$field->key} field is required.",
                    ];
                }

                continue;
            }

            if (! $this->typeMatches($field->type, $value)) {
                $errors[] = [
                    'field' => $field->key,
                    'message' => "The {$field->key} field must be a {$field->type}.",
                ];

                continue;
            }

            if ($field->enum !== [] && ! in_array($value, $field->enum, true)) {
                $errors[] = [
                    'field' => $field->key,
                    'message' => 'The '.$field->key.' field must be one of: '.implode(', ', $field->enum).'.',
                ];
            }

            if (is_string($value) && $field->pattern !== null && preg_match($field->pattern, $value) !== 1) {
                $errors[] = [
                    'field' => $field->key,
                    'message' => "The {$field->key} field format is invalid.",
                ];
            }
        }

        foreach ($this->requiredWhen as $key => $rule) {
            if (($input[$rule['field']] ?? null) !== $rule['equals']) {
                continue;
            }

            if ($this->present($input[$key] ?? null)) {
                continue;
            }

            $errors[] = [
                'field' => $key,
                'message' => "The {$key} field is required when {$rule['field']} is {$rule['equals']}.",
            ];
        }

        return $errors;
    }

    /**
     * Child nodes this kind produces. Paths are relative to the stream handle.
     *
     * @param  array<string, mixed>  $input
     * @return list<array{kind: string, path: string, input: array<string, mixed>}>
     */
    public function expand(array $input): array
    {
        $steps = [];

        foreach ($this->chain as $link) {
            $steps[] = [
                'kind' => $link->kind,
                'path' => $link->path,
                'input' => $link->resolve($input),
            ];
        }

        return $steps;
    }

    /**
     * Same as expand(), with the stream handle prefixed onto each dot path.
     *
     * @param  array<string, mixed>  $input
     * @return list<array{kind: string, path: string, input: array<string, mixed>}>
     */
    public function expandUnder(string $stream, array $input): array
    {
        $steps = $this->expand($input);

        if ($stream === '') {
            return $steps;
        }

        foreach ($steps as $index => $step) {
            $steps[$index]['path'] = $stream.'.'.$step['path'];
        }

        return $steps;
    }

    protected function present(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    protected function typeMatches(string $type, mixed $value): bool
    {
        foreach (explode('|', $type) as $option) {
            $matches = match ($option) {
                'string' => is_string($value),
                'boolean' => is_bool($value),
                'integer' => is_int($value),
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
                default => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
