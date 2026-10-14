<?php

// Reproduce the sample reports through the registered Artisan command.
require __DIR__.'/../../../vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Tests\SdkTestCase;

$capture = new class('capture') extends SdkTestCase
{
    public function capture(): void
    {
        $this->setUp();
        $app = sys_get_temp_dir().'/streams-sdk-people-evidence-'.bin2hex(random_bytes(8));
        $fixture = __DIR__.'/../../Fixtures/PeopleMake/streams.json';

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
            $reports = ['dry' => $run(['--dry-run' => true]), 'real' => $run(), 'second' => $run()];
            if ($reports['dry'] !== $reports['real']) {
                throw new RuntimeException('Dry and real reports differ.');
            }
            file_put_contents(__DIR__.'/sample-reports.json', json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            $page = $app.'/app/Domains/People/Panel/Pages/PeoplePagesManage.php';
            File::delete($app.'/streams.lock');
            File::put($page, File::get(__DIR__.'/../../Fixtures/PeopleMake/ExistingManage.php'));
            $attached = $run(['path' => 'people.actions.add']);
            file_put_contents(__DIR__.'/attachment-report.json', json_encode($attached, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            $broken = str_replace('function getComponents()', 'function customComponents()', File::get($page));
            File::put($page, $broken);
            $unapplied = $run(['path' => 'people.actions.add']);
            if (File::get($page) !== $broken) {
                throw new RuntimeException('Unapplied page changed.');
            }
            file_put_contents(__DIR__.'/unapplied-report.json', json_encode($unapplied, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        } finally {
            File::deleteDirectory($app);
            $this->tearDown();
        }
    }
};

$capture->capture();
