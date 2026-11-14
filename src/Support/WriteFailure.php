<?php

namespace Streams\Sdk\Support;

/**
 * A write that Streams refused. Carries the messages a caller should show or
 * return, so the TUI can print them and an MCP tool can answer with them
 * instead of a stack trace.
 */
class WriteFailure extends \RuntimeException
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(string $message, protected array $errors = [])
    {
        parent::__construct($message);

        if ($this->errors === []) {
            $this->errors = [$message];
        }
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
