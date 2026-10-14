<?php

namespace Streams\Sdk\Tests\Support;

use PHPUnit\Framework\TestCase;
use Streams\Sdk\Support\CatalogGenerator;

class CatalogManifestMergeTest extends TestCase
{
    public function test_installed_ui_preserves_every_manifest_builder_and_its_metadata(): void
    {
        $generator = new class extends CatalogGenerator {
            protected function uiInstallPath(): string
            {
                return __DIR__ . '/../fixtures/catalog-ui';
            }
        };

        $manifest = json_decode(file_get_contents(__DIR__ . '/../../resources/catalog/ui-builders.manifest.json'), true);
        $builders = array_column($generator->uiBuilders(), null, 'id');

        foreach ($manifest as $entry) {
            $this->assertArrayHasKey($entry['id'], $builders);
            $actual = $builders[$entry['id']];
            unset($actual['source']);
            $this->assertEquals($entry, json_decode(json_encode($actual), true));
        }

        foreach (['table', 'input-text', 'livewire-table'] as $id) {
            $this->assertSame('code', $builders[$id]['source']);
        }

        $this->assertSame('manifest', $builders['form']['source']);
        $this->assertSame('widget', $builders['custom-widget']['group']);
        $this->assertInstanceOf(\stdClass::class, $builders['custom-widget']['options']);
        $this->assertSame([], $builders['custom-widget']['allowed_parents']);
        $this->assertCount(count($manifest) + 1, $builders);
        $this->assertSame(json_encode($generator->uiBuilders()), json_encode($generator->uiBuilders()));
    }
}
