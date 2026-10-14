<?php

// Reproduce the sample reports through the registered Artisan command.
require __DIR__.'/../../../vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Console\Make\StreamLock;
use Streams\Sdk\Tests\SdkTestCase;

$capture = new class('capture') extends SdkTestCase
{
    public function capture(): void
    {
        $this->setUp();
        $app = sys_get_temp_dir().'/streams-sdk-fix-evidence-'.bin2hex(random_bytes(8));
        $fixture = __DIR__.'/../../../resources/schemas/fixtures/people/streams.json';

        try {
            $run = function (array $extra = []) use ($app, $fixture): array {
                $exit = Artisan::call('streams:make', array_merge([
                    '--tree' => $fixture,
                    '--output' => $app,
                    '--json' => true,
                ], $extra));
                if ($exit !== 0) {
                    throw new RuntimeException(Artisan::output());
                }

                return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            };

            $dry = $run(['--dry-run' => true]);
            $real = $run();
            file_put_contents(__DIR__.'/sample-reports.json', json_encode(['dry' => $dry, 'real' => $real], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            $relative = 'app/Domains/People/Panel/Actions/PeopleActionsAdd.php';
            $absolute = $app.'/'.$relative;
            File::put($absolute, str_replace("return 'Add person';", "return 'Hand edited';", File::get($absolute)));

            $preview = $run(['--fix' => true, '--dry-run' => true]);
            if (File::get($absolute) !== str_replace("return 'Add person';", "return 'Hand edited';", File::get($absolute)) && ! str_contains(File::get($absolute), 'Hand edited')) {
                throw new RuntimeException('Preview changed the file.');
            }
            file_put_contents(__DIR__.'/preview-report.json', json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            $exit = Artisan::call('streams:make', [
                '--tree' => $fixture,
                '--output' => $app,
                '--fix' => true,
            ]);
            if ($exit !== 0) {
                throw new RuntimeException(Artisan::output());
            }
            file_put_contents(__DIR__.'/fixed-report.txt', Artisan::output());

            File::put($absolute, str_replace("return 'Add person';", "return 'Hand edited again';", File::get($absolute)));

            $fixed = $run(['--fix' => true]);
            file_put_contents(__DIR__.'/fixed-report.json', json_encode($fixed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        } finally {
            File::deleteDirectory($app);
            $this->tearDown();
        }
    }
};

$capture->capture();