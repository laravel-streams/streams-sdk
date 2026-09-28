<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Streams\Sdk\Support\AddonGenerator;

class MakeAddon extends Command
{
    protected $signature = 'make:addon
        {name : The addon package name.}
        {--description= : A short description of the package.}
        {--force : Overwrite an existing addon.}';

    protected $description = 'Make an addon for Laravel Streams.';

    public function handle(AddonGenerator $generator)
    {
        $description = $this->option('description')
            ?: $this->ask('A short description of the package', 'An addon for Laravel Streams.');

        $result = $generator->make($this->argument('name'), $description, (bool) $this->option('force'));

        foreach ($result['files'] as $file) {
            $this->info("Created: {$file}");
        }
    }
}
