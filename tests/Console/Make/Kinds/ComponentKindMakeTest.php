<?php

namespace Streams\Sdk\Tests\Console\Make\Kinds;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Streams\Sdk\Console\Make\Kinds\InputField;
use Streams\Sdk\Console\Make\Kinds\Kind;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * A custom kind is reached through streams:make. Registering it does not
 * add a make:* command. php artisan list shows streams:make.
 */
class ComponentKindMakeTest extends SdkTestCase
{
    protected string $fixture;

    protected string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = dirname(__DIR__, 4).'/resources/schemas/fixtures/people/streams.json';
        $this->output = sys_get_temp_dir().'/streams-sdk-kinds-'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->output)) {
            File::deleteDirectory($this->output);
        }

        parent::tearDown();
    }

    public function test_streams_make_reaches_the_people_kinds(): void
    {
        $payload = $this->makeJson(['path' => 'people']);

        $this->assertSame('stream', $payload['kinds']['people']['kind']);
        $this->assertSame('sdk', $payload['kinds']['people']['source']);
        $this->assertSame([], $payload['kinds']['people']['errors']);

        $this->assertSame('page', $payload['kinds']['people.pages.manage']['kind']);
        $this->assertSame('table', $payload['kinds']['people.table']['kind']);
        $this->assertSame('action', $payload['kinds']['people.actions.add']['kind']);
        $this->assertSame([], $payload['kinds']['people.actions.add']['errors']);
        $this->assertArrayNotHasKey('make:page', Artisan::all());
    }

    public function test_a_custom_kind_is_reached_through_streams_make_without_a_new_command(): void
    {
        $before = array_keys(Artisan::all());

        app(KindRegistry::class)->registerFromPackage($this->badgeKind(), 'example/badges');

        $this->assertSame($before, array_keys(Artisan::all()));
        $this->assertNotContains('make:badge', array_keys(Artisan::all()));

        $root = sys_get_temp_dir().'/streams-sdk-badge-'.uniqid();
        File::ensureDirectoryExists($root);

        File::put($root.'/streams.json', json_encode([
            'name' => 'People',
            'streams' => [
                'people' => [
                    'name' => 'People',
                    'handle' => 'people',
                    'fields' => [],
                    'ui' => [
                        'badges' => [
                            'star' => [
                                'handle' => 'star',
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $payload = $this->makeJson([
            'path' => 'people.badges.star',
            '--tree' => $root.'/streams.json',
        ]);

        $this->assertSame('badge', $payload['kinds']['people.badges.star']['kind']);
        $this->assertSame('package', $payload['kinds']['people.badges.star']['source']);
        $this->assertSame('example/badges', $payload['kinds']['people.badges.star']['package']);
        $this->assertSame('label', $payload['kinds']['people.badges.star']['errors'][0]['field']);
        $this->assertStringContainsString('label', $payload['kinds']['people.badges.star']['errors'][0]['message']);

        $after = array_keys(Artisan::all());
        sort($before);
        sort($after);

        $this->assertSame($before, $after);
        $this->assertNotContains('make:badge', $after);

        File::deleteDirectory($root);
    }

    public function test_artisan_list_shows_streams_make_and_no_component_kind_commands(): void
    {
        app(KindRegistry::class)->registerFromApp(Kind::define('widget', 'An app widget.', [
            InputField::string('handle')->required(),
        ]));
        app(KindRegistry::class)->registerFromPackage($this->badgeKind(), 'example/badges');

        $exit = Artisan::call('list');
        $list = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('streams:make', $list);

        $names = array_keys(Artisan::all());
        $this->assertContains('streams:make', $names);

        foreach (['make:page', 'make:action', 'make:form', 'make:manage', 'make:badge', 'make:widget'] as $command) {
            $this->assertNotContains($command, $names);
            $this->assertStringNotContainsString($command, $list);
        }

        foreach (Artisan::all() as $name => $command) {
            if (! str_starts_with($command::class, 'Streams\\Sdk\\')) {
                continue;
            }

            $this->assertFalse(
                str_starts_with($name, 'make:'),
                'streams-sdk registered ['.$name.'] from '.$command::class.'.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function makeJson(array $extra = []): array
    {
        $code = Artisan::call('streams:make', array_merge([
            '--tree' => $this->fixture,
            '--output' => $this->output,
            '--json' => true,
            '--dry-run' => true,
        ], $extra));

        $output = Artisan::output();

        $this->assertSame(0, $code, $output);

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded, $output);
        $this->assertArrayHasKey('kinds', $decoded);

        return $decoded;
    }

    protected function badgeKind(): Kind
    {
        return Kind::define('badge', 'A package badge.', [
            InputField::string('handle')->required()->prompt('Badge handle')->flag('handle'),
            InputField::string('label')->required()->prompt('Badge label')->flag('label'),
        ])->placedOn('ui', 'badges', map: true, mapOf: 'badge');
    }
}
