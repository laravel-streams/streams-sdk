<?php

namespace Streams\Sdk\Console\Make;

use RuntimeException;

/**
 * A tree edit that an agent can read: the message names the field when one is known.
 */
class TreeEditException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
