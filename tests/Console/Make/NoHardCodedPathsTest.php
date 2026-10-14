<?php

namespace Streams\Sdk\Tests\Console\Make;

use Illuminate\Support\Facades\File;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * Generator code resolves output locations from the published config
 * (streams.sdk.make.paths) on top of the reference defaults. No file other
 * than Paths (which owns the reference default layout) may hard-code an app
 * path or namespace: this is the grep check that keeps new generator code
 * honest.
 */
class NoHardCodedPathsTest extends SdkTestCase
{
    public function test_generator_code_hard_codes_no_app_paths(): void
    {
        $root = dirname(__DIR__, 3).'/src/Console';

        $files = [];

        foreach (['Make', 'Commands'] as $directory) {
            $files = array_merge($files, File::allFiles($root.'/'.$directory));
        }

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $path = $file->getRealPath();

            // Paths owns the reference defaults; it is the allowed exception.
            if (str_ends_with($path, DIRECTORY_SEPARATOR.'Paths.php')) {
                continue;
            }

            $source = (string) File::get($path);

            $this->assertStringNotContainsString(
                'app/Domains',
                $source,
                "Hard-coded reference path in {$file->getRelativePathname()}",
            );

            $this->assertStringNotContainsString(
                'App\\Domains',
                $source,
                "Hard-coded reference namespace in {$file->getRelativePathname()}",
            );
        }
    }
}