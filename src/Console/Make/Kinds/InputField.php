<?php

namespace Streams\Sdk\Console\Make\Kinds;

/**
 * One input on a component kind.
 *
 * The JSON key, the prompt, and the flag all come from this definition.
 * A kind is data for streams:make. It is never registered as a command.
 */
class InputField
{
    /**
     * @param  list<string>  $enum
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly bool $required = false,
        public readonly ?string $prompt = null,
        public readonly ?string $flag = null,
        public readonly array $enum = [],
        public readonly ?string $pattern = null,
        public readonly ?string $description = null,
        public readonly mixed $default = null,
    ) {}

    public static function make(string $key, string $type): self
    {
        return new self($key, $type);
    }

    public static function string(string $key): self
    {
        return new self($key, 'string');
    }

    public function required(): self
    {
        return $this->copy(required: true);
    }

    public function type(string $type): self
    {
        return $this->copy(type: $type);
    }

    public function prompt(string $prompt): self
    {
        return $this->copy(prompt: $prompt);
    }

    public function flag(string $flag): self
    {
        return $this->copy(flag: $flag);
    }

    /**
     * @param  list<string>  $enum
     */
    public function enum(array $enum): self
    {
        return $this->copy(enum: $enum);
    }

    public function pattern(string $pattern): self
    {
        return $this->copy(pattern: $pattern);
    }

    public function description(string $description): self
    {
        return $this->copy(description: $description);
    }

    public function default(mixed $default): self
    {
        return $this->copy(default: $default, setDefault: true);
    }

    /**
     * @param  list<string>|null  $enum
     */
    protected function copy(
        ?string $type = null,
        ?bool $required = null,
        ?string $prompt = null,
        ?string $flag = null,
        ?array $enum = null,
        ?string $pattern = null,
        ?string $description = null,
        mixed $default = null,
        bool $setDefault = false,
    ): self {
        return new self(
            $this->key,
            $type ?? $this->type,
            $required ?? $this->required,
            $prompt ?? $this->prompt,
            $flag ?? $this->flag,
            $enum ?? $this->enum,
            $pattern ?? $this->pattern,
            $description ?? $this->description,
            $setDefault ? $default : $this->default,
        );
    }
}
