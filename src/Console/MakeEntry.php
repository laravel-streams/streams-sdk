<?php

namespace Streams\Dev\Console;

use Illuminate\Support\Arr;
use Streams\Core\Field\Field;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Streams\Core\Support\Facades\Streams;

class MakeEntry extends Command
{

    /**
     * @inheritDoc
     *
     * @var string
     */
    protected $signature = 'entries:make : Make a stream entry.
        {stream : The entry stream.}
        {input? : Query string formatted attributes.}
        {--update : Update if exists.}';

    public function handle()
    {
        $stream = Streams::make($this->argument('stream'));

        parse_str($this->argument('input'), $input);

        $input = new Collection($input);

        $key = Arr::get($input, $stream->config('key_name', 'id'));

        foreach ($stream->fields as $field) {
            if (!$input->has($field->handle)) {
                $this->askForInput($field, $input);
            }
        }

        if (!$key || !$instance = $stream->repository()->find($key)) {
            $instance = $stream->repository()->newInstance($input->all());
        }

        if ($key && $instance) {
            foreach ($input as $key => $value) {
                $instance->{$key} = $value;
            }
        }

        $fresh = !$this->option('update');

        $validator = $stream->validator($instance, $fresh);

        $valid = $validator->passes();

        if ($valid) {
            $instance->save();
        }

        if (!$valid) {

            $messages = $validator->messages();

            foreach ($messages->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return;
        }

        $this->info(json_encode($instance));
    }

    protected function askForInput(Field $field, Collection $input, $prefix = null)
    {
        $value = $this->ask($field->name(), $field->default($input->get($field->handle)));

        $input->put($prefix . $field->handle, $value);
    }
}