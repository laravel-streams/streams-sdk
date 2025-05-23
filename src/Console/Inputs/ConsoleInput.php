<?php

namespace Streams\Sdk\Console\Inputs;

use Streams\Core\Field\Field;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ConsoleInput
{
    public function __construct(public Field $field)
    {
    }

    public function ask(Command $command, Collection $input)
    {
        if ($input->has($this->field->handle)) {
            $default = $input->get($this->field->handle);
        } else {
            $default = $this->field->config('default');
        }

        return $command->ask(
            $this->field->name(),
            is_null($default) ? $default : $this->field->default($default)
        );
    }
}
