<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Streams\Sdk\Support\StreamGenerator;

class MakeStream extends Command
{
    protected $signature = 'make:stream
        {id : snake_case stream ID. Also the file name.}
        {--name= : Human readable name.}
        {--description= : What the stream models.}
        {--force : Overwrite an existing streams/{id}.json.}';

    protected $description = 'Create a validated stream definition.';

    public function handle(StreamGenerator $generator)
    {
        $attributes = array_filter([
            'name' => $this->option('name'),
            'description' => $this->option('description'),
        ], fn ($value) => is_string($value) && $value !== '');

        try {
            $result = $generator->make($this->argument('id'), $attributes, (bool) $this->option('force'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach (Arr::wrap($messages) as $message) {
                    $text = is_string($message) ? $message : json_encode($message);

                    $this->error($text);

                    if (is_string($message) && str_contains($message, 'already exists')) {
                        $this->line('Re-run with --force to replace it.');
                    }
                }
            }

            return self::FAILURE;
        }

        $this->info('Stream created: ' . $result['path']);

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
