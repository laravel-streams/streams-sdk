<?php

// Run from the SDK root: php tests/Evidence/streams-make-define/capture.php
require dirname(__DIR__, 3).'/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Streams\Sdk\Tests\SdkTestCase;

class CaptureMakeEvidence extends SdkTestCase
{
    public function capture(): void
    {
        $this->setUp();

        try {
            $fixture = dirname(__DIR__, 3).'/resources/schemas/fixtures/people/streams.json';

            foreach ([
                'list.txt' => ['--list' => true],
                'list.json' => ['--list' => true, '--json' => true],
                'error.json' => ['path' => 'people.actions.add', '--no-interaction' => true],
            ] as $file => $options) {
                $status = Artisan::call('streams:make', $options + ['--tree' => $fixture]);
                file_put_contents(__DIR__.'/'.$file, Artisan::output());
                echo $file.': exit '.$status.PHP_EOL;
            }
        } finally {
            $this->tearDown();
        }
    }
}

(new CaptureMakeEvidence)->capture();
