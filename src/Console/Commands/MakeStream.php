<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Streams\Sdk\Support\DefinitionWriter;
use Streams\Sdk\Support\WriteFailure;

class MakeStream extends Command
{
    /**
     * Writes through DefinitionWriter, the same path the make-stream MCP tool
     * uses: the definition is validated first, an existing stream is refused
     * without --force, and the file lands in the configured definitions
     * directory rather than a hard-coded one.
     *
     * @var string
     */
    protected $signature = 'make:stream
        {id : The stream id. Also the file name: streams/{id}.json.}
        {--name= : Human-readable name. Defaults to the headline of the id.}
        {--description= : What the stream is for.}
        {--force : Replace the definition if the stream already exists.}';

    protected $description = 'Create a validated stream definition.';

    public function __construct(protected ?DefinitionWriter $writer = null)
    {
        parent::__construct();
    }

    protected function writer(): DefinitionWriter
    {
        return $this->writer ??= app(DefinitionWriter::class);
    }

    public function handle()
    {
        $id = (string) $this->argument('id');

        $contents = View::parse(file_get_contents(__DIR__.'/stubs/stream.stub'), [
            'name' => (string) ($this->option('name') ?: Str::headline($id)),
        ]);

        $definition = json_decode((string) $contents, true);

        if (! is_array($definition)) {
            $this->components->error('The stream stub at src/Console/Commands/stubs/stream.stub is not valid JSON.');

            return Command::FAILURE;
        }

        if ($description = $this->option('description')) {
            $definition['description'] = (string) $description;
        }

        try {
            $result = $this->writer()->write($id, $definition, (bool) $this->option('force'));
        } catch (WriteFailure $failure) {
            $this->components->error($failure->getMessage());

            foreach (array_filter($failure->errors(), fn ($error) => $error !== $failure->getMessage()) as $error) {
                $this->line('   <fg=red>·</> '.$error);
            }

            return Command::FAILURE;
        }

        $this->components->info('Stream created: '.$result['path']);

        foreach ($result['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        foreach ($result['notes'] as $note) {
            $this->components->info($note);
        }

        $this->line(' <fg=default>Add fields to <options=bold>'.$result['path'].'</>, then run <options=bold>php artisan streams:validate</>. Next recipes: '.$this->implied());

        return Command::SUCCESS;
    }

    protected function implied(): string
    {
        return \Streams\Sdk\Support\Playbooks::handles() === []
            ? 'see php artisan streams:tui'
            : implode(', ', array_slice(\Streams\Sdk\Support\Playbooks::handles(), 0, 3));
    }
}
