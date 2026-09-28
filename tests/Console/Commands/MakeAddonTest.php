<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Streams\Sdk\Tests\SdkTestCase;

class MakeAddonTest extends SdkTestCase
{
    public function test_it_makes_addons()
    {
        $this->artisan('make:addon', [
            'name' => 'acme/widgets',
            '--description' => 'Widgets for Streams.',
        ])->assertSuccessful();

        $composer = json_decode(file_get_contents($this->basePath('addons/acme/widgets/composer.json')), true);

        $this->assertSame('acme/widgets', $composer['name']);
        $this->assertSame('Widgets for Streams.', $composer['description']);
        $this->assertSame(['Acme\\Widgets\\WidgetsProvider'], $composer['extra']['laravel']['providers']);
        $this->assertFileExists($this->basePath('addons/acme/widgets/src/WidgetsProvider.php'));
    }

    public function test_it_rejects_invalid_names()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->artisan('make:addon', ['name' => 'Not Valid', '--description' => 'x']);
    }
}
