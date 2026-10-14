<?php

namespace Streams\Sdk\Tests\Support;

use Illuminate\Support\Facades\File;
use Streams\Sdk\Tests\SdkTestCase;
use Streams\Sdk\Tests\Support\SchemaValidator;

/**
 * App-definition schema: the root streams.json schema plus the per-node $ref
 * schemas validate the people fixture, and streams:schema outputs them.
 */
class AppSchemaTest extends SdkTestCase
{
    protected string $schemaDir = __DIR__ . '/../../resources/schemas';

    protected string $temp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temp = sys_get_temp_dir() . '/streams-sdk-schema-' . uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temp);

        parent::tearDown();
    }

    public function test_the_people_fixture_validates_against_the_app_schema(): void
    {
        $validator = new SchemaValidator($this->schemaDir);

        foreach ($this->fixtureFiles() as $file) {
            $data = json_decode((string) file_get_contents($file));

            $this->assertIsObject($data, "{$file} is a JSON object.");

            $schemaId = $data->{'$schema'} ?? null;

            $this->assertIsString($schemaId, "{$file} declares a \$schema.");

            $validator->assertValid($data, $schemaId, basename($file));
        }
    }

    public function test_the_app_schema_covers_every_node_type(): void
    {
        $schema = json_decode((string) file_get_contents($this->schemaDir . '/app.schema.json'));

        $this->assertSame('https://streams.dev/schema/app.schema.json', $schema->{'$id'});

        foreach (glob($this->schemaDir . '/nodes/*.schema.json') ?: [] as $file) {
            $node = json_decode((string) file_get_contents($file));

            $this->assertSame(
                'https://streams.dev/schema/node/' . basename($file),
                $node->{'$id'},
                "{$file}: \$id must match its URL."
            );
        }

        foreach (['stream', 'panel', 'page', 'public-page', 'table', 'column', 'action', 'form', 'section'] as $type) {
            $this->assertFileExists($this->schemaDir . '/nodes/' . $type . '.schema.json', "node schema for [{$type}].");
        }
    }

    public function test_streams_schema_outputs_the_app_definition_schema(): void
    {
        $out = $this->temp . '/out';

        $this->artisan('streams:schema', ['--path' => $out])->assertSuccessful();

        $this->assertFileExists($out . '/schemas/app.schema.json');

        $app = json_decode((string) File::get($out . '/schemas/app.schema.json'));

        $this->assertSame('https://streams.dev/schema/app.schema.json', $app->{'$id'});

        $this->assertFileExists($out . '/schemas/nodes/table.schema.json');
        $this->assertFileExists($out . '/schemas/nodes/action.schema.json');

        // The written schema still validates the fixture.
        $validator = new SchemaValidator($out . '/schemas');

        foreach ($this->fixtureFiles() as $file) {
            $data = json_decode((string) file_get_contents($file));

            $validator->assertValid($data, $data->{'$schema'}, basename($file));
        }
    }

    /**
     * @return array<int, string>
     */
    protected function fixtureFiles(): array
    {
        $files = glob($this->schemaDir . '/fixtures/people/streams.json') ?: [];

        foreach (glob($this->schemaDir . '/fixtures/people/streams/*.json') ?: [] as $file) {
            $files[] = $file;
        }

        return $files;
    }
}
